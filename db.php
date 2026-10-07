<?php
require_once __DIR__ . '/config.php';

try {
    // Attempt connecting directly to the database (handles production cPanel/shared hosting)
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $connectionError) {
        // Fallback: Connect to host first and try creating it (local development auto-setup)
        $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
} catch (PDOException $e) {
    die("Database connection failed. Details: " . $e->getMessage());
}

try {
    // 1. Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'super_admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
    
    // Seed default admin account if users table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
    $row = $stmt->fetch();
    if ($row['cnt'] == 0) {
        $hashedPass = password_hash('admin', PASSWORD_DEFAULT);
        $stmtSeed = $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
        $stmtSeed->execute(['admin', $hashedPass, 'Super Admin', 'super_admin']);
    }
    
    // 2. Create settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL
    ) ENGINE=InnoDB;");
    
    // Seed default company details if settings table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM settings");
    $row = $stmt->fetch();
    if ($row['cnt'] == 0) {
        $defaultSettings = [
            'company_name' => 'MAGADH SAMARAJYA',
            'gstin' => '20EQBPK5133G1ZA',
            'address' => 'Bhadodih, Diwan Garden, Jhumri Telaiya, Koderma - 825409, Jharkhand',
            'mobiles' => '88253 51729, 94702 20659'
        ];
        $stmtSeed = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($defaultSettings as $key => $val) {
            $stmtSeed->execute([$key, $val]);
        }
    }
    
    // 3. Create branches table
    $pdo->exec("CREATE TABLE IF NOT EXISTS branches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_name VARCHAR(100) NOT NULL,
        gstin VARCHAR(50) NOT NULL,
        address TEXT NOT NULL,
        mobiles VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Seed default branch if empty
    $stmtB = $pdo->query("SELECT COUNT(*) as cnt FROM branches");
    $rowB = $stmtB->fetch();
    if ($rowB['cnt'] == 0) {
        $compGstin = '20EQBPK5133G1ZA';
        $compAddr = 'Bhadodih, Diwan Garden, Jhumri Telaiya, Koderma - 825409, Jharkhand';
        $compMobs = '88253 51729, 94702 20659';

        try {
            $stmtSet = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'gstin'");
            $v = $stmtSet->fetchColumn();
            if ($v) $compGstin = $v;

            $stmtSet = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'address'");
            $v = $stmtSet->fetchColumn();
            if ($v) $compAddr = $v;

            $stmtSet = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'mobiles'");
            $v = $stmtSet->fetchColumn();
            if ($v) $compMobs = $v;
        } catch (Exception $ex) {
            // Suppress settings load error
        }

        $stmtSeedB = $pdo->prepare("INSERT INTO branches (id, branch_name, gstin, address, mobiles) VALUES (?, ?, ?, ?, ?)");
        $stmtSeedB->execute([1, 'Jharkhand Office', $compGstin, $compAddr, $compMobs]);
    }
    
    // 4. Create invoices table
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        serial_no VARCHAR(50) UNIQUE NOT NULL,
        bill_date DATE NOT NULL,
        bill_month VARCHAR(50) NOT NULL,
        customer_to TEXT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        fuel_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        amount_in_words TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Migrate existing invoices if branch_id column doesn't exist yet
    $hasBranchId = false;
    try {
        $stmtCol = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'branch_id'");
        if ($stmtCol->fetch()) {
            $hasBranchId = true;
        }
    } catch (PDOException $ex) {
        // Suppress check error
    }

    if (!$hasBranchId) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN branch_id INT DEFAULT NULL AFTER id;");
        $pdo->exec("UPDATE invoices SET branch_id = 1 WHERE branch_id IS NULL;");
        $pdo->exec("ALTER TABLE invoices MODIFY COLUMN branch_id INT NOT NULL;");
        try {
            $pdo->exec("ALTER TABLE invoices ADD CONSTRAINT fk_invoice_branch FOREIGN KEY (branch_id) REFERENCES branches(id);");
        } catch (PDOException $ex) {
            // Suppress constraint creation error
        }
    }

    // Migrate existing invoices if fuel_amount column doesn't exist yet
    $hasFuelAmount = false;
    try {
        $stmtCol = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'fuel_amount'");
        if ($stmtCol->fetch()) {
            $hasFuelAmount = true;
        }
    } catch (PDOException $ex) {
        // Suppress check error
    }

    if (!$hasFuelAmount) {
        try {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN fuel_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_amount;");
        } catch (PDOException $ex) {
            // Suppress migration error
        }
    }
    
    // 5. Create invoice_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        c_note VARCHAR(100) NOT NULL,
        destination VARCHAR(255) NOT NULL,
        packets INT NOT NULL DEFAULT 0,
        weight DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        item_date DATE DEFAULT NULL,
        delivery_date DATE DEFAULT NULL,
        FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // Migrate existing invoice_items if item_date column doesn't exist yet
    $hasItemDate = false;
    try {
        $stmtCol = $pdo->query("SHOW COLUMNS FROM invoice_items LIKE 'item_date'");
        if ($stmtCol->fetch()) {
            $hasItemDate = true;
        }
    } catch (PDOException $ex) {
        // Suppress check error
    }
    if (!$hasItemDate) {
        try {
            $pdo->exec("ALTER TABLE invoice_items ADD COLUMN item_date DATE DEFAULT NULL AFTER amount;");
        } catch (PDOException $ex) {
            // Suppress migration error
        }
    }

    // Migrate existing invoice_items if delivery_date column doesn't exist yet
    $hasDeliveryDate = false;
    try {
        $stmtCol = $pdo->query("SHOW COLUMNS FROM invoice_items LIKE 'delivery_date'");
        if ($stmtCol->fetch()) {
            $hasDeliveryDate = true;
        }
    } catch (PDOException $ex) {
        // Suppress check error
    }
    if (!$hasDeliveryDate) {
        try {
            $pdo->exec("ALTER TABLE invoice_items ADD COLUMN delivery_date DATE DEFAULT NULL AFTER item_date;");
        } catch (PDOException $ex) {
            // Suppress migration error
        }
    }


    // 6. Stationery Quotations
    $pdo->exec("CREATE TABLE IF NOT EXISTS stat_quotations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        serial_no VARCHAR(50) UNIQUE NOT NULL,
        quotation_date DATE NOT NULL,
        customer_to TEXT NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        gst_rate DECIMAL(5,2) NOT NULL DEFAULT 18.00,
        gst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        grand_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        amount_in_words TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // 7. Stationery Quotation Items
    $pdo->exec("CREATE TABLE IF NOT EXISTS stat_quotation_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quotation_id INT NOT NULL,
        particulars VARCHAR(255) NOT NULL,
        qty INT NOT NULL DEFAULT 0,
        rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (quotation_id) REFERENCES stat_quotations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // 8. Stationery Invoices
    $pdo->exec("CREATE TABLE IF NOT EXISTS stat_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quotation_id INT DEFAULT NULL,
        serial_no VARCHAR(50) UNIQUE NOT NULL,
        bill_date DATE NOT NULL,
        customer_to TEXT NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        gst_rate DECIMAL(5,2) NOT NULL DEFAULT 18.00,
        gst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        grand_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        amount_in_words TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (quotation_id) REFERENCES stat_quotations(id) ON DELETE SET NULL
    ) ENGINE=InnoDB;");

    // 9. Stationery Invoice Items
    $pdo->exec("CREATE TABLE IF NOT EXISTS stat_invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        particulars VARCHAR(255) NOT NULL,
        qty INT NOT NULL DEFAULT 0,
        rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (invoice_id) REFERENCES stat_invoices(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

} catch (PDOException $e) {
    die("Database connection/initialization failed. Details: " . $e->getMessage());
}
