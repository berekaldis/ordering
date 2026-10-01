<?php
require_once '../config.php';
requireAdminLogin();
requirePermission('products');

// Handle GET actions (file downloads)
$getAction = $_GET['action'] ?? '';
if ($getAction === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=kaldis_products_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Product Code', 'Product Name', 'Product Name (Amharic)', 'Category', 'Variety', 'Unit', 'Unit Price', 'Walk-in Price', 'Stock Quantity', 'Shelf Life', 'Status']);
    
    $stmt = db()->query("SELECT * FROM dairy_products ORDER BY sort_order ASC, product_name ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['product_code'],
            $row['product_name'],
            $row['product_name_am'],
            $row['category'],
            $row['variety'] ?? '',
            $row['unit'],
            $row['unit_price'],
            $row['walkin_price'] ?? '',
            $row['stock_quantity'],
            ($row['shelf_life_days'] ?? '') . ' days',
            $row['status'] ? 'Active' : 'Inactive'
        ]);
    }
    fclose($output);
    exit;
} elseif ($getAction === 'download_sample_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=kaldis_products_sample.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['product_code', 'product_name', 'product_name_am', 'category', 'unit', 'unit_price', 'stock_quantity', 'description', 'description_am', 'status']);
    fputcsv($output, ['ESP-01', 'Single Espresso', 'ሲንግል ኤስፕሬሶ', 'coffee', 'cup', '65.00', '100', 'Rich and intense single shot espresso', 'በጣም ጣፋጭ የነጠላ ኤስፕሬሶ ቡና', '1']);
    fputcsv($output, ['MAC-02', 'Macchiato', 'ማኪያቶ', 'coffee', 'cup', '75.00', '100', 'Classic Ethiopian Macchiato with steamed milk foam', 'ኢትዮጵያዊ ማኪያቶ በወተት አረፋ', '1']);
    fputcsv($output, ['LAT-03', 'Cafe Latte', 'ካፌ ላቴ', 'coffee', 'cup', '85.00', '100', 'Smooth espresso with fresh steamed milk', 'ለሰስ ያለ የኤስፕሬሶ እና የወተት ውህድ', '1']);
    fputcsv($output, ['CK-01', 'Chocolate Cake Slice', 'የቾኮሌት ኬክ', 'pastry', 'piece', '120.00', '50', 'Decadent dark chocolate layer cake slice', 'በጣም ጣፋጭ የቾኮሌት ኬክ ቆራጭ', '1']);
    fclose($output);
    exit;
}

