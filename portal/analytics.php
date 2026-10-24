<?php
/**
 * CTECHLIT & SYSTEMS — Business analytics
 * Revenue, customers, station use, printing and receivables for any period, compared with the previous one.
 * Data comes from api/analytics.php.
 */

require_once __DIR__ . '/../includes/auth.php';
requireStaffAuth();
requirePermission('reports.view');

$v = static fn(string $file) => filemtime(__DIR__ . '/../assets/' . $file);
$presets = ['today' => 'Today', '7d' => '7D', '30d' => '30D', '90d' => '90D', 'month' => 'This month', 'last_month' => 'Last month', 'ytd' => 'YTD', '12m' => '12M'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics — CTECHLIT &amp; SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <link rel="stylesheet" href="../assets/css/analytics.css?v=<?php echo $v('css/analytics.css'); ?>">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" async></script>
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="portal-content an-page">
      <header class="an-head">
        <div>
          <div class="an-eyebrow">Business intelligence</div>
          <h1>Analytics</h1>
          <p id="anRangeText">Loading…</p>
        </div>
        <div class="an-tools">
          <button type="button" class="btn btn-secondary" id="anPrint"><i class="fas fa-print"></i> Print</button>
          <a class="btn btn-primary" id="anExport" href="api/analytics.php?range=30d&amp;export=csv"><i class="fas fa-file-csv"></i> Export CSV</a>
        </div>
      </header>

      <div class="an-controls" role="group" aria-label="Reporting period">
        <div class="an-presets" id="anPresets">
          <?php foreach ($presets as $key => $label): ?>
            <button type="button" data-range="<?php echo $key; ?>"><?php echo $label; ?></button>
          <?php endforeach; ?>
        </div>
        <form class="an-custom" id="anCustom">
          <label class="sr-only" for="anFrom">From</label><input type="date" id="anFrom" class="form-input" required>
          <span aria-hidden="true">→</span>
          <label class="sr-only" for="anTo">To</label><input type="date" id="anTo" class="form-input" required>
          <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
        </form>
      </div>

      <div class="an-error" id="anError" role="alert" hidden></div>

      <section class="an-kpis" id="anKpis" aria-label="Key figures"></section>

      <section class="an-insights" id="anInsights" aria-label="Highlights" hidden></section>

      <div class="an-grid">
        <article class="an-card an-span2">
          <header>
            <div><h2>Revenue trend</h2><p id="anTrendNote">Net of refunds · dashed line is the previous period</p></div>
            <div class="an-toggle" role="group" aria-label="Trend measure">
              <button type="button" data-measure="revenue" aria-pressed="true">Revenue</button>
              <button type="button" data-measure="transactions" aria-pressed="false">Transactions</button>
            </div>
          </header>
          <div class="an-chart"><canvas id="anTrend" role="img" aria-label="Revenue over time compared with the previous period"></canvas></div>
        </article>

        <article class="an-card">
          <header><div><h2>Revenue by category</h2><p>Where the money comes from</p></div></header>
          <div class="an-chart an-donut"><canvas id="anCategories" role="img" aria-label="Revenue by category"></canvas><div class="an-donut-center" id="anDonutCenter"></div></div>
          <ul class="an-legend" id="anCategoryList"></ul>
        </article>

        <article class="an-card an-span2">
          <header><div><h2>Busiest times</h2><p>Sales by day of week and hour</p></div>
            <div class="an-toggle" role="group" aria-label="Heatmap measure">
              <button type="button" data-heat="transactions" aria-pressed="true">Sales</button>
              <button type="button" data-heat="revenue" aria-pressed="false">Revenue</button>
            </div>
          </header>
          <div class="an-heat-wrap"><div class="an-heat" id="anHeat"></div></div>
        </article>

        <article class="an-card">
          <header><div><h2>Payment methods</h2><p>How customers pay</p></div></header>
          <ul class="an-bars" id="anMethods"></ul>
        </article>

        <article class="an-card an-span2">
          <header><div><h2>Station utilisation</h2><p id="anCapacity">Hours used against opening hours</p></div></header>
          <ul class="an-bars an-stations" id="anStations"></ul>
        </article>

        <article class="an-card">
          <header><div><h2>Cash position</h2><p>Right now, across all time</p></div></header>
          <dl class="an-figures" id="anReceivables"></dl>
        </article>

        <article class="an-card an-span2">
          <header><div><h2>Top customers</h2><p id="anCustomerMix">—</p></div></header>
          <div class="an-table-wrap"><table class="an-table"><thead><tr><th>#</th><th>Customer</th><th>Visits</th><th>Last visit</th><th class="num">Revenue</th></tr></thead><tbody id="anCustomers"></tbody></table></div>
        </article>

        <article class="an-card">
          <header><div><h2>Printing</h2><p>Jobs submitted in the period</p></div><a class="an-link" href="print-jobs.php?view=history">Jobs <i class="fas fa-arrow-right"></i></a></header>
          <dl class="an-figures" id="anPrinting"></dl>
        </article>

        <article class="an-card an-span3">
          <header><div><h2>Best sellers</h2><p>Services and products by revenue</p></div></header>
          <ul class="an-bars an-items" id="anItems"></ul>
        </article>
      </div>
    </div>
  </main>

  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
  <script src="../assets/js/modal-controller.js"></script>
  <script src="../assets/js/analytics.js?v=<?php echo $v('js/analytics.js'); ?>" defer></script>
</body>
</html>
