<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// Fetch settings & branches
$settings = [];
$branches = [];
try {
    $stmt = $pdo->query("SELECT * FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $stmtB = $pdo->query("SELECT * FROM branches ORDER BY id ASC");
    $branches = $stmtB->fetchAll();
} catch (PDOException $e) {}

$isEdit = false;
$invoiceId = null;
$invoiceData = [
    'branch_id' => 1,
    'serial_no' => '',
    'bill_date' => date('Y-m-d'),
    'bill_month' => date('F Y'),
    'customer_to' => '',
    'total_amount' => '0.00',
    'fuel_amount' => '0.00',
    'amount_in_words' => ''
];
$invoiceItems = [];

// Check if edit mode
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $invoiceId = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? LIMIT 1");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
        
        if ($invoice) {
            $isEdit = true;
            $invoiceData = $invoice;
            
            // Fetch items
            $itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
            $itemsStmt->execute([$invoiceId]);
            $invoiceItems = $itemsStmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error = "Error loading invoice: " . $e->getMessage();
    }
} else {
    // Attempt to auto-generate the next serial number
    try {
        $serialStmt = $pdo->query("SELECT serial_no FROM invoices ORDER BY id DESC LIMIT 1");
        $lastSerial = $serialStmt->fetchColumn();
        $prefix = 'MACS/HZB';
        if ($lastSerial && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastSerial, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
            $invoiceData['serial_no'] = $prefix . $nextNumber;
        } else {
            // Default starting serial number
            $invoiceData['serial_no'] = 'MACS/HZB191';
        }
    } catch (PDOException $e) {}
}

// Extract selected month and year for billing selection
$selectedMonth = '';
$selectedYear = '';
if (!empty($invoiceData['bill_month'])) {
    $parts = explode(' ', $invoiceData['bill_month']);
    if (count($parts) >= 2) {
        $selectedMonth = $parts[0];
        $selectedYear = $parts[1];
    }
}
if (empty($selectedMonth)) {
    $selectedMonth = date('F');
}
if (empty($selectedYear)) {
    $selectedYear = date('Y');
}

