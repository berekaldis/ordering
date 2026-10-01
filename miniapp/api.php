<?php

require_once __DIR__ . '/../config.php';

// ============================================================
// SESSION INITIALIZATION (once at top, before any output)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ============================================================
// FALLBACK FOR logActivity (if not defined in config.php)
// ============================================================
if (!function_exists('logActivity')) {
    function logActivity($action, $data = []) {
        $entry = date('Y-m-d H:i:s') . ' | ' . $action . ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        $logDir = __DIR__ . '/../logs/';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        @file_put_contents($logDir . 'activity.log', $entry, FILE_APPEND | LOCK_EX);
    }
}

// ============================================================
// RATE LIMITING
// ============================================================
function checkRateLimit($action, $max = 30, $window = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!isset($_SESSION['rl'])) $_SESSION['rl'] = [];
    $key = md5($ip . '_' . $action);
    $now = time();
    if (!isset($_SESSION['rl'][$key])) $_SESSION['rl'][$key] = ['c' => 0, 't' => $now];
    if ($now - $_SESSION['rl'][$key]['t'] >= $window) {
        $_SESSION['rl'][$key] = ['c' => 0, 't' => $now];
    }
    $_SESSION['rl'][$key]['c']++;
    if ($_SESSION['rl'][$key]['c'] > $max) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait.']);
        exit;
    }
}

// ============================================================
// INPUT HELPERS
// ============================================================
function getJsonInput() {
    static $input = null;
    if ($input === null) {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: [];
    }
    return $input;
}

/**
 * Basic input sanitization — trim, stripslashes, length limit.
 */
function clean($str, $max = 255) {
    if ($str === null) return '';
    $str = trim(stripslashes($str));
    return substr($str, 0, $max);
}

/**
 * HTML-escape user data for Telegram messages (parse_mode: HTML).
 */
function escHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Validate and normalize Ethiopian phone number.
 */
function cleanPhone($phone) {
    $d = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strpos($d, '251') === 0) $d = substr($d, 3);
    if (strpos($d, '0') === 0) $d = substr($d, 1);
    if (strlen($d) === 9 && preg_match('/^[79][0-9]{8}$/', $d)) {
        return ['ok' => true, 'intl' => '+251' . $d, 'local' => '0' . $d];
    }
    return ['ok' => false];
}

/**
 * Validate date string is YYYY-MM-DD and within allowed range.
 */
function validateDeliveryDate($date) {
    if (empty($date)) return ['ok' => false, 'msg' => 'Select delivery date'];
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
        return ['ok' => false, 'msg' => 'Invalid date format'];
    }
    if (!checkdate(intval($m[2]), intval($m[3]), intval($m[1]))) {
        return ['ok' => false, 'msg' => 'Invalid date'];
    }
    $today   = date('Y-m-d');
    $maxDate = date('Y-m-d', strtotime('+30 days'));
    if ($date < $today)    return ['ok' => false, 'msg' => 'Delivery date cannot be in the past'];
    if ($date > $maxDate)  return ['ok' => false, 'msg' => 'Delivery date must be within 30 days'];
    return ['ok' => true];
}

// ============================================================
// MAIN ROUTER
// ============================================================
$action = clean($_GET['action'] ?? $_POST['action'] ?? '', 50);

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

