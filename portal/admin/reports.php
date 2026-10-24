<?php
/**
 * CTECHLIT & SYSTEMS — Report Builder
 * Configure operational and financial reports using approved data sources.
 */

require_once __DIR__ . '/../../includes/auth.php';
requirePermission('reports.view');

$canBuildReports = hasPermission('reports.export');
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Report Builder — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/portal.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
    .report-builder { display:grid; grid-template-columns:minmax(280px, 360px) minmax(0, 1fr); gap:20px; align-items:start; }
    .report-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    .report-control label, .report-section-label { display:block; color:var(--ink-secondary); font-size:12px; font-weight:600; margin-bottom:6px; }
    .report-control input, .report-control select, .report-filter-row select, .report-filter-row input, .saved-report-select {
      box-sizing:border-box; width:100%; padding:10px 11px; border:1px solid var(--border); border-radius:var(--radius-sm);
      color:var(--ink); background:var(--bg); font:inherit; font-size:13px;
    }
    .report-control.full { grid-column:1/-1; }
    .report-checks { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:7px; max-height:220px; overflow:auto; padding:10px; border:1px solid var(--border); border-radius:var(--radius); }
    .report-check { display:flex; gap:8px; align-items:flex-start; color:var(--ink); font-size:12px; cursor:pointer; }
    .report-check input { accent-color:var(--violet); margin-top:2px; }
    .report-filter-row { display:grid; grid-template-columns:minmax(90px,1fr) minmax(100px,1fr) minmax(100px,1.2fr) auto; gap:7px; margin-bottom:8px; }
    .report-toolbar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .report-table-wrap { overflow:auto; }
    .report-table-wrap table { min-width:700px; }
    .report-chart-wrap { position:relative; height:340px; }
    .report-empty { padding:40px 20px; text-align:center; color:var(--ink-secondary); }
    .report-pill { display:inline-flex; align-items:center; gap:6px; padding:5px 9px; border-radius:999px; background:rgba(124,58,237,.1); color:var(--violet); font-size:11px; font-weight:600; }
    @media(max-width:1050px) { .report-builder { grid-template-columns:1fr; } }
    @media(max-width:650px) { .report-fields, .report-checks { grid-template-columns:1fr; } .report-filter-row { grid-template-columns:1fr 1fr; } }
  </style>
