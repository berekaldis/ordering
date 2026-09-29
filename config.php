<?php

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Session start with security settings
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters for security
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => true, // Only over HTTPS
        'httponly' => true, // Not accessible via JavaScript
        'samesite' => 'Strict' // Prevent CSRF attacks
    ]);
    session_start();
}

// Initialize session activity time if not set
if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

// ============================================================
// 1. DATABASE CONFIGURATION
// ============================================================
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'kaldis_ordering');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Production fallback credentials (for cPanel deployment)
define('PROD_DB_NAME', 'kaldisbp_ordering');
define('PROD_DB_USER', 'kaldisbp_IT');
define('PROD_DB_PASS', '@Kaldis2026!');

// ============================================================
// 2. SITE CONFIGURATION
// ============================================================
define('SITE_NAME', 'Kaldis Coffee - ECA Branch');
define('SITE_NAME_AM', 'ካልዲስ ቡና - ኢሲኤ ቅርንጫፍ');
define('BRANCH_NAME', 'ECA Branch');

// Auto-detect SITE_URL if not provided
if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptDir === '.' || $scriptDir === '/' || $scriptDir === '\\') {
        $scriptDir = '';
    }
    $rootPath = preg_replace('#/(admin|miniapp|uploads|api|includes).*$#', '', $scriptDir);
    $rootPath = rtrim($rootPath, '/.\\');
    define('SITE_URL', $protocol . $host . ($rootPath ? '/' . ltrim($rootPath, '/') : ''));
}

define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', __DIR__ . '/uploads/slips/');
define('LOGO_DIR', __DIR__ . '/uploads/logo/');
define('LOGO_URL', SITE_URL . '/uploads/logo/');

// Ensure directories exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!file_exists(LOGO_DIR)) {
    mkdir(LOGO_DIR, 0755, true);
}

// Logo file configuration
$logoFiles = ['kaldis-logo.png', 'kaldis.png', 'logo.png', 'kaldis-logo.svg', 'logo.svg', 'logokaldis.png'];
$activeLogo = '';
foreach ($logoFiles as $file) {
    if (file_exists(LOGO_DIR . $file)) {
        $activeLogo = LOGO_URL . $file;
        break;
    }
}
define('LOGO_PATH', $activeLogo ?: LOGO_URL . 'kaldis-logo.png');

// ============================================================
// 3. TELEGRAM BOT CONFIGURATION
// ============================================================
// define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE'); 
// define('ADMIN_GROUP_CHAT_ID', 'YOUR_ADMIN_CHAT_ID_HERE');

// ============================================================
// 4. TIMEZONE
// ============================================================
date_default_timezone_set('Africa/Addis_Ababa');

// ============================================================
// 5. DATABASE CONNECTION CLASS (SINGLETON)
// ============================================================
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $configs = [
            ['host' => DB_HOST, 'name' => DB_NAME, 'user' => DB_USER, 'pass' => DB_PASS],
            ['host' => '127.0.0.1', 'name' => 'kaldis_ordering', 'user' => 'root', 'pass' => ''],
            ['host' => 'localhost', 'name' => 'kaldis_ordering', 'user' => 'root', 'pass' => ''],
            ['host' => '127.0.0.1', 'name' => PROD_DB_NAME, 'user' => PROD_DB_USER, 'pass' => PROD_DB_PASS],
            ['host' => 'localhost', 'name' => PROD_DB_NAME, 'user' => PROD_DB_USER, 'pass' => PROD_DB_PASS],
        ];

        $lastException = null;
        foreach ($configs as $cfg) {
            try {
                $dsn = "mysql:host=" . $cfg['host'] . ";dbname=" . $cfg['name'] . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false,
                ];
                $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
                return;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        // Log error details for debugging
        error_log("Database connection failed: " . ($lastException ? $lastException->getMessage() : 'Unknown error'));
        die("Database connection error. Please check your database configuration or contact the administrator.");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}

// ============================================================
// 6. SESSION MANAGEMENT & AUTHENTICATION
// ============================================================

// Session timeout configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds
define('INACTIVITY_TIMEOUT', 900); // 15 minutes for inactivity warning

// Session activity tracking
function updateSessionActivity() {
    $_SESSION['last_activity'] = time();
    $_SESSION['session_start'] = $_SESSION['session_start'] ?? time();
}

