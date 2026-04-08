<?php
// Simple Bill Splitter API
// Save as: api.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Create bills directory
$billsDir = __DIR__ . '/bills';

// Create directory with more aggressive permissions
if (!is_dir($billsDir)) {
    if (!mkdir($billsDir, 0777, true)) {
        respond(['success' => false, 'error' => 'Cannot create bills directory', 'path' => $billsDir], 500);
    }
    chmod($billsDir, 0777); // Ensure permissions are set
}

$method = $_SERVER['REQUEST_METHOD'];
$billId = isset($_GET['id']) ? $_GET['id'] : '';

// If no bill ID from GET, try to extract from PUT data
if (empty($billId) && $method === 'PUT') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (isset($data['billId'])) {
        $billId = $data['billId'];
    }
}

// Validate bill ID
function isValidBillId($id) {
    return preg_match('/^[a-zA-Z0-9]{7}$/', $id);
}

// Send JSON response
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Health check
if (empty($billId)) {
    respond([
        'success' => true,
        'message' => 'Bill Splitter API Ready',
        'time' => date('Y-m-d H:i:s'),
        'bills_directory' => $billsDir,
        'directory_exists' => is_dir($billsDir),
        'directory_writable' => is_writable($billsDir),
        'php_version' => phpversion(),
        'current_directory' => __DIR__
    ]);
}

// Validate bill ID
if (!isValidBillId($billId)) {
    respond(['success' => false, 'error' => 'Invalid bill ID format', 'provided' => $billId], 400);
}

$billFile = $billsDir . '/' . $billId . '.json';

// GET - Load bill
if ($method === 'GET') {
    if (!file_exists($billFile)) {
        respond([
            'success' => false, 
            'error' => 'Bill not found', 
            'billId' => $billId,
            'file_path' => $billFile,
            'directory_contents' => is_dir($billsDir) ? scandir($billsDir) : 'Directory not found'
        ], 404);
    }
    
    $data = file_get_contents($billFile);
    $bill = json_decode($data, true);
    
    if (!$bill) {
        respond(['success' => false, 'error' => 'Invalid bill data', 'raw_data' => $data], 500);
    }
    
    respond($bill);
}

// PUT - Save bill
if ($method === 'PUT') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        respond(['success' => false, 'error' => 'Invalid JSON', 'raw_input' => $input], 400);
    }
    
    if (!isset($data['people']) || !isset($data['items'])) {
        respond(['success' => false, 'error' => 'Missing required fields', 'received_keys' => array_keys($data)], 400);
    }
    
    // Ensure billId is set
    if (!isset($data['billId']) || empty($data['billId'])) {
        $data['billId'] = $billId;
    }
    
    $data['saved'] = date('c');
    $data['server_info'] = [
        'php_version' => phpversion(),
        'timestamp' => time()
    ];
    
    // Try to save the file
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $saved = file_put_contents($billFile, $jsonData);
    
    if ($saved === false) {
        respond([
            'success' => false, 
            'error' => 'Could not save bill', 
            'file_path' => $billFile,
            'directory_writable' => is_writable($billsDir),
            'directory_exists' => is_dir($billsDir),
            'parent_writable' => is_writable(dirname($billFile))
        ], 500);
    }
    
    // Verify the file was actually saved
    if (!file_exists($billFile)) {
        respond([
            'success' => false, 
            'error' => 'File save reported success but file does not exist',
            'bytes_written' => $saved,
            'file_path' => $billFile
        ], 500);
    }
    
    respond([
        'success' => true, 
        'billId' => $billId, 
        'saved' => date('c'),
        'bytes_written' => $saved,
        'file_path' => $billFile,
        'file_exists' => file_exists($billFile)
    ]);
}

// Method not allowed
respond(['success' => false, 'error' => 'Method not allowed', 'method' => $method], 405);
?>
