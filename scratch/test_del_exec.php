<?php
require_once 'config.php';

// Check order items for product_id
$stmt = db()->query("SELECT DISTINCT pre_order_product_id FROM pre_order_items");
$orderedProductIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Products in pre_order_items: " . implode(', ', $orderedProductIds) . "\n";

// Try deleting a product in a transaction and rollback
db()->beginTransaction();
try {
    $prodId = $orderedProductIds[0] ?? 1;
    echo "Attempting to delete product ID $prodId...\n";
    $del = db()->prepare("DELETE FROM dairy_products WHERE id = ?");
    $del->execute([$prodId]);
    echo "Delete succeeded!\n";
} catch (Exception $e) {
    echo "Delete failed with error: " . $e->getMessage() . "\n";
}
db()->rollBack();
