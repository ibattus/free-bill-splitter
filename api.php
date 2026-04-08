<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$billsDir = __DIR__ . '/bills';

if (!is_dir($billsDir)) {
    if (!mkdir($billsDir, 0777, true)) {
        respond(['success' => false, 'error' => 'Cannot create bills directory'], 500);
    }
    chmod($billsDir, 0777);
}

$method = $_SERVER['REQUEST_METHOD'];
$billId = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($billId) && $method === 'PUT') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (isset($data['billId'])) {
        $billId = $data['billId'];
    }
}

function isValidBillId($id) {
    return preg_match('/^[a-zA-Z0-9]{7}$/', $id);
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

if (empty($billId)) {
    respond(['success' => true, 'message' => 'Bill Splitter API Ready']);
}

if (!isValidBillId($billId)) {
    respond(['success' => false, 'error' => 'Invalid bill ID format'], 400);
}

$billFile = $billsDir . '/' . $billId . '.json';

if ($method === 'GET') {
    if (!file_exists($billFile)) {
        respond(['success' => false, 'error' => 'Bill not found'], 404);
    }

    $bill = json_decode(file_get_contents($billFile), true);

    if (!$bill) {
        respond(['success' => false, 'error' => 'Invalid bill data'], 500);
    }

    respond($bill);
}

if ($method === 'PUT') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        respond(['success' => false, 'error' => 'Invalid JSON'], 400);
    }

    if (!isset($data['people']) || !isset($data['items'])) {
        respond(['success' => false, 'error' => 'Missing required fields'], 400);
    }

    if (!isset($data['billId']) || empty($data['billId'])) {
        $data['billId'] = $billId;
    }

    $data['saved'] = date('c');

    $saved = file_put_contents($billFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($saved === false) {
        respond(['success' => false, 'error' => 'Could not save bill'], 500);
    }

    respond(['success' => true, 'billId' => $billId, 'saved' => date('c')]);
}

respond(['success' => false, 'error' => 'Method not allowed'], 405);