// Handle POST product operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $productCode = trim($_POST['product_code'] ?? '');
        $productName = trim($_POST['product_name'] ?? '');
        $productNameAm = trim($_POST['product_name_am'] ?? '');
        $category = trim($_POST['category'] ?? 'coffee');
        $unit = trim($_POST['unit'] ?? 'piece');
        $unitPrice = floatval($_POST['unit_price'] ?? 0);
        $stockQuantity = intval($_POST['stock_quantity'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $descriptionAm = trim($_POST['description_am'] ?? '');
        $status = intval($_POST['status'] ?? 1);
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        
        // Handle image upload
        $uploadedImage = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/products/';
            if (!file_exists($uploadDir)) @mkdir($uploadDir, 0777, true);
            
            $fileExt = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($fileExt, $allowed)) {
                $newFilename = uniqid('prod_', true) . '.' . $fileExt;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newFilename)) {
                    $uploadedImage = $newFilename;
                }
            }
        }
        
        try {
            if ($action === 'add') {
                $imgVal = $uploadedImage ?? '';
                $stmt = db()->prepare("
                    INSERT INTO dairy_products (product_code, product_name, product_name_am, category, unit, unit_price, image, description, description_am, stock_quantity, status, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$productCode, $productName, $productNameAm, $category, $unit, $unitPrice, $imgVal, $description, $descriptionAm, $stockQuantity, $status, $sortOrder]);
                $success = "Product added successfully";
                logActivity($_SESSION['admin_id'], 'ADD_PRODUCT', 'PRODUCT', db()->lastInsertId(), "Added product: $productName");
            } else {
                if ($uploadedImage !== null) {
                    $stmt = db()->prepare("
                        UPDATE dairy_products SET product_code=?, product_name=?, product_name_am=?, category=?, unit=?, unit_price=?, image=?, description=?, description_am=?, stock_quantity=?, status=?, sort_order=?
                        WHERE id=?
                    ");
                    $stmt->execute([$productCode, $productName, $productNameAm, $category, $unit, $unitPrice, $uploadedImage, $description, $descriptionAm, $stockQuantity, $status, $sortOrder, $id]);
                } else {
                    $stmt = db()->prepare("
                        UPDATE dairy_products SET product_code=?, product_name=?, product_name_am=?, category=?, unit=?, unit_price=?, description=?, description_am=?, stock_quantity=?, status=?, sort_order=?
                        WHERE id=?
                    ");
                    $stmt->execute([$productCode, $productName, $productNameAm, $category, $unit, $unitPrice, $description, $descriptionAm, $stockQuantity, $status, $sortOrder, $id]);
                }
                $success = "Product updated successfully";
                logActivity($_SESSION['admin_id'], 'EDIT_PRODUCT', 'PRODUCT', $id, "Edited product: $productName");
            }
        } catch (Exception $e) {
            $error = "Failed to save product: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = db()->prepare("DELETE FROM dairy_products WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Product deleted successfully";
            logActivity($_SESSION['admin_id'], 'DELETE_PRODUCT', 'PRODUCT', $id, "Deleted product ID: $id");
        } catch (Exception $e) {
            $error = "Failed to delete product";
        }
    } elseif ($action === 'toggle_status') {
        $id = $_POST['id'] ?? 0;
        $status = $_POST['status'] ?? 0;
        try {
            $stmt = db()->prepare("UPDATE dairy_products SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            $success = "Product status updated";
        } catch (Exception $e) {
            $error = "Failed to update status";
        }
    } elseif ($action === 'update_stock') {
        $id = $_POST['id'] ?? 0;
        $stock = $_POST['stock_quantity'] ?? 0;
        try {
            $stmt = db()->prepare("UPDATE dairy_products SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$stock, $id]);
            $success = "Stock updated successfully";
        } catch (Exception $e) {
            $error = "Failed to update stock";
        }
    } elseif ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = db()->prepare("DELETE FROM dairy_products WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $success = "Deleted " . count($ids) . " products successfully";
                logActivity($_SESSION['admin_id'], 'BULK_DELETE_PRODUCT', 'PRODUCT', implode(',', $ids), "Bulk deleted products");
            } catch (Exception $e) {
                $error = "Failed to delete products: " . $e->getMessage();
            }
        }
    } elseif ($action === 'import_products') {
        $duplicateHandling = $_POST['duplicate_handling'] ?? 'update';
        
        if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['import_file']['tmp_name'];
            $fileName = $_FILES['import_file']['name'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $importedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $rows = [];
            
            if ($ext === 'csv') {
                if (($handle = fopen($tmpPath, 'r')) !== FALSE) {
                    $bom = fread($handle, 3);
                    if ($bom !== "\xEF\xBB\xBF") {
                        rewind($handle);
                    }
                    
                    $headers = fgetcsv($handle, 1000, ',');
                    if ($headers) {
                        $headers = array_map(function($h) {
                            return strtolower(trim(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $h)));
                        }, $headers);
                        
                        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                            if (count($data) > 0) {
                                $row = [];
                                foreach ($headers as $i => $header) {
                                    $row[$header] = trim($data[$i] ?? '');
                                }
                                $rows[] = $row;
                            }
                        }
                    }
                    fclose($handle);
                }
            } elseif ($ext === 'json') {
                $jsonContent = file_get_contents($tmpPath);
                $jsonData = json_decode($jsonContent, true);
                if (is_array($jsonData)) {
                    $rows = $jsonData;
                }
            } else {
                $error = "Unsupported file format. Please upload a CSV or JSON file.";
            }
            
            if (!empty($rows)) {
                db()->beginTransaction();
                try {
                    $checkStmt = db()->prepare("SELECT id FROM dairy_products WHERE (product_code = ? AND product_code != '') OR product_name = ? LIMIT 1");
                    
                    $insertStmt = db()->prepare("
                        INSERT INTO dairy_products 
                        (product_code, product_name, product_name_am, category, unit, unit_price, stock_quantity, description, description_am, status, sort_order)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $updateStmt = db()->prepare("
                        UPDATE dairy_products 
                        SET product_name=?, product_name_am=?, category=?, unit=?, unit_price=?, stock_quantity=?, description=?, description_am=?, status=?
                        WHERE id=?
                    ");
                    
                    foreach ($rows as $row) {
                        $code = $row['product_code'] ?? $row['code'] ?? $row['product code'] ?? '';
                        $name = $row['product_name'] ?? $row['name'] ?? $row['product name'] ?? '';
                        $nameAm = $row['product_name_am'] ?? $row['product_name (amharic)'] ?? $row['amharic_name'] ?? $row['name_am'] ?? '';
                        $cat = strtolower($row['category'] ?? 'coffee');
                        $unit = $row['unit'] ?? 'cup';
                        $price = floatval($row['unit_price'] ?? $row['unit price'] ?? $row['price'] ?? 0);
                        $stock = intval($row['stock_quantity'] ?? $row['stock quantity'] ?? $row['stock'] ?? 100);
                        $desc = $row['description'] ?? '';
                        $descAm = $row['description_am'] ?? '';
                        $statusRaw = strtolower(trim((string)($row['status'] ?? '1')));
                        $status = ($statusRaw === 'active' || $statusRaw === '1' || $statusRaw === 'true') ? 1 : 0;
                        $sortOrder = intval($row['sort_order'] ?? 0);
                        
                        if (empty($name) && empty($code)) {
                            continue;
                        }
                        
                        if (empty($code)) {
                            $code = 'PROD-' . strtoupper(substr(md5($name . microtime()), 0, 6));
                        }
                        
                        $checkStmt->execute([$code, $name]);
                        $existing = $checkStmt->fetch();
                        
                        if ($existing) {
                            if ($duplicateHandling === 'skip') {
                                $skippedCount++;
                                continue;
                            } else {
                                $updateStmt->execute([
                                    $name, $nameAm, $cat, $unit, $price, $stock, $desc, $descAm, $status, $existing['id']
                                ]);
                                $updatedCount++;
                            }
                        } else {
                            $insertStmt->execute([
                                $code, $name, $nameAm, $cat, $unit, $price, $stock, $desc, $descAm, $status, $sortOrder
                            ]);
                            $importedCount++;
                        }
                    }
                    
                    db()->commit();
                    $success = "Product import complete! <b>$importedCount</b> created, <b>$updatedCount</b> updated, <b>$skippedCount</b> skipped.";
                    logActivity($_SESSION['admin_id'], 'IMPORT_PRODUCTS', 'PRODUCT', 0, "Imported products: $importedCount new, $updatedCount updated, $skippedCount skipped");
                } catch (Exception $e) {
                    db()->rollBack();
                    $error = "Failed to import products: " . $e->getMessage();
                }
            } elseif (!isset($error)) {
                $error = "No valid product rows found in the uploaded file.";
            }
        } else {
            $error = "Please select a valid CSV or JSON file to upload.";
        }
    }
}

