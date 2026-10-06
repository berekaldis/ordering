<?php
require_once '../config.php';
requireAdminLogin();

// Date range filter
 $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
 $dateTo = $_GET['date_to'] ?? date('Y-m-d');

try {
    // Get sales analytics
    $salesQuery = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            SUM(total_amount) as revenue,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ";
    $salesStmt = db()->prepare($salesQuery);
    $salesStmt->execute([$dateFrom, $dateTo]);
    $salesData = $salesStmt->fetchAll();
    
    // Get product analytics
    $productQuery = "
        SELECT 
            p.name as product_name,
            COUNT(oi.quantity) as total_quantity,
            SUM(oi.quantity * oi.unit_price) as total_revenue,
            COUNT(DISTINCT o.id) as orders_count
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY p.id, p.name
        ORDER BY total_revenue DESC
        LIMIT 10
    ";
    $productStmt = db()->prepare($productQuery);
    $productStmt->execute([$dateFrom, $dateTo]);
    $productData = $productStmt->fetchAll();
    
    // Get customer analytics
    $customerQuery = "
        SELECT 
            c.username,
            COUNT(o.id) as total_orders,
            SUM(o.total_amount) as total_spent,
            COUNT(DISTINCT DATE(o.created_at)) as active_days
        FROM customers c
        JOIN orders o ON c.id = o.customer_id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY c.id, c.username
        ORDER BY total_spent DESC
        LIMIT 10
    ";
    $customerStmt = db()->prepare($customerQuery);
    $customerStmt->execute([$dateFrom, $dateTo]);
    $customerData = $customerStmt->fetchAll();
    
    // Get daily summary
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ";
    $summaryStmt = db()->prepare($summaryQuery);
    $summaryStmt->execute([$dateFrom, $dateTo]);
    $summary = $summaryStmt->fetch();
    
    // Get monthly comparison
    $monthlyQuery = "
        SELECT 
            MONTH(created_at) as month,
            YEAR(created_at) as year,
            COUNT(*) as orders,
            SUM(total_amount) as revenue
        FROM orders 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY year, month
    ";
    $monthlyStmt = db()->prepare($monthlyQuery);
    $monthlyStmt->execute();
    $monthlyData = $monthlyStmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Failed to load analytics: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Kaldis Coffee ECA Admin</title>
    <link rel="icon" type="image/png" href="../uploads/logo/kaldis-logo.png">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="../uploads/logo/kaldis-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-800">Analytics Dashboard</h1>
                <p class="text-sm text-gray-500 mt-1">Comprehensive sales and business analytics</p>
            </div>
            
            <div class="p-6">
                <!-- Date Range Filter -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <form method="GET" class="flex flex-wrap gap-4 items-center">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"
                                   class="px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"
                                   class="px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                                <i class="fas fa-search mr-2"></i>Apply Filter
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="stat-card bg-white rounded-xl shadow-sm p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-xs text-green-600 font-medium">+12%</span>
                        </div>
                        <p class="text-2xl font-bold text-gray-800"><?php echo number_format($summary['total_orders'] ?? 0); ?></p>
                        <p class="text-sm text-gray-500">Total Orders</p>
                    </div>
                    
                    <div class="stat-card bg-white rounded-xl shadow-sm p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                            </div>
                            <span class="text-xs text-green-600 font-medium">+8%</span>
                        </div>
                        <p class="text-2xl font-bold text-gray-800">ETB <?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></p>
                        <p class="text-sm text-gray-500">Total Revenue</p>
                    </div>
                    
                    <div class="stat-card bg-white rounded-xl shadow-sm p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-xs text-green-600 font-medium">+5%</span>
                        </div>
                        <p class="text-2xl font-bold text-gray-800"><?php echo number_format($summary['delivered_orders'] ?? 0); ?></p>
                        <p class="text-sm text-gray-500">Delivered Orders</p>
                    </div>
                    
                    <div class="stat-card bg-white rounded-xl shadow-sm p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-orange-600 text-xl"></i>
                            </div>
                            <span class="text-xs text-red-600 font-medium">+3%</span>
                        </div>
                        <p class="text-2xl font-bold text-gray-800"><?php echo number_format($summary['pending_orders'] ?? 0); ?></p>
                        <p class="text-sm text-gray-500">Pending Orders</p>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Sales Trend Chart -->
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Sales Trend</h3>
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Monthly Comparison Chart -->
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Comparison</h3>
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Top Products and Customers -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Products -->
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Products</h3>
                        <div class="space-y-3">
                            <?php foreach ($productData as $product): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-box text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($product['product_name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $product['orders_count']; ?> orders</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-gray-800">ETB <?php echo number_format($product['total_revenue'], 2); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $product['total_quantity']; ?> units</p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Top Customers -->
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Customers</h3>
                        <div class="space-y-3">
                            <?php foreach ($customerData as $customer): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-user text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($customer['username']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $customer['active_days']; ?> active days</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-gray-800">ETB <?php echo number_format($customer['total_spent'], 2); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $customer['total_orders']; ?> orders</p>
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
        // Sales Trend Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($salesData, 'date')); ?>,
                datasets: [{
                    label: 'Orders',
                    data: <?php echo json_encode(array_column($salesData, 'orders')); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($salesData, 'revenue')); ?>,
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.4,
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
                        title: {
                            display: true,
                            text: 'Orders'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenue (ETB)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });
        
        // Monthly Comparison Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function($m) { return date('M Y', strtotime($m['year'] . '-' . $m['month'] . '-01')); }, $monthlyData)); ?>,
                datasets: [{
                    label: 'Orders',
                    data: <?php echo json_encode(array_column($monthlyData, 'orders')); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)'
                }, {
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($monthlyData, 'revenue')); ?>,
                    backgroundColor: 'rgba(34, 197, 94, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>