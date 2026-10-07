<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request. Invoice ID required.");
}

$invoiceId = intval($_GET['id']);

// Fetch settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {}

// Fetch invoice details
try {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? LIMIT 1");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        die("Invoice not found in system database.");
    }
    
    // Fetch items
    $itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $itemsStmt->execute([$invoiceId]);
    $items = $itemsStmt->fetchAll();
    
    // Fetch branch info
    $branch = null;
    if (!empty($invoice['branch_id'])) {
        $branchStmt = $pdo->prepare("SELECT * FROM branches WHERE id = ? LIMIT 1");
        $branchStmt->execute([$invoice['branch_id']]);
        $branch = $branchStmt->fetch();
    }
    
} catch (PDOException $e) {
    die("Database query failure: " . $e->getMessage());
}

// Format default business information if empty in settings/branch
$companyName = !empty($settings['company_name']) ? $settings['company_name'] : 'MAGADH SAMARAJYA';
$gstin = !empty($branch['gstin']) ? $branch['gstin'] : (!empty($settings['gstin']) ? $settings['gstin'] : '20EQBPK5133G1ZA');
$address = !empty($branch['address']) ? $branch['address'] : (!empty($settings['address']) ? $settings['address'] : 'Bhadodih, Diwan Garden, Jhumri Telaiya, Koderma - 825409, Jharkhand');
$mobiles = !empty($branch['mobiles']) ? $branch['mobiles'] : (!empty($settings['mobiles']) ? $settings['mobiles'] : '88253 51729, 94702 20659');

$minRows = 13; // Maintain uniform heights like pre-printed note pads
$itemsCount = count($items);

// Check if any item has a delivery date — if so, show the column in print
$hasDeliveryDate = false;
foreach ($items as $it) {
    if (!empty($it['delivery_date'])) {
        $hasDeliveryDate = true;
        break;
    }
}

