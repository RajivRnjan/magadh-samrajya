<?php
require_once __DIR__ . '/db.php';
try {
    $pdo->exec("ALTER TABLE stat_quotations ADD COLUMN customer_name VARCHAR(255) NULL AFTER quotation_date");
    $pdo->exec("ALTER TABLE stat_quotations ADD COLUMN customer_gst VARCHAR(50) NULL AFTER customer_to");
    $pdo->exec("ALTER TABLE stat_invoices ADD COLUMN customer_name VARCHAR(255) NULL AFTER bill_date");
    $pdo->exec("ALTER TABLE stat_invoices ADD COLUMN customer_gst VARCHAR(50) NULL AFTER customer_to");
    echo "Migration successful.";
} catch (PDOException $e) {
    echo "Error or already exists: " . $e->getMessage();
}
