<?php
require_once __DIR__ . '/../config.php';

$stmt = db()->query("SELECT * FROM dairy_products ORDER BY id ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total products in DB: " . count($products) . "\n";
