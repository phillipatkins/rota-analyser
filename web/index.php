<?php
if (!file_exists(__DIR__ . '/config.php')) { header('Location: install.php'); exit; }
require __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Rota Analyser</title>
<link rel="stylesheet" href="assets/style.css">
<style>
  .heatmap-grid { display: grid; gap: 3px; margin-top: 8px; }
  .heatmap-row  { display: flex; gap: 3px; align-items: center; }
  .heatmap-label { width: 120px; font-size: 11px; color: #64748b; flex-shrink: 0; }
  .heatmap-cell  { flex: 1; height: 22px; border-radius: 3px; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; cursor: default; }
  .cell-ok    { background: #14532d; color: #4ade80; }
  .cell-under { background: #78350f; color: #fbbf24; }
  .cell-zero  { background: #7f1d1d; color: #f87171; }
  .heatmap-weeks { display: flex; gap: 2px; overflow-x: auto; }
  .heatmap-week  { min-width: 200px; }
  .week-label    { font-size: 11px; font-weight: 700; color: #6366f1; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .5px; }
</style>
</head>
<body>

<div class="topbar">
  <div>
    <h1>Staff Rota Coverage Analyser</h1>
    <div class="sub">Upload your rota CSV — instantly see coverage gaps and overtime risk</div>
  </div>
  <a href="install.php" class="text-muted" style="font-size:12px">Setup</a>
</div>

<div class="container">

  <div class="card">
    <h2>Upload Rota</h2>
    <div class="drop-zone" id="drop-zone" onclick="document.getElementById('rota-input').click()">
      <div class="icon">📅</div>
      <strong id="file-label">Click or drag your rota CSV here</strong>
      <p>Format: Staff column, then one column per shift (Week1_Mon_Early, etc.)</p>
    </div>
    <input type="file" id="rota-input" accept=".csv">
    <div class="form-row" style="margin-top:16px">
      <div>
        <label>Minimum staff per shift</label>
        <input type="number" id="min-staff" value="2" min="1" max="20" style="width:80px">
      </div>
      <div style="align-self:flex-end">
        <button class="btn btn-primary" id="run-btn" disabled onclick="runAnalysis()">Analyse Rota</button>
      </div>
      <div style="align-self:flex-end">
        <button class="btn btn-secondary" onclick="loadSample()">Try Sample Rota</button>
      </div>
    </div>
  </div>

  <div class="spinner" id="spinner">⏳ Analysing rota...</div>
  <div id="results"></div>

</div>

<script>
let rotaFile = null;
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('rota-input');

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
  e.preventDefault(); dropZone.classList.remove('drag-over');
  if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', () => { if (fileInput.files[0]) setFile(fileInput.files[0]); });

function setFile(f) {
  rotaFile = f;
  document.getElementById('file-label').textContent = f.name;
  document.getElementById('run-btn').disabled = false;
}

function showSpinner() {
  document.getElementById('spinner').classList.add('show');
  document.getElementById('results').innerHTML = '';
}

function loadSample() {
  showSpinner();
  fetch('process.php?sample=1&min=' + document.getElementById('min-staff').value)
    .then(r => r.json()).then(renderResults)
    .catch(e => alert('Error: ' + e.message));
}

function runAnalysis() {
  if (!rotaFile) return;
  const form = new FormData();
  form.append('rota', rotaFile);
  form.append('min', document.getElementById('min-staff').value);
  showSpinner();
  fetch('process.php', { method: 'POST', body: form })
    .then(r => r.json()).then(renderResults)
    .catch(e => { document.getElementById('spinner').classList.remove('show'); alert('Error: ' + e.message); });
}

function renderResults(d) {
  document.getElementById('spinner').classList.remove('show');
  if (d.error) { document.getElementById('results').innerHTML = `<div class="alert alert-error">${d.error}</div>`; return; }

  const s = d.summary;
  let html = `<div class="stat-grid">
    <div class="stat"><div class="val text-red">${s.zero_cover_count}</div><div class="lbl">Zero Cover Shifts</div></div>
    <div class="stat"><div class="val text-amber">${s.understaffed_count}</div><div class="lbl">Understaffed Shifts</div></div>
    <div class="stat"><div class="val text-red">${s.wtd_risk_count}</div><div class="lbl">Overtime Risk</div></div>
  </div>`;

  // Zero cover alerts
  d.zero_cover.forEach(shift => {
    html += `<div class="alert alert-error">🚨 Zero cover: <strong>${shift}</strong> — nobody assigned to this shift</div>`;
  });

  // Heatmap
  const weeks = {};
  d.heatmap.forEach(cell => {
    const week = cell.shift.split('_')[0];
    if (!weeks[week]) weeks[week] = [];
    weeks[week].push(cell);
  });

  let heatmapHtml = '<div class="heatmap-weeks">';
  Object.entries(weeks).sort().forEach(([week, cells]) => {
    heatmapHtml += `<div class="heatmap-week"><div class="week-label">${week}</div>`;
    cells.forEach(cell => {
      const dayShift = cell.shift.split('_').slice(1).join(' ');
      const cls = cell.status === 'zero' ? 'cell-zero' : cell.status === 'under' ? 'cell-under' : 'cell-ok';
      const tip = `${cell.count} staff`;
      heatmapHtml += `<div class="heatmap-row">
        <div class="heatmap-label">${dayShift}</div>
        <div class="heatmap-cell ${cls}" title="${tip}">${cell.count}</div>
      </div>`;
    });
    heatmapHtml += '</div>';
  });
  heatmapHtml += '</div>';

  html += `<div class="card"><h2>Coverage Heatmap</h2>
    <div class="text-muted" style="font-size:12px;margin-bottom:10px">
      <span style="background:#14532d;color:#4ade80;padding:2px 8px;border-radius:3px;margin-right:6px">OK</span>
      <span style="background:#78350f;color:#fbbf24;padding:2px 8px;border-radius:3px;margin-right:6px">UNDERSTAFFED</span>
      <span style="background:#7f1d1d;color:#f87171;padding:2px 8px;border-radius:3px">ZERO COVER</span>
    </div>
    ${heatmapHtml}
  </div>`;

  // Staff hours
  let staffRows = d.staff_hours.map(s => {
    const risk = s.wtd_risk
      ? `<span class="badge badge-red">OVERTIME RISK</span>`
      : `<span class="badge badge-green">OK</span>`;
    return `<tr>
      <td>${s.name}</td>
      <td class="mono">${s.hours}h</td>
      <td>${risk}</td>
    </tr>`;
  }).join('');

  html += `<div class="card"><h2>Staff Hours</h2>
    <table><thead><tr><th>Staff Member</th><th>Total Hours</th><th>WTD Status</th></tr></thead>
    <tbody>${staffRows}</tbody></table>
  </div>`;

  document.getElementById('results').innerHTML = html;
}
</script>
</body>
</html>
