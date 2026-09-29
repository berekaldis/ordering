<?php
require_once '../config.php';
requireAdminLogin();

 $admin = getCurrentAdmin();

// Get dashboard statistics
try {
    // Total orders today
    $stmt = db()->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM pre_orders WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayStats = $stmt->fetch();
    
    // Total orders this month
    $stmt = db()->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM pre_orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stmt->execute();
    $monthStats = $stmt->fetch();
    
    // Pending orders
    $stmt = db()->prepare("SELECT COUNT(*) as count FROM pre_orders WHERE status = 'Pending'");
    $stmt->execute();
    $pendingOrders = $stmt->fetchColumn();
    
    // Total customers
    $stmt = db()->prepare("SELECT COUNT(DISTINCT chat_id) as count FROM pre_orders WHERE chat_id IS NOT NULL");
    $stmt->execute();
    $totalCustomers = $stmt->fetchColumn();
    
    // Recent orders
    $stmt = db()->prepare("SELECT * FROM pre_orders ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $recentOrders = $stmt->fetchAll();
    
    // Top selling products
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
    $topProducts = $stmt->fetchAll();
    
    // Revenue trends for the last 7 days
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
    $revenueTrends = $stmt->fetchAll();
    
    // Order status distribution
    $stmt = db()->prepare("
        SELECT status, COUNT(*) as count
        FROM pre_orders
        GROUP BY status
    ");
    $stmt->execute();
    $orderStatus = $stmt->fetchAll();
    
    // Delivery location performance
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
    $locationPerformance = $stmt->fetchAll();
    
    // Customer growth (last 30 days)
    $stmt = db()->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(DISTINCT chat_id) as new_customers
        FROM pre_orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $customerGrowth = $stmt->fetchAll();
    
    // Get notifications
    $notifications = [];
    if ($pendingOrders > 10) {
        $notifications[] = [
            'type' => 'warning',
            'title' => 'High Pending Orders',
            'message' => "You have {$pendingOrders} pending orders that need attention",
            'time' => 'Just now'
        ];
    }
    
    // Get recent activity logs
    if (isSuperAdmin()) {
        $stmt = db()->prepare("
            SELECT action, target_type, created_at, user_name
            FROM activity_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute();
        $recentActivity = $stmt->fetchAll();
    } else {
        $recentActivity = [];
    }
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar-item {
            transition: all 0.2s ease;
        }
        .sidebar-item:hover {
            background-color: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
        }
        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .notification-item {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .quick-action-btn {
            transition: all 0.2s ease;
        }
        .quick-action-btn:hover {
            transform: scale(1.05);
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-confirmed {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-processing {
            background-color: #ede9fe;
            color: #5b21b6;
        }
        .status-delivered {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Header -->
            <div class="bg-white shadow-sm border-b">
                <div class="px-6 py-4">
                    <div class="flex justify-between items-center">
                        <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
                        <div class="flex items-center gap-4">
                            <div class="relative">
                                <button id="notificationBtn" class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none">
                                    <i class="fas fa-bell text-xl"></i>
                                    <?php if (!empty($notifications)): ?>
                                        <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full"></span>
                                    <?php endif; ?>
                                </button>
                                <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-50">
                                    <div class="p-4 border-b">
                                        <h3 class="font-semibold text-gray-800">Notifications</h3>
                                    </div>
                                    <div class="max-h-80 overflow-y-auto">
                                        <?php if (empty($notifications)): ?>
                                            <div class="p-4 text-center text-gray-500">No notifications</div>
                                        <?php else: ?>
                                            <?php foreach ($notifications as $notification): ?>
                                                <div class="p-4 border-b notification-item">
                                                    <div class="flex items-start">
                                                        <div class="mr-3">
                                                            <i class="fas fa-exclamation-circle text-yellow-500"></i>
                                                        </div>
                                                        <div class="flex-1">
                                                            <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                            <p class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($notification['time']); ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-3 bg-gray-50 text-center">
                                        <a href="notifications.php" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                                    </div>
                                </div>
                            </div>
                            <span class="text-sm text-gray-600">Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? $admin['username']); ?></span>
                            <a href="logout.php" class="text-red-600 hover:text-red-800">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Today's Orders -->
                    <div class="stat-card bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Today's Orders</p>
                                <p class="text-2xl font-bold"><?php echo number_format($todayStats['count'] ?? 0); ?></p>
                                <p class="text-green-600 text-sm"><?php echo number_format($todayStats['revenue'] ?? 0); ?> ETB</p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Monthly Revenue -->
                    <div class="stat-card bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Monthly Revenue</p>
                                <p class="text-2xl font-bold"><?php echo number_format($monthStats['revenue'] ?? 0); ?> ETB</p>
                                <p class="text-gray-500 text-sm"><?php echo number_format($monthStats['count'] ?? 0); ?> orders</p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-chart-line text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Orders -->
                    <div class="stat-card bg-white rounded-xl shadow-sm p-6 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Pending Orders</p>
                                <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($pendingOrders ?? 0); ?></p>
                                <p class="text-gray-500 text-sm">Need verification</p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Customers -->
                    <div class="stat-card bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Customers</p>
                                <p class="text-2xl font-bold"><?php echo number_format($totalCustomers ?? 0); ?></p>
                                <p class="text-gray-500 text-sm">Unique users</p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-users text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Revenue Trends Chart -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                            <h2 class="font-semibold text-gray-800">Revenue Trends (Last 7 Days)</h2>
                            <button onclick="exportRevenueReport()" class="text-sm text-blue-600 hover:text-blue-800">
                                <i class="fas fa-download mr-1"></i> Export
                            </button>
                        </div>
                        <div class="p-6">
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Growth Chart -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b bg-gray-50">
                            <h2 class="font-semibold text-gray-800">Customer Growth (Last 30 Days)</h2>
                        </div>
                        <div class="p-6">
                            <div class="chart-container">
                                <canvas id="customerChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Status & Location Performance -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Order Status Distribution -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b bg-gray-50">
                            <h2 class="font-semibold text-gray-800">Order Status Distribution</h2>
                        </div>
                        <div class="p-6">
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delivery Location Performance -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b bg-gray-50">
                            <h2 class="font-semibold text-gray-800">Delivery Location Performance</h2>
                        </div>
                        <div class="p-6">
                            <div class="chart-container">
                                <canvas id="locationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions & Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b bg-gray-50">
                            <h2 class="font-semibold text-gray-800">Quick Actions</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <a href="orders.php?status=Pending" class="quick-action-btn flex flex-col items-center justify-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100">
                                    <i class="fas fa-tasks text-blue-600 text-2xl mb-2"></i>
                                    <span class="text-sm font-medium text-gray-800">View Pending Orders</span>
                                </a>
                                <a href="products.php" class="quick-action-btn flex flex-col items-center justify-center p-4 bg-green-50 rounded-lg hover:bg-green-100">
                                    <i class="fas fa-box text-green-600 text-2xl mb-2"></i>
                                    <span class="text-sm font-medium text-gray-800">Manage Products</span>
                                </a>
                                <a href="customers.php" class="quick-action-btn flex flex-col items-center justify-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100">
                                    <i class="fas fa-user-friends text-purple-600 text-2xl mb-2"></i>
                                    <span class="text-sm font-medium text-gray-800">View Customers</span>
                                </a>
                                <a href="reports.php" class="quick-action-btn flex flex-col items-center justify-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100">
                                    <i class="fas fa-chart-bar text-yellow-600 text-2xl mb-2"></i>
                                    <span class="text-sm font-medium text-gray-800">View Reports</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Orders -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b bg-gray-50">
                            <h2 class="font-semibold text-gray-800">Recent Orders</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 text-xs text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Order #</th>
                                        <th class="px-4 py-3 text-left">Customer</th>
                                        <th class="px-4 py-3 text-left">Amount</th>
                                        <th class="px-4 py-3 text-left">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <?php foreach ($recentOrders as $order): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-mono"><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($order['client_name']); ?></td>
                                        <td class="px-4 py-3 text-sm"><?php echo number_format($order['total_amount']); ?> ETB</td>
                                        <td class="px-4 py-3">
                                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                                <?php echo $order['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="px-6 py-3 bg-gray-50 border-t">
                            <a href="orders.php" class="text-sm text-green-600 hover:text-green-800">View All Orders →</a>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b bg-gray-50">
                            <h2 class="font-semibold text-gray-800">Recent Activity</h2>
                        </div>
                        <div class="p-4 space-y-4 max-h-96 overflow-y-auto">
                            <?php if (empty($recentActivity)): ?>
                                <div class="text-center text-gray-500 py-4">No recent activity</div>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="flex items-start">
                                        <div class="mr-3 mt-1">
                                            <i class="fas fa-circle text-blue-500 text-xs"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm text-gray-800">
                                                <span class="font-medium"><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></span> 
                                                <?php echo htmlspecialchars($activity['action']); ?> 
                                                <?php if (!empty($activity['target_type'])): ?>
                                                    <span class="text-gray-600"><?php echo htmlspecialchars($activity['target_type']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                            <p class="text-xs text-gray-500"><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (isSuperAdmin()): ?>
                                <div class="mt-4 pt-4 border-t">
                                    <a href="activity_logs.php" class="text-sm text-blue-600 hover:text-blue-800">View All Activity Logs →</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Top Selling Products -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                        <h2 class="font-semibold text-gray-800">Top Selling Products (30 days)</h2>
                        <button onclick="exportProductReport()" class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-download mr-1"></i> Export
                        </button>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <?php foreach ($topProducts as $index => $product): ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 font-bold shrink-0">
                                            <i class="fas fa-mug-hot text-base"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($product['product_name']); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo number_format($product['total_sold']); ?> units sold</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-32 bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, $index * 20 + 20); ?>%"></div>
                                        </div>
                                        <span class="text-sm font-medium text-gray-700"><?php echo $index + 1; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Notification dropdown toggle
        document.getElementById('notificationBtn').addEventListener('click', function() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('hidden');
        });

        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            if (!notificationBtn.contains(event.target) && !notificationDropdown.contains(event.target)) {
                notificationDropdown.classList.add('hidden');
            }
        });

        // Revenue Trends Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($revenueTrends, 'date')); ?>,
                datasets: [{
                    label: 'Revenue (ETB)',
                    data: <?php echo json_encode(array_column($revenueTrends, 'revenue')); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: ' + context.parsed.y + ' ETB';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' ETB';
                            }
                        }
                    }
                }
            }
        });

        // Customer Growth Chart
        const customerCtx = document.getElementById('customerChart').getContext('2d');
        const customerChart = new Chart(customerCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($customerGrowth, 'date')); ?>,
                datasets: [{
                    label: 'New Customers',
                    data: <?php echo json_encode(array_column($customerGrowth, 'new_customers')); ?>,
                    backgroundColor: 'rgba(139, 92, 246, 0.8)',
                    borderColor: 'rgb(139, 92, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Order Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($orderStatus, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($orderStatus, 'count')); ?>,
                    backgroundColor: [
                        'rgba(251, 191, 36, 0.8)',  // Pending - Yellow
                        'rgba(59, 130, 246, 0.8)',  // Confirmed - Blue
                        'rgba(139, 92, 246, 0.8)',  // Processing - Purple
                        'rgba(16, 185, 129, 0.8)',  // Delivered - Green
                        'rgba(239, 68, 68, 0.8)'    // Cancelled - Red
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // Location Performance Chart
        const locationCtx = document.getElementById('locationChart').getContext('2d');
        const locationChart = new Chart(locationCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($locationPerformance, 'location_name')); ?>,
                datasets: [{
                    label: 'Orders',
                    data: <?php echo json_encode(array_column($locationPerformance, 'order_count')); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Revenue (ETB)',
                    data: <?php echo json_encode(array_column($locationPerformance, 'revenue')); ?>,
                    type: 'line',
                    borderColor: 'rgb(245, 158, 11)',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Orders'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Revenue (ETB)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Export functions
        function exportRevenueReport() {
            window.location.href = 'api.php?action=export_revenue_report';
        }

        function exportProductReport() {
            window.location.href = 'api.php?action=export_product_report';
        }
    </script>
</body>
</html>