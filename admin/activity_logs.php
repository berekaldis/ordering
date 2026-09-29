<?php
require_once '../config.php';
requireAdminLogin();

// Only super admin can view logs
if (!isSuperAdmin()) {
    header('Location: index.php');
    exit;
}

 $page = $_GET['page'] ?? 1;
 $limit = 50;
 $offset = ($page - 1) * $limit;
 $action = $_GET['action'] ?? '';
 $userId = $_GET['user_id'] ?? '';
 $dateFrom = $_GET['date_from'] ?? '';
 $dateTo = $_GET['date_to'] ?? '';
 $exportFormat = $_GET['export'] ?? '';

// Handle CSV export
if ($exportFormat === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=activity_logs_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    
    // Get all logs with filters
    $query = "SELECT al.*, au.username, au.full_name FROM activity_logs al LEFT JOIN admin_users au ON al.user_id = au.id WHERE 1=1";
    $params = [];
    
    if ($action) { $query .= " AND al.action LIKE ?"; $params[] = "%$action%"; }
    if ($userId) { $query .= " AND al.user_id = ?"; $params[] = $userId; }
    if ($dateFrom) { $query .= " AND DATE(al.created_at) >= ?"; $params[] = $dateFrom; }
    if ($dateTo) { $query .= " AND DATE(al.created_at) <= ?"; $params[] = $dateTo; }
    
    $query .= " ORDER BY al.created_at DESC";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    
    // CSV headers
    fputcsv($output, ['Timestamp', 'User', 'Action', 'Target Type', 'Target ID', 'IP Address', 'Details']);
    
    // CSV data
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['created_at'],
            $row['full_name'] ?: $row['username'] ?: 'System',
            $row['action'],
            $row['target_type'] ?: '',
            $row['target_id'] ?: '',
            $row['ip_address'] ?: '',
            $row['details'] ?: ''
        ]);
    }
    
    fclose($output);
    exit;
}

