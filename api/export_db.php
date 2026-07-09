<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Verify the user is authenticated and is an admin
checkRole(['admin']);

try {
    // 1. Get all table names in the database
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    if (empty($tables)) {
        die("No tables found to export.");
    }

    // 2. Build the SQL file content
    $sqlOutput = "-- =====================================================\n";
    $sqlOutput .= "-- RestroPulse ERP - Automated Database Backup\n";
    $sqlOutput .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $sqlOutput .= "-- PHP Version: " . phpversion() . "\n";
    $sqlOutput .= "-- =====================================================\n\n";

    // Disable foreign key checks during import to prevent constraint failures
    $sqlOutput .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        // Skip migration tables if any exist, but export all here
        
        // Structure (CREATE TABLE statement)
        $sqlOutput .= "-- -----------------------------------------------------\n";
        $sqlOutput .= "-- Table Structure for `$table`\n";
        $sqlOutput .= "-- -----------------------------------------------------\n";
        $sqlOutput .= "DROP TABLE IF EXISTS `$table`;\n";
        
        $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $sqlOutput .= $createStmt[1] . ";\n\n";

        // Data (INSERT INTO statements)
        $sqlOutput .= "-- Data Dump for `$table`\n";
        
        // Fetch all rows
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $columns = array_map(function($col) {
                    return "`$col`";
                }, array_keys($row));
                
                $values = array_map(function($val) use ($pdo) {
                    if ($val === null) {
                        return "NULL";
                    }
                    // Safely quote string data using PDO quote
                    return $pdo->quote($val);
                }, array_values($row));
                
                $sqlOutput .= "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
        }
        $sqlOutput .= "\n";
    }

    // Re-enable foreign key checks after import is done
    $sqlOutput .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    // 3. Output headers to force file download
    $filename = 'restropulse_backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sqlOutput));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $sqlOutput;
    exit;

} catch (PDOException $e) {
    die("Database backup failed: " . $e->getMessage());
}
