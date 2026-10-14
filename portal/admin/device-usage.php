<?php
/**
 * Admin POS and Device Usage Console
 */

require_once __DIR__ . '/../../includes/auth.php';
requirePermission('pos.manage');

$staffList = Database::tableExists('staff')
    ? Database::fetchAll(
        "SELECT staff_id, full_name, role, station_id, is_on_duty, last_login_at FROM staff ORDER BY full_name"
    )
    : [];

$posTerminals = Database::tableExists('pos_terminals')
    ? Database::fetchAll(
        "SELECT pt.*, s.full_name, s.role, st.station_code, st.name AS station_name
         FROM pos_terminals pt
         LEFT JOIN staff s ON s.staff_id = pt.staff_id
         LEFT JOIN stations st ON st.station_id = pt.station_id
         ORDER BY pt.assigned_at DESC"
    )
    : [];

$deviceUsage = Database::tableExists('device_usage_logs')
    ? Database::fetchAll(
        "SELECT dul.*, st.station_code, st.name AS station_name, s.full_name AS staff_name
         FROM device_usage_logs dul
         LEFT JOIN stations st ON st.station_id = dul.station_id
         LEFT JOIN staff s ON s.staff_id = dul.staff_id
         ORDER BY dul.started_at DESC
         LIMIT 20"
    )
    : [];

$revenueTotal = Database::tableExists('pos_transactions')
    ? (float) Database::fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM pos_transactions WHERE status = 'paid'")
    : 0.0;

