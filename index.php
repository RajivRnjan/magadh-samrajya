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
    // Gracefully handle if DB query fails
}

// Fetch stats summary
$totalInvoices = 0;
$totalRevenue = 0.00;
$totalPackets = 0;
$totalWeight = 0.00;

try {
    // 1. Total Invoices & Revenue
    $statsStmt = $pdo->query("SELECT COUNT(*) as count, SUM(total_amount) as revenue FROM invoices");
    $stats = $statsStmt->fetch();
    $totalInvoices = $stats['count'] ?? 0;
    $totalRevenue = $stats['revenue'] ?? 0.00;
    
    // 2. Total Packets & Weight
    $itemsStatsStmt = $pdo->query("SELECT SUM(packets) as pkts, SUM(weight) as wt FROM invoice_items");
    $itemsStats = $itemsStatsStmt->fetch();
    $totalPackets = $itemsStats['pkts'] ?? 0;
    $totalWeight = $itemsStats['wt'] ?? 0.00;
} catch (PDOException $e) {
    // Log or handle
}

// Fetch filter/search details
$search = trim($_GET['search'] ?? '');
$invoices = [];
try {
    if ($search !== '') {
        $query = "SELECT * FROM invoices 
                  WHERE serial_no LIKE :search 
                     OR customer_to LIKE :search 
                     OR bill_date LIKE :search 
                     OR bill_month LIKE :search
                  ORDER BY created_at DESC LIMIT 10";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['search' => "%$search%"]);
        $invoices = $stmt->fetchAll();
    } else {
        $query = "SELECT * FROM invoices ORDER BY created_at DESC LIMIT 10";
        $invoices = $pdo->query($query)->fetchAll();
    }
} catch (PDOException $e) {
    // Handle
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Invoice ERP</title>
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
                <li class="sidebar-item active">
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
        
        <!-- Main Content Grid -->
        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <h1>Dashboard</h1>
                    <p>Overview of bills, transactions, and metrics.</p>
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
            
            <!-- Statistics Cards -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-data">
                        <p>Total Revenue</p>
                        <h3>₹<?php echo number_format($totalRevenue, 2); ?></h3>
                    </div>
                    <div class="stat-icon success">
                        <i class="fa-solid fa-indian-rupee-sign"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-data">
                        <p>Total Invoices</p>
                        <h3><?php echo number_format($totalInvoices); ?></h3>
                    </div>
                    <div class="stat-icon primary">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-data">
                        <p>Total Packets</p>
                        <h3><?php echo number_format($totalPackets); ?></h3>
                    </div>
                    <div class="stat-icon warning">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-data">
                        <p>Total Weight</p>
                        <h3><?php echo number_format($totalWeight, 2); ?> Kg</h3>
                    </div>
                    <div class="stat-icon danger">
                        <i class="fa-solid fa-weight-scale"></i>
                    </div>
                </div>
            </section>
            
            <!-- Invoice List Table -->
            <section class="content-card">
                <div class="card-header">
                    <h3>Invoices Ledger</h3>
                    <div class="search-filter-wrapper">
                        <form action="index.php" method="GET" class="search-input-wrapper">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" name="search" placeholder="Search by Invoice No, Client, Date..." value="<?php echo htmlspecialchars($search); ?>">
                        </form>
                        <?php if ($search !== ''): ?>
                            <a href="index.php" class="btn btn-secondary" style="padding: 8px 12px; font-size: 13px;">Clear</a>
                        <?php endif; ?>
                        <a href="all_invoices.php" class="btn btn-secondary" style="padding: 8px 12px; font-size: 13px;">
                            <i class="fa-solid fa-list"></i> View All
                        </a>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
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
                                        <td>
                                            <?php echo date('d-M-Y', strtotime($invoice['bill_date'])); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($invoice['bill_month']); ?>
                                        </td>
                                        <td style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
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
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="fa-solid fa-folder-open"></i>
                                            <p>No invoices found. Create your first invoice to get started!</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
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
