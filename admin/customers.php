<?php
require_once '../config.php';
requireAdminLogin();
requirePermission('customers');

// ============================================================
// HELPER: Send Telegram Notification to Customer
// ============================================================
function sendCustomerNotification($chatId, $message) {
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

// ============================================================
// AJAX HANDLERS
// ============================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    // --- Get Customer's Order History ---
    if ($action === 'get_customer_orders') {
        $phone = trim($_GET['phone'] ?? '');
        if (empty($phone)) { echo json_encode(['success' => false, 'message' => 'Phone required']); exit; }

        try {
            $digits = preg_replace('/[^0-9]/', '', $phone);
            if (strpos($digits, '251') === 0) $digits = substr($digits, 3);
            if (strpos($digits, '0') === 0) $digits = substr($digits, 1);

            $variants = array_values(array_filter(array_unique([
                $phone, $digits, '0' . $digits, '+251' . $digits, '251' . $digits
            ])));

            $placeholders = implode(',', array_fill(0, count($variants), '?'));
            $params = $variants;

            $sql = "
                SELECT po.order_number, po.status, po.total_amount, po.payment_method, po.delivery_date, po.created_at, 
                       loc.name as location_name
                FROM pre_orders po
                LEFT JOIN delivery_locations loc ON po.delivery_location_id = loc.id
                WHERE (po.phone_number IN ($placeholders)";

            if (!empty($digits)) {
                $sql .= " OR po.phone_number LIKE ?";
                $params[] = '%' . $digits;
            }
            $sql .= ") ORDER BY po.created_at DESC LIMIT 50";

            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'orders' => $orders]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]); }
        exit;
    }

    // --- Get Customer Details ---
    if ($action === 'get_customer_details') {
        $phone = trim($_GET['phone'] ?? '');
        if (empty($phone)) { echo json_encode(['success' => false, 'message' => 'Phone required']); exit; }

        try {
            $digits = preg_replace('/[^0-9]/', '', $phone);
            if (strpos($digits, '251') === 0) $digits = substr($digits, 3);
            if (strpos($digits, '0') === 0) $digits = substr($digits, 1);

            $variants = array_values(array_filter(array_unique([
                $phone, $digits, '0' . $digits, '+251' . $digits, '251' . $digits
            ])));

            $placeholders = implode(',', array_fill(0, count($variants), '?'));
            $params = $variants;

            $sql = "
                SELECT phone_number, MAX(client_name) as client_name, MAX(chat_id) as chat_id,
                       COUNT(*) as order_count, SUM(total_amount) as total_spent,
                       MIN(created_at) as first_order, MAX(created_at) as last_order
                FROM pre_orders
                WHERE (phone_number IN ($placeholders)";

            if (!empty($digits)) {
                $sql .= " OR phone_number LIKE ?";
                $params[] = '%' . $digits;
            }
            $sql .= ") GROUP BY phone_number ORDER BY order_count DESC LIMIT 1";

            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                echo json_encode(['success' => false, 'message' => 'Customer details not found for phone: ' . $phone]);
                exit;
            }

            // Calculate customer segment
            $segment = 'New';
            if ($customer['order_count'] > 5 || $customer['total_spent'] > 10000) {
                $segment = 'VIP';
            } elseif ($customer['order_count'] > 2 || $customer['total_spent'] > 1000) {
                $segment = 'Regular';
            }

            // Calculate days since last order
            $daysSinceLastOrder = 0;
            if (!empty($customer['last_order'])) {
                $daysSinceLastOrder = (new DateTime())->diff(new DateTime($customer['last_order']))->days;
            }

            // Get customer tags
            $tags = [];
            if ($daysSinceLastOrder > 30) $tags[] = 'Inactive';
            if ($customer['order_count'] > 10) $tags[] = 'Frequent Buyer';
            if ($customer['total_spent'] > 50000) $tags[] = 'High Value';

            echo json_encode([
                'success' => true,
                'customer' => $customer,
                'segment' => $segment,
                'tags' => $tags,
                'days_since_last_order' => $daysSinceLastOrder,
                'avg_order_value' => $customer['order_count'] > 0 ? $customer['total_spent'] / $customer['order_count'] : 0
            ]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]); }
        exit;
    }

    // --- Send Customer Message ---
    if ($action === 'send_message') {
        $input = json_decode(file_get_contents('php://input'), true);
        $phone = $input['phone'] ?? '';
        $message = $input['message'] ?? '';
        
        if (empty($phone) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Phone and message required']);
            exit;
        }
        
        try {
            $stmt = db()->prepare("SELECT chat_id FROM pre_orders WHERE phone_number = ? LIMIT 1");
            $stmt->execute([$phone]);
            $chatId = $stmt->fetchColumn();
            
            if ($chatId && sendCustomerNotification($chatId, $message)) {
                echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send message']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }

    // --- Export Customers CSV ---
    if ($action === 'export_csv') {
        $search = $_GET['search'] ?? '';
        $segment = $_GET['segment'] ?? '';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=kaldis_customers_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Phone', 'Segment', 'Orders', 'Total Spent (ETB)', 'Last Order', 'Days Since Last Order']);

        $query = "SELECT phone_number, MAX(client_name) as client_name, COUNT(*) as order_count, SUM(total_amount) as total_spent, MAX(created_at) as last_order FROM pre_orders WHERE 1=1";
        $params = [];
        if ($search) { 
            $query .= " AND (client_name LIKE ? OR phone_number LIKE ?)"; 
            $sp = "%$search%"; 
            $params = array_merge($params, [$sp, $sp]); 
        }
        $query .= " GROUP BY phone_number ORDER BY last_order DESC";
        
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $daysSince = (new DateTime())->diff(new DateTime($row['last_order']))->days;
            fputcsv($output, [$row['client_name'], $row['phone_number'], '', $row['order_count'], $row['total_spent'], $row['last_order'], $daysSince]);
        }
        fclose($output);
        exit;
    }

    exit;
}

// ============================================================
// PAGE LOAD
// ============================================================
 $page = $_GET['page'] ?? 1;
 $limit = 20;
 $offset = ($page - 1) * $limit;
 $search = $_GET['search'] ?? '';
 $segment = $_GET['segment'] ?? '';

try {
    // Main Query with Latest Status Subquery
    $query = "
        SELECT 
            phone_number,
            MAX(client_name) as client_name,
            MAX(chat_id) as chat_id,
            COUNT(*) as order_count,
            SUM(total_amount) as total_spent,
            MAX(created_at) as last_order,
            (SELECT status FROM pre_orders sub WHERE sub.phone_number = main.phone_number ORDER BY sub.created_at DESC LIMIT 1) as latest_status
        FROM pre_orders main
        WHERE 1=1
    ";
    $params = [];
    
    if ($search) {
        $query .= " AND (client_name LIKE ? OR phone_number LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam]);
    }
    
    $query .= " GROUP BY phone_number ORDER BY last_order DESC LIMIT $limit OFFSET $offset";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
    
    // Total Count
    $countQuery = "SELECT COUNT(DISTINCT phone_number) FROM pre_orders WHERE 1=1";
    $countParams = [];
    if ($search) {
        $countQuery .= " AND (client_name LIKE ? OR phone_number LIKE ?)";
        $searchParam = "%$search%";
        $countParams = array_merge($countParams, [$searchParam, $searchParam]);
    }
    $countStmt = db()->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalCustomers = $countStmt->fetchColumn();
    $totalPages = ceil($totalCustomers / $limit);

    // Stats
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT phone_number) as total_customers,
            SUM(total_amount) as total_revenue,
            COUNT(*) as total_orders,
            SUM(CASE WHEN cnt > 1 THEN 1 ELSE 0 END) as repeat_customers
        FROM (SELECT phone_number, COUNT(*) as cnt, SUM(total_amount) as total_amount FROM pre_orders GROUP BY phone_number) sub
    ";
    $stats = db()->query($statsQuery)->fetch();

} catch (Exception $e) { $error = "Failed to load customers"; }

