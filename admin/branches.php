<?php
require_once '../config.php';
requireAdminLogin();

// Handle branch operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $branchCode = $_POST['branch_code'] ?? '';
        $name = $_POST['name'] ?? '';
        $nameAm = $_POST['name_am'] ?? '';
        $location = $_POST['location'] ?? '';
        $address = $_POST['address'] ?? '';
        $contactPhone = $_POST['contact_phone'] ?? '';
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;
        $description = $_POST['description'] ?? '';
        $status = $_POST['status'] ?? 'active';
        $displayOrder = $_POST['display_order'] ?? 0;
        
        try {
            if ($action === 'add') {
                $stmt = db()->prepare("
                    INSERT INTO branches (branch_code, name, name_am, location, address, contact_phone, latitude, longitude, description, status, display_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$branchCode, $name, $nameAm, $location, $address, $contactPhone, $latitude, $longitude, $description, $status, $displayOrder]);
                $success = "Branch added successfully";
                logActivity($_SESSION['admin_id'], 'ADD_BRANCH', 'BRANCH', db()->lastInsertId(), "Added branch: $name");
            } else {
                $stmt = db()->prepare("
                    UPDATE branches SET branch_code=?, name=?, name_am=?, location=?, address=?, contact_phone=?, latitude=?, longitude=?, description=?, status=?, display_order=?
                    WHERE id=?
                ");
                $stmt->execute([$branchCode, $name, $nameAm, $location, $address, $contactPhone, $latitude, $longitude, $description, $status, $displayOrder, $id]);
                $success = "Branch updated successfully";
                logActivity($_SESSION['admin_id'], 'EDIT_BRANCH', 'BRANCH', $id, "Edited branch: $name");
            }
        } catch (Exception $e) {
            $error = "Failed to save branch: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = db()->prepare("DELETE FROM branches WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Branch deleted successfully";
            logActivity($_SESSION['admin_id'], 'DELETE_BRANCH', 'BRANCH', $id, "Deleted branch ID: $id");
        } catch (Exception $e) {
            $error = "Failed to delete branch";
        }
    } elseif ($action === 'toggle_status') {
        $id = $_POST['id'] ?? 0;
        $status = $_POST['status'] ?? 'active';
        try {
            $stmt = db()->prepare("UPDATE branches SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            $success = "Branch status updated";
        } catch (Exception $e) {
            $error = "Failed to update status";
        }
    }
}

