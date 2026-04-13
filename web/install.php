<?php
$checks = [];
$phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
$checks[] = ['label' => 'PHP ' . PHP_VERSION, 'ok' => $phpOk, 'note' => $phpOk ? 'Good.' : 'Need PHP 7.4+'];
$pythonPath = trim(shell_exec('which python3 2>/dev/null') ?: '');
$pythonOk = !empty($pythonPath);
$checks[] = ['label' => 'Python 3', 'ok' => $pythonOk, 'note' => $pythonOk ? trim(shell_exec('python3 --version 2>&1')) . ' at ' . $pythonPath : 'Install Python 3 from python.org'];
$scriptPath = realpath(__DIR__ . '/../analyser.py');
$scriptOk = $scriptPath && file_exists($scriptPath);
$checks[] = ['label' => 'analyser.py', 'ok' => $scriptOk, 'note' => $scriptOk ? 'Found.' : 'web/ must be inside the rota_analyser/ folder'];
$coloramaOk = $pythonOk && trim(shell_exec('python3 -c "import colorama; print(\'ok\')" 2>/dev/null') ?? '') === 'ok';
$checks[] = ['label' => 'Python: colorama', 'ok' => $coloramaOk, 'note' => $coloramaOk ? 'Installed.' : 'Run: pip3 install colorama'];
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
$uploadOk = is_writable($uploadDir);
$checks[] = ['label' => 'Uploads folder', 'ok' => $uploadOk, 'note' => $uploadOk ? 'Writable.' : 'chmod 755 web/uploads'];
$shellOk = function_exists('shell_exec');
$checks[] = ['label' => 'shell_exec', 'ok' => $shellOk, 'note' => $shellOk ? 'Enabled.' : 'Enable shell_exec in php.ini'];
$allOk = array_reduce($checks, fn($c, $i) => $c && $i['ok'], true);
if ($allOk) {
    $cfg = "<?php\ndefine('PYTHON_PATH', " . var_export($pythonPath, true) . ");\ndefine('SCRIPT_PATH', " . var_export($scriptPath, true) . ");\ndefine('UPLOAD_DIR', " . var_export($uploadDir, true) . ");\n";
    file_put_contents(__DIR__ . '/config.php', $cfg);
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Rota Analyser — Setup</title><link rel="stylesheet" href="assets/style.css"></head><body>
<div class="topbar"><div><h1>Rota Analyser — Setup</h1><div class="sub">Run once to verify setup</div></div></div>
<div class="container" style="max-width:680px"><div class="card"><h2>System Check</h2>
<?php foreach ($checks as $c): ?>
<div class="install-step">
  <div class="step-num <?= $c['ok'] ? 'done' : 'fail' ?>"><?= $c['ok'] ? '✓' : '✗' ?></div>
  <div class="step-content"><div class="step-title"><?= htmlspecialchars($c['label']) ?></div><div class="step-desc"><?= htmlspecialchars($c['note']) ?></div></div>
</div>
<?php endforeach; ?>
<?php if ($allOk): ?>
  <div class="alert alert-ok" style="margin-top:16px">✓ All good. <a href="index.php">→ Open the tool</a></div>
<?php else: ?>
  <div class="alert alert-warn" style="margin-top:16px">Fix the issues above then refresh this page.</div>
<?php endif; ?>
</div></div></body></html>
