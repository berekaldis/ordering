<?php
require_once '../config.php';
requireAdminLogin();
requirePermission('reports');

 $reportType = $_GET['type'] ?? 'daily';
 $startDate = $_GET['start_date'] ?? date('Y-m-01');
 $endDate = $_GET['end_date'] ?? date('Y-m-d');
 $exportFormat = $_GET['export'] ?? null;

// Handle CSV export
if ($exportFormat === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sales_report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    
    // Generate report data
    if ($reportType === 'daily') {
        fputcsv($output, ['Date', 'Orders', 'Total Revenue', 'Avg Order', 'Pending', 'Confirmed', 'Completed', 'Cancelled']);
        $stmt = db()->prepare("
            SELECT DATE(created_at) as sale_date, COUNT(*) as order_count, SUM(total_amount) as total_revenue,
                   AVG(total_amount) as avg_order_value,
                   SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                   SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
                   SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                   SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM pre_orders WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY sale_date DESC");
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['sale_date'],
                $row['order_count'],
                $row['total_revenue'],
                $row['avg_order_value'],
                $row['pending'],
                $row['confirmed'],
                $row['completed'],
                $row['cancelled']
            ]);
        }
    } elseif ($reportType === 'product') {
        fputcsv($output, ['Product', 'Category', 'Quantity Sold', 'Orders', 'Revenue']);
        $stmt = db()->prepare("
            SELECT dp.product_name, dp.category, SUM(poi.quantity) as total_quantity, 
                   COUNT(DISTINCT po.id) as order_count, SUM(poi.subtotal) as total_revenue
            FROM pre_order_items poi
            JOIN dairy_products dp ON poi.pre_order_product_id = dp.id
            JOIN pre_orders po ON poi.pre_order_id = po.id
            WHERE DATE(po.created_at) BETWEEN ? AND ?
            GROUP BY poi.pre_order_product_id
            ORDER BY total_revenue DESC");
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['product_name'],
                $row['category'],
                $row['total_quantity'],
                $row['order_count'],
                $row['total_revenue']
            ]);
        }
    } elseif ($reportType === 'branch') {
        fputcsv($output, ['Branch', 'Orders', 'Revenue', 'Avg Order', 'Unique Customers']);
        $stmt = db()->prepare("
            SELECT COALESCE(b.name, 'Unknown') as branch_name, COUNT(po.id) as order_count,
                   SUM(po.total_amount) as total_revenue, AVG(po.total_amount) as avg_order,
                   COUNT(DISTINCT po.chat_id) as unique_customers
            FROM pre_orders po
            LEFT JOIN branches b ON po.collection_branch_id = b.id
            WHERE DATE(po.created_at) BETWEEN ? AND ?
            GROUP BY po.collection_branch_id
            ORDER BY total_revenue DESC");
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['branch_name'],
                $row['order_count'],
                $row['total_revenue'],
                $row['avg_order'],
                $row['unique_customers']
            ]);
        }
    }
    fclose($output);
    exit;
}

