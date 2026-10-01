<?php
require_once 'config.php';
try {
    $stmt = db()->query("SHOW CREATE TABLE pre_order_items");
    print_r($stmt->fetch());
    
    $stmt2 = db()->query("SHOW CREATE TABLE daily_inventory");
    print_r($stmt2->fetch());
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
