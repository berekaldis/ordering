<?php
require_once '../config.php';
requireAdminLogin();
requirePermission('orders');

// ============================================================
// HELPER: Send Telegram Notification to Customer
// ============================================================
function sendCustomerNotification($chatId, $message, $replyMarkup = null) {
    if (empty($chatId)) return false;
    try {
        $db = db();
        $stmt = $db->query("SELECT `value` FROM settings WHERE `key` = 'bot_token'");
        $botToken = $stmt ? $stmt->fetchColumn() : null;
        if (!$botToken) return false;

        $url = "https://api.telegram.org/bot" . $botToken . "/sendMessage";
        $postData = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
        if (!empty($replyMarkup)) {
            $postData['reply_markup'] = is_array($replyMarkup) ? json_encode($replyMarkup) : $replyMarkup;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        curl_close($ch);
        return true;
    } catch (Exception $e) {
        error_log("Telegram notification failed: " . $e->getMessage());
        return false;
    }
}

$validStatuses = ['Pending', 'Confirmed', 'Out for Delivery', 'Delivered', 'Cancelled'];

// ============================================================
// AJAX HANDLERS
// ============================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    // --- Get Order Details for View Modal ---
    if ($action === 'get_order_details') {
        $id = intval($_GET['id'] ?? 0);
        try {
            $stmt = db()->prepare("SELECT po.*, loc.name as location_name FROM pre_orders po LEFT JOIN delivery_locations loc ON po.delivery_location_id = loc.id WHERE po.id = ?");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) { echo json_encode(['success' => false, 'message' => 'Order not found']); exit; }

            $itemStmt = db()->prepare("SELECT poi.*, dp.product_name FROM pre_order_items poi LEFT JOIN dairy_products dp ON poi.pre_order_product_id = dp.id WHERE poi.pre_order_id = ?");
            $itemStmt->execute([$id]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            // Get order timeline
            $timelineStmt = db()->prepare("
                SELECT action, created_at, user_name
                FROM activity_logs
                WHERE target_type = 'ORDER' AND target_id = ?
                ORDER BY created_at ASC
            ");
            $timelineStmt->execute([$id]);
            $timeline = $timelineStmt->fetchAll(PDO::FETCH_ASSOC);

            // Get customer history
            $customerOrdersStmt = db()->prepare("
                SELECT order_number, status, total_amount, created_at
                FROM pre_orders
                WHERE chat_id = ? AND id != ?
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $customerOrdersStmt->execute([$order['chat_id'], $id]);
            $customerOrders = $customerOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'order' => $order, 'items' => $items, 'timeline' => $timeline, 'customer_orders' => $customerOrders]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Database error']); }
        exit;
    }

    // --- Get Order Details for Edit Modal ---
    if ($action === 'get_edit_data') {
        $id = intval($_GET['id'] ?? 0);
        try {
            $stmt = db()->prepare("SELECT * FROM pre_orders WHERE id = ?");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) { echo json_encode(['success' => false, 'message' => 'Order not found']); exit; }

            $itemStmt = db()->prepare("SELECT poi.*, dp.product_name FROM pre_order_items poi LEFT JOIN dairy_products dp ON poi.pre_order_product_id = dp.id WHERE poi.pre_order_id = ?");
            $itemStmt->execute([$id]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'order' => $order, 'items' => $items]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Database error']); }
        exit;
    }

    // --- Update Status (AJAX) ---
    if ($action === 'update_status') {
        $input = json_decode(file_get_contents('php://input'), true);
        $orderId = intval($input['order_id'] ?? 0);
        $newStatus = $input['status'] ?? '';
        $note = trim($input['note'] ?? '');
        
        if ($orderId && in_array($newStatus, $validStatuses)) {
            try {
                $fetchStmt = db()->prepare("SELECT * FROM pre_orders WHERE id = ?");
                $fetchStmt->execute([$orderId]);
                $order = $fetchStmt->fetch(PDO::FETCH_ASSOC);

                if ($order) {
                    $updateStmt = db()->prepare("UPDATE pre_orders SET status = ?, updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$newStatus, $orderId]);
                    
                    logActivity($_SESSION['admin_id'] ?? 0, 'UPDATE_ORDER_STATUS', 'ORDER', $orderId, "Status updated to $newStatus");
                    $notifSent = false;
                    $chatId = $order['chat_id'];
                    $orderNum = $order['order_number'];
                    $lang = $order['language'] ?? 'en';
                    
                    if (!empty($chatId)) {
                        $msg = '';
                        if ($newStatus === 'Paid') {
                            $msg = $lang === 'am' ? "✅ <b>ክፍያው ተረጋግጧል!</b>\n\nየትእዛዝዎ <code>{$orderNum}</code> ክፍያ ተረጋግጧል።" : "✅ <b>Payment Confirmed!</b>\n\nYour payment for order <code>{$orderNum}</code> has been verified.";
                            $notifSent = true;
                        } else if ($newStatus === 'Cancelled' || $newStatus === 'Rejected') {
                            $msg = $lang === 'am' ? "❌ <b>ትእዛዝ ተሰርዟል</b>\n\nትእዛዝዎ <code>{$orderNum}</code> ተሰርዟል።" : "❌ <b>Order {$newStatus}</b>\n\nYour order <code>{$orderNum}</code> has been {$newStatus}.";
                            $notifSent = true;
                        } else if ($newStatus === 'Out for Delivery') {
                            $msg = "🚚 <b>Out for Delivery!</b>\n\nYour order <code>{$orderNum}</code> is on its way!";
                            $notifSent = true;
                        } else if ($newStatus === 'Delivered') {
                            $msg = "🎉 <b>Order Delivered!</b>\n\nYour order <code>{$orderNum}</code> has been delivered to your office desk at ECA. Enjoy your coffee & meal! ☕🥐\n\n⭐ <b>Please rate your experience:</b>\nHelp us improve Product Quality, Delivery Speed, and Service.";
                            $notifSent = true;
                            $miniAppUrl = getSetting('mini_app_url', SITE_URL . '/miniapp/app.html');
                            $replyMarkup = [
                                'inline_keyboard' => [
                                    [
                                        ['text' => '⭐ Rate Product & Delivery', 'web_app' => ['url' => $miniAppUrl . '?action=rate&order_number=' . urlencode($orderNum)]]
                                    ]
                                ]
                            ];
                        }

                        if ($msg) {
                            if (!empty($note)) {
                                $msg .= "\n\n📝 <b>Note:</b> " . $note;
                            }
                            sendCustomerNotification($chatId, $msg, $replyMarkup ?? null);
                        }
                    }
                    echo json_encode(['success' => true, 'notifSent' => $notifSent]);
                } else { echo json_encode(['success' => false, 'message' => 'Order not found']); }
            } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        } else { echo json_encode(['success' => false, 'message' => 'Invalid status']); }
        exit;
    }

    // --- Save Edited Order (AJAX) ---
    if ($action === 'save_order') {
        $input = json_decode(file_get_contents('php://input'), true);
        $orderId = intval($input['order_id'] ?? 0);
        
        if (!$orderId) { echo json_encode(['success' => false, 'message' => 'Invalid Order ID']); exit; }

        try {
            $db = db();
            $db->beginTransaction();

            $stmt = $db->prepare("UPDATE pre_orders SET 
                client_name = ?, phone_number = ?, building_name = ?, apartment_number = ?, 
                delivery_address = ?, delivery_date = ?, transaction_reference = ?, updated_at = NOW() 
                WHERE id = ?");
            
            $address = ($input['building_name'] ?? '') . ', ' . ($input['apartment_number'] ?? '');
            $stmt->execute([
                $input['client_name'], $input['phone_number'], $input['building_name'], $input['apartment_number'],
                $address, $input['delivery_date'], $input['transaction_reference'], $orderId
            ]);

            $db->prepare("DELETE FROM pre_order_items WHERE pre_order_id = ?")->execute([$orderId]);
            
            $insertItem = $db->prepare("INSERT INTO pre_order_items (pre_order_id, pre_order_product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            $totalAmount = 0;
            
            foreach ($input['items'] as $item) {
                $qty = intval($item['quantity']);
                $price = floatval($item['unit_price']);
                $sub = $qty * $price;
                $totalAmount += $sub;
                $insertItem->execute([$orderId, $item['product_id'], $qty, $price, $sub]);
            }

            $db->prepare("UPDATE pre_orders SET total_amount = ? WHERE id = ?")->execute([$totalAmount, $orderId]);

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Order updated successfully', 'new_total' => $totalAmount]);

        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to update order: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- Export CSV ---
    if ($action === 'export_csv') {
        $statusFilter = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $paymentMethod = $_GET['payment_method'] ?? '';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=kaldis_orders_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Order #', 'Client Name', 'Phone', 'Amount (ETB)', 'Payment Method', 'Reference', 'Status', 'Delivery Date', 'Address', 'Created At']);

        $query = "SELECT po.* FROM pre_orders po WHERE 1=1";
        $params = [];
        if ($statusFilter && in_array($statusFilter, $validStatuses)) { $query .= " AND po.status = ?"; $params[] = $statusFilter; }
        if ($search) { $query .= " AND (po.order_number LIKE ? OR po.client_name LIKE ? OR po.phone_number LIKE ?)"; $sp = "%$search%"; $params = array_merge($params, [$sp, $sp, $sp]); }
        if ($dateFrom) { $query .= " AND DATE(po.created_at) >= ?"; $params[] = $dateFrom; }
        if ($dateTo) { $query .= " AND DATE(po.created_at) <= ?"; $params[] = $dateTo; }
        if ($paymentMethod) { $query .= " AND po.payment_method = ?"; $params[] = $paymentMethod; }
        $query .= " ORDER BY po.created_at DESC";

        $stmt = db()->prepare($query);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [$row['order_number'], $row['client_name'], $row['phone_number'], $row['total_amount'], $row['payment_method'], $row['transaction_reference'], $row['status'], $row['delivery_date'], $row['delivery_address'], $row['created_at']]);
        }
        fclose($output);
        exit;
    }
    
    // --- Bulk Status Update ---
    if ($action === 'bulk_update_status') {
        $input = json_decode(file_get_contents('php://input'), true);
        $orderIds = $input['order_ids'] ?? [];
        $newStatus = $input['status'] ?? '';
        $note = trim($input['note'] ?? '');
        
        if (!empty($orderIds) && in_array($newStatus, $validStatuses)) {
            try {
                $db = db();
                $db->beginTransaction();
                
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                $stmt = $db->prepare("SELECT id, chat_id, order_number, language FROM pre_orders WHERE id IN ($placeholders)");
                $stmt->execute($orderIds);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $updateStmt = $db->prepare("UPDATE pre_orders SET status = ?, updated_at = NOW() WHERE id = ?");
                $notifCount = 0;
                
                foreach ($orders as $order) {
                    $updateStmt->execute([$newStatus, $order['id']]);
                    
                    if (!empty($order['chat_id'])) {
                        $msg = '';
                        $lang = $order['language'] ?? 'en';
                        
                        if ($newStatus === 'Paid') {
                            $msg = $lang === 'am' ? "✅ <b>ክፍያው ተረጋግጧል!</b>\n\nየትእዛዝዎ <code>{$order['order_number']}</code> ክፍያ ተረጋግጧል።" : "✅ <b>Payment Confirmed!</b>\n\nYour payment for order <code>{$order['order_number']}</code> has been verified.";
                        } else if ($newStatus === 'Cancelled' || $newStatus === 'Rejected') {
                            $msg = $lang === 'am' ? "❌ <b>ትእዛዝ ተሰርዟል</b>\n\nትእዛዝዎ <code>{$order['order_number']}</code> ተሰርዟል።" : "❌ <b>Order {$newStatus}</b>\n\nYour order <code>{$order['order_number']}</code> has been {$newStatus}.";
                        } else if ($newStatus === 'Out for Delivery') {
                            $msg = "🚚 <b>Out for Delivery!</b>\n\nYour order <code>{$order['order_number']}</code> is on its way!";
                        } else if ($newStatus === 'Delivered') {
                            $msg = "📦 <b>Order Delivered!</b>\n\nYour order <code>{$order['order_number']}</code> has been delivered. Enjoy! 🥛";
                        }
                        
                        if ($msg) {
                            if (!empty($note)) {
                                $msg .= "\n\n📝 <b>Note:</b> " . $note;
                            }
                            if (sendCustomerNotification($order['chat_id'], $msg)) {
                                $notifCount++;
                            }
                        }
                    }
                }
                
                // Log bulk action
                logActivity($_SESSION['admin_id'] ?? 0, 'BULK_UPDATE_ORDER_STATUS', 'ORDERS', implode(',', $orderIds), "Bulk status updated to $newStatus for " . count($orderIds) . " orders");
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => "Updated " . count($orderIds) . " orders", 'notifications_sent' => $notifCount]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Bulk update failed: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid orders or status']);
        }
        exit;
    }

    // --- Send Reminder ---
    if ($action === 'send_reminder') {
        $input = json_decode(file_get_contents('php://input'), true);
        $orderIds = $input['order_ids'] ?? [];
        
        if (!empty($orderIds)) {
            try {
                $db = db();
                $db->beginTransaction();
                
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                $stmt = $db->prepare("SELECT id, chat_id, order_number FROM pre_orders WHERE id IN ($placeholders)");
                $stmt->execute($orderIds);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $reminderSent = 0;
                
                foreach ($orders as $order) {
                    if (!empty($order['chat_id']) && sendCustomerNotification($order['chat_id'], "🔔 <b>Order Reminder</b>\n\nYour order <code>{$order['order_number']}</code> is still pending. Please check your payment details.")) {
                        $reminderSent++;
                    }
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => "Sent reminders to {$reminderSent} orders"]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to send reminders: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No orders selected']);
        }
        exit;
    }

    exit;
}

// ============================================================
// PAGE LOAD: GET ORDERS WITH FILTERS & STATS
// ============================================================
 $statusFilter = $_GET['status'] ?? '';
 $search = $_GET['search'] ?? '';
 $dateFrom = $_GET['date_from'] ?? '';
 $dateTo = $_GET['date_to'] ?? '';
 $paymentMethod = $_GET['payment_method'] ?? '';
 $page = $_GET['page'] ?? 1;
 $limit = 20;
 $offset = ($page - 1) * $limit;

try {
    // Get statistics
    $statsStmt = db()->query("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered_orders,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_orders
        FROM pre_orders
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $countStmt = db()->query("SELECT status, COUNT(*) as count FROM pre_orders GROUP BY status");
    $statusCounts = [];
    while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) { $statusCounts[$row['status']] = $row['count']; }
    $totalOrdersCount = array_sum($statusCounts);

    $query = "SELECT po.*, loc.name as location_name FROM pre_orders po LEFT JOIN delivery_locations loc ON po.delivery_location_id = loc.id WHERE 1=1";
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, $validStatuses)) { $query .= " AND po.status = ?"; $params[] = $statusFilter; }
    if ($search) { $query .= " AND (po.order_number LIKE ? OR po.client_name LIKE ? OR po.phone_number LIKE ?)"; $sp = "%$search%"; $params = array_merge($params, [$sp, $sp, $sp]); }
    if ($dateFrom) { $query .= " AND DATE(po.created_at) >= ?"; $params[] = $dateFrom; }
    if ($dateTo) { $query .= " AND DATE(po.created_at) <= ?"; $params[] = $dateTo; }
    if ($paymentMethod) { $query .= " AND po.payment_method = ?"; $params[] = $paymentMethod; }
    
    $query .= " ORDER BY po.created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    $countQuery = "SELECT COUNT(*) FROM pre_orders po WHERE 1=1";
    $countParams = [];
    if ($statusFilter && in_array($statusFilter, $validStatuses)) { $countQuery .= " AND po.status = ?"; $countParams[] = $statusFilter; }
    if ($search) { $countQuery .= " AND (po.order_number LIKE ? OR po.client_name LIKE ? OR po.phone_number LIKE ?)"; $countParams = array_merge($countParams, [$sp, $sp, $sp]); }
    if ($dateFrom) { $countQuery .= " AND DATE(po.created_at) >= ?"; $countParams[] = $dateFrom; }
    if ($dateTo) { $countQuery .= " AND DATE(po.created_at) <= ?"; $countParams[] = $dateTo; }
    if ($paymentMethod) { $countQuery .= " AND po.payment_method = ?"; $countParams[] = $paymentMethod; }
    $countStmt = db()->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalFilteredOrders = $countStmt->fetchColumn();
    $totalPages = ceil($totalFilteredOrders / $limit);

    // Fetch all active products for the "Add Item" dropdown in Edit Modal
    $prodStmt = db()->query("SELECT id, product_name, unit_price FROM dairy_products WHERE status = 1 ORDER BY product_name ASC");
    $allProducts = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get payment methods for filter
    $paymentStmt = db()->query("SELECT DISTINCT payment_method FROM pre_orders WHERE payment_method IS NOT NULL ORDER BY payment_method");
    $paymentMethods = $paymentStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) { $error = "Failed to load orders: " . $e->getMessage(); }

function getStatusColor($status) {
    $map = ['Pending'=>'bg-yellow-100 text-yellow-800','Paid'=>'bg-cyan-100 text-cyan-800','Confirmed'=>'bg-blue-100 text-blue-800','Processing'=>'bg-purple-100 text-purple-800','Out for Delivery'=>'bg-orange-100 text-orange-800','Delivered'=>'bg-green-100 text-green-800','Cancelled'=>'bg-red-100 text-red-800','Rejected'=>'bg-red-100 text-red-800'];
    return $map[$status] ?? 'bg-gray-100 text-gray-800';
}

function getStatusBtnColor($status) {
    $map = ['Paid'=>'bg-cyan-600 hover:bg-cyan-700','Cancelled'=>'bg-red-600 hover:bg-red-700','Rejected'=>'bg-red-600 hover:bg-red-700','Out for Delivery'=>'bg-orange-600 hover:bg-orange-700','Delivered'=>'bg-green-600 hover:bg-green-700'];
    return $map[$status] ?? 'bg-blue-600 hover:bg-blue-700';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .status-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236B7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.5rem center; background-size: 1.5em 1.5em; padding-right: 2rem; }
        .toast { position: fixed; top: 20px; right: 20px; z-index: 9999; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .checkbox-custom { appearance: none; width: 1.25rem; height: 1.25rem; border: 2px solid #d1d5db; border-radius: 0.25rem; cursor: pointer; position: relative; }
        .checkbox-custom:checked { background-color: #10b981; border-color: #10b981; }
        .checkbox-custom:checked::after { content: '✓'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-weight: bold; }
        .tag { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .tag-vip { background-color: #fef3c7; color: #92400e; }
        .tag-urgent { background-color: #fee2e2; color: #991b1b; }
        .tag-problem { background-color: #ede9fe; color: #5b21b6; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Orders Management</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage and track customer orders</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="openBulkUpdateModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-medium">
                        <i class="fas fa-edit mr-2"></i>Bulk Update
                    </button>
                    <button onclick="sendBulkReminders()" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition text-sm font-medium">
                        <i class="fas fa-bell mr-2"></i>Send Reminders
                    </button>
                    <a href="?action=export_csv&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&payment_method=<?php echo urlencode($paymentMethod); ?>" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                        <i class="fas fa-file-csv mr-2 text-green-600"></i>Export CSV
                    </a>
                    <div class="text-sm text-gray-500">Total: <span class="font-bold text-gray-800"><?php echo number_format($totalOrdersCount); ?></span></div>
                </div>
            </div>
            
            <!-- Order Analytics Summary -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Orders</p>
                                <p class="text-2xl font-bold"><?php echo number_format($stats['total_orders']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Revenue</p>
                                <p class="text-2xl font-bold"><?php echo number_format($stats['total_revenue']); ?> ETB</p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Pending Orders</p>
                                <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($stats['pending_orders']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Delivered Orders</p>
                                <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['delivered_orders']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Cancelled Orders</p>
                                <p class="text-2xl font-bold text-red-600"><?php echo number_format($stats['cancelled_orders']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-times-circle text-red-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            
                <!-- Status Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
                    <?php foreach ($validStatuses as $s): $count = $statusCounts[$s] ?? 0; ?>
                    <a href="?status=<?php echo urlencode($s); ?>" class="bg-white p-3 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition <?php echo $statusFilter === $s ? 'ring-2 ring-green-500 shadow-md' : ''; ?>">
                        <div class="text-[10px] font-semibold text-gray-500 uppercase truncate"><?php echo $s; ?></div>
                        <div class="text-xl font-bold mt-1 <?php echo explode(' ', getStatusColor($s))[1] ?? 'text-gray-800'; ?>"><?php echo $count; ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Advanced Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6 border border-gray-100">
                    <form method="GET" class="flex flex-wrap gap-3 items-center">
                        <div class="flex-1 min-w-[220px] relative">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" name="search" placeholder="Search order #, name, phone..." value="<?php echo htmlspecialchars($search); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">From Date</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-green-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">To Date</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-green-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Payment Method</label>
                            <select name="payment_method" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-green-500 outline-none">
                                <option value="">All Methods</option>
                                <?php foreach ($paymentMethods as $pm): ?>
                                <option value="<?php echo htmlspecialchars($pm); ?>" <?php echo $paymentMethod === $pm ? 'selected' : ''; ?>><?php echo htmlspecialchars($pm); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <select name="status" class="px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                            <option value="">All Status</option>
                            <?php foreach ($validStatuses as $s): ?><option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo $s; ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700"><i class="fas fa-filter mr-2"></i>Filter</button>
                        <a href="orders.php" class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg hover:bg-gray-200"><i class="fas fa-sync-alt mr-2"></i>Reset</a>
                    </form>
                </div>
                
                <!-- Bulk Actions -->
                <div id="bulkActions" class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6 hidden">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" id="selectAll" class="checkbox-custom" onchange="toggleSelectAll()">
                            <label for="selectAll" class="text-sm font-medium text-gray-700">Select all <span id="selectedCount">0</span> orders</label>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="openBulkUpdateModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-medium">
                                <i class="fas fa-edit mr-2"></i>Update Status
                            </button>
                            <button onclick="sendBulkReminders()" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition text-sm font-medium">
                                <i class="fas fa-bell mr-2"></i>Send Reminders
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Table -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">
                                        <input type="checkbox" id="tableSelectAll" class="checkbox-custom" onchange="toggleTableSelectAll()">
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Order #</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Customer</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Location / Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Payment</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($orders)): ?>
                                <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400"><i class="fas fa-box-open text-4xl mb-3 block"></i>No orders found</td></tr>
                                <?php endif; ?>
                                
                                <?php foreach ($orders as $order): ?>
                                <tr class="hover:bg-gray-50 transition" id="row_<?php echo $order['id']; ?>">
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" class="order-checkbox checkbox-custom" data-id="<?php echo $order['id']; ?>" onchange="updateSelectedCount()">
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-mono font-bold text-green-700"><?php echo htmlspecialchars($order['order_number']); ?></span>
                                            <?php 
                                            // Generate random tags for demonstration
                                            $tags = [];
                                            if (rand(1, 10) == 1) $tags[] = 'VIP';
                                            if (rand(1, 10) == 1) $tags[] = 'Urgent';
                                            if (rand(1, 10) == 1) $tags[] = 'Problem';
                                            foreach ($tags as $tag): 
                                                $tagClass = $tag === 'VIP' ? 'tag-vip' : ($tag === 'Urgent' ? 'tag-urgent' : 'tag-problem');
                                            ?>
                                            <span class="tag <?php echo $tagClass; ?>"><?php echo $tag; ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-1"><?php echo date('M d, g:i A', strtotime($order['created_at'])); ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($order['client_name']); ?></div>
                                        <div class="text-xs text-gray-500 mt-1"><i class="fas fa-phone text-[10px] mr-1"></i><?php echo htmlspecialchars($order['phone_number']); ?></div>
                                        <div class="text-xs text-gray-400 mt-1">
                                            <?php 
                                            $orderCount = db()->prepare("SELECT COUNT(*) FROM pre_orders WHERE chat_id = ?");
                                            $orderCount->execute([$order['chat_id']]);
                                            echo $orderCount->fetchColumn() . " orders total";
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-semibold text-stone-800 truncate max-w-[170px]" title="<?php echo htmlspecialchars($order['delivery_address']); ?>"><?php echo htmlspecialchars($order['location_name'] ?? $order['building_name'] ?? 'N/A'); ?></div>
                                        <div class="text-xs text-stone-500 mt-0.5 max-w-[180px] leading-tight"><?php echo htmlspecialchars($order['delivery_address']); ?></div>
                                        <?php if (!empty($order['latitude']) && !empty($order['longitude'])): ?>
                                        <div class="mt-1.5">
                                            <a href="https://www.google.com/maps?q=<?php echo $order['latitude']; ?>,<?php echo $order['longitude']; ?>" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold text-red-700 bg-red-50 hover:bg-red-100 px-2 py-0.5 rounded-full border border-red-200 transition">
                                                <i class="fas fa-map-marker-alt text-red-500"></i> GPS: <?php echo number_format($order['latitude'], 4); ?>, <?php echo number_format($order['longitude'], 4); ?>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold text-gray-800" id="total_<?php echo $order['id']; ?>">
                                        <?php echo number_format($order['total_amount'], 2); ?> <span class="text-xs font-normal text-gray-500">ETB</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-xs font-semibold text-gray-700"><?php echo htmlspecialchars($order['payment_method']); ?></div>
                                        <?php if (!empty($order['transaction_reference'])): ?>
                                        <div class="text-[10px] text-gray-400 mt-1 font-mono truncate max-w-[100px]" title="<?php echo htmlspecialchars($order['transaction_reference']); ?>"><?php echo htmlspecialchars($order['transaction_reference']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <select onchange="openStatusModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>', '<?php echo $order['status']; ?>', this.value)" 
                                                class="status-select text-xs rounded-full px-3 py-1.5 border-0 font-semibold w-full cursor-pointer focus:ring-2 focus:ring-green-500 <?php echo getStatusColor($order['status']); ?>">
                                            <?php foreach ($validStatuses as $s): ?><option value="<?php echo $s; ?>" <?php echo $order['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option><?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <?php if (!empty($order['payment_slip'])): ?>
                                            <a href="../uploads/slips/<?php echo htmlspecialchars($order['payment_slip']); ?>" target="_blank" class="text-blue-500 hover:text-blue-700 bg-blue-50 p-1.5 rounded-lg" title="Slip"><i class="fas fa-receipt text-sm"></i></a>
                                            <?php endif; ?>
                                            <?php if (!empty($order['latitude'])): ?>
                                            <a href="https://www.google.com/maps?q=<?php echo $order['latitude']; ?>,<?php echo $order['longitude']; ?>" target="_blank" class="text-red-500 hover:text-red-700 bg-red-50 p-1.5 rounded-lg" title="Map"><i class="fas fa-map-marker-alt text-sm"></i></a>
                                            <?php endif; ?>
                                            <button onclick="openEditModal(<?php echo $order['id']; ?>)" class="text-indigo-500 hover:text-indigo-700 bg-indigo-50 p-1.5 rounded-lg" title="Edit"><i class="fas fa-pen text-sm"></i></button>
                                            <button onclick="viewOrder(<?php echo $order['id']; ?>)" class="text-green-600 hover:text-green-800 bg-green-50 p-1.5 rounded-lg" title="View"><i class="fas fa-eye text-sm"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <div class="px-6 py-4 border-t flex justify-between items-center bg-gray-50">
                        <div class="text-sm text-gray-500">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalFilteredOrders); ?> of <?php echo $totalFilteredOrders; ?></div>
                        <div class="flex gap-1">
                            <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&payment_method=<?php echo urlencode($paymentMethod); ?>" class="px-3 py-1 border rounded hover:bg-white text-sm">Prev</a><?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&payment_method=<?php echo urlencode($paymentMethod); ?>" class="px-3 py-1 border rounded text-sm <?php echo $i === $page ? 'bg-green-600 text-white border-green-600' : 'hover:bg-white'; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&payment_method=<?php echo urlencode($paymentMethod); ?>" class="px-3 py-1 border rounded hover:bg-white text-sm">Next</a><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Confirmation Modal -->
    <div id="statusModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl text-center">
            <div id="smIconBox" class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center text-2xl bg-blue-100 text-blue-600 transition-colors">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-1">Update Order Status</h3>
            <p class="text-sm text-gray-500 mb-4">Order <span id="smOrderNum" class="font-bold font-mono text-gray-800"></span></p>
            
            <div class="flex items-center justify-center gap-3 mb-5 p-3 bg-gray-50 rounded-lg border">
                <span id="smOldStatus" class="px-3 py-1 rounded-full text-xs font-bold"></span>
                <i class="fas fa-long-arrow-alt-right text-gray-400 text-lg"></i>
                <span id="smNewStatus" class="px-3 py-1 rounded-full text-xs font-bold"></span>
            </div>

            <div id="smNotifBox" class="bg-blue-50 border border-blue-100 rounded-lg p-3 mb-4 text-xs text-blue-700 flex items-start gap-2 text-left hidden">
                <i class="fas fa-paper-plane mt-0.5"></i>
                <span>A Telegram notification will be sent to the customer about this update.</span>
            </div>

            <div id="smNoteBox" class="mb-4 text-left hidden">
                <label class="block text-xs font-semibold text-gray-500 mb-1">Add a note (Optional)</label>
                <textarea id="smNote" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g. Payment verified, dispatching tomorrow..."></textarea>
            </div>

            <div class="flex gap-3">
                <button onclick="closeStatusModal()" class="flex-1 px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-medium text-sm">Cancel</button>
                <button id="smConfirmBtn" onclick="confirmStatusChange()" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium text-sm transition-colors">Confirm Change</button>
            </div>
        </div>
    </div>

    <!-- Bulk Update Modal -->
    <div id="bulkUpdateModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Bulk Update Status</h3>
            <p class="text-sm text-gray-500 mb-4">Update status for <span id="bulkOrderCount" class="font-bold">0</span> selected orders</p>
            
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-500 mb-2">New Status</label>
                <select id="bulkStatusSelect" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <?php foreach ($validStatuses as $s): ?>
                    <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="bulkNotifBox" class="bg-blue-50 border border-blue-100 rounded-lg p-3 mb-4 text-xs text-blue-700 flex items-start gap-2 text-left hidden">
                <i class="fas fa-paper-plane mt-0.5"></i>
                <span>Telegram notifications will be sent to customers about this update.</span>
            </div>

            <div id="bulkNoteBox" class="mb-4 text-left hidden">
                <label class="block text-xs font-semibold text-gray-500 mb-1">Add a note (Optional)</label>
                <textarea id="bulkNote" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g. Payment verified, dispatching tomorrow..."></textarea>
            </div>

            <div class="flex gap-3">
                <button onclick="closeBulkUpdateModal()" class="flex-1 px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-medium text-sm">Cancel</button>
                <button id="bulkConfirmBtn" onclick="confirmBulkUpdate()" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium text-sm transition-colors">Update Status</button>
            </div>
        </div>
    </div>

    <!-- View Order Modal -->
    <div id="orderModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
            <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-center z-10">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2"><span class="w-8 h-8 bg-green-100 text-green-600 rounded-lg flex items-center justify-center"><i class="fas fa-box"></i></span><span id="modalTitle">Order Details</span></h3>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 w-8 h-8 rounded-lg flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
            <div id="modalContent" class="p-6 overflow-y-auto flex-1"><div class="flex justify-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div></div></div>
        </div>
    </div>

    <!-- Edit Order Modal -->
    <div id="editModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
            <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-center z-10">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2"><span class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center"><i class="fas fa-pen"></i></span><span id="editModalTitle">Edit Order</span></h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 w-8 h-8 rounded-lg flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
            <div id="editModalContent" class="p-6 overflow-y-auto flex-1"><div class="flex justify-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div></div></div>
        </div>
    </div>

    <script>
        const productCatalog = <?php echo json_encode($allProducts); ?>;
        const statusColors = <?php echo json_encode(array_map(function($s) { return getStatusColor($s); }, array_combine($validStatuses, $validStatuses))); ?>;
        const statusBtnColors = <?php echo json_encode(array_map(function($s) { return getStatusBtnColor($s); }, array_combine($validStatuses, $validStatuses))); ?>;

        let pendingStatusSelect = null;
        let pendingOrderId = null;
        let pendingNewStatus = null;
        let selectedOrders = [];

        // --- TOAST NOTIFICATION ---
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast p-4 rounded-lg shadow-lg border-l-4 ${type === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'} flex items-center gap-3`;
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span class="font-medium text-sm">${message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
        }

        // --- BULK ACTIONS ---
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            const selectAll = document.getElementById('selectAll');
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                cb.dispatchEvent(new Event('change'));
            });
        }

        function toggleTableSelectAll() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            const selectAll = document.getElementById('tableSelectAll');
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                cb.dispatchEvent(new Event('change'));
            });
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            selectedOrders = Array.from(checkboxes).map(cb => cb.dataset.id);
            document.getElementById('selectedCount').textContent = selectedOrders.length;
            document.getElementById('bulkOrderCount').textContent = selectedOrders.length;
            
            const bulkActions = document.getElementById('bulkActions');
            bulkActions.classList.toggle('hidden', selectedOrders.length === 0);
        }

        function openBulkUpdateModal() {
            if (selectedOrders.length === 0) {
                showToast('Please select orders first', 'error');
                return;
            }
            
            const modal = document.getElementById('bulkUpdateModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // Reset form
            document.getElementById('bulkStatusSelect').value = 'Pending';
            document.getElementById('bulkNote').value = '';
            
            // Show/hide note based on status
            const notifyStatuses = ['Paid', 'Cancelled', 'Rejected', 'Confirmed', 'Out for Delivery', 'Delivered'];
            document.getElementById('bulkNotifBox').classList.toggle('hidden', !notifyStatuses.includes(document.getElementById('bulkStatusSelect').value));
            document.getElementById('bulkNoteBox').classList.toggle('hidden', !notifyStatuses.includes(document.getElementById('bulkStatusSelect').value));
        }

        function closeBulkUpdateModal() {
            document.getElementById('bulkUpdateModal').classList.add('hidden');
            document.getElementById('bulkUpdateModal').classList.remove('flex');
        }

        async function confirmBulkUpdate() {
            const btn = document.getElementById('bulkConfirmBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
            btn.disabled = true;

            try {
                const res = await fetch('orders.php?action=bulk_update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        order_ids: selectedOrders,
                        status: document.getElementById('bulkStatusSelect').value,
                        note: document.getElementById('bulkNote').value 
                    })
                });
                const data = await res.json();
                
                if (data.success) {
                    showToast(`Updated ${data.message} (${data.notifications_sent} notifications sent)`, 'success');
                    // Uncheck all checkboxes and reset selection
                    document.querySelectorAll('.order-checkbox').forEach(cb => cb.checked = false);
                    updateSelectedCount();
                    closeBulkUpdateModal();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message || 'Failed to update status', 'error');
                    closeBulkUpdateModal();
                }
            } catch (e) {
                showToast('Network error', 'error');
                closeBulkUpdateModal();
            }
        }

        async function sendBulkReminders() {
            if (selectedOrders.length === 0) {
                showToast('Please select orders first', 'error');
                return;
            }
            
            if (!confirm(`Are you sure you want to send reminders to ${selectedOrders.length} orders?`)) {
                return;
            }
            
            try {
                const res = await fetch('orders.php?action=send_reminder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_ids: selectedOrders })
                });
                const data = await res.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    // Uncheck all checkboxes and reset selection
                    document.querySelectorAll('.order-checkbox').forEach(cb => cb.checked = false);
                    updateSelectedCount();
                } else {
                    showToast(data.message || 'Failed to send reminders', 'error');
                }
            } catch (e) {
                showToast('Network error', 'error');
            }
        }

        // --- STATUS CHANGE LOGIC ---
        function openStatusModal(orderId, orderNum, oldStatus, newStatus) {
            if (oldStatus === newStatus) return;
            pendingOrderId = orderId;
            pendingNewStatus = newStatus;
            
            document.getElementById('smOrderNum').textContent = orderNum;
            
            const oldEl = document.getElementById('smOldStatus');
            const newEl = document.getElementById('smNewStatus');
            const iconBox = document.getElementById('smIconBox');
            const confirmBtn = document.getElementById('smConfirmBtn');
            
            oldEl.textContent = oldStatus;
            oldEl.className = `px-3 py-1 rounded-full text-xs font-bold ${statusColors[oldStatus] || 'bg-gray-100 text-gray-800'}`;
            newEl.textContent = newStatus;
            newEl.className = `px-3 py-1 rounded-full text-xs font-bold ${statusColors[newStatus] || 'bg-gray-100 text-gray-800'}`;

            // Dynamic Icon and Button Color
            const icons = {'Paid':'fa-money-bill-wave','Cancelled':'fa-times-circle','Rejected':'fa-ban','Out for Delivery':'fa-truck','Delivered':'fa-box-open'};
            const iconColors = {'Paid':'bg-cyan-100 text-cyan-600','Cancelled':'bg-red-100 text-red-600','Rejected':'bg-red-100 text-red-600','Out for Delivery':'bg-orange-100 text-orange-600','Delivered':'bg-green-100 text-green-600'};
            
            iconBox.className = `w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center text-2xl transition-colors ${iconColors[newStatus] || 'bg-blue-100 text-blue-600'}`;
            iconBox.innerHTML = `<i class="fas ${icons[newStatus] || 'fa-exchange-alt'}"></i>`;
            confirmBtn.className = `flex-1 px-4 py-2.5 text-white rounded-xl font-medium text-sm transition-colors ${statusBtnColors[newStatus] || 'bg-blue-600 hover:bg-blue-700'}`;

            const notifyStatuses = ['Paid', 'Cancelled', 'Rejected', 'Confirmed', 'Out for Delivery', 'Delivered'];
            document.getElementById('smNotifBox').classList.toggle('hidden', !notifyStatuses.includes(newStatus));
            document.getElementById('smNoteBox').classList.toggle('hidden', !notifyStatuses.includes(newStatus));
            document.getElementById('smNote').value = '';

            document.getElementById('statusModal').classList.remove('hidden');
            document.getElementById('statusModal').classList.add('flex');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
            document.getElementById('statusModal').classList.remove('flex');
            window.location.reload(); 
        }

        async function confirmStatusChange() {
            const btn = document.getElementById('smConfirmBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
            btn.disabled = true;

            try {
                const res = await fetch('orders.php?action=update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        order_id: pendingOrderId, 
                        status: pendingNewStatus,
                        note: document.getElementById('smNote').value 
                    })
                });
                const data = await res.json();
                
                if (data.success) {
                    let msg = `Status updated to ${pendingNewStatus}`;
                    if (data.notifSent) msg += ` (Notification Sent)`;
                    showToast(msg, 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message || 'Failed to update status', 'error');
                    closeStatusModal();
                }
            } catch (e) {
                showToast('Network error', 'error');
                closeStatusModal();
            }
        }

        // --- VIEW ORDER LOGIC ---
        function viewOrder(id) {
            const modal = document.getElementById('orderModal');
            const content = document.getElementById('modalContent');
            modal.classList.remove('hidden'); modal.classList.add('flex');
            content.innerHTML = '<div class="flex justify-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div></div>';
            
            fetch(`orders.php?action=get_order_details&id=${id}`).then(r => r.json()).then(data => {
                if (data.success) {
                    const o = data.order; const items = data.items; const timeline = data.timeline; const customerOrders = data.customer_orders;
                    document.getElementById('modalTitle').textContent = o.order_number;
                    const sc = statusColors[o.status] || 'bg-gray-100 text-gray-800';
                    
                    let html = `
                    <div class="flex items-center gap-3 mb-4"><span class="px-3 py-1 rounded-full text-xs font-bold ${sc}">${o.status}</span></div>
                    
                    <!-- Order Timeline -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-gray-500 mb-3 uppercase">Order Timeline</h4>
                        <div class="space-y-3">
                            ${timeline.map(t => `
                                <div class="flex gap-3">
                                    <div class="w-2 h-2 bg-blue-500 rounded-full mt-1.5"></div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium">${t.action}</p>
                                        <p class="text-xs text-gray-500">By ${t.user_name || 'System'} at ${new Date(t.created_at).toLocaleString()}</p>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-50 p-3 rounded-lg"><div class="text-xs text-gray-500 font-semibold mb-1"><i class="fas fa-user mr-1"></i>Customer</div><div class="font-bold text-gray-800">${o.client_name}</div><div class="text-sm text-gray-600">${o.phone_number}</div></div>
                        <div class="bg-gray-50 p-3 rounded-lg"><div class="text-xs text-gray-500 font-semibold mb-1"><i class="fas fa-building mr-1"></i>Office Desk Delivery</div><div class="font-bold text-gray-800 text-sm">${o.delivery_address}</div><div class="text-sm text-gray-600 mt-0.5"><i class="far fa-calendar mr-1"></i>${o.delivery_date} ${o.delivery_time_slot ? `&bull; <span class="text-amber-800 font-medium">${o.delivery_time_slot}</span>` : ''}</div>${o.notes ? `<div class="text-xs text-amber-900 bg-amber-50 rounded p-1.5 mt-1.5"><i class="fas fa-info-circle mr-1 text-amber-700"></i><strong>Desk Note:</strong> ${o.notes}</div>` : ''}${o.latitude ? `<a href="https://www.google.com/maps?q=${o.latitude},${o.longitude}" target="_blank" class="text-xs text-blue-500 hover:underline mt-1 inline-block"><i class="fas fa-map-marker-alt mr-1"></i>View Map</a>` : ''}</div>
                    </div>
                    <div class="mb-6"><div class="text-xs text-gray-500 font-semibold mb-2 uppercase">Items</div><div class="border rounded-lg overflow-hidden"><table class="w-full text-sm"><thead class="bg-gray-50"><tr><th class="text-left p-2 text-xs text-gray-500">Product</th><th class="text-center p-2 text-xs text-gray-500">Qty</th><th class="text-right p-2 text-xs text-gray-500">Subtotal</th></tr></thead><tbody class="divide-y">`;
                    items.forEach(i => { html += `<tr><td class="p-2 font-medium">${i.product_name || 'Unknown'}</td><td class="p-2 text-center">${i.quantity}</td><td class="p-2 text-right font-semibold">${parseFloat(i.subtotal).toLocaleString()} ETB</td></tr>`; });
                    html += `</tbody><tfoot class="bg-gray-50 font-bold"><tr><td colspan="2" class="p-2 text-right">Total</td><td class="p-2 text-right text-green-700">${parseFloat(o.total_amount).toLocaleString()} ETB</td></tr></tfoot></table></div></div>`;
                    html += `<div class="grid grid-cols-2 gap-4"><div class="bg-gray-50 p-3 rounded-lg"><div class="text-xs text-gray-500 font-semibold mb-1">Payment</div><div class="font-bold text-gray-800 text-sm">${o.payment_method}</div>${o.transaction_reference ? `<div class="text-xs text-gray-500 mt-1 font-mono">Ref: ${o.transaction_reference}</div>` : ''}</div><div class="bg-gray-50 p-3 rounded-lg"><div class="text-xs text-gray-500 font-semibold mb-1">Timeline</div><div class="text-xs text-gray-600">Ordered: ${new Date(o.created_at).toLocaleString()}</div></div></div>`;
                    
                    if (o.payment_slip) html += `<div class="border-t pt-4 mt-4"><div class="text-xs text-gray-500 font-semibold mb-2 uppercase">Payment Proof</div><a href="../uploads/slips/${o.payment_slip}" target="_blank"><img src="../uploads/slips/${o.payment_slip}" class="h-32 rounded-lg object-cover border hover:opacity-80 transition"></a></div>`;
                    
                    <!-- Customer Order History -->
                    if (customerOrders.length > 0) {
                        html += `<div class="border-t pt-4 mt-4"><div class="text-xs text-gray-500 font-semibold mb-2 uppercase">Customer Order History</div><div class="space-y-2">`;
                        customerOrders.forEach(co => {
                            html += `<div class="flex justify-between items-center text-sm bg-gray-50 p-2 rounded"><span>${co.order_number}</span><span class="text-xs text-gray-500">${co.status} - ${parseFloat(co.total_amount).toLocaleString()} ETB</span></div>`;
                        });
                        html += `</div></div>`;
                    }
                    
                    content.innerHTML = html;
                } else { content.innerHTML = '<div class="text-center py-8 text-red-500">Failed to load</div>'; }
            });
        }
        function closeViewModal() { document.getElementById('orderModal').classList.add('hidden'); document.getElementById('orderModal').classList.remove('flex'); }

        // --- EDIT ORDER LOGIC ---
        function openEditModal(id) {
            const modal = document.getElementById('editModal');
            const content = document.getElementById('editModalContent');
            modal.classList.remove('hidden'); modal.classList.add('flex');
            content.innerHTML = '<div class="flex justify-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div></div>';
            
            fetch(`orders.php?action=get_edit_data&id=${id}`).then(r => r.json()).then(data => {
                if (data.success) {
                    const o = data.order; const items = data.items;
                    document.getElementById('editModalTitle').textContent = `Edit: ${o.order_number}`;
                    
                    let html = `<form id="editForm" onsubmit="saveEdit(event, ${id})">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-xs font-semibold text-gray-500 mb-1">Client Name</label><input type="text" name="client_name" value="${o.client_name}" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                            <div><label class="block text-xs font-semibold text-gray-500 mb-1">Phone Number</label><input type="text" name="phone_number" value="${o.phone_number}" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-xs font-semibold text-gray-500 mb-1">Building / Area</label><input type="text" name="building_name" value="${o.building_name || ''}" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                            <div><label class="block text-xs font-semibold text-gray-500 mb-1">Apt / Office</label><input type="text" name="apartment_number" value="${o.apartment_number || ''}" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-xs font-semibold text-gray-500 mb-1">Delivery Date</label><input type="date" name="delivery_date" value="${o.delivery_date}" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                            <div><label class="block text-xs font-semibold text-gray-500 mb-1">Transaction Ref</label><input type="text" name="transaction_reference" value="${o.transaction_reference || ''}" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-xs font-semibold text-gray-500 uppercase">Order Items</label>
                                <span class="text-xs font-bold text-green-700" id="editTotalDisplay">Total: ${parseFloat(o.total_amount).toLocaleString()} ETB</span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3 space-y-2" id="editItemsContainer">`;
                    
                    items.forEach((item, index) => {
                        html += `<div class="flex items-center gap-2 bg-white p-2 rounded border">
                            <input type="hidden" name="items[${index}][product_id]" value="${item.pre_order_product_id}">
                            <input type="hidden" name="items[${index}][unit_price]" value="${item.unit_price}" class="item-price">
                            <span class="flex-1 text-sm font-medium truncate" title="${item.product_name}">${item.product_name || 'Item'}</span>
                            <input type="number" name="items[${index}][quantity]" value="${item.quantity}" min="0" onchange="recalcEditTotal()" class="w-16 px-2 py-1 border rounded text-center text-sm item-qty">
                            <span class="text-xs text-gray-500 w-20 text-right item-sub">${parseFloat(item.subtotal).toLocaleString()} ETB</span>
                            <button type="button" onclick="this.parentElement.remove(); recalcEditTotal();" class="text-red-400 hover:text-red-600 p-1"><i class="fas fa-trash-alt text-xs"></i></button>
                        </div>`;
                    });

                    html += `</div>
                    <button type="button" onclick="addNewItemRow()" class="mt-2 w-full py-2 border-2 border-dashed border-indigo-200 text-indigo-600 rounded-lg hover:bg-indigo-50 text-xs font-bold"><i class="fas fa-plus mr-1"></i> Add Product</button>
                    </div>
                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <button type="button" onclick="closeEditModal()" class="px-5 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">Cancel</button>
                            <button type="submit" id="editSaveBtn" class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-sm"><i class="fas fa-save mr-2"></i>Save Changes</button>
                        </div>
                    </form>`;
                    content.innerHTML = html;
                } else { content.innerHTML = '<div class="text-center py-8 text-red-500">Failed to load</div>'; }
            });
        }

        function addNewItemRow() {
            const container = document.getElementById('editItemsContainer');
            const idx = container.children.length;
            let opts = '<option value="">-- Select Product --</option>';
            productCatalog.forEach(p => {
                opts += `<option value="${p.id}" data-price="${p.unit_price}">${p.product_name} (${parseFloat(p.unit_price).toLocaleString()} ETB)</option>`;
            });
            
            const div = document.createElement('div');
            div.className = 'flex items-center gap-2 bg-white p-2 rounded border border-indigo-200';
            div.innerHTML = `
                <select name="items[${idx}][product_id]" required class="flex-1 px-2 py-1 border rounded text-sm item-select" onchange="updateNewItemPrice(this)">
                    ${opts}
                </select>
                <input type="number" name="items[${idx}][quantity]" value="1" min="1" onchange="recalcEditTotal()" class="w-16 px-2 py-1 border rounded text-center text-sm item-qty">
                <input type="hidden" name="items[${idx}][unit_price]" class="item-price" value="0">
                <span class="text-xs text-gray-500 w-20 text-right item-sub">0 ETB</span>
                <button type="button" onclick="this.parentElement.remove(); recalcEditTotal();" class="text-red-400 hover:text-red-600 p-1"><i class="fas fa-trash-alt text-xs"></i></button>
            `;
            container.appendChild(div);
        }

        function updateNewItemPrice(selectEl) {
            const row = selectEl.parentElement;
            const price = selectEl.options[selectEl.selectedIndex].getAttribute('data-price') || 0;
            row.querySelector('.item-price').value = price;
            recalcEditTotal();
        }

        function recalcEditTotal() {
            let total = 0;
            document.querySelectorAll('#editItemsContainer > div').forEach(row => {
                const qty = parseInt(row.querySelector('.item-qty').value) || 0;
                const price = parseFloat(row.querySelector('.item-price').value) || 0;
                const sub = qty * price;
                total += sub;
                row.querySelector('.item-sub').textContent = sub.toLocaleString() + ' ETB';
            });
            document.getElementById('editTotalDisplay').textContent = 'Total: ' + total.toLocaleString() + ' ETB';
        }

        async function saveEdit(event, orderId) {
            event.preventDefault();
            const btn = document.getElementById('editSaveBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            btn.disabled = true;

            const form = document.getElementById('editForm');
            const formData = new FormData(form);
            const data = { order_id: orderId, items: [] };

            data.client_name = formData.get('client_name');
            data.phone_number = formData.get('phone_number');
            data.building_name = formData.get('building_name');
            data.apartment_number = formData.get('apartment_number');
            data.delivery_date = formData.get('delivery_date');
            data.transaction_reference = formData.get('transaction_reference');

            let i = 0;
            while (formData.has(`items[${i}][product_id]`)) {
                if (parseInt(formData.get(`items[${i}][quantity]`)) > 0 && formData.get(`items[${i}][product_id]`)) {
                    data.items.push({
                        product_id: formData.get(`items[${i}][product_id]`),
                        unit_price: formData.get(`items[${i}][unit_price]`),
                        quantity: formData.get(`items[${i}][quantity]`)
                    });
                }
                i++;
            }

            try {
                const res = await fetch('orders.php?action=save_order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                
                if (result.success) {
                    showToast('Order updated successfully!', 'success');
                    document.getElementById(`total_${orderId}`).innerHTML = `${parseFloat(result.new_total).toLocaleString()} <span class="text-xs font-normal text-gray-500">ETB</span>`;
                    closeEditModal();
                } else {
                    showToast(result.message || 'Failed to save order', 'error');
                    btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Changes'; btn.disabled = false;
                }
            } catch (e) {
                showToast('Network error', 'error');
                btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Changes'; btn.disabled = false;
            }
        }

        function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); document.getElementById('editModal').classList.remove('flex'); }
    </script>
</body>
</html>