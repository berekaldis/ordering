<?php
require_once __DIR__ . '/../config.php';

function clean($str, $max = 255) {
    if ($str === null) return '';
    $str = trim(stripslashes($str));
    return substr($str, 0, $max);
}

function cleanPhone($phone) {
    $d = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strpos($d, '251') === 0) $d = substr($d, 3);
    if (strpos($d, '0') === 0) $d = substr($d, 1);
    if (strlen($d) === 9 && preg_match('/^[79][0-9]{8}$/', $d)) {
        return ['ok' => true, 'intl' => '+251' . $d, 'local' => '0' . $d];
    }
    return ['ok' => false];
}

try {
    $db = db();
    
    $_GET['order_number'] = 'KLD-00008';
    
    $on    = clean($_GET['order_number'] ?? $_POST['order_number'] ?? '', 50);
    $ci    = clean($_GET['chat_id']      ?? $_POST['chat_id']      ?? '', 50);
    $phone = clean($_GET['phone']        ?? $_POST['phone']        ?? '', 30);

    if (empty($on)) {
        if (!empty($ci)) {
            $s = $db->prepare("SELECT order_number FROM pre_orders WHERE chat_id = ? ORDER BY created_at DESC LIMIT 1");
            $s->execute([$ci]);
            $r = $s->fetch(PDO::FETCH_ASSOC);
            if ($r) $on = $r['order_number'];
        }
        if (empty($on) && !empty($phone)) {
            $pr = cleanPhone($phone);
            if ($pr['ok']) {
                $s = $db->prepare("SELECT order_number FROM pre_orders WHERE phone_number = ? OR phone_number = ? ORDER BY created_at DESC LIMIT 1");
                $s->execute([$pr['intl'], $pr['local']]);
                $r = $s->fetch(PDO::FETCH_ASSOC);
                if ($r) $on = $r['order_number'];
            }
        }
    }

    if (empty($on)) {
        echo json_encode(['success' => false, 'message' => 'No recent order found to track. Tap Order Now to get started!']);
        exit;
    }

    $labels = [
        'Pending' => '⏳ Pending Verification',
        'Confirmed' => '✅ Order Confirmed',
        'Out for Delivery' => '🛵 Out for Delivery',
        'Delivered' => '☕ Delivered',
        'Cancelled' => '❌ Cancelled',
        'Rejected' => '❌ Rejected'
    ];

    $sql  = "SELECT id, order_number, client_name, phone_number, status, rejection_reason, total_amount, delivery_address, delivery_time_slot, delivery_date, created_at FROM pre_orders WHERE order_number = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$on]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    echo "Order Query SUCCESS!\n";
    print_r($order);

} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
