<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qId = !empty($_POST['quotation_id']) ? (int)$_POST['quotation_id'] : null;
    $serial_no = trim($_POST['serial_no'] ?? '');
    $quotation_date = trim($_POST['quotation_date'] ?? '');
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
        if ($qId) {
            $stmt = $pdo->prepare("UPDATE stat_quotations SET serial_no=?, quotation_date=?, customer_name=?, customer_to=?, customer_gst=?, subtotal=?, gst_rate=?, gst_amount=?, grand_total=?, amount_in_words=? WHERE id=?");
            $stmt->execute([$serial_no, $quotation_date, $customer_name, $customer_to, $customer_gst, $subtotal, $gst_rate, $gst_amount, $grand_total, $amount_in_words, $qId]);
            $pdo->prepare("DELETE FROM stat_quotation_items WHERE quotation_id=?")->execute([$qId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO stat_quotations (serial_no, quotation_date, customer_name, customer_to, customer_gst, subtotal, gst_rate, gst_amount, grand_total, amount_in_words) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$serial_no, $quotation_date, $customer_name, $customer_to, $customer_gst, $subtotal, $gst_rate, $gst_amount, $grand_total, $amount_in_words]);
            $qId = $pdo->lastInsertId();
        }

        $stmtItem = $pdo->prepare("INSERT INTO stat_quotation_items (quotation_id, particulars, qty, rate, amount) VALUES (?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            if (!empty($item['particulars'])) {
                $stmtItem->execute([$qId, $item['particulars'], $item['qty'], $item['rate'], $item['amount']]);
            }
        }
        $pdo->commit();
        header("Location: stat_dashboard.php?status=saved");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Error saving quotation: " . $e->getMessage());
    }
}
