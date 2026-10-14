<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Customers
 * View and manage customers
 */

require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission('users.view');

$currentUser = currentStaff() ?: currentUser();
$canCreateCustomers = hasPermission('users.create');
$canEditCustomers = hasPermission('users.edit');
$canDeleteCustomers = hasPermission('users.delete');
$canCreateCards = hasPermission('cards.create');
$customerStats = portalSummaryStats();
$customers = portalCustomers();
$canTopUp = hasPermission('billing.manage') || hasPermission('pos.manage');
$canViewBilling = hasPermission('billing.view');
$walletByUser = [];
if (Database::tableExists('billing_accounts')) {
  require_once __DIR__ . '/../includes/billing.php';
  foreach (Database::fetchAll("SELECT user_id, balance FROM billing_accounts") as $row) {
    $walletByUser[(int) $row['user_id']]['balance'] = (float) $row['balance'];
  }
  foreach (Database::fetchAll("SELECT user_id, SUM(total_amount) AS owed FROM transactions WHERE status = 'pending' GROUP BY user_id") as $row) {
    $walletByUser[(int) $row['user_id']]['owed'] = (float) $row['owed'];
  }
}
$cardBrandName = siteSetting('business_name', 'CTECHLIT & SYSTEMS');
$loyaltyCardCustomers = array_map(static function (array $customer): array {
  return [
    'id' => (int) ($customer['user_id'] ?? 0),
    'name' => (string) ($customer['full_name'] ?? 'Customer'),
    'tier' => (string) ($customer['membership_tier'] ?? 'standard'),
  ];
}, $customers);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customers — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <!-- Main Content -->
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <!-- Header -->
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <!-- Dashboard Content -->
    <div class="portal-content">
      <!-- Customer Stats -->
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-users"></i>
            </div>
            <div class="kpi-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>12.5%</span>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($customerStats['total_customers']); ?></div>
          <div class="kpi-label">Total Customers</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-user-plus"></i>
            </div>
            <div class="kpi-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>Month</span>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($customerStats['new_customers']); ?></div>
          <div class="kpi-label">New This Month</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-redo"></i>
            </div>
            <div class="kpi-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>Live</span>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($customerStats['conversion_rate'], 2); ?>%</div>
          <div class="kpi-label">Retention Rate</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-star"></i>
            </div>
            <div class="kpi-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>Avg</span>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format((float) ($customerStats['avg_order_value'] ?? 0), 2); ?></div>
          <div class="kpi-label">Avg Order</div>
        </div>
      </div>

      <!-- Customers Table -->
      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title">All Customers</div>
            <div class="card-subtitle"><?php echo number_format($customerStats['total_customers']); ?> total customers</div>
          </div>
          <div class="customer-header-actions">
            <?php if ($canCreateCards): ?>
            <button class="btn btn-primary" id="loyaltyCardOpen" type="button">
              <i class="fas fa-id-card"></i> Create Loyalty Card
            </button>
            <?php endif; ?>
            <?php if ($canCreateCustomers): ?>
            <button class="btn btn-primary" id="addCustomerBtn" style="padding: 10px 16px; font-size: 13px;">
              <i class="fas fa-plus"></i> Add Customer
            </button>
            <?php endif; ?>
          </div>
        </div>

        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table" id="customersTable">
            <thead>
              <tr>
                <th>Customer</th>
                <th>Email</th>
                <th>Total Spent</th>
                <th>Wallet</th>
                <th>Orders</th>
                <th>Last Visit</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($customers as $customer): ?>
              <tr data-id="<?php echo (int) $customer['user_id']; ?>" data-customer='<?php echo json_encode($customer, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>
                <td>
                  <div class="table-product">
                    <div class="user-avatar" style="width: 36px; height: 36px; font-size: 13px;"><?php echo strtoupper(substr((string) ($customer['full_name'] ?? 'U'), 0, 2)); ?></div>
                    <div class="table-product-info">
                      <span class="table-product-name"><?php echo sanitize($customer['full_name'] ?? 'Customer'); ?></span>
                      <?php
                        $membershipTier = trim((string) ($customer['membership_tier'] ?? 'standard'));
                        $membershipTierLabel = ucwords(str_replace(['_', '-'], ' ', $membershipTier !== '' ? $membershipTier : 'standard'));
                      ?>
                      <span class="customer-tier-badge tier-<?php echo sanitize(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $membershipTier) ?: 'standard')); ?>">
                        <i class="fas fa-award" aria-hidden="true"></i>
                        <?php echo sanitize($membershipTierLabel); ?>
                      </span>
                    </div>
                  </div>
                </td>
                <td><?php echo sanitize($customer['email'] ?? ''); ?></td>
                <td style="font-weight: 600; color: var(--green);">GHS <?php echo number_format((float) ($customer['total_spent'] ?? 0), 2); ?></td>
                <?php $wallet = $walletByUser[(int) $customer['user_id']] ?? []; ?>
                <td>
                  <span style="font-weight: 600;">GHS <?php echo number_format((float) ($wallet['balance'] ?? 0), 2); ?></span>
                  <?php if (!empty($wallet['owed'])): ?><br><span style="font-size: 11px; color: var(--red);">Owes GHS <?php echo number_format($wallet['owed'], 2); ?></span><?php endif; ?>
                </td>
                <td><?php echo (int) ($customer['order_count'] ?? 0); ?></td>
                <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo sanitize(relativeTime($customer['last_login_at'] ?? date('Y-m-d H:i:s'))); ?></td>
                <td><span class="status-badge <?php echo ((int)($customer['is_active'] ?? 1) === 1 ? 'success' : 'error'); ?>"><span class="status-dot"></span><?php echo ((int)($customer['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive'); ?></span></td>
                <td>
                  <div class="action-buttons" style="display: flex; gap: 6px;">
                    <button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button>
                    <?php if ($canTopUp): ?>
                    <button class="header-btn" title="Top up wallet" data-action="topup" data-owed="<?php echo (float) ($wallet['owed'] ?? 0); ?>" style="color: var(--violet);"><i class="fas fa-wallet"></i></button>
                    <?php endif; ?>
                    <?php if ($canViewBilling): ?>
                    <a class="header-btn" title="Billing account" href="billing.php?user_id=<?php echo (int) $customer['user_id']; ?>" style="color: var(--cyan); display: inline-grid; place-items: center;"><i class="fas fa-file-invoice-dollar"></i></a>
                    <?php endif; ?>
                    <?php if ($canEditCustomers): ?>
                    <button class="header-btn edit-btn" title="Edit" data-action="edit"><i class="fas fa-edit"></i></button>
                    <?php endif; ?>
                    <?php if ($canDeleteCustomers): ?>
                    <button class="header-btn delete-btn" title="Delete" data-action="delete" style="color: var(--red);"><i class="fas fa-trash"></i></button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

  <div class="loyalty-modal" id="loyaltyCardModal" hidden>
    <div class="loyalty-modal-backdrop" data-loyalty-close></div>
    <section class="loyalty-modal-panel" role="dialog" aria-modal="true" aria-labelledby="loyaltyCardTitle">
      <div class="loyalty-modal-heading">
        <div>
          <span class="loyalty-eyebrow">RECOGNIZE YOUR REGULARS</span>
          <h2 id="loyaltyCardTitle">Create a loyalty card</h2>
          <p>Personalize a branded membership card, ready to share or print.</p>
        </div>
        <button class="loyalty-close" type="button" aria-label="Close card generator" data-loyalty-close><i class="fas fa-times"></i></button>
      </div>
      <div class="loyalty-generator">
        <div class="loyalty-controls">
          <label class="loyalty-field" for="loyaltyCustomer">
            <span>Choose customer</span>
            <select id="loyaltyCustomer" <?php echo $loyaltyCardCustomers ? '' : 'disabled'; ?>>
              <?php foreach ($loyaltyCardCustomers as $customer): ?>
              <option value="<?php echo (int) $customer['id']; ?>"><?php echo sanitize($customer['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="loyalty-field" for="loyaltyDesign">
            <span>Card design</span>
            <select id="loyaltyDesign">
              <option value="aurora">Aurora glass</option>
              <option value="emerald">Emerald club</option>
              <option value="royal">Royal purple</option>
              <option value="classic">Modern classic</option>
            </select>
          </label>
          <label class="loyalty-field" for="loyaltyIssueDate">
            <span>Issue date</span>
            <input type="date" id="loyaltyIssueDate" class="loyalty-field-input">
          </label>
          <label class="loyalty-field" for="loyaltyExpiryDate">
            <span>Expiry date</span>
            <input type="date" id="loyaltyExpiryDate" class="loyalty-field-input">
          </label>
          <div class="loyalty-tier-note">
            <span class="loyalty-tier-icon"><i class="fas fa-award"></i></span>
            <span><strong>Membership tier</strong><small id="loyaltyTierHint">Select a customer to see their tier</small></span>
          </div>
          <p class="loyalty-help">Your preview is the same artwork that will be saved in the PNG file.</p>
          <button class="btn btn-primary loyalty-download" id="loyaltyCardDownload" type="button" <?php echo $loyaltyCardCustomers ? '' : 'disabled'; ?>>
            <i class="fas fa-download"></i> Download PNG
          </button>
          <div class="loyalty-feedback" id="loyaltyFeedback" role="status" aria-live="polite"></div>
        </div>
        <div class="loyalty-preview-wrap">
          <div class="loyalty-preview-topline"><span><i class="fas fa-sparkles"></i> LIVE PREVIEW</span><span>Print-ready · 1050 × 660 px</span></div>
          <canvas id="loyaltyCardCanvas" width="1050" height="660" aria-label="Preview of the loyalty membership card"></canvas>
          <div class="loyalty-preview-caption"><i class="fas fa-shield-alt"></i> Official member card from <?php echo sanitize($cardBrandName); ?></div>
        </div>
      </div>
    </section>
  </div>

  <?php if ($canCreateCustomers): ?>
  <!-- Floating Action Button -->
  <button class="fab" id="fabAddCustomer" aria-label="Add customer">
  <i class="fas fa-plus"></i>
</button>
<?php endif; ?>

<!-- Include Modals -->
<?php include_once __DIR__ . '/../includes/modals.php'; ?>

<script src="../assets/js/modal-controller.js"></script>
<script>
  window.LOYALTY_CARD_CUSTOMERS = <?php echo json_encode($loyaltyCardCustomers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  window.LOYALTY_CARD_BRAND = <?php echo json_encode($cardBrandName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

  // Initialize Modal Configs
  window.MODAL_CONFIGS = {
    customer: {
      title: 'Customer',
      fields: [
        {name: 'full_name', label: 'Full Name', type: 'text', required: true, placeholder: 'Enter full name'},
        {name: 'email', label: 'Email', type: 'email', required: false, requiredOnCreate: true, placeholder: 'email@example.com'},
        {name: 'phone', label: 'Phone', type: 'tel', required: false, requiredOnCreate: true, placeholder: '+233 24 123 4567'},
        {name: 'membership_tier', label: 'Membership Tier', type: 'select', required: true, options: [
          {value: 'standard', label: 'Standard'},
          {value: 'premium', label: 'Premium'},
          {value: 'vip', label: 'VIP'},
        ]},
        {name: 'is_active', label: 'Active', type: 'checkbox', required: false},
      ],
      api: 'api/customers.php',
      table: 'users',
      idField: 'user_id',
    }
  };

  // Sidebar Toggle
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    sidebar.classList.add('open');
    sidebarOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      closeSidebar();
    }
  });

  // Table action handlers
  document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('customersTable');
    const addBtn = document.getElementById('addCustomerBtn');
    const fabBtn = document.getElementById('fabAddCustomer');
    const loyaltyModal = document.getElementById('loyaltyCardModal');
    const loyaltyOpenBtn = document.getElementById('loyaltyCardOpen');
    const loyaltyCustomerSelect = document.getElementById('loyaltyCustomer');
    const loyaltyDesignSelect = document.getElementById('loyaltyDesign');
    const loyaltyIssueDate = document.getElementById('loyaltyIssueDate');
    const loyaltyExpiryDate = document.getElementById('loyaltyExpiryDate');
    const loyaltyTierHint = document.getElementById('loyaltyTierHint');
    const loyaltyDownloadBtn = document.getElementById('loyaltyCardDownload');
    const loyaltyFeedback = document.getElementById('loyaltyFeedback');
    const loyaltyCanvas = document.getElementById('loyaltyCardCanvas');
    const loyaltyContext = loyaltyCanvas.getContext('2d');
    const loyaltyCustomers = window.LOYALTY_CARD_CUSTOMERS || [];
    const loyaltyBrand = window.LOYALTY_CARD_BRAND || 'CTECHLIT & SYSTEMS';
    const loyaltyLogo = new Image();
    let lastLoyaltyFocus = null;
    let loyaltyLogoFailed = false;

    const loyaltyDesigns = {
      aurora: {
        background: ['#10172f', '#342b70', '#12647a'],
        accent: '#5ee7f2',
        secondary: '#b59aff',
        text: '#ffffff',
        muted: 'rgba(238, 242, 255, 0.72)',
        footer: 'rgba(5, 12, 31, 0.42)',
      },
      emerald: {
        background: ['#082b2d', '#0a5a52', '#127c69'],
        accent: '#f5d785',
        secondary: '#7ee7bf',
        text: '#ffffff',
        muted: 'rgba(235, 255, 247, 0.76)',
        footer: 'rgba(3, 25, 26, 0.42)',
      },
      royal: {
        background: ['#21163e', '#4c2775', '#8b3d78'],
        accent: '#ffc2a7',
        secondary: '#e7b5ff',
        text: '#ffffff',
        muted: 'rgba(255, 241, 255, 0.76)',
        footer: 'rgba(25, 12, 43, 0.42)',
      },
      classic: {
        background: ['#f7f8ff', '#e7edff', '#dff8f7'],
        accent: '#6b45d8',
        secondary: '#00aab8',
        text: '#202544',
        muted: 'rgba(32, 37, 68, 0.66)',
        footer: 'rgba(255, 255, 255, 0.65)',
      },
    };

    function roundedPath(context, x, y, width, height, radius) {
      const r = Math.min(radius, width / 2, height / 2);
      context.beginPath();
      context.moveTo(x + r, y);
      context.lineTo(x + width - r, y);
      context.quadraticCurveTo(x + width, y, x + width, y + r);
      context.lineTo(x + width, y + height - r);
      context.quadraticCurveTo(x + width, y + height, x + width - r, y + height);
      context.lineTo(x + r, y + height);
      context.quadraticCurveTo(x, y + height, x, y + height - r);
      context.lineTo(x, y + r);
      context.quadraticCurveTo(x, y, x + r, y);
      context.closePath();
    }

    function fitCanvasText(context, text, maxWidth, startingSize, minimumSize, weight) {
      let size = startingSize;
      context.font = `${weight} ${size}px Inter, Arial, sans-serif`;
      while (context.measureText(text).width > maxWidth && size > minimumSize) {
        size -= 1;
        context.font = `${weight} ${size}px Inter, Arial, sans-serif`;
      }

      if (context.measureText(text).width <= maxWidth) {
        return text;
      }

      let shortened = text;
      while (shortened.length > 1 && context.measureText(`${shortened}…`).width > maxWidth) {
        shortened = shortened.slice(0, -1);
      }
      return `${shortened}…`;
    }

    function selectedLoyaltyCustomer() {
      if (!loyaltyCustomerSelect || !loyaltyCustomerSelect.value) return null;
      return loyaltyCustomers.find((customer) => String(customer.id) === loyaltyCustomerSelect.value) || null;
    }

    function membershipLabel(tier) {
      const normalized = String(tier || 'standard').trim().toLowerCase();
      const knownTiers = {standard: 'Standard', premium: 'Premium', vip: 'VIP'};
      return knownTiers[normalized] || normalized.replace(/[_-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
    }

    function drawLoyaltyCard() {
      const customer = selectedLoyaltyCustomer();
      const design = loyaltyDesigns[loyaltyDesignSelect.value] || loyaltyDesigns.aurora;
      const context = loyaltyContext;
      const width = loyaltyCanvas.width;
      const height = loyaltyCanvas.height;

      context.clearRect(0, 0, width, height);
      context.save();
      roundedPath(context, 0, 0, width, height, 48);
      context.clip();

      const background = context.createLinearGradient(0, 0, width, height);
      background.addColorStop(0, design.background[0]);
      background.addColorStop(0.58, design.background[1]);
      background.addColorStop(1, design.background[2]);
      context.fillStyle = background;
      context.fillRect(0, 0, width, height);

      const glow = context.createRadialGradient(850, 145, 20, 850, 145, 430);
      glow.addColorStop(0, design.secondary + '50');
      glow.addColorStop(1, design.secondary + '00');
      context.fillStyle = glow;
      context.fillRect(400, 0, 650, 510);

      context.globalAlpha = 0.13;
      context.fillStyle = design.secondary;
      context.beginPath();
      context.arc(1000, 70, 190, 0, Math.PI * 2);
      context.fill();
      context.beginPath();
      context.arc(940, 610, 255, 0, Math.PI * 2);
      context.fill();
      context.globalAlpha = 1;

      if (loyaltyLogo.complete && loyaltyLogo.naturalWidth > 0) {
        context.save();
        context.globalAlpha = design.text === '#ffffff' ? 0.13 : 0.09;
        context.drawImage(loyaltyLogo, 610, 180, 360, 360);
        context.restore();
      }

      context.strokeStyle = design.text === '#ffffff' ? 'rgba(255,255,255,0.16)' : 'rgba(36,48,93,0.12)';
      context.lineWidth = 2;
      roundedPath(context, 18, 18, width - 36, height - 36, 34);
      context.stroke();

      if (loyaltyLogo.complete && loyaltyLogo.naturalWidth > 0) {
        context.drawImage(loyaltyLogo, 70, 56, 66, 66);
      }

      context.fillStyle = design.text;
      const headerBrand = fitCanvasText(context, loyaltyBrand, 600, 27, 18, 700);
      context.fillText(headerBrand, 153, 87);
      context.fillStyle = design.muted;
      context.font = '500 12px Inter, Arial, sans-serif';
      context.letterSpacing = '2px';
      context.fillText('INTERNET CAFÉ & DIGITAL SERVICES', 155, 111);
      context.letterSpacing = '0px';

      context.textAlign = 'right';
      context.fillStyle = design.accent;
      context.font = '700 16px Inter, Arial, sans-serif';
      context.fillText('LOYALTY MEMBER', 970, 82);
      context.fillStyle = design.muted;
      context.font = '500 12px Inter, Arial, sans-serif';
      context.fillText('MEMBERSHIP CARD', 970, 106);
      context.textAlign = 'left';

      context.fillStyle = design.accent;
      context.font = '700 14px Inter, Arial, sans-serif';
      context.letterSpacing = '3px';
      context.fillText('YOUR VISITS. YOUR REWARDS.', 78, 204);
      context.letterSpacing = '0px';

      context.fillStyle = design.muted;
      context.font = '600 13px Inter, Arial, sans-serif';
      context.fillText('PROUDLY PRESENTED TO', 80, 275);
      context.fillStyle = design.text;
      const name = customer ? customer.name : 'Select a customer';
      const fittedName = fitCanvasText(context, name, 810, 58, 35, 700);
      context.fillText(fittedName, 78, 350);

      const tier = customer ? membershipLabel(customer.tier) : 'Member tier';
      context.font = '700 15px Inter, Arial, sans-serif';
      const badgeWidth = Math.max(150, context.measureText(tier.toUpperCase()).width + 58);
      roundedPath(context, 78, 392, badgeWidth, 48, 24);
      context.fillStyle = design.text === '#ffffff' ? 'rgba(255,255,255,0.15)' : 'rgba(71,55,148,0.1)';
      context.fill();
      context.strokeStyle = design.text === '#ffffff' ? 'rgba(255,255,255,0.28)' : 'rgba(71,55,148,0.2)';
      context.stroke();
      context.beginPath();
      context.arc(102, 416, 7, 0, Math.PI * 2);
      context.fillStyle = design.accent;
      context.fill();
      context.fillStyle = design.text;
      context.font = '700 15px Inter, Arial, sans-serif';
      context.fillText(tier.toUpperCase(), 120, 421);

      context.fillStyle = design.footer;
      context.fillRect(0, 520, width, 140);
      context.fillStyle = design.accent;
      context.fillRect(78, 520, 95, 4);
      context.fillStyle = design.text;
      const footerBrand = fitCanvasText(context, loyaltyBrand, 620, 21, 14, 700);
      context.fillText(footerBrand, 78, 574);

      const issueValue = loyaltyIssueDate ? loyaltyIssueDate.value : '';
      const expiryValue = loyaltyExpiryDate ? loyaltyExpiryDate.value : '';
      const issueFormatted = issueValue ? new Date(issueValue + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
      const expiryFormatted = expiryValue ? new Date(expiryValue + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
      if (issueFormatted || expiryFormatted) {
        context.fillStyle = design.muted;
        context.font = '500 12px Inter, Arial, sans-serif';
        context.fillText('VALID FROM: ' + (issueFormatted || '___'), 78, 600);
        context.fillText('EXPIRES: ' + (expiryFormatted || '___'), 78, 620);
      }
      context.textAlign = 'right';
      context.fillStyle = design.muted;
      context.font = '600 12px Inter, Arial, sans-serif';
      context.fillText('THANK YOU FOR BEING A REGULAR', 970, 582);
      context.fillStyle = design.accent;
      context.font = '700 12px Inter, Arial, sans-serif';
      context.fillText('MEMBER CARD  ·  ' + new Date().getFullYear(), 970, 605);
      context.textAlign = 'left';

      context.restore();
      loyaltyTierHint.textContent = customer ? tier : 'Select a customer to see their tier';
      loyaltyDownloadBtn.disabled = !customer || !loyaltyLogo.complete || loyaltyLogo.naturalWidth === 0;
      if (loyaltyLogoFailed) {
        setLoyaltyFeedback('The system logo could not be loaded, so the card cannot be exported.', true);
      } else if (!loyaltyLogo.complete || loyaltyLogo.naturalWidth === 0) {
        setLoyaltyFeedback('Loading system branding…', false);
      } else {
        setLoyaltyFeedback('', false);
      }
    }

    function setLoyaltyFeedback(message, isError) {
      loyaltyFeedback.textContent = message;
      loyaltyFeedback.classList.toggle('error', Boolean(isError));
    }

    function openLoyaltyGenerator() {
      if (!loyaltyModal) return;
      lastLoyaltyFocus = document.activeElement;
      loyaltyModal.hidden = false;
      document.body.style.overflow = 'hidden';
      const today = new Date();
      const nextYear = new Date(today);
      nextYear.setFullYear(today.getFullYear() + 1);
      const formatDate = (date) => date.toISOString().slice(0, 10);
      if (!loyaltyIssueDate.value) loyaltyIssueDate.value = formatDate(today);
      if (!loyaltyExpiryDate.value) loyaltyExpiryDate.value = formatDate(nextYear);
      drawLoyaltyCard();
      loyaltyCustomerSelect.focus();
    }

    function closeLoyaltyGenerator() {
      if (!loyaltyModal || loyaltyModal.hidden) return;
      loyaltyModal.hidden = true;
      document.body.style.overflow = '';
      if (lastLoyaltyFocus) lastLoyaltyFocus.focus();
    }

    loyaltyLogo.onload = () => {
      loyaltyLogoFailed = false;
      drawLoyaltyCard();
    };
    loyaltyLogo.onerror = () => {
      loyaltyLogoFailed = true;
      drawLoyaltyCard();
    };
    loyaltyLogo.src = '../assets/images/logo-icon.svg';
    if (loyaltyOpenBtn) loyaltyOpenBtn.addEventListener('click', openLoyaltyGenerator);
    if (loyaltyCustomerSelect) loyaltyCustomerSelect.addEventListener('change', drawLoyaltyCard);
    loyaltyDesignSelect.addEventListener('change', drawLoyaltyCard);
    loyaltyIssueDate.addEventListener('change', drawLoyaltyCard);
    loyaltyExpiryDate.addEventListener('change', drawLoyaltyCard);
    loyaltyModal.addEventListener('click', (event) => {
      if (event.target.closest('[data-loyalty-close]')) closeLoyaltyGenerator();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeLoyaltyGenerator();
    });
    loyaltyDownloadBtn.addEventListener('click', () => {
      const customer = selectedLoyaltyCustomer();
      if (!customer) {
        setLoyaltyFeedback('Choose a customer before downloading the card.', true);
        return;
      }

      try {
        loyaltyCanvas.toBlob((blob) => {
          if (!blob) {
            setLoyaltyFeedback('The PNG could not be created. Please try again.', true);
            return;
          }
          const safeName = customer.name.trim().replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase() || 'member';
          const downloadUrl = URL.createObjectURL(blob);
          const downloadLink = document.createElement('a');
          downloadLink.href = downloadUrl;
          downloadLink.download = `${safeName}-loyalty-card.png`;
          document.body.appendChild(downloadLink);
          downloadLink.click();
          downloadLink.remove();
          window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
          setLoyaltyFeedback('Your high-resolution loyalty card is ready to share or print.', false);
        }, 'image/png');
      } catch (error) {
        setLoyaltyFeedback('Unable to export the card: ' + error.message, true);
      }
    });

    drawLoyaltyCard();

    // Add customer buttons
    function openAddModal() {
      ModalController.open('customer', 'create');
    }

    if (addBtn) {
      addBtn.addEventListener('click', openAddModal);
    }
    if (fabBtn) {
      fabBtn.addEventListener('click', openAddModal);
    }

    // Row actions
    table.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;

      const row = btn.closest('tr');
      const id = row.dataset.id;
      const customerData = JSON.parse(row.dataset.customer);
      const action = btn.dataset.action;

      switch (action) {
        case 'view':
          ModalController.openView('customer', customerData);
          break;
        case 'topup':
          CtlPayments.openTopUp({userId: Number(id), name: customerData.full_name, owed: Number(btn.dataset.owed || 0), onDone: () => setTimeout(() => location.reload(), 600)});
          break;
        case 'edit':
          ModalController.open('customer', 'edit', customerData);
          break;
        case 'delete':
          ModalController.openConfirm({
            title: 'Delete Customer',
            message: `Are you sure you want to delete "${customerData.full_name}"?`,
            detail: 'This action cannot be undone.',
            icon: 'danger',
            confirmText: 'Delete',
            confirmClass: 'btn-danger',
            onConfirm: () => deleteCustomer(id),
          });
          break;
      }
    });

    // Delete customer
    async function deleteCustomer(id) {
      try {
        const response = await fetch('api/customers.php', {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({user_id: id}),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Failed to delete');
        ModalController.showNotification('Customer deleted successfully', 'success');
        refreshTable();
      } catch (error) {
        ModalController.showNotification(error.message, 'error');
      }
    }

    // Refresh table
    window.refreshTable = function() {
      location.reload();
    };

    ModalController.setCallback('onSave', (data, mode) => {
      if (mode !== 'edit' || data.user_id === undefined || data.membership_tier === undefined) return;

      const row = Array.from(table.querySelectorAll('tr[data-id]'))
        .find((customerRow) => customerRow.dataset.id === String(data.user_id));
      if (!row) return;

      const tier = String(data.membership_tier || 'standard').trim() || 'standard';
      const tierSlug = tier.toLowerCase().replace(/[^a-z0-9]+/g, '-') || 'standard';
      const tierLabel = tier.replace(/[_-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
      const tierBadge = row.querySelector('.customer-tier-badge');
      if (tierBadge) {
        tierBadge.className = `customer-tier-badge tier-${tierSlug}`;
        tierBadge.replaceChildren();
        const icon = document.createElement('i');
        icon.className = 'fas fa-award';
        icon.setAttribute('aria-hidden', 'true');
        tierBadge.append(icon, document.createTextNode(` ${tierLabel}`));
      }

      const customerData = JSON.parse(row.dataset.customer);
      customerData.membership_tier = tier;
      row.dataset.customer = JSON.stringify(customerData);
    });
  });
</script>
</body>
</html>