// Check if session has expired
function checkSessionTimeout() {
    if (!isset($_SESSION['admin_id'])) {
        return false; // Not logged in
    }
    
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    $timeSinceActivity = time() - $lastActivity;
    
    // Check if session has expired
    if ($timeSinceActivity > SESSION_TIMEOUT) {
        // Log timeout event
        if (isset($_SESSION['admin_id'])) {
            logActivity('SESSION_TIMEOUT', [
                'timeout_after' => $timeSinceActivity,
                'session_duration' => time() - ($_SESSION['session_start'] ?? 0)
            ], 'ADMIN', $_SESSION['admin_id']);
        }
        
        // Destroy session
        session_destroy();
        return true;
    }
    
    return false;
}

// Check for inactivity warning
function checkInactivityWarning() {
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    $timeSinceActivity = time() - $lastActivity;
    
    // Show warning if approaching timeout
    return $timeSinceActivity > INACTIVITY_TIMEOUT;
}

// Regenerate session ID for security
function regenerateSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        updateSessionActivity();
    }
}

// ============================================================
// 7. HELPER FUNCTIONS
// ============================================================

function db() {
    try {
        return Database::getInstance()->getConnection();
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection error. Please try again later.");
    }
}

function generateId() {
    return bin2hex(random_bytes(16));
}

// FIXED: Prevent function redeclaration
if (!function_exists('generateOrderNumberKaldis')) {
    function generateOrderNumberKaldis() {
        $prefix = 'KLD';
        $date = date('ymd');
        
        try {
            $stmt = db()->prepare("SELECT order_number FROM pre_orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$prefix . $date . '%']);
            $last = $stmt->fetchColumn();
            
            if ($last) {
                $lastSeq = intval(substr($last, -4));
                $newSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newSeq = '0001';
            }
            return $prefix . $date . $newSeq;
        } catch (Exception $e) {
            error_log("Order number generation error: " . $e->getMessage());
            return $prefix . $date . '0001';
        }
    }
}

if (!function_exists('generateOrderNumberLoni')) {
    function generateOrderNumberLoni() {
        return generateOrderNumberKaldis();
    }
}

function getSetting($key, $default = '') {
    static $settingsCache = [];
    if (empty($settingsCache)) {
        try {
            $stmt = db()->prepare("SELECT `key`, `value` FROM settings");
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $settingsCache[$row['key']] = $row['value'];
            }
        } catch (Exception $e) {
            error_log("Error loading settings: " . $e->getMessage());
        }
    }
    return $settingsCache[$key] ?? $default;
}

function logActivity($action, $details = [], $targetType = 'APP', $targetId = 'USER') {
    try {
        $db = db();
        $id = bin2hex(random_bytes(16));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $userId = $_SERVER['HTTP_X_TELEGRAM_USER_ID'] ?? '';
        $jsonDetails = json_encode($details);
        $stmt = $db->prepare(
            "INSERT INTO activity_logs
                (id, action, target_type, target_id, details, ip_address, user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$id, $action, $targetType, $targetId, $jsonDetails, $ip, $userId ?: null]);
    } catch (Exception $e) {
        error_log("logActivity error: " . $e->getMessage());
    }
}

// File Upload Helper
function saveBase64Image($base64String, $orderId) {
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    $imageParts = explode('base64,', $base64String);
    if (count($imageParts) < 2) return null;
    
    // Detect image type from the data URI
    $mimeType = 'image/png'; // default
    $dataUri = $imageParts[0];
    if (preg_match('/data:image\/(jpeg|jpg|png|gif|webp);/', $dataUri, $matches)) {
        $mimeType = $matches[1];
    }
    
    // Convert extension to lowercase
    $extension = strtolower($mimeType);
    if ($extension === 'jpeg') $extension = 'jpg';
    
    // Validate image
    $imageData = base64_decode($imageParts[1], true);
    if ($imageData === false) {
        error_log("Invalid base64 image data for order $orderId");
        return null;
    }
    
    // Check if it's a valid image
    $imageInfo = @getimagesizefromstring($imageData);
    if ($imageInfo === false) {
        error_log("Invalid image data for order $orderId");
        return null;
    }
    
    // Check file size (max 5MB)
    if (strlen($imageData) > 5 * 1024 * 1024) {
        error_log("Image too large for order $orderId");
        return null;
    }
    
    $fileName = $orderId . '_' . time() . '.' . $extension;
    $filePath = UPLOAD_DIR . $fileName;
    
    if (file_put_contents($filePath, $imageData) === false) {
        error_log("Failed to save image file for order $orderId");
        return null;
    }
    
    // Set proper permissions
    chmod($filePath, 0644);
    
    return 'uploads/slips/' . $fileName;
}

