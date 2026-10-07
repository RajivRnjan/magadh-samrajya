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

$search = trim($_GET['search'] ?? '');
$items = [];
try {
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT * FROM stat_invoices WHERE serial_no LIKE :search OR customer_to LIKE :search ORDER BY id DESC");
        $stmt->execute(['search' => "%$search%"]);
        $items = $stmt->fetchAll();
    } else {
        $items = $pdo->query("SELECT * FROM stat_invoices ORDER BY id DESC")->fetchAll();
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Invoices | Stationery ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="layout-wrapper">
        <aside class="sidebar"><div class="sidebar-header"><div class="sidebar-logo" style="background: transparent; box-shadow: none;"><img src="assets/mslogo.png" style="width: 100%; height: 100%; object-fit: contain;"></div><div class="sidebar-brand"><h3><?php echo htmlspecialchars($settings['company_name'] ?? 'MAGADH SAMARAJYA'); ?></h3><p>Billing Panel</p></div></div><ul class="sidebar-menu"><li class="sidebar-header-title" style="padding: 10px 20px; font-size: 11px; text-transform: uppercase; color: #888; font-weight: 700; margin-top: 10px;">Transport ERP</li><li class="sidebar-item"><a href="index.php"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a></li><li class="sidebar-item"><a href="invoice.php"><i class="fa-solid fa-plus-circle"></i><span>Create Invoice</span></a></li><li class="sidebar-item"><a href="all_invoices.php"><i class="fa-solid fa-receipt"></i><span>All Invoices</span></a></li><li class="sidebar-header-title" style="padding: 10px 20px; font-size: 11px; text-transform: uppercase; color: #888; font-weight: 700; margin-top: 10px;">Stationery ERP</li><li class="sidebar-item"><a href="stat_dashboard.php"><i class="fa-solid fa-chart-pie"></i><span>Stat Dashboard</span></a></li><li class="sidebar-item"><a href="stat_quotation.php"><i class="fa-solid fa-file-invoice"></i><span>Create Quotation</span></a></li><li class="sidebar-item"><a href="stat_all_quotations.php"><i class="fa-solid fa-list-alt"></i><span>All Quotations</span></a></li><li class="sidebar-item"><a href="stat_invoice.php"><i class="fa-solid fa-plus-square"></i><span>Create Invoice</span></a></li><li class="sidebar-item active"><a href="stat_all_invoices.php"><i class="fa-solid fa-receipt"></i><span>All Invoices</span></a></li><li class="sidebar-header-title" style="padding: 10px 20px; font-size: 11px; text-transform: uppercase; color: #888; font-weight: 700; margin-top: 10px;">System</li><li class="sidebar-item"><a href="settings.php"><i class="fa-solid fa-gears"></i><span>Settings</span></a></li></ul><div class="sidebar-footer"><a href="logout.php" class="logout-icon" title="Log Out"><i class="fa-solid fa-right-from-bracket"></i></a></div></aside>
        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <h1>All Invoices</h1>
                </div>
                <div><a href="stat_invoice.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> New Invoice</a></div>
            </header>
            <section class="content-card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Date</th>
                                <th>Client</th>
                                <th class="text-right">Total Amount</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td><span class="serial-badge">#<?php echo htmlspecialchars($item['serial_no']); ?></span></td>
                                <td><?php echo date('d-M-Y', strtotime($item['bill_date'])); ?></td>
                                <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($item['customer_to']); ?></td>
                                <td class="text-right">₹<?php echo number_format($item['grand_total'], 2); ?></td>
                                <td class="text-right actions-cell">
                                    <a href="stat_print_invoice.php?id=<?php echo $item['id']; ?>" target="_blank" class="btn-icon btn-icon-view"><i class="fa-solid fa-print"></i></a>
                                    <a href="stat_invoice.php?id=<?php echo $item['id']; ?>" class="btn-icon btn-icon-edit"><i class="fa-solid fa-pen"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>