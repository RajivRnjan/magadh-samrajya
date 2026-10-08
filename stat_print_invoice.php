<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = "INVOICE / BILL";

if ($id <= 0) die("Invalid ID.");

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {}

try {
    $isInvoice = (strpos($type, 'INVOICE') !== false);
    $table = $isInvoice ? 'stat_invoices' : 'stat_quotations';
    $itemTable = $isInvoice ? 'stat_invoice_items' : 'stat_quotation_items';
    $idCol = $isInvoice ? 'invoice_id' : 'quotation_id';
    $dateCol = $isInvoice ? 'bill_date' : 'quotation_date';

    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc) die("Document not found.");
    
    $stmtItems = $pdo->prepare("SELECT * FROM $itemTable WHERE $idCol = ? ORDER BY id ASC");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll();
} catch (PDOException $e) {
    die("Error fetching data.");
}

$to_name = $doc['customer_name'] ?? '';
$to_rest = $doc['customer_to'] ?? '';
$customer_gstin = $doc['customer_gst'] ?? '';

// Fallback for older documents before schema change
if (empty($to_name) && !empty($doc['customer_to'])) {
    $to_address = $doc['customer_to'];
    if (empty($customer_gstin) && preg_match('/GSTIN:\s*([A-Z0-9]+)/i', $to_address, $matches)) {
        $customer_gstin = $matches[1];
        $to_address = preg_replace('/GSTIN:\s*[A-Z0-9]+/i', '', $to_address);
    }
    $to_lines = array_filter(array_map('trim', explode("\n", $to_address)));
    $to_name = array_shift($to_lines);
    $to_rest = implode(", ", $to_lines);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
        <title><?php echo $type; ?> - <?php echo $doc['serial_no']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .action-bar-wrapper { width: 100%; display: flex; justify-content: center; margin-bottom: 20px; }
        .action-bar { width: 210mm; background: #fff; border-radius: 8px; padding: 15px 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; }
        .action-btn { background: #fff; border: 1px solid #e5e7eb; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 8px; font-weight: 600; color: #374151; font-family: Arial, sans-serif; transition: 0.2s; }
        .action-btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .btn-print { background: #4f46e5; color: white; border: none; }
        .btn-print:hover { background: #4338ca; }
        @media print {
            .action-bar-wrapper { display: none !important; }
            body { background: white; padding: 0; }
        }
        body { margin: 0; padding: 20px; font-family: 'Calibri', 'Helvetica', 'Arial', sans-serif; background: #525659; display: flex; flex-direction: column; align-items: center; }
        .page { background: white; width: 210mm; min-height: 297mm; padding: 10mm; box-sizing: border-box; position: relative; }
        @media print {
            body { background: white; padding: 0; }
            .page { width: 100%; box-shadow: none; margin: 0; padding: 5mm 8mm !important; }
            @page { size: A4; margin: 10mm; }
        }
        
        .header-title { text-align: center; font-size: 20px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
        
        .header-box { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        
        .logo-box { width: 120px; }
        .logo-box img { max-width: 100%; height: auto; }
        
        .company-box { text-align: center; flex: 1; }
        .company-box h1 { font-size: 28px; margin: 0; letter-spacing: 0.5px; font-weight: bold; text-transform: uppercase; }
        .company-box p { margin: 2px 0; font-size: 15px; }
        
        .info-box { border: 2px solid #000; padding: 5px 10px; width: 160px; text-align: left; }
        .info-box p { margin: 5px 0; font-size: 15px; }
        
        .customer-box { border-bottom: 2px solid #000; padding: 5px 0; display: flex; justify-content: space-between; font-size: 15px; margin-bottom: 15px; }
        .customer-left { width: 65%; }
        .customer-right { width: 35%; text-align: right; }
        
        table { width: 100%; border-collapse: collapse; border: 2px solid #000; margin-bottom: 0; }
        th, td { border: 1px solid #000; padding: 5px 8px; }
        th { font-weight: bold; text-align: center; font-size: 15px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .items-row td { height: 350px; vertical-align: top; font-size: 15px; border-bottom: 2px solid #000; }
        
        
        
        .footer-box { display: flex; justify-content: space-between; font-size: 15px; }
        .terms-box p { margin: 3px 0; }
        
        .sign-box { text-align: center; position: relative; width: 250px; }
        .sign-box h3 { color: blue; font-size: 18px; margin: 0; margin-bottom: 10px; }
        .sign-box p { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="action-bar-wrapper">
        <div class="action-bar">
            <div style="display: flex; align-items: center; gap: 10px; font-family: Arial, sans-serif;">
                <i class="fa-solid fa-file-pdf" style="font-size: 20px; color: #a5b4fc;"></i>
                <span style="font-weight: 600; font-size: 15px; color: #1f2937;">Print Preview: Invoice #<?php echo htmlspecialchars($doc['serial_no']); ?></span>
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
    </div>
    <div class="page">
        <div class="header-title"><?php echo $type; ?></div>
        
        <div class="header-box">
            <div class="logo-box">
                <img src="assets/mslogo.png" alt="Logo">
            </div>
            <div class="company-box">
                <h1><?php echo htmlspecialchars($settings['company_name']); ?></h1>
                <p>Bhadodih, Diwan Garden, Jhumri Telaiya, Koderma - 825409, Jharkhand<br>
                Bansilal chowk, Hazaribagh, Jharkhand, 825301</p>
                <p>Mob. : 88253 51729, 94702 20659</p>
            </div>
            <div class="info-box">
                <p>Invoice No. <?php echo htmlspecialchars($doc['serial_no']); ?></p>
                <div style="border-bottom: 1px dotted #000; margin-bottom: 5px;"></div>
                <p>Bill Date <?php echo date('d-m-Y', strtotime($doc[$dateCol])); ?></p>
                <div style="border-bottom: 1px dotted #000;"></div>
            </div>
        </div>
        
        <div class="customer-box">
            <div class="customer-left">
                <p style="margin: 0;"><strong>To, <?php echo htmlspecialchars($to_name); ?></strong></p>
                <p style="margin: 3px 0; margin-left: 25px;"><?php echo htmlspecialchars($to_rest); ?></p>
                <div style="border-bottom: 1px dotted #000; margin-bottom: 5px; margin-left: 25px; width: 80%;"></div>
                <?php if($customer_gstin): ?>
                <p style="margin: 0; margin-left: 25px;"><strong>GSTIN: <?php echo htmlspecialchars($customer_gstin); ?></strong></p>
                <?php endif; ?>
            </div>
            <div class="customer-right">
                <p style="margin: 0;">GSTIN: <?php echo htmlspecialchars($settings['gstin']); ?></p>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th width="8%">S.No.</th>
                    <th width="52%">PARTICULARS (STATIONERY ITEMS)</th>
                    <th width="12%">QTY.</th>
                    <th width="14%">RATE</th>
                    <th width="14%">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <tr class="items-row">
                    <td class="text-center">
                        <?php foreach($items as $i => $it) { echo ($i+1) . "<br><br>"; } ?>
                    </td>
                    <td>
                        <?php foreach($items as $it) { echo htmlspecialchars($it['particulars']) . "<br><br>"; } ?>
                    </td>
                    <td class="text-center">
                        <?php foreach($items as $it) { echo htmlspecialchars($it['qty']) . "<br><br>"; } ?>
                    </td>
                    <td class="text-right">
                        <?php foreach($items as $it) { echo number_format($it['rate'], 2) . "<br><br>"; } ?>
                    </td>
                    <td class="text-right">
                        <?php foreach($items as $it) { echo number_format($it['amount'], 2) . "<br><br>"; } ?>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right" style="border-right: 1px solid #000; font-weight: bold; font-size: 15px;">SUBTOTAL (BASE):</td>
                    <td class="text-right" style="font-weight: bold; font-size: 15px;"><?php echo number_format($doc['subtotal'], 2); ?></td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right" style="border-right: 1px solid #000; font-weight: bold; font-size: 15px;">GST @ <?php echo rtrim(rtrim($doc['gst_rate'], '0'), '.'); ?>%:</td>
                    <td class="text-right" style="font-weight: bold; font-size: 15px;"><?php echo number_format($doc['gst_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right" style="border-right: 1px solid #000; font-weight: bold; font-size: 15px;">GRAND TOTAL RS.:</td>
                    <td class="text-right" style="font-weight: bold; font-size: 15px;"><?php echo number_format($doc['grand_total'], 2); ?></td>
                </tr>
                <tr>
                    <td colspan="5" style="border-top: 2px solid #000; padding: 8px; font-weight: bold; font-size: 15px;">
                        RUPEES IN WORDS: <?php echo htmlspecialchars($doc['amount_in_words']); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
        
        <div class="footer-box">
            <div class="terms-box">
                <p>Please pay the cheque/ DD in Favour of</p>
                <p>A/CNo: 1940458835, IFSC Code: IDIB000H036,</p>
                <p>MAGADHSAMRAJYA ALLAHABAD BANK HAZARIBAGH</p>
                <p style="font-weight:bold; margin-top:5px;">TERMS & CONDITIONS:</p>
                <p>1. Goods once sold will not be taken back or exchanged.</p>
                <p>2. Subject to Hazaribagh Jurisdiction only.</p>
                <p>3. Payment due within 15 days of invoice.</p>
                <p>E. & O.E.</p>
            </div>
            <div class="sign-box">
                
                <img src="assets/stamp.png" style="width:200px; max-height:80px; object-fit:contain;" onerror="this.style.display='none'">
                <p style="margin-top:20px;">For: MAGADH SAMRAJYA</p>
                <p>(Auth. Signatory)</p>
            </div>
        </div>
        
    </div>
</body>
</html>