// Get branches
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    $query = "SELECT * FROM branches WHERE 1=1";
    $params = [];
    
    if ($search) {
        $query .= " AND (name LIKE ? OR branch_code LIKE ? OR location LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    if ($status) {
        $query .= " AND status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY display_order ASC, name ASC LIMIT $limit OFFSET $offset";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $branches = $stmt->fetchAll();
    
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM branches WHERE 1=1";
    $countParams = [];
    if ($search) {
        $countQuery .= " AND (name LIKE ? OR branch_code LIKE ? OR location LIKE ?)";
        $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam]);
    }
    if ($status) {
        $countQuery .= " AND status = ?";
        $countParams[] = $status;
    }
    $countStmt = db()->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalBranches = $countStmt->fetchColumn();
    $totalPages = ceil($totalBranches / $limit);
    
} catch (Exception $e) {
    $error = "Failed to load branches";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branches - Kaldis Coffee ECA Admin</title>
    <link rel="icon" type="image/png" href="../uploads/logo/kaldis-logo.png">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="../uploads/logo/kaldis-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">Branches Management</h1>
                <button onclick="openBranchModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-plus mr-2"></i>Add Branch
                </button>
            </div>
            
            <div class="p-6">
                <?php if (isset($success)): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4 rounded">
                        <p class="text-green-700"><?php echo $success; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4 rounded">
                        <p class="text-red-700"><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <form method="GET" class="flex flex-wrap gap-4">
                        <div class="flex-1 min-w-[200px]">
                            <input type="text" name="search" placeholder="Search branches..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                        </div>
                        <div>
                            <a href="branches.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 inline-block">
                                <i class="fas fa-sync-alt mr-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Branches Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($branches as $branch): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-md transition">
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-store text-green-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($branch['name']); ?></h3>
                                        <p class="text-xs text-gray-500"><?php echo $branch['branch_code']; ?></p>
                                    </div>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $branch['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($branch['status']); ?>
                                </span>
                            </div>
                            
                            <?php if ($branch['location']): ?>
                                <p class="text-sm text-gray-600 mb-2">
                                    <i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>
                                    <?php echo htmlspecialchars($branch['location']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($branch['contact_phone']): ?>
                                <p class="text-sm text-gray-600 mb-3">
                                    <i class="fas fa-phone text-gray-400 mr-1"></i>
                                    <?php echo htmlspecialchars($branch['contact_phone']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="flex gap-2 pt-3 border-t">
                                <button onclick="editBranch(<?php echo htmlspecialchars(json_encode($branch)); ?>)" 
                                        class="flex-1 bg-blue-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-600">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </button>
                                <button onclick="deleteBranch(<?php echo $branch['id']; ?>, '<?php echo htmlspecialchars($branch['name']); ?>')" 
                                        class="flex-1 bg-red-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-red-600">
                                    <i class="fas fa-trash mr-1"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-6 flex justify-center gap-2">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                           class="px-3 py-1 border rounded <?php echo $i == $page ? 'bg-green-600 text-white' : 'hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Branch Modal -->
    <div id="branchModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-center">
                <h3 class="text-lg font-bold" id="modalTitle">Add Branch</h3>
                <button onclick="closeBranchModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" id="branchForm" class="p-4 space-y-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="branchId" value="0">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Branch Code</label>
                        <input type="text" name="branch_code" id="branchCode" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Display Order</label>
                        <input type="number" name="display_order" id="displayOrder" value="0"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch Name (English)</label>
                    <input type="text" name="name" id="branchName" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch Name (Amharic)</label>
                    <input type="text" name="name_am" id="branchNameAm"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                    <input type="text" name="location" id="location"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Address</label>
                    <textarea name="address" id="address" rows="2"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Phone</label>
                        <input type="text" name="contact_phone" id="contactPhone"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                        <input type="text" name="latitude" id="latitude" placeholder="e.g., 9.0192"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                        <input type="text" name="longitude" id="longitude" placeholder="e.g., 38.7525"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="description" rows="2"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">
                        Save Branch
                    </button>
                    <button type="button" onclick="closeBranchModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openBranchModal() {
            document.getElementById('modalTitle').textContent = 'Add Branch';
            document.getElementById('formAction').value = 'add';
            document.getElementById('branchId').value = '0';
            document.getElementById('branchForm').reset();
            document.getElementById('branchModal').classList.add('flex');
            document.getElementById('branchModal').classList.remove('hidden');
        }
        
        function editBranch(branch) {
            document.getElementById('modalTitle').textContent = 'Edit Branch';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('branchId').value = branch.id;
            document.getElementById('branchCode').value = branch.branch_code;
            document.getElementById('branchName').value = branch.name;
            document.getElementById('branchNameAm').value = branch.name_am || '';
            document.getElementById('location').value = branch.location || '';
            document.getElementById('address').value = branch.address || '';
            document.getElementById('contactPhone').value = branch.contact_phone || '';
            document.getElementById('latitude').value = branch.latitude || '';
            document.getElementById('longitude').value = branch.longitude || '';
            document.getElementById('description').value = branch.description || '';
            document.getElementById('status').value = branch.status;
            document.getElementById('displayOrder').value = branch.display_order;
            document.getElementById('branchModal').classList.add('flex');
            document.getElementById('branchModal').classList.remove('hidden');
        }
        
        function closeBranchModal() {
            document.getElementById('branchModal').classList.add('hidden');
            document.getElementById('branchModal').classList.remove('flex');
        }
        
        function deleteBranch(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
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
    </script>
</body>
</html>