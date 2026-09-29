<?php
require_once '../config.php';
requireAdminLogin();

// Log viewing
if (!isSuperAdmin()) {
    header('Location: dashboard.php');
    exit;
}

// Get logs
 $page = $_GET['page'] ?? 1;
 $limit = 50;
 $offset = ($page - 1) * $limit;
 $level = $_GET['level'] ?? '';
 $type = $_GET['type'] ?? '';

try {
    $query = "SELECT * FROM system_logs WHERE 1=1";
    $params = [];
    
    if ($level) {
        $query .= " AND level = ?";
        $params[] = $level;
    }
    
    if ($type) {
        $query .= " AND type = ?";
        $params[] = $type;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM system_logs WHERE 1=1";
    $countParams = [];
    if ($level) {
        $countQuery .= " AND level = ?";
        $countParams[] = $level;
    }
    if ($type) {
        $countQuery .= " AND type = ?";
        $countParams[] = $type;
    }
    $countStmt = db()->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalLogs = $countStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $limit);
    
    // Get log levels for filter
    $levels = db()->query("SELECT DISTINCT level FROM system_logs ORDER BY level")->fetchAll(PDO::FETCH_COLUMN);
    $types = db()->query("SELECT DISTINCT type FROM system_logs ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $error = "Failed to load system logs: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .log-card {
            transition: all 0.3s ease;
        }
        .log-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .log-level-error { border-left: 4px solid #ef4444; }
        .log-level-warning { border-left: 4px solid #f59e0b; }
        .log-level-info { border-left: 4px solid #3b82f6; }
        .log-level-debug { border-left: 4px solid #6b7280; }
        .code-font { font-family: 'Courier New', monospace; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">System Logs</h1>
                    <p class="text-sm text-gray-500 mt-1">Monitor system activity and errors</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="exportLogs()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-download mr-2"></i>Export
                    </button>
                    <button onclick="clearLogs()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                        <i class="fas fa-trash mr-2"></i>Clear Old
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <form method="GET" class="flex flex-wrap gap-4 items-center">
                        <div>
                            <select name="level" class="px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="">All Levels</option>
                                <?php foreach ($levels as $lvl): ?>
                                    <option value="<?php echo $lvl; ?>" <?php echo $level == $lvl ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($lvl); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select name="type" class="px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="">All Types</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?php echo $t; ?>" <?php echo $type == $t ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($t); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                        </div>
                        <div>
                            <a href="system_logs.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                                <i class="fas fa-sync-alt mr-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Log Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold text-blue-600"><?php echo number_format($totalLogs); ?></p>
                        <p class="text-sm text-gray-500">Total Logs</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold text-red-600"><?php echo count(array_filter($logs, fn($l) => $l['level'] === 'error')); ?></p>
                        <p class="text-sm text-gray-500">Errors</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold text-yellow-600"><?php echo count(array_filter($logs, fn($l) => $l['level'] === 'warning')); ?></p>
                        <p class="text-sm text-gray-500">Warnings</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold text-green-600"><?php echo count(array_filter($logs, fn($l) => $l['level'] === 'info')); ?></p>
                        <p class="text-sm text-gray-500">Info</p>
                    </div>
                </div>
                
                <!-- Logs List -->
                <div class="space-y-4">
                    <?php foreach ($logs as $log): ?>
                    <div class="log-card log-level-<?php echo $log['level']; ?> bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-<?php echo $log['level'] === 'error' ? 'red' : ($log['level'] === 'warning' ? 'yellow' : 'blue'); ?>-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-<?php echo $log['level'] === 'error' ? 'exclamation-triangle' : ($log['level'] === 'warning' ? 'exclamation-circle' : 'info-circle'); ?> text-<?php echo $log['level'] === 'error' ? 'red' : ($log['level'] === 'warning' ? 'yellow' : 'blue'); ?>-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($log['message']); ?></h3>
                                        <p class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($log['type']); ?> • 
                                            <?php echo date('M d, Y g:i A', strtotime($log['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full bg-<?php echo $log['level'] === 'error' ? 'red' : ($log['level'] === 'warning' ? 'yellow' : 'blue'); ?>-100 text-<?php echo $log['level'] === 'error' ? 'red' : ($log['level'] === 'warning' ? 'yellow' : 'blue'); ?>-800">
                                    <?php echo ucfirst($log['level']); ?>
                                </span>
                            </div>
                            
                            <?php if ($log['details']): ?>
                            <div class="bg-gray-50 rounded-lg p-3 mt-2">
                                <p class="text-sm font-medium text-gray-700 mb-1">Details:</p>
                                <pre class="text-xs code-font text-gray-600"><?php echo htmlspecialchars($log['details']); ?></pre>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Empty State -->
                <?php if (empty($logs)): ?>
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <i class="fas fa-file-alt text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No system logs found matching your filters.</p>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-6 px-6 py-4 border-t flex justify-between items-center bg-gray-50">
                    <div class="text-sm text-gray-500">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalLogs); ?> of <?php echo number_format($totalLogs); ?>
                    </div>
                    <div class="flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&level=<?php echo urlencode($level); ?>&type=<?php echo urlencode($type); ?>" 
                               class="px-3 py-1 border rounded hover:bg-white text-sm transition">
                                <i class="fas fa-chevron-left mr-1"></i>Prev
                            </a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&level=<?php echo urlencode($level); ?>&type=<?php echo urlencode($type); ?>" 
                               class="px-3 py-1 border rounded text-sm transition <?php echo $i == $page ? 'bg-green-600 text-white' : 'hover:bg-white'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&level=<?php echo urlencode($level); ?>&type=<?php echo urlencode($type); ?>" 
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
    
    <script>
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = `system_logs.php?${params.toString()}`;
        }
        
        function clearLogs() {
            if (confirm('Are you sure you want to clear old logs? This action cannot be undone.')) {
                window.location.href = 'clear_logs.php';
            }
        }
    </script>
</body>
</html>