try {
    $db = db();
    switch ($action) {
        case 'get_products':
            checkRateLimit('gp', 60);
            handleGetProducts($db);
            break;
        case 'get_locations':
            checkRateLimit('gl', 60);
            handleGetLocations($db);
            break;
        case 'get_payment_methods':
            checkRateLimit('gpm', 60);
            handleGetPaymentMethods($db);
            break;
        case 'check_user':
            checkRateLimit('cu', 20, 60);
            handleCheckUser($db);
            break;
        case 'get_user_profile':
            checkRateLimit('gup', 30);
            handleGetUserProfile($db);
            break;
        case 'create_order':
            checkRateLimit('co', 5, 120);
            handleCreateOrder($db);
            break;
        case 'check_reference':
            checkRateLimit('cr', 20);
            handleCheckReference($db);
            break;
        case 'get_order_status':
            checkRateLimit('gos', 30);
            handleGetOrderStatus($db);
            break;
        case 'submit_order_rating':
            checkRateLimit('sor', 15, 60);
            handleSubmitOrderRating($db);
            break;
        case 'get_order_rating':
            checkRateLimit('gor', 30);
            handleGetOrderRating($db);
            break;
        case 'telegram_callback':
            handleTelegramCallback($db);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}

// ============================================================
// HANDLERS
// ============================================================
function handleGetProducts($db) {
    header('Cache-Control: public, max-age=60, stale-while-revalidate=120');
    $stmt = $db->query("
        SELECT id, product_name, product_name_am, unit_price, image, description, category, sort_order
        FROM dairy_products
        WHERE status = 1
        ORDER BY sort_order ASC, product_name ASC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($products as &$p) {
        $basePrice = floatval($p['unit_price']);
        $serviceCharge = $basePrice * 0.0435;
        $subtotalWithSc = $basePrice + $serviceCharge;
        $vat = $subtotalWithSc * 0.15;
        $grandTotal = round($subtotalWithSc + $vat, 2);
        
        $p['base_price']      = $basePrice;
        $p['service_charge']  = round($serviceCharge, 2);
        $p['vat']             = round($vat, 2);
        $p['grand_total']     = $grandTotal;
        $p['unit_price']      = $grandTotal;
        $p['product_name_am'] = $p['product_name_am'] ?? '';
        $p['description']     = $p['description'] ?? '';
    }
    echo json_encode(['success' => true, 'products' => $products]);
}

function handleGetLocations($db) {
    $stmt = $db->query("
        SELECT id, location_code, name, name_am, icon, latitude, longitude
        FROM delivery_locations
        WHERE status = 'active'
        ORDER BY display_order ASC, name ASC
    ");
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($locations as &$l) {
        $l['icon']      = $l['icon'] ?: '🏢';
        $l['name_am']   = $l['name_am'] ?? '';
        $l['latitude']  = $l['latitude']  ? floatval($l['latitude'])  : null;
        $l['longitude'] = $l['longitude'] ? floatval($l['longitude']) : null;
    }
    echo json_encode(['success' => true, 'locations' => $locations]);
}

function handleGetPaymentMethods($db) {
    $stmt = $db->query("
        SELECT id, name, name_am, account_name, account_number, instructions
        FROM payment_methods
        WHERE status = 1
        ORDER BY display_order ASC, name ASC
    ");
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($methods as &$m) {
        $m['name_am']      = $m['name_am'] ?? '';
        $m['instructions'] = $m['instructions'] ?? '';
    }
    echo json_encode(['success' => true, 'payment_methods' => $methods]);
}

// ============================================================
// CHECK USER — duplicate name / phone detection
// ============================================================
function handleCheckUser($db) {
    $input = getJsonInput();

    $rawName  = clean($input['name']  ?? '', 120);
    $rawPhone = clean($input['phone'] ?? '', 20);

    if (empty($rawName) || empty($rawPhone)) {
        echo json_encode(['ok' => true]);
        return;
    }

    $normName = mb_strtolower(trim(preg_replace('/\s+/', ' ', $rawName)), 'UTF-8');
    $pr = cleanPhone($rawPhone);
    if (!$pr['ok']) {
        echo json_encode(['ok' => true]);
        return;
    }
    $intlPhone = $pr['intl'];

    $stmtPhone = $db->prepare("
        SELECT client_name
        FROM pre_orders
        WHERE phone_number = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmtPhone->execute([$intlPhone]);
    $existingByPhone = $stmtPhone->fetch(PDO::FETCH_ASSOC);

    if ($existingByPhone) {
        $existingNorm = mb_strtolower(
            trim(preg_replace('/\s+/', ' ', $existingByPhone['client_name'])),
            'UTF-8'
        );
        if ($existingNorm !== $normName) {
            echo json_encode([
                'ok'            => false,
                'conflict'      => 'phone',
                'existing_name' => $existingByPhone['client_name'],
            ]);
            return;
        }
        echo json_encode(['ok' => true]);
        return;
    }

    $stmtName = $db->prepare("
        SELECT phone_number
        FROM pre_orders
        WHERE LOWER(client_name) = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmtName->execute([$normName]);
    $existingByName = $stmtName->fetch(PDO::FETCH_ASSOC);

    if ($existingByName) {
        $existingPhone = $existingByName['phone_number'];
        if ($existingPhone !== $intlPhone) {
            $masked = maskPhone($existingPhone);
            echo json_encode([
                'ok'             => false,
                'conflict'       => 'name',
                'existing_phone' => $masked,
            ]);
            return;
        }
    }

    echo json_encode(['ok' => true]);
}

function maskPhone($phone) {
    $len = strlen($phone);
    if ($len < 7) return str_repeat('*', $len);
    $visible_start = min(6, intval($len * 0.5));
    $visible_end   = 3;
    $masked_len    = $len - $visible_start - $visible_end;
    if ($masked_len < 1) return $phone;
    return substr($phone, 0, $visible_start)
         . str_repeat('*', $masked_len)
         . substr($phone, -$visible_end);
}

// ============================================================
// GET USER PROFILE (RETURNING CUSTOMERS)
// ============================================================
function handleGetUserProfile($db) {
    $chatId = clean($_GET['chat_id'] ?? $_POST['chat_id'] ?? '', 50);
    $phone  = clean($_GET['phone']   ?? $_POST['phone']   ?? '', 20);

    if (empty($chatId) && empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Chat ID or Phone required']);
        return;
    }

    $sql = "SELECT client_name, phone_number, building_name, apartment_number, floor_number, department, delivery_location_id, language 
            FROM pre_orders WHERE ";
    $params = [];
    if (!empty($chatId)) {
        $sql .= "chat_id = ? ";
        $params[] = $chatId;
    } else {
        $pr = cleanPhone($phone);
        $sql .= "phone_number = ? ";
        $params[] = $pr['intl'];
    }
    $sql .= "ORDER BY id DESC LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => true, 'found' => false]);
        return;
    }

    $parts = explode(' ', trim($user['client_name']), 2);
    $firstName = $parts[0] ?? '';
    $lastName  = $parts[1] ?? '';
    $cleanPh   = preg_replace('/[^0-9]/', '', $user['phone_number']);
    if (strlen($cleanPh) >= 9) {
        $cleanPh = substr($cleanPh, -9);
    }

    echo json_encode([
        'success' => true,
        'found'   => true,
        'profile' => [
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'full_name'         => $user['client_name'],
            'phone'             => $cleanPh,
            'building_name'     => $user['building_name'],
            'apartment_number'  => $user['apartment_number'],
            'floor_number'      => $user['floor_number'],
            'department'        => $user['department'],
            'location_id'       => $user['delivery_location_id'],
            'lang'              => $user['language']
        ]
    ]);
}

// ============================================================
// GET ORDER STATUS
// ============================================================
function handleGetOrderStatus($db) {
    $on    = clean($_GET['order_number'] ?? $_POST['order_number'] ?? '', 50);
    $ci    = clean($_GET['chat_id']      ?? $_POST['chat_id']      ?? '', 50);
    $phone = clean($_GET['phone']        ?? $_POST['phone']        ?? '', 30);

    if (empty($on)) {
        if (!empty($ci)) {
            $s = $db->prepare("SELECT order_number FROM pre_orders WHERE chat_id = ? ORDER BY created_at DESC LIMIT 1");
            $s->execute([$ci]);
            $r = $s->fetch(PDO::FETCH_ASSOC);
            if ($r) $on = $r['order_number'];
        }
        if (empty($on) && !empty($phone)) {
            $pr = cleanPhone($phone);
            if ($pr['ok']) {
                $s = $db->prepare("SELECT order_number FROM pre_orders WHERE phone_number = ? OR phone_number = ? ORDER BY created_at DESC LIMIT 1");
                $s->execute([$pr['intl'], $pr['local']]);
                $r = $s->fetch(PDO::FETCH_ASSOC);
                if ($r) $on = $r['order_number'];
            }
        }
    }

    if (empty($on)) {
        echo json_encode(['success' => false, 'message' => 'No recent order found to track. Tap Order Now to get started!']);
        return;
    }

    $labels = [
        'Pending' => '⏳ Pending Verification',
        'Confirmed' => '✅ Order Confirmed',
        'Out for Delivery' => '🛵 Out for Delivery',
        'Delivered' => '☕ Delivered',
        'Cancelled' => '❌ Cancelled',
        'Rejected' => '❌ Rejected'
    ];

    $sql  = "SELECT id, order_number, client_name, phone_number, status, rejection_reason, total_amount, delivery_address, delivery_time_slot, delivery_date, created_at FROM pre_orders WHERE order_number = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$on]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }

    // Get Items
    $itemStmt = $db->prepare("
        SELECT poi.quantity, poi.unit_price, poi.subtotal, dp.product_name
        FROM pre_order_items poi
        LEFT JOIN dairy_products dp ON poi.pre_order_product_id = dp.id
        WHERE poi.pre_order_id = ?
    ");
    $itemStmt->execute([$order['id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if rating exists
    $rateStmt = $db->prepare("SELECT service_rating, product_rating, delivery_rating, written_feedback, created_at FROM feedback WHERE order_number = ? LIMIT 1");
    $rateStmt->execute([$order['order_number']]);
    $ratingInfo = $rateStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'order'   => [
            'id'            => $order['id'],
            'order_number'  => $order['order_number'],
            'client_name'   => $order['client_name'],
            'phone_number'  => $order['phone_number'],
            'status'        => $order['status'],
            'status_label'  => $labels[$order['status']] ?? $order['status'],
            'total_amount'  => floatval($order['total_amount']),
            'delivery_address' => $order['delivery_address'],
            'delivery_time_slot' => $order['delivery_time_slot'],
            'delivery_date' => $order['delivery_date'],
            'created_at'    => $order['created_at'],
            'is_rated'      => !empty($ratingInfo),
            'rating'        => $ratingInfo ?: null,
            'items'         => $items
        ],
    ]);
}

// ============================================================
// ORDER CREATION
// ============================================================
function handleCreateOrder($db) {
    $input = getJsonInput();
    if (empty($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        return;
    }

    $chatId              = clean($input['chat_id']           ?? '', 50);
    $name                = clean($input['name']              ?? '', 120);
    $phone               = clean($input['phone']             ?? '', 20);
    $cart                = $input['cart']                    ?? [];
    $locationId          = $input['location_id']             ?? '';
    $buildingName        = clean($input['building_name']     ?? '', 150);
    $apartmentNumber     = clean($input['apartment_number']  ?? '', 50);
    $floorNumber         = clean($input['floor_number']      ?? '', 50);
    $department          = clean($input['department']        ?? '', 100);
    $deliveryDate        = clean($input['delivery_date']     ?? date('Y-m-d'), 12);
    $deliveryTimeSlot    = clean($input['delivery_time_slot']?? 'ASAP', 50);
    $notes               = clean($input['notes']             ?? '', 500);
    $paymentMethodId     = $input['payment_method_id']       ?? '';
    $paymentRef          = clean($input['payment_ref']       ?? '', 100);
    $paymentSlipBase64   = $input['payment_slip']            ?? '';
    $slipType            = clean($input['slip_type']         ?? 'image', 10);
    $lang                = clean($input['lang']              ?? 'en', 2);
    $plusCode            = clean($input['plus_code']         ?? '', 150);
    $userLat             = !empty($input['latitude'])  ? floatval($input['latitude'])  : null;
    $userLng             = !empty($input['longitude']) ? floatval($input['longitude']) : null;

    if (!empty($plusCode)) {
        if (preg_match('/(-?\d{1,2}\.\d{3,10})[\s,]+(-?\d{1,3}\.\d{3,10})/', $plusCode, $cm)) {
            if ($userLat === null) $userLat = floatval($cm[1]);
            if ($userLng === null) $userLng = floatval($cm[2]);
        } else if (preg_match('/([23456789CFGHJMPQRVWX]{4,8}\+[23456789CFGHJMPQRVWX]{2,4})/i', $plusCode, $pcm)) {
            $rawPc = strtoupper($pcm[1]);
            $prefix = (strpos($rawPc, '+') === 4) ? '6GWW' : '';
            $fullCode = substr(str_replace('+', '', $prefix . $rawPc), 0, 10);
            $alphabet = '23456789CFGHJMPQRVWX';
            $vals = [];
            for ($i = 0; $i < strlen($fullCode); $i++) {
                $pos = strpos($alphabet, $fullCode[$i]);
                if ($pos !== false) $vals[] = $pos;
            }
            if (count($vals) >= 10 && $userLat === null) {
                $userLat = floatval(number_format(($vals[0] * 20 + $vals[2]) * 1.0 - 90 + ($vals[4] / 20.0) + ($vals[6] / 400.0) + ($vals[8] / 8000.0) + (1.0 / 16000.0), 6, '.', ''));
                $userLng = floatval(number_format(($vals[1] * 20 + $vals[3]) * 1.0 - 180 + ($vals[5] / 20.0) + ($vals[7] / 400.0) + ($vals[9] / 8000.0) + (1.0 / 16000.0), 6, '.', ''));
            }
        }
    }

    if (empty($name))  { echo json_encode(['success' => false, 'message' => 'Please enter your name']);    return; }
    if (empty($cart) || !is_array($cart) || count($cart) === 0) {
        echo json_encode(['success' => false, 'message' => 'Your cart is empty']); return;
    }
    if (count($cart) > 50) { echo json_encode(['success' => false, 'message' => 'Too many items']); return; }
    if (empty($locationId))      { echo json_encode(['success' => false, 'message' => 'Select delivery location']);      return; }
    if (empty($apartmentNumber)) { echo json_encode(['success' => false, 'message' => 'Enter office/room number']); return; }

    if (empty($deliveryDate)) {
        $deliveryDate = date('Y-m-d');
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $deliveryDate)) {
        $deliveryDate = date('Y-m-d');
    }

    $pr = cleanPhone($phone);
    if (!$pr['ok']) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone. Must be a valid 9-digit Ethiopian number']); return;
    }

    $locationId = clean(strval($locationId), 20);
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $locationId)) {
        echo json_encode(['success' => false, 'message' => 'Invalid location']); return;
    }
    $locS = $db->prepare("SELECT id, name, latitude, longitude FROM delivery_locations WHERE id = ? AND status = 'active'");
    $locS->execute([$locationId]);
    $loc = $locS->fetch(PDO::FETCH_ASSOC);
    if (!$loc) { echo json_encode(['success' => false, 'message' => 'Invalid location']); return; }

    $paymentMethodId = clean(strval($paymentMethodId), 20);
    if (empty($paymentMethodId)) {
        $paymentMethodId = '4'; // Default to Pay at Office Desk
    }

    if (!empty($paymentRef)) {
        $ds = $db->prepare("SELECT 1 FROM pre_orders WHERE transaction_reference = ? LIMIT 1");
        $ds->execute([$paymentRef]);
        if ($ds->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This reference number has already been used']); return;
        }
    }

    $pids = array_unique(array_map('intval', array_column($cart, 'product_id')));
    if (empty($pids)) { echo json_encode(['success' => false, 'message' => 'Invalid cart']); return; }
    $ph = implode(',', array_fill(0, count($pids), '?'));
    $ps = $db->prepare("SELECT id, product_name, unit_price, status FROM dairy_products WHERE id IN ($ph)");
    $ps->execute(array_values($pids));
    $pmap = [];
    foreach ($ps->fetchAll(PDO::FETCH_ASSOC) as $p) { $pmap[$p['id']] = $p; }

    $total = 0; $items = []; $totalQty = 0;
    foreach ($cart as $it) {
        $pid  = intval($it['product_id'] ?? 0);
        $prod = $pmap[$pid] ?? null;
        if (!$prod) { echo json_encode(['success' => false, 'message' => 'Product not found']); return; }
        if ($prod['status'] != 1) {
            echo json_encode(['success' => false, 'message' => $prod['product_name'] . ' is unavailable']); return;
        }
        $qty = intval($it['quantity'] ?? 0);
        if ($qty <= 0 || $qty > 99) { echo json_encode(['success' => false, 'message' => 'Invalid quantity']); return; }
        $basePrice = floatval($prod['unit_price']);
        $serviceCharge = $basePrice * 0.0435;
        $subtotalWithSc = $basePrice + $serviceCharge;
        $vat = $subtotalWithSc * 0.15;
        $price = round($subtotalWithSc + $vat, 2);
        $sub   = $price * $qty;
        $total    += $sub;
        $totalQty += $qty;
        $items[] = [
            'product_id'   => $pid,
            'product_name' => $prod['product_name'],
            'quantity'     => $qty,
            'unit_price'   => $price,
            'subtotal'     => $sub,
        ];
    }
    if ($total <= 0)      { echo json_encode(['success' => false, 'message' => 'Invalid total']);              return; }
    if ($totalQty > 200)  { echo json_encode(['success' => false, 'message' => 'Total quantity exceeds limit']); return; }

    $pmS = $db->prepare("SELECT id, name FROM payment_methods WHERE id = ?");
    $pmS->execute([$paymentMethodId]);
    $pm = $pmS->fetch(PDO::FETCH_ASSOC);
    $pmName = $pm ? $pm['name'] : 'Pay at Office Desk';

    $finalBldg = !empty($buildingName) ? $buildingName : $loc['name'];
    $addrParts = [$finalBldg];
    if (!empty($floorNumber)) $addrParts[] = "Floor " . $floorNumber;
    if (!empty($apartmentNumber)) $addrParts[] = "Office/Room " . $apartmentNumber;
    if (!empty($department)) $addrParts[] = "Dept: " . $department;
    if (!empty($plusCode)) $addrParts[] = "Pin: " . $plusCode;
    $fullAddr  = implode(', ', $addrParts);
    $finalLat  = $userLat ?? ($loc['latitude']  ? floatval($loc['latitude'])  : null);
    $finalLng  = $userLng ?? ($loc['longitude'] ? floatval($loc['longitude']) : null);

    $slipFile = null;
    if ($paymentSlipBase64) {
        $slipResult = savePaymentSlip($paymentSlipBase64, $slipType);
        if ($slipResult !== null) $slipFile = $slipResult;
    }

    $orderNumber = generateOrderNumber($db);

    $db->beginTransaction();
    try {
        $os = $db->prepare("
            INSERT INTO pre_orders
                (order_number, client_name, phone_number, chat_id,
                 delivery_location_id, building_name, apartment_number, floor_number, department,
                 delivery_address, delivery_date, delivery_time_slot, status, total_amount,
                 payment_method, transaction_reference, payment_slip, notes,
                 language, latitude, longitude, refrigeration_acknowledged,
                 created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'Pending',?,?,?,?,?,?,?,?,1,NOW(),NOW())
        ");
        $os->execute([
            $orderNumber, $name, $pr['intl'], $chatId,
            $locationId, $finalBldg, $apartmentNumber, $floorNumber, $department,
            $fullAddr, $deliveryDate, $deliveryTimeSlot, $total, $pmName,
            $paymentRef, $slipFile, $notes,
            $lang, $finalLat, $finalLng,
        ]);
        $orderId = $db->lastInsertId();
        if (!$orderId) throw new Exception("Insert failed — no lastInsertId");

        $is = $db->prepare("
            INSERT INTO pre_order_items
                (pre_order_id, pre_order_product_id, quantity, unit_price, subtotal)
            VALUES (?,?,?,?,?)
        ");
        foreach ($items as $it) {
            $is->execute([$orderId, $it['product_id'], $it['quantity'], $it['unit_price'], $it['subtotal']]);
        }

        $db->commit();

    } catch (PDOException $e) {
        $db->rollBack();
        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'order_number') !== false) {
            $orderNumber = generateOrderNumber($db, true);
            $db->beginTransaction();
            try {
                $os->execute([
                    $orderNumber, $name, $pr['intl'], $chatId,
                    $locationId, $finalBldg, $apartmentNumber, $floorNumber, $department,
                    $fullAddr, $deliveryDate, $deliveryTimeSlot, $total, $pm['name'],
                    $paymentRef, $slipFile, $notes,
                    $lang, $finalLat, $finalLng,
                ]);
                $orderId = $db->lastInsertId();
                if (!$orderId) throw new Exception("Retry insert failed");
                foreach ($items as $it) {
                    $is->execute([$orderId, $it['product_id'], $it['quantity'], $it['unit_price'], $it['subtotal']]);
                }
                $db->commit();
            } catch (Exception $e2) {
                $db->rollBack();
                error_log("Order retry failed: " . $e2->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to create order. Please try again.']);
                return;
            }
        } else {
            error_log("Order failed: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to create order. Please try again.']);
            return;
        }
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Order failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create order. Please try again.']);
        return;
    }

    echo json_encode(['success' => true, 'order_number' => $orderNumber, 'order_id' => $orderId]);
    if (ob_get_level()) ob_end_flush();
    flush();
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

    $notifData = [
        'order_id'             => $orderId,
        'order_number'         => $orderNumber,
        'client_name'          => $name,
        'phone_number'         => $pr['intl'],
        'delivery_address'     => $fullAddr,
        'delivery_date'        => $deliveryDate,
        'total_amount'         => $total,
        'payment_method'       => $pm['name'],
        'transaction_reference'=> $paymentRef,
        'payment_slip'         => $slipFile,
        'chat_id'              => $chatId,
        'lang'                 => $lang,
        'total_quantity'       => $totalQty,
    ];

    logActivity('ORDER_CREATED', ['order_id' => $orderId, 'order_number' => $orderNumber, 'total' => $total]);

    try {
        sendAdminNotification($notifData, $items, $finalLat, $finalLng);
        sendCustomerNotification($notifData, $items);
    } catch (Exception $e) {
        error_log("Notification failed for order {$orderNumber}: " . $e->getMessage());
    }
}

// ============================================================
// PAYMENT SLIP SAVER
// ============================================================
function savePaymentSlip($base64, $slipType) {
    if (strlen($base64) > 14000000) return null;

    $parts = explode(";base64,", $base64);
    if (!isset($parts[1]) || empty($parts[1])) return null;

    $mime         = $parts[0] ?? '';
    $allowedMimes = [
        'data:image/jpeg', 'data:image/jpg', 'data:image/png',
        'data:image/webp', 'data:image/gif', 'data:application/pdf',
    ];
    $mimeValid = false;
    foreach ($allowedMimes as $am) {
        if (stripos($mime, $am) === 0) { $mimeValid = true; break; }
    }
    if (!$mimeValid) return null;

    $raw = base64_decode($parts[1], true);
    if ($raw === false) return null;
    if (strlen($raw) < 100 || strlen($raw) > 10485760) return null;

    if ($slipType === 'pdf') {
        if (substr($raw, 0, 4) !== '%PDF') return null;
    } else {
        $imgHeaders = ["\xFF\xD8\xFF", "\x89PNG", "GIF8", "RIFF"];
        $validImg   = false;
        foreach ($imgHeaders as $hdr) {
            if (substr($raw, 0, strlen($hdr)) === $hdr) { $validImg = true; break; }
        }
        if (!$validImg) return null;
    }

    $dir = __DIR__ . '/../uploads/slips/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!file_exists($dir . '.htaccess'))  file_put_contents($dir . '.htaccess', "Deny from all\n");
    if (!file_exists($dir . 'index.php'))  file_put_contents($dir . 'index.php', '<?php http_response_code(403);');

    $ext      = ($slipType === 'pdf') ? 'pdf' : 'jpg';
    $slipFile = 'slip_' . uniqid('', true) . '_' . date('Ymd_His') . '.' . $ext;

    if (file_put_contents($dir . $slipFile, $raw) === false) return null;
    return $slipFile;
}

// ============================================================
// REFERENCE CHECK
// ============================================================
function handleCheckReference($db) {
    $ref = clean(getJsonInput()['reference'] ?? '', 100);
    if (empty($ref)) { echo json_encode(['exists' => false]); return; }
    $s = $db->prepare("SELECT COUNT(*) FROM pre_orders WHERE transaction_reference = ?");
    $s->execute([$ref]);
    echo json_encode(['exists' => intval($s->fetchColumn()) > 0]);
}

// ============================================================
// TELEGRAM CALLBACK
// ============================================================
function handleTelegramCallback($db) {
    $input = getJsonInput();
    $cbd   = $input['callback_data'] ?? '';
    $cbid  = $input['callback_id']   ?? '';

    if (empty($cbd)) { echo json_encode(['success' => false]); return; }

    $parts = explode('_', $cbd, 2);
    if (count($parts) !== 2) { echo json_encode(['success' => false]); return; }
    $act = $parts[0];
    $oid = intval($parts[1]);
    if (!in_array($act, ['approve', 'reject']) || $oid <= 0) {
        echo json_encode(['success' => false]); return;
    }

    $s = $db->prepare("SELECT * FROM pre_orders WHERE id = ? LIMIT 1");
    $s->execute([$oid]);
    $order = $s->fetch(PDO::FETCH_ASSOC);
    if (!$order)                        { echo json_encode(['success' => false, 'message' => 'Order not found']);        return; }
    if ($order['status'] !== 'Pending') { echo json_encode(['success' => false, 'message' => 'Order already processed']); return; }

    $ns = ($act === 'approve') ? 'Confirmed' : 'Rejected';
    $db->prepare("UPDATE pre_orders SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$ns, $oid]);

    if (!empty($order['chat_id'])) {
        $settings = getTelegramSettings();
        $bt       = $settings['bot_token'] ?? null;
        if ($bt) {
            $escOn   = escHtml($order['order_number']);
            $escAddr = escHtml($order['delivery_address']);
            $escDate = escHtml($order['delivery_date']);

            if ($ns === 'Confirmed') {
                $msg = "✅ <b>Order Confirmed!</b>\n\nYour Kaldis Coffee order <code>{$escOn}</code> has been confirmed! ☕🎉\n\n"
                     . "📅 Delivery: {$escDate}\n🏢 Office: {$escAddr}\n\n"
                     . "Our runner is preparing to deliver your order to your office desk. Thank you! ☕";
            } else {
                $msg = "❌ <b>Order Update</b>\n\nYour order <code>{$escOn}</code> could not be confirmed.\n\n"
                     . "This may be due to payment verification. Please contact Kaldis ECA Support:\n"
                     . "📞 0992098459\n💬 @ECAKB\n\n"
                     . "Refund will be processed promptly if payment was made.";
            }

            sendTelegramRequest(
                "https://api.telegram.org/bot{$bt}/sendMessage",
                ['chat_id' => $order['chat_id'], 'text' => $msg, 'parse_mode' => 'HTML']
            );

            if (!empty($cbid)) {
                sendTelegramRequest(
                    "https://api.telegram.org/bot{$bt}/answerCallbackQuery",
                    ['callback_query_id' => $cbid, 'text' => "Order {$ns}!", 'show_alert' => true]
                );
            }
        }
    }

    logActivity('ORDER_' . strtoupper($ns), ['order_id' => $oid]);
    echo json_encode(['success' => true, 'message' => 'Order ' . $ns]);
}

// ============================================================
// HELPERS
// ============================================================

function generateOrderNumber($db, $forceRandom = false) {
    $maxAttempts = 3;
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        try {
            if ($forceRandom) {
                $n = rand(10000, 99999);
            } else {
                $s = $db->query("SELECT MAX(CAST(SUBSTRING(order_number, 5) AS UNSIGNED)) as mx FROM pre_orders WHERE order_number LIKE 'KLD-%'");
                $r = $s->fetch(PDO::FETCH_ASSOC);
                $n = ($r && $r['mx']) ? intval($r['mx']) + 1 : 1;
            }
            $num = 'KLD-' . str_pad($n, 5, '0', STR_PAD_LEFT);
            $chk = $db->prepare("SELECT 1 FROM pre_orders WHERE order_number = ? LIMIT 1");
            $chk->execute([$num]);
            if (!$chk->fetch()) return $num;
            $forceRandom = true;
        } catch (Exception $e) {
            $forceRandom = true;
        }
    }
    return 'KLD-' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
}

function getTelegramSettings() {
    static $s = null;
    if ($s !== null) return $s;
    try {
        $db = db();
        $r  = $db->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('bot_token','admin_chat_id')");
        $s  = [];
        foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) $s[$row['key']] = $row['value'];
        return $s;
    } catch (Exception $e) { return []; }
}

function sendAdminNotification($od, $items, $lat = null, $lng = null) {
    $s  = getTelegramSettings();
    $bt = $s['bot_token']     ?? null;
    $ac = $s['admin_chat_id'] ?? null;
    if (!$bt || !$ac) return;

    $escNum      = escHtml($od['order_number']);
    $escName     = escHtml($od['client_name']);
    $escPhone    = escHtml($od['phone_number']);
    $escChatId   = escHtml(strval($od['chat_id']));
    $escAddr     = escHtml($od['delivery_address']);
    $escDate     = escHtml($od['delivery_date']);
    $escPayMeth  = escHtml($od['payment_method']);
    $escRef      = escHtml($od['transaction_reference']);

    $itxt = ""; $tq = 0;
    foreach ($items as $i) {
        $escPName = escHtml($i['product_name']);
        $itxt    .= "• {$escPName} ×{$i['quantity']} = " . number_format($i['subtotal'], 2) . " ETB\n";
        $tq      += $i['quantity'];
    }

    $msg  = "☕ <b>New Kaldis Coffee Order (ECA Branch)!</b>\n━━━━━━━━━━━━━━\n";
    $msg .= "📋 <code>{$escNum}</code>\n";
    $msg .= "👤 {$escName}\n📞 <code>{$escPhone}</code>\n";
    if (!empty($od['chat_id'])) $msg .= "💬 Chat: <code>{$escChatId}</code>\n";
    $msg .= "━━━━━━━━━━━━━━\n☕ <b>Items ({$tq}):</b>\n{$itxt}";
    $msg .= "━━━━━━━━━━━━━━\n💰 <b>" . number_format($od['total_amount'], 2) . " ETB</b>\n🏦 {$escPayMeth}\n";
    if (!empty($od['transaction_reference'])) $msg .= "🔢 Ref: <code>{$escRef}</code>\n";
    $msg .= "━━━━━━━━━━━━━━\n🏢 <b>Office:</b> {$escAddr}\n📅 {$escDate}\n";
    if ($lat && $lng) $msg .= "🗺 <a href=\"https://www.google.com/maps?q={$lat},{$lng}\">Map</a>\n";
    $msg .= "🕐 " . date('M j, g:i A');

    $adminOrderUrl = defined('SITE_URL') ? SITE_URL . '/admin/orders.php?id=' . $od['order_id'] : 'admin/orders.php?id=' . $od['order_id'];

    sendTelegramRequest(
        "https://api.telegram.org/bot{$bt}/sendMessage",
        [
            'chat_id'      => $ac,
            'text'         => $msg,
            'parse_mode'   => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Approve', 'callback_data' => 'approve_' . $od['order_id']],
                        ['text' => '❌ Reject',  'callback_data' => 'reject_'  . $od['order_id']],
                    ],
                    [
                        ['text' => '📋 View Order', 'url' => $adminOrderUrl],
                    ],
                ],
            ]),
        ]
    );

    if ($lat && $lng) {
        sendTelegramRequest(
            "https://api.telegram.org/bot{$bt}/sendLocation",
            ['chat_id' => $ac, 'latitude' => $lat, 'longitude' => $lng]
        );
    }

    if (!empty($od['payment_slip'])) {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $surl  = $proto . '://' . $_SERVER['HTTP_HOST'] . '/uploads/slips/' . $od['payment_slip'];
        $ext   = strtolower(pathinfo($od['payment_slip'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            sendTelegramRequest(
                "https://api.telegram.org/bot{$bt}/sendPhoto",
                ['chat_id' => $ac, 'photo' => $surl, 'caption' => "🧾 Payment Slip — {$escNum}"]
            );
        } else {
            $escLink = escHtml($surl);
            sendTelegramRequest(
                "https://api.telegram.org/bot{$bt}/sendMessage",
                ['chat_id' => $ac, 'text' => "🧾 <a href=\"{$escLink}\">Download Slip</a> — {$escNum}", 'parse_mode' => 'HTML']
            );
        }
    }
}

function sendCustomerNotification($od, $items) {
    $s   = getTelegramSettings();
    $bt  = $s['bot_token'] ?? null;
    $cid = $od['chat_id']  ?? '';
    if (!$bt || empty($cid)) return;

    $escNum      = escHtml($od['order_number']);
    $escName     = escHtml($od['client_name']);
    $escPhone    = escHtml($od['phone_number']);
    $escAddr     = escHtml($od['delivery_address']);
    $escDate     = escHtml($od['delivery_date']);
    $escPayMeth  = escHtml($od['payment_method']);

    $itxt = "";
    foreach ($items as $i) {
        $escPName = escHtml($i['product_name']);
        $itxt    .= "• {$escPName} ×{$i['quantity']}\n";
    }

    $isAm = ($od['lang'] ?? 'en') === 'am';

    if ($isAm) {
        $msg  = "🎉 <b>ትዕዛዝዎ በተሳካ ሁኔታ ተመዝግቧል!</b>\n━━━━━━━━━━━━━━\n";
        $msg .= "☕ <b>ካልዲስ ቡና - ኢሲኤ ቅርንጫፍ</b>\n";
        $msg .= "📋 <code>{$escNum}</code>\n👤 {$escName}\n📞 <code>{$escPhone}</code>\n";
        $msg .= "━━━━━━━━━━━━━━\n📦 <b>ዕቃዎች፦</b>\n{$itxt}";
        $msg .= "━━━━━━━━━━━━━━\n";
        $msg .= "💰 <b>" . number_format($od['total_amount'], 2) . " ብር</b>\n";
        $msg .= "🏦 {$escPayMeth}\n🏢 {$escAddr}\n📅 {$escDate}\n";
        $msg .= "━━━━━━━━━━━━━━\n";
        $msg .= "⏳ <b>ክፍያ ከተረጋገጠ በኋላ ትኩስ ቡናዎን እና ምግቦችን ወደ ቢሮዎ እናደርሳለን።</b>\n\n";
        $msg .= "📞 0992098459 | 💬 @ECAKB\n\n";
        $msg .= "ካልዲስ ቡናን ስለመረጡ እናመሰግናለን! ☕";
    } else {
        $msg  = "🎉 <b>Your Office Order Has Been Placed Successfully!</b>\n━━━━━━━━━━━━━━\n";
        $msg .= "☕ <b>Kaldis Coffee - ECA Branch</b>\n";
        $msg .= "📋 <code>{$escNum}</code>\n👤 {$escName}\n📞 <code>{$escPhone}</code>\n";
        $msg .= "━━━━━━━━━━━━━━\n📦 <b>Items:</b>\n{$itxt}";
        $msg .= "━━━━━━━━━━━━━━\n";
        $msg .= "💰 <b>" . number_format($od['total_amount'], 2) . " ETB</b>\n";
        $msg .= "🏦 {$escPayMeth}\n🏢 {$escAddr}\n📅 {$escDate}\n";
        $msg .= "━━━━━━━━━━━━━━\n";
        $msg .= "⏳ <b>Our barista is preparing your order for office desk delivery.</b>\n\n";
        $msg .= "📞 0992098459 | 💬 @ECAKB\n\n";
        $msg .= "Thank you for choosing Kaldis Coffee! ☕";
    }

    $r    = sendTelegramRequest(
        "https://api.telegram.org/bot{$bt}/sendMessage",
        ['chat_id' => $cid, 'text' => $msg, 'parse_mode' => 'HTML']
    );
    $resp = json_decode($r, true);
    if (!$resp || !($resp['ok'] ?? false)) {
        error_log("Customer notification failed for {$od['order_number']}: " . ($resp['description'] ?? 'unknown'));
    }
}

function sendTelegramRequest($url, $data, $timeout = 10) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL             => $url,
        CURLOPT_POST            => 1,
        CURLOPT_POSTFIELDS      => $data,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_TIMEOUT         => $timeout,
        CURLOPT_CONNECTTIMEOUT  => 3,
        CURLOPT_SSL_VERIFYPEER  => true,
        CURLOPT_FOLLOWLOCATION  => false,
    ]);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("TG cURL Error: " . curl_error($ch));
    } else {
        $r = json_decode($result, true);
        if (isset($r['ok']) && !$r['ok']) {
            error_log("TG API Error: " . ($r['description'] ?? ''));
        }
    }
    curl_close($ch);
    return $result;
}

