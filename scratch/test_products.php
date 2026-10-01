<?php
require_once 'config.php';

// Check if any products exist
$stmt = db()->query("SELECT id, product_code, product_name FROM dairy_products LIMIT 5");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Existing products:\n";
print_r($products);
