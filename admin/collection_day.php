<?php
require_once '../config.php';
requireAdminLogin();

// ============================================================
// AJAX HANDLERS
// ============================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    // --- Toggle Status ---
    if ($action === 'toggle_status') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $currentStatus = $input['current_status'] ?? '';
        
        if ($id && in_array($currentStatus, ['Active', 'Inactive'])) {
            $newStatus = $currentStatus === 'Active' ? 'Inactive' : 'Active';
            try {
                $stmt = db()->prepare("UPDATE collection_days SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $id]);
                echo json_encode(['success' => true, 'new_status' => $newStatus]);
            } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        } else { echo json_encode(['success' => false, 'message' => 'Invalid data']); }
        exit;
    }

    // --- Add / Edit Delivery Date ---
    if ($action === 'save_date') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $nameAm = trim($input['name_am'] ?? '');
        $date = trim($input['date'] ?? '');
        $startTime = !empty($input['start_time']) ? $input['start_time'] : null;
        $endTime = !empty($input['end_time']) ? $input['end_time'] : null;
        $status = in_array($input['status'] ?? '', ['Active', 'Inactive']) ? $input['status'] : 'Active';
        $displayOrder = intval($input['display_order'] ?? 0);
        $maxOrders = !empty($input['max_orders']) ? intval($input['max_orders']) : null;
        
        if (empty($name) || empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Name and Date are required']);
            exit;
        }

        try {
            if ($id === 0) {
                // Check for duplicate date
                $dup = db()->prepare("SELECT id FROM collection_days WHERE date = ?");
                $dup->execute([$date]);
                if ($dup->fetch()) { echo json_encode(['success' => false, 'message' => 'A delivery date for this day already exists']); exit; }

                $stmt = db()->prepare("INSERT INTO collection_days (name, name_am, date, start_time, end_time, status, display_order, max_orders) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $nameAm, $date, $startTime, $endTime, $status, $displayOrder, $maxOrders]);
                echo json_encode(['success' => true, 'message' => 'Delivery date added successfully']);
            } else {
                $stmt = db()->prepare("UPDATE collection_days SET name=?, name_am=?, date=?, start_time=?, end_time=?, status=?, display_order=?, max_orders=? WHERE id=?");
                $stmt->execute([$name, $nameAm, $date, $startTime, $endTime, $status, $displayOrder, $maxOrders, $id]);
                echo json_encode(['success' => true, 'message' => 'Delivery date updated successfully']);
            }
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]); }
        exit;
    }

    // --- Delete Delivery Date ---
    if ($action === 'delete_date') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        
        try {
            // Check if any orders use this date string
            $dateStmt = db()->prepare("SELECT date FROM collection_days WHERE id = ?");
            $dateStmt->execute([$id]);
            $dateStr = $dateStmt->fetchColumn();
            
            if ($dateStr) {
                $checkStmt = db()->prepare("SELECT COUNT(*) FROM pre_orders WHERE delivery_date = ?");
                $checkStmt->execute([$dateStr]);
                $orderCount = $checkStmt->fetchColumn();
                
                if ($orderCount > 0) {
                    echo json_encode(['success' => false, 'message' => "Cannot delete: $orderCount orders are scheduled for this date. Set to Inactive instead."]);
                    exit;
                }
            }

            $stmt = db()->prepare("DELETE FROM collection_days WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Delivery date deleted successfully']);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Failed to delete']); }
        exit;
    }

    exit;
}

// ============================================================
// PAGE LOAD
// ============================================================
 $page = $_GET['page'] ?? 1;
 $limit = 20;
 $offset = ($page - 1) * $limit;
 $statusFilter = $_GET['status'] ?? '';

