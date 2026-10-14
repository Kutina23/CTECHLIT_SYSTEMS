<?php
/**
 * Reusable Modal Components for CRUD Operations
 * Include this file in portal pages to use modals
 */

// Entity configurations for modals
$modalConfigs = [
    'customer' => [
        'title' => 'Customer',
        'fields' => [
            ['name' => 'full_name', 'label' => 'Full Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Enter full name'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false, 'requiredOnCreate' => true, 'placeholder' => 'email@example.com'],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => false, 'requiredOnCreate' => true, 'placeholder' => '+233 24 123 4567'],
            ['name' => 'membership_tier', 'label' => 'Membership Tier', 'type' => 'select', 'required' => true, 'options' => [
                ['value' => 'standard', 'label' => 'Standard'],
                ['value' => 'premium', 'label' => 'Premium'],
                ['value' => 'vip', 'label' => 'VIP'],
            ]],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'required' => false],
        ],
        'api' => 'api/customers.php',
        'table' => 'users',
        'idField' => 'user_id',
    ],
    'product' => [
        'title' => 'Product',
        'fields' => [
            ['name' => 'name', 'label' => 'Product Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Enter product name'],
            ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'required' => true, 'options' => [
                ['value' => 'beverage', 'label' => 'Beverage'],
                ['value' => 'snack', 'label' => 'Snack'],
                ['value' => 'merchandise', 'label' => 'Merchandise'],
                ['value' => 'accessory', 'label' => 'Accessory'],
                ['value' => 'other', 'label' => 'Other'],
            ]],
            ['name' => 'sku', 'label' => 'SKU', 'type' => 'text', 'required' => false, 'placeholder' => 'Optional SKU'],
            ['name' => 'barcode', 'label' => 'Barcode', 'type' => 'text', 'required' => false, 'placeholder' => 'Optional barcode'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false, 'placeholder' => 'Product description'],
            ['name' => 'cost_price', 'label' => 'Cost Price', 'type' => 'number', 'required' => true, 'step' => '0.01', 'min' => '0', 'placeholder' => '0.00'],
            ['name' => 'selling_price', 'label' => 'Selling Price', 'type' => 'number', 'required' => true, 'step' => '0.01', 'min' => '0', 'placeholder' => '0.00'],
            ['name' => 'stock_quantity', 'label' => 'Stock Quantity', 'type' => 'number', 'required' => true, 'min' => '0', 'placeholder' => '0'],
            ['name' => 'min_stock_level', 'label' => 'Min Stock Level', 'type' => 'number', 'required' => true, 'min' => '0', 'placeholder' => '5'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'required' => false],
        ],
        'api' => 'api/products.php',
        'table' => 'products',
        'idField' => 'product_id',
    ],
    'service' => [
        'title' => 'Service',
        'fields' => [
            ['name' => 'name', 'label' => 'Service Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Enter service name'],
            ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'required' => true, 'options' => [
                ['value' => 'internet', 'label' => 'Internet'],
                ['value' => 'printing', 'label' => 'Printing'],
                ['value' => 'scanning', 'label' => 'Scanning'],
                ['value' => 'gaming', 'label' => 'Gaming'],
                ['value' => 'typing', 'label' => 'Typing'],
                ['value' => 'mobile_money', 'label' => 'Mobile Money'],
                ['value' => 'consultation', 'label' => 'Consultation'],
                ['value' => 'other', 'label' => 'Other'],
            ]],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false, 'placeholder' => 'Service description'],
            ['name' => 'base_price', 'label' => 'Base Price', 'type' => 'number', 'required' => true, 'step' => '0.01', 'min' => '0', 'placeholder' => '0.00'],
            ['name' => 'price_unit', 'label' => 'Price Unit', 'type' => 'select', 'required' => true, 'options' => [
                ['value' => 'per_hour', 'label' => 'Per Hour'],
                ['value' => 'per_minute', 'label' => 'Per Minute'],
                ['value' => 'per_page', 'label' => 'Per Page'],
                ['value' => 'per_job', 'label' => 'Per Job'],
                ['value' => 'fixed', 'label' => 'Fixed'],
            ]],
            ['name' => 'requires_station', 'label' => 'Requires Station', 'type' => 'checkbox', 'required' => false],
            ['name' => 'estimated_duration', 'label' => 'Estimated Duration (minutes)', 'type' => 'number', 'required' => false, 'min' => '0', 'placeholder' => '0'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'required' => false],
        ],
        'api' => 'api/services.php',
        'table' => 'services',
        'idField' => 'service_id',
    ],
    'station' => [
        'title' => 'Station',
        'fields' => [
            ['name' => 'station_code', 'label' => 'Station Code', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., ST-01'],
            ['name' => 'name', 'label' => 'Station Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Enter station name'],
            ['name' => 'location', 'label' => 'Location', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Zone A, Gaming Corner'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => [
                ['value' => 'available', 'label' => 'Available'],
                ['value' => 'occupied', 'label' => 'Occupied'],
                ['value' => 'maintenance', 'label' => 'Maintenance'],
                ['value' => 'offline', 'label' => 'Offline'],
            ]],
            ['name' => 'specs', 'label' => 'Specifications (JSON)', 'type' => 'textarea', 'required' => false, 'placeholder' => '{"cpu":"Intel i5","ram":"16GB","gpu":"Integrated","os":"Windows 11"}'],
            ['name' => 'ip_address', 'label' => 'IP Address', 'type' => 'text', 'required' => false, 'placeholder' => '192.168.1.101'],
            ['name' => 'mac_address', 'label' => 'MAC Address', 'type' => 'text', 'required' => false, 'placeholder' => 'AA:BB:CC:DD:EE:FF'],
        ],
        'api' => 'api/stations.php',
        'table' => 'stations',
        'idField' => 'station_id',
    ],
    'staff' => [
        'title' => 'Staff Member',
        'fields' => [
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'placeholder' => 'staff@example.com'],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'placeholder' => '+233 24 123 4567'],
            ['name' => 'full_name', 'label' => 'Full Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Enter full name'],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => false, 'placeholder' => 'Leave blank to keep current'],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'required' => true, 'options' => [
                ['value' => 'admin', 'label' => 'Admin'],
                ['value' => 'manager', 'label' => 'Manager'],
                ['value' => 'attendant', 'label' => 'Attendant'],
                ['value' => 'technician', 'label' => 'Technician'],
            ]],
            ['name' => 'station_id', 'label' => 'Assigned Station', 'type' => 'select', 'required' => false, 'options' => [], 'ajax' => 'api/stations.php?list=1'],
            ['name' => 'hired_at', 'label' => 'Hire Date', 'type' => 'date', 'required' => true],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'required' => false],
        ],
        'api' => 'api/staff.php',
        'table' => 'staff',
        'idField' => 'staff_id',
    ],
    'session' => [
        'title' => 'Session',
        'fields' => [
            ['name' => 'user_id', 'label' => 'User', 'type' => 'select', 'required' => true, 'options' => [], 'ajax' => 'api/users.php?list=1'],
            ['name' => 'station_id', 'label' => 'Station', 'type' => 'select', 'required' => true, 'options' => [], 'ajax' => 'api/stations.php?list=1&status=available'],
            ['name' => 'staff_id', 'label' => 'Attendant', 'type' => 'select', 'required' => false, 'options' => [], 'ajax' => 'api/staff.php?list=1&role=attendant'],
        ],
        'api' => 'api/sessions.php',
        'table' => 'sessions',
        'idField' => 'session_id',
    ],
];
?>

