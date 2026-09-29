<?php
require_once '../config.php';
requireAdminLogin();

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'mark_read':
                $notificationId = $_POST['notification_id'] ?? '';
                if ($notificationId) {
                    $stmt = db()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND admin_id = ?");
                    $stmt->execute([$notificationId, $_SESSION['admin_id']]);
                    echo json_encode(['success' => true]);
                }
                break;
                
            case 'mark_all_read':
                $stmt = db()->prepare("UPDATE notifications SET is_read = 1 WHERE admin_id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                echo json_encode(['success' => true]);
                break;
                
            case 'delete_notification':
                $notificationId = $_POST['notification_id'] ?? '';
                if ($notificationId) {
                    $stmt = db()->prepare("DELETE FROM notifications WHERE id = ? AND admin_id = ?");
                    $stmt->execute([$notificationId, $_SESSION['admin_id']]);
                    echo json_encode(['success' => true]);
                }
                break;
                
            case 'delete_all':
                $stmt = db()->prepare("DELETE FROM notifications WHERE admin_id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                echo json_encode(['success' => true]);
                break;
                
            case 'send_notification':
                if (!isSuperAdmin()) {
                    throw new Exception('Unauthorized access');
                }
                
                $message = $_POST['message'] ?? '';
                $type = $_POST['type'] ?? 'info';
                $target_users = $_POST['target_users'] ?? 'all';
                
                if (empty($message)) {
                    throw new Exception('Message is required');
                }
                
                if ($target_users === 'all') {
                    // Send to all admin users
                    $stmt = db()->prepare("SELECT id FROM admin_users WHERE status = 1");
                    $stmt->execute();
                    $adminIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($adminIds as $adminId) {
                        $stmt = db()->prepare("
                            INSERT INTO notifications (admin_id, message, type, created_at, is_read)
                            VALUES (?, ?, ?, NOW(), 0)
                        ");
                        $stmt->execute([$adminId, $message, $type]);
                    }
                } else {
                    // Send to specific users
                    foreach ($target_users as $adminId) {
                        $stmt = db()->prepare("
                            INSERT INTO notifications (admin_id, message, type, created_at, is_read)
                            VALUES (?, ?, ?, NOW(), 0)
                        ");
                        $stmt->execute([$adminId, $message, $type]);
                    }
                }
                
                logActivity('SEND_NOTIFICATION', [
                    'message' => $message,
                    'type' => $type,
                    'target_users' => $target_users
                ], 'ADMIN', $_SESSION['admin_id']);
                
                echo json_encode(['success' => true, 'message' => 'Notification sent successfully']);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get notifications
 $page = $_GET['page'] ?? 1;
 $limit = 20;
 $offset = ($page - 1) * $limit;
 $type = $_GET['type'] ?? '';
 $unread_only = isset($_GET['unread']) ? 1 : 0;

try {
    $query = "SELECT * FROM notifications WHERE admin_id = ?";
    $params = [$_SESSION['admin_id']];
    
    if ($type) {
        $query .= " AND type = ?";
        $params[] = $type;
    }
    
    if ($unread_only) {
        $query .= " AND is_read = 0";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM notifications WHERE admin_id = ?";
    $countParams = [$_SESSION['admin_id']];
    
    if ($type) {
        $countQuery .= " AND type = ?";
        $countParams[] = $type;
    }
    
    if ($unread_only) {
        $countQuery .= " AND is_read = 0";
    }
    
    $countStmt = db()->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalNotifications = $countStmt->fetchColumn();
    $totalPages = ceil($totalNotifications / $limit);
    
    // Get unread count
    $unreadStmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE admin_id = ? AND is_read = 0");
    $unreadStmt->execute([$_SESSION['admin_id']]);
    $unreadCount = $unreadStmt->fetchColumn();
    
    // Get notification types for filter
    $typesStmt = db()->prepare("
        SELECT DISTINCT type 
        FROM notifications 
        WHERE admin_id = ?
        ORDER BY type
    ");
    $typesStmt->execute([$_SESSION['admin_id']]);
    $notificationTypes = $typesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all admin users for sending notifications (super admin only)
    $adminUsers = [];
    if (isSuperAdmin()) {
        $usersStmt = db()->prepare("SELECT id, username, role FROM admin_users WHERE status = 1 ORDER BY username");
        $usersStmt->execute();
        $adminUsers = $usersStmt->fetchAll();
    }
    
} catch (Exception $e) {
    $error = "Failed to load notifications: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .notification-card {
            transition: all 0.3s ease;
        }
        
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .notification-unread {
            border-left: 4px solid #10b981;
        }
        
        .notification-read {
            border-left: 4px solid transparent;
        }
        
        .type-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-weight: 500;
        }
        
        .type-info { background-color: #dbeafe; color: #1e40af; }
        .type-success { background-color: #d1fae5; color: #065f46; }
        .type-warning { background-color: #fed7aa; color: #92400e; }
        .type-error { background-color: #fee2e2; color: #991b1b; }
        
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .slide-in {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Notifications</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage system notifications and alerts</p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                        <?php echo $unreadCount; ?> Unread
                    </span>
                    <?php if (isSuperAdmin()): ?>
                        <button onclick="openSendModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-paper-plane mr-2"></i>Send Notification
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <form method="GET" class="flex flex-wrap gap-4 items-center">
                        <div>
                            <select name="type" class="px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="">All Types</option>
                                <?php foreach ($notificationTypes as $nt): ?>
                                    <option value="<?php echo $nt; ?>" <?php echo $type == $nt ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($nt); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="unread" id="unread" value="1" 
                                   class="mr-2" <?php echo $unread_only ? 'checked' : ''; ?>>
                            <label for="unread" class="text-sm text-gray-600">Show unread only</label>
                        </div>
                        <div>
                            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                                <i class="fas fa-filter mr-2"></i>Apply Filter
                            </button>
                        </div>
                        <div>
                            <a href="notifications.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                                <i class="fas fa-times mr-2"></i>Clear Filter
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="flex flex-wrap gap-3">
                        <button onclick="markAllRead()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                            <i class="fas fa-check-double mr-2"></i>Mark All Read
                        </button>
                        <button onclick="deleteAllNotifications()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm">
                            <i class="fas fa-trash mr-2"></i>Delete All
                        </button>
                        <button onclick="exportNotifications()" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 text-sm">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                    </div>
                </div>
                
                <!-- Notifications List -->
                <div class="space-y-4">
                    <?php foreach ($notifications as $notification): ?>
                    <div id="notification-<?php echo $notification['id']; ?>" 
                         class="notification-card notification-<?php echo $notification['is_read'] ? 'read' : 'unread'; ?> bg-white rounded-xl shadow-sm p-4 fade-in">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="type-badge type-<?php echo $notification['type']; ?>">
                                        <?php echo ucfirst($notification['type']); ?>
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </span>
                                </div>
                                <p class="text-gray-800 leading-relaxed">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                            </div>
                            <div class="flex items-center gap-2 ml-4">
                                <?php if (!$notification['is_read']): ?>
                                    <button onclick="markRead(<?php echo $notification['id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-800 p-2 rounded-lg hover:bg-blue-50 transition">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                <button onclick="deleteNotification(<?php echo $notification['id']; ?>)" 
                                        class="text-red-600 hover:text-red-800 p-2 rounded-lg hover:bg-red-50 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Empty State -->
                <?php if (empty($notifications)): ?>
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <i class="fas fa-bell-slash text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-600 mb-2">No notifications found</h3>
                        <p class="text-gray-500">There are no notifications matching your filters.</p>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-6 px-6 py-4 border-t flex justify-between items-center bg-gray-50">
                    <div class="text-sm text-gray-500">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalNotifications); ?> of <?php echo number_format($totalNotifications); ?>
                    </div>
                    <div class="flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&type=<?php echo urlencode($type); ?>&unread=<?php echo $unread_only; ?>" 
                               class="px-3 py-1 border rounded hover:bg-white text-sm transition">
                                <i class="fas fa-chevron-left mr-1"></i>Prev
                            </a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&type=<?php echo urlencode($type); ?>&unread=<?php echo $unread_only; ?>" 
                               class="px-3 py-1 border rounded text-sm transition <?php echo $i == $page ? 'bg-green-600 text-white' : 'hover:bg-white'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&type=<?php echo urlencode($type); ?>&unread=<?php echo $unread_only; ?>" 
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
    
    <!-- Send Notification Modal -->
    <?php if (isSuperAdmin()): ?>
    <div id="sendModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 slide-in">
            <div class="p-6 border-b">
                <h3 class="text-xl font-semibold text-gray-800">Send Notification</h3>
            </div>
            <form id="sendNotificationForm" method="POST" class="p-6">
                <input type="hidden" name="action" value="send_notification">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                    <textarea name="message" rows="4" required
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                              placeholder="Enter notification message..."></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                    <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <option value="info">Information</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                    </select>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Send to</label>
                    <div class="flex gap-2 mb-3">
                        <label class="flex items-center">
                            <input type="radio" name="target_users" value="all" checked class="mr-2">
                            <span>All Admin Users</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="target_users" value="specific" class="mr-2">
                            <span>Specific Users</span>
                        </label>
                    </div>
                    <div id="specificUsers" class="hidden">
                        <div class="border border-gray-300 rounded-lg p-3 max-h-40 overflow-y-auto">
                            <?php foreach ($adminUsers as $user): ?>
                                <label class="flex items-center py-2 hover:bg-gray-50 px-2 -mx-2 rounded">
                                    <input type="checkbox" name="target_users[]" value="<?php echo $user['id']; ?>" class="mr-2">
                                    <span class="text-sm"><?php echo htmlspecialchars($user['username']); ?> (<?php echo ucfirst($user['role']); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-paper-plane mr-2"></i>Send
                    </button>
                    <button type="button" onclick="closeSendModal()" class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300 transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // Mark notification as read
        function markRead(notificationId) {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_read&notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notification = document.getElementById('notification-' + notificationId);
                    notification.classList.remove('notification-unread');
                    notification.classList.add('notification-read');
                    
                    // Update unread count
                    updateUnreadCount();
                }
            });
        }
        
        // Mark all notifications as read
        function markAllRead() {
            if (confirm('Are you sure you want to mark all notifications as read?')) {
                fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=mark_all_read'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update all notifications
                        document.querySelectorAll('.notification-unread').forEach(notification => {
                            notification.classList.remove('notification-unread');
                            notification.classList.add('notification-read');
                        });
                        
                        // Update unread count
                        updateUnreadCount();
                    }
                });
            }
        }
        
        // Delete notification
        function deleteNotification(notificationId) {
            if (confirm('Are you sure you want to delete this notification?')) {
                fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete_notification&notification_id=' + notificationId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const notification = document.getElementById('notification-' + notificationId);
                        notification.style.opacity = '0';
                        notification.style.transform = 'translateX(100%)';
                        setTimeout(() => notification.remove(), 300);
                    }
                });
            }
        }
        
        // Delete all notifications
        function deleteAllNotifications() {
            if (confirm('Are you sure you want to delete all notifications? This action cannot be undone.')) {
                fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete_all'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove all notifications with animation
                        document.querySelectorAll('.notification-card').forEach(notification => {
                            notification.style.opacity = '0';
                            notification.style.transform = 'translateX(100%)';
                        });
                        
                        setTimeout(() => {
                            document.querySelector('.space-y-4').innerHTML = `
                                <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                                    <i class="fas fa-bell-slash text-4xl text-gray-300 mb-4"></i>
                                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No notifications found</h3>
                                    <p class="text-gray-500">There are no notifications matching your filters.</p>
                                </div>
                            `;
                        }, 300);
                    }
                });
            }
        }
        
        // Export notifications
        function exportNotifications() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = `export_notifications.php?${params.toString()}`;
        }
        
        // Open send modal
        function openSendModal() {
            document.getElementById('sendModal').classList.remove('hidden');
            document.getElementById('sendModal').classList.add('flex');
        }
        
        // Close send modal
        function closeSendModal() {
            document.getElementById('sendModal').classList.add('hidden');
            document.getElementById('sendModal').classList.remove('flex');
            document.getElementById('sendNotificationForm').reset();
            document.getElementById('specificUsers').classList.add('hidden');
        }
        
        // Update unread count
        function updateUnreadCount() {
            fetch('notifications.php?action=get_unread_count')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.querySelector('.bg-red-500');
                        if (badge) {
                            badge.textContent = data.count + ' Unread';
                            if (data.count === 0) {
                                badge.style.display = 'none';
                            }
                        }
                    }
                });
        }
        
        // Handle target users radio buttons
        document.querySelectorAll('input[name="target_users"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'specific') {
                    document.getElementById('specificUsers').classList.remove('hidden');
                } else {
                    document.getElementById('specificUsers').classList.add('hidden');
                }
            });
        });
        
        // Close modal on outside click
        document.getElementById('sendModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSendModal();
            }
        });
        
        // Handle form submission
        document.getElementById('sendNotificationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const targetUsersRadio = document.querySelector('input[name="target_users"]:checked');
            
            if (targetUsersRadio.value === 'specific') {
                const checkboxes = document.querySelectorAll('input[name="target_users[]"]:checked');
                if (checkboxes.length === 0) {
                    alert('Please select at least one user to send the notification to.');
                    return;
                }
            }
            
            fetch('notifications.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeSendModal();
                    // Show success message
                    const successDiv = document.createElement('div');
                    successDiv.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 fade-in';
                    successDiv.innerHTML = '<i class="fas fa-check mr-2"></i>' + data.message;
                    document.body.appendChild(successDiv);
                    
                    setTimeout(() => {
                        successDiv.remove();
                    }, 3000);
                }
            });
        });
    </script>
</body>
</html>