<?php
require_once '../config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Log the logout activity with additional details
if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    $adminId = $_SESSION['admin_id'];
    $adminUsername = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Unknown';
    $adminRole = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'Unknown';
    
    // Get session duration
    $loginTime = isset($_SESSION['login_time']) ? $_SESSION['login_time'] : date('Y-m-d H:i:s');
    $sessionDuration = (time() - strtotime($loginTime)) / 60; // in minutes
    
    // Log detailed logout information
    $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
    $logMessage = "Admin logged out. Session duration: " . round($sessionDuration, 2) . " minutes. Role: $adminRole. IP: $ipAddress";
    logActivity($adminId, 'LOGOUT', 'ADMIN', $adminId, $logMessage);
    
    // Store session data for audit trail
    try {
        $stmt = db()->prepare("
            INSERT INTO admin_sessions 
            (admin_id, login_time, logout_time, ip_address, user_agent, duration_minutes, browser_info)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $browserInfo = getBrowserInfo();
        $stmt->execute([
            $adminId,
            $loginTime,
            date('Y-m-d H:i:s'),
            $ipAddress,
            isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown',
            round($sessionDuration, 2),
            json_encode($browserInfo)
        ]);
    } catch (Exception $e) {
        // Log database error but don't prevent logout
        error_log("Failed to store session data: " . $e->getMessage());
    }
}

// Clear all session data
 $_SESSION = array();

// Destroy session cookie
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

// Destroy session
session_destroy();

// Clear all admin-specific cookies
 $adminCookies = [
    'admin_remember_token',
    'admin_session_id',
    'admin_auth_token',
    'admin_login_token',
    'admin_csrf_token'
];

foreach ($adminCookies as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        setcookie($cookie, '', time() - 3600, '/', '', true, true);
        unset($_COOKIE[$cookie]);
    }
}

// Clear browser cache and sensitive data
echo "<script>
    // Clear localStorage sensitive data
    if (typeof localStorage !== 'undefined') {
        localStorage.removeItem('admin_token');
        localStorage.removeItem('admin_user_data');
        localStorage.removeItem('admin_preferences');
    }
    
    // Clear sessionStorage
    if (typeof sessionStorage !== 'undefined') {
        sessionStorage.clear();
    }
    
    // Redirect to login page
    window.location.href = 'login.php?success=logout';
</script>";

exit;
?>

<?php
// Helper function to get browser information
function getBrowserInfo() {
    $browser = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
    $language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : 'Unknown';
    $encoding = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : 'Unknown';
    $connection = isset($_SERVER['HTTP_CONNECTION']) ? $_SERVER['HTTP_CONNECTION'] : 'Unknown';
    
    return [
        'browser' => $browser,
        'ip' => $ip,
        'language' => $language,
        'encoding' => $encoding,
        'connection' => $connection,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}
?>