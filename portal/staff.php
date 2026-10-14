<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Staff
 * View and manage staff members
 */

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();
$isAdmin = hasPermission('staff.assign_permissions');
$canCreateStaff = hasPermission('staff.create');
$canEditStaff = hasPermission('staff.edit') || $isAdmin;
$canDeleteStaff = hasPermission('staff.delete') || $isAdmin;

$staffList = Database::fetchAll(
  "SELECT s.staff_id, s.email, s.phone, s.full_name, s.role, s.station_id, st.station_code,
          s.is_on_duty, s.is_active, s.hired_at
   FROM staff s
   LEFT JOIN stations st ON st.station_id = s.station_id
   ORDER BY s.full_name"
);
$staffCardBrand = siteSetting('business_name', 'CTECHLIT & SYSTEMS');
$staffCardPhone = siteSetting('contact_phone', '+233 24 123 4567');
$staffCardTagline = siteSetting('business_tagline', 'Internet Café & Digital Services');
$totalStaff = count($staffList);
$onDutyCount = count(array_filter($staffList, static fn($staff) => (bool) $staff['is_on_duty']));
$adminCount = count(array_filter($staffList, static fn($staff) => in_array($staff['role'], ['admin', 'ceo'], true)));
$attendantCount = count(array_filter($staffList, static fn($staff) => $staff['role'] === 'attendant'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="portal-content">
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-users"></i></div></div>
          <div class="kpi-value"><?php echo $totalStaff; ?></div><div class="kpi-label">Total Staff</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-user-check"></i></div></div>
          <div class="kpi-value"><?php echo $onDutyCount; ?></div><div class="kpi-label">On Duty</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-user-tie"></i></div></div>
          <div class="kpi-value"><?php echo $adminCount; ?></div><div class="kpi-label">Admins</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-headset"></i></div></div>
          <div class="kpi-value"><?php echo $attendantCount; ?></div><div class="kpi-label">Attendants</div>
        </div>
      </div>

      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div><div class="card-title">All Staff</div><div class="card-subtitle"><?php echo $totalStaff; ?> staff members</div></div>
          <div class="customer-header-actions">
            <?php if (hasPermission('cards.create')): ?><button class="btn btn-primary" id="staffIdCardOpen" type="button"><i class="fas fa-id-card"></i> Create Staff ID Card</button><?php endif; ?>
            <?php if ($canCreateStaff): ?><button class="btn btn-primary" id="addStaffBtn" style="padding: 10px 16px; font-size: 13px;"><i class="fas fa-plus"></i> Add Staff</button><?php endif; ?>
          </div>
        </div>

        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table" id="staffTable">
            <thead>
              <tr><th>Staff</th><th>Email</th><th>Role</th><th>Station</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($staffList as $staff):
                $staffData = [
                  'staff_id' => (int) $staff['staff_id'],
                  'email' => $staff['email'],
                  'phone' => $staff['phone'],
                  'full_name' => $staff['full_name'],
                  'role' => $staff['role'],
                  'station_id' => $staff['station_id'],
                  'is_on_duty' => (int) $staff['is_on_duty'],
                  'is_active' => (int) $staff['is_active'],
                  'hired_at' => $staff['hired_at'],
                ];
                $staffJson = htmlspecialchars(
                  json_encode($staffData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP),
                  ENT_QUOTES,
                  'UTF-8'
                );
                $nameParts = preg_split('/\s+/', trim($staff['full_name']));
                $initials = strtoupper(substr($nameParts[0] ?? '', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
                $roleLabel = getRoleLabel($staff['role']);
              ?>
                <tr data-id="<?php echo (int) $staff['staff_id']; ?>" data-staff="<?php echo $staffJson; ?>">
                  <td><div class="table-product"><div class="user-avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div><div class="table-product-info"><span class="table-product-name"><?php echo htmlspecialchars($staff['full_name'], ENT_QUOTES, 'UTF-8'); ?></span><span class="table-product-category"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></span></div></div></td>
                  <td><?php echo htmlspecialchars($staff['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><span class="status-badge info"><span class="status-dot"></span><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                  <td><?php echo htmlspecialchars($staff['station_code'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><span class="status-badge <?php echo $staff['is_on_duty'] ? 'success' : 'error'; ?>"><span class="status-dot"></span><?php echo $staff['is_on_duty'] ? 'On Duty' : 'Off Duty'; ?></span></td>
                  <td><div class="action-buttons" style="display: flex; gap: 6px;">
                    <button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button>
                    <?php if ($canEditStaff): ?><button class="header-btn edit-btn" title="Edit" data-action="edit"><i class="fas fa-edit"></i></button><?php endif; ?>
                    <?php if ($isAdmin): ?><button class="header-btn perm-btn" title="Permissions" data-action="permissions" style="color: var(--violet);"><i class="fas fa-key"></i></button><?php endif; ?>
                    <?php if ($canDeleteStaff): ?><button class="header-btn delete-btn" title="Delete" data-action="delete" style="color: var(--red);"><i class="fas fa-trash"></i></button><?php endif; ?>
                  </div></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="loyalty-modal" id="staffIdCardModal" hidden>
  <div class="loyalty-modal-backdrop" data-staff-card-close></div>
  <section class="loyalty-modal-panel" role="dialog" aria-modal="true" aria-labelledby="staffIdCardTitle">
    <div class="loyalty-modal-heading">
      <div>
        <span class="loyalty-eyebrow">OFFICIAL TEAM IDENTIFICATION</span>
        <h2 id="staffIdCardTitle">Create a staff ID card</h2>
        <p>Choose a team member and design a branded card to download or print.</p>
      </div>
      <button class="loyalty-close" type="button" aria-label="Close card generator" data-staff-card-close><i class="fas fa-times"></i></button>
    </div>
    <div class="loyalty-generator">
      <div class="loyalty-controls">
        <label class="loyalty-field" for="staffIdCardMember">
          <span>Choose staff member</span>
          <select id="staffIdCardMember" <?php echo $staffList ? '' : 'disabled'; ?>>
            <?php foreach ($staffList as $staff): ?>
            <option value="<?php echo (int) $staff['staff_id']; ?>"><?php echo htmlspecialchars($staff['full_name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="loyalty-field" for="staffIdCardDesign">
          <span>Card design</span>
          <select id="staffIdCardDesign">
            <option value="aurora">Aurora glass</option>
            <option value="emerald">Emerald team</option>
            <option value="royal">Royal purple</option>
            <option value="classic">Modern classic</option>
          </select>
        </label>
        <div class="loyalty-tier-note">
          <span class="loyalty-tier-icon"><i class="fas fa-user-shield"></i></span>
          <span><strong>Staff role</strong><small id="staffIdCardRoleHint">Select a staff member</small></span>
        </div>
        <p class="loyalty-help">The footer includes lost-property return guidance and the center phone number.</p>
        <button class="btn btn-primary loyalty-download" id="staffIdCardDownload" type="button" <?php echo $staffList ? '' : 'disabled'; ?>>
          <i class="fas fa-download"></i> Download PNG
        </button>
        <div class="loyalty-feedback" id="staffIdCardFeedback" role="status" aria-live="polite"></div>
      </div>
      <div class="loyalty-preview-wrap">
        <div class="loyalty-preview-topline"><span><i class="fas fa-sparkles"></i> LIVE PREVIEW</span><span>Print-ready · 1050 × 660 px</span></div>
        <canvas id="staffIdCardCanvas" width="1050" height="660" aria-label="Preview of the staff ID card"></canvas>
        <div class="loyalty-preview-caption"><i class="fas fa-shield-alt"></i> Official staff identification from <?php echo htmlspecialchars($staffCardBrand, ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
    </div>
  </section>
</div>

<?php if ($canCreateStaff): ?><button class="fab" id="fabAddStaff" aria-label="Add staff"><i class="fas fa-plus"></i></button><?php endif; ?>

<?php include_once __DIR__ . '/../includes/modals.php'; ?>
<datalist id="roleSuggestions">
  <option value="Admin">
  <option value="Manager">
  <option value="Attendant">
  <option value="Technician">
  <option value="Super Admin">
  <option value="Deputy Manager">
  <option value="Head Technician">
</datalist>
<script src="../assets/js/modal-controller.js"></script>
<script>
  window.STAFF_ID_CARD_MEMBERS = <?php echo json_encode(array_map(static function (array $staff): array {
    return [
      'id' => (int) $staff['staff_id'],
      'name' => (string) $staff['full_name'],
      'role' => (string) $staff['role'],
      'email' => (string) ($staff['email'] ?? ''),
      'phone' => (string) ($staff['phone'] ?? ''),
      'station' => (string) ($staff['station_code'] ?? ''),
      'hiredAt' => (string) ($staff['hired_at'] ?? ''),
    ];
  }, $staffList), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
  window.STAFF_ID_CARD_BRAND = <?php echo json_encode($staffCardBrand, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
  window.STAFF_ID_CARD_TAGLINE = <?php echo json_encode($staffCardTagline, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
  window.STAFF_ID_CARD_PHONE = <?php echo json_encode($staffCardPhone, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>;

  // Permission categories for the modal
  const PERMISSION_CATEGORIES = {
    'Dashboard': ['dashboard.view', 'dashboard.stats'],
    'Users': ['users.view', 'users.create', 'users.edit', 'users.delete'],
    'Stations': ['stations.view', 'stations.manage', 'stations.control'],
    'Sessions': ['sessions.view', 'sessions.manage', 'sessions.end'],
    'Services': ['services.view', 'services.create', 'services.edit', 'services.delete'],
    'Print Jobs': ['print_jobs.view', 'print_jobs.manage', 'print_jobs.cancel'],
    'Transactions': ['transactions.view', 'transactions.refund', 'transactions.reports'],
    'POS & Finance': ['pos.view', 'pos.manage', 'audit.view'],
    'Billing': ['billing.view', 'billing.manage'],
    'Inventory': ['inventory.view', 'inventory.manage', 'inventory.adjust'],
    'Staff': ['staff.view', 'staff.create', 'staff.edit', 'staff.delete', 'staff.assign_permissions'],
    'Settings': ['settings.view', 'settings.edit'],
    'Reports': ['reports.view', 'reports.export'],
    'Feedback': ['feedback.view', 'feedback.respond'],
    'Support': ['support.view', 'support.manage'],
    'Jarvis': ['jarvis.view', 'jarvis.use'],
    'Personal Devices': ['personal_devices.view', 'personal_devices.manage'],
    'Branded Assets': ['branded_assets.view', 'branded_assets.create', 'branded_assets.edit', 'branded_assets.delete'],
  };

  const PERMISSION_LABELS = {
    'dashboard.view': 'View Dashboard', 'dashboard.stats': 'View Statistics',
    'users.view': 'View Users', 'users.create': 'Create Users', 'users.edit': 'Edit Users', 'users.delete': 'Delete Users',
    'stations.view': 'View Stations', 'stations.manage': 'Manage Stations', 'stations.control': 'Control Stations',
    'sessions.view': 'View Sessions', 'sessions.manage': 'Manage Sessions', 'sessions.end': 'End Sessions',
    'services.view': 'View Services', 'services.create': 'Create Services', 'services.edit': 'Edit Services', 'services.delete': 'Delete Services',
    'print_jobs.view': 'View Print Jobs', 'print_jobs.manage': 'Manage Print Jobs', 'print_jobs.cancel': 'Cancel Print Jobs',
    'transactions.view': 'View Transactions', 'transactions.refund': 'Process Refunds', 'transactions.reports': 'View Financial Reports',
    'pos.view': 'View POS', 'pos.manage': 'Manage POS Sales', 'audit.view': 'View Audit Trail',
    'inventory.view': 'View Inventory', 'inventory.manage': 'Manage Inventory', 'inventory.adjust': 'Adjust Stock',
    'staff.view': 'View Staff', 'staff.create': 'Create Staff', 'staff.edit': 'Edit Staff', 'staff.delete': 'Delete Staff', 'staff.assign_permissions': 'Assign Permissions',
    'settings.view': 'View Settings', 'settings.edit': 'Edit Settings',
    'reports.view': 'View Reports', 'reports.export': 'Export Reports',
    'feedback.view': 'View Feedback', 'feedback.respond': 'Respond to Feedback',
    'support.view': 'View Support Tickets', 'support.manage': 'Manage Support Tickets',
    'jarvis.view': 'View Jarvis Assistant', 'jarvis.use': 'Use Jarvis Assistant',
    'billing.view': 'View Customer Billing', 'billing.manage': 'Manage Billing, Invoices & Payments',
    'personal_devices.view': 'View Personal Devices', 'personal_devices.manage': 'Manage Personal Devices',
    'branded_assets.view': 'View Branded Assets', 'branded_assets.create': 'Generate Branded Assets', 'branded_assets.edit': 'Edit Branded Asset Templates', 'branded_assets.delete': 'Delete Branded Asset Templates',
  };

  window.MODAL_CONFIGS = {
    staff: {
      title: 'Staff Member',
      fields: [
        {name: 'email', label: 'Email', type: 'email', required: true, placeholder: 'staff@example.com'},
        {name: 'phone', label: 'Phone', type: 'tel', required: true, placeholder: '+233 24 123 4567'},
        {name: 'full_name', label: 'Full Name', type: 'text', required: true, placeholder: 'Enter full name'},
        {name: 'password', label: 'Password', type: 'password', required: false, placeholder: 'Leave blank to keep current'},
        {name: 'role', label: 'Role', type: 'text', required: true, placeholder: 'e.g. Admin, Manager, or custom title'},
        {name: 'station_id', label: 'Assigned Station', type: 'select', required: false, options: [], ajax: 'api/stations.php?list=1'},
        {name: 'hired_at', label: 'Hire Date', type: 'date', required: true},
        {name: 'is_active', label: 'Active', type: 'checkbox', required: false},
        {name: 'is_on_duty', label: 'On Duty', type: 'checkbox', required: false},
      ],
      api: 'api/staff.php',
      table: 'staff',
      idField: 'staff_id',
    }
  };

  let currentStaffId = null;
  let currentStaffRole = null;

  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  function openSidebar() { sidebar.classList.add('open'); sidebarOverlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
  function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('active'); document.body.style.overflow = ''; }
  window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });

  document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('staffTable');
    const addBtn = document.getElementById('addStaffBtn');
    const fabBtn = document.getElementById('fabAddStaff');
    const staffCardModal = document.getElementById('staffIdCardModal');
    const staffCardOpen = document.getElementById('staffIdCardOpen');
    const staffCardMember = document.getElementById('staffIdCardMember');
    const staffCardDesign = document.getElementById('staffIdCardDesign');
    const staffCardRoleHint = document.getElementById('staffIdCardRoleHint');
    const staffCardDownload = document.getElementById('staffIdCardDownload');
    const staffCardFeedback = document.getElementById('staffIdCardFeedback');
    const staffCardCanvas = document.getElementById('staffIdCardCanvas');
    const staffCardContext = staffCardCanvas.getContext('2d');
    const staffCardMembers = window.STAFF_ID_CARD_MEMBERS || [];
    const staffCardBrand = window.STAFF_ID_CARD_BRAND || 'CTECHLIT & SYSTEMS';
    const staffCardTagline = window.STAFF_ID_CARD_TAGLINE || 'Internet Café & Digital Services';
    const staffCardPhone = window.STAFF_ID_CARD_PHONE || '';
    const staffCardLogo = new Image();
    let staffCardLogoFailed = false;
    let staffCardPreviousFocus = null;

    const staffCardDesigns = {
      aurora: {background: ['#10172f', '#342b70', '#12647a'], accent: '#5ee7f2', secondary: '#b59aff', text: '#ffffff', muted: 'rgba(238,242,255,.74)', footer: 'rgba(5,12,31,.48)'},
      emerald: {background: ['#082b2d', '#0a5a52', '#127c69'], accent: '#f5d785', secondary: '#7ee7bf', text: '#ffffff', muted: 'rgba(235,255,247,.76)', footer: 'rgba(3,25,26,.48)'},
      royal: {background: ['#21163e', '#4c2775', '#8b3d78'], accent: '#ffc2a7', secondary: '#e7b5ff', text: '#ffffff', muted: 'rgba(255,241,255,.76)', footer: 'rgba(25,12,43,.48)'},
      classic: {background: ['#f7f8ff', '#e7edff', '#dff8f7'], accent: '#6b45d8', secondary: '#00aab8', text: '#202544', muted: 'rgba(32,37,68,.66)', footer: 'rgba(255,255,255,.78)'},
    };

    function roundStaffCardPath(context, x, y, width, height, radius) {
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

    function fitStaffCardText(context, text, maxWidth, startingSize, minimumSize, weight) {
      let size = startingSize;
      context.font = `${weight} ${size}px Inter, Arial, sans-serif`;
      while (context.measureText(text).width > maxWidth && size > minimumSize) {
        size -= 1;
        context.font = `${weight} ${size}px Inter, Arial, sans-serif`;
      }
      if (context.measureText(text).width <= maxWidth) return text;
      let shortened = text;
      while (shortened.length > 1 && context.measureText(`${shortened}…`).width > maxWidth) {
        shortened = shortened.slice(0, -1);
      }
      return `${shortened}…`;
    }

    function staffRoleLabel(role) {
      if (!role) return 'Staff';
      const labels = {ceo: 'Chief Executive Officer', admin: 'Administrator', manager: 'Manager', attendant: 'Attendant', technician: 'Technician'};
      return labels[role] || String(role).replace(/[_-]+/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
    }

    function selectedStaffCardMember() {
      if (!staffCardMember || !staffCardMember.value) return null;
      return staffCardMembers.find(member => String(member.id) === staffCardMember.value) || null;
    }

    function setStaffCardFeedback(message, isError) {
      staffCardFeedback.textContent = message;
      staffCardFeedback.classList.toggle('error', Boolean(isError));
    }

    function drawStaffIdCard() {
      const member = selectedStaffCardMember();
      const design = staffCardDesigns[staffCardDesign.value] || staffCardDesigns.aurora;
      const context = staffCardContext;
      const width = staffCardCanvas.width;
      const height = staffCardCanvas.height;
      context.clearRect(0, 0, width, height);
      context.save();
      roundStaffCardPath(context, 0, 0, width, height, 48);
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
      context.globalAlpha = 0.12;
      context.fillStyle = design.secondary;
      context.beginPath();
      context.arc(1000, 70, 190, 0, Math.PI * 2);
      context.fill();
      context.beginPath();
      context.arc(940, 470, 240, 0, Math.PI * 2);
      context.fill();
      context.globalAlpha = 1;

      if (staffCardLogo.complete && staffCardLogo.naturalWidth > 0) {
        context.save();
        context.globalAlpha = design.text === '#ffffff' ? 0.13 : 0.09;
        context.drawImage(staffCardLogo, 620, 145, 350, 350);
        context.restore();
      }

      context.strokeStyle = design.text === '#ffffff' ? 'rgba(255,255,255,.16)' : 'rgba(36,48,93,.12)';
      context.lineWidth = 2;
      roundStaffCardPath(context, 18, 18, width - 36, height - 36, 34);
      context.stroke();

      if (staffCardLogo.complete && staffCardLogo.naturalWidth > 0) {
        context.drawImage(staffCardLogo, 70, 48, 62, 62);
      }
      context.fillStyle = design.text;
      const brandText = fitStaffCardText(context, staffCardBrand, 590, 25, 17, 700);
      context.fillText(brandText, 148, 77);
      context.fillStyle = design.muted;
      context.font = '500 12px Inter, Arial, sans-serif';
      context.fillText(fitStaffCardText(context, staffCardTagline.toUpperCase(), 590, 12, 10, 500), 150, 101);
      context.textAlign = 'right';
      context.fillStyle = design.accent;
      context.font = '700 15px Inter, Arial, sans-serif';
      context.fillText('OFFICIAL STAFF ID', 970, 72);
      context.fillStyle = design.muted;
      context.font = '500 12px Inter, Arial, sans-serif';
      context.fillText('TEAM MEMBER', 970, 94);
      context.textAlign = 'left';

      context.fillStyle = design.accent;
      context.font = '700 13px Inter, Arial, sans-serif';
      context.letterSpacing = '2.5px';
      context.fillText('CTECHLIT & SYSTEMS  ·  STAFF IDENTIFICATION', 78, 183);
      context.letterSpacing = '0px';

      context.fillStyle = design.muted;
      context.font = '600 13px Inter, Arial, sans-serif';
      context.fillText('AUTHORIZED TEAM MEMBER', 80, 241);
      context.fillStyle = design.text;
      const fullName = member ? member.name : 'Select a staff member';
      const staffName = fitStaffCardText(context, fullName, 800, 54, 34, 700);
      context.fillText(staffName, 78, 308);

      const role = member ? staffRoleLabel(member.role) : 'Staff Role';
      context.font = '700 15px Inter, Arial, sans-serif';
      const roleWidth = Math.max(180, context.measureText(role.toUpperCase()).width + 58);
      roundStaffCardPath(context, 78, 338, roleWidth, 44, 22);
      context.fillStyle = design.text === '#ffffff' ? 'rgba(255,255,255,.15)' : 'rgba(71,55,148,.1)';
      context.fill();
      context.strokeStyle = design.text === '#ffffff' ? 'rgba(255,255,255,.28)' : 'rgba(71,55,148,.2)';
      context.stroke();
      context.beginPath();
      context.arc(102, 360, 6, 0, Math.PI * 2);
      context.fillStyle = design.accent;
      context.fill();
      context.fillStyle = design.text;
      context.font = '700 14px Inter, Arial, sans-serif';
      context.fillText(role.toUpperCase(), 120, 365);

      if (member) {
        context.fillStyle = design.muted;
        context.font = '500 13px Inter, Arial, sans-serif';
        const staffId = `STAFF ID  ${String(member.id).padStart(4, '0')}`;
        context.fillText(staffId, 80, 421);
        if (member.station) {
          context.textAlign = 'right';
          context.fillText(`STATION  ${member.station}`, 970, 421);
          context.textAlign = 'left';
        }
      }

      context.fillStyle = design.footer;
      context.fillRect(0, 462, width, 198);
      context.fillStyle = design.accent;
      context.fillRect(78, 462, 95, 4);
      context.fillStyle = design.text;
      context.font = '700 15px Inter, Arial, sans-serif';
      context.fillText('FOUND THIS CARD?', 78, 500);
      context.fillStyle = design.muted;
      context.font = '500 13px Inter, Arial, sans-serif';
      context.fillText('Please return it to the nearest police station or radio station,', 78, 527);
      context.fillText('or call the center line:', 78, 549);
      context.fillStyle = design.accent;
      context.font = '700 17px Inter, Arial, sans-serif';
      const phoneText = staffCardPhone ? staffCardPhone : 'Contact the center';
      context.fillText(fitStaffCardText(context, phoneText, 500, 17, 13, 700), 78, 581);

      context.textAlign = 'right';
      context.fillStyle = design.text;
      context.font = '700 15px Inter, Arial, sans-serif';
      context.fillText(fitStaffCardText(context, staffCardBrand, 360, 15, 11, 700), 970, 524);
      context.fillStyle = design.muted;
      context.font = '500 12px Inter, Arial, sans-serif';
      context.fillText('STAFF IDENTIFICATION', 970, 549);
      if (member && member.email) {
        context.fillText(fitStaffCardText(context, member.email, 360, 12, 10, 500), 970, 574);
      }
      context.textAlign = 'left';
      context.restore();

      staffCardRoleHint.textContent = member ? role : 'Select a staff member';
      staffCardDownload.disabled = !member || !staffCardLogo.complete || staffCardLogo.naturalWidth === 0;
      if (staffCardLogoFailed) {
        setStaffCardFeedback('The system logo could not be loaded, so the card cannot be exported.', true);
      } else if (!staffCardLogo.complete || staffCardLogo.naturalWidth === 0) {
        setStaffCardFeedback('Loading system branding…', false);
      } else {
        setStaffCardFeedback('', false);
      }
    }

    function openStaffIdCardGenerator() {
      staffCardPreviousFocus = document.activeElement;
      staffCardModal.hidden = false;
      document.body.style.overflow = 'hidden';
      drawStaffIdCard();
      staffCardMember.focus();
    }

    function closeStaffIdCardGenerator() {
      if (staffCardModal.hidden) return;
      staffCardModal.hidden = true;
      document.body.style.overflow = '';
      if (staffCardPreviousFocus) staffCardPreviousFocus.focus();
    }

    staffCardLogo.onload = () => {
      staffCardLogoFailed = false;
      drawStaffIdCard();
    };
    staffCardLogo.onerror = () => {
      staffCardLogoFailed = true;
      drawStaffIdCard();
    };
    staffCardLogo.src = '../assets/images/logo-icon.svg';
    staffCardOpen.addEventListener('click', openStaffIdCardGenerator);
    staffCardMember.addEventListener('change', drawStaffIdCard);
    staffCardDesign.addEventListener('change', drawStaffIdCard);
    staffCardModal.addEventListener('click', event => {
      if (event.target.closest('[data-staff-card-close]')) closeStaffIdCardGenerator();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeStaffIdCardGenerator();
    });
    staffCardDownload.addEventListener('click', () => {
      const member = selectedStaffCardMember();
      if (!member) {
        setStaffCardFeedback('Choose a staff member before downloading the ID card.', true);
        return;
      }
      try {
        staffCardCanvas.toBlob(blob => {
          if (!blob) {
            setStaffCardFeedback('The PNG could not be created. Please try again.', true);
            return;
          }
          const safeName = member.name.trim().replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase() || `staff-${member.id}`;
          const downloadUrl = URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = downloadUrl;
          link.download = `${safeName}-staff-id-card.png`;
          document.body.appendChild(link);
          link.click();
          link.remove();
          window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
          setStaffCardFeedback('Your high-resolution staff ID card is ready to print.', false);
        }, 'image/png');
      } catch (error) {
        setStaffCardFeedback('Unable to export the ID card: ' + error.message, true);
      }
    });
    drawStaffIdCard();

    // Attach role suggestions datalist when modal opens
    const roleDatalist = document.getElementById('roleSuggestions');
    if (roleDatalist) {
      const modalObserver = new MutationObserver(() => {
        const roleInput = document.getElementById('role');
        if (roleInput && !roleInput.hasAttribute('list')) {
          roleInput.setAttribute('list', 'roleSuggestions');
          roleInput.setAttribute('autocomplete', 'off');
        }
      });
      const modalOverlay = document.getElementById('modalOverlay');
      if (modalOverlay) {
        modalObserver.observe(modalOverlay, { attributes: true, attributeFilter: ['class'] });
      }
    }
    function openAddModal() { ModalController.open('staff', 'create'); }
    if (addBtn) addBtn.addEventListener('click', openAddModal);
    if (fabBtn) fabBtn.addEventListener('click', openAddModal);

    table.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;
      const row = btn.closest('tr');
      const id = row.dataset.id;
      const data = JSON.parse(row.dataset.staff);
      const action = btn.dataset.action;

      switch (action) {
        case 'view': ModalController.openView('staff', data); break;
        case 'edit': ModalController.open('staff', 'edit', data); break;
        case 'permissions': openPermissionsModal(id, data); break;
        case 'delete':
          ModalController.openConfirm({
            title: 'Delete Staff', message: `Delete "${data.full_name}"?`, detail: 'This action cannot be undone.',
            icon: 'danger', confirmText: 'Delete', confirmClass: 'btn-danger',
            onConfirm: () => deleteStaff(id)
          });
          break;
      }
    });

    async function openPermissionsModal(staffId, staffData) {
      currentStaffId = staffId;
      currentStaffRole = staffData.role;

      try {
        const res = await fetch(`api/staff-permissions.php?staff_id=${staffId}`, {
          headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        const result = await res.json();
        if (!res.ok) throw new Error(result.message || 'Failed to load permissions');

        const payload = result?.data ?? result;
        const permissions = Array.isArray(payload?.permissions) ? payload.permissions : [];
        const role = payload?.role ?? staffData.role;

        renderPermissionsModal(staffData, permissions, role);
      } catch (err) {
        ModalController.showNotification(err.message, 'error');
      }
    }

    function renderPermissionsModal(staffData, permissions, role) {
      const isAdminRole = role === 'admin' || role === 'ceo';
      const isCustomRole = !isAdminRole && !(role in {admin: 1, ceo: 1, manager: 1, attendant: 1, technician: 1});
      const hasWildcard = permissions.some(p => p.key === '*' && p.granted);

      let html = `
        <div class="permissions-modal" style="max-height: 70vh; overflow-y: auto;">
          <div style="margin-bottom: 16px; padding: 12px; border-radius: var(--radius); background: ${isAdminRole ? 'rgba(245, 158, 11, 0.1)' : isCustomRole ? 'rgba(124, 58, 237, 0.1)' : 'rgba(6, 182, 212, 0.1)'}; border: 1px solid ${isAdminRole ? 'var(--amber)' : isCustomRole ? 'var(--violet)' : 'var(--cyan)'};">
            <div style="display: flex; align-items: center; gap: 8px;">
              <i class="fas fa-${isAdminRole ? 'user-shield' : isCustomRole ? 'star' : 'info-circle'}" style="color: ${isAdminRole ? 'var(--amber)' : isCustomRole ? 'var(--violet)' : 'var(--cyan)'};"></i>
              <div>
                <strong>${staffData.full_name}</strong> (${getRoleLabel(role)})
                ${isAdminRole ? '<br><small style="color: var(--amber);">Administrator has all permissions by default. Custom overrides can be set below.</small>' : ''}
                ${isCustomRole ? '<br><small style="color: var(--violet);">Custom role title. Permissions are controlled explicitly below.</small>' : ''}
              </div>
            </div>
          </div>
      `;

      if (isCustomRole) {
        html += `
          <div style="margin-bottom: 16px; padding: 12px; border-radius: var(--radius); background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25);">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
              <input type="checkbox" id="fullAccessToggle" ${hasWildcard ? 'checked' : ''} style="width: 18px; height: 18px; accent-color: var(--amber);">
              <div>
                <span style="font-weight: 600; font-size: 13px; color: var(--ink);">Full Access</span>
                <small style="color: var(--ink-secondary);">Grant all permissions to this staff member regardless of individual checks below.</small>
              </div>
            </label>
          </div>
        `;
      }

      html += `
          <form id="permissionsForm">
      `;

      Object.entries(PERMISSION_CATEGORIES).forEach(([category, perms]) => {
        const catPerms = perms.filter(p => permissions.some(perm => perm.key === p));
        if (!catPerms.length) return;

        html += `
          <fieldset style="margin-bottom: 20px; padding: 0; border: none;">
            <legend style="font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">${category}</legend>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px;">
        `;

        catPerms.forEach(permKey => {
          const perm = permissions.find(p => p.key === permKey);
          const granted = perm ? perm.granted : false;
          const isOverride = perm && perm.is_override;
          const label = PERMISSION_LABELS[permKey] || permKey;

          html += `
            <label class="perm-checkbox-label" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: var(--radius-sm); background: var(--bg-secondary); cursor: pointer; transition: var(--transition); position: relative;"
              onmouseover="this.style.background='var(--border-light)'"
              onmouseout="this.style.background='var(--bg-secondary)'">
              <input type="checkbox" class="perm-checkbox" name="permissions[]" value="${permKey}" ${granted ? 'checked' : ''}
                style="width: 16px; height: 16px; accent-color: var(--violet);"
                ${isAdminRole || hasWildcard ? 'disabled' : ''}>
              <span style="font-size: 13px; color: var(--ink); flex: 1;">${label}</span>
              ${isOverride ? '<span style="font-size: 10px; color: var(--violet); background: rgba(124, 58, 237, 0.1); padding: 2px 6px; border-radius: 999px;">Custom</span>' : ''}
            </label>
          `;
        });

        html += `
            </div>
          </fieldset>
        `;
      });

      html += `
          </form>
        </div>
      `;

      // Use the view modal for permissions
      const viewModalOverlay = document.getElementById('viewModalOverlay');
      const viewModalBody = document.getElementById('viewModalBody');
      const viewModalTitle = document.getElementById('viewModalTitle');

      viewModalTitle.textContent = `Permissions - ${staffData.full_name}`;
      viewModalBody.innerHTML = html;

      // Replace footer with save button
      const viewFooter = viewModalOverlay.querySelector('.modal-footer');
      viewFooter.innerHTML = `
        <button class="btn btn-secondary" id="permCancelBtn">Cancel</button>
        <button class="btn btn-primary" id="permSaveBtn" ${isAdminRole ? 'disabled' : ''}>
          <i class="fas fa-save"></i> ${isAdminRole ? 'Admin - Read Only' : 'Save Permissions'}
        </button>
      `;

      // Bind events
      document.getElementById('permCancelBtn').onclick = () => closePermissionsModal();
      document.getElementById('permSaveBtn').onclick = () => savePermissions(isCustomRole);

      viewModalOverlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closePermissionsModal() {
      const viewModalOverlay = document.getElementById('viewModalOverlay');
      viewModalOverlay.classList.remove('active');
      document.body.style.overflow = '';
      currentStaffId = null;
      currentStaffRole = null;
    }

    async function savePermissions(isCustomRole = false) {
      const form = document.getElementById('permissionsForm');
      const fullAccessToggle = document.getElementById('fullAccessToggle');
      const permissions = [];

      if (isCustomRole && fullAccessToggle && fullAccessToggle.checked) {
        permissions.push('*');
      } else {
        const checkboxes = form.querySelectorAll('input[name="permissions[]"]:checked');
        permissions.push(...Array.from(checkboxes).map(cb => cb.value));
      }

      try {
        const res = await fetch('api/staff-permissions.php', {
          method: 'PUT',
          headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
          body: JSON.stringify({staff_id: currentStaffId, permissions})
        });
        const result = await res.json();
        if (!res.ok) throw new Error(result.message || 'Failed to save');

        ModalController.showNotification('Permissions saved successfully', 'success');
        closePermissionsModal();
      } catch (err) {
        ModalController.showNotification(err.message, 'error');
      }
    }

    async function deleteStaff(id) {
      try {
        const res = await fetch('api/staff.php', {method: 'DELETE', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: JSON.stringify({staff_id: id})});
        const result = await res.json();
        if (!res.ok) throw new Error(result.message || 'Failed to delete');
        ModalController.showNotification('Staff deleted successfully', 'success');
        refreshTable();
      } catch (err) { ModalController.showNotification(err.message, 'error'); }
    }

    window.refreshTable = () => location.reload();
  // Helper
  function getRoleLabel(role) {
    const labels = {ceo: 'Chief Executive Officer', admin: 'Administrator', manager: 'Manager', attendant: 'Attendant', technician: 'Technician'};
    return labels[role] || (role ? String(role).replace(/[_-]+/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase()) : 'Staff');
  }
  });
</script>
</body>
</html>