$usageHours = Database::tableExists('device_usage_logs')
    ? (int) Database::fetchColumn("SELECT COALESCE(SUM(usage_minutes), 0) FROM device_usage_logs WHERE status = 'completed'") / 60
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Device Usage — CTECHLIT</title>
  <link rel="icon" type="image/svg+xml" href="../../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../../includes/header.php'; ?>

    <div class="portal-content">
      <div class="card glass-card animate-fade-in" style="margin-bottom: 24px; animation-delay: 0.1s;">
        <div class="card-header">
          <div>
            <div class="card-title">Assign POS Terminal</div>
            <div class="card-subtitle">Link a finance terminal to a staff member and device</div>
          </div>
        </div>

        <form id="assignPosForm" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-top: 18px;">
          <div>
            <label for="assign_staff_id" style="display:block; margin-bottom:6px; font-size:13px; color: var(--ink-secondary);">Staff Member</label>
            <select id="assign_staff_id" name="staff_id" class="form-input" required>
              <?php foreach ($staffList as $staff): ?>
                <option value="<?php echo (int) $staff['staff_id']; ?>"><?php echo htmlspecialchars($staff['full_name'] . ' (' . $staff['role'] . ')'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="assign_station_id" style="display:block; margin-bottom:6px; font-size:13px; color: var(--ink-secondary);">Device / Station</label>
            <select id="assign_station_id" name="station_id" class="form-input">
              <option value="">Select device</option>
              <?php foreach (Database::fetchAll("SELECT station_id, station_code, name FROM stations ORDER BY station_code") as $station): ?>
                <option value="<?php echo (int) $station['station_id']; ?>"><?php echo htmlspecialchars($station['station_code'] . ' - ' . $station['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="terminal_name" style="display:block; margin-bottom:6px; font-size:13px; color: var(--ink-secondary);">Terminal Name</label>
            <input id="terminal_name" name="terminal_name" type="text" class="form-input" value="POS-01" required>
          </div>
          <div>
            <label for="service_focus" style="display:block; margin-bottom:6px; font-size:13px; color: var(--ink-secondary);">Service Focus</label>
            <select id="service_focus" name="service_focus" class="form-input">
              <option value="mixed">Mixed</option>
              <option value="internet">Internet</option>
              <option value="gaming">Gaming</option>
              <option value="printing">Printing</option>
            </select>
          </div>
          <div style="display:flex; align-items:end;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-user-check"></i> Assign POS</button>
          </div>
          <div id="assignPosMessage" style="grid-column: 1 / -1; min-height: 20px; font-size: 13px; color: var(--green);"></div>
        </form>
      </div>

      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-wallet"></i></div></div>
          <div class="kpi-value">GHS <?php echo number_format($revenueTotal, 2); ?></div>
          <div class="kpi-label">Total POS Revenue</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-user-check"></i></div></div>
          <div class="kpi-value"><?php echo count(array_filter($staffList, fn($row) => !empty($row['is_on_duty']))); ?></div>
          <div class="kpi-label">Staff Online</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-clock"></i></div></div>
          <div class="kpi-value"><?php echo number_format($usageHours, 1); ?>h</div>
          <div class="kpi-label">Device Usage Hours</div>
        </div>
      </div>

      <div class="card glass-card animate-fade-in" style="margin-bottom: 24px; animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title">Assigned POS Terminals</div>
            <div class="card-subtitle">Staff assigned to finance counter and device access</div>
          </div>
        </div>
        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table">
            <thead>
              <tr><th>Staff</th><th>Role</th><th>Station</th><th>Service Focus</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php if (empty($posTerminals)): ?>
                <tr><td colspan="5" style="text-align:center; color: var(--ink-secondary);">No POS terminals assigned yet.</td></tr>
              <?php else: ?>
                <?php foreach ($posTerminals as $terminal): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($terminal['full_name'] ?? 'Unknown'); ?></td>
                    <td><?php echo htmlspecialchars($terminal['role'] ?? 'Staff'); ?></td>
                    <td><?php echo htmlspecialchars($terminal['station_code'] ?? ($terminal['station_name'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($terminal['service_focus'] ?? 'mixed')); ?></td>
                    <td><span class="status-badge <?php echo !empty($terminal['is_active']) ? 'success' : 'warning'; ?>"><span class="status-dot"></span><?php echo !empty($terminal['is_active']) ? 'Active' : 'Inactive'; ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card glass-card animate-fade-in" style="margin-bottom: 24px; animation-delay: 0.25s;">
        <div class="card-header">
          <div>
            <div class="card-title">Device Usage Summary</div>
            <div class="card-subtitle">Customer and total amount recorded per device use</div>
          </div>
        </div>
        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table">
            <thead>
              <tr><th>Device</th><th>Customer</th><th>Staff</th><th>Service</th><th>Minutes</th><th>Total</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php if (empty($deviceUsage)): ?>
                <tr><td colspan="7" style="text-align:center; color: var(--ink-secondary);">No device usage logs yet.</td></tr>
              <?php else: ?>
                <?php foreach ($deviceUsage as $entry): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($entry['station_code'] ?? ($entry['station_name'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars($entry['customer_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($entry['staff_name'] ?? 'Unassigned'); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($entry['service_type'] ?? 'other')); ?></td>
                    <td><?php echo (int) ($entry['usage_minutes'] ?? 0); ?></td>
                    <td style="font-weight: 600; color: var(--green);">GHS <?php echo number_format((float) ($entry['total_amount'] ?? 0), 2); ?></td>
                    <td><span class="status-badge <?php echo ($entry['status'] ?? 'completed') === 'active' ? 'warning' : 'success'; ?>"><span class="status-dot"></span><?php echo htmlspecialchars($entry['status'] ?? 'completed'); ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

  <script>
    const assignPosForm = document.getElementById('assignPosForm');
    const assignPosMessage = document.getElementById('assignPosMessage');

    assignPosForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const payload = {
        action: 'assign',
        staff_id: Number(document.getElementById('assign_staff_id').value),
        station_id: document.getElementById('assign_station_id').value || null,
        terminal_name: document.getElementById('terminal_name').value.trim(),
        service_focus: document.getElementById('service_focus').value,
        is_active: true
      };

      try {
        const res = await fetch('../../portal/api/pos.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
          body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (!res.ok) throw new Error(result.message || 'Assignment failed');

        assignPosMessage.textContent = 'POS terminal assigned successfully.';
        assignPosMessage.style.color = 'var(--green)';
        setTimeout(() => location.reload(), 700);
      } catch (error) {
        assignPosMessage.textContent = error.message || 'Unable to assign POS terminal.';
        assignPosMessage.style.color = 'var(--red)';
      }
    });
  </script>
</body>
</html>
