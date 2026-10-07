<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $csrfToken = $_GET['csrf_token'] ?? '';
    if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        header("HTTP/1.1 403 Forbidden");
        exit("CSRF token validation failed. Unauthorized request.");
    }
    $invoiceId = intval($_GET['id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
        $stmt->execute([$invoiceId]);
        $redirectUrl = 'index.php?status=deleted';
        if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'all_invoices.php') !== false) {
            $refererParts = parse_url($_SERVER['HTTP_REFERER']);
            $queryStr = isset($refererParts['query']) ? '?' . $refererParts['query'] : '';
            if ($queryStr === '') {
                $redirectUrl = 'all_invoices.php?status=deleted';
            } else {
                $queryStr = preg_replace('/[?&]status=[^&]*/', '', $queryStr);
                $connector = (strpos($queryStr, '?') === false) ? '?' : '&';
                $redirectUrl = 'all_invoices.php' . $queryStr . $connector . 'status=deleted';
            }
        }
        header("Location: " . $redirectUrl);
        exit();
    } catch (PDOException $e) {
        die("Error deleting invoice: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit();
}
?>
