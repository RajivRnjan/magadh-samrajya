<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {}

$isEdit = false;
$iId = null;
$iData = [
    'serial_no' => '',
    'bill_date' => date('Y-m-d'),
    'customer_name' => '',
    'customer_to' => '',
    'customer_gst' => '',
    'subtotal' => '0.00',
    'gst_rate' => '18.00',
    'gst_amount' => '0.00',
    'grand_total' => '0.00',
    'amount_in_words' => ''
];
$iItems = [];

if (isset($_GET['id'])) {
    $iId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM stat_invoices WHERE id = ?");
    $stmt->execute([$iId]);
    if ($row = $stmt->fetch()) {
        $iData = $row;
        $isEdit = true;
        
        $itemStmt = $pdo->prepare("SELECT * FROM stat_invoice_items WHERE invoice_id = ? ORDER BY id ASC");
    }
}

$quote_id = null;
if (isset($_GET['quote_id']) && !$isEdit) {
    $quote_id = (int)$_GET['quote_id'];
    $stmt = $pdo->prepare("SELECT * FROM stat_quotations WHERE id = ?");
    $stmt->execute([$quote_id]);
    if ($row = $stmt->fetch()) {
        $iData['customer_name'] = $row['customer_name'];
        $iData['customer_to'] = $row['customer_to'];
        $iData['customer_gst'] = $row['customer_gst'];
        $iData['subtotal'] = $row['subtotal'];
        $iData['gst_rate'] = $row['gst_rate'];
        $iData['gst_amount'] = $row['gst_amount'];
        $iData['grand_total'] = $row['grand_total'];
        $iData['amount_in_words'] = $row['amount_in_words'];
        
        $itemStmt = $pdo->prepare("SELECT * FROM stat_quotation_items WHERE quotation_id = ? ORDER BY id ASC");
        $itemStmt->execute([$quote_id]);
        $iItems = $itemStmt->fetchAll();
    }
}

