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

$totalInvoices = 0;
$totalRevenue = 0.00;
$totalQuotations = 0;
$totalPendingQuotations = 0;

try {
    $statsStmt = $pdo->query("SELECT COUNT(*) as count, SUM(grand_total) as revenue FROM stat_invoices");
    $stats = $statsStmt->fetch();
    $totalInvoices = $stats['count'] ?? 0;
    $totalRevenue = $stats['revenue'] ?? 0.00;
    
    $qStatsStmt = $pdo->query("SELECT COUNT(*) as count FROM stat_quotations");
    $qStats = $qStatsStmt->fetch();
    $totalQuotations = $qStats['count'] ?? 0;

    $qpStatsStmt = $pdo->query("SELECT COUNT(*) as count FROM stat_quotations WHERE status='pending'");
    $qpStats = $qpStatsStmt->fetch();
    $totalPendingQuotations = $qpStats['count'] ?? 0;
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stationery Dashboard | Invoice ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/mslogo.png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="mobile-header">
        <button id="mobileMenuToggle" class="menu-toggle-btn"><i class="fa-solid fa-bars"></i></button>
        <div class="mobile-brand">
            <img src="assets/mslogo.png" alt="Logo" style="height: 24px; width: 24px; object-fit: contain;">
            <span>MS ERP</span>
        </div>
        <div style="width: 36px;"></div>
    </div>
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <button id="desktopSidebarToggle" class="sidebar-toggle-btn" title="Toggle Sidebar"><i class="fa-solid fa-bars"></i></button>

    <div class="layout-wrapper">
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
                <li class="sidebar-item">
                    <a href="all_invoices.php">
                        <i class="fa-solid fa-receipt"></i>
                        <span>All Invoices</span>
                    </a>
                </li><li class="sidebar-header-title" style="padding: 10px 20px; font-size: 11px; text-transform: uppercase; color: #888; font-weight: 700; margin-top: 10px;">Stationery ERP</li>
                <li class="sidebar-item active">
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
        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <h1>Stationery Dashboard</h1>
                    <p>Overview of stationery quotations and invoices.</p>
                </div>
                <div>
                    <a href="stat_quotation.php" class="btn btn-secondary" style="margin-right: 10px;">
                        <i class="fa-solid fa-file-invoice"></i> New Quotation
                    </a>
                    <a href="stat_invoice.php" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i> New Invoice
                    </a>
                </div>
            </header>
            
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-data">
                        <p>Total Revenue (Invoices)</p>
                        <h3>₹<?php echo number_format($totalRevenue, 2); ?></h3>
                    </div>
                    <div class="stat-icon success"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-data">
                        <p>Total Invoices</p>
                        <h3><?php echo number_format($totalInvoices); ?></h3>
                    </div>
                    <div class="stat-icon primary"><i class="fa-solid fa-receipt"></i></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-data">
                        <p>Total Quotations</p>
                        <h3><?php echo number_format($totalQuotations); ?></h3>
                    </div>
                    <div class="stat-icon warning"><i class="fa-solid fa-list-alt"></i></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-data">
                        <p>Pending Quotations</p>
                        <h3><?php echo number_format($totalPendingQuotations); ?></h3>
                    </div>
                    <div class="stat-icon danger"><i class="fa-solid fa-clock"></i></div>
                </div>
            </section>
        </main>
    </div>
    <script>
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
        });
    </script>
</body>
</html>