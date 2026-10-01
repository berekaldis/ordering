<?php
require_once __DIR__ . '/../config.php';

$ecaProducts = [
    ['code' => '1001', 'name' => 'Ambo', 'name_am' => 'አምቦ', 'category' => 'beverage', 'unit' => 'bottle', 'price' => 50.00],
    ['code' => '1002', 'name' => 'Soft Drink', 'name_am' => 'Soft Drink', 'category' => 'beverage', 'unit' => 'can', 'price' => 50.00],
    ['code' => '1003', 'name' => 'Bottled Water 600ml', 'name_am' => 'Bottled Water 600ml', 'category' => 'beverage', 'unit' => 'bottle', 'price' => 35.00],
    ['code' => '2001', 'name' => 'Banana Cake', 'name_am' => 'Banana Cake', 'category' => 'pastry', 'unit' => 'slice', 'price' => 110.00],
    ['code' => '2002', 'name' => 'Bigne 3PCS', 'name_am' => 'Bigne 3PCS', 'category' => 'pastry', 'unit' => 'portion', 'price' => 90.00],
    ['code' => '2003', 'name' => 'Chocolate Millefoglie', 'name_am' => 'Chocolate Millefoglie', 'category' => 'pastry', 'unit' => 'piece', 'price' => 120.00],
    ['code' => '2006', 'name' => 'Fasting Millefoglie', 'name_am' => 'Fasting Millefoglie', 'category' => 'pastry', 'unit' => 'piece', 'price' => 110.00],
    ['code' => '2007', 'name' => 'Millefoglie', 'name_am' => 'Millefoglie', 'category' => 'pastry', 'unit' => 'piece', 'price' => 115.00],
    ['code' => '2015', 'name' => 'Assorted Cake', 'name_am' => 'Assorted Cake', 'category' => 'pastry', 'unit' => 'slice', 'price' => 130.00],
    ['code' => '2016', 'name' => 'Soft Cake', 'name_am' => 'Soft Cake', 'category' => 'pastry', 'unit' => 'slice', 'price' => 115.00],
    ['code' => '2020', 'name' => 'Anebabero Slice', 'name_am' => 'Anebabero Slice', 'category' => 'pastry', 'unit' => 'slice', 'price' => 100.00],
    ['code' => '2021', 'name' => 'Cookies Big', 'name_am' => 'Cookies Big', 'category' => 'pastry', 'unit' => 'piece', 'price' => 60.00],
    ['code' => '2022', 'name' => 'Cookies Small', 'name_am' => 'Cookies Small', 'category' => 'pastry', 'unit' => 'piece', 'price' => 35.00],
    ['code' => '2044', 'name' => 'Fasting Baklava', 'name_am' => 'Fasting Baklava', 'category' => 'pastry', 'unit' => 'piece', 'price' => 95.00],
    ['code' => '3001', 'name' => 'Cold Cup', 'name_am' => 'Cold Cup', 'category' => 'packaging', 'unit' => 'piece', 'price' => 15.00],
    ['code' => '3003', 'name' => 'Torta Box', 'name_am' => 'Torta Box', 'category' => 'packaging', 'unit' => 'piece', 'price' => 25.00],
    ['code' => '3004', 'name' => 'Hot Cup 4oz', 'name_am' => 'Hot Cup 4oz', 'category' => 'packaging', 'unit' => 'piece', 'price' => 10.00],
    ['code' => '4001', 'name' => 'French Toast Served With Syrup', 'name_am' => 'French Toast Served With Syrup', 'category' => 'food', 'unit' => 'plate', 'price' => 180.00],
    ['code' => '4002', 'name' => 'Omelette', 'name_am' => 'Omelette', 'category' => 'food', 'unit' => 'plate', 'price' => 150.00],
    ['code' => '4003', 'name' => 'Omelette With Cheese', 'name_am' => 'Omelette With Cheese', 'category' => 'food', 'unit' => 'plate', 'price' => 180.00],
    ['code' => '4004', 'name' => 'Scrambled Eggs', 'name_am' => 'Scrambled Eggs', 'category' => 'food', 'unit' => 'plate', 'price' => 140.00],
    ['code' => '4005', 'name' => 'Cheese Burger', 'name_am' => 'Cheese Burger', 'category' => 'food', 'unit' => 'portion', 'price' => 260.00],
    ['code' => '4008', 'name' => 'Lentil Sambusa', 'name_am' => 'Lentil Sambusa', 'category' => 'food', 'unit' => 'piece', 'price' => 30.00],
    ['code' => '4009', 'name' => 'Meat Sambusa', 'name_am' => 'Meat Sambusa', 'category' => 'food', 'unit' => 'piece', 'price' => 40.00],
    ['code' => '4010', 'name' => 'Tuna Sandwich', 'name_am' => 'Tuna Sandwich', 'category' => 'food', 'unit' => 'portion', 'price' => 220.00],
    ['code' => '4011', 'name' => 'Steak Sandwich', 'name_am' => 'Steak Sandwich', 'category' => 'food', 'unit' => 'portion', 'price' => 280.00],
    ['code' => '4012', 'name' => 'Club Sandwich', 'name_am' => 'Club Sandwich', 'category' => 'food', 'unit' => 'portion', 'price' => 290.00],
    ['code' => '4015', 'name' => 'Kaldis Special Burger', 'name_am' => 'Kaldis Special Burger', 'category' => 'food', 'unit' => 'portion', 'price' => 320.00],
    ['code' => '4017', 'name' => 'Egg Sandwich With Cheese', 'name_am' => 'Egg Sandwich With Cheese', 'category' => 'food', 'unit' => 'portion', 'price' => 190.00],
    ['code' => '4018', 'name' => 'Cheese Burger With Egg', 'name_am' => 'Cheese Burger With Egg', 'category' => 'food', 'unit' => 'portion', 'price' => 290.00],
    ['code' => '4023', 'name' => 'Chicken Pesto Wrap', 'name_am' => 'Chicken Pesto Wrap', 'category' => 'food', 'unit' => 'portion', 'price' => 270.00],
    ['code' => '4027', 'name' => 'Veggie Wrap', 'name_am' => 'Veggie Wrap', 'category' => 'food', 'unit' => 'portion', 'price' => 210.00],
    ['code' => '4140', 'name' => 'Egg Sandwich', 'name_am' => 'Egg Sandwich', 'category' => 'food', 'unit' => 'portion', 'price' => 160.00],
    ['code' => '4510', 'name' => 'Take Away Fast Food', 'name_am' => 'Take Away Fast Food', 'category' => 'food', 'unit' => 'portion', 'price' => 200.00],
    ['code' => '5001', 'name' => 'Cafe Latte Short', 'name_am' => 'Cafe Latte Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 75.00],
    ['code' => '5002', 'name' => 'Cafe Latte Tall', 'name_am' => 'Cafe Latte Tall', 'category' => 'coffee', 'unit' => 'cup', 'price' => 85.00],
    ['code' => '5003', 'name' => 'Cappuccino Short', 'name_am' => 'Cappuccino Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 75.00],
    ['code' => '5004', 'name' => 'Cappuccino Tall', 'name_am' => 'Cappuccino Tall', 'category' => 'coffee', 'unit' => 'cup', 'price' => 85.00],
    ['code' => '5005', 'name' => 'Caramel Macchiato Short', 'name_am' => 'Caramel Macchiato Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 85.00],
    ['code' => '5006', 'name' => 'Caramel Macchiato Tall', 'name_am' => 'Caramel Macchiato Tall', 'category' => 'coffee', 'unit' => 'cup', 'price' => 95.00],
    ['code' => '5007', 'name' => 'Coffee Americano Short', 'name_am' => 'Coffee Americano Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 60.00],
    ['code' => '5009', 'name' => 'Coffee Short', 'name_am' => 'Coffee Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 50.00],
    ['code' => '5010', 'name' => 'Coffee Tall', 'name_am' => 'Coffee Tall', 'category' => 'coffee', 'unit' => 'cup', 'price' => 60.00],
    ['code' => '5011', 'name' => 'Espresso Short', 'name_am' => 'Espresso Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 65.00],
    ['code' => '5012', 'name' => 'Espresso Tall', 'name_am' => 'Espresso Tall', 'category' => 'coffee', 'unit' => 'cup', 'price' => 75.00],
    ['code' => '5014', 'name' => 'Coffee Mate Cappuccino Short', 'name_am' => 'Coffee Mate Cappuccino Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 80.00],
    ['code' => '5015', 'name' => 'Coffee Mate Cappuccino Tall', 'name_am' => 'Coffee Mate Cappuccino Tall', 'category' => 'coffee', 'unit' => 'cup', 'price' => 90.00],
    ['code' => '5018', 'name' => 'Coffee Mate Cafe Latte Short', 'name_am' => 'Coffee Mate Cafe Latte Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 80.00],
    ['code' => '5020', 'name' => 'Coffee Mate Macchiato Short', 'name_am' => 'Coffee Mate Macchiato Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 80.00],
    ['code' => '5021', 'name' => 'Coffee Mate Macchiato Tall', 'name_am' => 'Coffee Mate Macchiato Tall', 'category' => 'coffee', 'unit' => 'cup', 'price' => 90.00],
    ['code' => '5023', 'name' => 'Hot Chocolate Short', 'name_am' => 'Hot Chocolate Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 85.00],
    ['code' => '5025', 'name' => 'Macchiato Short', 'name_am' => 'Macchiato Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 75.00],
    ['code' => '5026', 'name' => 'Macchiato Tall', 'name_am' => 'Macchiato Tall', 'category' => 'coffee', 'unit' => 'cup', 'price' => 85.00],
    ['code' => '5028', 'name' => 'Steamed Milk Short', 'name_am' => 'Steamed Milk Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 60.00],
    ['code' => '5029', 'name' => 'Steamed Milk Tall', 'name_am' => 'Steamed Milk Tall', 'category' => 'coffee', 'unit' => 'cup', 'price' => 70.00],
    ['code' => '5037', 'name' => 'Maslati Tea', 'name_am' => 'Maslati Tea', 'category' => 'tea', 'unit' => 'cup', 'price' => 65.00],
    ['code' => '5038', 'name' => 'Tea', 'name_am' => 'Tea', 'category' => 'tea', 'unit' => 'cup', 'price' => 40.00],
    ['code' => '5041', 'name' => 'Tea Espresso', 'name_am' => 'Tea Espresso', 'category' => 'tea', 'unit' => 'cup', 'price' => 50.00],
    ['code' => '5042', 'name' => 'Steamed Milk With Tea Short', 'name_am' => 'Steamed Milk With Tea Short', 'category' => 'coffee', 'unit' => 'cup', 'price' => 65.00],
    ['code' => '5043', 'name' => 'Steamed Milk With Tea Tall', 'name_am' => 'Steamed Milk With Tea Tall', 'category' => 'coffee', 'unit' => 'cup', 'price' => 75.00],
    ['code' => '5045', 'name' => 'Yalekelet Tea', 'name_am' => 'Yalekelet Tea', 'category' => 'tea', 'unit' => 'cup', 'price' => 60.00],
    ['code' => '5052', 'name' => 'Lemon Tea', 'name_am' => 'Lemon Tea', 'category' => 'tea', 'unit' => 'cup', 'price' => 55.00],
    ['code' => '5053', 'name' => 'Jigger Tea', 'name_am' => 'Jigger Tea', 'category' => 'tea', 'unit' => 'cup', 'price' => 60.00],
    ['code' => '5054', 'name' => 'Green Tea', 'name_am' => 'Green Tea', 'category' => 'tea', 'unit' => 'cup', 'price' => 60.00],
    ['code' => '7002', 'name' => 'Kaldi\'s Tea', 'name_am' => 'Kaldi\'s Tea', 'category' => 'tea', 'unit' => 'cup', 'price' => 70.00],
    ['code' => '7003', 'name' => 'Extra Honey', 'name_am' => 'Extra Honey', 'category' => 'extra', 'unit' => 'portion', 'price' => 25.00],
    ['code' => '7004', 'name' => 'Mixed Juice', 'name_am' => 'Mixed Juice', 'category' => 'beverage', 'unit' => 'glass', 'price' => 120.00],
    ['code' => '7008', 'name' => 'Strawberry Special With Milk', 'name_am' => 'Strawberry Special With Milk', 'category' => 'beverage', 'unit' => 'glass', 'price' => 140.00],
    ['code' => '7010', 'name' => 'Pineapple Juice', 'name_am' => 'Pineapple Juice', 'category' => 'beverage', 'unit' => 'glass', 'price' => 110.00],
    ['code' => '7011', 'name' => 'Avocado Juice', 'name_am' => 'Avocado Juice', 'category' => 'beverage', 'unit' => 'glass', 'price' => 110.00],
    ['code' => '7017', 'name' => 'Mango Juice', 'name_am' => 'Mango Juice', 'category' => 'beverage', 'unit' => 'glass', 'price' => 110.00],
    ['code' => '7019', 'name' => 'Take Away Pastry', 'name_am' => 'Take Away Pastry', 'category' => 'pastry', 'unit' => 'piece', 'price' => 100.00],
    ['code' => '7034', 'name' => 'Papaya Juice', 'name_am' => 'Papaya Juice', 'category' => 'beverage', 'unit' => 'glass', 'price' => 110.00],
    ['code' => '9348', 'name' => 'Guava Juice', 'name_am' => 'Guava Juice', 'category' => 'beverage', 'unit' => 'glass', 'price' => 110.00],
    ['code' => '9374', 'name' => 'Hot Cup 7oz', 'name_am' => 'Hot Cup 7oz', 'category' => 'packaging', 'unit' => 'piece', 'price' => 12.00],
];

echo "Updating database with ECA Branch Products (" . count($ecaProducts) . " items)...\n";

$checkStmt = db()->prepare("SELECT id FROM dairy_products WHERE product_code = ? LIMIT 1");
$updateStmt = db()->prepare("UPDATE dairy_products SET product_name=?, product_name_am=?, category=?, unit=?, unit_price=?, status=1 WHERE id=?");
$insertStmt = db()->prepare("INSERT INTO dairy_products (product_code, product_name, product_name_am, category, unit, unit_price, stock_quantity, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, 100, 1, ?)");

$inserted = 0;
$updated = 0;
$sort = 1;

foreach ($ecaProducts as $p) {
    $checkStmt->execute([$p['code']]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $updateStmt->execute([$p['name'], $p['name_am'], $p['category'], $p['unit'], $p['price'], $existing['id']]);
        $updated++;
    } else {
        $insertStmt->execute([$p['code'], $p['name'], $p['name_am'], $p['category'], $p['unit'], $p['price'], $sort]);
        $inserted++;
    }
    $sort++;
}

echo "Successfully updated $updated and inserted $inserted products into dairy_products!\n";
