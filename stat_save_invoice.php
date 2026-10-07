<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invId = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;
    $quotation_id = !empty($_POST['quotation_id']) ? (int)$_POST['quotation_id'] : null;
    $serial_no = trim($_POST['serial_no'] ?? '');
    $bill_date = trim($_POST['bill_date'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_to = trim($_POST['customer_to'] ?? '');
    $customer_gst = trim($_POST['customer_gst'] ?? '');
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $gst_rate = floatval($_POST['gst_rate'] ?? 0);
    $gst_amount = floatval($_POST['gst_amount'] ?? 0);
    $grand_total = floatval($_POST['grand_total'] ?? 0);
    $amount_in_words = trim($_POST['amount_in_words'] ?? '');
    $items = $_POST['items'] ?? [];

    try {
        $pdo->beginTransaction();
        if ($invId) {
            $stmt = $pdo->prepare("UPDATE stat_invoices SET serial_no=?, bill_date=?, customer_name=?, customer_to=?, customer_gst=?, subtotal=?, gst_rate=?, gst_amount=?, grand_total=?, amount_in_words=?, quotation_id=? WHERE id=?");
            $stmt->execute([$serial_no, $bill_date, $customer_name, $customer_to, $customer_gst, $subtotal, $gst_rate, $gst_amount, $grand_total, $amount_in_words, $quotation_id, $invId]);
            $pdo->prepare("DELETE FROM stat_invoice_items WHERE invoice_id=?")->execute([$invId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO stat_invoices (quotation_id, serial_no, bill_date, customer_name, customer_to, customer_gst, subtotal, gst_rate, gst_amount, grand_total, amount_in_words) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$quotation_id, $serial_no, $bill_date, $customer_name, $customer_to, $customer_gst, $subtotal, $gst_rate, $gst_amount, $grand_total, $amount_in_words]);
            $invId = $pdo->lastInsertId();
        }

        if ($quotation_id) {
            $pdo->prepare("UPDATE stat_quotations SET status='invoiced' WHERE id=?")->execute([$quotation_id]);
        }

        $stmtItem = $pdo->prepare("INSERT INTO stat_invoice_items (invoice_id, particulars, qty, rate, amount) VALUES (?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            if (!empty($item['particulars'])) {
                $stmtItem->execute([$invId, $item['particulars'], $item['qty'], $item['rate'], $item['amount']]);
            }
        }
        $pdo->commit();
        header("Location: stat_dashboard.php?status=saved");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Error saving invoice: " . $e->getMessage());
    }
}
