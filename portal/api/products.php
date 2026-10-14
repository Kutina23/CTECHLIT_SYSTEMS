<?php
/**
 * Products API
 * CRUD operations for products table + stock adjustments
 */

require_once __DIR__ . '/common.php';

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Check that all given fields are present and non-empty.
 */
function requireProductFields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            errorResponse("Field '$field' is required");
        }
    }
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['movements'])) {
                $productId = (int) ($_GET['product_id'] ?? 0);
                if ($productId <= 0) {
                    errorResponse('product_id is required to read stock movements');
                }
                successResponse(productStockMovements($productId, (int) ($_GET['limit'] ?? 20)));
            } elseif (isset($_GET['low_stock'])) {
                $products = Database::fetchAll(
                    "SELECT product_id, name, category, stock_quantity, min_stock_level
                     FROM products
                     WHERE is_active = 1 AND stock_quantity <= min_stock_level
                     ORDER BY stock_quantity ASC, name ASC"
                );
                successResponse($products);
            } elseif (isset($_GET['list'])) {
                $products = Database::fetchAll(
                    "SELECT product_id as id, name, selling_price as price FROM products WHERE is_active = 1 ORDER BY name"
                );
                successResponse($products);
            } elseif (isset($_GET['product_id'])) {
                $product = Database::fetchOne("SELECT * FROM products WHERE product_id = ?", [$_GET['product_id']]);
                if (!$product) errorResponse('Product not found', 404);
                successResponse($product);
            } else {
                $products = Database::fetchAll("SELECT * FROM products ORDER BY created_at DESC");
                successResponse($products);
            }
            break;

        case 'POST':
            // Adjust stock (restock, removal, waste, correction)
            if (($input['action'] ?? '') === 'adjust_stock') {
                requirePermission('inventory.adjust');
                requireProductFields($input, ['product_id', 'quantity', 'movement_type']);

                $productId = (int) $input['product_id'];
                $quantity = (int) $input['quantity'];
                $movementType = (string) $input['movement_type'];
                $notes = isset($input['notes']) ? trim((string) $input['notes']) : '';

                $validTypes = ['purchase', 'sale', 'adjustment', 'transfer', 'waste'];
                if (!in_array($movementType, $validTypes, true)) {
                    errorResponse('Invalid movement type');
                }

                if ($quantity === 0) {
                    errorResponse('Quantity cannot be zero');
                }

                if ($movementType === 'purchase' && $quantity < 0) {
                    errorResponse('A restock must use a positive quantity');
                }

                if (in_array($movementType, ['sale', 'waste'], true) && $quantity > 0) {
                    errorResponse('Sales and waste must be recorded as a negative quantity');
                }

                if ($movementType === 'adjustment' && $notes === '') {
                    errorResponse('A reason is required when correcting stock counts');
                }

                $product = Database::fetchOne(
                    "SELECT product_id, name, stock_quantity FROM products WHERE product_id = ?",
                    [$productId]
                );
                if (!$product) {
                    errorResponse('Product not found', 404);
                }

                $currentStock = (int) $product['stock_quantity'];
                $newStock = $currentStock + $quantity;

                if ($newStock < 0) {
                    errorResponse(sprintf(
                        'Cannot remove %d units — only %d in stock',
                        abs($quantity),
                        $currentStock
                    ), 400);
                }

                $staffId = currentStaffId();

                Database::beginTransaction();
                try {
                    Database::execute(
                        "UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE product_id = ?",
                        [$newStock, $productId]
                    );

                    Database::insert(
                        "INSERT INTO inventory_movements (product_id, movement_type, quantity, reference_type, notes, created_by)
                         VALUES (?, ?, ?, 'adjustment', ?, ?)",
                        [$productId, $movementType, $quantity, $notes !== '' ? $notes : null, $staffId ?: null]
                    );

                    Database::commit();
                } catch (Exception $e) {
                    Database::rollback();
                    throw $e;
                }

                logAuditTrail(
                    'inventory_adjust',
                    'product',
                    $productId,
                    [
                        'product_name' => $product['name'],
                        'movement_type' => $movementType,
                        'quantity' => $quantity,
                        'stock_before' => $currentStock,
                        'stock_after' => $newStock,
                        'notes' => $notes,
                    ],
                    $staffId ?: null,
                    'staff'
                );

                $updated = Database::fetchOne("SELECT * FROM products WHERE product_id = ?", [$productId]);
                $updated['stock_before'] = $currentStock;
                successResponse($updated, 'Stock adjusted successfully');
            }

            requirePermission('inventory.manage');
            requireProductFields($input, ['name', 'category', 'cost_price', 'selling_price', 'stock_quantity', 'min_stock_level']);

            $initialStock = (int) $input['stock_quantity'];
            $staffId = currentStaffId();

            Database::beginTransaction();
            try {
                $productId = Database::insert(
                    "INSERT INTO products (name, category, sku, barcode, description, cost_price, selling_price, stock_quantity, min_stock_level, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $input['name'],
                        $input['category'],
                        $input['sku'] ?? null,
                        $input['barcode'] ?? null,
                        $input['description'] ?? null,
                        $input['cost_price'],
                        $input['selling_price'],
                        $initialStock,
                        $input['min_stock_level'],
                        $input['is_active'] ?? 1,
                    ]
                );

                if ($initialStock !== 0) {
                    Database::insert(
                        "INSERT INTO inventory_movements (product_id, movement_type, quantity, reference_type, notes, created_by)
                         VALUES (?, 'purchase', ?, 'adjustment', 'Opening stock', ?)",
                        [$productId, $initialStock, $staffId ?: null]
                    );
                }

                Database::commit();
            } catch (Exception $e) {
                Database::rollback();
                throw $e;
            }

            $product = Database::fetchOne("SELECT * FROM products WHERE product_id = ?", [$productId]);
            successResponse($product, 'Product created successfully');
            break;

        case 'PUT':
            requirePermission('inventory.manage');
            requireProductFields($input, ['product_id']);

            $updates = [];
            $params = [];

            $allowedFields = ['name', 'category', 'sku', 'barcode', 'description', 'cost_price', 'selling_price', 'min_stock_level', 'is_active'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }

            // Stock is only changed through adjust_stock so movements stay traceable
            if (array_key_exists('stock_quantity', $input)) {
                $stockChange = (int) $input['stock_quantity'] - (int) (Database::fetchColumn(
                    "SELECT stock_quantity FROM products WHERE product_id = ?",
                    [$input['product_id']]
                ) ?: 0);
                if ($stockChange !== 0) {
                    errorResponse('Use the stock adjustment action to change quantities', 400);
                }
            }

            if (empty($updates)) errorResponse('No fields to update');

            $params[] = $input['product_id'];
            Database::execute("UPDATE products SET " . implode(', ', $updates) . " WHERE product_id = ?", $params);

            $product = Database::fetchOne("SELECT * FROM products WHERE product_id = ?", [$input['product_id']]);
            successResponse($product, 'Product updated successfully');
            break;

        case 'DELETE':
            requirePermission('inventory.manage');
            requireProductFields($input, ['product_id']);
            Database::execute("DELETE FROM products WHERE product_id = ?", [$input['product_id']]);
            successResponse(null, 'Product deleted successfully');
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}
