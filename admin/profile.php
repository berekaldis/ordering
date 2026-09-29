<?php
require_once '../config.php';
requireAdminLogin();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    try {
        $admin = getCurrentAdmin();
        if (!$admin || $admin['id'] != $id) {
            throw new Exception("Invalid profile data");
        }
        
        // Validate current password if changing password
        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                throw new Exception("Current password is required");
            }
            
            if (!password_verify($currentPassword, $admin['password'])) {
                throw new Exception("Current password is incorrect");
            }
            
            if ($newPassword !== $confirmPassword) {
                throw new Exception("New passwords do not match");
            }
            
            if (strlen($newPassword) < 8) {
                throw new Exception("New password must be at least 8 characters");
            }
        }
        
        // Update admin profile
        $updateFields = ['username = ?, email = ?, full_name = ?'];
        $updateValues = [$username, $email, $fullName];
        
        if (!empty($newPassword)) {
            $updateFields[] = 'password = ?';
            $updateValues[] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        
        $updateQuery = "UPDATE admin_users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $updateStmt = db()->prepare($updateQuery);
        $updateValues[] = $id;
        $updateStmt->execute($updateValues);
        
        // Update session data
        $_SESSION['admin_username'] = $username;
        if (isset($_SESSION['admin_data'])) {
            $_SESSION['admin_data']['username'] = $username;
            $_SESSION['admin_data']['email'] = $email;
            $_SESSION['admin_data']['full_name'] = $fullName;
        }
        
        $success = "Profile updated successfully";
        logActivity($admin['id'], 'UPDATE_PROFILE', 'ADMIN', $admin['id'], "Updated profile information");
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get admin profile data
 $admin = getCurrentAdmin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .profile-card {
            transition: all 0.3s ease;
        }
        .profile-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-800">Profile Settings</h1>
                <p class="text-sm text-gray-500 mt-1">Manage your account profile and security settings</p>
            </div>
            
            <div class="p-6">
                <!-- Success/Error Messages -->
                <?php if (isset($success)): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <p class="text-green-700"><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Profile Card -->
                    <div class="lg:col-span-1">
                        <div class="profile-card bg-white rounded-xl shadow-sm p-6 text-center">
                            <div class="w-24 h-24 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-user text-white text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-800 mb-1"><?php echo htmlspecialchars($admin['full_name'] ?? $admin['username']); ?></h3>
                            <p class="text-sm text-gray-500 mb-4"><?php echo htmlspecialchars($admin['email']); ?></p>
                            <div class="bg-gray-50 rounded-lg p-3 mb-4">
                                <p class="text-xs text-gray-500 mb-1">Role</p>
                                <p class="font-medium text-gray-800"><?php echo ucfirst($admin['role']); ?></p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-500 mb-1">Member Since</p>
                                <p class="font-medium text-gray-800"><?php echo date('M d, Y', strtotime($admin['created_at'] ?? date('Y-m-d'))); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Form -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-6">Edit Profile</h3>
                            
                            <form method="POST" class="space-y-6">
                                <input type="hidden" name="id" value="<?php echo $admin['id']; ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                        <input type="text" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    </div>
                                </div>
                                
                                <hr class="border-gray-200">
                                
                                <h3 class="text-lg font-semibold text-gray-800">Change Password</h3>
                                <p class="text-sm text-gray-600 mb-4">Leave blank to keep current password</p>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                        <input type="password" name="current_password"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                        <input type="password" name="new_password"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                        <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                        <input type="password" name="confirm_password"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    </div>
                                </div>
                                
                                <div class="flex gap-3 pt-4">
                                    <button type="submit" class="bg-green-600 text-white px-6 py-2.5 rounded-lg hover:bg-green-700 transition">
                                        <i class="fas fa-save mr-2"></i>Save Changes
                                    </button>
                                    <button type="button" onclick="window.location.href='dashboard.php'" 
                                            class="bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg hover:bg-gray-300 transition">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>