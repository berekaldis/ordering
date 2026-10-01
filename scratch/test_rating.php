<?php
require_once __DIR__ . '/../config.php';

try {
    $db = db();
    
    // Find an order number to test
    $stmt = $db->query("SELECT order_number, chat_id, phone_number, client_name FROM pre_orders ORDER BY created_at DESC LIMIT 1");
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($order);
    
    $orderNumber = $order['order_number'];
    $serviceRating = 5;
    $productRating = 4;
    $deliveryRating = 5;
    $writtenFeedback = "Test review from code";
    $chatId = $order['chat_id'] ?? '';
    $phoneNumber = $order['phone_number'] ?? '';
    $clientName = $order['client_name'] ?? 'Test User';
    
    $checkStmt = $db->prepare("SELECT id FROM feedback WHERE order_number = ? LIMIT 1");
    $checkStmt->execute([$orderNumber]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "Updating existing feedback ID: {$existing['id']}...\n";
        $upd = $db->prepare("
            UPDATE feedback 
            SET service_rating = ?, product_rating = ?, delivery_rating = ?, written_feedback = ?, chat_id = ?, phone_number = ?, client_name = ?
            WHERE id = ?
        ");
        $upd->execute([
            $serviceRating, $productRating, $deliveryRating, $writtenFeedback,
            $chatId, $phoneNumber, $clientName, $existing['id']
        ]);
        echo "Update SUCCESS!\n";
    } else {
        echo "Inserting new feedback...\n";
        $feedbackId = bin2hex(random_bytes(16));
        $ins = $db->prepare("
            INSERT INTO feedback (id, order_number, chat_id, phone_number, client_name, branch_id, service_rating, product_rating, delivery_rating, written_feedback, created_at)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, NOW())
        ");
        $ins->execute([
            $feedbackId, $orderNumber, $chatId, $phoneNumber, $clientName,
            $serviceRating, $productRating, $deliveryRating, $writtenFeedback
        ]);
        echo "Insert SUCCESS!\n";
    }

} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
