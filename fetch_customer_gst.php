<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$customerName = trim($input['customer_name'] ?? '');

if (empty($customerName)) {
    echo json_encode(['success' => false, 'gst' => null]);
    exit();
}

// Extract the first line of the input to match against the database
$lines = explode("\n", str_replace("\r", "", $customerName));
$firstLine = trim($lines[0]);

if (empty($firstLine)) {
    echo json_encode(['success' => false, 'gst' => null]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT customer_gst FROM invoices WHERE customer_to LIKE ? AND customer_gst IS NOT NULL AND customer_gst != '' ORDER BY id DESC LIMIT 1");
    // Match any customer_to that starts with this first line
    $stmt->execute([$firstLine . '%']);
    $result = $stmt->fetch();

    if ($result && !empty($result['customer_gst'])) {
        echo json_encode(['success' => true, 'gst' => $result['customer_gst']]);
    } else {
        echo json_encode(['success' => true, 'gst' => null]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
