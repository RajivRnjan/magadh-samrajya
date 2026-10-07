<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    header("HTTP/1.1 403 Forbidden");
    exit("CSRF token validation failed. Unauthorized request.");
}

$invoiceId = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : null;
$branchId = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 1;
$serialNo = trim($_POST['serial_no'] ?? '');
$billDate = trim($_POST['bill_date'] ?? '');
$billMonthName = trim($_POST['bill_month_name'] ?? '');
$billMonthYear = trim($_POST['bill_month_year'] ?? '');
$billMonth = $billMonthName . ' ' . $billMonthYear;
$customerTo = trim($_POST['customer_to'] ?? '');
$customerGst = trim($_POST['customer_gst'] ?? '');
$totalAmount = floatval($_POST['total_amount'] ?? 0.00);
$fuelAmount = floatval($_POST['fuel_amount'] ?? 0.00);
$amountInWords = trim($_POST['amount_in_words'] ?? '');

// Items arrays
$cNotes = $_POST['c_note'] ?? [];
$destinations = $_POST['destination'] ?? [];
$itemDates = $_POST['item_date'] ?? [];
$deliveryDates = $_POST['delivery_date'] ?? [];
$packets = $_POST['packets'] ?? [];
$weights = $_POST['weight'] ?? [];
$amounts = $_POST['amount'] ?? [];

if (empty($serialNo) || empty($billDate) || empty($billMonth) || empty($customerTo) || count($cNotes) === 0) {
    header("Location: invoice.php?status=error");
    exit();
}

try {
    $pdo->beginTransaction();
    
    if ($invoiceId) {
        // Edit Mode: Update Invoices table
        $stmt = $pdo->prepare("UPDATE invoices SET 
            branch_id = ?,
            serial_no = ?, 
            bill_date = ?, 
            bill_month = ?, 
            customer_to = ?, 
            customer_gst = ?,
            total_amount = ?, 
            fuel_amount = ?, 
            amount_in_words = ? 
            WHERE id = ?");
        $stmt->execute([$branchId, $serialNo, $billDate, $billMonth, $customerTo, $customerGst, $totalAmount, $fuelAmount, $amountInWords, $invoiceId]);
        
        // Remove old invoice items
        $stmtDeleteItems = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmtDeleteItems->execute([$invoiceId]);
    } else {
        // Create Mode: Insert into Invoices table
        $stmt = $pdo->prepare("INSERT INTO invoices 
            (branch_id, serial_no, bill_date, bill_month, customer_to, customer_gst, total_amount, fuel_amount, amount_in_words) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$branchId, $serialNo, $billDate, $billMonth, $customerTo, $customerGst, $totalAmount, $fuelAmount, $amountInWords]);
        $invoiceId = $pdo->lastInsertId();
    }
    
    // Insert new invoice items
    $stmtItem = $pdo->prepare("INSERT INTO invoice_items 
        (invoice_id, c_note, destination, packets, weight, amount, item_date, delivery_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
    for ($i = 0; $i < count($cNotes); $i++) {
        // Skip completely empty rows if they exist
        if (trim($cNotes[$i]) === '' && trim($destinations[$i]) === '') {
            continue;
        }
        
        $cNoteVal = trim($cNotes[$i]);
        $destVal = trim($destinations[$i]);
        $pktVal = intval($packets[$i] ?? 0);
        $wtVal = floatval($weights[$i] ?? 0.00);
        $amtVal = floatval($amounts[$i] ?? 0.00);
        $itemDateVal = !empty($itemDates[$i]) ? trim($itemDates[$i]) : null;
        $deliveryDateVal = !empty($deliveryDates[$i]) ? trim($deliveryDates[$i]) : null;
        
        $stmtItem->execute([$invoiceId, $cNoteVal, $destVal, $pktVal, $wtVal, $amtVal, $itemDateVal, $deliveryDateVal]);
    }
    
    $pdo->commit();

    // Redirect to print view if "Save & Print" was clicked, else to dashboard
    $printAfterSave = ($_POST['print_after_save'] ?? '0') === '1';
    if ($printAfterSave) {
        header("Location: print.php?id=" . $invoiceId);
    } else {
        header("Location: index.php?status=saved");
    }
    exit();
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Debug info: log error or print
    die("Database Error occurred: " . $e->getMessage());
}
?>
