<?php
require_once '../config.php';
requireAdminLogin();

// Handle payment method operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $name = trim($_POST['name'] ?? '');
        $nameAm = trim($_POST['name_am'] ?? '');
        $accountName = trim($_POST['account_name'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $bankName = trim($_POST['bank_name'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        $displayOrder = intval($_POST['display_order'] ?? 0);
        
        // Validate inputs
        if (empty($name)) {
            $error = "Payment method name is required";
        } else {
            try {
                if ($action === 'add') {
                    // Check for duplicate names
                    $checkStmt = db()->prepare("SELECT id FROM payment_methods WHERE name = ?");
                    $checkStmt->execute([$name]);
                    if ($checkStmt->fetch()) {
                        $error = "Payment method with this name already exists";
                    } else {
                        $stmt = db()->prepare("
                            INSERT INTO payment_methods (name, name_am, account_name, account_number, bank_name, instructions, status, display_order)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$name, $nameAm, $accountName, $accountNumber, $bankName, $instructions, $status, $displayOrder]);
                        $success = "Payment method added successfully";
                        logActivity($_SESSION['admin_id'], 'ADD_PAYMENT_METHOD', 'PAYMENT', db()->lastInsertId(), "Added payment method: $name");
                    }
                } else {
                    $stmt = db()->prepare("
                        UPDATE payment_methods SET name=?, name_am=?, account_name=?, account_number=?, bank_name=?, instructions=?, status=?, display_order=?
                        WHERE id=?
                    ");
                    $stmt->execute([$name, $nameAm, $accountName, $accountNumber, $bankName, $instructions, $status, $displayOrder, $id]);
                    $success = "Payment method updated successfully";
                    logActivity($_SESSION['admin_id'], 'EDIT_PAYMENT_METHOD', 'PAYMENT', $id, "Edited payment method: $name");
                }
            } catch (Exception $e) {
                $error = "Failed to save payment method: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            // Check if payment method is used in orders
            $checkStmt = db()->prepare("SELECT COUNT(*) FROM orders WHERE payment_method_id = ?");
            $checkStmt->execute([$id]);
            if ($checkStmt->fetchColumn() > 0) {
                $error = "Cannot delete payment method. It is used in existing orders.";
            } else {
                $stmt = db()->prepare("DELETE FROM payment_methods WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Payment method deleted successfully";
                logActivity($_SESSION['admin_id'], 'DELETE_PAYMENT_METHOD', 'PAYMENT', $id, "Deleted payment method ID: $id");
            }
        } catch (Exception $e) {
            $error = "Failed to delete payment method: " . $e->getMessage();
        }
    } elseif ($action === 'toggle_status') {
        $id = $_POST['id'] ?? 0;
        $status = $_POST['status'] ?? 1;
        try {
            $stmt = db()->prepare("UPDATE payment_methods SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            $success = "Payment method status updated";
            logActivity($_SESSION['admin_id'], 'TOGGLE_PAYMENT_STATUS', 'PAYMENT', $id, "Updated payment method status to: " . ($status ? 'Active' : 'Inactive'));
        } catch (Exception $e) {
            $error = "Failed to update status: " . $e->getMessage();
        }
    } elseif ($action === 'reorder') {
        $orderData = json_decode($_POST['order'] ?? '', true);
        if (is_array($orderData)) {
            try {
                db()->beginTransaction();
                foreach ($orderData as $index => $id) {
                    $stmt = db()->prepare("UPDATE payment_methods SET display_order = ? WHERE id = ?");
                    $stmt->execute([$index, $id]);
                }
                db()->commit();
                $success = "Payment methods reordered successfully";
                logActivity($_SESSION['admin_id'], 'REORDER_PAYMENT_METHODS', 'PAYMENT', null, "Reordered payment methods");
            } catch (Exception $e) {
                db()->rollBack();
                $error = "Failed to reorder payment methods: " . $e->getMessage();
            }
        }
    }
}

// Get payment methods
 $page = $_GET['page'] ?? 1;
 $limit = 20;
 $offset = ($page - 1) * $limit;

try {
    $stmt = db()->prepare("SELECT * FROM payment_methods ORDER BY display_order ASC, name ASC LIMIT $limit OFFSET $offset");
    $stmt->execute();
    $paymentMethods = $stmt->fetchAll();
    
    // Get total count
    $countStmt = db()->query("SELECT COUNT(*) FROM payment_methods");
    $totalMethods = $countStmt->fetchColumn();
    $totalPages = ceil($totalMethods / $limit);
    
    // Get statistics
    $statsStmt = db()->query("
        SELECT 
            COUNT(*) as total_methods,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_methods,
            SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_methods
        FROM payment_methods
    ");
    $stats = $statsStmt->fetch();
    
} catch (Exception $e) {
    $error = "Failed to load payment methods: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Methods - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sortable-ghost {
            opacity: 0.4;
        }
        .sortable-chosen {
            background-color: #dbeafe;
        }
        .payment-card {
            transition: all 0.3s ease;
        }
        .payment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .payment-card.dragging {
            opacity: 0.5;
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Payment Methods</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage payment options and customer payment instructions</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="openPaymentModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-plus mr-2"></i>Add Payment Method
                    </button>
                    <button onclick="reorderPaymentMethods()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-sort mr-2"></i>Reorder
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_methods'] ?? 0); ?></p>
                        <p class="text-sm text-gray-500">Total Methods</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['active_methods'] ?? 0); ?></p>
                        <p class="text-sm text-gray-500">Active</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold text-red-600"><?php echo number_format($stats['inactive_methods'] ?? 0); ?></p>
                        <p class="text-sm text-gray-500">Inactive</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold text-blue-600"><?php echo round(($stats['active_methods'] / max(1, $stats['total_methods'])) * 100, 0); ?>%</p>
                        <p class="text-sm text-gray-500">Active Rate</p>
                    </div>
                </div>
                
                <!-- Success/Error Messages -->
                <?php if (isset($success)): ?>
                    <div class="toast bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 mr-3"></i>
                            <p class="text-green-700"><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="toast bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-600 mr-3"></i>
                            <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Payment Methods Grid -->
                <div id="paymentMethodsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($paymentMethods as $method): ?>
                    <div class="payment-card bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-md transition cursor-move" data-id="<?php echo $method['id']; ?>">
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white shadow-md">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($method['name']); ?></h3>
                                        <?php if ($method['name_am']): ?>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($method['name_am']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button onclick="toggleStatus(<?php echo $method['id']; ?>, <?php echo $method['status']; ?>)" 
                                            class="text-sm <?php echo $method['status'] ? 'text-green-600 hover:text-green-800' : 'text-gray-400 hover:text-gray-600'; ?>">
                                        <i class="fas <?php echo $method['status'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                    </button>
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $method['status'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $method['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="space-y-2 mb-4">
                                <?php if ($method['account_name']): ?>
                                    <div class="flex items-center gap-2 text-sm">
                                        <i class="fas fa-user text-gray-400 w-4"></i>
                                        <span class="text-gray-600"><?php echo htmlspecialchars($method['account_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($method['account_number']): ?>
                                    <div class="flex items-center gap-2 text-sm">
                                        <i class="fas fa-hashtag text-gray-400 w-4"></i>
                                        <span class="font-mono text-gray-800"><?php echo htmlspecialchars($method['account_number']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($method['bank_name']): ?>
                                    <div class="flex items-center gap-2 text-sm">
                                        <i class="fas fa-building text-gray-400 w-4"></i>
                                        <span class="text-gray-600"><?php echo htmlspecialchars($method['bank_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($method['instructions']): ?>
                                    <div class="mt-3 p-2 bg-blue-50 rounded-lg">
                                        <p class="text-xs text-blue-800 font-semibold mb-1">Instructions:</p>
                                        <p class="text-xs text-blue-700"><?php echo nl2br(htmlspecialchars($method['instructions'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex gap-2 pt-3 border-t">
                                <button onclick="editPayment(<?php echo htmlspecialchars(json_encode($method, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" 
                                        class="flex-1 bg-blue-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-600 transition">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </button>
                                <button onclick="deletePayment(<?php echo $method['id']; ?>, '<?php echo htmlspecialchars($method['name']); ?>')" 
                                        class="flex-1 bg-red-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-red-600 transition">
                                    <i class="fas fa-trash mr-1"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($paymentMethods)): ?>
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <i class="fas fa-credit-card text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No payment methods found.</p>
                        <button onclick="openPaymentModal()" class="mt-4 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-plus mr-2"></i>Add First Payment Method
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-6 px-6 py-4 border-t flex justify-between items-center bg-gray-50">
                    <div class="text-sm text-gray-500">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalMethods); ?> of <?php echo number_format($totalMethods); ?>
                    </div>
                    <div class="flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" 
                               class="px-3 py-1 border rounded hover:bg-white text-sm transition">
                                <i class="fas fa-chevron-left mr-1"></i>Prev
                            </a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>" 
                               class="px-3 py-1 border rounded text-sm transition <?php echo $i == $page ? 'bg-green-600 text-white' : 'hover:bg-white'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" 
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
    
    <!-- Payment Method Modal -->
    <div id="paymentModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
            <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-center z-10">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <span class="w-8 h-8 bg-green-100 text-green-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-credit-card"></i>
                    </span>
                    <span id="modalTitle">Add Payment Method</span>
                </h3>
                <button onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 w-8 h-8 rounded-lg flex items-center justify-center transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="paymentForm" class="p-6 space-y-4 overflow-y-auto flex-1">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="paymentId" value="0">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Display Order</label>
                        <input type="number" name="display_order" id="displayOrder" value="0" min="0"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Method Name (English) *
                        <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="methodName" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           placeholder="e.g., Bank Transfer, Mobile Money">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Method Name (Amharic)</label>
                    <input type="text" name="name_am" id="methodNameAm"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           placeholder="የአማርኛ ስም">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                    <input type="text" name="account_name" id="accountName"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           placeholder="Account holder name">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                    <input type="text" name="account_number" id="accountNumber"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg font-mono focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           placeholder="Account/phone number">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                    <input type="text" name="bank_name" id="bankName"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           placeholder="Bank or service provider name">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Instructions</label>
                    <textarea name="instructions" id="instructions" rows="4"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                              placeholder="Enter detailed payment instructions for customers..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">This will be shown to customers during checkout</p>
                </div>
                
                <div class="flex gap-3 pt-4 border-t">
                    <button type="submit" class="flex-1 bg-green-600 text-white py-2.5 rounded-lg hover:bg-green-700 transition font-medium">
                        <i class="fas fa-save mr-2"></i>Save Payment Method
                    </button>
                    <button type="button" onclick="closePaymentModal()" class="flex-1 bg-gray-200 text-gray-700 py-2.5 rounded-lg hover:bg-gray-300 transition font-medium">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reorder Modal -->
    <div id="reorderModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden shadow-2xl flex flex-col">
            <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-center z-10">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <span class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sort"></i>
                    </span>
                    <span>Reorder Payment Methods</span>
                </h3>
                <button onclick="closeReorderModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 w-8 h-8 rounded-lg flex items-center justify-center transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-1">
                <p class="text-sm text-gray-600 mb-4">Drag and drop to reorder payment methods. The order will be reflected in the customer checkout.</p>
                <div id="sortableList" class="space-y-2">
                    <?php foreach ($paymentMethods as $method): ?>
                    <div class="sortable-item bg-gray-50 p-3 rounded-lg flex items-center gap-3 cursor-move" data-id="<?php echo $method['id']; ?>">
                        <i class="fas fa-grip-vertical text-gray-400"></i>
                        <div class="flex-1">
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($method['name']); ?></p>
                            <?php if ($method['name_am']): ?>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($method['name_am']); ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="text-sm text-gray-500">Order: <?php echo $method['display_order']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="border-t p-4 flex gap-3">
                <button onclick="saveOrder()" class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 transition font-medium">
                    <i class="fas fa-save mr-2"></i>Save Order
                </button>
                <button onclick="closeReorderModal()" class="flex-1 bg-gray-200 text-gray-700 py-2.5 rounded-lg hover:bg-gray-300 transition font-medium">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        // Initialize sortable
        let sortable = null;
        
        function openPaymentModal() {
            document.getElementById('modalTitle').textContent = 'Add Payment Method';
            document.getElementById('formAction').value = 'add';
            document.getElementById('paymentId').value = '0';
            document.getElementById('paymentForm').reset();
            document.getElementById('paymentModal').classList.remove('hidden');
            document.getElementById('paymentModal').classList.add('flex');
        }
        
        function editPayment(method) {
            document.getElementById('modalTitle').textContent = 'Edit Payment Method';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('paymentId').value = method.id;
            document.getElementById('methodName').value = method.name;
            document.getElementById('methodNameAm').value = method.name_am || '';
            document.getElementById('accountName').value = method.account_name || '';
            document.getElementById('accountNumber').value = method.account_number || '';
            document.getElementById('bankName').value = method.bank_name || '';
            document.getElementById('instructions').value = method.instructions || '';
            document.getElementById('status').value = method.status;
            document.getElementById('displayOrder').value = method.display_order;
            document.getElementById('paymentModal').classList.remove('hidden');
            document.getElementById('paymentModal').classList.add('flex');
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').classList.add('hidden');
            document.getElementById('paymentModal').classList.remove('flex');
        }
        
        function deletePayment(id, name) {
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
        
        function toggleStatus(id, currentStatus) {
            const newStatus = currentStatus ? 0 : 1;
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="status" value="${newStatus}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function reorderPaymentMethods() {
            document.getElementById('reorderModal').classList.remove('hidden');
            document.getElementById('reorderModal').classList.add('flex');
            
            // Initialize sortable
            if (!sortable) {
                sortable = Sortable.create(document.getElementById('sortableList'), {
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    onEnd: function(evt) {
                        // Update order numbers visually
                        const items = document.querySelectorAll('.sortable-item');
                        items.forEach((item, index) => {
                            const orderSpan = item.querySelector('.text-gray-500');
                            if (orderSpan) {
                                orderSpan.textContent = `Order: ${index}`;
                            }
                        });
                    }
                });
            }
        }
        
        function closeReorderModal() {
            document.getElementById('reorderModal').classList.add('hidden');
            document.getElementById('reorderModal').classList.remove('flex');
        }
        
        function saveOrder() {
            const items = document.querySelectorAll('.sortable-item');
            const order = [];
            
            items.forEach(item => {
                order.push(item.dataset.id);
            });
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="reorder">
                <input type="hidden" name="order" value='${JSON.stringify(order)}'>
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast p-4 rounded-lg shadow-lg border-l-4 ${type === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'}`;
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i><span class="font-medium text-sm">${message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
        }
        
        // Initialize sortable on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add drag handle to payment cards
            const cards = document.querySelectorAll('.payment-card');
            cards.forEach(card => {
                card.draggable = true;
                card.addEventListener('dragstart', function(e) {
                    this.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', this.outerHTML);
                });
                card.addEventListener('dragend', function() {
                    this.classList.remove('dragging');
                });
            });
        });
    </script>
</body>
</html>