// Get products
 $search = $_GET['search'] ?? '';
 $category = $_GET['category'] ?? '';
 $status = $_GET['status'] ?? '';
 $page = $_GET['page'] ?? 1;
 $limit = 20;
 $offset = ($page - 1) * $limit;

try {
    $query = "SELECT * FROM dairy_products WHERE 1=1";
    $params = [];
    
    if ($search) {
        $query .= " AND (product_name LIKE ? OR product_code LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam]);
    }
    
    if ($category) {
        $query .= " AND category = ?";
        $params[] = $category;
    }
    
    if ($status !== '') {
        $query .= " AND status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY sort_order ASC, product_name ASC LIMIT $limit OFFSET $offset";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM dairy_products WHERE 1=1";
    $countParams = [];
    if ($search) {
        $countQuery .= " AND (product_name LIKE ? OR product_code LIKE ?)";
        $searchParam = "%$search%";
        $countParams = array_merge($countParams, [$searchParam, $searchParam]);
    }
    if ($category) {
        $countQuery .= " AND category = ?";
        $countParams[] = $category;
    }
    if ($status !== '') {
        $countQuery .= " AND status = ?";
        $countParams[] = $status;
    }
    $countStmt = db()->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalProducts = $countStmt->fetchColumn();
    $totalPages = ceil($totalProducts / $limit);
    
    // Get statistics
    $statsStmt = db()->query("
        SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_products,
            SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_products,
            SUM(stock_quantity) as total_stock,
            SUM(CASE WHEN stock_quantity < 10 THEN 1 ELSE 0 END) as low_stock_products
        FROM dairy_products
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get categories
    $categoriesStmt = db()->query("SELECT DISTINCT category FROM dairy_products ORDER BY category");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $error = "Failed to load products";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .checkbox-custom { appearance: none; width: 1.25rem; height: 1.25rem; border: 2px solid #d1d5db; border-radius: 0.25rem; cursor: pointer; position: relative; }
        .checkbox-custom:checked { background-color: #10b981; border-color: #10b981; }
        .checkbox-custom:checked::after { content: '✓'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-weight: bold; }
        .toast { position: fixed; top: 20px; right: 20px; z-index: 9999; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .tag { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .tag-new { background-color: #dbeafe; color: #1e40af; }
        .tag-popular { background-color: #fef3c7; color: #92400e; }
        .tag-premium { background-color: #ede9fe; color: #5b21b6; }
        .tag-low-stock { background-color: #fee2e2; color: #991b1b; }
        .image-preview { max-width: 200px; max-height: 200px; object-fit: cover; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">Kaldis Menu & Products</h1>
                <div class="flex items-center gap-3">
                    <button onclick="exportProducts()" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 font-medium transition flex items-center gap-2">
                        <i class="fas fa-file-csv"></i>Export CSV
                    </button>
                    <button onclick="openImportModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition flex items-center gap-2">
                        <i class="fas fa-file-import"></i>Import Products
                    </button>
                    <button onclick="openProductModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 font-medium transition flex items-center gap-2">
                        <i class="fas fa-plus"></i>Add Product
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <?php if (isset($success)): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4 rounded">
                        <p class="text-green-700"><?php echo $success; ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Products</p>
                                <p class="text-2xl font-bold"><?php echo number_format($stats['total_products']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-box text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Active Products</p>
                                <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['active_products']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Low Stock</p>
                                <p class="text-2xl font-bold text-red-600"><?php echo number_format($stats['low_stock_products']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Stock</p>
                                <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['total_stock']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-warehouse text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-orange-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Categories</p>
                                <p class="text-2xl font-bold text-orange-600"><?php echo count($categories); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-layer-group text-orange-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <form method="GET" class="flex flex-wrap gap-4">
                        <div class="flex-1 min-w-[200px]">
                            <input type="text" name="search" placeholder="Search products..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <select name="category" class="px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="">All Status</option>
                                <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Bulk Actions -->
                <div id="bulkActions" class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6 hidden">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" id="selectAll" class="checkbox-custom" onchange="toggleSelectAll()">
                            <label for="selectAll" class="text-sm font-medium text-gray-700">Select all <span id="selectedCount">0</span> products</label>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="bulkDelete()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                                <i class="fas fa-trash mr-2"></i>Delete Selected
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Products List -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-4 py-3 text-center">
                                        <input type="checkbox" id="tableSelectAll" class="checkbox-custom" onchange="toggleTableSelectAll()">
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Code</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Category</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Price</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Stock</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($products as $product): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" class="product-checkbox checkbox-custom" data-id="<?php echo $product['id']; ?>" onchange="updateSelectedCount()">
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-start gap-3">
                                            <?php if (!empty($product['image'])): ?>
                                                <img src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="w-12 h-12 rounded-lg object-cover border border-gray-200">
                                            <?php else: ?>
                                                <div class="w-12 h-12 rounded-lg bg-amber-50 border border-amber-200/80 flex items-center justify-center text-amber-700 font-bold shrink-0">
                                                    <i class="fas fa-mug-hot text-lg"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                                <?php if ($product['product_name_am']): ?>
                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($product['product_name_am']); ?></p>
                                                <?php endif; ?>
                                                <div class="flex gap-1 mt-1">
                                                    <?php 
                                                    // Generate random tags for demonstration
                                                    $tags = [];
                                                    if (rand(1, 10) == 1) $tags[] = 'New';
                                                    if (rand(1, 10) == 1) $tags[] = 'Popular';
                                                    if (rand(1, 10) == 1) $tags[] = 'Premium';
                                                    if ($product['stock_quantity'] < 10) $tags[] = 'Low Stock';
                                                    
                                                    foreach ($tags as $tag): 
                                                        $tagClass = $tag === 'New' ? 'tag-new' : 
                                                            ($tag === 'Popular' ? 'tag-popular' : 
                                                            ($tag === 'Premium' ? 'tag-premium' : 'tag-low-stock'));
                                                    ?>
                                                    <span class="tag <?php echo $tagClass; ?>"><?php echo $tag; ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <code class="text-sm bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($product['product_code']); ?></code>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-sm"><?php echo ucfirst($product['category']); ?></span>
                                        <?php if ($product['variety']): ?>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($product['variety']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>
                                            <p class="font-bold text-green-700"><?php echo number_format($product['unit_price'], 2); ?> ETB</p>
                                            <p class="text-xs text-gray-500">per <?php echo htmlspecialchars($product['unit']); ?></p>
                                            <?php if ($product['walkin_price']): ?>
                                            <p class="text-xs text-gray-500">Walk-in: <?php echo number_format($product['walkin_price'], 2); ?> ETB</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold <?php echo $product['stock_quantity'] < 10 ? 'text-red-600' : 'text-gray-800'; ?>">
                                                <?php echo number_format($product['stock_quantity']); ?>
                                            </span>
                                            <span class="text-xs text-gray-500">units</span>
                                            <?php if ($product['shelf_life_days']): ?>
                                            <span class="text-xs text-gray-500">
                                                <i class="fas fa-clock mr-1"></i><?php echo $product['shelf_life_days']; ?> days
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" class="sr-only peer" <?php echo $product['status'] ? 'checked' : ''; ?> 
                                                   onchange="toggleStatus(<?php echo $product['id']; ?>, <?php echo $product['status'] ? '0' : '1'; ?>)">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                            <span class="ml-3 text-sm font-medium <?php echo $product['status'] ? 'text-green-600' : 'text-gray-500'; ?>">
                                                <?php echo $product['status'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </label>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                                    class="text-blue-500 hover:text-blue-700 bg-blue-50 p-2 rounded-lg" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="duplicateProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['product_name']); ?>')" 
                                                    class="text-green-500 hover:text-green-700 bg-green-50 p-2 rounded-lg" title="Duplicate">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                            <button onclick="updateStock(<?php echo $product['id']; ?>, '<?php echo addslashes($product['product_name']); ?>')" 
                                                    class="text-purple-500 hover:text-purple-700 bg-purple-50 p-2 rounded-lg" title="Update Stock">
                                                <i class="fas fa-boxes"></i>
                                            </button>
                                            <button onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['product_name']); ?>')" 
                                                    class="text-red-500 hover:text-red-700 bg-red-50 p-2 rounded-lg" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
                        <div class="text-sm text-gray-500">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalProducts); ?> of <?php echo $totalProducts; ?></div>
                        <div class="flex gap-1">
                            <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>" class="px-3 py-1 border rounded hover:bg-white text-sm">Prev</a><?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>" class="px-3 py-1 border rounded text-sm <?php echo $i === $page ? 'bg-green-600 text-white border-green-600' : 'hover:bg-white'; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>" class="px-3 py-1 border rounded hover:bg-white text-sm">Next</a><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Modal -->
    <div id="productModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-center">
                <h3 class="text-lg font-bold" id="modalTitle">Add Product</h3>
                <button onclick="closeProductModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>            <form method="POST" id="productForm" enctype="multipart/form-data" class="p-4 space-y-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="productId" value="0">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product Code</label>
                        <input type="text" name="product_code" id="productCode" required placeholder="e.g. ESP-01"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category" id="category" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option value="coffee">☕ Coffee & Hot Drinks</option>
                            <option value="cold_drinks">🧋 Cold Drinks & Juices</option>
                            <option value="pastry">🥐 Pastry & Bakery</option>
                            <option value="food">🥪 Food & Lunch Sandwiches</option>
                            <option value="cakes">🍰 Cakes & Desserts</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product Name (English)</label>
                        <input type="text" name="product_name" id="productName" required placeholder="e.g. Double Macchiato"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product Name (Amharic)</label>
                        <input type="text" name="product_name_am" id="productNameAm" placeholder="e.g. ድብል ማኪያቶ"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
                
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price (ETB)</label>
                        <input type="number" step="0.01" name="unit_price" id="unitPrice" required placeholder="120"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                        <input type="text" name="unit" id="unit" value="cup" required placeholder="cup / piece"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stock Quantity</label>
                        <input type="number" name="stock_quantity" id="stockQuantity" value="100"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product Image</label>
                    <input type="file" name="image" id="image" accept="image/*"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <div id="imagePreview" class="mt-2 hidden">
                        <img src="" alt="Preview" class="image-preview rounded-lg border max-h-36">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description (English)</label>
                        <textarea name="description" id="description" rows="2" placeholder="Rich espresso with steamed milk foam"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description (Amharic)</label>
                        <textarea name="description_am" id="descriptionAm" rows="2" placeholder="በጥራት የተዘጋጀ ትኩስ ማኪያቶ"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" id="sortOrder" value="0"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 bg-green-600 text-white py-2.5 rounded-lg font-bold hover:bg-green-700 shadow-md">
                        Save Product
                    </button>
                    <button type="button" onclick="closeProductModal()" class="flex-1 bg-gray-200 text-gray-700 py-2.5 rounded-lg font-bold hover:bg-gray-300">
                        Cancel
                    </button>
                </div>
            </form>m>
        </div>
    </div>
    
    <!-- Stock Update Modal -->
    <div id="stockModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl max-w-md w-full p-6">
            <h3 class="text-lg font-bold mb-4">Update Stock</h3>
            <form method="POST" id="stockForm">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="id" id="stockProductId" value="0">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                    <p class="font-semibold text-gray-800" id="stockProductName"></p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Stock</label>
                    <p class="font-semibold text-gray-800" id="currentStock"></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Stock Quantity</label>
                    <input type="number" name="stock_quantity" id="newStock" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">
                        Update Stock
                    </button>
                    <button type="button" onclick="closeStockModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Product Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl transition-all border border-gray-100">
            <div class="flex justify-between items-center border-b pb-4 mb-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-file-import text-blue-600"></i> Import Products
                    </h3>
                    <p class="text-xs text-gray-500 mt-1">Bulk upload products using a CSV or JSON file</p>
                </div>
                <button onclick="closeImportModal()" class="text-gray-400 hover:text-gray-600 text-xl font-bold p-1">&times;</button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="import_products">
                
                <!-- Sample Download Banner -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-3.5 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center font-bold">
                            <i class="fas fa-file-csv text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-blue-900">Need a template?</p>
                            <p class="text-[11px] text-blue-700">Download sample CSV file with pre-formatted headers</p>
                        </div>
                    </div>
                    <a href="products.php?action=download_sample_csv" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3.5 py-2 rounded-lg transition flex items-center gap-1.5 shadow-sm">
                        <i class="fas fa-download"></i> Sample CSV
                    </a>
                </div>
                
                <!-- File Upload Area -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Select CSV or JSON File <span class="text-red-500">*</span></label>
                    <div class="border-2 border-dashed border-gray-300 hover:border-blue-500 rounded-xl p-6 text-center bg-gray-50 transition cursor-pointer" onclick="document.getElementById('import_file').click()">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                        <p class="text-sm font-medium text-gray-700">Click to choose file or drag & drop</p>
                        <p class="text-xs text-gray-400 mt-1">Supported formats: .csv, .json (Max 10MB)</p>
                        <p id="fileNameDisplay" class="text-xs font-bold text-blue-600 mt-2.5 hidden"></p>
                    </div>
                    <input type="file" id="import_file" name="import_file" accept=".csv, .json, text/csv, application/json" required class="hidden" onchange="displayImportFileName(this)">
                </div>
                
                <!-- Duplicate Handling Option -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Duplicate Product Handling</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition border-blue-500 bg-blue-50/50">
                            <input type="radio" name="duplicate_handling" value="update" checked class="text-blue-600 focus:ring-blue-500">
                            <div class="ml-2.5">
                                <span class="block text-xs font-bold text-gray-800">Update Existing</span>
                                <span class="block text-[11px] text-gray-500">Update details if code/name matches</span>
                            </div>
                        </label>
                        <label class="flex items-center p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition">
                            <input type="radio" name="duplicate_handling" value="skip" class="text-blue-600 focus:ring-blue-500">
                            <div class="ml-2.5">
                                <span class="block text-xs font-bold text-gray-800">Skip Duplicates</span>
                                <span class="block text-[11px] text-gray-500">Ignore items that already exist</span>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Accepted Headers Reference -->
                <div class="bg-gray-50 rounded-xl p-3 border text-[11px] text-gray-600 space-y-1">
                    <p class="font-bold text-gray-700 mb-1"><i class="fas fa-info-circle text-blue-500"></i> Supported Headers in CSV:</p>
                    <p class="font-mono text-gray-800 bg-white p-1.5 rounded border">product_code, product_name, product_name_am, category, unit, unit_price, stock_quantity, description, description_am, status</p>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t">
                    <button type="button" onclick="closeImportModal()" class="px-4 py-2 border rounded-xl text-gray-700 hover:bg-gray-100 font-semibold text-sm">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-semibold text-sm shadow-md transition flex items-center gap-2">
                        <i class="fas fa-upload"></i> Start Import
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let selectedProducts = [];
        
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast p-4 rounded-lg shadow-lg border-l-4 ${type === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'} flex items-center gap-3`;
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span class="font-medium text-sm">${message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
        }
        
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            const selectAll = document.getElementById('selectAll');
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                cb.dispatchEvent(new Event('change'));
            });
        }
        
        function toggleTableSelectAll() {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            const selectAll = document.getElementById('tableSelectAll');
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                cb.dispatchEvent(new Event('change'));
            });
        }
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            selectedProducts = Array.from(checkboxes).map(cb => cb.dataset.id);
            document.getElementById('selectedCount').textContent = selectedProducts.length;
            
            const bulkActions = document.getElementById('bulkActions');
            bulkActions.classList.toggle('hidden', selectedProducts.length === 0);
        }
        
        function bulkDelete() {
            if (selectedProducts.length === 0) {
                showToast('Please select products first', 'error');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${selectedProducts.length} products? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="bulk_delete">
                    <input type="hidden" name="ids" value='${JSON.stringify(selectedProducts)}'>
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function toggleStatus(id, newStatus) {
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
        
        function updateStock(id, name) {
            document.getElementById('stockProductId').value = id;
            document.getElementById('stockProductName').textContent = name;
            document.getElementById('currentStock').textContent = document.querySelector(`tr:has(input[data-id="${id}"]) .stock-quantity`)?.textContent || '0';
            document.getElementById('newStock').value = '';
            document.getElementById('stockModal').classList.add('flex');
            document.getElementById('stockModal').classList.remove('hidden');
        }
        
        function closeStockModal() {
            document.getElementById('stockModal').classList.add('hidden');
            document.getElementById('stockModal').classList.remove('flex');
        }
        
        function duplicateProduct(id, name) {
            if (confirm(`Are you sure you want to duplicate "${name}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="${id}">
                    <input type="hidden" name="product_code" value="${document.getElementById('productCode').value}-copy">
                    <input type="hidden" name="product_name" value="${name} (Copy)">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function exportProducts() {
            window.location.href = 'products.php?action=export_csv';
        }
        
        function openImportModal() {
            document.getElementById('importModal').classList.add('flex');
            document.getElementById('importModal').classList.remove('hidden');
        }
        
        function closeImportModal() {
            document.getElementById('importModal').classList.add('hidden');
            document.getElementById('importModal').classList.remove('flex');
        }
        
        function displayImportFileName(input) {
            const display = document.getElementById('fileNameDisplay');
            if (input.files && input.files[0]) {
                display.textContent = '📄 Selected file: ' + input.files[0].name;
                display.classList.remove('hidden');
            } else {
                display.classList.add('hidden');
            }
        }
        
        function openProductModal() {
            document.getElementById('modalTitle').textContent = 'Add Product';
            document.getElementById('formAction').value = 'add';
            document.getElementById('productId').value = '0';
            document.getElementById('productForm').reset();
            document.getElementById('productModal').classList.add('flex');
            document.getElementById('productModal').classList.remove('hidden');
        }
        
        function editProduct(product) {
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('productId').value = product.id;
            document.getElementById('productCode').value = product.product_code || '';
            document.getElementById('productName').value = product.product_name || '';
            document.getElementById('productNameAm').value = product.product_name_am || '';
            document.getElementById('category').value = product.category || 'coffee';
            document.getElementById('unit').value = product.unit || 'cup';
            document.getElementById('unitPrice').value = product.unit_price || 0;
            document.getElementById('stockQuantity').value = product.stock_quantity || 0;
            document.getElementById('description').value = product.description || '';
            document.getElementById('descriptionAm').value = product.description_am || '';
            document.getElementById('sortOrder').value = product.sort_order || 0;
            document.getElementById('status').value = product.status !== undefined ? product.status : 1;
            
            // Show image preview if exists
            const preview = document.getElementById('imagePreview');
            if (product.image) {
                preview.classList.remove('hidden');
                preview.querySelector('img').src = product.image.indexOf('/') === 0 || product.image.indexOf('http') === 0 ? product.image : `../uploads/products/${product.image}`;
            } else {
                preview.classList.add('hidden');
                preview.querySelector('img').src = '';
            }
            
            document.getElementById('productModal').classList.add('flex');
            document.getElementById('productModal').classList.remove('hidden');
        }
        
        function closeProductModal() {
            document.getElementById('productModal').classList.add('hidden');
            document.getElementById('productModal').classList.remove('flex');
        }
        
        function deleteProduct(id, name) {
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
        
        // Image preview functionality
        document.getElementById('image')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    preview.classList.remove('hidden');
                    preview.querySelector('img').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>