if (!$isEdit) {
    $stmt = $pdo->query("SELECT serial_no FROM stat_invoices ORDER BY id DESC LIMIT 1");
    if ($row = $stmt->fetch()) {
        $iData['serial_no'] = str_pad(intval($row['serial_no']) + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $iData['serial_no'] = '0001';
    }
}

// Fetch unique values for datalists (from both quotations and invoices)
$clientNames = []; $clientAddresses = []; $clientGSTs = []; $itemParticulars = [];
try {
    $clientNames = $pdo->query("SELECT DISTINCT customer_name FROM stat_quotations WHERE customer_name != '' UNION SELECT DISTINCT customer_name FROM stat_invoices WHERE customer_name != ''")->fetchAll(PDO::FETCH_COLUMN);
    $clientAddresses = $pdo->query("SELECT DISTINCT customer_to FROM stat_quotations WHERE customer_to != '' UNION SELECT DISTINCT customer_to FROM stat_invoices WHERE customer_to != ''")->fetchAll(PDO::FETCH_COLUMN);
    $clientGSTs = $pdo->query("SELECT DISTINCT customer_gst FROM stat_quotations WHERE customer_gst != '' UNION SELECT DISTINCT customer_gst FROM stat_invoices WHERE customer_gst != ''")->fetchAll(PDO::FETCH_COLUMN);
    $itemParticulars = $pdo->query("SELECT DISTINCT particulars FROM stat_quotation_items WHERE particulars != '' UNION SELECT DISTINCT particulars FROM stat_invoice_items WHERE particulars != ''")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

function getSidebar($activePage) {
    global $settings;
    $menu = [
        ['Transport ERP', true],
        ['index.php', 'fa-chart-pie', 'Dashboard'],
        ['invoice.php', 'fa-plus-circle', 'Create Invoice'],
        ['all_invoices.php', 'fa-receipt', 'All Invoices'],
        ['Stationery ERP', true],
        ['stat_dashboard.php', 'fa-chart-pie', 'Stat Dashboard'],
        ['stat_quotation.php', 'fa-file-invoice', 'Create Quotation'],
        ['stat_all_quotations.php', 'fa-list-alt', 'All Quotations'],
        ['stat_invoice.php', 'fa-plus-square', 'Create Invoice'],
        ['stat_all_invoices.php', 'fa-receipt', 'All Invoices'],
        ['System', true],
        ['settings.php', 'fa-gears', 'Settings']
    ];

    $sidebar = '<aside class="sidebar"><div class="sidebar-header"><div class="sidebar-logo" style="background: transparent; box-shadow: none;"><img src="assets/mslogo.png" style="width: 100%; height: 100%; object-fit: contain;"></div><div class="sidebar-brand"><h3>'.htmlspecialchars($settings['company_name'] ?? 'MAGADH SAMARAJYA').'</h3><p>Billing Panel</p></div></div><ul class="sidebar-menu">';
            
    foreach ($menu as $item) {
        if (isset($item[1]) && $item[1] === true) {
            $sidebar .= '<li class="sidebar-header-title" style="padding: 10px 20px; font-size: 11px; text-transform: uppercase; color: #888; font-weight: 700; margin-top: 10px;">' . $item[0] . '</li>';
        } else {
            $activeClass = ($item[0] == $activePage) ? ' active' : '';
            $sidebar .= '<li class="sidebar-item' . $activeClass . '"><a href="' . $item[0] . '"><i class="fa-solid ' . $item[1] . '"></i><span>' . $item[2] . '</span></a></li>';
        }
    }
    
    $sidebar .= '</ul><div class="sidebar-footer"><div class="user-info"><div class="user-avatar">' . strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)) . '</div><div class="user-details"><h4>' . htmlspecialchars($_SESSION['name'] ?? 'Administrator') . '</h4><p>' . ucfirst(str_replace('_', ' ', $_SESSION['role'] ?? 'super_admin')) . '</p></div></div><a href="logout.php" class="logout-icon" title="Log Out"><i class="fa-solid fa-right-from-bracket"></i></a></div></aside>';
    return $sidebar;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Create'; ?> Invoice | Stationery ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="layout-wrapper">
        <?php echo getSidebar('stat_invoice.php'); ?>
        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <h1><?php echo $isEdit ? 'Edit Invoice' : 'New Invoice'; ?></h1>
                    <p><?php echo $isEdit ? 'Modify details of existing invoice.' : 'Fill in the information to generate a stationery invoice.'; ?></p>
                </div>
                <div>
                    <a href="stat_all_invoices.php" class="btn btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i> Back to Invoices
                    </a>
                </div>
            </header>
            
                        <datalist id="client_names_list">
                <?php foreach($clientNames as $v) echo '<option value="'.htmlspecialchars($v).'">'; ?>
            </datalist>
            <datalist id="client_gsts_list">
                <?php foreach($clientGSTs as $v) echo '<option value="'.htmlspecialchars($v).'">'; ?>
            </datalist>
                        <datalist id="client_addresses_list">
                <?php foreach($clientAddresses as $v) echo '<option value="'.htmlspecialchars($v).'">'; ?>
            </datalist>
            <datalist id="item_particulars_list">
                <?php foreach($itemParticulars as $v) echo '<option value="'.htmlspecialchars($v).'">'; ?>
            </datalist>
            
            <form id="invoiceForm" action="stat_save_invoice.php" method="POST">
                <input type="hidden" name="invoice_id" value="<?php echo $iId; ?>">
                <input type="hidden" name="quotation_id" value="<?php echo isset($quote_id) ? $quote_id : ($isEdit ? htmlspecialchars($iData['quotation_id'] ?? '') : ''); ?>">
                
                <div class="content-card">
                    <div class="card-header">
                        <h3>General Invoice Information</h3>
                    </div>
                    
                    <div class="invoice-meta-grid">
                        <div class="form-group">
                            <label for="serial_no">Invoice Number</label>
                            <input type="text" id="serial_no" name="serial_no" class="form-control" required value="<?php echo htmlspecialchars($iData['serial_no']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="bill_date">Invoice Date</label>
                            <input type="date" id="bill_date" name="bill_date" class="form-control" required value="<?php echo htmlspecialchars($iData['bill_date']); ?>">
                        </div>
                    </div>
                    
                    <div class="invoice-textarea-container">
                        <div class="form-group" style="margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <label for="customer_name" style="margin-bottom: 0;">Client Name *</label>
                                <div style="display: flex; align-items: center;">
                                    <label for="customer_gst" style="margin-bottom: 0; margin-right: 10px; font-weight: 500; font-size: 13px;">Client GSTIN:</label>
                                    <input type="text" id="customer_gst" name="customer_gst" list="client_gsts_list" autocomplete="off" class="form-control" style="width: 220px; padding: 6px 10px;" placeholder="GST Number..." value="<?php echo htmlspecialchars($iData['customer_gst']); ?>">
                                </div>
                            </div>
                            <input type="text" id="customer_name" name="customer_name" list="client_names_list" class="form-control" autocomplete="off" required placeholder="e.g. BHARAT FINANCIAL INCLUSION LIMITED" value="<?php echo htmlspecialchars($iData['customer_name']); ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="customer_to" style="margin-bottom: 8px;">Client Address *</label>
                            <input type="text" id="customer_to" name="customer_to" list="client_addresses_list" autocomplete="off" class="form-control" required placeholder="Vikash Nagar, Hazaribagh, Jharkhand – 825301" value="<?php echo htmlspecialchars($iData['customer_to']); ?>">
                        </div>
                    </div>
                </div>

                <div class="content-card invoice-builder-card">
                    <div class="items-table-header">
                        <h3>Particulars (Stationery Items)</h3>
                        <button type="button" class="btn btn-success" id="addRowBtn" style="padding: 8px 16px; font-size: 13px;">
                            <i class="fa-solid fa-plus"></i> Add Row
                        </button>
                    </div>
                    
                    <div class="table-responsive items-table-container">
                        <table class="items-table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 5%;" class="text-center">S.No.</th>
                                    <th style="width: 50%;">PARTICULARS</th>
                                    <th style="width: 10%;" class="text-right">QTY.</th>
                                    <th style="width: 15%;" class="text-right">RATE</th>
                                    <th style="width: 15%;" class="text-right">AMOUNT</th>
                                    <th style="width: 5%;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <?php if (count($iItems) > 0): ?>
                                    <?php foreach ($iItems as $idx => $item): ?>
                                        <tr class="item-row">
                                            <td class="text-center row-num"><?php echo $idx + 1; ?></td>
                                            <td><input type="text" name="items[<?php echo $idx; ?>][particulars]" value="<?php echo htmlspecialchars($item['particulars']); ?>" list="item_particulars_list" autocomplete="off" class="input-cell" required></td>
                                            <td><input type="number" name="items[<?php echo $idx; ?>][qty]" value="<?php echo $item['qty']; ?>" class="input-cell text-right num-input calc-qty" required></td>
                                            <td><input type="number" step="0.01" name="items[<?php echo $idx; ?>][rate]" value="<?php echo $item['rate']; ?>" class="input-cell text-right num-input calc-rate" required></td>
                                            <td><input type="number" step="0.01" name="items[<?php echo $idx; ?>][amount]" value="<?php echo $item['amount']; ?>" class="input-cell text-right num-input calc-amount" readonly></td>
                                            <td class="text-center">
                                                <button type="button" class="btn-icon btn-icon-delete delete-row-btn" title="Remove Row">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="item-row">
                                        <td class="text-center row-num">1</td>
                                        <td><input type="text" name="items[0][particulars]" list="item_particulars_list" autocomplete="off" class="input-cell" required></td>
                                        <td><input type="number" name="items[0][qty]" class="input-cell text-right num-input calc-qty" required></td>
                                        <td><input type="number" step="0.01" name="items[0][rate]" class="input-cell text-right num-input calc-rate" required></td>
                                        <td><input type="number" step="0.01" name="items[0][amount]" class="input-cell text-right num-input calc-amount" readonly></td>
                                        <td class="text-center">
                                            <button type="button" class="btn-icon btn-icon-delete delete-row-btn" title="Remove Row">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <!-- Subtotal -->
                                <tr class="total-row" style="background-color: rgba(0, 0, 0, 0.02);">
                                    <td colspan="4" class="text-right" style="font-weight: 500; font-size: 14px; border-bottom: 1px solid var(--border-color);">SUBTOTAL (BASE):</td>
                                    <td class="text-right" style="padding: 4px 8px; border-bottom: 1px solid var(--border-color);">
                                        <input type="number" step="0.01" name="subtotal" id="subtotal" value="<?php echo $iData['subtotal']; ?>" class="input-cell text-right num-input" readonly style="font-weight: 500; font-size: 14px; background: transparent; border: none;">
                                    </td>
                                    <td style="border-bottom: 1px solid var(--border-color);"></td>
                                </tr>
                                <!-- GST -->
                                <tr class="total-row" style="background-color: rgba(0, 0, 0, 0.02);">
                                    <td colspan="4" class="text-right" style="font-weight: 500; font-size: 14px; border-bottom: 1px solid var(--border-color);">
                                        GST RATE (%):
                                        <input type="number" step="0.01" name="gst_rate" id="gst_rate" class="input-cell text-right num-input" style="font-weight: 500; font-size: 14px; background-color: var(--bg-input); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; padding: 4px 8px; width: 80px; display: inline-block; margin-left: 10px;" value="<?php echo $iData['gst_rate']; ?>">
                                    </td>
                                    <td class="text-right" style="padding: 4px 8px; border-bottom: 1px solid var(--border-color);">
                                        <input type="number" step="0.01" name="gst_amount" id="gst_amount" value="<?php echo $iData['gst_amount']; ?>" class="input-cell text-right num-input" readonly style="font-weight: 500; font-size: 14px; background: transparent; border: none;">
                                    </td>
                                    <td style="border-bottom: 1px solid var(--border-color);"></td>
                                </tr>
                                <!-- Grand Total -->
                                <tr class="total-row" style="background-color: rgba(0, 0, 0, 0.04);">
                                    <td colspan="4" class="text-right" style="font-weight: 700; font-size: 15px;">GRAND TOTAL RS.</td>
                                    <td class="text-right" style="padding: 4px 8px;">
                                        <input type="number" step="0.01" name="grand_total" id="grand_total" value="<?php echo $iData['grand_total']; ?>" class="input-cell text-right num-input" readonly style="font-weight: 700; font-size: 15px; color: var(--color-success); background: transparent; border: none;">
                                    </td>
                                    <td></td>
                                </tr>
                                <tr class="total-words-row">
                                    <td colspan="6">
                                        <div style="display: flex; align-items: center; gap: 10px; width: 100%;">
                                            <span style="font-weight: 600; white-space: nowrap;">Amount in Words:</span>
                                            <input type="text" name="amount_in_words" id="amountInWords" class="input-cell" style="font-style: italic; background-color: rgba(255, 255, 255, 0.05); font-weight: 500;" readonly value="<?php echo htmlspecialchars($iData['amount_in_words']); ?>">
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="form-actions-bar">
                        <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-size: 15px;">
                            <i class="fa-solid fa-floppy-disk"></i> Save Invoice
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Prevent scrolling from changing number input values
            document.addEventListener("wheel", function(event){
                if(document.activeElement.type === "number"){
                    document.activeElement.blur();
                }
            }, { passive: false });

            const tableBody = document.getElementById('itemsBody');
            const addRowBtn = document.getElementById('addRowBtn');
            
            function numberToWords(amount) {
                if(amount === 0) return 'Zero Only';
                const a = ['','One ','Two ','Three ','Four ', 'Five ','Six ','Seven ','Eight ','Nine ','Ten ','Eleven ','Twelve ','Thirteen ','Fourteen ','Fifteen ','Sixteen ','Seventeen ','Eighteen ','Nineteen '];
                const b = ['', '', 'Twenty','Thirty','Forty','Fifty', 'Sixty','Seventy','Eighty','Ninety'];

                function inWords (num) {
                    if ((num = num.toString()).length > 9) return 'overflow';
                    let n = ('000000000' + num).substr(-9).match(/^(\d{2})(\d{2})(\d{2})(\d{1})(\d{2})$/);
                    if (!n) return; let str = '';
                    str += (n[1] != 0) ? (a[Number(n[1])] || b[n[1][0]] + ' ' + a[n[1][1]]) + 'Crore ' : '';
                    str += (n[2] != 0) ? (a[Number(n[2])] || b[n[2][0]] + ' ' + a[n[2][1]]) + 'Lakh ' : '';
                    str += (n[3] != 0) ? (a[Number(n[3])] || b[n[3][0]] + ' ' + a[n[3][1]]) + 'Thousand ' : '';
                    str += (n[4] != 0) ? (a[Number(n[4])] || b[n[4][0]] + ' ' + a[n[4][1]]) + 'Hundred ' : '';
                    str += (n[5] != 0) ? ((str != '') ? 'and ' : '') + (a[Number(n[5])] || b[n[5][0]] + ' ' + a[n[5][1]]) : '';
                    return str;
                }
                return 'Rupees ' + inWords(amount) + 'Only';
            }

            function updateTotals() {
                let subtotal = 0;
                document.querySelectorAll('.calc-amount').forEach(el => {
                    subtotal += parseFloat(el.value || 0);
                });
                document.getElementById('subtotal').value = subtotal.toFixed(2);
                let gstRate = parseFloat(document.getElementById('gst_rate').value || 18);
                let gstAmount = subtotal * (gstRate / 100);
                document.getElementById('gst_amount').value = gstAmount.toFixed(2);
                let grandTotal = subtotal + gstAmount;
                document.getElementById('grand_total').value = grandTotal.toFixed(2);
                document.getElementById('amountInWords').value = numberToWords(Math.round(grandTotal));
            }

            tableBody.addEventListener('input', (e) => {
                if (e.target.classList.contains('calc-qty') || e.target.classList.contains('calc-rate')) {
                    let tr = e.target.closest('tr');
                    let qty = parseFloat(tr.querySelector('.calc-qty').value || 0);
                    let rate = parseFloat(tr.querySelector('.calc-rate').value || 0);
                    tr.querySelector('.calc-amount').value = (qty * rate).toFixed(2);
                    updateTotals();
                }
            });

            document.getElementById('gst_rate').addEventListener('input', updateTotals);

            tableBody.addEventListener('click', (e) => {
                if (e.target.closest('.delete-row-btn')) {
                    if (tableBody.children.length > 1) {
                        e.target.closest('tr').remove();
                        let rows = tableBody.querySelectorAll('tr');
                        rows.forEach((row, idx) => {
                            row.querySelector('.row-num').innerText = idx + 1;
                            // Update input names index
                            row.querySelectorAll('input').forEach(input => {
                                let name = input.getAttribute('name');
                                if(name) {
                                    input.setAttribute('name', name.replace(/\[\d+\]/, '[' + idx + ']'));
                                }
                            });
                        });
                        updateTotals();
                    }
                }
            });

            addRowBtn.addEventListener('click', () => {
                let tr = document.createElement('tr');
                tr.className = 'item-row';
                let idx = tableBody.children.length;
                tr.innerHTML = `
                    <td class="text-center row-num">${idx + 1}</td>
                    <td><input type="text" name="items[${idx}][particulars]" list="item_particulars_list" autocomplete="off" class="input-cell" required></td>
                    <td><input type="number" name="items[${idx}][qty]" class="input-cell text-right num-input calc-qty" required></td>
                    <td><input type="number" step="0.01" name="items[${idx}][rate]" class="input-cell text-right num-input calc-rate" required></td>
                    <td><input type="number" step="0.01" name="items[${idx}][amount]" class="input-cell text-right num-input calc-amount" readonly></td>
                    <td class="text-center">
                        <button type="button" class="btn-icon btn-icon-delete delete-row-btn" title="Remove Row">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
        });
    </script>
</body>
</html>