</head>
<body>
  <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../../includes/header.php'; ?>
    <div class="portal-content">
      <div class="card glass-card" style="margin-bottom:20px;">
        <div class="card-header" style="align-items:flex-start;">
          <div>
            <div class="card-title"><i class="fas fa-chart-pie" style="color:var(--violet); margin-right:8px;"></i>Report Studio</div>
            <div class="card-subtitle">Build operational, customer, inventory, and financial reports from live system data.</div>
          </div>
          <span class="report-pill"><i class="fas fa-shield-alt"></i> Controlled data sources</span>
        </div>
      </div>

      <?php if (!$canBuildReports): ?>
        <div class="card glass-card">
          <div class="report-empty">
            <i class="fas fa-lock" style="font-size:28px; color:var(--amber); margin-bottom:12px;"></i>
            <h3 style="color:var(--ink); margin:0 0 8px;">Report builder access required</h3>
            <p style="margin:0;">Your account can view reports but does not have permission to create or export them. Ask an administrator to grant <strong>Export Reports</strong>.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="report-builder">
          <section class="card glass-card">
            <div class="card-header">
              <div><div class="card-title">Configure report</div><div class="card-subtitle">Choose a source, columns, filters, and presentation.</div></div>
            </div>
            <div class="report-fields">
              <div class="report-control full">
                <label for="reportName">Report name</label>
                <input id="reportName" type="text" maxlength="100" placeholder="e.g. Monthly transaction review">
              </div>
              <div class="report-control full">
                <label for="reportSource">Data source</label>
                <select id="reportSource"></select>
              </div>
              <div class="report-control full">
                <label>Columns</label>
                <div id="reportColumns" class="report-checks"></div>
              </div>
              <div class="report-control">
                <label for="reportFrom">Date from</label>
                <input id="reportFrom" type="date">
              </div>
              <div class="report-control">
                <label for="reportTo">Date to</label>
                <input id="reportTo" type="date">
              </div>
              <div class="report-control full">
                <label>Additional filters</label>
                <div id="reportFilters"></div>
                <button type="button" class="btn btn-secondary" id="addFilterBtn" style="margin-top:4px;"><i class="fas fa-plus"></i> Add filter</button>
              </div>
              <div class="report-control">
                <label for="reportGroup">Group rows (optional)</label>
                <select id="reportGroup"><option value="">No grouping</option></select>
              </div>
              <div class="report-control">
                <label for="reportMeasure">Measure</label>
                <select id="reportMeasure"><option value="">Count records</option></select>
              </div>
              <div class="report-control">
                <label for="reportAggregate">Calculation</label>
                <select id="reportAggregate">
                  <option value="count">Count</option>
                  <option value="sum">Sum</option>
                  <option value="avg">Average</option>
                  <option value="min">Minimum</option>
                  <option value="max">Maximum</option>
                </select>
              </div>
              <div class="report-control">
                <label for="reportLayout">Output format</label>
                <select id="reportLayout">
                  <option value="table">Tabular grid</option>
                  <option value="bar">Bar chart + data grid</option>
                  <option value="line">Line chart + data grid</option>
                  <option value="doughnut">Doughnut chart + data grid</option>
                </select>
              </div>
              <div class="report-control">
                <label for="reportSort">Sort by</label>
                <select id="reportSort"></select>
              </div>
              <div class="report-control">
                <label for="reportDirection">Sort direction</label>
                <select id="reportDirection"><option value="desc">Descending</option><option value="asc">Ascending</option></select>
              </div>
              <div class="report-control full">
                <label for="reportLimit">Maximum rows</label>
                <select id="reportLimit">
                  <option value="100">100 rows</option>
                  <option value="500" selected>500 rows</option>
                  <option value="1000">1,000 rows</option>
                  <option value="5000">5,000 rows</option>
                </select>
              </div>
              <div class="report-control full report-toolbar">
                <button type="button" class="btn btn-primary" id="runReportBtn"><i class="fas fa-play"></i> Run report</button>
                <button type="button" class="btn btn-secondary" id="saveReportBtn"><i class="fas fa-save"></i> Save template</button>
              </div>
              <div class="report-control full">
                <label for="savedReports">Saved templates (this browser)</label>
                <div style="display:flex; gap:7px;">
                  <select id="savedReports" class="saved-report-select"><option value="">Choose a saved report</option></select>
                  <button type="button" class="btn btn-secondary" id="loadReportBtn" title="Load template"><i class="fas fa-folder-open"></i></button>
                  <button type="button" class="btn btn-secondary" id="deleteReportBtn" title="Delete template"><i class="fas fa-trash"></i></button>
                </div>
              </div>
            </div>
          </section>

          <section>
            <div class="kpi-grid" style="grid-template-columns:repeat(3,minmax(0,1fr)); margin-bottom:16px;">
              <div class="kpi-card" style="--kpi-color:var(--violet); --kpi-bg:rgba(124,58,237,.1);">
                <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-list"></i></div></div>
                <div class="kpi-value" id="resultCount">—</div><div class="kpi-label">Rows returned</div>
              </div>
              <div class="kpi-card" style="--kpi-color:var(--cyan); --kpi-bg:rgba(6,182,212,.1);">
                <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-database"></i></div></div>
                <div class="kpi-value" id="resultSource">—</div><div class="kpi-label">Data source</div>
              </div>
              <div class="kpi-card" style="--kpi-color:var(--green); --kpi-bg:rgba(16,185,129,.1);">
                <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-clock"></i></div></div>
                <div class="kpi-value" id="resultTime">—</div><div class="kpi-label">Generation time</div>
              </div>
            </div>
            <div class="card glass-card">
              <div class="card-header">
                <div><div class="card-title" id="resultTitle">Report results</div><div class="card-subtitle" id="resultSubtitle">Configure the report and run it to see live results.</div></div>
                <button type="button" class="btn btn-secondary" id="exportCsvBtn" disabled><i class="fas fa-file-csv"></i> Export CSV</button>
              </div>
              <div id="reportChartWrap" class="report-chart-wrap" hidden><canvas id="reportChart"></canvas></div>
              <div id="reportResults" class="report-table-wrap"><div class="report-empty"><i class="fas fa-table" style="font-size:28px; margin-bottom:12px;"></i><div>Build a report to display your data in a sortable tabular grid or chart.</div></div></div>
            </div>
          </section>
        </div>
      <?php endif; ?>
    </div>
  </main>
