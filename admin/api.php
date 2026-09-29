<?php
require_once '../config.php';
requireAdminLogin();

header('Content-Type: application/json');

// Log API access
logActivity('API_ACCESS', [
    'action' => $_GET['action'] ?? 'unknown',
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
], 'API', $_SESSION['admin_id']);

 $action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // === ORDER MANAGEMENT ===
        case 'get_order':
            $orderId = $_GET['id'] ?? '';
            if (!$orderId) {
                throw new Exception('Order ID is required');
            }
            
            $stmt = db()->prepare("
                SELECT po.*, dl.name as delivery_location, 
                       CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                       c.phone as customer_phone
                FROM pre_orders po
                LEFT JOIN delivery_locations dl ON po.delivery_location_id = dl.id
                LEFT JOIN customers c ON po.chat_id = c.id
                WHERE po.id = ?
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if (!$order) {
                throw new Exception('Order not found');
            }
            
            // Get order items
            $itemsStmt = db()->prepare("
                SELECT poi.*, dp.product_name, dp.category
                FROM pre_order_items poi
                JOIN dairy_products dp ON poi.pre_order_product_id = dp.id
                WHERE poi.pre_order_id = ?
            ");
            $itemsStmt->execute([$orderId]);
            $order['items'] = $itemsStmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $order]);
            break;
            
        case 'get_customer_orders':
            $customerId = $_GET['customer_id'] ?? '';
            if (!$customerId) {
                throw new Exception('Customer ID is required');
            }
            
            $stmt = db()->prepare("
                SELECT po.*, dl.name as delivery_location
                FROM pre_orders po
                LEFT JOIN delivery_locations dl ON po.delivery_location_id = dl.id
                WHERE po.chat_id = ?
                ORDER BY po.created_at DESC
            ");
            $stmt->execute([$customerId]);
            $orders = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $orders]);
            break;
            
        // === FEEDBACK MANAGEMENT ===
        case 'get_feedback':
            $feedbackId = $_GET['id'] ?? '';
            if (!$feedbackId) {
                throw new Exception('Feedback ID is required');
            }
            
            $stmt = db()->prepare("
                SELECT f.*, b.name as branch_name
                FROM feedback f
                LEFT JOIN branches b ON f.branch_id = b.id
                WHERE f.id = ?
            ");
            $stmt->execute([$feedbackId]);
            $feedback = $stmt->fetch();
            
            if (!$feedback) {
                throw new Exception('Feedback not found');
            }
            
            echo json_encode(['success' => true, 'data' => $feedback]);
            break;
            
        case 'get_feedback_list':
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $offset = ($page - 1) * $limit;
            
            $branchFilter = $_GET['branch'] ?? '';
            $ratingFilter = $_GET['rating'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';
            $searchTerm = $_GET['search'] ?? '';
            $chatIdFilter = $_GET['chat_id'] ?? '';
            $complaintStatus = $_GET['complaint_status'] ?? '';
            
            $query = "
                SELECT f.*, b.name as branch_name
                FROM feedback f
                LEFT JOIN branches b ON f.branch_id = b.id
                WHERE 1=1
            ";
            $params = [];
            
            if ($branchFilter) {
                $query .= " AND f.branch_id = ?";
                $params[] = $branchFilter;
            }
            
            if ($ratingFilter) {
                $query .= " AND (f.delivery_rating = ? OR f.product_rating = ? OR f.service_rating = ?)";
                $params = array_merge($params, [$ratingFilter, $ratingFilter, $ratingFilter]);
            }
            
            if ($dateFrom) {
                $query .= " AND DATE(f.created_at) >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $query .= " AND DATE(f.created_at) <= ?";
                $params[] = $dateTo;
            }
            
            if ($chatIdFilter) {
                $query .= " AND f.chat_id = ?";
                $params[] = $chatIdFilter;
            }
            
            if ($searchTerm) {
                $query .= " AND (f.written_feedback LIKE ? OR f.complaint_description LIKE ?)";
                $searchParam = "%$searchTerm%";
                $params = array_merge($params, [$searchParam, $searchParam]);
            }
            
            if ($complaintStatus) {
                $query .= " AND f.complaint_status = ?";
                $params[] = $complaintStatus;
            }
            
            $query .= " ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = db()->prepare($query);
            $stmt->execute($params);
            $feedbacks = $stmt->fetchAll();
            
            // Get total count
            $countQuery = "SELECT COUNT(*) FROM feedback f WHERE 1=1";
            $countParams = [];
            
            if ($branchFilter) {
                $countQuery .= " AND f.branch_id = ?";
                $countParams[] = $branchFilter;
            }
            
            if ($ratingFilter) {
                $countQuery .= " AND (f.delivery_rating = ? OR f.product_rating = ? OR f.service_rating = ?)";
                $countParams = array_merge($countParams, [$ratingFilter, $ratingFilter, $ratingFilter]);
            }
            
            if ($dateFrom) {
                $countQuery .= " AND DATE(f.created_at) >= ?";
                $countParams[] = $dateFrom;
            }
            
            if ($dateTo) {
                $countQuery .= " AND DATE(f.created_at) <= ?";
                $countParams[] = $dateTo;
            }
            
            if ($chatIdFilter) {
                $countQuery .= " AND f.chat_id = ?";
                $countParams[] = $chatIdFilter;
            }
            
            if ($searchTerm) {
                $countQuery .= " AND (f.written_feedback LIKE ? OR f.complaint_description LIKE ?)";
                $searchParam = "%$searchTerm%";
                $countParams = array_merge($countParams, [$searchParam, $searchParam]);
            }
            
            if ($complaintStatus) {
                $countQuery .= " AND f.complaint_status = ?";
                $countParams[] = $complaintStatus;
            }
            
            $countStmt = db()->prepare($countQuery);
            $countStmt->execute($countParams);
            $totalFeedbacks = $countStmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => $feedbacks,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalFeedbacks,
                    'pages' => ceil($totalFeedbacks / $limit)
                ]
            ]);
            break;
            
        case 'update_complaint_status':
            $feedbackId = $_POST['id'] ?? '';
            $status = $_POST['status'] ?? '';
            
            if (!$feedbackId || !$status) {
                throw new Exception('Feedback ID and status are required');
            }
            
            // Validate status
            $validStatuses = ['open', 'in_progress', 'resolved', 'closed'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Invalid complaint status');
            }
            
            $stmt = db()->prepare("
                UPDATE feedback 
                SET complaint_status = ?, updated_at = NOW()
                WHERE id = ? AND complaint_type IS NOT NULL
            ");
            $stmt->execute([$status, $feedbackId]);
            
            logActivity('UPDATE_COMPLAINT_STATUS', [
                'feedback_id' => $feedbackId,
                'status' => $status
            ], 'FEEDBACK', $_SESSION['admin_id']);
            
            echo json_encode(['success' => true, 'message' => 'Complaint status updated']);
            break;
            
        case 'export_feedback':
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            $branchFilter = $_GET['branch'] ?? '';
            $ratingFilter = $_GET['rating'] ?? '';
            $complaintType = $_GET['complaint_type'] ?? '';
            
            $query = "
                SELECT 
                    f.id,
                    f.chat_id,
                    f.created_at,
                    f.written_feedback,
                    f.complaint_type,
                    f.complaint_description,
                    f.complaint_status,
                    f.delivery_rating,
                    f.product_rating,
                    f.service_rating,
                    b.name as branch_name
                FROM feedback f
                LEFT JOIN branches b ON f.branch_id = b.id
                WHERE DATE(f.created_at) BETWEEN ? AND ?
            ";
            $params = [$dateFrom, $dateTo];
            
            if ($branchFilter) {
                $query .= " AND f.branch_id = ?";
                $params[] = $branchFilter;
            }
            
            if ($ratingFilter) {
                $query .= " AND (f.delivery_rating = ? OR f.product_rating = ? OR f.service_rating = ?)";
                $params = array_merge($params, [$ratingFilter, $ratingFilter, $ratingFilter]);
            }
            
            if ($complaintType) {
                $query .= " AND f.complaint_type = ?";
                $params[] = $complaintType;
            }
            
            $query .= " ORDER BY f.created_at DESC";
            
            $stmt = db()->prepare($query);
            $stmt->execute($params);
            $feedbacks = $stmt->fetchAll();
            
            $filename = "feedback_export_" . date('Y-m-d_H-i-s') . ".csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'ID', 
                'Chat ID', 
                'Date', 
                'Branch', 
                'Delivery Rating', 
                'Product Rating', 
                'Service Rating', 
                'Written Feedback', 
                'Complaint Type', 
                'Complaint Description', 
                'Complaint Status'
            ]);
            
            foreach ($feedbacks as $feedback) {
                fputcsv($output, [
                    $feedback['id'],
                    $feedback['chat_id'],
                    $feedback['created_at'],
                    $feedback['branch_name'] ?? 'N/A',
                    $feedback['delivery_rating'] ?? '',
                    $feedback['product_rating'] ?? '',
                    $feedback['service_rating'] ?? '',
                    $feedback['written_feedback'] ?? '',
                    $feedback['complaint_type'] ?? '',
                    $feedback['complaint_description'] ?? '',
                    $feedback['complaint_status'] ?? ''
                ]);
            }
            fclose($output);
            exit;
            
        case 'get_feedback_stats':
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            // Overall ratings
            $stmt = db()->prepare("
                SELECT 
                    COUNT(*) as total_feedback,
                    COUNT(DISTINCT chat_id) as unique_customers,
                    AVG(delivery_rating) as avg_delivery,
                    AVG(product_rating) as avg_product,
                    AVG(service_rating) as avg_service,
                    COUNT(CASE WHEN delivery_rating <= 2 OR product_rating <= 2 OR service_rating <= 2 THEN 1 END) as negative_reviews
                FROM feedback
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $ratings = $stmt->fetch();
            
            // Complaint stats
            $stmt = db()->prepare("
                SELECT 
                    COUNT(*) as total_complaints,
                    COUNT(CASE WHEN complaint_status = 'open' THEN 1 END) as open_complaints,
                    COUNT(CASE WHEN complaint_status = 'in_progress' THEN 1 END) as in_progress,
                    COUNT(CASE WHEN complaint_status = 'resolved' THEN 1 END) as resolved,
                    COUNT(CASE WHEN complaint_status = 'closed' THEN 1 END) as closed
                FROM feedback
                WHERE complaint_type IS NOT NULL
                AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $complaints = $stmt->fetch();
            
            // Rating distribution
            $stmt = db()->prepare("
                SELECT 
                    rating,
                    COUNT(*) as count
                FROM (
                    SELECT delivery_rating as rating FROM feedback WHERE delivery_rating IS NOT NULL
                    UNION ALL
                    SELECT product_rating as rating FROM feedback WHERE product_rating IS NOT NULL
                    UNION ALL
                    SELECT service_rating as rating FROM feedback WHERE service_rating IS NOT NULL
                ) as ratings
                WHERE rating IS NOT NULL
                GROUP BY rating
                ORDER BY rating DESC
            ");
            $stmt->execute();
            $distribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Complaint types
            $stmt = db()->prepare("
                SELECT 
                    complaint_type,
                    COUNT(*) as count
                FROM feedback
                WHERE complaint_type IS NOT NULL
                AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY complaint_type
                ORDER BY count DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $complaintTypes = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'ratings' => $ratings,
                    'complaints' => $complaints,
                    'distribution' => $distribution,
                    'complaint_types' => $complaintTypes
                ]
            ]);
            break;
            
        // === EXPORTS ===
        case 'export_logs':
            if (!isSuperAdmin()) {
                throw new Exception('Unauthorized access');
            }
            
            $actionFilter = $_GET['action'] ?? '';
            $userIdFilter = $_GET['user_id'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';
            $limit = $_GET['limit'] ?? 10000;
            
            $query = "SELECT * FROM activity_logs WHERE 1=1";
            $params = [];
            
            if ($actionFilter) {
                $query .= " AND action LIKE ?";
                $params[] = "%$actionFilter%";
            }
            
            if ($userIdFilter) {
                $query .= " AND user_id = ?";
                $params[] = $userIdFilter;
            }
            
            if ($dateFrom) {
                $query .= " AND DATE(created_at) >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $query .= " AND DATE(created_at) <= ?";
                $params[] = $dateTo;
            }
            
            $query .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = (int)$limit;
            
            $stmt = db()->prepare($query);
            $stmt->execute($params);
            $logs = $stmt->fetchAll();
            
            $filename = "activity_logs_" . date('Y-m-d_H-i-s') . ".csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'User ID', 'Username', 'Action', 'Target Type', 'Target ID', 'IP Address', 'Details']);
            
            foreach ($logs as $log) {
                $userName = 'System';
                if ($log['user_id']) {
                    try {
                        $userStmt = db()->prepare("SELECT username FROM admin_users WHERE id = ?");
                        $userStmt->execute([$log['user_id']]);
                        $userName = $userStmt->fetchColumn() ?: 'System';
                    } catch (Exception $e) {
                        $userName = 'System';
                    }
                }
                
                fputcsv($output, [
                    $log['created_at'],
                    $log['user_id'] ?? '',
                    $userName,
                    $log['action'],
                    $log['target_type'] ?? '',
                    $log['target_id'] ?? '',
                    $log['ip_address'] ?? '',
                    $log['details'] ?? ''
                ]);
            }
            fclose($output);
            exit;
            
        case 'export_revenue_report':
            if (!isSuperAdmin()) {
                throw new Exception('Unauthorized access');
            }
            
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $query = "
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as revenue,
                    COUNT(DISTINCT chat_id) as unique_customers,
                    COUNT(CASE WHEN status = 'Delivered' THEN 1 END) as delivered_orders,
                    COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN status = 'Cancelled' THEN 1 END) as cancelled_orders
                FROM pre_orders 
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ";
            
            $stmt = db()->prepare($query);
            $stmt->execute([$dateFrom, $dateTo]);
            $data = $stmt->fetchAll();
            
            $filename = "revenue_report_" . $dateFrom . "_to_" . $dateTo . ".csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Order Count', 'Revenue (ETB)', 'Unique Customers', 'Delivered', 'Pending', 'Cancelled']);
            
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['date'],
                    $row['order_count'],
                    $row['revenue'],
                    $row['unique_customers'],
                    $row['delivered_orders'],
                    $row['pending_orders'],
                    $row['cancelled_orders']
                ]);
            }
            fclose($output);
            exit;
            
        case 'export_product_report':
            if (!isSuperAdmin()) {
                throw new Exception('Unauthorized access');
            }
            
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $query = "
                SELECT 
                    dp.product_name,
                    dp.category,
                    COUNT(poi.id) as total_items,
                    SUM(poi.quantity) as total_quantity,
                    COALESCE(SUM(poi.subtotal), 0) as total_revenue,
                    AVG(poi.unit_price) as avg_price,
                    COUNT(DISTINCT poi.pre_order_id) as unique_orders
                FROM pre_order_items poi
                JOIN dairy_products dp ON poi.pre_order_product_id = dp.id
                JOIN pre_orders po ON poi.pre_order_id = po.id
                WHERE DATE(po.created_at) BETWEEN ? AND ?
                GROUP BY dp.id
                ORDER BY total_quantity DESC
            ";
            
            $stmt = db()->prepare($query);
            $stmt->execute([$dateFrom, $dateTo]);
            $data = $stmt->fetchAll();
            
            $filename = "product_report_" . $dateFrom . "_to_" . $dateTo . ".csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Product Name', 'Category', 'Total Items', 'Total Quantity', 'Total Revenue (ETB)', 'Avg Price (ETB)', 'Unique Orders']);
            
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['product_name'],
                    $row['category'],
                    $row['total_items'],
                    $row['total_quantity'],
                    $row['total_revenue'],
                    $row['avg_price'],
                    $row['unique_orders']
                ]);
            }
            fclose($output);
            exit;
            
        case 'export_customer_report':
            if (!isSuperAdmin()) {
                throw new Exception('Unauthorized access');
            }
            
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $query = "
                SELECT 
                    c.id as customer_id,
                    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                    c.phone,
                    c.address,
                    COUNT(po.id) as total_orders,
                    COALESCE(SUM(po.total_amount), 0) as total_spent,
                    COUNT(DISTINCT DATE(po.created_at)) as active_days,
                    MIN(po.created_at) as first_order_date,
                    MAX(po.created_at) as last_order_date
                FROM customers c
                LEFT JOIN pre_orders po ON c.id = po.chat_id
                WHERE DATE(po.created_at) BETWEEN ? AND ?
                GROUP BY c.id
                ORDER BY total_spent DESC
            ";
            
            $stmt = db()->prepare($query);
            $stmt->execute([$dateFrom, $dateTo]);
            $data = $stmt->fetchAll();
            
            $filename = "customer_report_" . $dateFrom . "_to_" . $dateTo . ".csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Customer ID', 'Name', 'Phone', 'Address', 'Total Orders', 'Total Spent (ETB)', 'Active Days', 'First Order', 'Last Order']);
            
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['customer_id'],
                    $row['customer_name'],
                    $row['phone'],
                    $row['address'],
                    $row['total_orders'],
                    $row['total_spent'],
                    $row['active_days'],
                    $row['first_order_date'],
                    $row['last_order_date']
                ]);
            }
            fclose($output);
            exit;
            
        // === DASHBOARD DATA ===
        case 'get_dashboard_data':
            $data = [];
            
            // Today's orders
            $stmt = db()->prepare("
                SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue 
                FROM pre_orders 
                WHERE DATE(created_at) = CURDATE()
            ");
            $stmt->execute();
            $data['today'] = $stmt->fetch();
            
            // Monthly revenue
            $stmt = db()->prepare("
                SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue 
                FROM pre_orders 
                WHERE MONTH(created_at) = MONTH(CURDATE()) 
                AND YEAR(created_at) = YEAR(CURDATE())
            ");
            $stmt->execute();
            $data['monthly'] = $stmt->fetch();
            
            // Pending orders
            $stmt = db()->prepare("
                SELECT COUNT(*) as count 
                FROM pre_orders 
                WHERE status = 'Pending'
            ");
            $stmt->execute();
            $data['pending'] = $stmt->fetchColumn();
            
            // Total customers
            $stmt = db()->prepare("
                SELECT COUNT(DISTINCT chat_id) as count 
                FROM pre_orders 
                WHERE chat_id IS NOT NULL
            ");
            $stmt->execute();
            $data['customers'] = $stmt->fetchColumn();
            
            // Revenue trends (last 7 days)
            $stmt = db()->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as orders,
                    COALESCE(SUM(total_amount), 0) as revenue
                FROM pre_orders 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute();
            $data['revenue_trends'] = $stmt->fetchAll();
            
            // Order status distribution
            $stmt = db()->prepare("
                SELECT status, COUNT(*) as count 
                FROM pre_orders
                GROUP BY status
            ");
            $stmt->execute();
            $data['order_status'] = $stmt->fetchAll();
            
            // Location performance
            $stmt = db()->prepare("
                SELECT 
                    dl.name as location_name,
                    COUNT(*) as order_count,
                    COALESCE(SUM(po.total_amount), 0) as revenue
                FROM pre_orders po
                JOIN delivery_locations dl ON po.delivery_location_id = dl.id
                GROUP BY dl.name
                ORDER BY order_count DESC
            ");
            $stmt->execute();
            $data['location_performance'] = $stmt->fetchAll();
            
            // Top products (last 30 days)
            $stmt = db()->prepare("
                SELECT dp.product_name, dp.category, SUM(poi.quantity) as total_sold
                FROM pre_order_items poi
                JOIN dairy_products dp ON poi.pre_order_product_id = dp.id
                JOIN pre_orders po ON poi.pre_order_id = po.id
                WHERE po.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY poi.pre_order_product_id
                ORDER BY total_sold DESC
                LIMIT 5
            ");
            $stmt->execute();
            $data['top_products'] = $stmt->fetchAll();
            
            // Recent orders
            $stmt = db()->prepare("
                SELECT po.*, dl.name as location_name
                FROM pre_orders po
                LEFT JOIN delivery_locations dl ON po.delivery_location_id = dl.id
                ORDER BY po.created_at DESC
                LIMIT 10
            ");
            $stmt->execute();
            $data['recent_orders'] = $stmt->fetchAll();
            
            // Recent activity (super admin only)
            if (isSuperAdmin()) {
                $stmt = db()->prepare("
                    SELECT al.*, au.username as user_name
                    FROM activity_logs al
                    LEFT JOIN admin_users au ON al.user_id = au.id
                    WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    ORDER BY al.created_at DESC
                    LIMIT 5
                ");
                $stmt->execute();
                $data['recent_activity'] = $stmt->fetchAll();
                
                // Feedback statistics
                $stmt = db()->prepare("
                    SELECT 
                        COUNT(*) as total_feedback,
                        COUNT(DISTINCT chat_id) as unique_customers,
                        COUNT(CASE WHEN complaint_type IS NOT NULL THEN 1 END) as total_complaints,
                        COUNT(CASE WHEN complaint_status = 'open' THEN 1 END) as open_complaints
                    FROM feedback
                    WHERE DATE(created_at) = CURDATE()
                ");
                $stmt->execute();
                $data['feedback_today'] = $stmt->fetch();
                
                $stmt = db()->prepare("
                    SELECT 
                        AVG(delivery_rating) as avg_delivery,
                        AVG(product_rating) as avg_product,
                        AVG(service_rating) as avg_service
                    FROM feedback
                    WHERE DATE(created_at) = CURDATE()
                ");
                $stmt->execute();
                $data['ratings_today'] = $stmt->fetch();
            } else {
                $data['recent_activity'] = [];
                $data['feedback_today'] = null;
                $data['ratings_today'] = null;
            }
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        // === USER MANAGEMENT ===
        case 'get_users':
            if (!isSuperAdmin()) {
                throw new Exception('Unauthorized access');
            }
            
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $offset = ($page - 1) * $limit;
            
            $search = $_GET['search'] ?? '';
            
            $query = "SELECT * FROM admin_users WHERE 1=1";
            $params = [];
            
            if ($search) {
                $query .= " AND (username LIKE ? OR email LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = db()->prepare($query);
            $stmt->execute($params);
            $users = $stmt->fetchAll();
            
            // Get total count
            $countQuery = "SELECT COUNT(*) FROM admin_users WHERE 1=1";
            $countParams = [];
            if ($search) {
                $countQuery .= " AND (username LIKE ? OR email LIKE ?)";
                $countParams[] = "%$search%";
                $countParams[] = "%$search%";
            }
            $countStmt = db()->prepare($countQuery);
            $countStmt->execute($countParams);
            $totalUsers = $countStmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => $users,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalUsers,
                    'pages' => ceil($totalUsers / $limit)
                ]
            ]);
            break;
            
        case 'create_user':
            if (!isSuperAdmin()) {
                throw new Exception('Unauthorized access');
            }
            
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'admin';
            
            if (!$username || !$email || !$password) {
                throw new Exception('All fields are required');
            }
            
            // Check if user exists
            $stmt = db()->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                throw new Exception('Username or email already exists');
            }
            
            // Create user
            $stmt = db()->prepare("
                INSERT INTO admin_users (username, email, password, role, status, created_at)
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $role
            ]);
            
            logActivity('CREATE_USER', [
                'user_id' => $stmt->lastInsertId(),
                'username' => $username,
                'email' => $email,
                'role' => $role
            ], 'ADMIN', $_SESSION['admin_id']);
            
            echo json_encode(['success' => true, 'message' => 'User created successfully']);
            break;
            
        case 'update_user':
            if (!isSuperAdmin()) {
                throw new Exception('Unauthorized access');
            }
            
            $userId = $_POST['id'] ?? '';
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $role = $_POST['role'] ?? 'admin';
            $status = $_POST['status'] ?? 1;
            
            if (!$userId || !$username || !$email) {
                throw new Exception('Required fields missing');
            }
            
            // Update user
            $stmt = db()->prepare("
                UPDATE admin_users 
                SET username = ?, email = ?, role = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $email, $role, $status, $userId]);
            
            logActivity('UPDATE_USER', [
                'user_id' => $userId,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'status' => $status
            ], 'ADMIN', $_SESSION['admin_id']);
            
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            break;
            
        case 'delete_user':
            if (!isSuperAdmin()) {
                throw new Exception('Unauthorized access');
            }
            
            $userId = $_POST['id'] ?? '';
            if (!$userId) {
                throw new Exception('User ID is required');
            }
            
            // Prevent deleting own account
            if ($userId == $_SESSION['admin_id']) {
                throw new Exception('You cannot delete your own account');
            }
            
            $stmt = db()->prepare("DELETE FROM admin_users WHERE id = ?");
            $stmt->execute([$userId]);
            
            logActivity('DELETE_USER', [
                'user_id' => $userId
            ], 'ADMIN', $_SESSION['admin_id']);
            
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            break;
            
        // === SETTINGS MANAGEMENT ===
        case 'get_settings':
            $stmt = db()->prepare("SELECT * FROM settings");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            echo json_encode(['success' => true, 'data' => $settings]);
            break;
            
        case 'update_settings':
            if (!isSuperAdmin()) {
                throw new Exception('Unauthorized access');
            }
            
            $settings = $_POST['settings'] ?? [];
            if (!is_array($settings)) {
                throw new Exception('Invalid settings format');
            }
            
            foreach ($settings as $key => $value) {
                $stmt = db()->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            logActivity('UPDATE_SETTINGS', [
                'settings' => $settings
            ], 'ADMIN', $_SESSION['admin_id']);
            
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
            break;
            
        // === ERROR HANDLING ===
        default:
            throw new Exception('Invalid action specified');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
    
    // Log errors
    logActivity('API_ERROR', [
        'action' => $action,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 'API', $_SESSION['admin_id']);
}
?>