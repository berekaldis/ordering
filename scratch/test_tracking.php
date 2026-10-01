<?php
require_once __DIR__ . '/../config.php';

try {
    $db = db();
    
    // Add rejection_reason column if missing
    $cols = [];
    $res = $db->query("SHOW COLUMNS FROM pre_orders");
    if ($res) {
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $cols[] = $row['Field'];
        }
    }
    
    if (!in_array('rejection_reason', $cols)) {
        $db->exec("ALTER TABLE pre_orders ADD COLUMN rejection_reason VARCHAR(255) DEFAULT NULL");
        echo "Added rejection_reason column to pre_orders successfully.\n";
    } else {
        echo "rejection_reason column already exists.\n";
    }
    
    // Query recent orders
    $stmt = $db->query("SELECT id, order_number, chat_id, phone_number, status, rejection_reason FROM pre_orders ORDER BY created_at DESC LIMIT 5");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "--- RECENT ORDERS IN DATABASE ---\n";
    print_r($orders);

} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
