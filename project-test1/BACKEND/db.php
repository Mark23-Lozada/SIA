<?php
/**
 * Simple POS - Unified Database Controller API
 * Location: ../BACKEND/db.php
 */

// Block raw warning messages from breaking JavaScript JSON data parsing
ini_set('display_errors', 0);
error_reporting(E_ALL);

// API Structural Security & Cross-Origin Resource Sharing (CORS) Configuration
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

// Handle Preflight Request Operations
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// XAMPP Default System Environment Credentials
$host     = 'localhost';
$username = 'root';
$password = '';         
$db_name  = 'pos'; 

// Initialize Database Instance Connection
$conn = mysqli_connect($host, $username, $password, $db_name);

if (!$conn) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database Connection Failed: ' . mysqli_connect_error()
    ]);
    exit;
}

// Establish character set for safe data encoding
mysqli_set_charset($conn, "utf8mb4");

// Parse raw input payload data stream sent from JavaScript fetch
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// ---------------------------------------------------------
// ROUTING METHOD: POST REQUEST ACTIONS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($data['action']) ? trim($data['action']) : '';

    // If you plan to log completed order histories later, you can handle it here:
    if ($action === 'log_transaction') {
        // Handle saving order details to database tables
        echo json_encode(['success' => true, 'message' => 'Transaction logged successfully.']);
        exit;
    }

    // Default fallback response for unknown actions
    echo json_encode(['success' => false, 'message' => 'Invalid system action requested.']);
    exit;
}

// Fallback for non-POST system requests
echo json_encode(['success' => false, 'message' => 'Invalid Request Method.']);
exit;