<?php if ($canBuildReports): ?>
<script>
(() => {
  const apiUrl = '../api/reports';
  const csrfToken = <?php echo json_encode($csrfToken); ?>;
  const sourceSelect = document.getElementById('reportSource');
  const columnsContainer = document.getElementById('reportColumns');
  const filterContainer = document.getElementById('reportFilters');
  const groupSelect = document.getElementById('reportGroup');
  const measureSelect = document.getElementById('reportMeasure');
  const sortSelect = document.getElementById('reportSort');
  const savedSelect = document.getElementById('savedReports');
  const resultsContainer = document.getElementById('reportResults');
  let catalog = {};
  let lastReport = null;
  let chart = null;
  let savedReports = [];

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  })[character]);

  async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    const payload = await response.json();
    if (!response.ok || payload.success === false) throw new Error(payload.message || 'The report request failed.');
    return payload.data ?? payload;
  }

  function currentSource() { return catalog[sourceSelect.value]; }
  function fieldOptions(fields, selected = '') {
    return fields.map(field => `<option value="${escapeHtml(field.key)}" ${field.key === selected ? 'selected' : ''}>${escapeHtml(field.label)}</option>`).join('');
  }

  function renderSourceFields() {
    const source = currentSource();
    if (!source) return;
    columnsContainer.innerHTML = source.fields.map((field, index) =>
      `<label class="report-check"><input type="checkbox" value="${escapeHtml(field.key)}" ${index < Math.min(6, source.fields.length) ? 'checked' : ''}><span>${escapeHtml(field.label)}</span></label>`
    ).join('');

    const previousGroup = groupSelect.value;
    groupSelect.innerHTML = '<option value="">No grouping</option>' + fieldOptions(source.fields, previousGroup);
    const previousMeasure = measureSelect.value;
    const numericFields = source.fields.filter(field => field.type === 'number');
    measureSelect.innerHTML = '<option value="">Count records</option>' + fieldOptions(numericFields, previousMeasure);
    const previousSort = sortSelect.value;
    sortSelect.innerHTML = fieldOptions(source.fields, previousSort || source.fields[0]?.key);
    document.getElementById('reportFrom').disabled = !source.date_field;
    document.getElementById('reportTo').disabled = !source.date_field;
    filterContainer.innerHTML = '';
  }

  function addFilter(field = '', operator = 'equals', value = '') {
    const source = currentSource();
    const row = document.createElement('div');
    row.className = 'report-filter-row';
    row.innerHTML = `
      <select class="filter-field" aria-label="Filter field">${fieldOptions(source.fields, field || source.fields[0]?.key)}</select>
      <select class="filter-operator" aria-label="Filter operation">
        <option value="equals">Is</option><option value="not_equals">Is not</option><option value="contains">Contains</option>
        <option value="greater">Greater than</option><option value="less">Less than</option>
        <option value="on_or_after">On or after</option><option value="on_or_before">On or before</option>
      </select>
      <input class="filter-value" type="text" value="${escapeHtml(value)}" placeholder="Value" aria-label="Filter value">
      <button type="button" class="btn btn-secondary remove-filter" aria-label="Remove filter"><i class="fas fa-times"></i></button>`;
    row.querySelector('.filter-operator').value = operator;
    row.querySelector('.remove-filter').addEventListener('click', () => row.remove());
    filterContainer.appendChild(row);
  }

  function buildConfig() {
    const selectedColumns = Array.from(columnsContainer.querySelectorAll('input:checked')).map(input => input.value);
    if (!selectedColumns.length) throw new Error('Select at least one column for the report.');
    return {
      name: document.getElementById('reportName').value.trim(),
      source: sourceSelect.value,
      columns: selectedColumns,
      date_from: document.getElementById('reportFrom').value,
      date_to: document.getElementById('reportTo').value,
      filters: Array.from(filterContainer.querySelectorAll('.report-filter-row')).map(row => ({
        field: row.querySelector('.filter-field').value,
        operator: row.querySelector('.filter-operator').value,
        value: row.querySelector('.filter-value').value.trim()
      })).filter(filter => filter.value !== ''),
      group_by: groupSelect.value,
      measure: measureSelect.value,
      aggregate: document.getElementById('reportAggregate').value,
      layout: document.getElementById('reportLayout').value,
      sort: sortSelect.value,
      direction: document.getElementById('reportDirection').value,
      limit: Number(document.getElementById('reportLimit').value)
    };
  }

  function showError(message) {
    resultsContainer.innerHTML = `<div class="report-empty" style="color:var(--red);"><i class="fas fa-exclamation-circle" style="font-size:24px; margin-bottom:10px;"></i><div>${escapeHtml(message)}</div></div>`;
  }

  function renderTable(columns, rows) {
    if (!rows.length) {
      resultsContainer.innerHTML = '<div class="report-empty"><i class="fas fa-search" style="font-size:26px; margin-bottom:10px;"></i><div>No records matched this report configuration.</div></div>';
      return;
    }
    const headings = columns.map(column => `<th>${escapeHtml(column.label)}</th>`).join('');
    const body = rows.map(row => `<tr>${columns.map(column => `<td>${escapeHtml(row[column.key])}</td>`).join('')}</tr>`).join('');
    resultsContainer.innerHTML = `<table class="data-table"><thead><tr>${headings}</tr></thead><tbody>${body}</tbody></table>`;
  }

  function renderReport(data, config, elapsedMs) {
    if (chart) { chart.destroy(); chart = null; }
    const chartWrap = document.getElementById('reportChartWrap');
    const grouped = Boolean(config.group_by);
    const chartLayout = ['bar', 'line', 'doughnut'].includes(config.layout);
    chartWrap.hidden = !chartLayout;
    const columns = data.columns;
    renderTable(columns, data.rows);
    if (chartLayout && data.rows.length) {
      const groupColumn = grouped ? 'group_label' : (columns[0]?.key);
      const valueColumn = grouped ? 'value' : (columns.find(column => column.type === 'number')?.key);
      if (!valueColumn) {
        chartWrap.hidden = true;
        document.getElementById('resultSubtitle').textContent = 'Choose a numeric measure or group the report to display a chart.';
      } else {
        chart = new Chart(document.getElementById('reportChart'), {
          type: config.layout,
          data: {
            labels: data.rows.map(row => String(row[groupColumn] ?? 'Not set')),
            datasets: [{
              label: grouped ? data.measure_label : columns.find(column => column.key === valueColumn).label,
              data: data.rows.map(row => Number(row[valueColumn]) || 0),
              backgroundColor: ['#7c3aed', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#14b8a6', '#f97316'],
              borderColor: '#7c3aed',
              borderWidth: config.layout === 'line' ? 2 : 0,
              fill: false,
              tension: .25
            }]
          },
          options: {responsive:true, maintainAspectRatio:false, plugins:{legend:{display:config.layout === 'doughnut', position:'bottom'}}, scales:config.layout === 'doughnut' ? {} : {y:{beginAtZero:true}}}
        });
      }
    }
    document.getElementById('resultCount').textContent = Number(data.rows.length).toLocaleString() + (data.truncated ? '+' : '');
    document.getElementById('resultSource').textContent = catalog[config.source].label;
    document.getElementById('resultTime').textContent = `${elapsedMs} ms`;
    document.getElementById('resultTitle').textContent = config.name || `${catalog[config.source].label} report`;
    document.getElementById('resultSubtitle').textContent = `${data.rows.length.toLocaleString()} row(s) displayed${data.truncated ? ' — increase the row limit to view more' : ''}.`;
    document.getElementById('exportCsvBtn').disabled = !data.rows.length;
    lastReport = {columns, rows:data.rows, name:config.name || `${config.source}-report`};
  }

  async function runReport() {
    const button = document.getElementById('runReportBtn');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running…';
    try {
      const config = buildConfig();
      const start = performance.now();
      const data = await requestJson(apiUrl, {
        method:'POST',
        headers:{'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-Token':csrfToken},
        body:JSON.stringify(config)
      });
      renderReport(data, config, Math.max(1, Math.round(performance.now() - start)));
    } catch (error) {
      showError(error.message);
    } finally {
      button.disabled = false;
      button.innerHTML = '<i class="fas fa-play"></i> Run report';
    }
  }

  function loadSavedReports() {
    try {
      savedReports = JSON.parse(localStorage.getItem('portalSavedReports') || '[]');
      if (!Array.isArray(savedReports)) savedReports = [];
    } catch (error) {
      console.warn('Unable to read saved report templates.', error);
      savedReports = [];
    }
    savedSelect.innerHTML = '<option value="">Choose a saved report</option>' + savedReports.map((item, index) =>
      `<option value="${index}">${escapeHtml(item.name || 'Untitled report')}</option>`
    ).join('');
  }

  function saveReport() {
    try {
      const config = buildConfig();
      if (!config.name) throw new Error('Enter a report name before saving the template.');
      const existing = savedReports.findIndex(item => item.name.toLowerCase() === config.name.toLowerCase());
      if (existing >= 0) savedReports[existing] = config;
      else savedReports.push(config);
      localStorage.setItem('portalSavedReports', JSON.stringify(savedReports));
      loadSavedReports();
      savedSelect.value = String(savedReports.findIndex(item => item.name === config.name));
      document.getElementById('resultSubtitle').textContent = 'Report template saved in this browser.';
    } catch (error) {
      showError(error.message || 'Unable to save the report template in this browser.');
    }
  }

  function applyConfig(config) {
    sourceSelect.value = config.source;
    renderSourceFields();
    document.getElementById('reportName').value = config.name || '';
    document.getElementById('reportFrom').value = config.date_from || '';
    document.getElementById('reportTo').value = config.date_to || '';
    columnsContainer.querySelectorAll('input').forEach(input => { input.checked = (config.columns || []).includes(input.value); });
    (config.filters || []).forEach(filter => addFilter(filter.field, filter.operator, filter.value));
    groupSelect.value = config.group_by || '';
    measureSelect.value = config.measure || '';
    document.getElementById('reportAggregate').value = config.aggregate || 'count';
    document.getElementById('reportLayout').value = config.layout || 'table';
    sortSelect.value = config.sort || '';
    document.getElementById('reportDirection').value = config.direction || 'desc';
    document.getElementById('reportLimit').value = String(config.limit || 500);
  }

  function exportCsv() {
    if (!lastReport) return;
    const quote = value => {
      let text = String(value ?? '');
      if (/^[\t\r ]*[=+\-@]/.test(text)) text = `'${text}`;
      return `"${text.replace(/"/g, '""')}"`;
    };
    const rows = [lastReport.columns.map(column => quote(column.label)).join(',')];
    lastReport.rows.forEach(row => rows.push(lastReport.columns.map(column => quote(row[column.key])).join(',')));
    const blob = new Blob(['\uFEFF' + rows.join('\r\n')], {type:'text/csv;charset=utf-8'});
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${lastReport.name.replace(/[^\w.-]+/g, '_')}.csv`;
    anchor.click();
    URL.revokeObjectURL(url);
  }

  sourceSelect.addEventListener('change', renderSourceFields);
  document.getElementById('addFilterBtn').addEventListener('click', () => addFilter());
  document.getElementById('runReportBtn').addEventListener('click', runReport);
  document.getElementById('saveReportBtn').addEventListener('click', saveReport);
  document.getElementById('exportCsvBtn').addEventListener('click', exportCsv);
  document.getElementById('loadReportBtn').addEventListener('click', () => {
    const config = savedReports[Number(savedSelect.value)];
    if (config) applyConfig(config);
  });
  document.getElementById('deleteReportBtn').addEventListener('click', () => {
    const index = Number(savedSelect.value);
    if (!Number.isInteger(index) || !savedReports[index]) return;
    savedReports.splice(index, 1);
    try {
      localStorage.setItem('portalSavedReports', JSON.stringify(savedReports));
      loadSavedReports();
    } catch (error) {
      showError('Unable to update saved report templates in this browser.');
    }
  });

  requestJson(`${apiUrl}?catalog=1`, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(response => {
    catalog = response.sources;
    sourceSelect.innerHTML = Object.entries(catalog).map(([key, source]) => `<option value="${escapeHtml(key)}">${escapeHtml(source.label)}</option>`).join('');
    renderSourceFields();
    loadSavedReports();
  }).catch(error => {
    showError(error.message);
    document.getElementById('runReportBtn').disabled = true;
  });
})();
</script>
<?php endif; ?>
</body>
</html>
