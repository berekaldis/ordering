<?php
require_once '../config.php';
requireAdminLogin();

// Require users permission to access user management
requirePermission('users');

// Helper function to check if user can perform actions
function canManageUser($targetUserId) {
    global $db;
    // Super admin can manage all users
    if (isSuperAdmin()) return true;
    
    // Regular admin cannot manage super admins
    $stmt = $db->prepare("SELECT role FROM admin_users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    $targetRole = $stmt->fetchColumn();
    
    return $targetRole !== 'superadmin';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'superadmin']) ? $_POST['role'] : 'admin';
        
        // Validation
        if (empty($username) || empty($password) || empty($email)) {
            $error = "Username, password, and email are required";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } else {
            // Check if username already exists
            $stmt = db()->prepare("SELECT id FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn()) {
                $error = "Username already exists";
            } else {
                try {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? json_encode($_POST['permissions']) : json_encode([]);
                    if ($role === 'superadmin') {
                        $permissions = json_encode(['orders','products','customers','delivery_locations','reports','feedback','settings','users']);
                    }
                    $stmt = db()->prepare("INSERT INTO admin_users (username, password, email, full_name, role, permissions) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $passwordHash, $email, $fullName, $role, $permissions]);
                    
                    // Log activity
                    logActivity($_SESSION['admin_id'], 'ADD_USER', 'USER', db()->lastInsertId(), "Added user: $username");
                    
                    // Send welcome email
                    sendWelcomeEmail($email, $username, $password);
                    
                    $success = "User added successfully";
                } catch (Exception $e) {
                    $error = "Failed to add user: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'superadmin']) ? $_POST['role'] : 'admin';
        $status = intval($_POST['status'] ?? 1);
        $permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? json_encode($_POST['permissions']) : json_encode([]);
        if ($role === 'superadmin') {
            $permissions = json_encode(['orders','products','customers','delivery_locations','reports','feedback','settings','users']);
        }
        
        // Check if current user can manage this user
        if (!canManageUser($id)) {
            $error = "You don't have permission to manage this user";
        } else {
            try {
                $stmt = db()->prepare("UPDATE admin_users SET email=?, full_name=?, role=?, status=?, permissions=? WHERE id=?");
                $stmt->execute([$email, $fullName, $role, $status, $permissions, $id]);
                
                logActivity($_SESSION['admin_id'], 'EDIT_USER', 'USER', $id, "Edited user ID: $id");
                $success = "User updated successfully";
            } catch (Exception $e) {
                $error = "Failed to update user: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        
        // Prevent deleting yourself
        if ($id == $_SESSION['admin_id']) {
            $error = "You cannot delete your own account";
        } elseif (!canManageUser($id)) {
            $error = "You don't have permission to delete this user";
        } else {
            try {
                $stmt = db()->prepare("DELETE FROM admin_users WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity($_SESSION['admin_id'], 'DELETE_USER', 'USER', $id, "Deleted user ID: $id");
                $success = "User deleted successfully";
            } catch (Exception $e) {
                $error = "Failed to delete user";
            }
        }
    } elseif ($action === 'reset_password') {
        $id = intval($_POST['id'] ?? 0);
        $newPassword = 'Admin@123';
        
        if (!canManageUser($id)) {
            $error = "You don't have permission to reset this user's password";
        } else {
            try {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = db()->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                $stmt->execute([$passwordHash, $id]);
                
                logActivity($_SESSION['admin_id'], 'RESET_PASSWORD', 'USER', $id, "Reset password for user ID: $id");
                
                // Send password reset notification
                sendPasswordResetNotification($id, $newPassword);
                
                $success = "Password reset to 'Admin@123'";
            } catch (Exception $e) {
                $error = "Failed to reset password";
            }
        }
    } elseif ($action === 'change_password') {
        $id = intval($_POST['id'] ?? 0);
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = "All password fields are required";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New passwords do not match";
        } elseif (strlen($newPassword) < 6) {
            $error = "New password must be at least 6 characters";
        } else {
            // Verify current password
            $stmt = db()->prepare("SELECT password FROM admin_users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($currentPassword, $user['password'])) {
                try {
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = db()->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                    $stmt->execute([$passwordHash, $id]);
                    
                    logActivity($_SESSION['admin_id'], 'CHANGE_PASSWORD', 'USER', $id, "Changed password for user ID: $id");
                    $success = "Password changed successfully";
                } catch (Exception $e) {
                    $error = "Failed to change password";
                }
            } else {
                $error = "Current password is incorrect";
            }
        }
    }
}

// Get users with search and filters
 $page = $_GET['page'] ?? 1;
 $limit = 20;
 $offset = ($page - 1) * $limit;
 $search = $_GET['search'] ?? '';
 $roleFilter = $_GET['role'] ?? '';
 $statusFilter = $_GET['status'] ?? '';

try {
    $query = "SELECT * FROM admin_users WHERE 1=1";
    $params = [];
    
    if ($search) {
        $query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    if ($roleFilter) {
        $query .= " AND role = ?";
        $params[] = $roleFilter;
    }
    
    if ($statusFilter !== '') {
        $query .= " AND status = ?";
        $params[] = $statusFilter;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM admin_users WHERE 1=1";
    $countParams = [];
    if ($search) {
        $countQuery .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
        $searchParam = "%$search%";
        $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam]);
    }
    if ($roleFilter) {
        $countQuery .= " AND role = ?";
        $countParams[] = $roleFilter;
    }
    if ($statusFilter !== '') {
        $countQuery .= " AND status = ?";
        $countParams[] = $statusFilter;
    }
    
    $countStmt = db()->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalUsers = $countStmt->fetchColumn();
    $totalPages = ceil($totalUsers / $limit);
    
    // Get user statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'superadmin' THEN 1 ELSE 0 END) as super_admins,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_users
        FROM admin_users
    ";
    $stats = db()->query($statsQuery)->fetch();
    
} catch (Exception $e) {
    $error = "Failed to load users";
}

// Helper functions
function sendWelcomeEmail($email, $username, $password) {
    // In a real implementation, you would send an email
    // For now, we'll just log it
    logActivity(0, 'WELCOME_EMAIL_SENT', 'EMAIL', $email, "Welcome email sent to $username");
}

function sendPasswordResetNotification($userId, $newPassword) {
    // Get user details
    $stmt = db()->prepare("SELECT email, username FROM admin_users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        // In a real implementation, you would send an email
        logActivity(0, 'PASSWORD_RESET_NOTIFICATION', 'EMAIL', $user['email'], "Password reset notification sent to {$user['username']}");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Users - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .toast { position: fixed; top: 20px; right: 20px; z-index: 9999; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Admin Users</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage system administrators and permissions</p>
                </div>
                <button onclick="openUserModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm font-medium">
                    <i class="fas fa-plus mr-2"></i>Add User
                </button>
            </div>
            
            <div class="p-6">
                <!-- Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Total Users</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_users'] ?? 0); ?></p>
                            </div>
                            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Super Admins</p>
                                <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['super_admins'] ?? 0); ?></p>
                            </div>
                            <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-crown"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Admins</p>
                                <p class="text-2xl font-bold text-blue-600"><?php echo number_format($stats['admins'] ?? 0); ?></p>
                            </div>
                            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user-shield"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Active</p>
                                <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['active_users'] ?? 0); ?></p>
                            </div>
                            <div class="w-10 h-10 bg-green-100 text-green-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Inactive</p>
                                <p class="text-2xl font-bold text-red-600"><?php echo number_format($stats['inactive_users'] ?? 0); ?></p>
                            </div>
                            <div class="w-10 h-10 bg-red-100 text-red-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-times-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="toast bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-lg">
                        <p class="text-green-700"><?php echo $success; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="toast bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-lg">
                        <p class="text-red-700"><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Search and Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6 border border-gray-100">
                    <form method="GET" class="flex flex-wrap gap-4 items-center">
                        <div class="flex-1 min-w-[220px] relative">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" name="search" placeholder="Search by username, email or name..." value="<?php echo htmlspecialchars($search); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                        </div>
                        <select name="role" class="px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                            <option value="">All Roles</option>
                            <option value="superadmin" <?php echo $roleFilter === 'superadmin' ? 'selected' : ''; ?>>Super Admin</option>
                            <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                        <select name="status" class="px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                            <option value="">All Status</option>
                            <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <button type="submit" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700"><i class="fas fa-filter mr-2"></i>Filter</button>
                        <a href="users.php" class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg hover:bg-gray-200"><i class="fas fa-sync-alt mr-2"></i>Reset</a>
                    </form>
                </div>
                
                <!-- Users Table -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Full Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Login</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                        <i class="fas fa-users text-4xl mb-3 block"></i>
                                        <p>No users found</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php foreach ($users as $user): 
                                    // Check if current user can manage this user
                                    $canManage = canManageUser($user['id']);
                                    $isSelf = $user['id'] == $_SESSION['admin_id'];
                                ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold text-gray-600">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </div>
                                            <span class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($user['username']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-600"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full font-semibold <?php echo $user['role'] === 'superadmin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <i class="fas <?php echo $user['role'] === 'superadmin' ? 'fa-crown' : 'fa-user-shield'; ?> mr-1"></i>
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full font-semibold <?php echo $user['status'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <i class="fas <?php echo $user['status'] ? 'fa-check-circle' : 'fa-times-circle'; ?> mr-1"></i>
                                            <?php echo $user['status'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php echo $user['last_login'] ? date('M d, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <?php if ($canManage): ?>
                                                <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                                        class="text-blue-600 hover:text-blue-800 bg-blue-50 p-2 rounded-lg transition" title="Edit">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </button>
                                                <?php if (!$isSelf): ?>
                                                    <button onclick="changePassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                            class="text-yellow-600 hover:text-yellow-800 bg-yellow-50 p-2 rounded-lg transition" title="Change Password">
                                                        <i class="fas fa-key text-sm"></i>
                                                    </button>
                                                    <button onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                            class="text-orange-600 hover:text-orange-800 bg-orange-50 p-2 rounded-lg transition" title="Reset Password">
                                                        <i class="fas fa-undo text-sm"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if ($canManage && !$isSelf): ?>
                                                <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                        class="text-red-600 hover:text-red-800 bg-red-50 p-2 rounded-lg transition" title="Delete">
                                                    <i class="fas fa-trash text-sm"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($isSelf): ?>
                                                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="px-6 py-4 border-t flex justify-between items-center bg-gray-50">
                        <div class="text-sm text-gray-500">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalUsers); ?> of <?php echo number_format($totalUsers); ?></div>
                        <div class="flex gap-1">
                            <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($roleFilter); ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-1 border rounded hover:bg-white text-sm">Prev</a><?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($roleFilter); ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-1 border rounded text-sm <?php echo $i === $page ? 'bg-green-600 text-white border-green-600' : 'hover:bg-white'; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($roleFilter); ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-1 border rounded hover:bg-white text-sm">Next</a><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Modal -->
    <div id="userModal" class="modal fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
            <div class="bg-gray-50 border-b p-4 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2" id="modalTitle">
                    <i class="fas fa-user-plus text-green-600"></i>
                    <span>Add User</span>
                </h3>
                <button onclick="closeUserModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 w-8 h-8 rounded-lg flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="userForm" class="p-5 space-y-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="userId" value="0">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                    <input type="text" name="username" id="username" required
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none"
                           placeholder="Enter username">
                </div>
                
                <div id="passwordField">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                    <input type="password" name="password" id="password" required
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none"
                           placeholder="Enter password">
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="full_name" id="fullName"
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none"
                           placeholder="Enter full name">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" id="email" required
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none"
                           placeholder="Enter email address">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="role" id="role" onchange="togglePermissionsByRole()" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                            <option value="admin">Admin</option>
                            <option value="superadmin">Super Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="status" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <!-- Granted Module Permissions -->
                <div class="border-t border-gray-100 pt-3 mt-3">
                    <label class="block text-sm font-semibold text-gray-800 mb-1">Granted Module Permissions</label>
                    <p class="text-xs text-gray-500 mb-2">Super Admin has full access. Grant specific permissions for Admin role:</p>
                    <div class="grid grid-cols-2 gap-2 bg-gray-50 p-3 rounded-xl border border-gray-200" id="permissionsGrid">
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="orders" class="perm-checkbox rounded text-green-600 focus:ring-green-500">
                            <span>📦 Orders Management</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="products" class="perm-checkbox rounded text-green-600 focus:ring-green-500">
                            <span>☕ Products & Stock</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="customers" class="perm-checkbox rounded text-green-600 focus:ring-green-500">
                            <span>👥 Customers</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="delivery_locations" class="perm-checkbox rounded text-green-600 focus:ring-green-500">
                            <span>🏢 Delivery Locations</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="reports" class="perm-checkbox rounded text-green-600 focus:ring-green-500">
                            <span>📊 Sales Reports</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="feedback" class="perm-checkbox rounded text-green-600 focus:ring-green-500">
                            <span>⭐ Customer Feedback</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="settings" class="perm-checkbox rounded text-green-600 focus:ring-green-500">
                            <span>⚙️ System Settings</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="users" class="perm-checkbox rounded text-green-600 focus:ring-green-500">
                            <span>🔐 Admin Users & Roles</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 bg-green-600 text-white py-2.5 rounded-lg hover:bg-green-700 font-medium transition">
                        <i class="fas fa-save mr-2"></i>Save User
                    </button>
                    <button type="button" onclick="closeUserModal()" class="flex-1 bg-gray-200 text-gray-700 py-2.5 rounded-lg hover:bg-gray-300 font-medium transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="passwordModal" class="modal fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
            <div class="bg-gray-50 border-b p-4 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-key text-yellow-600"></i>
                    <span>Change Password</span>
                </h3>
                <button onclick="closePasswordModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 w-8 h-8 rounded-lg flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="passwordForm" class="p-5 space-y-4">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="id" id="passwordUserId" value="0">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password *</label>
                    <input type="password" name="current_password" id="currentPassword" required
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password *</label>
                    <input type="password" name="new_password" id="newPassword" required
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 outline-none">
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password *</label>
                    <input type="password" name="confirm_password" id="confirmPassword" required
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 outline-none">
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 bg-yellow-600 text-white py-2.5 rounded-lg hover:bg-yellow-700 font-medium transition">
                        <i class="fas fa-save mr-2"></i>Update Password
                    </button>
                    <button type="button" onclick="closePasswordModal()" class="flex-1 bg-gray-200 text-gray-700 py-2.5 rounded-lg hover:bg-gray-300 font-medium transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast p-4 rounded-lg shadow-lg border-l-4 ${type === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'} flex items-center gap-3`;
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span class="font-medium text-sm">${message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
        }
        
        function togglePermissionsByRole() {
            const role = document.getElementById('role').value;
            const checkboxes = document.querySelectorAll('.perm-checkbox');
            checkboxes.forEach(cb => {
                if (role === 'superadmin') {
                    cb.checked = true;
                    cb.disabled = true;
                } else {
                    cb.disabled = false;
                }
            });
        }
        
        function openUserModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus text-green-600"></i><span>Add User</span>';
            document.getElementById('formAction').value = 'add';
            document.getElementById('userId').value = '0';
            document.getElementById('passwordField').style.display = 'block';
            document.getElementById('userForm').reset();
            togglePermissionsByRole();
            document.getElementById('userModal').classList.add('flex');
            document.getElementById('userModal').classList.remove('hidden');
        }
        
        function editUser(user) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit text-blue-600"></i><span>Edit User</span>';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('userId').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('fullName').value = user.full_name || '';
            document.getElementById('email').value = user.email;
            document.getElementById('role').value = user.role;
            document.getElementById('status').value = user.status;
            document.getElementById('passwordField').style.display = 'none';

            let perms = [];
            if (user.permissions) {
                try { perms = typeof user.permissions === 'string' ? JSON.parse(user.permissions) : user.permissions; } catch(e) {}
            }
            const checkboxes = document.querySelectorAll('.perm-checkbox');
            checkboxes.forEach(cb => {
                if (user.role === 'superadmin') {
                    cb.checked = true;
                    cb.disabled = true;
                } else {
                    cb.disabled = false;
                    cb.checked = Array.isArray(perms) && perms.includes(cb.value);
                }
            });

            document.getElementById('userModal').classList.add('flex');
            document.getElementById('userModal').classList.remove('hidden');
        }
        
        function closeUserModal() {
            document.getElementById('userModal').classList.add('hidden');
            document.getElementById('userModal').classList.remove('flex');
        }
        
        function changePassword(id, username) {
            document.getElementById('passwordUserId').value = id;
            document.getElementById('passwordForm').reset();
            document.getElementById('passwordModal').classList.add('flex');
            document.getElementById('passwordModal').classList.remove('hidden');
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').classList.add('hidden');
            document.getElementById('passwordModal').classList.remove('flex');
        }
        
        function deleteUser(id, username) {
            if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function resetPassword(id, username) {
            if (confirm(`Reset password for "${username}" to "Admin@123"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Handle form submissions
        document.getElementById('userForm').addEventListener('submit', function(e) {
            e.preventDefault();
            this.submit();
        });
        
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                showToast('New passwords do not match', 'error');
                return;
            }
            
            if (newPassword.length < 6) {
                showToast('Password must be at least 6 characters', 'error');
                return;
            }
            
            this.submit();
        });
        
        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeUserModal();
                closePasswordModal();
            }
        });
    </script>
</body>
</html>