function getStatusColor($status) {
    $map = ['Pending'=>'bg-yellow-100 text-yellow-800','Paid'=>'bg-cyan-100 text-cyan-800','Confirmed'=>'bg-blue-100 text-blue-800','Processing'=>'bg-purple-100 text-purple-800','Out for Delivery'=>'bg-orange-100 text-orange-800','Delivered'=>'bg-green-100 text-green-800','Cancelled'=>'bg-red-100 text-red-800','Rejected'=>'bg-red-100 text-red-800'];
    return $map[$status] ?? 'bg-gray-100 text-gray-800';
}

function getSegmentColor($segment) {
    $map = ['New'=>'bg-gray-100 text-gray-800','Regular'=>'bg-blue-100 text-blue-800','VIP'=>'bg-yellow-100 text-yellow-800'];
    return $map[$segment] ?? 'bg-gray-100 text-gray-800';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .toast { position: fixed; top: 20px; right: 20px; z-index: 9999; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .checkbox-custom { appearance: none; width: 1.25rem; height: 1.25rem; border: 2px solid #d1d5db; border-radius: 0.25rem; cursor: pointer; position: relative; }
        .checkbox-custom:checked { background-color: #10b981; border-color: #10b981; }
        .checkbox-custom:checked::after { content: '✓'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-weight: bold; }
        .tag { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .tag-inactive { background-color: #fee2e2; color: #991b1b; }
        .tag-frequent { background-color: #dbeafe; color: #1e40af; }
        .tag-high-value { background-color: #fef3c7; color: #92400e; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Customers</h1>
                    <p class="text-sm text-gray-500 mt-1">View customer history and analytics</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="openBulkActions()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-edit mr-2"></i>Bulk Actions
                    </button>
                    <a href="?action=export_csv&search=<?php echo urlencode($search); ?>&segment=<?php echo urlencode($segment); ?>" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                        <i class="fas fa-file-csv mr-2 text-green-600"></i>Export CSV
                    </a>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-users"></i></div>
                        <div><div class="text-sm text-gray-500">Total Customers</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_customers'] ?? 0); ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-coins"></i></div>
                        <div><div class="text-sm text-gray-500">Total Revenue</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_revenue'] ?? 0, 0); ?> <span class="text-xs font-normal text-gray-500">ETB</span></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-shopping-bag"></i></div>
                        <div><div class="text-sm text-gray-500">Total Orders</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_orders'] ?? 0); ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-redo"></i></div>
                        <div><div class="text-sm text-gray-500">Repeat Customers</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['repeat_customers'] ?? 0); ?></div></div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6 border border-gray-100">
                    <form method="GET" class="flex flex-wrap gap-3 items-center">
                        <div class="flex-1 min-w-[220px] relative">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" name="search" placeholder="Search by name or phone..." value="<?php echo htmlspecialchars($search); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                        </div>
                        <div>
                            <select name="segment" class="px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                                <option value="">All Segments</option>
                                <option value="New" <?php echo $segment === 'New' ? 'selected' : ''; ?>>New</option>
                                <option value="Regular" <?php echo $segment === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                                <option value="VIP" <?php echo $segment === 'VIP' ? 'selected' : ''; ?>>VIP</option>
                            </select>
                        </div>
                        <button type="submit" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700"><i class="fas fa-filter mr-2"></i>Filter</button>
                        <a href="customers.php" class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg hover:bg-gray-200"><i class="fas fa-sync-alt mr-2"></i>Reset</a>
                    </form>
                </div>
                
                <!-- Bulk Actions -->
                <div id="bulkActions" class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6 hidden">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" id="selectAll" class="checkbox-custom" onchange="toggleSelectAll()">
                            <label for="selectAll" class="text-sm font-medium text-gray-700">Select all <span id="selectedCount">0</span> customers</label>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="sendBulkMessages()" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700">
                                <i class="fas fa-paper-plane mr-2"></i>Send Messages
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
                                    <th class="px-4 py-3 text-center">
                                        <input type="checkbox" id="tableSelectAll" class="checkbox-custom" onchange="toggleTableSelectAll()">
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Customer</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Phone</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Segment</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Orders</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Lifetime Value</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Last Order</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($customers)): ?>
                                <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400"><i class="fas fa-users text-4xl mb-3 block"></i>No customers found.</td></tr>
                                <?php endif; ?>
                                
                                <?php foreach ($customers as $c): 
                                    $initials = strtoupper(substr($c['client_name'], 0, 1));
                                    $segment = 'New';
                                    if ($c['order_count'] > 5 || $c['total_spent'] > 10000) {
                                        $segment = 'VIP';
                                    } elseif ($c['order_count'] > 2 || $c['total_spent'] > 1000) {
                                        $segment = 'Regular';
                                    }
                                ?>
                                <tr class="hover:bg-gray-50 transition" id="row_<?php echo $c['phone_number']; ?>">
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" class="customer-checkbox checkbox-custom" data-phone="<?php echo htmlspecialchars($c['phone_number']); ?>" onchange="updateSelectedCount()">
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-xs font-bold"><?php echo $initials; ?></div>
                                            <div>
                                                <span class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($c['client_name']); ?></span>
                                                <div class="flex gap-1 mt-1">
                                                    <?php 
                                                    $tags = [];
                                                    $daysSince = (new DateTime())->diff(new DateTime($c['last_order']))->days;
                                                    if ($daysSince > 30) $tags[] = 'Inactive';
                                                    if ($c['order_count'] > 10) $tags[] = 'Frequent';
                                                    if ($c['total_spent'] > 50000) $tags[] = 'High Value';
                                                    
                                                    foreach ($tags as $tag): 
                                                        $tagClass = $tag === 'Inactive' ? 'tag-inactive' : 
                                                            ($tag === 'Frequent' ? 'tag-frequent' : 'tag-high-value');
                                                    ?>
                                                    <span class="tag <?php echo $tagClass; ?>"><?php echo $tag; ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-600"><?php echo htmlspecialchars($c['phone_number']); ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 text-[10px] rounded-full font-bold <?php echo getSegmentColor($segment); ?>">
                                            <?php echo $segment; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center justify-center min-w-[28px] h-7 rounded-full text-xs font-bold <?php echo $c['order_count'] > 1 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700'; ?>">
                                            <?php echo $c['order_count']; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold text-green-700"><?php echo number_format($c['total_spent'], 2); ?> <span class="text-[10px] font-normal text-gray-500">ETB</span></td>
                                    <td class="px-4 py-3">
                                        <div class="text-xs font-bold text-gray-800"><?php echo date('M d, Y', strtotime($c['last_order'])); ?></div>
                                        <div class="text-[10px] text-gray-400"><?php echo date('g:i A', strtotime($c['last_order'])); ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button onclick="viewCustomer('<?php echo htmlspecialchars($c['phone_number']); ?>', '<?php echo htmlspecialchars($c['client_name']); ?>')" class="text-green-600 hover:text-green-800 bg-green-50 p-1.5 rounded-lg" title="View Details"><i class="fas fa-eye text-sm"></i></button>
                                            <?php if (!empty($c['chat_id'])): ?>
                                            <a href="https://t.me/<?php echo htmlspecialchars($c['chat_id']); ?>" target="_blank" class="text-blue-500 hover:text-blue-700 bg-blue-50 p-1.5 rounded-lg" title="Telegram Chat"><i class="fab fa-telegram text-sm"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <div class="px-6 py-4 border-t flex justify-between items-center bg-gray-50">
                        <div class="text-sm text-gray-500">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalCustomers); ?> of <?php echo number_format($totalCustomers); ?></div>
                        <div class="flex gap-1">
                            <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&segment=<?php echo urlencode($segment); ?>" class="px-3 py-1 border rounded hover:bg-white text-sm">Prev</a><?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&segment=<?php echo urlencode($segment); ?>" class="px-3 py-1 border rounded text-sm <?php echo $i === $page ? 'bg-green-600 text-white border-green-600' : 'hover:bg-white'; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&segment=<?php echo urlencode($segment); ?>" class="px-3 py-1 border rounded hover:bg-white text-sm">Next</a><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Customer Details Modal -->
    <div id="customerModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
            <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-center z-10">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2" id="customerModalTitle">
                    <span class="w-8 h-8 bg-green-100 text-green-600 rounded-lg flex items-center justify-center"><i class="fas fa-user"></i></span>
                    <span>Customer Details</span>
                </h3>
                <button onclick="closeCustomerModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 w-8 h-8 rounded-lg flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
            <div id="customerContent" class="p-6 overflow-y-auto flex-1">
                <div class="flex justify-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div></div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div id="bulkActionsModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Bulk Actions</h3>
            <p class="text-sm text-gray-500 mb-4">Perform actions on <span id="bulkCustomerCount" class="font-bold">0</span> selected customers</p>
            
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-500 mb-2">Message Template</label>
                <textarea id="bulkMessage" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Enter your message..."></textarea>
            </div>

            <div class="flex gap-3">
                <button onclick="closeBulkActionsModal()" class="flex-1 px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-medium text-sm">Cancel</button>
                <button id="bulkConfirmBtn" onclick="confirmBulkMessages()" class="flex-1 px-4 py-2.5 bg-orange-600 text-white rounded-xl hover:bg-orange-700 font-medium text-sm transition-colors">Send Messages</button>
            </div>
        </div>
    </div>

    <script>
        let selectedCustomers = [];
        
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast p-4 rounded-lg shadow-lg border-l-4 ${type === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'} flex items-center gap-3`;
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span class="font-medium text-sm">${message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
        }
        
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.customer-checkbox');
            const selectAll = document.getElementById('selectAll');
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                cb.dispatchEvent(new Event('change'));
            });
        }
        
        function toggleTableSelectAll() {
            const checkboxes = document.querySelectorAll('.customer-checkbox');
            const selectAll = document.getElementById('tableSelectAll');
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                cb.dispatchEvent(new Event('change'));
            });
        }
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.customer-checkbox:checked');
            selectedCustomers = Array.from(checkboxes).map(cb => cb.dataset.phone);
            document.getElementById('selectedCount').textContent = selectedCustomers.length;
            document.getElementById('bulkCustomerCount').textContent = selectedCustomers.length;
            
            const bulkActions = document.getElementById('bulkActions');
            bulkActions.classList.toggle('hidden', selectedCustomers.length === 0);
        }
        
        function openBulkActions() {
            if (selectedCustomers.length === 0) {
                showToast('Please select customers first', 'error');
                return;
            }
            document.getElementById('bulkMessage').value = '';
            document.getElementById('bulkActionsModal').classList.remove('hidden');
            document.getElementById('bulkActionsModal').classList.add('flex');
        }
        
        function closeBulkActionsModal() {
            document.getElementById('bulkActionsModal').classList.add('hidden');
            document.getElementById('bulkActionsModal').classList.remove('flex');
        }
        
        function sendBulkMessages() {
            if (selectedCustomers.length === 0) {
                showToast('Please select customers first', 'error');
                return;
            }
            openBulkActions();
        }
        
        async function confirmBulkMessages() {
            const message = document.getElementById('bulkMessage').value;
            if (!message) {
                showToast('Please enter a message', 'error');
                return;
            }
            
            const btn = document.getElementById('bulkConfirmBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
            btn.disabled = true;
            
            let successCount = 0;
            for (const phone of selectedCustomers) {
                try {
                    const res = await fetch('customers.php?action=send_message', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ phone, message })
                    });
                    const data = await res.json();
                    if (data.success) successCount++;
                } catch (e) {
                    console.error('Error sending message:', e);
                }
            }
            
            closeBulkActionsModal();
            showToast(`Sent messages to ${successCount}/${selectedCustomers.length} customers`, successCount > 0 ? 'success' : 'error');
            
            // Reset selection
            document.querySelectorAll('.customer-checkbox').forEach(cb => cb.checked = false);
            updateSelectedCount();
        }
        
        function viewCustomer(phone, name) {
            const modal = document.getElementById('customerModal');
            const content = document.getElementById('customerContent');
            document.getElementById('customerModalTitle').innerHTML = `<span class="w-8 h-8 bg-green-100 text-green-600 rounded-lg flex items-center justify-center"><i class="fas fa-user"></i></span><span>${name}</span>`;
            
            modal.classList.remove('hidden'); modal.classList.add('flex');
            content.innerHTML = '<div class="flex justify-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div></div>';
            
            fetch(`customers.php?action=get_customer_details&phone=${encodeURIComponent(phone)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const c = data.customer;
                        const segmentColor = getSegmentColor(data.segment);
                        
                        let html = `
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div class="bg-gray-50 rounded-xl p-4">
                                <h4 class="text-sm font-semibold text-gray-500 mb-2">Customer Info</h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Name:</span>
                                        <span class="text-sm font-semibold text-gray-800">${c.client_name}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Phone:</span>
                                        <span class="text-sm font-mono text-gray-800">${c.phone_number}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Segment:</span>
                                        <span class="px-2 py-1 text-[10px] rounded-full font-bold ${segmentColor}">${data.segment}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 rounded-xl p-4">
                                <h4 class="text-sm font-semibold text-gray-500 mb-2">Order Analytics</h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Total Orders:</span>
                                        <span class="text-sm font-semibold text-gray-800">${c.order_count}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Total Spent:</span>
                                        <span class="text-sm font-semibold text-green-700">${parseFloat(c.total_spent).toLocaleString()} ETB</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Avg Order Value:</span>
                                        <span class="text-sm font-semibold text-gray-800">${parseFloat(data.avg_order_value).toLocaleString()} ETB</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 rounded-xl p-4">
                                <h4 class="text-sm font-semibold text-gray-500 mb-2">Activity</h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">First Order:</span>
                                        <span class="text-sm font-semibold text-gray-800">${new Date(c.first_order).toLocaleDateString()}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Last Order:</span>
                                        <span class="text-sm font-semibold text-gray-800">${new Date(c.last_order).toLocaleDateString()}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Days Since:</span>
                                        <span class="text-sm font-semibold ${data.days_since_last_order > 30 ? 'text-red-600' : 'text-gray-800'}">${data.days_since_last_order} days</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-500 mb-3">Customer Tags</h4>
                            <div class="flex flex-wrap gap-2">
                                ${data.tags.map(tag => {
                                    const tagClass = tag === 'Inactive' ? 'tag-inactive' : 
                                        (tag === 'Frequent Buyer' ? 'tag-frequent' : 'tag-high-value');
                                    return `<span class="tag ${tagClass}">${tag}</span>`;
                                }).join('')}
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-500 mb-3">Order History</h4>
                            <div class="space-y-3">`;
                        
                        // Get order history
                        fetch(`customers.php?action=get_customer_orders&phone=${encodeURIComponent(phone)}`)
                            .then(r => r.json())
                            .then(orderData => {
                                if (orderData.success && orderData.orders.length > 0) {
                                    orderData.orders.forEach(o => {
                                        const sc = getStatusColor(o.status);
                                        html += `
                                        <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 hover:bg-gray-100 transition">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <span class="font-mono text-sm font-bold text-green-700">${o.order_number}</span>
                                                    <div class="text-[10px] text-gray-500 mt-0.5">${new Date(o.created_at).toLocaleString()}</div>
                                                </div>
                                                <span class="px-2 py-1 text-[10px] rounded-full font-bold ${sc}">${o.status}</span>
                                            </div>
                                            <div class="flex items-center justify-between mt-2 text-xs text-gray-600">
                                                <span><i class="fas fa-map-marker-alt text-gray-400 mr-1"></i> ${o.location_name || 'N/A'}</span>
                                                <span><i class="fas fa-calendar text-gray-400 mr-1"></i> ${o.delivery_date}</span>
                                            </div>
                                            <div class="flex justify-between items-center mt-3 pt-2 border-t border-gray-200">
                                                <span class="text-xs text-gray-500">${o.payment_method || ''}</span>
                                                <span class="text-sm font-bold text-gray-900">${parseFloat(o.total_amount).toLocaleString()} ETB</span>
                                            </div>
                                        </div>`;
                                    });
                                } else {
                                    html += '<div class="text-center py-4 text-gray-400">No orders found</div>';
                                }
                                html += `</div></div>`;
                                content.innerHTML = html;
                            })
                            .catch(() => {
                                html += '<div class="text-center py-4 text-red-500">Failed to load orders</div></div>';
                                content.innerHTML = html;
                            });
                    } else {
                        content.innerHTML = '<div class="text-center py-8 text-red-500 font-medium">' + (data.message || 'Failed to load customer details') + '</div>';
                    }
                })
                .catch(err => { content.innerHTML = '<div class="text-center py-8 text-red-500 font-medium">Failed to load customer details: ' + (err.message || 'Network error') + '</div>'; });
        }
        
        function closeCustomerModal() { 
            document.getElementById('customerModal').classList.add('hidden'); 
            document.getElementById('customerModal').classList.remove('flex'); 
        }
        
        document.addEventListener('keydown', function(e) { 
            if (e.key === 'Escape') {
                closeCustomerModal();
                closeBulkActionsModal();
            }
        });
    </script>
</body>
</html>