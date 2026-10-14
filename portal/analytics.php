<?php
/**
 * CTECHLIT & SYSTEMS — Business analytics dashboard
 * Reports revenue, transactions, customers, and station usage from live data.
 */

require_once __DIR__ . '/../includes/auth.php';
requirePermission('reports.view');

$periods = [
    7 => 'Last 7 days',
    30 => 'Last 30 days',
    90 => 'Last 90 days',
    365 => 'Last 12 months',
];
$requestedDays = filter_input(INPUT_GET, 'period', FILTER_VALIDATE_INT);
$days = ($requestedDays !== false && $requestedDays !== null && array_key_exists($requestedDays, $periods))
    ? $requestedDays
    : 30;
$interval = (int) ($days - 1);
$currency = siteSetting('currency_symbol', siteSetting('currency', 'GHS'));
$analytics = [
    'revenue' => 0.0,
    'completed_transactions' => 0,
    'pending_transactions' => 0,
    'new_customers' => 0,
    'sessions' => 0,
    'average_session_minutes' => 0,
    'trend' => [],
    'services' => [],
    'customers' => [],
    'recent_transactions' => [],
];
$error = '';

try {
    $summary = Database::fetchOne(
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) AS revenue,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_transactions,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_transactions
         FROM transactions
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$interval} DAY)"
    );
    $analytics['revenue'] = (float) ($summary['revenue'] ?? 0);
    $analytics['completed_transactions'] = (int) ($summary['completed_transactions'] ?? 0);
    $analytics['pending_transactions'] = (int) ($summary['pending_transactions'] ?? 0);

    $analytics['new_customers'] = (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$interval} DAY)"
    );

    $sessionStats = Database::fetchOne(
        "SELECT COUNT(*) AS session_count,
                COALESCE(AVG(CASE WHEN status = 'completed' THEN duration_minutes END), 0) AS average_session_minutes
         FROM sessions
         WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL {$interval} DAY)"
    );
    $analytics['sessions'] = (int) ($sessionStats['session_count'] ?? 0);
    $analytics['average_session_minutes'] = (int) round((float) ($sessionStats['average_session_minutes'] ?? 0));

    $trendRows = Database::fetchAll(
        "SELECT DATE(created_at) AS report_date,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) AS revenue,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS transactions
         FROM transactions
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$interval} DAY)
         GROUP BY DATE(created_at)
         ORDER BY report_date"
    );
    $trendByDate = [];
    foreach ($trendRows as $row) {
        $trendByDate[$row['report_date']] = $row;
    }
    for ($offset = $days - 1; $offset >= 0; $offset--) {
        $date = date('Y-m-d', strtotime("-{$offset} days"));
        $row = $trendByDate[$date] ?? [];
        $analytics['trend'][] = [
            'date' => $date,
            'label' => date('M j', strtotime($date)),
            'revenue' => (float) ($row['revenue'] ?? 0),
            'transactions' => (int) ($row['transactions'] ?? 0),
        ];
    }

    $analytics['services'] = Database::fetchAll(
        "SELECT COALESCE(s.category, 'other') AS category,
                COALESCE(SUM(ti.total_price), 0) AS revenue
         FROM transaction_items ti
         INNER JOIN transactions t ON t.transaction_id = ti.transaction_id
         LEFT JOIN services s ON s.service_id = ti.service_id
         WHERE t.status = 'completed'
           AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL {$interval} DAY)
         GROUP BY COALESCE(s.category, 'other')
         ORDER BY revenue DESC
         LIMIT 8"
    );

    $analytics['customers'] = Database::fetchAll(
        "SELECT u.full_name,
                COUNT(t.transaction_id) AS transaction_count,
                COALESCE(SUM(t.total_amount), 0) AS revenue
         FROM transactions t
         INNER JOIN users u ON u.user_id = t.user_id
         WHERE t.status = 'completed'
           AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL {$interval} DAY)
         GROUP BY u.user_id, u.full_name
         ORDER BY revenue DESC
         LIMIT 5"
    );

    $analytics['recent_transactions'] = Database::fetchAll(
        "SELECT t.receipt_number, u.full_name, t.total_amount, t.currency, t.status, t.created_at
         FROM transactions t
         LEFT JOIN users u ON u.user_id = t.user_id
         WHERE t.created_at >= DATE_SUB(CURDATE(), INTERVAL {$interval} DAY)
         ORDER BY t.created_at DESC
         LIMIT 8"
    );
} catch (Throwable $e) {
    logError('Failed to load analytics dashboard: ' . $e->getMessage());
    $error = 'Analytics could not be loaded. Please check the database connection and try again.';
}

