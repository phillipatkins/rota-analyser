<?php
header('Content-Type: application/json');
if (!file_exists(__DIR__ . '/config.php')) { echo json_encode(['error' => 'Run install.php first']); exit; }
require __DIR__ . '/config.php';

$minStaff = isset($_GET['min']) ? max(1, (int)$_GET['min']) : (isset($_POST['min']) ? max(1, (int)$_POST['min']) : 2);

if (isset($_GET['sample'])) {
    $sample = realpath(__DIR__ . '/../sample_rota.csv');
    if (!$sample) { echo json_encode(['error' => 'sample_rota.csv not found']); exit; }
    $python = escapeshellarg(PYTHON_PATH);
    $script = escapeshellarg(SCRIPT_PATH);
    $out = shell_exec("$python $script " . escapeshellarg($sample) . " --min $minStaff --format json 2>&1");
    $data = json_decode($out, true);
    echo json_encode($data ?: ['error' => 'Parse error: ' . substr($out ?? '', 0, 200)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['rota'])) {
    echo json_encode(['error' => 'No file uploaded']); exit;
}

$file = $_FILES['rota'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') { echo json_encode(['error' => 'CSV files only']); exit; }
if ($file['size'] > 5242880) { echo json_encode(['error' => 'File too large (max 5MB)']); exit; }

$path = UPLOAD_DIR . 'rota_' . uniqid() . '.csv';
if (!move_uploaded_file($file['tmp_name'], $path)) { echo json_encode(['error' => 'Could not save file']); exit; }

$python = escapeshellarg(PYTHON_PATH);
$script = escapeshellarg(SCRIPT_PATH);
$out = shell_exec("$python $script " . escapeshellarg($path) . " --min $minStaff --format json 2>&1");
@unlink($path);
$data = json_decode($out, true);
echo json_encode($data ?: ['error' => 'Parse error: ' . substr($out ?? '', 0, 200)]);