// Logo helper function
function getLogoUrl() {
    // Check if the logo file exists in the local directory
    $logoFiles = ['kaldis-logo.png', 'kaldis.png', 'logo.png', 'kaldis-logo.svg', 'logo.svg', 'logokaldis.png'];
    foreach ($logoFiles as $file) {
        if (file_exists(LOGO_DIR . $file)) {
            return LOGO_URL . $file;
        }
    }
    return null;
}

function displayLogo($size = 'w-10 h-10', $class = '') {
    $logoUrl = getLogoUrl();
    if ($logoUrl) {
        return '<img src="' . htmlspecialchars($logoUrl) . '" alt="Kaldis Coffee Logo" class="' . $size . ' object-contain ' . $class . '">';
    }
    return '<span class="text-2xl ' . $class . '">☕</span>';
}

// ============================================================
// 8. AUTH & ADMIN HELPERS
// ============================================================

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireAdminLogin() {
    // Update session activity on every admin page access
    updateSessionActivity();
    
    // Check if session has expired
    if (checkSessionTimeout()) {
        header('Location: login.php?timeout=1');
        exit;
    }
    
    if (!isAdminLoggedIn()) {
        // Store the requested URL to redirect after login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: login.php');
        exit;
    }
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['session_regenerated']) || time() - $_SESSION['session_regenerated'] > 300) {
        regenerateSession();
        $_SESSION['session_regenerated'] = time();
    }
}

function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    // Check if admin data is already in session and not expired
    if (isset($_SESSION['admin_data']) && 
        $_SESSION['admin_data']['id'] == $_SESSION['admin_id'] && 
        (time() - $_SESSION['admin_data']['last_activity'] < 1800)) { // 30 minutes
        $_SESSION['admin_data']['last_activity'] = time();
        return $_SESSION['admin_data'];
    }

    try {
        // Auto-ensure permissions column exists
        static $permissionsColChecked = false;
        if (!$permissionsColChecked) {
            $colRes = db()->query("SHOW COLUMNS FROM admin_users LIKE 'permissions'");
            if ($colRes && !$colRes->fetch()) {
                db()->exec("ALTER TABLE admin_users ADD COLUMN permissions TEXT NULL DEFAULT NULL AFTER role");
            }
            $permissionsColChecked = true;
        }

        $stmt = db()->prepare("SELECT id, username, email, role, status, permissions FROM admin_users WHERE id = ? AND status = 1");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            $admin['last_activity'] = time();
            $_SESSION['admin_data'] = $admin;
        }
        return $admin;
    } catch (Exception $e) {
        error_log("Error fetching admin data: " . $e->getMessage());
        return null;
    }
}

function isSuperAdmin() {
    $admin = getCurrentAdmin();
    return ($admin['role'] ?? '') === 'superadmin';
}

/**
 * Check if the currently logged-in admin user has a specific permission module.
 * Super admins have full access automatically. Regular admin access is based on granted permissions.
 */
function hasPermission($permissionKey) {
    if (!isAdminLoggedIn()) {
        return false;
    }
    $admin = getCurrentAdmin();
    if (!$admin) {
        return false;
    }
    // Super admin has full access to everything
    if (($admin['role'] ?? '') === 'superadmin') {
        return true;
    }
    if (empty($admin['permissions'])) {
        return false;
    }
    $perms = is_array($admin['permissions']) ? $admin['permissions'] : (json_decode($admin['permissions'], true) ?: []);
    return in_array($permissionKey, $perms);
}

/**
 * Enforce permission requirement on admin pages.
 */
function requirePermission($permissionKey) {
    requireAdminLogin();
    if (!hasPermission($permissionKey)) {
        header('HTTP/1.1 403 Forbidden');
        if (file_exists(__DIR__ . '/403.php')) {
            include __DIR__ . '/403.php';
        } else {
            echo '<div style="font-family:sans-serif;padding:40px;text-align:center;"><h1>403 Forbidden</h1><p>You do not have permission to access this page.</p><a href="dashboard.php">Return to Dashboard</a></div>';
        }
        exit;
    }
}

function adminLogout() {
    // Log logout activity
    if (isset($_SESSION['admin_id'])) {
        $sessionDuration = time() - ($_SESSION['session_start'] ?? 0);
        logActivity('ADMIN_LOGOUT', [
            'session_duration' => $sessionDuration,
            'last_activity' => time() - ($_SESSION['last_activity'] ?? 0)
        ], 'ADMIN', $_SESSION['admin_id']);
    }
    
    // Clear all session data
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
    header('Location: login.php?logout=1');
    exit;
}

// Check session timeout (wrapper function for backward compatibility)
function checkSessionTimeoutHandler($timeout = 1800) {
    return checkSessionTimeout();
}