<?php
require_once '../config.php';
requireAdminLogin();
requirePermission('delivery_locations');

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
        
        if ($id && in_array($currentStatus, ['active', 'inactive'])) {
            $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
            try {
                $stmt = db()->prepare("UPDATE delivery_locations SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $id]);
                logActivity($_SESSION['admin_id'], 'TOGGLE_LOCATION_STATUS', 'LOCATION', $id, "Status changed to: $newStatus");
                echo json_encode(['success' => true, 'new_status' => $newStatus]);
            } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        } else { echo json_encode(['success' => false, 'message' => 'Invalid data']); }
        exit;
    }

    // --- Bulk Actions ---
    if ($action === 'bulk_action') {
        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['ids'] ?? [];
        $operation = $input['operation'] ?? '';
        
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No locations selected']);
            exit;
        }
        
        try {
            if ($operation === 'delete') {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = db()->prepare("DELETE FROM delivery_locations WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                logActivity($_SESSION['admin_id'], 'BULK_DELETE_LOCATIONS', 'LOCATION', implode(',', $ids), "Bulk deleted locations");
                echo json_encode(['success' => true, 'message' => 'Locations deleted successfully']);
            } elseif ($operation === 'toggle_status') {
                $newStatus = $input['new_status'] ?? 'active';
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = db()->prepare("UPDATE delivery_locations SET status = ? WHERE id IN ($placeholders)");
                $stmt->execute([$newStatus, ...$ids]);
                logActivity($_SESSION['admin_id'], 'BULK_TOGGLE_LOCATION_STATUS', 'LOCATION', implode(',', $ids), "Bulk status changed to: $newStatus");
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            }
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Database error']); }
        exit;
    }

    // --- Add / Edit Location ---
    if ($action === 'save_location') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $locationCode = trim($input['location_code'] ?? '');
        $name = trim($input['name'] ?? '');
        $nameAm = trim($input['name_am'] ?? '');
        $area = trim($input['area'] ?? '');
        $address = trim($input['address'] ?? '');
        $contactPhone = trim($input['contact_phone'] ?? '');
        $latitude = !empty($input['latitude']) ? floatval($input['latitude']) : null;
        $longitude = !empty($input['longitude']) ? floatval($input['longitude']) : null;
        $description = trim($input['description'] ?? '');
        $status = in_array($input['status'] ?? '', ['active', 'inactive']) ? $input['status'] : 'active';
        $displayOrder = intval($input['display_order'] ?? 0);
        
        if (empty($name) || empty($locationCode)) {
            echo json_encode(['success' => false, 'message' => 'Location Code and Name are required']);
            exit;
        }

        try {
            if ($id === 0) {
                $stmt = db()->prepare("INSERT INTO delivery_locations (location_code, name, name_am, area, address, contact_phone, latitude, longitude, description, status, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$locationCode, $name, $nameAm, $area, $address, $contactPhone, $latitude, $longitude, $description, $status, $displayOrder]);
                logActivity($_SESSION['admin_id'], 'ADD_LOCATION', 'LOCATION', db()->lastInsertId(), "Added location: $name");
                echo json_encode(['success' => true, 'message' => 'Location added successfully']);
            } else {
                $stmt = db()->prepare("UPDATE delivery_locations SET location_code=?, name=?, name_am=?, area=?, address=?, contact_phone=?, latitude=?, longitude=?, description=?, status=?, display_order=? WHERE id=?");
                $stmt->execute([$locationCode, $name, $nameAm, $area, $address, $contactPhone, $latitude, $longitude, $description, $status, $displayOrder, $id]);
                logActivity($_SESSION['admin_id'], 'EDIT_LOCATION', 'LOCATION', $id, "Edited location: $name");
                echo json_encode(['success' => true, 'message' => 'Location updated successfully']);
            }
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]); }
        exit;
    }

    // --- Delete Location ---
    if ($action === 'delete_location') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        
        try {
            $checkStmt = db()->prepare("SELECT COUNT(*) FROM pre_orders WHERE delivery_location_id = ?");
            $checkStmt->execute([$id]);
            $orderCount = $checkStmt->fetchColumn();
            
            if ($orderCount > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete: $orderCount orders are linked to this location. Deactivate it instead."]);
                exit;
            }

            $stmt = db()->prepare("DELETE FROM delivery_locations WHERE id = ?");
            $stmt->execute([$id]);
            logActivity($_SESSION['admin_id'], 'DELETE_LOCATION', 'LOCATION', $id, "Deleted location ID: $id");
            echo json_encode(['success' => true, 'message' => 'Location deleted successfully']);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Failed to delete']); }
        exit;
    }

    // --- Export Locations CSV ---
    if ($action === 'export_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=delivery_locations_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Code', 'Name', 'Name (Amharic)', 'Area', 'Address', 'Contact', 'Latitude', 'Longitude', 'Status', 'Orders']);

        $stmt = db()->query("SELECT * FROM delivery_locations ORDER BY display_order ASC, name ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['location_code'],
                $row['name'],
                $row['name_am'],
                $row['area'],
                $row['address'],
                $row['contact_phone'],
                $row['latitude'],
                $row['longitude'],
                $row['status'],
                $row['order_count']
            ]);
        }
        fclose($output);
        exit;
    }

    // --- Get Location Orders ---
    if ($action === 'get_location_orders') {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Location ID required']);
            exit;
        }
        
        try {
            $stmt = db()->prepare("
                SELECT po.order_number, po.status, po.total_amount, po.created_at, po.client_name
                FROM pre_orders po
                WHERE po.delivery_location_id = ?
                ORDER BY po.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$id]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'orders' => $orders]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }

    exit;
}

