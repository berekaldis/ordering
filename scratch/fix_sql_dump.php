<?php
require_once __DIR__ . '/../config.php';

$sqlFile = __DIR__ . '/../database.sql';

// 1. First ensure table schema in MySQL matches clean column list
try {
    // Check columns of dairy_products in live DB
    $stmt = db()->query("DESCRIBE dairy_products");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Live DB Columns: " . implode(', ', $cols) . "\n";
} catch (Exception $e) {
    echo "Error describing table: " . $e->getMessage() . "\n";
}

// 2. Fetch all products from DB
$stmt = db()->query("SELECT id, product_code, product_name, product_name_am, category, variety, unit, unit_price, walkin_price, image, description, description_am, stock_quantity, shelf_life_days, status, sort_order, created_at, updated_at FROM dairy_products ORDER BY id ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total products: " . count($products) . "\n";

// Create clean CREATE TABLE statement
$createTable = <<<SQL
DROP TABLE IF EXISTS `dairy_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dairy_products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name_am` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'coffee',
  `variety` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cup',
  `unit_price` decimal(10,2) NOT NULL,
  `walkin_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_am` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `stock_quantity` int DEFAULT '100',
  `shelf_life_days` int DEFAULT '1',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_product_code` (`product_code`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SQL;

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
    $v .= ($p['shelf_life_days'] !== null ? intval($p['shelf_life_days']) : "1") . ",";
    $v .= intval($p['status']) . ",";
    $v .= intval($p['sort_order']) . ",";
    $v .= db()->quote($p['created_at'] ?? date('Y-m-d H:i:s')) . ",";
    $v .= db()->quote($p['updated_at'] ?? date('Y-m-d H:i:s'));
    $v .= ")";
    $values[] = $v;
}

$columnsList = "(`id`, `product_code`, `product_name`, `product_name_am`, `category`, `variety`, `unit`, `unit_price`, `walkin_price`, `image`, `description`, `description_am`, `stock_quantity`, `shelf_life_days`, `status`, `sort_order`, `created_at`, `updated_at`)";

$insertSection = "LOCK TABLES `dairy_products` WRITE;\n";
$insertSection .= "/*!40000 ALTER TABLE `dairy_products` DISABLE KEYS */;\n";
$insertSection .= "INSERT INTO `dairy_products` $columnsList VALUES\n" . implode(",\n", $values) . ";\n";
$insertSection .= "/*!40000 ALTER TABLE `dairy_products` ENABLE KEYS */;\n";
$insertSection .= "UNLOCK TABLES;";

$fullDumpBlock = $createTable . "\n\n--\n-- Dumping data for table `dairy_products`\n--\n\n" . $insertSection;

// Read database.sql
$content = file_get_contents($sqlFile);

// Replace from DROP TABLE IF EXISTS `dairy_products` down to UNLOCK TABLES
$pattern = '/DROP TABLE IF EXISTS `dairy_products`;.*?UNLOCK TABLES;/s';
if (preg_match($pattern, $content)) {
    $newContent = preg_replace($pattern, $fullDumpBlock, $content);
    file_put_contents($sqlFile, $newContent);
    echo "Successfully updated database.sql schema and inserts with explicit column mapping!\n";
} else {
    echo "Pattern for dairy_products not found in database.sql\n";
}