try {
    $query = "SELECT al.*, au.username, au.full_name FROM activity_logs al LEFT JOIN admin_users au ON al.user_id = au.id WHERE 1=1";
    $params = [];
    
    if ($action) { $query .= " AND al.action LIKE ?"; $params[] = "%$action%"; }
    if ($userId) { $query .= " AND al.user_id = ?"; $params[] = $userId; }
    if ($dateFrom) { $query .= " AND DATE(al.created_at) >= ?"; $params[] = $dateFrom; }
    if ($dateTo) { $query .= " AND DATE(al.created_at) <= ?"; $params[] = $dateTo; }
    
    $query .= " ORDER BY al.created_at DESC LIMIT $limit OFFSET $offset";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Count query for pagination
    $countQuery = "SELECT COUNT(*) FROM activity_logs WHERE 1=1";
    $countParams = [];
    if ($action) { $countQuery .= " AND action LIKE ?"; $countParams[] = "%$action%"; }
    if ($userId) { $countQuery .= " AND user_id = ?"; $countParams[] = $userId; }
    if ($dateFrom) { $countQuery .= " AND DATE(created_at) >= ?"; $countParams[] = $dateFrom; }
    if ($dateTo) { $countQuery .= " AND DATE(created_at) <= ?"; $countParams[] = $dateTo; }
    $countStmt = db()->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalLogs = $countStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $limit);
    
    // Get unique actions for filter
    $actions = db()->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll();
    
    // Performance Optimization: Fetch all relevant users in ONE query (Fixes N+1 problem)
    $users = db()->query("SELECT id, username, full_name FROM admin_users ORDER BY username")->fetchAll();
    $usersMap = [];
    foreach ($users as $u) { $usersMap[$u['id']] = $u['full_name'] ?: $u['username']; }
    
    // Get statistics
    $statsQuery = "SELECT 
        COUNT(*) as total_logs, 
        COUNT(DISTINCT DATE(created_at)) as active_days, 
        MAX(created_at) as last_activity, 
        MIN(created_at) as first_activity,
        COUNT(DISTINCT user_id) as active_users,
        (SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()) as today_logs
    FROM activity_logs";
    $stats = db()->query($statsQuery)->fetch();
    
    // Get action statistics for the last 7 days
    $actionStatsQuery = "
        SELECT action, COUNT(*) as count 
        FROM activity_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY action 
        ORDER BY count DESC 
        LIMIT 10
    ";
    $actionStats = db()->query($actionStatsQuery)->fetchAll();
    
    // Get daily activity chart data
    $chartQuery = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
        FROM activity_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ";
    $chartData = db()->query($chartQuery)->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) { 
    $error = "Failed to load logs: " . $e->getMessage(); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .toast { position: fixed; top: 20px; right: 20px; z-index: 9999; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .code-font { font-family: 'JetBrains Mono', monospace; }
        .chart-container { position: relative; height: 300px; }
        .action-badge {
            transition: all 0.2s ease;
        }
        .action-badge:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Activity Logs</h1>
                    <p class="text-sm text-gray-500 mt-1">Track all system activities and user actions</p>
                </div>
                <div class="flex gap-2">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </a>
                    <button onclick="exportLogs()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-medium">
                        <i class="fas fa-download mr-2"></i>Export CSV
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Statistics Dashboard -->
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-history"></i></div>
                        <div><div class="text-sm text-gray-500">Total Logs</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_logs'] ?? 0); ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-calendar-alt"></i></div>
                        <div><div class="text-sm text-gray-500">Active Days</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['active_days'] ?? 0); ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-users"></i></div>
                        <div><div class="text-sm text-gray-500">Active Users</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['active_users'] ?? 0); ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-clock"></i></div>
                        <div><div class="text-sm text-gray-500">Last Activity</div><div class="text-sm font-bold text-gray-800"><?php echo $stats['last_activity'] ? date('M d, g:i A', strtotime($stats['last_activity'])) : 'N/A'; ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-red-50 text-red-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-flag-checkered"></i></div>
                        <div><div class="text-sm text-gray-500">First Activity</div><div class="text-sm font-bold text-gray-800"><?php echo $stats['first_activity'] ? date('M d, Y', strtotime($stats['first_activity'])) : 'N/A'; ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-chart-line"></i></div>
                        <div><div class="text-sm text-gray-500">Today's Logs</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['today_logs'] ?? 0); ?></div></div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Daily Activity (30 Days)</h3>
                        <div class="chart-container">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Actions (Last 7 Days)</h3>
                        <div class="chart-container">
                            <canvas id="actionsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Actions -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Actions</h3>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($actionStats as $action): ?>
                            <?php
                                $badgeColor = 'gray';
                                if (strpos($action['action'], 'LOGIN') !== false) $badgeColor = 'blue';
                                elseif (strpos($action['action'], 'DELETE') !== false || strpos($action['action'], 'CANCEL') !== false) $badgeColor = 'red';
                                elseif (strpos($action['action'], 'ADD') !== false || strpos($action['action'], 'CREATE') !== false) $badgeColor = 'green';
                                elseif (strpos($action['action'], 'UPDATE') !== false || strpos($action['action'], 'EDIT') !== false) $badgeColor = 'yellow';
                                elseif (strpos($action['action'], 'EXPORT') !== false) $badgeColor = 'purple';
                                
                                $badgeClasses = [
                                    'blue' => 'bg-blue-100 text-blue-800 border-blue-200',
                                    'red' => 'bg-red-100 text-red-800 border-red-200',
                                    'green' => 'bg-green-100 text-green-800 border-green-200',
                                    'yellow' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                    'purple' => 'bg-purple-100 text-purple-800 border-purple-200',
                                    'gray' => 'bg-gray-100 text-gray-800 border-gray-200'
                                ];
                            ?>
                            <span class="action-badge px-3 py-1.5 text-sm rounded-full font-semibold border <?php echo $badgeClasses[$badgeColor]; ?>">
                                <?php echo htmlspecialchars($action['action']); ?> <span class="text-xs opacity-70">(<?php echo $action['count']; ?>)</span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6 border border-gray-100">
                    <form method="GET" class="flex flex-wrap gap-3 items-center">
                        <select name="action" class="px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $act): ?>
                            <option value="<?php echo htmlspecialchars($act['action']); ?>" <?php echo $action === $act['action'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($act['action']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="user_id" class="px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $userId == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['full_name'] ?: $u['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-medium">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="logs.php" class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg hover:bg-gray-200 text-sm font-medium">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </form>
                </div>
                
                <!-- Table -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Timestamp</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">User</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Action</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Target</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">IP Address</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                                        <i class="fas fa-inbox text-4xl mb-3 block"></i>
                                        <p>No activity logs found matching your filters.</p>
                                        <?php if ($_GET): ?>
                                            <p class="text-sm mt-2">Try adjusting your filters or clear them to see all logs.</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                    <tr class="hover:bg-gray-50 transition cursor-pointer" onclick="showDetails(<?php echo json_encode($log, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-xs font-bold text-gray-800"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></div>
                                            <div class="text-[10px] text-gray-400 code-font"><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-[10px] font-bold">
                                                    <?php echo strtoupper(substr($usersMap[$log['user_id']] ?? 'S', 0, 1)); ?>
                                                </div>
                                                <span class="text-xs font-semibold text-gray-800"><?php echo htmlspecialchars($usersMap[$log['user_id']] ?? 'System'); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php
                                                $badgeColor = 'gray';
                                                if (strpos($log['action'], 'LOGIN') !== false) $badgeColor = 'blue';
                                                elseif (strpos($log['action'], 'DELETE') !== false || strpos($log['action'], 'CANCEL') !== false) $badgeColor = 'red';
                                                elseif (strpos($log['action'], 'ADD') !== false || strpos($log['action'], 'CREATE') !== false) $badgeColor = 'green';
                                                elseif (strpos($log['action'], 'UPDATE') !== false || strpos($log['action'], 'EDIT') !== false) $badgeColor = 'yellow';
                                                elseif (strpos($log['action'], 'EXPORT') !== false) $badgeColor = 'purple';
                                                
                                                $badgeClasses = [
                                                    'blue' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                    'red' => 'bg-red-100 text-red-800 border-red-200',
                                                    'green' => 'bg-green-100 text-green-800 border-green-200',
                                                    'yellow' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                    'purple' => 'bg-purple-100 text-purple-800 border-purple-200',
                                                    'gray' => 'bg-gray-100 text-gray-800 border-gray-200'
                                                ];
                                            ?>
                                            <span class="action-badge px-2 py-1 text-[10px] rounded-full font-semibold border <?php echo $badgeClasses[$badgeColor]; ?>">
                                                <?php echo htmlspecialchars($log['action']); ?>
                                            </span>
                                         </td>
                                        <td class="px-4 py-3 text-xs text-gray-600 code-font">
                                            <?php if ($log['target_type']): ?>
                                                <?php echo htmlspecialchars($log['target_type']); ?> #<?php echo htmlspecialchars($log['target_id'] ?? '?'); ?>
                                            <?php else: ?> 
                                                <span class="text-gray-300">—</span> 
                                            <?php endif; ?>
                                         </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 code-font text-center">
                                            <?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?>
                                         </td>
                                        <td class="px-4 py-3 text-center">
                                            <button onclick="event.stopPropagation(); showDetails(<?php echo json_encode($log, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)" 
                                                    class="text-blue-500 hover:text-blue-700 bg-blue-50 p-1.5 rounded-lg transition" 
                                                    title="View Details">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                         </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <div class="px-6 py-4 border-t flex justify-between items-center bg-gray-50">
                        <div class="text-sm text-gray-500">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalLogs); ?> of <?php echo number_format($totalLogs); ?>
                        </div>
                        <div class="flex gap-1">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&action=<?php echo urlencode($action); ?>&user_id=<?php echo urlencode($userId); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" 
                                   class="px-3 py-1 border rounded hover:bg-white text-sm transition">
                                    <i class="fas fa-chevron-left mr-1"></i>Prev
                                </a>
                            <?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&action=<?php echo urlencode($action); ?>&user_id=<?php echo urlencode($userId); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" 
                                   class="px-3 py-1 border rounded text-sm transition <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-white'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&action=<?php echo urlencode($action); ?>&user_id=<?php echo urlencode($userId); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" 
                                   class="px-3 py-1 border rounded hover:bg-white text-sm transition">
                                    Next<i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detail Modal -->
    <div id="detailModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
            <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-center z-10">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <span class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-alt"></i>
                    </span>
                    <span>Log Detail</span>
                </h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 w-8 h-8 rounded-lg flex items-center justify-center transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="detailContent" class="p-6 overflow-y-auto flex-1">
                <div class="flex justify-center py-12">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = `logs.php?${params.toString()}`;
        }
        
        function showDetails(log) {
            const modal = document.getElementById('detailModal');
            const content = document.getElementById('detailContent');
            
            let html = `
            <div class="space-y-6">
                <!-- Header -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div class="text-[10px] font-semibold text-gray-500 uppercase mb-1">Timestamp</div>
                            <div class="text-sm font-bold text-gray-800">${new Date(log.created_at).toLocaleString()}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-semibold text-gray-500 uppercase mb-1">IP Address</div>
                            <div class="text-sm font-bold code-font text-gray-800">${escapeHtml(log.ip_address || '—')}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-semibold text-gray-500 uppercase mb-1">Action</div>
                            <div class="text-xs font-bold text-gray-800">${escapeHtml(log.action)}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-semibold text-gray-500 uppercase mb-1">User</div>
                            <div class="text-sm font-bold text-gray-800">${escapeHtml(log.full_name || log.username || 'System')}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Target Information -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <div class="text-[10px] font-semibold text-gray-500 uppercase mb-1">Target Type</div>
                            <div class="text-xs font-bold code-font text-gray-800">${escapeHtml(log.target_type || '—')}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-semibold text-gray-500 uppercase mb-1">Target ID</div>
                            <div class="text-xs font-bold code-font text-gray-800">${escapeHtml(log.target_id || '—')}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-semibold text-gray-500 uppercase mb-1">User ID</div>
                            <div class="text-xs font-bold code-font text-gray-800">${escapeHtml(log.user_id || '—')}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Details Section -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <div class="text-[10px] font-semibold text-gray-500 uppercase">Raw Data</div>
                        <button onclick="copyToClipboard(document.getElementById('jsonViewer').textContent)" 
                                class="text-xs text-blue-600 hover:text-blue-800 transition">
                            <i class="fas fa-copy mr-1"></i>Copy
                        </button>
                    </div>
                    <div class="bg-gray-900 rounded-xl p-4 overflow-x-auto border border-gray-800 shadow-inner">
                        <pre class="text-green-400 text-xs code-font leading-relaxed whitespace-pre-wrap" id="jsonViewer"></pre>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="flex gap-2">
                    <button onclick="searchSimilarLogs('${escapeHtml(log.action)}')" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
                        <i class="fas fa-search mr-2"></i>Find Similar Actions
                    </button>
                    <button onclick="searchByUser(${log.user_id})" 
                            class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition text-sm">
                        <i class="fas fa-user mr-2"></i>View User's Activity
                    </button>
                </div>
            </div>`;
            
            content.innerHTML = html;
            
            try {
                const parsed = JSON.parse(log.details || '{}');
                document.getElementById('jsonViewer').textContent = JSON.stringify(parsed, null, 2);
            } catch(e) {
                document.getElementById('jsonViewer').textContent = log.details || 'No details available';
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        
        function closeModal() { 
            document.getElementById('detailModal').classList.add('hidden'); 
            document.getElementById('detailModal').classList.remove('flex'); 
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('Copied to clipboard!', 'success');
            }).catch(() => {
                showToast('Failed to copy', 'error');
            });
        }
        
        function searchSimilarLogs(action) {
            const params = new URLSearchParams(window.location.search);
            params.set('action', action);
            window.location.href = `logs.php?${params.toString()}`;
        }
        
        function searchByUser(userId) {
            const params = new URLSearchParams(window.location.search);
            params.set('user_id', userId);
            window.location.href = `logs.php?${params.toString()}`;
        }
        
        function showToast(msg, type) {
            const toast = document.createElement('div');
            toast.className = `toast p-4 rounded-lg shadow-lg border-l-4 ${type === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'}`;
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i><span class="font-medium text-sm">${msg}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
        }
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Daily Activity Chart
            const activityCtx = document.getElementById('activityChart').getContext('2d');
            new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($chartData, 'date')); ?>,
                    datasets: [{
                        label: 'Activity Count',
                        data: <?php echo json_encode(array_column($chartData, 'count')); ?>,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
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
            
            // Actions Chart
            const actionsCtx = document.getElementById('actionsChart').getContext('2d');
            new Chart(actionsCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($actionStats, 'action')); ?>,
                    datasets: [{
                        label: 'Count',
                        data: <?php echo json_encode(array_column($actionStats, 'count')); ?>,
                        backgroundColor: 'rgba(34, 197, 94, 0.8)'
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
        });
    </script>
</body>
</html>