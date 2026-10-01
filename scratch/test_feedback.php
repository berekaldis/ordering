<?php
require_once __DIR__ . '/../config.php';

try {
    $db = db();
    
    // Fix feedback table column defaults to allow NULL or empty strings without constraint errors
    $db->exec("ALTER TABLE feedback MODIFY COLUMN chat_id VARCHAR(50) DEFAULT NULL");
    $db->exec("ALTER TABLE feedback MODIFY COLUMN phone_number VARCHAR(50) DEFAULT NULL");
    $db->exec("ALTER TABLE feedback MODIFY COLUMN client_name VARCHAR(100) DEFAULT NULL");
    $db->exec("ALTER TABLE feedback MODIFY COLUMN branch_id INT DEFAULT 1");
    
    echo "Altered feedback table constraints successfully.\n";

} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
