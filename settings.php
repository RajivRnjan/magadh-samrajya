<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$message = '';
$messageType = '';

// Handle submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        header("HTTP/1.1 403 Forbidden");
        exit("CSRF token validation failed. Unauthorized request.");
    }
    
    if (isset($_POST['action'])) {
    if ($_POST['action'] === 'add_branch') {
        $branchName = trim($_POST['branch_name'] ?? '');
        $gstin = trim($_POST['gstin'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $mobiles = trim($_POST['mobiles'] ?? '');
        
        if (empty($branchName) || empty($gstin) || empty($address) || empty($mobiles)) {
            $message = 'All branch fields are required.';
            $messageType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO branches (branch_name, gstin, address, mobiles) VALUES (?, ?, ?, ?)");
                $stmt->execute([$branchName, $gstin, $address, $mobiles]);
                $message = 'Office location added successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Failed to add location: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif ($_POST['action'] === 'edit_branch') {
        $branchId = intval($_POST['branch_id'] ?? 0);
        $branchName = trim($_POST['branch_name'] ?? '');
        $gstin = trim($_POST['gstin'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $mobiles = trim($_POST['mobiles'] ?? '');
        
        if ($branchId <= 0 || empty($branchName) || empty($gstin) || empty($address) || empty($mobiles)) {
            $message = 'All branch fields are required.';
            $messageType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE branches SET branch_name = ?, gstin = ?, address = ?, mobiles = ? WHERE id = ?");
                $stmt->execute([$branchName, $gstin, $address, $mobiles, $branchId]);
                $message = 'Office location updated successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Failed to update location: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif ($_POST['action'] === 'delete_branch') {
        $branchId = intval($_POST['branch_id'] ?? 0);
        if ($branchId <= 0) {
            $message = 'Invalid branch selected.';
            $messageType = 'danger';
        } elseif ($branchId == 1) {
            $message = 'The default primary branch cannot be deleted.';
            $messageType = 'danger';
        } else {
            try {
                // Check if any invoice is linked
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE branch_id = ?");
                $stmtCheck->execute([$branchId]);
                $linkedCount = $stmtCheck->fetchColumn();
                
                if ($linkedCount > 0) {
                    $message = "Cannot delete this location. It is currently linked to $linkedCount invoices.";
                    $messageType = 'danger';
                } else {
                    $stmtDel = $pdo->prepare("DELETE FROM branches WHERE id = ?");
                    $stmtDel->execute([$branchId]);
                    $message = 'Office location deleted successfully!';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Failed to delete location: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif ($_POST['action'] === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (empty($oldPass) || empty($newPass) || empty($confirmPass)) {
            $message = 'Please fill in all password fields.';
            $messageType = 'danger';
        } elseif ($newPass !== $confirmPass) {
            $message = 'New passwords do not match.';
            $messageType = 'danger';
        } else {
            try {
                // Retrieve current password hash
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $currentHash = $stmt->fetchColumn();
                
                if (password_verify($oldPass, $currentHash)) {
                    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmtUpdate->execute([$newHash, $_SESSION['user_id']]);
                    
                    $message = 'Admin password updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Incorrect current password.';
                    $messageType = 'danger';
                }
            } catch (PDOException $e) {
                $message = 'Failed to change password: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}
}

// Fetch current business settings & branches
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Invoice ERP</title>
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
                <li class="sidebar-item active">
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
                    <h1>System Settings</h1>
                    <p>Manage business profile configurations and login credentials.</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </header>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?> alert-icon"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="settings-container">
                <!-- Left Pane: Branches List Grid -->
                <div class="content-card" style="border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; background: var(--bg-card); display: flex; flex-direction: column; gap: 20px;">
                    <div class="settings-header">
                        <h4 style="font-size: 18px; font-weight: 700; margin: 0;">Office Addresses & Locations</h4>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 4px 0 0 0;">Manage separate billing profiles under the Magadh Samrajya brand.</p>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                        <?php foreach ($branches as $branch): ?>
                            <div class="branch-card" style="display: flex; flex-direction: column; justify-content: space-between; border: 1px solid var(--border-color); border-radius: 12px; padding: 18px; background: #ffffff; box-shadow: var(--shadow-sm); transition: var(--transition-normal);">
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                        <h5 style="font-weight: 700; font-size: 15px; margin: 0; color: var(--color-primary);"><?php echo htmlspecialchars($branch['branch_name']); ?></h5>
                                        <span style="font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 20px; background: rgba(99, 102, 241, 0.1); color: var(--color-primary);">ID: #<?php echo $branch['id']; ?></span>
                                    </div>
                                    
                                    <div style="font-size: 12px; color: var(--text-main); margin-bottom: 6px; line-height: 1.4;">
                                        <strong style="color: var(--text-muted);">GSTIN:</strong> <?php echo htmlspecialchars($branch['gstin']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-main); margin-bottom: 6px; line-height: 1.4;">
                                        <strong style="color: var(--text-muted);">Mobiles:</strong> <?php echo htmlspecialchars($branch['mobiles']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-main); line-height: 1.5;">
                                        <strong style="color: var(--text-muted);">Address:</strong><br>
                                        <span style="font-style: italic; color: #475569;"><?php echo nl2br(htmlspecialchars($branch['address'])); ?></span>
                                    </div>
                                </div>
                                
                                <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; border-top: 1px solid #f1f5f9; padding-top: 12px;">
                                    <button class="btn btn-secondary btn-sm" onclick='editBranch(<?php echo json_encode($branch, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' style="padding: 6px 12px; font-size: 11px; height: auto;">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </button>
                                    <?php if ($branch['id'] != 1): ?>
                                        <form action="settings.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this office address?');" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="delete_branch">
                                            <input type="hidden" name="branch_id" value="<?php echo $branch['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" style="padding: 6px 12px; font-size: 11px; background-color: var(--color-danger); border-color: var(--color-danger); color: white; height: auto;">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right Pane: Add/Edit Form & Password Change -->
                <div style="display: flex; flex-direction: column; gap: 30px;">
                    <!-- Office Location Form -->
                    <div class="content-card settings-card" id="form-container">
                        <div class="settings-header">
                            <h4 id="form-title">Add Office Location</h4>
                            <p id="form-desc">Create a new billing profile with a distinct address.</p>
                        </div>
                        
                        <form action="settings.php" method="POST" id="branch-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" id="form-action" value="add_branch">
                            <input type="hidden" name="branch_id" id="form-branch-id" value="">
                            
                            <div class="form-group">
                                <label for="branch_name">Office/Branch Name</label>
                                <input type="text" id="branch_name" name="branch_name" class="form-control" placeholder="e.g. Jharkhand Office, Bihar Office" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="gstin">GSTIN (Tax ID)</label>
                                <input type="text" id="gstin" name="gstin" class="form-control" placeholder="e.g. 20EQBPK5133G1ZA" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="mobiles">Mobile Numbers (comma separated)</label>
                                <input type="text" id="mobiles" name="mobiles" class="form-control" placeholder="e.g. 88253 51729, 94702 20659" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="address">Full Address</label>
                                <textarea id="address" name="address" class="form-control textarea-control" rows="3" placeholder="Enter physical office address..." required></textarea>
                            </div>
                            
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <button type="submit" class="btn btn-primary" id="submit-btn" style="flex: 1; justify-content: center;">
                                    <i class="fa-solid fa-plus"></i> Save Location
                                </button>
                                <button type="button" class="btn btn-secondary" id="cancel-btn" onclick="resetForm()" style="display: none; justify-content: center; height: 38px;">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Change Password Form -->
                    <div class="content-card settings-card">
                        <div class="settings-header">
                            <h4>Security Credentials</h4>
                            <p>Change your super admin password.</p>
                        </div>
                        
                        <form action="settings.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label for="old_password">Current Password</label>
                                <input type="password" id="old_password" name="old_password" class="form-control" required autocomplete="current-password">
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" required autocomplete="new-password">
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required autocomplete="new-password">
                            </div>
                            
                            <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                                <i class="fa-solid fa-key"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Interface scripts for editing office states -->
    <script>
    function editBranch(branch) {
        document.getElementById('form-title').innerText = 'Edit Office Location';
        document.getElementById('form-desc').innerText = 'Update this billing profile location details.';
        document.getElementById('form-action').value = 'edit_branch';
        document.getElementById('form-branch-id').value = branch.id;
        document.getElementById('branch_name').value = branch.branch_name;
        document.getElementById('gstin').value = branch.gstin;
        document.getElementById('mobiles').value = branch.mobiles;
        document.getElementById('address').value = branch.address;
        
        const submitBtn = document.getElementById('submit-btn');
        submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Location';
        submitBtn.className = 'btn btn-primary';
        
        document.getElementById('cancel-btn').style.display = 'inline-flex';
        document.getElementById('form-container').scrollIntoView({ behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('form-title').innerText = 'Add Office Location';
        document.getElementById('form-desc').innerText = 'Create a new billing profile with a distinct address.';
        document.getElementById('form-action').value = 'add_branch';
        document.getElementById('form-branch-id').value = '';
        document.getElementById('branch-form').reset();
        
        const submitBtn = document.getElementById('submit-btn');
        submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> Save Location';
        
        document.getElementById('cancel-btn').style.display = 'none';
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
