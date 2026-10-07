<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// Fetch business settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Gracefully handle
}

// Fetch all branches for filter
$branches = [];
try {
    $branches = $pdo->query("SELECT * FROM branches ORDER BY branch_name ASC")->fetchAll();
} catch (PDOException $e) {
    // Gracefully handle
}

// Active filters
$filterBranch = isset($_GET['branch_id']) ? trim($_GET['branch_id']) : '';
$filterMonth = isset($_GET['month']) ? trim($_GET['month']) : '';
$filterYear = isset($_GET['year']) ? trim($_GET['year']) : '';
$search = trim($_GET['search'] ?? '');

// Build query
$where = [];
$params = [];

if ($filterBranch !== '' && $filterBranch !== 'all') {
    $where[] = "i.branch_id = :branch_id";
    $params['branch_id'] = intval($filterBranch);
}

if ($filterMonth !== '' && $filterMonth !== 'all' && $filterYear !== '' && $filterYear !== 'all') {
    $where[] = "i.bill_month = :bill_month";
    $params['bill_month'] = $filterMonth . ' ' . $filterYear;
} elseif ($filterYear !== '' && $filterYear !== 'all') {
    $where[] = "i.bill_month LIKE :bill_month_year";
    $params['bill_month_year'] = "% " . $filterYear;
} elseif ($filterMonth !== '' && $filterMonth !== 'all') {
    $where[] = "i.bill_month LIKE :bill_month_name";
    $params['bill_month_name'] = $filterMonth . " %";
}

if ($search !== '') {
    $where[] = "(i.serial_no LIKE :search OR i.customer_to LIKE :search OR i.bill_date LIKE :search)";
    $params['search'] = "%$search%";
}

$whereSql = '';
if (count($where) > 0) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}

// Pagination settings
$perPage  = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 20;
$page     = isset($_GET['page'])     ? max(1, intval($_GET['page']))     : 1;
$offset   = ($page - 1) * $perPage;

// Total count query (for pagination bar)
$totalCount = 0;
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM invoices i LEFT JOIN branches b ON i.branch_id = b.id $whereSql");
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();
} catch (PDOException $e) {}

$totalPages = $totalCount > 0 ? (int) ceil($totalCount / $perPage) : 1;
if ($page > $totalPages) $page = $totalPages;

// Fetch invoices (paginated)
$invoices = [];
try {
    $pagedParams = $params;
    $pagedParams['limit']  = $perPage;
    $pagedParams['offset'] = $offset;
    $query = "SELECT i.*, b.branch_name FROM invoices i 
              LEFT JOIN branches b ON i.branch_id = b.id 
              $whereSql 
              ORDER BY i.created_at DESC
              LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    // Bind named params first, then limit/offset as integers
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $invoices = $stmt->fetchAll();
} catch (PDOException $e) {
    // Gracefully handle
}

// Calculate filtered stats from ALL matching invoices (not just current page)
$totalInvoices = $totalCount;
$totalRevenue  = 0.00;
$totalPackets  = 0;
$totalWeight   = 0.00;