$averageTransaction = $analytics['completed_transactions'] > 0
    ? $analytics['revenue'] / $analytics['completed_transactions']
    : 0;
$trendLabels = array_column($analytics['trend'], 'label');
$trendRevenue = array_column($analytics['trend'], 'revenue');
$trendTransactions = array_column($analytics['trend'], 'transactions');
$serviceLabels = array_map(static function (array $row): string {
    return ucwords(str_replace('_', ' ', (string) $row['category']));
}, $analytics['services']);
$serviceRevenue = array_map(static function (array $row): float {
    return (float) $row['revenue'];
}, $analytics['services']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics — CTECHLIT &amp; SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .analytics-header { display:flex; justify-content:space-between; align-items:flex-end; gap:20px; margin-bottom:22px; }
    .analytics-header h1 { margin:0; font-size:clamp(1.8rem,3vw,2.4rem); }
    .analytics-header p { margin:6px 0 0; color:var(--muted); }
    .analytics-eyebrow { color:var(--violet); font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; }
    .period-filter { display:flex; align-items:center; gap:8px; }
    .period-filter label { color:var(--ink-secondary); font-size:12px; font-weight:600; }
    .period-filter select { min-width:145px; padding:10px 12px; border:1px solid var(--border); border-radius:var(--radius); color:var(--ink); background:rgba(255,255,255,.8); font:inherit; }
    .analytics-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin-bottom:20px; }
    .analytics-kpi { padding:18px; }
    .analytics-kpi-top { display:flex; justify-content:space-between; align-items:center; gap:12px; color:var(--muted); font-size:12px; font-weight:600; }
    .analytics-kpi-icon { width:38px; height:38px; display:grid; place-items:center; border-radius:12px; background:var(--kpi-bg); color:var(--kpi-color); font-size:16px; }
    .analytics-kpi-value { margin-top:14px; color:var(--ink); font-size:clamp(1.35rem,2vw,1.8rem); font-weight:700; line-height:1.2; overflow-wrap:anywhere; }
    .analytics-kpi-note { margin-top:5px; color:var(--muted); font-size:11px; }
    .analytics-grid { display:grid; grid-template-columns:minmax(0,1.65fr) minmax(280px,1fr); gap:18px; margin-bottom:18px; }
    .analytics-grid.equal { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .analytics-card { min-width:0; padding:20px; }
    .analytics-card-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:16px; }
    .analytics-card-heading h2 { margin:0; color:var(--ink); font-size:15px; }
    .analytics-card-heading p { margin:4px 0 0; color:var(--muted); font-size:11px; }
    .analytics-chart { position:relative; height:290px; }
    .analytics-chart.doughnut { height:260px; }
    .analytics-table-wrap { overflow:auto; }
    .analytics-table { width:100%; border-collapse:collapse; font-size:12px; }
    .analytics-table th,.analytics-table td { padding:11px 9px; border-bottom:1px solid rgba(148,163,184,.16); text-align:left; white-space:nowrap; }
    .analytics-table th { color:var(--muted); font-size:10px; text-transform:uppercase; letter-spacing:.06em; }
    .analytics-table td { color:var(--ink-secondary); }
    .analytics-table td strong { color:var(--ink); }
    .analytics-table .numeric { text-align:right; }
    .analytics-empty { padding:36px 14px; color:var(--muted); text-align:center; font-size:12px; }
    .analytics-empty i { display:block; margin-bottom:10px; color:var(--cyan); font-size:22px; }
    .status-pill { display:inline-block; padding:3px 8px; border-radius:999px; background:rgba(148,163,184,.14); color:var(--ink-secondary); font-size:10px; text-transform:capitalize; }
    .status-pill.completed { background:rgba(16,185,129,.12); color:#047857; }
    .status-pill.pending { background:rgba(245,158,11,.14); color:#92400e; }
    .analytics-alert { margin-bottom:18px; padding:14px 16px; border-radius:var(--radius); color:#991b1b; background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.2); }
    @media(max-width:1000px) { .analytics-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:760px) { .analytics-grid,.analytics-grid.equal { grid-template-columns:1fr; } .analytics-header { align-items:flex-start; flex-direction:column; } }
    @media(max-width:440px) { .analytics-kpis { gap:10px; } .analytics-kpi { padding:14px; } .period-filter { width:100%; justify-content:space-between; } }
  </style>
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    <div class="portal-content">
      <header class="analytics-header">
        <div>
          <div class="analytics-eyebrow">Business intelligence</div>
          <h1>Analytics</h1>
          <p>Revenue, customer activity, and station usage from recorded system transactions.</p>
        </div>
        <form class="period-filter" method="get">
          <label for="period">Reporting period</label>
          <select id="period" name="period" onchange="this.form.submit()">
            <?php foreach ($periods as $periodDays => $periodLabel): ?>
              <option value="<?php echo (int) $periodDays; ?>" <?php echo $days === $periodDays ? 'selected' : ''; ?>>
                <?php echo sanitize($periodLabel); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </header>

      <?php if ($error !== ''): ?>
        <div class="analytics-alert" role="alert"><i class="fas fa-circle-exclamation"></i> <?php echo sanitize($error); ?></div>
      <?php endif; ?>

      <section class="analytics-kpis" aria-label="Reporting period summary">
        <article class="card glass-card analytics-kpi" style="--kpi-color:var(--violet);--kpi-bg:rgba(124,58,237,.1);">
          <div class="analytics-kpi-top"><span>Completed revenue</span><span class="analytics-kpi-icon"><i class="fas fa-coins"></i></span></div>
          <div class="analytics-kpi-value"><?php echo sanitize($currency); ?> <?php echo number_format($analytics['revenue'], 2); ?></div>
          <div class="analytics-kpi-note">From completed transactions</div>
        </article>
        <article class="card glass-card analytics-kpi" style="--kpi-color:var(--cyan);--kpi-bg:rgba(6,182,212,.1);">
          <div class="analytics-kpi-top"><span>Completed sales</span><span class="analytics-kpi-icon"><i class="fas fa-receipt"></i></span></div>
          <div class="analytics-kpi-value"><?php echo number_format($analytics['completed_transactions']); ?></div>
          <div class="analytics-kpi-note"><?php echo number_format($analytics['pending_transactions']); ?> pending transactions</div>
        </article>
        <article class="card glass-card analytics-kpi" style="--kpi-color:var(--green);--kpi-bg:rgba(16,185,129,.1);">
          <div class="analytics-kpi-top"><span>Average sale</span><span class="analytics-kpi-icon"><i class="fas fa-chart-line"></i></span></div>
          <div class="analytics-kpi-value"><?php echo sanitize($currency); ?> <?php echo number_format($averageTransaction, 2); ?></div>
          <div class="analytics-kpi-note">Revenue divided by completed sales</div>
        </article>
        <article class="card glass-card analytics-kpi" style="--kpi-color:var(--amber);--kpi-bg:rgba(245,158,11,.12);">
          <div class="analytics-kpi-top"><span>New customers</span><span class="analytics-kpi-icon"><i class="fas fa-user-plus"></i></span></div>
          <div class="analytics-kpi-value"><?php echo number_format($analytics['new_customers']); ?></div>
          <div class="analytics-kpi-note">Accounts created in this period</div>
        </article>
        <article class="card glass-card analytics-kpi" style="--kpi-color:var(--pink);--kpi-bg:rgba(236,72,153,.1);">
          <div class="analytics-kpi-top"><span>Station sessions</span><span class="analytics-kpi-icon"><i class="fas fa-desktop"></i></span></div>
          <div class="analytics-kpi-value"><?php echo number_format($analytics['sessions']); ?></div>
          <div class="analytics-kpi-note">Sessions started in this period</div>
        </article>
        <article class="card glass-card analytics-kpi" style="--kpi-color:var(--green);--kpi-bg:rgba(16,185,129,.1);">
          <div class="analytics-kpi-top"><span>Average session</span><span class="analytics-kpi-icon"><i class="fas fa-stopwatch"></i></span></div>
          <div class="analytics-kpi-value"><?php echo number_format($analytics['average_session_minutes']); ?> min</div>
          <div class="analytics-kpi-note">Completed sessions only</div>
        </article>
      </section>

      <section class="analytics-grid">
        <article class="card glass-card analytics-card">
          <div class="analytics-card-heading">
            <div><h2>Revenue and completed sales</h2><p>Daily performance for <?php echo sanitize($periods[$days]); ?></p></div>
            <i class="fas fa-chart-area" style="color:var(--violet);" aria-hidden="true"></i>
          </div>
          <div class="analytics-chart"><canvas id="performanceChart" role="img" aria-label="Daily revenue and completed sales chart"></canvas></div>
        </article>
        <article class="card glass-card analytics-card">
          <div class="analytics-card-heading">
            <div><h2>Revenue by service</h2><p>Completed transaction line items</p></div>
            <i class="fas fa-layer-group" style="color:var(--cyan);" aria-hidden="true"></i>
          </div>
          <?php if ($analytics['services']): ?>
            <div class="analytics-chart doughnut"><canvas id="servicesChart" role="img" aria-label="Revenue by service category chart"></canvas></div>
          <?php else: ?>
            <div class="analytics-empty"><i class="fas fa-chart-pie"></i>No service line-item data for this period.</div>
          <?php endif; ?>
        </article>
      </section>

      <section class="analytics-grid equal">
        <article class="card glass-card analytics-card">
          <div class="analytics-card-heading">
            <div><h2>Top customers</h2><p>Ranked by completed transaction revenue</p></div>
            <i class="fas fa-users" style="color:var(--green);" aria-hidden="true"></i>
          </div>
          <?php if ($analytics['customers']): ?>
            <div class="analytics-table-wrap">
              <table class="analytics-table">
                <thead><tr><th>Customer</th><th class="numeric">Sales</th><th class="numeric">Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($analytics['customers'] as $customer): ?>
                  <tr>
                    <td><strong><?php echo sanitize((string) $customer['full_name']); ?></strong></td>
                    <td class="numeric"><?php echo number_format((int) $customer['transaction_count']); ?></td>
                    <td class="numeric"><?php echo sanitize($currency); ?> <?php echo number_format((float) $customer['revenue'], 2); ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="analytics-empty"><i class="fas fa-user-group"></i>No completed customer sales for this period.</div>
          <?php endif; ?>
        </article>

        <article class="card glass-card analytics-card">
          <div class="analytics-card-heading">
            <div><h2>Recent transactions</h2><p>Latest activity in the selected period</p></div>
            <i class="fas fa-clock-rotate-left" style="color:var(--amber);" aria-hidden="true"></i>
          </div>
          <?php if ($analytics['recent_transactions']): ?>
            <div class="analytics-table-wrap">
              <table class="analytics-table">
                <thead><tr><th>Receipt</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($analytics['recent_transactions'] as $transaction): ?>
                  <tr>
                    <td><strong><?php echo sanitize((string) ($transaction['receipt_number'] ?: '—')); ?></strong></td>
                    <td><?php echo sanitize((string) ($transaction['full_name'] ?: 'Unknown')); ?></td>
                    <td><?php echo sanitize((string) ($transaction['currency'] ?: $currency)); ?> <?php echo number_format((float) $transaction['total_amount'], 2); ?></td>
                    <td><span class="status-pill <?php echo sanitize((string) $transaction['status']); ?>"><?php echo sanitize((string) $transaction['status']); ?></span></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="analytics-empty"><i class="fas fa-receipt"></i>No transactions recorded in this period.</div>
          <?php endif; ?>
        </article>
      </section>
    </div>
  </main>
  <script>
    const trendLabels = <?php echo json_encode($trendLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const trendRevenue = <?php echo json_encode($trendRevenue, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const trendTransactions = <?php echo json_encode($trendTransactions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const serviceLabels = <?php echo json_encode($serviceLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const serviceRevenue = <?php echo json_encode($serviceRevenue, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    new Chart(document.getElementById('performanceChart'), {
      type: 'bar',
      data: {
        labels: trendLabels,
        datasets: [
          { type: 'line', label: 'Revenue', data: trendRevenue, yAxisID: 'revenue', borderColor: '#7C3AED', backgroundColor: 'rgba(124,58,237,.12)', fill: true, tension: .35, pointRadius: 2 },
          { type: 'bar', label: 'Completed sales', data: trendTransactions, yAxisID: 'transactions', backgroundColor: 'rgba(6,182,212,.55)', borderRadius: 4, maxBarThickness: 16 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
        scales: {
          x: { grid: { display: false }, ticks: { maxTicksLimit: 10, maxRotation: 0 } },
          revenue: { type: 'linear', position: 'left', beginAtZero: true, title: { display: true, text: <?php echo json_encode($currency, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> }, grid: { color: 'rgba(148,163,184,.14)' } },
          transactions: { type: 'linear', position: 'right', beginAtZero: true, ticks: { precision: 0 }, grid: { display: false } }
        }
      }
    });

    <?php if ($analytics['services']): ?>
    new Chart(document.getElementById('servicesChart'), {
      type: 'doughnut',
      data: {
        labels: serviceLabels,
        datasets: [{ data: serviceRevenue, backgroundColor: ['#7C3AED', '#06B6D4', '#10B981', '#F59E0B', '#EC4899', '#6366F1', '#14B8A6', '#94A3B8'], borderWidth: 0, hoverOffset: 6 }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '66%',
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 14, boxWidth: 8 } } }
      }
    });
    <?php endif; ?>
  </script>
</body>
</html>