<!-- Base Modal Template -->
<div class="modal-overlay" id="modalOverlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal" id="modal" role="document" tabindex="-1">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Modal Title</h3>
            <button class="modal-close" id="modalClose" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Modal content injected here -->
        </div>
        <div class="modal-footer" id="modalFooter">
            <button class="btn btn-secondary" id="modalCancel">Cancel</button>
            <button class="btn btn-primary" id="modalConfirm">Confirm</button>
        </div>
    </div>
</div>

<!-- Confirmation Modal Template -->
<div class="modal-overlay modal-overlay-confirm" id="confirmModalOverlay" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <div class="modal modal-sm modal-confirm" id="confirmModal" role="document" tabindex="-1">
        <div class="modal-header">
            <h3 class="modal-title" id="confirmModalTitle">Confirm Action</h3>
            <button class="modal-close" id="confirmModalClose" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="confirmModalBody">
            <div class="modal-icon warning" id="confirmModalIcon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p class="modal-message" id="confirmModalMessage">Are you sure you want to proceed?</p>
            <p class="modal-detail" id="confirmModalDetail"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="confirmModalCancel">Cancel</button>
            <button class="btn btn-danger" id="confirmModalConfirm">Delete</button>
        </div>
    </div>
</div>

<!-- View Detail Modal Template -->
<div class="modal-overlay" id="viewModalOverlay" role="dialog" aria-modal="true" aria-labelledby="viewModalTitle">
    <div class="modal modal-lg" id="viewModal" role="document" tabindex="-1">
        <div class="modal-header">
            <h3 class="modal-title" id="viewModalTitle">Details</h3>
            <button class="modal-close" id="viewModalClose" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="viewModalBody">
            <!-- View content injected here -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="viewModalCloseBtn">Close</button>
        </div>
    </div>
</div>