try {
    // Daily Sales Report
    if ($reportType === 'daily') {
        $stmt = db()->prepare("
            SELECT 
                DATE(created_at) as sale_date,
                COUNT(*) as order_count,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order_value,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN payment_method = 'Cash' THEN 1 ELSE 0 END) as cash_payments,
                SUM(CASE WHEN payment_method = 'Card' THEN 1 ELSE 0 END) as card_payments,
                SUM(CASE WHEN payment_method = 'Mobile' THEN 1 ELSE 0 END) as mobile_payments
            FROM pre_orders
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY sale_date DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $dailyReports = $stmt->fetchAll();
        
        // Summary totals
        $summary = [
            'total_orders' => array_sum(array_column($dailyReports, 'order_count')),
            'total_revenue' => array_sum(array_column($dailyReports, 'total_revenue')),
            'avg_order' => array_sum(array_column($dailyReports, 'avg_order_value')) / (count($dailyReports) ?: 1),
            'completed_rate' => array_sum(array_column($dailyReports, 'completed')) / (array_sum(array_column($dailyReports, 'order_count')) ?: 1) * 100,
            'cash_payments' => array_sum(array_column($dailyReports, 'cash_payments')),
            'card_payments' => array_sum(array_column($dailyReports, 'card_payments')),
            'mobile_payments' => array_sum(array_column($dailyReports, 'mobile_payments'))
        ];
        
        // Prepare chart data
        $chartData = [];
        foreach ($dailyReports as $report) {
            $chartData[] = [
                'date' => $report['sale_date'],
                'revenue' => $report['total_revenue'],
                'orders' => $report['order_count'],
                'completed' => $report['completed']
            ];
        }
        
    } elseif ($reportType === 'product') {
        // Product Sales Report
        $stmt = db()->prepare("
            SELECT 
                dp.product_name,
                dp.category,
                dp.unit,
                SUM(poi.quantity) as total_quantity,
                SUM(poi.subtotal) as total_revenue,
                COUNT(DISTINCT po.id) as order_count,
                SUM(CASE WHEN po.status = 'Completed' THEN 1 ELSE 0 END) as completed_orders,
                AVG(poi.unit_price) as avg_price
            FROM pre_order_items poi
            JOIN dairy_products dp ON poi.pre_order_product_id = dp.id
            JOIN pre_orders po ON poi.pre_order_id = po.id
            WHERE DATE(po.created_at) BETWEEN ? AND ?
            GROUP BY poi.pre_order_product_id
            ORDER BY total_revenue DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $productReports = $stmt->fetchAll();
        
        // Top performing products
        $topProducts = array_slice($productReports, 0, 5);
        
        // Category performance
        $categoryStmt = db()->prepare("
            SELECT dp.category, SUM(poi.quantity) as total_quantity, SUM(poi.subtotal) as total_revenue
            FROM pre_order_items poi
            JOIN dairy_products dp ON poi.pre_order_product_id = dp.id
            JOIN pre_orders po ON poi.pre_order_id = po.id
            WHERE DATE(po.created_at) BETWEEN ? AND ?
            GROUP BY dp.category
            ORDER BY total_revenue DESC
        ");
        $categoryStmt->execute([$startDate, $endDate]);
        $categoryReports = $categoryStmt->fetchAll();
        
    } elseif ($reportType === 'branch') {
        // Branch Performance Report
        $stmt = db()->prepare("
            SELECT 
                COALESCE(b.name, 'Unknown') as branch_name,
                COUNT(po.id) as order_count,
                SUM(po.total_amount) as total_revenue,
                AVG(po.total_amount) as avg_order,
                COUNT(DISTINCT po.chat_id) as unique_customers,
                SUM(CASE WHEN po.status = 'Completed' THEN 1 ELSE 0 END) as completed_orders,
                AVG(CASE WHEN po.status = 'Completed' THEN 1 ELSE 0 END) * 100 as completion_rate
            FROM pre_orders po
            LEFT JOIN branches b ON po.collection_branch_id = b.id
            WHERE DATE(po.created_at) BETWEEN ? AND ?
            GROUP BY po.collection_branch_id
            ORDER BY total_revenue DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $branchReports = $stmt->fetchAll();
        
        // Branch growth comparison
        $growthStmt = db()->prepare("
            SELECT 
                COALESCE(b.name, 'Unknown') as branch_name,
                COUNT(CASE WHEN DATE(created_at) BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND DATE_SUB(?, INTERVAL 1 DAY) THEN 1 END) as prev_orders,
                COUNT(CASE WHEN DATE(created_at) BETWEEN ? AND ? THEN 1 END) as current_orders,
                SUM(CASE WHEN DATE(created_at) BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND DATE_SUB(?, INTERVAL 1 DAY) THEN total_amount END) as prev_revenue,
                SUM(CASE WHEN DATE(created_at) BETWEEN ? AND ? THEN total_amount END) as current_revenue
            FROM pre_orders po
            LEFT JOIN branches b ON po.collection_branch_id = b.id
            WHERE DATE(created_at) BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND ?
            GROUP BY po.collection_branch_id
        ");
        $growthStmt->execute([$startDate, $startDate, $startDate, $endDate, $startDate, $startDate, $startDate, $endDate, $startDate, $endDate]);
        $branchGrowth = $growthStmt->fetchAll();
        
    }
    
} catch (Exception $e) {
    $error = "Failed to generate report: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); }
        .chart-container { position: relative; height: 300px; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Sales Reports</h1>
                        <p class="text-sm text-gray-500 mt-1">Comprehensive sales analytics and insights</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button onclick="exportReport()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-medium">
                            <i class="fas fa-download mr-2"></i>Export CSV
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Report Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <form method="GET" class="flex flex-wrap gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                            <select name="type" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                                <option value="daily" <?php echo $reportType === 'daily' ? 'selected' : ''; ?>>Daily Sales</option>
                                <option value="product" <?php echo $reportType === 'product' ? 'selected' : ''; ?>>Product Performance</option>
                                <option value="branch" <?php echo $reportType === 'branch' ? 'selected' : ''; ?>>Branch Analysis</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo $startDate; ?>"
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" value="<?php echo $endDate; ?>"
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                        </div>
                        <div>
                            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                                <i class="fas fa-chart-line mr-2"></i>Generate Report
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4 rounded">
                        <p class="text-red-700"><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Daily Sales Report -->
                <?php if ($reportType === 'daily' && isset($dailyReports)): ?>
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="stat-card bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Total Orders</p>
                                    <p class="text-2xl font-bold text-gray-800"><?php echo number_format($summary['total_orders']); ?></p>
                                    <p class="text-xs text-green-600 mt-1">
                                        <i class="fas fa-arrow-up mr-1"></i><?php echo number_format(($summary['total_orders'] / 30), 0); ?>/day
                                    </p>
                                </div>
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Total Revenue</p>
                                    <p class="text-2xl font-bold text-gray-800"><?php echo number_format($summary['total_revenue'], 2); ?> ETB</p>
                                    <p class="text-xs text-green-600 mt-1">
                                        <i class="fas fa-arrow-up mr-1"></i><?php echo number_format(($summary['total_revenue'] / 30), 2); ?>/day
                                    </p>
                                </div>
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-coins text-green-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Avg Order Value</p>
                                    <p class="text-2xl font-bold text-gray-800"><?php echo number_format($summary['avg_order'], 2); ?> ETB</p>
                                    <p class="text-xs text-gray-500 mt-1">Per order</p>
                                </div>
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Completion Rate</p>
                                    <p class="text-2xl font-bold text-gray-800"><?php echo number_format($summary['completed_rate'], 1); ?>%</p>
                                    <p class="text-xs text-gray-500 mt-1">Of total orders</p>
                                </div>
                                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-check-circle text-yellow-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Methods Summary -->
                    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Methods Distribution</h3>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center">
                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-money-bill-wave text-green-600 text-2xl"></i>
                                </div>
                                <p class="text-sm text-gray-500">Cash</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo $summary['cash_payments']; ?></p>
                            </div>
                            <div class="text-center">
                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-credit-card text-blue-600 text-2xl"></i>
                                </div>
                                <p class="text-sm text-gray-500">Card</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo $summary['card_payments']; ?></p>
                            </div>
                            <div class="text-center">
                                <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-mobile-alt text-purple-600 text-2xl"></i>
                                </div>
                                <p class="text-sm text-gray-500">Mobile</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo $summary['mobile_payments']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-4">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Revenue Trend</h3>
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-4">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Orders Trend</h3>
                            <div class="chart-container">
                                <canvas id="ordersChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Daily Sales Table -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b">
                            <h3 class="text-lg font-semibold text-gray-800">Daily Sales Details</h3>
                        </div>
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Orders</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Order</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($dailyReports as $report): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm"><?php echo date('M d, Y', strtotime($report['sale_date'])); ?></td>
                                    <td class="px-6 py-4 text-sm font-medium"><?php echo $report['order_count']; ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-green-600"><?php echo number_format($report['total_revenue'], 2); ?> ETB</td>
                                    <td class="px-6 py-4 text-sm"><?php echo number_format($report['avg_order_value'], 2); ?> ETB</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full font-bold bg-green-100 text-green-800">
                                            <?php echo number_format(($report['completed'] / $report['order_count'] * 100), 0); ?>% Complete
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <!-- Product Sales Report -->
                <?php if ($reportType === 'product' && isset($productReports)): ?>
                    <!-- Top Products -->
                    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Performing Products</h3>
                        <div class="space-y-3">
                            <?php foreach ($topProducts as $product): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-box text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($product['product_name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo ucfirst($product['category']); ?> ? <?php echo $product['unit']; ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-green-600"><?php echo number_format($product['total_revenue'], 2); ?> ETB</p>
                                    <p class="text-xs text-gray-500"><?php echo $product['total_quantity']; ?> units</p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Category Performance -->
                    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Category Performance</h3>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Product Sales Table -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b">
                            <h3 class="text-lg font-semibold text-gray-800">Product Sales Details</h3>
                        </div>
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Orders</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($productReports as $report): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium"><?php echo htmlspecialchars($report['product_name']); ?></td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php 
                                            $icons = ['milk' => '🥛', 'cheese' => '🧀', 'yogurt' => '🥄', 'butter' => '🧈', 'cream' => '🥛'];
                                            echo ($icons[$report['category']] ?? '📦') . ' ' . ucfirst($report['category']);
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium"><?php echo number_format($report['total_quantity']); ?> units</td>
                                    <td class="px-6 py-4 text-sm"><?php echo number_format($report['avg_price'], 2); ?> ETB</td>
                                    <td class="px-6 py-4 text-sm"><?php echo $report['order_count']; ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-green-600"><?php echo number_format($report['total_revenue'], 2); ?> ETB</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <!-- Branch Performance Report -->
                <?php if ($reportType === 'branch' && isset($branchReports)): ?>
                    <!-- Branch Performance Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <?php foreach ($branchReports as $branch): ?>
                        <div class="bg-white rounded-xl shadow-sm p-4">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($branch['branch_name']); ?></h3>
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-store text-blue-600 text-xl"></i>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">Orders</span>
                                    <span class="font-medium text-gray-800"><?php echo $branch['order_count']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">Revenue</span>
                                    <span class="font-medium text-green-600"><?php echo number_format($branch['total_revenue'], 2); ?> ETB</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">Avg Order</span>
                                    <span class="font-medium text-gray-800"><?php echo number_format($branch['avg_order'], 2); ?> ETB</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">Unique Customers</span>
                                    <span class="font-medium text-gray-800"><?php echo $branch['unique_customers']; ?></span>
                                </div>
                                <div class="pt-2 border-t">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500">Completion Rate</span>
                                        <span class="px-2 py-1 text-xs rounded-full font-bold bg-green-100 text-green-800">
                                            <?php echo number_format($branch['completion_rate'], 0); ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Branch Growth Chart -->
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Branch Growth Comparison</h3>
                        <div class="chart-container">
                            <canvas id="branchGrowthChart"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = `reports.php?${params.toString()}`;
        }
        
        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Daily Sales Charts
            if (document.getElementById('revenueChart')) {
                const ctx = document.getElementById('revenueChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_column($chartData, 'date')); ?>,
                        datasets: [{
                            label: 'Revenue (ETB)',
                            data: <?php echo json_encode(array_column($chartData, 'revenue')); ?>,
                            borderColor: 'rgb(34, 197, 94)',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
            
            if (document.getElementById('ordersChart')) {
                const ctx = document.getElementById('ordersChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($chartData, 'date')); ?>,
                        datasets: [{
                            label: 'Orders',
                            data: <?php echo json_encode(array_column($chartData, 'orders')); ?>,
                            backgroundColor: 'rgba(59, 130, 246, 0.8)'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
            
            // Product Category Chart
            if (document.getElementById('categoryChart')) {
                const ctx = document.getElementById('categoryChart').getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_column($categoryReports, 'category')); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_column($categoryReports, 'total_revenue')); ?>,
                            backgroundColor: [
                                'rgba(34, 197, 94, 0.8)',
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(168, 85, 247, 0.8)',
                                'rgba(251, 146, 60, 0.8)',
                                'rgba(236, 72, 153, 0.8)'
                            ]
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
            }
            
            // Branch Growth Chart
            if (document.getElementById('branchGrowthChart')) {
                const ctx = document.getElementById('branchGrowthChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($branchGrowth, 'branch_name')); ?>,
                        datasets: [{
                            label: 'Previous Period',
                            data: <?php echo json_encode(array_column($branchGrowth, 'prev_orders')); ?>,
                            backgroundColor: 'rgba(156, 163, 175, 0.8)'
                        }, {
                            label: 'Current Period',
                            data: <?php echo json_encode(array_column($branchGrowth, 'current_orders')); ?>,
                            backgroundColor: 'rgba(34, 197, 94, 0.8)'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>