try {
    $query = "SELECT cd.*, (SELECT COUNT(*) FROM pre_orders WHERE delivery_date = cd.date) as order_count FROM collection_days cd WHERE 1=1";
    $params = [];
    
    if ($statusFilter) { $query .= " AND cd.status = ?"; $params[] = $statusFilter; }
    
    $query .= " ORDER BY cd.date DESC LIMIT $limit OFFSET $offset";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $collectionDays = $stmt->fetchAll();
    
    $countQuery = "SELECT COUNT(*) FROM collection_days WHERE 1=1";
    $countParams = [];
    if ($statusFilter) { $countQuery .= " AND status = ?"; $countParams[] = $statusFilter; }
    $countStmt = db()->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalDays = $countStmt->fetchColumn();
    $totalPages = ceil($totalDays / $limit);

    $activeCount = db()->query("SELECT COUNT(*) FROM collection_days WHERE status = 'Active'")->fetchColumn();
    $pastCount = db()->query("SELECT COUNT(*) FROM collection_days WHERE date < CURDATE()")->fetchColumn();
} catch (Exception $e) { $error = "Failed to load delivery dates"; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Dates - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .toast { position: fixed; top: 20px; right: 20px; z-index: 9999; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .toggle-checkbox:checked { right: 0; border-color: #4CAF50; }
        .toggle-checkbox:checked + .toggle-label { background-color: #4CAF50; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Delivery Dates</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage available delivery schedules</p>
                </div>
                <button onclick="openModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm font-medium">
                    <i class="fas fa-plus mr-2"></i>Add Date
                </button>
            </div>
            
            <div class="p-6">
                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-calendar-check"></i></div>
                        <div><div class="text-sm text-gray-500">Active Dates</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($activeCount ?? 0); ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-calendar-alt"></i></div>
                        <div><div class="text-sm text-gray-500">Total Dates</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($totalDays ?? 0); ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-gray-50 text-gray-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-calendar-times"></i></div>
                        <div><div class="text-sm text-gray-500">Past Dates</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($pastCount ?? 0); ?></div></div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6 border border-gray-100">
                    <form method="GET" class="flex flex-wrap gap-3 items-center">
                        <select name="status" class="px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $statusFilter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <button type="submit" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700"><i class="fas fa-filter mr-2"></i>Filter</button>
                        <a href="collection_day.php" class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg hover:bg-gray-200"><i class="fas fa-sync-alt mr-2"></i>Reset</a>
                    </form>
                </div>
                
                <!-- Table -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Time Slot</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Orders</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Limit</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($collectionDays)): ?>
                                <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400"><i class="fas fa-calendar-day text-4xl mb-3 block"></i>No delivery dates found</td></tr>
                                <?php endif; ?>
                                
                                <?php foreach ($collectionDays as $day): 
                                    $isPast = strtotime($day['date']) < strtotime(date('Y-m-d'));
                                ?>
                                <tr class="hover:bg-gray-50 transition <?php echo $isPast ? 'opacity-60' : ''; ?>" id="row_<?php echo $day['id']; ?>">
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-bold text-gray-800 font-mono"><?php echo date('M d, Y', strtotime($day['date'])); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('l', strtotime($day['date'])); ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($day['name']); ?></div>
                                        <?php if ($day['name_am']): ?><div class="text-xs text-gray-500 font-family: 'Noto Sans Ethiopic'"><?php echo htmlspecialchars($day['name_am']); ?></div><?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?php if ($day['start_time'] && $day['end_time']): ?>
                                            <i class="fas fa-clock text-xs mr-1 text-gray-400"></i><?php echo date('g:i A', strtotime($day['start_time'])) . ' - ' . date('g:i A', strtotime($day['end_time'])); ?>
                                        <?php else: ?> <span class="text-gray-400 text-xs">All day</span> <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold <?php echo $day['order_count'] > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>">
                                            <?php echo $day['order_count']; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-600">
                                        <?php echo $day['max_orders'] ? number_format($day['max_orders']) : '<span class="text-gray-400">∞</span>'; ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button onclick="toggleStatus(<?php echo $day['id']; ?>, '<?php echo $day['status']; ?>')" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none <?php echo $day['status'] === 'Active' ? 'bg-green-500' : 'bg-gray-300'; ?>">
                                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?php echo $day['status'] === 'Active' ? 'translate-x-6' : 'translate-x-1'; ?>"></span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button onclick="openModal(<?php echo htmlspecialchars(json_encode($day)); ?>)" class="text-indigo-500 hover:text-indigo-700 bg-indigo-50 p-1.5 rounded-lg" title="Edit"><i class="fas fa-pen text-sm"></i></button>
                                            <button onclick="openDeleteModal(<?php echo $day['id']; ?>, '<?php echo htmlspecialchars($day['name']); ?>')" class="text-red-500 hover:text-red-700 bg-red-50 p-1.5 rounded-lg" title="Delete"><i class="fas fa-trash-alt text-sm"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <div class="px-6 py-4 border-t flex justify-between items-center bg-gray-50">
                        <div class="text-sm text-gray-500">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
                        <div class="flex gap-1">
                            <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-1 border rounded hover:bg-white text-sm">Prev</a><?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-1 border rounded text-sm <?php echo $i === $page ? 'bg-green-600 text-white border-green-600' : 'hover:bg-white'; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-1 border rounded hover:bg-white text-sm">Next</a><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Modal -->
    <div id="dateModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden">
            <div class="bg-gray-50 border-b p-4 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2" id="dateModalTitle">
                    <span class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center"><i class="fas fa-calendar-plus"></i></span>
                    <span>Add Delivery Date</span>
                </h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 w-8 h-8 rounded-lg flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
            <form id="dateForm" onsubmit="saveDate(event)" class="p-5 space-y-4">
                <input type="hidden" id="formId" value="0">
                
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Name (English) *</label><input type="text" id="dayName" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="e.g., Monday Delivery"></div>
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Name (Amharic)</label><input type="text" id="dayNameAm" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="e.g., ሰኞ"></div>
                </div>
                
                <div><label class="block text-xs font-semibold text-gray-500 mb-1">Date *</label><input type="date" id="dayDate" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Start Time</label><input type="time" id="dayStart" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">End Time</label><input type="time" id="dayEnd" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                </div>
                
                <div class="grid grid-cols-3 gap-4">
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Max Orders</label><input type="number" id="dayMax" placeholder="∞" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Status</label><select id="dayStatus" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Sort Order</label><input type="number" id="dayOrder" value="0" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                </div>
                
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-medium text-sm">Cancel</button>
                    <button type="submit" id="saveBtn" class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 font-medium text-sm"><i class="fas fa-save mr-2"></i>Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl text-center">
            <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center text-2xl bg-red-100 text-red-600"><i class="fas fa-trash-alt"></i></div>
            <h3 class="text-xl font-bold text-gray-800 mb-1">Delete Date?</h3>
            <p class="text-sm text-gray-500 mb-5">Are you sure you want to delete <span id="delName" class="font-bold"></span>? This cannot be undone.</p>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()" class="flex-1 px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-medium text-sm">Cancel</button>
                <button id="delConfirmBtn" onclick="confirmDelete()" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 font-medium text-sm">Delete</button>
            </div>
        </div>
    </div>

    <script>
        let pendingDeleteId = null;

        // --- TOAST NOTIFICATION ---
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast p-4 rounded-lg shadow-lg border-l-4 ${type === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'} flex items-center gap-3`;
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span class="font-medium text-sm">${message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
        }

        // --- STATUS TOGGLE ---
        async function toggleStatus(id, currentStatus) {
            try {
                const res = await fetch('collection_day.php?action=toggle_status', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, current_status: currentStatus })
                });
                const data = await res.json();
                if (data.success) { showToast(`Status changed to ${data.new_status}`); setTimeout(() => window.location.reload(), 800); }
                else { showToast(data.message, 'error'); }
            } catch (e) { showToast('Network error', 'error'); }
        }

        // --- ADD / EDIT MODAL ---
        function openModal(dayData = null) {
            document.getElementById('dateForm').reset();
            document.getElementById('formId').value = '0';
            
            const titleEl = document.getElementById('dateModalTitle');
            if (dayData) {
                titleEl.innerHTML = `<span class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center"><i class="fas fa-pen"></i></span><span>Edit Delivery Date</span>`;
                document.getElementById('formId').value = dayData.id;
                document.getElementById('dayName').value = dayData.name;
                document.getElementById('dayNameAm').value = dayData.name_am || '';
                document.getElementById('dayDate').value = dayData.date;
                document.getElementById('dayStart').value = dayData.start_time || '';
                document.getElementById('dayEnd').value = dayData.end_time || '';
                document.getElementById('dayMax').value = dayData.max_orders || '';
                document.getElementById('dayStatus').value = dayData.status;
                document.getElementById('dayOrder').value = dayData.display_order;
            } else {
                titleEl.innerHTML = `<span class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center"><i class="fas fa-calendar-plus"></i></span><span>Add Delivery Date</span>`;
            }
            document.getElementById('dateModal').classList.remove('hidden');
            document.getElementById('dateModal').classList.add('flex');
        }
        function closeModal() { document.getElementById('dateModal').classList.add('hidden'); document.getElementById('dateModal').classList.remove('flex'); }

        async function saveDate(event) {
            event.preventDefault();
            const btn = document.getElementById('saveBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...'; btn.disabled = true;

            const payload = {
                id: document.getElementById('formId').value,
                name: document.getElementById('dayName').value,
                name_am: document.getElementById('dayNameAm').value,
                date: document.getElementById('dayDate').value,
                start_time: document.getElementById('dayStart').value,
                end_time: document.getElementById('dayEnd').value,
                max_orders: document.getElementById('dayMax').value,
                status: document.getElementById('dayStatus').value,
                display_order: document.getElementById('dayOrder').value
            };

            try {
                const res = await fetch('collection_day.php?action=save_date', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) { showToast(data.message); setTimeout(() => window.location.reload(), 800); }
                else { showToast(data.message, 'error'); btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save'; btn.disabled = false; }
            } catch (e) { showToast('Network error', 'error'); btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save'; btn.disabled = false; }
        }

        // --- DELETE MODAL ---
        function openDeleteModal(id, name) {
            pendingDeleteId = id;
            document.getElementById('delName').textContent = name;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }
        function closeDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); document.getElementById('deleteModal').classList.remove('flex'); }

        async function confirmDelete() {
            const btn = document.getElementById('delConfirmBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...'; btn.disabled = true;

            try {
                const res = await fetch('collection_day.php?action=delete_date', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: pendingDeleteId })
                });
                const data = await res.json();
                if (data.success) { showToast(data.message); document.getElementById(`row_${pendingDeleteId}`).remove(); closeDeleteModal(); setTimeout(() => window.location.reload(), 800); }
                else { showToast(data.message, 'error'); btn.innerHTML = 'Delete'; btn.disabled = false; closeDeleteModal(); }
            } catch (e) { showToast('Network error', 'error'); btn.innerHTML = 'Delete'; btn.disabled = false; }
        }
    </script>
</body>
</html>