<?php

// Enable PHP error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Prevent direct access
if (count(get_included_files()) == 1) {
    header("HTTP/1.0 403 Forbidden");
    exit("Direct access forbidden.");
}

// Local DB Configuration
// define('DB_HOST', '127.0.0.1');
// define('DB_USER', 'root');
// define('DB_PASS', '');
// define('DB_NAME', 'invoice_erp');

// Server DB Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u161533628_mag_invoice');
define('DB_PASS', 'mR1=s6o|V9>>');
define('DB_NAME', 'u161533628_mag_invoice');

?>