// ============================================================
// PAGE LOAD
// ============================================================
 $search = $_GET['search'] ?? '';
 $statusFilter = $_GET['status'] ?? '';
 $viewMode = $_GET['view'] ?? 'grid'; // grid or map
 $page = $_GET['page'] ?? 1;
 $limit = 15;
 $offset = ($page - 1) * $limit;

try {
    $query = "SELECT dl.*, (SELECT COUNT(*) FROM pre_orders WHERE delivery_location_id = dl.id) as order_count FROM delivery_locations dl WHERE 1=1";
    $params = [];
    
    if ($search) {
        $query .= " AND (dl.name LIKE ? OR dl.location_code LIKE ? OR dl.area LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    if ($statusFilter) { $query .= " AND dl.status = ?"; $params[] = $statusFilter; }
    
    $query .= " ORDER BY dl.display_order ASC, dl.name ASC";
    
    if ($viewMode === 'grid') {
        $query .= " LIMIT $limit OFFSET $offset";
    }
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $locations = $stmt->fetchAll();
    
    // Total Count
    $countQuery = "SELECT COUNT(*) FROM delivery_locations WHERE 1=1";
    $countParams = [];
    if ($search) {
        $countQuery .= " AND (name LIKE ? OR location_code LIKE ? OR area LIKE ?)";
        $searchParam = "%$search%";
        $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam]);
    }
    if ($statusFilter) { $countQuery .= " AND status = ?"; $countParams[] = $statusFilter; }
    $countStmt = db()->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalLocations = $countStmt->fetchColumn();
    $totalPages = ceil($totalLocations / $limit);

    // Stats
    $statsQuery = "
        SELECT 
            COUNT(*) as total_locations,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_locations,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_locations,
            SUM(order_count) as total_orders,
            AVG(order_count) as avg_orders_per_location
        FROM delivery_locations
    ";
    $stats = db()->query($statsQuery)->fetch();
    
    // Get locations for map view
    $mapLocations = [];
    if ($viewMode === 'map') {
        $mapStmt = db()->prepare($query);
        $mapStmt->execute($params);
        $mapLocations = $mapStmt->fetchAll();
    }
} catch (Exception $e) { $error = "Failed to load locations"; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Locations - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .toast { position: fixed; top: 20px; right: 20px; z-index: 9999; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .checkbox-custom { appearance: none; width: 1.25rem; height: 1.25rem; border: 2px solid #d1d5db; border-radius: 0.25rem; cursor: pointer; position: relative; }
        .checkbox-custom:checked { background-color: #10b981; border-color: #10b981; }
        .checkbox-custom:checked::after { content: '✓'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-weight: bold; }
        .location-card { transition: all 0.2s ease; }
        .location-card:hover { transform: translateY(-2px); }
        #map { height: 600px; border-radius: 0.5rem; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Delivery Locations</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage customer delivery areas</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="openModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>Add Location
                    </button>
                    <a href="?action=export_csv&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                        <i class="fas fa-file-csv mr-2 text-green-600"></i>Export CSV
                    </a>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-map-marker-alt"></i></div>
                        <div><div class="text-sm text-gray-500">Active Locations</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['active_locations'] ?? 0); ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-gray-50 text-gray-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-ban"></i></div>
                        <div><div class="text-sm text-gray-500">Inactive</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['inactive_locations'] ?? 0); ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-globe-africa"></i></div>
                        <div><div class="text-sm text-gray-500">Total Locations</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_locations'] ?? 0); ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-box"></i></div>
                        <div><div class="text-sm text-gray-500">Total Orders</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_orders'] ?? 0); ?></div></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-chart-line"></i></div>
                        <div><div class="text-sm text-gray-500">Avg Orders/Loc</div><div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['avg_orders_per_location'] ?? 0, 1); ?></div></div>
                    </div>
                </div>

                <!-- Filters and View Toggle -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6 border border-gray-100">
                    <div class="flex flex-wrap gap-4 items-center justify-between">
                        <form method="GET" class="flex flex-wrap gap-3 items-center">
                            <div class="flex-1 min-w-[220px] relative">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                <input type="text" name="search" placeholder="Search name, code or area..." value="<?php echo htmlspecialchars($search); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                            </div>
                            <select name="status" class="px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <button type="submit" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700"><i class="fas fa-filter mr-2"></i>Filter</button>
                            <a href="locations.php" class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg hover:bg-gray-200"><i class="fas fa-sync-alt mr-2"></i>Reset</a>
                        </form>
                        
                        <div class="flex items-center gap-2 bg-gray-100 rounded-lg p-1">
                            <button onclick="setViewMode('grid')" class="px-3 py-1 rounded text-sm <?php echo $viewMode === 'grid' ? 'bg-white shadow' : ''; ?>">
                                <i class="fas fa-th mr-1"></i>Grid
                            </button>
                            <button onclick="setViewMode('map')" class="px-3 py-1 rounded text-sm <?php echo $viewMode === 'map' ? 'bg-white shadow' : ''; ?>">
                                <i class="fas fa-map mr-1"></i>Map
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Bulk Actions -->
                <div id="bulkActions" class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6 hidden">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" id="selectAll" class="checkbox-custom" onchange="toggleSelectAll()">
                            <label for="selectAll" class="text-sm font-medium text-gray-700">Select all <span id="selectedCount">0</span> locations</label>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="bulkToggleStatus('active')" class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-green-700">
                                <i class="fas fa-check mr-1"></i>Active
                            </button>
                            <button onclick="bulkToggleStatus('inactive')" class="bg-gray-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-gray-700">
                                <i class="fas fa-ban mr-1"></i>Inactive
                            </button>
                            <button onclick="bulkDelete()" class="bg-red-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-red-700">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Locations Grid -->
                <?php if ($viewMode === 'grid'): ?>
                <?php if (empty($locations)): ?>
                <div class="bg-white rounded-xl shadow-sm p-12 text-center border border-gray-100">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-map-marker-alt text-gray-400 text-3xl"></i></div>
                    <h3 class="text-lg font-medium text-gray-800 mb-2">No Locations Found</h3>
                    <p class="text-gray-500 mb-4 text-sm">Get started by adding your first delivery location.</p>
                    <button onclick="openModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm font-medium"><i class="fas fa-plus mr-2"></i>Add Location</button>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($locations as $loc): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-all flex flex-col location-card" id="card_<?php echo $loc['id']; ?>">
                        <div class="p-4 flex-1">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-green-50 text-green-600 rounded-xl flex items-center justify-center font-bold text-sm">
                                        <?php echo htmlspecialchars($loc['location_code']); ?>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($loc['name']); ?></h3>
                                        <?php if ($loc['name_am']): ?><p class="text-xs text-gray-500" style="font-family: 'Noto Sans Ethiopic', sans-serif;"><?php echo htmlspecialchars($loc['name_am']); ?></p><?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" class="location-checkbox checkbox-custom" data-id="<?php echo $loc['id']; ?>" onchange="updateSelectedCount()">
                                    <button onclick="toggleStatus(<?php echo $loc['id']; ?>, '<?php echo $loc['status']; ?>')" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none flex-shrink-0 <?php echo $loc['status'] === 'active' ? 'bg-green-500' : 'bg-gray-300'; ?>">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?php echo $loc['status'] === 'active' ? 'translate-x-6' : 'translate-x-1'; ?>"></span>
                                    </button>
                                </div>
                            </div>
                            
                            <?php if ($loc['area']): ?><p class="text-xs text-gray-600 mb-2 flex items-center gap-1.5"><i class="fas fa-building text-gray-400 w-3 text-center"></i><?php echo htmlspecialchars($loc['area']); ?></p><?php endif; ?>
                            
                            <?php if ($loc['address']): ?><p class="text-xs text-gray-600 mb-2 flex items-center gap-1.5"><i class="fas fa-location-dot text-gray-400 w-3 text-center"></i><span class="line-clamp-1"><?php echo htmlspecialchars($loc['address']); ?></span></p><?php endif; ?>

                            <?php if ($loc['contact_phone']): ?><p class="text-xs text-gray-600 mb-2 flex items-center gap-1.5"><i class="fas fa-phone text-gray-400 w-3 text-center"></i><?php echo htmlspecialchars($loc['contact_phone']); ?></p><?php endif; ?>

                            <?php if ($loc['latitude'] && $loc['longitude']): ?>
                            <a href="https://www.google.com/maps?q=<?php echo $loc['latitude']; ?>,<?php echo $loc['longitude']; ?>" target="_blank" class="text-xs text-blue-500 hover:text-blue-700 mb-2 flex items-center gap-1.5 hover:underline">
                                <i class="fas fa-map-pin w-3 text-center"></i><?php echo floatval($loc['latitude'])->toFixed(4); ?>, <?php echo floatval($loc['longitude'])->toFixed(4); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="px-4 py-3 bg-gray-50 border-t flex justify-between items-center">
                            <div class="flex items-center gap-1.5 text-xs font-semibold <?php echo $loc['order_count'] > 0 ? 'text-green-700 bg-green-100 px-2 py-1 rounded-full' : 'text-gray-500 bg-gray-200 px-2 py-1 rounded-full'; ?>">
                                <i class="fas fa-box text-[10px]"></i> <?php echo $loc['order_count']; ?> Orders
                            </div>
                            <div class="flex items-center gap-1">
                                <button onclick="viewOrders(<?php echo $loc['id']; ?>, '<?php echo htmlspecialchars($loc['name']); ?>')" class="text-blue-500 hover:text-blue-700 bg-blue-50 p-1.5 rounded-lg" title="View Orders"><i class="fas fa-eye text-xs"></i></button>
                                <button onclick="openModal(<?php echo htmlspecialchars(json_encode($loc)); ?>)" class="text-indigo-500 hover:text-indigo-700 bg-indigo-50 p-1.5 rounded-lg" title="Edit"><i class="fas fa-pen text-xs"></i></button>
                                <button onclick="openDeleteModal(<?php echo $loc['id']; ?>, '<?php echo htmlspecialchars($loc['name']); ?>')" class="text-red-500 hover:text-red-700 bg-red-50 p-1.5 rounded-lg" title="Delete"><i class="fas fa-trash-alt text-xs"></i></button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-6 flex justify-between items-center">
                    <div class="text-sm text-gray-500">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalLocations); ?> of <?php echo $totalLocations; ?></div>
                    <div class="flex gap-1">
                        <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-1 border rounded hover:bg-white text-sm">Prev</a><?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-1 border rounded text-sm <?php echo $i === $page ? 'bg-green-600 text-white border-green-600' : 'hover:bg-white'; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-1 border rounded hover:bg-white text-sm">Next</a><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Map View -->
                <?php if ($viewMode === 'map'): ?>
                <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                    <div id="map"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Modal -->
    <div id="locModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden">
            <div class="bg-gray-50 border-b p-4 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2" id="locModalTitle">
                    <span class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center"><i class="fas fa-map-marker-alt"></i></span>
                    <span>Add Location</span>
                </h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 w-8 h-8 rounded-lg flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
            <form id="locForm" onsubmit="saveLoc(event)" class="p-5 space-y-4">
                <input type="hidden" id="formId" value="0">
                
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Location Code *</label><input type="text" id="locCode" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="e.g., TSEHAY-01"></div>
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Sort Order</label><input type="number" id="locOrder" value="0" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Name (English) *</label><input type="text" id="locName" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="e.g., Tsehay Real Estate"></div>
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Name (Amharic)</label><input type="text" id="locNameAm" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="e.g., ፀሐይ ሪል እስቴት"></div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Area</label><input type="text" id="locArea" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="e.g., Bole"></div>
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Contact Phone</label><input type="text" id="locPhone" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="0912345678"></div>
                </div>

                <div><label class="block text-xs font-semibold text-gray-500 mb-1">Full Address</label><textarea id="locAddr" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Complete address details"></textarea></div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Latitude</label><input type="text" id="locLat" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="9.0192"></div>
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Longitude</label><input type="text" id="locLng" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="38.7525"></div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Status</label><select id="locStatus" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Description</label><input type="text" id="locDesc" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Optional notes"></div>
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
            <h3 class="text-xl font-bold text-gray-800 mb-1">Delete Location?</h3>
            <p class="text-sm text-gray-500 mb-5">Are you sure you want to delete <span id="delName" class="font-bold"></span>? Linked orders will be affected.</p>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()" class="flex-1 px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-medium text-sm">Cancel</button>
                <button id="delConfirmBtn" onclick="confirmDelete()" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 font-medium text-sm">Delete</button>
            </div>
        </div>
    </div>

    <!-- Orders Modal -->
    <div id="ordersModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
            <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-center z-10">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2" id="ordersModalTitle">
                    <span class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center"><i class="fas fa-box"></i></span>
                    <span>Location Orders</span>
                </h3>
                <button onclick="closeOrdersModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 w-8 h-8 rounded-lg flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
            <div id="ordersContent" class="p-6 overflow-y-auto flex-1">
                <div class="flex justify-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>
            </div>
        </div>
    </div>

    <script>
        let selectedLocations = [];
        let map = null;
        let markers = [];

        // --- TOAST NOTIFICATION ---
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast p-4 rounded-lg shadow-lg border-l-4 ${type === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'} flex items-center gap-3`;
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span class="font-medium text-sm">${message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
        }

        // --- VIEW MODE ---
        function setViewMode(mode) {
            window.location.href = `locations.php?view=${mode}&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>`;
        }

        // --- BULK ACTIONS ---
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.location-checkbox');
            const selectAll = document.getElementById('selectAll');
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                cb.dispatchEvent(new Event('change'));
            });
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.location-checkbox:checked');
            selectedLocations = Array.from(checkboxes).map(cb => cb.dataset.id);
            document.getElementById('selectedCount').textContent = selectedLocations.length;
            
            const bulkActions = document.getElementById('bulkActions');
            bulkActions.classList.toggle('hidden', selectedLocations.length === 0);
        }

        async function bulkToggleStatus(status) {
            if (selectedLocations.length === 0) {
                showToast('Please select locations first', 'error');
                return;
            }

            try {
                const res = await fetch('locations.php?action=bulk_action', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ids: selectedLocations,
                        operation: 'toggle_status',
                        new_status: status
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (e) {
                showToast('Network error', 'error');
            }
        }

        async function bulkDelete() {
            if (selectedLocations.length === 0) {
                showToast('Please select locations first', 'error');
                return;
            }

            if (confirm(`Are you sure you want to delete ${selectedLocations.length} locations?`)) {
                try {
                    const res = await fetch('locations.php?action=bulk_action', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            ids: selectedLocations,
                            operation: 'delete'
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message);
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (e) {
                    showToast('Network error', 'error');
                }
            }
        }

        // --- STATUS TOGGLE ---
        async function toggleStatus(id, currentStatus) {
            try {
                const res = await fetch('locations.php?action=toggle_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, current_status: currentStatus })
                });
                const data = await res.json();
                if (data.success) { showToast(`Status changed to ${data.new_status}`); setTimeout(() => window.location.reload(), 800); }
                else { showToast(data.message, 'error'); }
            } catch (e) { showToast('Network error', 'error'); }
        }

        // --- MAP VIEW ---
        function initMap() {
            if (!map) {
                map = L.map('map').setView([9.0192, 38.7525], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
            }

            // Clear existing markers
            markers.forEach(marker => map.removeLayer(marker));
            markers = [];

            // Add new markers
            <?php foreach ($mapLocations as $loc): ?>
            <?php if ($loc['latitude'] && $loc['longitude']): ?>
            const marker = L.marker([<?php echo $loc['latitude']; ?>, <?php echo $loc['longitude']; ?>])
                .addTo(map)
                .bindPopup(`
                    <div class="p-2">
                        <h3 class="font-bold text-sm"><?php echo htmlspecialchars($loc['name']); ?></h3>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($loc['location_code']); ?></p>
                        <?php if ($loc['area']): ?><p class="text-xs mt-1"><i class="fas fa-building text-gray-400 mr-1"></i><?php echo htmlspecialchars($loc['area']); ?></p><?php endif; ?>
                        <p class="text-xs mt-1"><i class="fas fa-box text-gray-400 mr-1"></i><?php echo $loc['order_count']; ?> Orders</p>
                        <div class="mt-2 flex gap-1">
                            <button onclick="openModal(<?php echo htmlspecialchars(json_encode($loc)); ?>)" class="text-xs bg-indigo-500 text-white px-2 py-1 rounded">Edit</button>
                            <button onclick="viewOrders(<?php echo $loc['id']; ?>, '<?php echo addslashes($loc['name']); ?>')" class="text-xs bg-blue-500 text-white px-2 py-1 rounded">Orders</button>
                        </div>
                    </div>
                `);
            markers.push(marker);
            <?php endif; ?>
            <?php endforeach; ?>
        }

        // --- VIEW ORDERS ---
        function viewOrders(id, name) {
            const modal = document.getElementById('ordersModal');
            const content = document.getElementById('ordersContent');
            document.getElementById('ordersModalTitle').innerHTML = `<span class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center"><i class="fas fa-box"></i></span><span>${name}</span>`;
            
            modal.classList.remove('hidden'); modal.classList.add('flex');
            content.innerHTML = '<div class="flex justify-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>';
            
            fetch(`locations.php?action=get_location_orders&id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.orders.length > 0) {
                        let html = '<div class="space-y-3">';
                        data.orders.forEach(o => {
                            html += `
                            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 hover:bg-gray-100 transition">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span class="font-mono text-sm font-bold text-green-700">${o.order_number}</span>
                                        <div class="text-[10px] text-gray-500 mt-0.5">${new Date(o.created_at).toLocaleString()}</div>
                                    </div>
                                    <span class="px-2 py-1 text-[10px] rounded-full font-bold bg-blue-100 text-blue-800">${o.status}</span>
                                </div>
                                <div class="flex items-center justify-between mt-2 text-xs text-gray-600">
                                    <span><i class="fas fa-user text-gray-400 mr-1"></i> ${o.client_name}</span>
                                    <span class="text-sm font-bold text-gray-900">${parseFloat(o.total_amount).toLocaleString()} ETB</span>
                                </div>
                            </div>`;
                        });
                        html += '</div>';
                        content.innerHTML = html;
                    } else {
                        content.innerHTML = '<div class="text-center py-8 text-gray-400"><i class="fas fa-box-open text-3xl mb-2 block"></i>No orders found for this location.</div>';
                    }
                })
                .catch(err => { content.innerHTML = '<div class="text-center py-8 text-red-500">Failed to load orders.</div>'; });
        }
        
        function closeOrdersModal() { 
            document.getElementById('ordersModal').classList.add('hidden'); 
            document.getElementById('ordersModal').classList.remove('flex'); 
        }

        // --- ADD / EDIT MODAL ---
        function openModal(locData = null) {
            document.getElementById('locForm').reset();
            document.getElementById('formId').value = '0';
            
            const titleEl = document.getElementById('locModalTitle');
            if (locData) {
                titleEl.innerHTML = `<span class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center"><i class="fas fa-pen"></i></span><span>Edit Location</span>`;
                document.getElementById('formId').value = locData.id;
                document.getElementById('locCode').value = locData.location_code;
                document.getElementById('locName').value = locData.name;
                document.getElementById('locNameAm').value = locData.name_am || '';
                document.getElementById('locArea').value = locData.area || '';
                document.getElementById('locAddr').value = locData.address || '';
                document.getElementById('locPhone').value = locData.contact_phone || '';
                document.getElementById('locLat').value = locData.latitude || '';
                document.getElementById('locLng').value = locData.longitude || '';
                document.getElementById('locDesc').value = locData.description || '';
                document.getElementById('locStatus').value = locData.status;
                document.getElementById('locOrder').value = locData.display_order;
            } else {
                titleEl.innerHTML = `<span class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center"><i class="fas fa-map-marker-alt"></i></span><span>Add Location</span>`;
            }
            document.getElementById('locModal').classList.remove('hidden');
            document.getElementById('locModal').classList.add('flex');
        }
        function closeModal() { document.getElementById('locModal').classList.add('hidden'); document.getElementById('locModal').classList.remove('flex'); }

        async function saveLoc(event) {
            event.preventDefault();
            const btn = document.getElementById('saveBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...'; btn.disabled = true;

            const payload = {
                id: document.getElementById('formId').value,
                location_code: document.getElementById('locCode').value,
                name: document.getElementById('locName').value,
                name_am: document.getElementById('locNameAm').value,
                area: document.getElementById('locArea').value,
                address: document.getElementById('locAddr').value,
                contact_phone: document.getElementById('locPhone').value,
                latitude: document.getElementById('locLat').value,
                longitude: document.getElementById('locLng').value,
                description: document.getElementById('locDesc').value,
                status: document.getElementById('locStatus').value,
                display_order: document.getElementById('locOrder').value
            };

            try {
                const res = await fetch('locations.php?action=save_location', {
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
                const res = await fetch('locations.php?action=delete_location', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: pendingDeleteId })
                });
                const data = await res.json();
                if (data.success) { showToast(data.message); const card = document.getElementById(`card_${pendingDeleteId}`); if(card) card.remove(); closeDeleteModal(); setTimeout(() => window.location.reload(), 800); }
                else { showToast(data.message, 'error'); btn.innerHTML = 'Delete'; btn.disabled = false; closeDeleteModal(); }
            } catch (e) { showToast('Network error', 'error'); btn.innerHTML = 'Delete'; btn.disabled = false; }
        }

        // Initialize map when in map view
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('map')) {
                initMap();
            }
        });
    </script>
</body>
</html>