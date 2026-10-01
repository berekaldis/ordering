<?php
require_once __DIR__ . '/../config.php';

$sqlFile = __DIR__ . '/../database.sql';
$content = file_get_contents($sqlFile);

// Query all dairy_products
$stmt = db()->query("SELECT * FROM dairy_products ORDER BY id ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$values = [];
foreach ($products as $p) {
    $v = "(";
    $v .= intval($p['id']) . ",";
    $v .= db()->quote($p['product_code']) . ",";
    $v .= db()->quote($p['product_name']) . ",";
    $v .= ($p['product_name_am'] !== null ? db()->quote($p['product_name_am']) : "NULL") . ",";
    $v .= ($p['category'] !== null ? db()->quote($p['category']) : "NULL") . ",";
    $v .= ($p['variety'] !== null ? db()->quote($p['variety']) : "NULL") . ",";
    $v .= ($p['unit'] !== null ? db()->quote($p['unit']) : "NULL") . ",";
    $v .= floatval($p['unit_price']) . ",";
    $v .= ($p['walkin_price'] !== null ? floatval($p['walkin_price']) : "NULL") . ",";
    $v .= ($p['image'] !== null ? db()->quote($p['image']) : "NULL") . ",";
    $v .= ($p['description'] !== null ? db()->quote($p['description']) : "NULL") . ",";
    $v .= ($p['description_am'] !== null ? db()->quote($p['description_am']) : "NULL") . ",";
    $v .= intval($p['stock_quantity']) . ",";
    $v .= ($p['shelf_life_days'] !== null ? intval($p['shelf_life_days']) : "NULL") . ",";
    $v .= intval($p['status']) . ",";
    $v .= intval($p['sort_order']) . ",";
    $v .= db()->quote($p['created_at']) . ",";
    $v .= db()->quote($p['updated_at']);
    $v .= ")";
    $values[] = $v;
}

$insertSql = "INSERT INTO `dairy_products` VALUES " . implode(",\n", $values) . ";";

// Replace INSERT INTO `dairy_products` block in database.sql
$pattern = '/INSERT INTO `dairy_products` VALUES.*?;/s';
if (preg_match($pattern, $content)) {
    $newContent = preg_replace($pattern, $insertSql, $content);
    file_put_contents($sqlFile, $newContent);
    echo "Successfully updated database.sql with " . count($products) . " ECA branch products!\n";
} else {
    echo "Pattern not found in database.sql\n";
}