$statusMessage = '';
$statusType = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'saved') {
        $statusMessage = 'Invoice saved successfully!';
        $statusType = 'success';
    } elseif ($_GET['status'] == 'error') {
        $statusMessage = 'An error occurred while saving the invoice.';
        $statusType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit Invoice' : 'Create Invoice'; ?> | Invoice ERP</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Style -->
    <link rel="icon" type="image/png" href="assets/mslogo.png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Mobile Navigation Header -->
    <div class="mobile-header">
        <button id="mobileMenuToggle" class="menu-toggle-btn">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="mobile-brand">
            <img src="assets/mslogo.png" alt="Logo" style="height: 24px; width: 24px; object-fit: contain;">
            <span>MS ERP</span>
        </div>
        <div style="width: 36px;"></div>
    </div>

    <!-- Sidebar Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- Desktop Sidebar Toggle Button (Three Lines) -->
    <button id="desktopSidebarToggle" class="sidebar-toggle-btn" title="Toggle Sidebar">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="layout-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo" style="background: transparent; box-shadow: none;">
                    <img src="assets/mslogo.png" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <div class="sidebar-brand">
                    <h3><?php echo htmlspecialchars($settings['company_name'] ?? 'MAGADH SAMARAJYA'); ?></h3>
                    <p>Billing Panel</p>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="index.php">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-item <?php echo !$isEdit ? 'active' : ''; ?>">
                    <a href="invoice.php">
                        <i class="fa-solid fa-plus-circle"></i>
                        <span>Create Invoice</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="all_invoices.php">
                        <i class="fa-solid fa-receipt"></i>
                        <span>All Invoices</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="settings.php">
                        <i class="fa-solid fa-gears"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['name'] ?? 'Administrator'); ?></h4>
                        <p><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'] ?? 'super_admin')); ?></p>
                    </div>
                </div>
                <a href="logout.php" class="logout-icon" title="Log Out">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </aside>
        
        <!-- Main Content Area -->
        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <h1><?php echo $isEdit ? 'Edit Invoice' : 'New Invoice'; ?></h1>
                    <p><?php echo $isEdit ? 'Modify details of existing invoice.' : 'Fill in the information to generate a billing receipt.'; ?></p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </header>
            
            <?php if (!empty($statusMessage)): ?>
                <div class="alert alert-<?php echo $statusType; ?>">
                    <i class="fa-solid fa-circle-check alert-icon"></i>
                    <span><?php echo htmlspecialchars($statusMessage); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Invoice Form -->
            <form id="invoiceForm" action="save_invoice.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="invoice_id" value="<?php echo $invoiceId; ?>">
                <?php endif; ?>
                
                <div class="content-card">
                    <div class="card-header">
                        <h3>General Billing Information</h3>
                    </div>
                    
                    <div class="invoice-meta-grid">
                        <div class="form-group">
                            <label for="branch_id">Billing Office Address</label>
                            <select name="branch_id" id="branch_id" class="form-control" required style="color: var(--text-main); background-color: var(--bg-input);">
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?php echo $b['id']; ?>" <?php echo (intval($invoiceData['branch_id']) === intval($b['id'])) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['branch_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="serial_no">Invoice Number</label>
                            <input type="text" id="serial_no" class="form-control" disabled value="<?php echo htmlspecialchars($invoiceData['serial_no']); ?>" style="opacity: 0.7; cursor: not-allowed;">
                            <input type="hidden" name="serial_no" value="<?php echo htmlspecialchars($invoiceData['serial_no']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="bill_date">Bill Date</label>
                            <input type="date" id="bill_date" name="bill_date" class="form-control" required value="<?php echo htmlspecialchars($invoiceData['bill_date']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Month & Year</label>
                            <div style="display: flex; gap: 10px;">
                                <select name="bill_month_name" class="form-control" required style="color: var(--text-main); background-color: var(--bg-input); flex: 1;">
                                    <?php 
                                    $monthsList = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                    foreach ($monthsList as $m): 
                                    ?>
                                        <option value="<?php echo $m; ?>" <?php echo ($selectedMonth === $m) ? 'selected' : ''; ?>>
                                            <?php echo $m; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="bill_month_year" class="form-control" required style="color: var(--text-main); background-color: var(--bg-input); flex: 1;">
                                    <?php 
                                    $startYear = intval(date('Y')) - 5;
                                    $endYear = intval(date('Y')) + 10;
                                    for ($y = $startYear; $y <= $endYear; $y++): 
                                    ?>
                                        <option value="<?php echo $y; ?>" <?php echo (intval($selectedYear) === $y) ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="invoice-textarea-container">
                        <div class="form-group" style="margin-bottom: 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <label for="customer_to" style="margin-bottom: 0;">To (Customer Details)</label>
                                <div style="display: flex; align-items: center;">
                                    <label for="customer_gst" style="margin-bottom: 0; margin-right: 10px; font-weight: 500; font-size: 13px;">Customer GST:</label>
                                    <input type="text" id="customer_gst" name="customer_gst" class="form-control" style="width: 220px; padding: 6px 10px;" placeholder="Fetch or enter GST..." value="<?php echo htmlspecialchars($invoiceData['customer_gst'] ?? ''); ?>">
                                </div>
                            </div>
                            <textarea id="customer_to" name="customer_to" class="form-control textarea-control" required placeholder="Enter customer name, shipping address, or billing details..."><?php echo htmlspecialchars($invoiceData['customer_to'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Invoice Line Items Builder -->
                <div class="content-card invoice-builder-card">
                    <div class="items-table-header">
                        <h3>Invoice Line Items</h3>
                        <button type="button" class="btn btn-success" id="addRowBtn" style="padding: 8px 16px; font-size: 13px;">
                            <i class="fa-solid fa-plus"></i> Add Row
                        </button>
                    </div>
                    
                    <div class="table-responsive items-table-container">
                        <table class="items-table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 11%;" class="text-center">DATE</th>
                                    <th style="width: 13%;" class="text-center">DELIVERY DATE</th>
                                    <th style="width: 16%;">C/NOTE</th>
                                    <th style="width: 16%;">DEST. (Destination)</th>
                                    <th style="width: 11%;" class="text-right">PKTS. (Packets)</th>
                                    <th style="width: 11%;" class="text-right">WT. (Weight in Kg)</th>
                                    <th style="width: 11%;" class="text-right">AMOUNT (₹)</th>
                                    <th style="width: 5%;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <?php if ($isEdit && count($invoiceItems) > 0): ?>
                                    <?php foreach ($invoiceItems as $index => $item): ?>
                                        <tr class="item-row">
                                            <td>
                                                <input type="date" name="item_date[]" class="input-cell" value="<?php echo htmlspecialchars($item['item_date'] ?? ''); ?>">
                                            </td>
                                            <td>
                                                <input type="date" name="delivery_date[]" class="input-cell" value="<?php echo htmlspecialchars($item['delivery_date'] ?? ''); ?>">
                                            </td>
                                            <td>
                                                <input type="text" name="c_note[]" class="input-cell" required value="<?php echo htmlspecialchars($item['c_note']); ?>">
                                            </td>
                                            <td>
                                                <input type="text" name="destination[]" class="input-cell" required value="<?php echo htmlspecialchars($item['destination']); ?>">
                                            </td>
                                            <td>
                                                <input type="number" name="packets[]" class="input-cell text-right num-input packets-input" required min="0" value="<?php echo intval($item['packets']); ?>">
                                            </td>
                                            <td>
                                                <input type="number" step="any" name="weight[]" class="input-cell text-right num-input weight-input" required min="0" value="<?php echo floatval($item['weight']); ?>">
                                            </td>
                                            <td>
                                                <input type="number" step="any" name="amount[]" class="input-cell text-right num-input amount-input" required min="0" value="<?php echo floatval($item['amount']); ?>">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn-icon btn-icon-delete delete-row-btn" title="Remove Row">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Default empty row for new invoice -->
                                    <tr class="item-row">
                                        <td>
                                            <input type="date" name="item_date[]" class="input-cell">
                                        </td>
                                        <td>
                                            <input type="date" name="delivery_date[]" class="input-cell">
                                        </td>
                                        <td>
                                            <input type="text" name="c_note[]" class="input-cell" required placeholder="Consignment Note No.">
                                        </td>
                                        <td>
                                            <input type="text" name="destination[]" class="input-cell" required placeholder="e.g. Ranchi">
                                        </td>
                                        <td>
                                            <input type="number" name="packets[]" class="input-cell text-right num-input packets-input" required min="0" placeholder="0">
                                        </td>
                                        <td>
                                            <input type="number" step="any" name="weight[]" class="input-cell text-right num-input weight-input" required min="0" placeholder="0">
                                        </td>
                                        <td>
                                            <input type="number" step="any" name="amount[]" class="input-cell text-right num-input amount-input" required min="0" placeholder="0">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn-icon btn-icon-delete delete-row-btn" title="Remove Row">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <!-- Add Row Bottom -->
                                <tr style="background-color: transparent;">
                                    <td colspan="7" style="border: none; padding: 8px 16px;"></td>
                                    <td class="text-center" style="padding: 8px 0; border: none;">
                                        <button type="button" class="btn btn-success" id="addRowBtnBottom" style="padding: 0; width: 32px; height: 32px; font-size: 13px; display: inline-flex; align-items: center; justify-content: center;" title="Add Row">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </td>
                                </tr>
                                <!-- Subtotal -->
                                <tr class="total-row" style="background-color: rgba(0, 0, 0, 0.02);">
                                    <td colspan="6" class="text-right" style="font-weight: 500; font-size: 14px; border-bottom: 1px solid var(--border-color);">SUBTOTAL (BASE):</td>
                                    <td class="text-right" id="subtotalDisplay" style="font-weight: 500; font-size: 14px; border-bottom: 1px solid var(--border-color);">₹0.00</td>
                                    <td style="border-bottom: 1px solid var(--border-color);"></td>
                                </tr>
                                <!-- Fuel Amount -->
                                <tr class="total-row" style="background-color: rgba(0, 0, 0, 0.02);">
                                    <td colspan="6" class="text-right" style="font-weight: 500; font-size: 14px; border-bottom: 1px solid var(--border-color);">
                                        <span style="margin-right: 10px;">FUEL %:</span>
                                        <input type="number" step="any" id="fuelPercentageInput" class="input-cell text-right num-input" style="font-weight: 500; font-size: 14px; background-color: var(--bg-input); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; padding: 4px 8px; width: 60px; display: inline-block; margin-right: 15px;" placeholder="0" min="0">
                                        FUEL AMOUNT:
                                    </td>
                                    <td class="text-right" style="padding: 2px 10px; border-bottom: 1px solid var(--border-color);">
                                        <input type="number" step="any" name="fuel_amount" id="fuelAmountInput" class="input-cell text-right num-input" style="font-weight: 500; font-size: 14px; background-color: var(--bg-input); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; padding: 4px 8px; width: 100px; display: inline-block;" value="<?php echo floatval($invoiceData['fuel_amount'] ?? 0) ?: ''; ?>" min="0" placeholder="0">
                                    </td>
                                    <td style="border-bottom: 1px solid var(--border-color);"></td>
                                </tr>
                                <!-- Total Taxable Value -->
                                <tr class="total-row" style="background-color: rgba(0, 0, 0, 0.02);">
                                    <td colspan="6" class="text-right" style="font-weight: 500; font-size: 14px; border-bottom: 1px solid var(--border-color);">TOTAL TAXABLE VALUE:</td>
                                    <td class="text-right" id="taxableDisplay" style="font-weight: 500; font-size: 14px; border-bottom: 1px solid var(--border-color);">₹0.00</td>
                                    <td style="border-bottom: 1px solid var(--border-color);"></td>
                                </tr>
                                <!-- CGST -->
                                <tr class="total-row" style="background-color: rgba(0, 0, 0, 0.02);">
                                    <td colspan="6" class="text-right" style="font-weight: 500; font-size: 14px; border-bottom: 1px solid var(--border-color);">CGST (9%):</td>
                                    <td class="text-right" id="cgstDisplay" style="font-weight: 500; font-size: 14px; border-bottom: 1px solid var(--border-color);">₹0.00</td>
                                    <td style="border-bottom: 1px solid var(--border-color);"></td>
                                </tr>
                                <!-- SGST -->
                                <tr class="total-row" style="background-color: rgba(0, 0, 0, 0.02);">
                                    <td colspan="6" class="text-right" style="font-weight: 500; font-size: 14px; border-bottom: 1px solid var(--border-color);">SGST (9%):</td>
                                    <td class="text-right" id="sgstDisplay" style="font-weight: 500; font-size: 14px; border-bottom: 1px solid var(--border-color);">₹0.00</td>
                                    <td style="border-bottom: 1px solid var(--border-color);"></td>
                                </tr>
                                <!-- Grand Total -->
                                <tr class="total-row" style="background-color: rgba(0, 0, 0, 0.04);">
                                    <td colspan="6" class="text-right" style="font-weight: 700; font-size: 15px;">GRAND TOTAL RS.</td>
                                    <td class="text-right" id="totalDisplay" style="font-weight: 700; font-size: 15px; color: var(--color-success);">₹<?php echo number_format($invoiceData['total_amount'], 2); ?></td>
                                    <td>
                                        <input type="hidden" name="total_amount" id="totalAmountInput" value="<?php echo $invoiceData['total_amount']; ?>">
                                    </td>
                                </tr>
                                <tr class="total-words-row">
                                    <td colspan="8">
                                        <div style="display: flex; align-items: center; gap: 10px; width: 100%;">
                                            <span style="font-weight: 600; white-space: nowrap;">Rupees in Words:</span>
                                            <input type="text" name="amount_in_words" id="amountInWordsInput" class="input-cell" style="font-style: italic; background-color: rgba(255, 255, 255, 0.05); font-weight: 500;" required value="<?php echo htmlspecialchars($invoiceData['amount_in_words'] ?? ''); ?>" placeholder="Automatic conversion on value entry...">
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="form-actions-bar">
                        <!-- Hidden field: tells save_invoice.php to redirect to print after saving -->
                        <input type="hidden" name="print_after_save" id="printAfterSave" value="0">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i> Save Invoice Details
                        </button>
                        <button type="submit" class="btn btn-success" onclick="document.getElementById('printAfterSave').value='1'" style="background: linear-gradient(135deg, #059669, #10b981); box-shadow: 0 4px 10px rgba(16,185,129,0.25);">
                            <i class="fa-solid fa-print"></i> Save &amp; Print
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <!-- Client-side Interactive Logic -->
    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ── Mobile sidebar toggle ──
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            if (mobileMenuToggle && sidebar && sidebarOverlay) {
                mobileMenuToggle.addEventListener('click', function() {
                    sidebar.classList.add('active');
                    sidebarOverlay.classList.add('active');
                });
                
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                });
            }

            // ── Desktop sidebar collapse toggle ──
            const desktopToggle = document.getElementById('desktopSidebarToggle');
            const mainContent = document.querySelector('.main-content');

            function applySidebarState(collapsed) {
                if (collapsed) {
                    sidebar.classList.add('sidebar-collapsed');
                    mainContent.classList.add('sidebar-collapsed');
                    desktopToggle.classList.add('visible');
                } else {
                    sidebar.classList.remove('sidebar-collapsed');
                    mainContent.classList.remove('sidebar-collapsed');
                    desktopToggle.classList.remove('visible');
                }
            }

            // Restore saved state
            const saved = localStorage.getItem('sidebarCollapsed') === 'true';
            applySidebarState(saved);

            if (desktopToggle) {
                desktopToggle.addEventListener('click', function() {
                    const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
                    const next = !isCollapsed;
                    localStorage.setItem('sidebarCollapsed', next);
                    applySidebarState(next);
                });
            }

            // Also allow clicking the sidebar header area to collapse on desktop
            const sidebarHeader = document.querySelector('.sidebar-header');
            if (sidebarHeader) {
                // Add a small collapse button inside the sidebar header
                const collapseBtn = document.createElement('button');
                collapseBtn.innerHTML = '<i class="fa-solid fa-bars"></i>';
                collapseBtn.style.cssText = 'background:none;border:none;color:var(--text-muted);font-size:16px;cursor:pointer;margin-left:auto;padding:4px 8px;border-radius:6px;transition:all 0.2s;';
                collapseBtn.title = 'Collapse Sidebar';
                collapseBtn.addEventListener('mouseenter', () => collapseBtn.style.color = 'var(--color-primary)');
                collapseBtn.addEventListener('mouseleave', () => collapseBtn.style.color = 'var(--text-muted)');
                collapseBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    localStorage.setItem('sidebarCollapsed', 'true');
                    applySidebarState(true);
                });
                sidebarHeader.appendChild(collapseBtn);
            }
        });
    </script>
</body>
</html>
