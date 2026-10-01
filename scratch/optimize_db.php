<?php
require_once __DIR__ . '/../config.php';

try {
    $db = db();
    
    $queries = [
        "ALTER TABLE feedback MODIFY COLUMN chat_id VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE feedback MODIFY COLUMN phone_number VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE feedback MODIFY COLUMN client_name VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE feedback MODIFY COLUMN branch_id INT DEFAULT 1",
        
        "ALTER TABLE pre_orders ADD INDEX idx_order_number (order_number)",
        "ALTER TABLE pre_orders ADD INDEX idx_chat_id (chat_id)",
        "ALTER TABLE pre_orders ADD INDEX idx_phone_number (phone_number)",
        "ALTER TABLE pre_orders ADD INDEX idx_status (status)",
        "ALTER TABLE pre_orders ADD INDEX idx_created_at (created_at)",
        
        "ALTER TABLE pre_order_items ADD INDEX idx_pre_order_id (pre_order_id)",
        "ALTER TABLE pre_order_items ADD INDEX idx_product_id (pre_order_product_id)",
        
        "ALTER TABLE feedback ADD INDEX idx_order_number (order_number)",
        "ALTER TABLE feedback ADD INDEX idx_chat_id (chat_id)",
        "ALTER TABLE feedback ADD INDEX idx_created_at (created_at)",
        
        "ALTER TABLE dairy_products ADD INDEX idx_status (status)"
    ];
    
    echo "--- OPTIMIZING DATABASE INDEXES AND CONSTRAINTS ---\n";
    foreach ($queries as $sql) {
        try {
            $db->exec($sql);
            echo "SUCCESS: $sql\n";
        } catch (Exception $e) {
            echo "INFO: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nDatabase optimization complete!\n";

} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