// ============================================================
// CUSTOMER ORDER RATING & FEEDBACK HANDLERS
// ============================================================
function handleSubmitOrderRating($db) {
    $input = getJsonInput();
    if (empty($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request payload']);
        return;
    }

    $orderNumber     = clean($input['order_number'] ?? '', 50);
    $serviceRating   = isset($input['service_rating']) ? intval($input['service_rating']) : null;
    $productRating   = isset($input['product_rating']) ? intval($input['product_rating']) : null;
    $deliveryRating  = isset($input['delivery_rating']) ? intval($input['delivery_rating']) : null;
    $writtenFeedback = clean($input['written_feedback'] ?? $input['comment'] ?? '', 1000);
    $chatId          = clean($input['chat_id'] ?? '', 50);

    if (empty($orderNumber)) {
        echo json_encode(['success' => false, 'message' => 'Order number is required']);
        return;
    }

    $stmt = $db->prepare("SELECT id, order_number, client_name, phone_number, chat_id FROM pre_orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }

    $clientName  = $order['client_name'];
    $phoneNumber = $order['phone_number'];
    if (empty($chatId)) {
        $chatId = $order['chat_id'] ?? '';
    }

    if ($serviceRating !== null) { $serviceRating = max(1, min(5, $serviceRating)); }
    if ($productRating !== null) { $productRating = max(1, min(5, $productRating)); }
    if ($deliveryRating !== null) { $deliveryRating = max(1, min(5, $deliveryRating)); }

    if ($serviceRating === null && $productRating === null && $deliveryRating === null) {
        echo json_encode(['success' => false, 'message' => 'Please provide at least one rating score.']);
        return;
    }

    // Check if feedback record already exists for this order
    $checkStmt = $db->prepare("SELECT id FROM feedback WHERE order_number = ? LIMIT 1");
    $checkStmt->execute([$orderNumber]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $upd = $db->prepare("
            UPDATE feedback 
            SET service_rating = ?, product_rating = ?, delivery_rating = ?, written_feedback = ?, chat_id = ?, phone_number = ?, client_name = ?
            WHERE id = ?
        ");
        $upd->execute([
            $serviceRating, $productRating, $deliveryRating, $writtenFeedback,
            $chatId, $phoneNumber, $clientName, $existing['id']
        ]);
        $feedbackId = $existing['id'];
    } else {
        $feedbackId = bin2hex(random_bytes(16));
        $ins = $db->prepare("
            INSERT INTO feedback (id, order_number, chat_id, phone_number, client_name, branch_id, service_rating, product_rating, delivery_rating, written_feedback, created_at)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, NOW())
        ");
        $ins->execute([
            $feedbackId, $orderNumber, $chatId, $phoneNumber, $clientName,
            $serviceRating, $productRating, $deliveryRating, $writtenFeedback
        ]);
    }

    // Log Activity
    try {
        $logId = bin2hex(random_bytes(16));
        $logStmt = $db->prepare("INSERT INTO activity_logs (id, action, target_type, target_id, details, chat_id, user_name, user_phone) VALUES (?, 'ORDER_RATED', 'ORDER', ?, ?, ?, ?, ?)");
        $details = json_encode([
            'order_number' => $orderNumber,
            'service_rating' => $serviceRating,
            'product_rating' => $productRating,
            'delivery_rating' => $deliveryRating,
            'comment' => $writtenFeedback
        ]);
        $logStmt->execute([$logId, $orderNumber, $details, $chatId, $clientName, $phoneNumber]);
    } catch (Exception $e) {
        error_log("Failed to log rating activity: " . $e->getMessage());
    }

    // Admin Telegram Notification
    try {
        $s = getTelegramSettings();
        $bt = $s['bot_token'] ?? null;
        $ac = $s['admin_chat_id'] ?? null;

        if ($bt && $ac) {
            $msg  = "⭐ <b>New Order Rating & Review!</b>\n━━━━━━━━━━━━━━\n";
            $msg .= "📋 <b>Order:</b> <code>" . escHtml($orderNumber) . "</code>\n";
            $msg .= "👤 <b>Customer:</b> " . escHtml($clientName) . " (" . escHtml($phoneNumber) . ")\n━━━━━━━━━━━━━━\n";
            $msg .= "🏆 <b>Service Quality:</b> " . ($serviceRating ? str_repeat("⭐", $serviceRating) . " ({$serviceRating}/5)" : "N/A") . "\n";
            $msg .= "☕ <b>Product Quality:</b> " . ($productRating ? str_repeat("⭐", $productRating) . " ({$productRating}/5)" : "N/A") . "\n";
            $msg .= "🛵 <b>Waiter Delivery & Response:</b> " . ($deliveryRating ? str_repeat("⭐", $deliveryRating) . " ({$deliveryRating}/5)" : "N/A") . "\n";
            if (!empty($writtenFeedback)) {
                $msg .= "━━━━━━━━━━━━━━\n💬 <b>Customer Comment:</b>\n<i>" . escHtml($writtenFeedback) . "</i>\n";
            }
            $msg .= "🕐 " . date('M j, g:i A');

            sendTelegramRequest("https://api.telegram.org/bot{$bt}/sendMessage", [
                'chat_id' => $ac,
                'text' => $msg,
                'parse_mode' => 'HTML'
            ]);
        }
    } catch (Exception $e) {
        error_log("Failed to send rating telegram notification: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your rating and feedback!',
        'rating' => [
            'service_rating'  => $serviceRating,
            'product_rating'  => $productRating,
            'delivery_rating' => $deliveryRating,
            'written_feedback'=> $writtenFeedback
        ]
    ]);
}

function handleGetOrderRating($db) {
    $on = clean($_GET['order_number'] ?? $_POST['order_number'] ?? '', 50);
    if (empty($on)) {
        echo json_encode(['success' => false, 'message' => 'Order number is required']);
        return;
    }

    $stmt = $db->prepare("SELECT service_rating, product_rating, delivery_rating, written_feedback, created_at FROM feedback WHERE order_number = ? LIMIT 1");
    $stmt->execute([$on]);
    $rating = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'is_rated' => !empty($rating),
        'rating' => $rating ?: null
    ]);
}