// Check if any item has an item date — if so, show the DATE column in print
$hasItemDate = false;
foreach ($items as $it) {
    if (!empty($it['item_date'])) {
        $hasItemDate = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Invoice #<?php echo htmlspecialchars($invoice['serial_no']); ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom print style sheet -->
    <link rel="icon" type="image/png" href="assets/mslogo.png">
    <link rel="stylesheet" href="assets/css/print.css?v=<?php echo time(); ?>">
</head>
<body>
    
    <!-- Screen View Action Control Bar -->
    <div class="action-bar">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-file-pdf" style="font-size: 20px; color: #a5b4fc;"></i>
            <span style="font-weight: 600;">Print Preview: Invoice #<?php echo htmlspecialchars($invoice['serial_no']); ?></span>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="action-btn btn-back" onclick="window.close(); if(window.opener){window.opener.focus();} else {window.location.href='index.php';}">
                <i class="fa-solid fa-arrow-left"></i> Close / Back
            </button>
            <button class="action-btn btn-print" onclick="window.print();">
                <i class="fa-solid fa-download"></i> Download PDF / Print
            </button>
        </div>
    </div>
    
    <!-- Physical Receipt Paper Outline Box -->
    <div class="print-preview-container">
        <div class="bill-wrapper">
            
            <!-- GST & Mobiles Top Bar -->
            <div class="bill-top-meta">
                <span>GSTIN - <?php echo htmlspecialchars($gstin); ?></span>
                <span>&nbsp;</span>
            </div>
            
            <!-- Main Ledger Header -->
            <header class="bill-header-row">
                <!-- Circular MS Logo -->
                <div class="bill-logo-box" style="border: none;">
                    <img src="assets/mslogo.png" alt="MS Logo" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                
                <!-- Business Name & Details -->
                <div class="bill-title-details">
                    <h1><?php echo htmlspecialchars($companyName); ?></h1>
                    <p><?php echo nl2br(htmlspecialchars($address)); ?></p>
                    <p style="font-weight: 600; margin-top: 2px; font-size: 11px;">Mob. : <?php echo htmlspecialchars($mobiles); ?></p>
                </div>
                
                <!-- Invoice Serial, Month, Date fields -->
                <div class="bill-right-meta">
                    <div class="meta-field">
                        <span class="meta-label">Invoice No.</span>
                        <div class="meta-dots serial-no-value"><?php echo htmlspecialchars($invoice['serial_no']); ?></div>
                    </div>
                    <div class="meta-field">
                        <span class="meta-label">Month</span>
                        <div class="meta-dots"><?php echo htmlspecialchars($invoice['bill_month']); ?></div>
                    </div>
                    <div class="meta-field">
                        <span class="meta-label">Bill Date</span>
                        <div class="meta-dots"><?php echo date('d-m-Y', strtotime($invoice['bill_date'])); ?></div>
                    </div>
                </div>
            </header>
            
            <!-- Customer To Box -->
            <div class="bill-to-row" style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div style="display: flex; flex: 1;">
                    <span class="bill-to-label">To</span>
                    <div class="bill-to-dots">
                        <?php echo nl2br(htmlspecialchars($invoice['customer_to'])); ?>
                    </div>
                </div>
                <?php if (!empty($invoice['customer_gst'])): ?>
                <div style="text-align: right; font-size: 11px; font-weight: 700; white-space: nowrap; padding-left: 10px;">
                    GSTIN: <?php echo htmlspecialchars($invoice['customer_gst']); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Items Data Grid Table -->
            <table class="bill-table">
                <thead>
                    <tr>
                        <?php if ($hasItemDate): ?>
                        <th class="col-date">DATE</th>
                        <?php endif; ?>
                        <th class="col-cnote">C/NOTE</th>
                        <th class="col-dest">DEST.</th>
                        <?php if ($hasDeliveryDate): ?>
                        <th class="col-deldate">DELIVERY DATE</th>
                        <?php endif; ?>
                        <th class="col-pkts">PKTS.</th>
                        <th class="col-wt">WT.</th>
                        <th class="col-amount">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Loop to guarantee a consistent size of empty ledger rows
                    $totalPrintRows = max($minRows, $itemsCount);
                    for ($i = 0; $i < $totalPrintRows; $i++): 
                        if ($i < $itemsCount):
                            $item = $items[$i];
                    ?>
                        <tr class="item-row">
                                <?php if ($hasItemDate): ?>
                                <td class="col-date"><?php echo !empty($item['item_date']) ? date('d-m-Y', strtotime($item['item_date'])) : '&nbsp;'; ?></td>
                                <?php endif; ?>
                                <td class="col-cnote"><?php echo htmlspecialchars($item['c_note']); ?></td>
                                <td class="col-dest"><?php echo htmlspecialchars($item['destination']); ?></td>
                                <?php if ($hasDeliveryDate): ?>
                                <td class="col-deldate"><?php echo !empty($item['delivery_date']) ? date('d-m-Y', strtotime($item['delivery_date'])) : '&nbsp;'; ?></td>
                                <?php endif; ?>
                                <td class="col-pkts"><?php echo intval($item['packets']) > 0 ? htmlspecialchars($item['packets']) : '&nbsp;'; ?></td>
                                <td class="col-wt"><?php echo floatval($item['weight']) > 0 ? number_format($item['weight'], 2) : '&nbsp;'; ?></td>
                                <td class="col-amount"><?php echo floatval($item['amount']) > 0 ? number_format($item['amount'], 2) : '&nbsp;'; ?></td>
                            </tr>
                    <?php 
                        else: 
                            // Empty spacer row matching style of receipt pad
                    ?>
                            <tr class="item-row">
                                <?php if ($hasItemDate): ?>
                                <td class="col-date">&nbsp;</td>
                                <?php endif; ?>
                                <td class="col-cnote">&nbsp;</td>
                                <td class="col-dest">&nbsp;</td>
                                <?php if ($hasDeliveryDate): ?>
                                <td class="col-deldate">&nbsp;</td>
                                <?php endif; ?>
                                <td class="col-pkts">&nbsp;</td>
                                <td class="col-wt">&nbsp;</td>
                                <td class="col-amount">&nbsp;</td>
                            </tr>
                    <?php 
                        endif; 
                    endfor; 
                    ?>
                    
                    <!-- Subtotal Row -->
                    <?php 
                    $taxable = $invoice['total_amount'] / 1.18;
                    $fuelAmount = floatval($invoice['fuel_amount'] ?? 0.00);
                    $subtotal = $taxable - $fuelAmount;
                    $fuelPct = ($subtotal > 0 && $fuelAmount > 0) ? round(($fuelAmount / $subtotal) * 100, 2) : 0;
                    $cgst = $taxable * 0.09;
                    $sgst = $taxable * 0.09;
                    $totalCols = 4 + ($hasItemDate ? 1 : 0) + ($hasDeliveryDate ? 1 : 0);
                    ?>
                    <tr class="subtotal-row" style="border-top: 2px solid #000;">
                        <td colspan="<?php echo $totalCols; ?>" style="text-align: right; border-right: 1px solid #000; padding-right: 10px; font-weight: 700; font-size: 11px; height: 26px;">SUBTOTAL (BASE)</td>
                        <td class="col-amount" style="font-weight: 700; font-size: 12px; height: 26px;">
                            <?php echo number_format($subtotal, 2); ?>
                        </td>
                    </tr>
                    <!-- Fuel Amount Row -->
                    <tr class="fuel-row" style="border-top: 1px solid #000;">
                        <td colspan="<?php echo $totalCols; ?>" style="text-align: right; border-right: 1px solid #000; padding-right: 10px; font-weight: 700; font-size: 11px; height: 26px;">FUEL AMOUNT<?php if ($fuelPct > 0): ?> (<?php echo $fuelPct; ?>%)<?php endif; ?></td>
                        <td class="col-amount" style="font-weight: 700; font-size: 12px; height: 26px;">
                            <?php echo number_format($fuelAmount, 2); ?>
                        </td>
                    </tr>
                    <!-- Total Taxable Value Row -->
                    <tr class="taxable-row" style="border-top: 1px solid #000;">
                        <td colspan="<?php echo $totalCols; ?>" style="text-align: right; border-right: 1px solid #000; padding-right: 10px; font-weight: 700; font-size: 11px; height: 26px;">TOTAL TAXABLE VALUE</td>
                        <td class="col-amount" style="font-weight: 700; font-size: 12px; height: 26px;">
                            <?php echo number_format($taxable, 2); ?>
                        </td>
                    </tr>
                    <!-- CGST Row -->
                    <tr class="cgst-row" style="border-top: 1px solid #000;">
                        <td colspan="<?php echo $totalCols; ?>" style="text-align: right; border-right: 1px solid #000; padding-right: 10px; font-weight: 700; font-size: 11px; height: 26px;">CGST @ 9%</td>
                        <td class="col-amount" style="font-weight: 700; font-size: 12px; height: 26px;">
                            <?php echo number_format($cgst, 2); ?>
                        </td>
                    </tr>
                    <!-- SGST Row -->
                    <tr class="sgst-row" style="border-top: 1px solid #000;">
                        <td colspan="<?php echo $totalCols; ?>" style="text-align: right; border-right: 1px solid #000; padding-right: 10px; font-weight: 700; font-size: 11px; height: 26px;">SGST @ 9%</td>
                        <td class="col-amount" style="font-weight: 700; font-size: 12px; height: 26px;">
                            <?php echo number_format($sgst, 2); ?>
                        </td>
                    </tr>
                    <!-- Grand Total Sum Row -->
                    <tr class="total-row" style="border-top: 2px solid #000; border-bottom: 2px solid #000;">
                        <td colspan="<?php echo $totalCols; ?>" style="text-align: right; border-right: 1px solid #000; padding-right: 10px; font-weight: 800; font-size: 12px; height: 28px;">GRAND TOTAL RS.</td>
                        <td class="col-amount" style="font-weight: 800; font-size: 13px; height: 28px;">
                            <?php echo number_format($invoice['total_amount'], 2); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Words & Authority Signature Footer -->
            <footer class="bill-footer">
                <div class="bill-footer-left">
                    <div class="words-wrapper">
                        <span class="words-label">RUPEES IN WORDS</span>
                        <div class="words-text-container">
                            <div class="words-lines-background">
                                <div class="words-bg-line"></div>
                                <div class="words-bg-line"></div>
                            </div>
                            <div class="words-text">
                                <?php echo htmlspecialchars($invoice['amount_in_words']); ?> Only
                            </div>
                        </div>
                    </div>
                    <div class="bank-details" style="font-size: 11px; font-weight: 600; line-height: 1.4; margin-top: 5px;">
                        Please pay the cheque/ DD in Favour of A/CNo : 7940458835, <br>IFSC Code : IDIB000H036<br>
                        MAGADHSAMRAJYA ALLAHABAD BANK HAZARIBAGH
                    </div>
                </div>
                
                <div class="bill-footer-right">
                    <div>
                        <div style="font-weight: 800; font-size: 11px; margin-bottom: 2px;">E. & O.E.</div>
                        <div class="for-tag">For : <?php echo htmlspecialchars($companyName); ?></div>
                    </div>
                    <div style="margin: 5px 0;">
                        <img src="assets/stamp.png" alt="Stamp" style="max-height: 60px; object-fit: contain;">
                    </div>
                    <span class="sign-tag">(Auth. Signatory)</span>
                </div>
            </footer>
            
        </div>
    </div>
    
</body>
</html>