if ($totalCount > 0) {
    // Fetch all IDs matching the filter for stats
    try {
        $allIdStmt = $pdo->prepare("SELECT i.id FROM invoices i LEFT JOIN branches b ON i.branch_id = b.id $whereSql ORDER BY i.created_at DESC");
        $allIdStmt->execute($params);
        $allIds = $allIdStmt->fetchAll(PDO::FETCH_COLUMN);
        $totalRevenue = 0;
        $allAmtStmt = $pdo->prepare("SELECT SUM(total_amount) FROM invoices WHERE id IN (" . implode(',', array_fill(0, count($allIds), '?')) . ")");
        $allAmtStmt->execute($allIds);
        $totalRevenue = floatval($allAmtStmt->fetchColumn());

        $inQuery = implode(',', array_fill(0, count($allIds), '?'));
        $itemsStmt = $pdo->prepare("SELECT SUM(packets) as pkts, SUM(weight) as wt FROM invoice_items WHERE invoice_id IN ($inQuery)");
        $itemsStmt->execute($allIds);
        $itemsStats  = $itemsStmt->fetch();
        $totalPackets = $itemsStats['pkts'] ?? 0;
        $totalWeight  = $itemsStats['wt']   ?? 0.00;
    } catch (PDOException $e) {}
}
// Pagination button style helper
function paginationBtnStyle($active) {
    $base = 'display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;transition:all 0.2s;border:1px solid var(--border-color);';
    if ($active) {
        return $base . 'background:var(--color-primary);color:#fff;border-color:var(--color-primary);';
    }
    return $base . 'background:var(--bg-card);color:var(--text-main);';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Invoices | Invoice ERP</title>
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
                    <h3><?php echo htmlspecialchars($settings['company_name'] ?? 'MAGADH SAMARAJYA');; ?></h3>
                    <p>Billing Panel</p>
                </div>
            </div>
            
            <ul class="sidebar-menu"><li class="sidebar-header-title" style="padding: 10px 20px; font-size: 11px; text-transform: uppercase; color: #888; font-weight: 700; margin-top: 10px;">Transport ERP</li>
                <li class="sidebar-item">
                    <a href="index.php">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="invoice.php">
                        <i class="fa-solid fa-plus-circle"></i>
                        <span>Create Invoice</span>
                    </a>
                </li>
                <li class="sidebar-item active">
                    <a href="all_invoices.php">
                        <i class="fa-solid fa-receipt"></i>
                        <span>All Invoices</span>
                    </a>
                </li><li class="sidebar-header-title" style="padding: 10px 20px; font-size: 11px; text-transform: uppercase; color: #888; font-weight: 700; margin-top: 10px;">Stationery ERP</li>
                <li class="sidebar-item">
                    <a href="stat_dashboard.php">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Stat Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="stat_quotation.php">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span>Create Quotation</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="stat_all_quotations.php">
                        <i class="fa-solid fa-list-alt"></i>
                        <span>All Quotations</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="stat_invoice.php">
                        <i class="fa-solid fa-plus-square"></i>
                        <span>Create Invoice</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="stat_all_invoices.php">
                        <i class="fa-solid fa-receipt"></i>
                        <span>All Invoices</span>
                    </a>
                </li><li class="sidebar-header-title" style="padding: 10px 20px; font-size: 11px; text-transform: uppercase; color: #888; font-weight: 700; margin-top: 10px;">System</li>
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
                        <?php echo strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));; ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['name'] ?? 'Administrator');; ?></h4>
                        <p><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'] ?? 'super_admin'));; ?></p>
                    </div>
                </div>
                <a href="logout.php" class="logout-icon" title="Log Out">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </aside>
        
        <!-- Main Content Grid -->
        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <h1>All Invoices</h1>
                    <p>Manage and filter all generated invoices.</p>
                </div>
                <div>
                    <a href="invoice.php" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i> Create Invoice
                    </a>
                </div>
            </header>
            
            <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check alert-icon"></i>
                    <span>Invoice has been successfully deleted!</span>
                </div>
            <?php elseif (isset($_GET['status']) && $_GET['status'] === 'saved'): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check alert-icon"></i>
                    <span>Invoice has been successfully saved!</span>
                </div>
            <?php endif; ?>
            
            <!-- Filters Card -->
            <section class="content-card" style="padding: 20px 24px; margin-bottom: 24px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px;">
                <form action="all_invoices.php" method="GET" class="filter-grid-row">
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="branch_id" style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; display: block;">Office Address</label>
                        <select name="branch_id" id="branch_id" class="form-control" onchange="this.form.submit()" style="font-size: 13px; height: 38px; color: var(--text-main); background-color: var(--bg-input);">
                            <option value="all">All Offices</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?php echo $b['id']; ?>" <?php echo ($filterBranch == $b['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['branch_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="month" style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; display: block;">Billing Month</label>
                        <select name="month" id="month" class="form-control" onchange="this.form.submit()" style="font-size: 13px; height: 38px; color: var(--text-main); background-color: var(--bg-input);">
                            <option value="all">All Months</option>
                            <?php 
                            $monthsList = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                            foreach ($monthsList as $m): 
                            ?>
                                <option value="<?php echo $m; ?>" <?php echo ($filterMonth === $m) ? 'selected' : ''; ?>>
                                    <?php echo $m; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="year" style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; display: block;">Billing Year</label>
                        <select name="year" id="year" class="form-control" onchange="this.form.submit()" style="font-size: 13px; height: 38px; color: var(--text-main); background-color: var(--bg-input);">
                            <option value="all">All Years</option>
                            <?php 
                            $currentYear = intval(date('Y'));
                            for ($y = $currentYear - 5; $y <= $currentYear + 10; $y++): 
                            ?>
                                <option value="<?php echo $y; ?>" <?php echo ($filterYear == $y) ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="search" style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; display: block;">Search</label>
                        <input type="text" name="search" id="search" class="form-control" placeholder="Invoice No, Client..." value="<?php echo htmlspecialchars($search); ?>" style="font-size: 13px; height: 38px;">
                    </div>

                    <div style="display: flex;">
                        <a href="all_invoices.php" class="btn btn-secondary" style="flex: 1; height: 38px; font-size: 13px; display: inline-flex; align-items: center; justify-content: center;">
                            Clear Filters
                        </a>
                    </div>
                </form>
            </section>
            
            <!-- Invoice List Table -->
            <section class="content-card">
                <div class="card-header">
                    <h3>Invoices Ledger <span style="font-size:13px;font-weight:500;color:var(--text-muted);margin-left:8px;">(<?php echo $totalCount; ?> total)</span></h3>
                    <!-- Per-page selector -->
                    <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);">
                        <span>Show</span>
                        <select onchange="changePerPage(this.value)" style="padding:4px 8px;border:1px solid var(--border-color);border-radius:6px;font-size:13px;background:var(--bg-input);color:var(--text-main);cursor:pointer;">
                            <?php foreach ([10, 20, 50, 100] as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo $perPage == $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span>per page</span>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Branch / Office</th>
                                <th>Bill Date</th>
                                <th>Month</th>
                                <th>To / Client</th>
                                <th class="text-right">Total Amount</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($invoices) > 0) {
                                foreach ($invoices as $invoice) { ?>
                                    <tr>
                                        <td>
                                            <span class="serial-badge">#<?php echo htmlspecialchars($invoice['serial_no']); ?></span>
                                        </td>
                                        <td style="font-weight: 500;">
                                            <?php echo htmlspecialchars($invoice['branch_name'] ?? 'Jharkhand Office'); ?>
                                        </td>
                                        <td>
                                            <?php echo date('d-M-Y', strtotime($invoice['bill_date'])); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($invoice['bill_month']); ?>
                                        </td>
                                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo nl2br(htmlspecialchars($invoice['customer_to'])); ?>
                                        </td>
                                        <td class="text-right" style="font-weight: 600; color: var(--color-success);">
                                            ₹<?php echo number_format($invoice['total_amount'], 2); ?>
                                        </td>
                                        <td class="actions-cell text-right">
                                            <a href="print.php?id=<?php echo $invoice['id']; ?>" target="_blank" class="btn-icon btn-icon-view" title="Print PDF / View">
                                                <i class="fa-solid fa-print"></i>
                                            </a>
                                            <a href="invoice.php?id=<?php echo $invoice['id']; ?>" class="btn-icon btn-icon-edit" title="Edit Invoice">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['serial_no']); ?>')" class="btn-icon btn-icon-delete" title="Delete Invoice">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fa-solid fa-folder-open"></i>
                                            <p>No matching invoices found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <?php if ($totalPages > 1): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-top:1px solid var(--border-color);flex-wrap:wrap;gap:12px;">
                    <span style="font-size:13px;color:var(--text-muted);">
                        Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalCount); ?> of <?php echo $totalCount; ?> invoices
                    </span>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <?php
                        // Build base URL preserving filters
                        $queryParams = array_filter([
                            'branch_id' => $filterBranch ?: null,
                            'month'     => $filterMonth  ?: null,
                            'year'      => $filterYear   ?: null,
                            'search'    => $search       ?: null,
                            'per_page'  => $perPage != 20 ? $perPage : null,
                        ]);

                        function pageUrl($p, $extra = []) {
                            global $queryParams;
                            $q = array_merge($queryParams, $extra, ['page' => $p]);
                            return 'all_invoices.php?' . http_build_query(array_filter($q));
                        }
                        ?>

                        <!-- First / Prev -->
                        <?php if ($page > 1): ?>
                            <a href="<?php echo pageUrl(1); ?>" style="<?php echo paginationBtnStyle(false); ?>" title="First">&laquo;</a>
                            <a href="<?php echo pageUrl($page - 1); ?>" style="<?php echo paginationBtnStyle(false); ?>">&#8249; Prev</a>
                        <?php endif; ?>

                        <!-- Page numbers (window of 5) -->
                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($totalPages, $page + 2);
                        for ($p = $start; $p <= $end; $p++):
                        ?>
                            <a href="<?php echo pageUrl($p); ?>" style="<?php echo paginationBtnStyle($p === $page); ?>"><?php echo $p; ?></a>
                        <?php endfor; ?>

                        <!-- Next / Last -->
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo pageUrl($page + 1); ?>" style="<?php echo paginationBtnStyle(false); ?>">Next &#8250;</a>
                            <a href="<?php echo pageUrl($totalPages); ?>" style="<?php echo paginationBtnStyle(false); ?>" title="Last">&raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Alert / Confirm script & Responsive Sidebar Logic -->
    <script>
        function confirmDelete(id, serialNo) {
            if (confirm("Are you sure you want to delete Invoice #" + serialNo + "? This action cannot be undone.")) {
                window.location.href = "delete_invoice.php?id=" + id + "&csrf_token=<?php echo $_SESSION['csrf_token']; ?>";
            }
        }

        function changePerPage(val) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', val);
            url.searchParams.set('page', '1'); // reset to first page
            window.location.href = url.toString();
        }

        // Mobile responsive sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
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

            // Desktop sidebar collapse toggle
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

            const saved = localStorage.getItem('sidebarCollapsed') === 'true';
            applySidebarState(saved);

            if (desktopToggle) {
                desktopToggle.addEventListener('click', function() {
                    const next = !sidebar.classList.contains('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', next);
                    applySidebarState(next);
                });
            }

            const sidebarHeader = document.querySelector('.sidebar-header');
            if (sidebarHeader) {
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
