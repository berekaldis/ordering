<?php

require_once __DIR__ . '/config.php';

// Handle webhook set/test requests
if (isset($_GET['test'])) {
    header('Content-Type: text/plain');
    echo "Bot is running! Time: " . date('Y-m-d H:i:s');
    exit;
}

if (isset($_GET['set_webhook'])) {
    header('Content-Type: application/json');
    $token = defined('DEFAULT_BOT_TOKEN') ? DEFAULT_BOT_TOKEN : '8575284682:AAGbp6ZDaj1T2vPk1GSRrFC7rXCPbO8vyX8';
    $webhookUrl = SITE_URL . '/bot.php';
    if (strpos($webhookUrl, 'http://') === 0) {
        $webhookUrl = 'https://' . substr($webhookUrl, 7);
    }
    $url = 'https://api.telegram.org/bot' . $token . '/setWebhook';
    $postData = [
        'url' => $webhookUrl,
        'allowed_updates' => ['message', 'callback_query', 'inline_query', 'shipping_query'],
        'drop_pending_updates' => true
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    echo json_encode(['target_webhook' => $webhookUrl, 'telegram_response' => $response]);
    exit;
}

if (isset($_GET['webhook_info'])) {
    header('Content-Type: application/json');
    $token = defined('DEFAULT_BOT_TOKEN') ? DEFAULT_BOT_TOKEN : '8575284682:AAGbp6ZDaj1T2vPk1GSRrFC7rXCPbO8vyX8';
    $url = 'https://api.telegram.org/bot' . $token . '/getWebhookInfo';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10
    ]);
    $tgInfo = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $info = [
        'status' => 'active',
        'time' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'telegram_webhook' => $tgInfo['result'] ?? $tgInfo
    ];
    echo json_encode($info);
    exit;
}

if (isset($_GET['view_log'])) {
    header('Content-Type: text/plain');
    $logFile = __DIR__ . '/bot_webhook_log.txt';
    if (file_exists($logFile)) {
        echo file_get_contents($logFile);
    } else {
        echo "No log file found yet.";
    }
    exit;
}

function syncBotMenuButton($targetUrl = null) {
    static $synced = false;
    if ($synced) return;
    $synced = true;
    
    $urlToUse = $targetUrl ?: (defined('MINI_APP_URL') ? MINI_APP_URL : 'https://ordering.kaldisbunnaet.com/eca/miniapp/app.html');
    if (strpos($urlToUse, 'http://') === 0) {
        $urlToUse = 'https://' . substr($urlToUse, 7);
    }
    
    $token = defined('BOT_TOKEN') ? BOT_TOKEN : (defined('DEFAULT_BOT_TOKEN') ? DEFAULT_BOT_TOKEN : '8575284682:AAGbp6ZDaj1T2vPk1GSRrFC7rXCPbO8vyX8');
    $apiUrl = 'https://api.telegram.org/bot' . $token . '/setChatMenuButton';
    
    $payload = json_encode([
        'menu_button' => [
            'type' => 'web_app',
            'text' => '☕ Order Now',
            'web_app' => [
                'url' => $urlToUse
            ]
        ]
    ]);
    
    try {
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        curl_exec($ch);
        curl_close($ch);
    } catch (Exception $e) {}
}

function loadBotSettings() {
    $fallbackToken = defined('DEFAULT_BOT_TOKEN') ? DEFAULT_BOT_TOKEN : '8575284682:AAGbp6ZDaj1T2vPk1GSRrFC7rXCPbO8vyX8';
    $defaultMiniAppUrl = defined('DEFAULT_MINI_APP_URL') ? DEFAULT_MINI_APP_URL : (SITE_URL . '/miniapp/app.html');
    $defaults = [
        'bot_token' => $fallbackToken,
        'admin_chat_id' => '',
        'mini_app_url' => $defaultMiniAppUrl,
        'support_phone' => '0992098459',
        'extension_phone' => '0115444437',
        'extension_short' => '34437',
        'telegram_channel' => 'https://t.me/ECAKB',
        'maintenance_mode' => 'false',
        'auto_respond' => 'true',
        'notification_enabled' => 'true',
        'max_orders_per_user' => '50',
        'allow_cancellations' => 'true',
        'cancellation_window' => '24',
        'enable_complaints' => 'true',
        'enable_subscriptions' => 'true',
        'feedback_photos' => 'true'
    ];
    
    try {
        $keys = array_keys($defaults);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $sql = "SELECT `key`, `value` FROM settings WHERE `key` IN ($placeholders)";
        $stmt = db()->prepare($sql);
        $stmt->execute($keys);
        
        foreach ($stmt->fetchAll() as $row) {
            if (isset($defaults[$row['key']]) && !empty($row['value'])) {
                $defaults[$row['key']] = $row['value'];
            }
        }
        
        // Auto-fix outdated or non-HTTPS URLs
        if (strpos($defaults['mini_app_url'], 'loniagro') !== false || strpos($defaults['mini_app_url'], 'localhost') !== false || strpos($defaults['mini_app_url'], 'http://') === 0) {
            $defaults['mini_app_url'] = 'https://ordering.kaldisbunnaet.com/eca/miniapp/app.html';
            try {
                $upStmt = db()->prepare("UPDATE settings SET `value` = ? WHERE `key` = 'mini_app_url'");
                $upStmt->execute([$defaults['mini_app_url']]);
            } catch (Exception $e) {}
        }
    } catch (Exception $e) {
        error_log("Failed to load bot settings: " . $e->getMessage());
    }
    
    return $defaults;
}

 $settings = loadBotSettings();

$botToken = !empty($settings['bot_token']) ? $settings['bot_token'] : (defined('DEFAULT_BOT_TOKEN') ? DEFAULT_BOT_TOKEN : '8575284682:AAGbp6ZDaj1T2vPk1GSRrFC7rXCPbO8vyX8');
define('BOT_TOKEN', $botToken);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN);

$miniAppUrl = $settings['mini_app_url'] ?: 'https://ordering.kaldisbunnaet.com/eca/miniapp/app.html';
if (strpos($miniAppUrl, 'http://') === 0) {
    $miniAppUrl = 'https://' . substr($miniAppUrl, 7);
}
define('MINI_APP_URL', $miniAppUrl);

define('SUPPORT_PHONE', $settings['support_phone']);
define('EXTENSION_PHONE', $settings['extension_phone'] ?? '0115444437');
define('EXTENSION_SHORT', $settings['extension_short'] ?? '34437');
define('TELEGRAM_CHANNEL', $settings['telegram_channel']);
define('ADMIN_CHAT_ID', $settings['admin_chat_id']);
define('MAINTENANCE_MODE', filter_var($settings['maintenance_mode'], FILTER_VALIDATE_BOOLEAN));
define('AUTO_RESPOND', filter_var($settings['auto_respond'], FILTER_VALIDATE_BOOLEAN));
define('NOTIFICATION_ENABLED', filter_var($settings['notification_enabled'], FILTER_VALIDATE_BOOLEAN));
define('MAX_ORDERS_PER_USER', (int)$settings['max_orders_per_user']);
define('ALLOW_CANCELLATIONS', filter_var($settings['allow_cancellations'], FILTER_VALIDATE_BOOLEAN));
define('CANCELLATION_WINDOW', (int)$settings['cancellation_window']);
define('ENABLE_COMPLAINTS', filter_var($settings['enable_complaints'], FILTER_VALIDATE_BOOLEAN));
define('ENABLE_SUBSCRIPTIONS', filter_var($settings['enable_subscriptions'], FILTER_VALIDATE_BOOLEAN));
define('FEEDBACK_PHOTOS', filter_var($settings['feedback_photos'], FILTER_VALIDATE_BOOLEAN));

// ============================================================
// ACTIVITY LOGGING (with runtime column check)
// ============================================================
function logBotActivity($chatId, $action, $details = []) {
    try {
        $id = bin2hex(random_bytes(8));
        $json = json_encode($details);
        $stmt = db()->prepare("
            INSERT INTO activity_logs 
            (id, action, entity_type, entity_id, details, ip_address, chat_id, language, created_at) 
            VALUES (?, ?, 'TELEGRAM', ?, ?, 'BOT', ?, 'en', NOW())
        ");
        $stmt->execute([$id, $action, (string)$chatId, $json, (string)$chatId]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        // bot continues
    }
}

// ============================================================
// HELPER FUNCTIONS (with performance improvements)
// ============================================================
function apiRequest($method, $params) {
    static $ch = null;
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_ENCODING => 'gzip, deflate'
        ]);
    }
    curl_setopt($ch, CURLOPT_URL, API_URL . '/' . $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    $res = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('Curl error: ' . curl_error($ch));
        return ['ok' => false, 'error' => curl_error($ch)];
    }
    return json_decode($res, true);
}

function sendMessage($chatId, $text, $keyboard = null) {
    if (empty($chatId)) {
        return ['ok' => false, 'description' => 'Missing chat_id'];
    }
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    if ($keyboard) $params['reply_markup'] = $keyboard;
    
    $result = apiRequest("sendMessage", $params);
    
    if (isset($result['ok']) && !$result['ok']) {
        $desc = $result['description'] ?? '';
        @file_put_contents(__DIR__ . '/bot_webhook_log.txt', date('[Y-m-d H:i:s] ') . "Telegram API Error: " . json_encode($result) . "\n", FILE_APPEND);

        // Fallback 1: If Telegram rejected web_app button (e.g. non-HTTPS), convert web_app to standard url button
        if ($keyboard && isset($keyboard['inline_keyboard']) && (strpos($desc, 'web app') !== false || strpos($desc, 'URL') !== false || strpos($desc, 'url') !== false)) {
            $fallbackKeyboard = $keyboard;
            foreach ($fallbackKeyboard['inline_keyboard'] as &$row) {
                foreach ($row as &$btn) {
                    if (isset($btn['web_app'])) {
                        $waUrl = $btn['web_app']['url'] ?? '';
                        unset($btn['web_app']);
                        $btn['url'] = $waUrl;
                    }
                }
            }
            $params['reply_markup'] = $fallbackKeyboard;
            $result = apiRequest("sendMessage", $params);
        }
        
        // Fallback 2: If HTML parse error occurred, strip HTML tags and retry without parse_mode
        if (isset($result['ok']) && !$result['ok'] && (strpos($desc, 'parse') !== false || strpos($desc, 'entity') !== false)) {
            unset($params['parse_mode']);
            $params['text'] = strip_tags($text);
            $result = apiRequest("sendMessage", $params);
        }
    }
    
    return $result;
}


/**
 * Ensure tables exist and have all required columns.
 */
function ensureBotTablesExist() {
    static $checked = false;
    if ($checked) return;
    $flagFile = __DIR__ . '/.bot_tables_ok';
    if (file_exists($flagFile)) {
        $checked = true;
        return;
    }

    try {
        db()->exec("CREATE TABLE IF NOT EXISTS customer_states (
            chat_id BIGINT PRIMARY KEY, 
            state VARCHAR(50), 
            temp_data JSON NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        db()->exec("CREATE TABLE IF NOT EXISTS feedback (
            id VARCHAR(32) PRIMARY KEY,
            chat_id VARCHAR(20) NOT NULL,
            branch_id INT DEFAULT NULL,
            delivery_rating TINYINT,
            product_rating TINYINT,
            service_rating TINYINT,
            written_feedback TEXT,
            complaint_type VARCHAR(50),
            complaint_description TEXT,
            complaint_status VARCHAR(20) DEFAULT 'open',
            complaint_photo VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_chat_id (chat_id),
            INDEX idx_created_at (created_at)
        )");
        
        db()->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id VARCHAR(32) PRIMARY KEY,
            user_id INT DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            target_type VARCHAR(50) DEFAULT NULL,
            target_id VARCHAR(50) DEFAULT NULL,
            details TEXT,
            ip_address VARCHAR(45) DEFAULT NULL,
            chat_id VARCHAR(50) DEFAULT NULL,
            step_number INT DEFAULT NULL,
            user_name VARCHAR(255) DEFAULT NULL,
            user_phone VARCHAR(50) DEFAULT NULL,
            language VARCHAR(5) DEFAULT 'en',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            entity_type VARCHAR(50) NOT NULL DEFAULT 'TELEGRAM',
            entity_id VARCHAR(50) NOT NULL DEFAULT '',
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at),
            INDEX idx_chat_id (chat_id)
        )");
        
        db()->exec("CREATE TABLE IF NOT EXISTS subscriptions (
            id VARCHAR(32) PRIMARY KEY,
            chat_id VARCHAR(20) NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (chat_id, notification_type)
        )");
        
        db()->exec("CREATE TABLE IF NOT EXISTS telegram_users (
            id VARCHAR(32) PRIMARY KEY,
            chat_id VARCHAR(50) NOT NULL UNIQUE,
            username VARCHAR(100) DEFAULT NULL,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            phone_number VARCHAR(50) DEFAULT NULL,
            language VARCHAR(5) DEFAULT 'en',
            state VARCHAR(50) DEFAULT 'idle',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_chat_id (chat_id),
            INDEX idx_username (username)
        )");
    } catch (Exception $e) {
        error_log("Table creation error: " . $e->getMessage());
    }

    
    // --- ALWAYS CHECK AND FIX MISSING COLUMNS ---
    try {
        $requiredColumns = [
            'entity_type' => "VARCHAR(50) NOT NULL DEFAULT 'TELEGRAM'",
            'entity_id'   => "VARCHAR(50) NOT NULL DEFAULT ''",
            'ip_address'  => "VARCHAR(45) DEFAULT NULL",
            'details'     => "TEXT NULL"
        ];
        
        $existingColumns = [];
        $res = db()->query("SHOW COLUMNS FROM activity_logs");
        if ($res) {
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                $existingColumns[] = $row['Field'];
            }
        }
        
        $columnsAdded = false;
        foreach ($requiredColumns as $col => $def) {
            if (!in_array($col, $existingColumns)) {
                db()->exec("ALTER TABLE activity_logs ADD COLUMN $col $def");
                error_log("Added missing column '$col' to activity_logs");
                $columnsAdded = true;
            }
        }
        
        // Add complaint columns to feedback table if missing
        $feedbackColumns = [];
        $res = db()->query("SHOW COLUMNS FROM feedback");
        if ($res) {
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                $feedbackColumns[] = $row['Field'];
            }
        }
        
        $complaintColumns = [
            'complaint_type' => "VARCHAR(50) DEFAULT NULL",
            'complaint_description' => "TEXT DEFAULT NULL",
            'complaint_status' => "VARCHAR(20) DEFAULT 'open'",
            'complaint_photo' => "VARCHAR(255) DEFAULT NULL"
        ];
        
        // Add rejection_reason column to pre_orders if missing
        $orderCols = [];
        $res = db()->query("SHOW COLUMNS FROM pre_orders");
        if ($res) {
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                $orderCols[] = $row['Field'];
            }
        }
        if (!in_array('rejection_reason', $orderCols)) {
            db()->exec("ALTER TABLE pre_orders ADD COLUMN rejection_reason VARCHAR(255) DEFAULT NULL");
            $columnsAdded = true;
        }

        if ($columnsAdded) {
            error_log("Table structure updated successfully.");
        }
    } catch (Exception $e) {
        error_log("Failed to check/add columns: " . $e->getMessage());
    }
    @file_put_contents($flagFile, '1');
    $checked = true;
}

function saveTelegramUser($user) {
    try {
        $id = bin2hex(random_bytes(16));
        $chatId = (string)($user['id'] ?? 0);
        $username = $user['username'] ?? null;
        $firstName = $user['first_name'] ?? null;
        $lastName = $user['last_name'] ?? null;
        
        $stmt = db()->prepare("
            INSERT INTO telegram_users (id, chat_id, username, first_name, last_name, language, state, created_at) 
            VALUES (?, ?, ?, ?, ?, 'en', 'idle', NOW())
            ON DUPLICATE KEY UPDATE 
            username = VALUES(username), 
            first_name = VALUES(first_name), 
            last_name = VALUES(last_name)
        ");
        $stmt->execute([$id, $chatId, $username, $firstName, $lastName]);
    } catch (Exception $e) {
        error_log("Save Telegram User Error: " . $e->getMessage());
    }
}

function getUserState($chatId) {
    try {
        $stmt = db()->prepare("SELECT state, temp_data FROM customer_states WHERE chat_id = ? LIMIT 1");
        $stmt->execute([$chatId]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'state' => $row['state'],
                'temp_data' => json_decode($row['temp_data'] ?? '{}', true)
            ];
        }
    } catch (Exception $e) {
        error_log("Get state error: " . $e->getMessage());
    }
    return ['state' => null, 'temp_data' => []];
}

function setUserState($chatId, $state, $tempData = []) {
    try {
        $json = json_encode($tempData);
        $stmt = db()->prepare("INSERT INTO customer_states (chat_id, state, temp_data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE state = ?, temp_data = ?");
        $stmt->execute([$chatId, $state, $json, $state, $json]);
    } catch (Exception $e) {
        error_log("Set state error: " . $e->getMessage());
    }
}

function clearUserState($chatId) {
    try {
        $stmt = db()->prepare("DELETE FROM customer_states WHERE chat_id = ?");
        $stmt->execute([$chatId]);
    } catch (Exception $e) {
        error_log("Clear state error: " . $e->getMessage());
    }
}

function getStarDisplay($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= $i <= $rating ? '⭐' : '☆';
    }
    return $stars;
}

// ============================================================
// FEEDBACK SYSTEM (Web-App Aligned Rating & Complaints)
// ============================================================

function showFeedbackMenu($chatId) {
    $message = "<b>📝 Feedback & Support</b>\n\n" .
               "How can we help you today?\n\n" .
               "Please select an option:";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '⭐ Rate Experience', 'callback_data' => 'rate_service'],
                ['text' => '📝 File Complaint', 'callback_data' => 'file_complaint']
            ],
            [
                ['text' => '📋 My Reviews', 'callback_data' => 'my_feedback'],
                ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function startServiceRating($chatId) {
    setUserState($chatId, 'awaiting_service_rating', []);
    
    $message = "<b>⭐ Rate Your Experience</b>\n\n" .
               "🏆 <b>Step 1 of 3: Service Quality</b>\n" .
               "How would you rate our staff & customer service experience?";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '1 ⭐', 'callback_data' => 'rate_service_1'],
                ['text' => '2 ⭐⭐', 'callback_data' => 'rate_service_2'],
                ['text' => '3 ⭐⭐⭐', 'callback_data' => 'rate_service_3']
            ],
            [
                ['text' => '4 ⭐⭐⭐⭐', 'callback_data' => 'rate_service_4'],
                ['text' => '5 ⭐⭐⭐⭐⭐', 'callback_data' => 'rate_service_5']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function handleServiceRatingStep($chatId, $rating) {
    setUserState($chatId, 'awaiting_product_rating', ['service_rating' => $rating]);
    logBotActivity($chatId, 'BOT_SERVICE_RATING', ['rating' => $rating]);
    
    $message = "<b>⭐ Rate Your Experience</b>\n\n" .
               "☕ <b>Step 2 of 3: Product Quality</b>\n" .
               "How would you rate coffee taste, food freshness & item quality?";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '1 ⭐', 'callback_data' => 'rate_product_1'],
                ['text' => '2 ⭐⭐', 'callback_data' => 'rate_product_2'],
                ['text' => '3 ⭐⭐⭐', 'callback_data' => 'rate_product_3']
            ],
            [
                ['text' => '4 ⭐⭐⭐⭐', 'callback_data' => 'rate_product_4'],
                ['text' => '5 ⭐⭐⭐⭐⭐', 'callback_data' => 'rate_product_5']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function handleProductRatingStep($chatId, $rating) {
    $state = getUserState($chatId);
    $tempData = $state['temp_data'] ?? [];
    $tempData['product_rating'] = $rating;
    
    setUserState($chatId, 'awaiting_delivery_rating', $tempData);
    logBotActivity($chatId, 'BOT_PRODUCT_RATING', ['rating' => $rating]);
    
    $message = "<b>⭐ Rate Your Experience</b>\n\n" .
               "🛵 <b>Step 3 of 3: Waiter Delivery & Response</b>\n" .
               "How would you rate our office desk delivery speed & waiter response?";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '1 ⭐', 'callback_data' => 'rate_delivery_1'],
                ['text' => '2 ⭐⭐', 'callback_data' => 'rate_delivery_2'],
                ['text' => '3 ⭐⭐⭐', 'callback_data' => 'rate_delivery_3']
            ],
            [
                ['text' => '4 ⭐⭐⭐⭐', 'callback_data' => 'rate_delivery_4'],
                ['text' => '5 ⭐⭐⭐⭐⭐', 'callback_data' => 'rate_delivery_5']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function handleDeliveryRatingStep($chatId, $rating) {
    $state = getUserState($chatId);
    $tempData = $state['temp_data'] ?? [];
    $tempData['delivery_rating'] = $rating;
    
    setUserState($chatId, 'awaiting_service_details', $tempData);
    logBotActivity($chatId, 'BOT_DELIVERY_RATING', ['rating' => $rating]);
    
    $message = "<b>💬 Customer Comment (Optional)</b>\n\n" .
               "Please type any detailed comments or suggestions below to help us improve, or tap <b>Skip</b>:";
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '⏭️ Skip', 'callback_data' => 'skip_service_feedback']]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function handleServiceFeedback($chatId, $text) {
    $state = getUserState($chatId);
    $tempData = $state['temp_data'];
    $tempData['service_feedback'] = $text;
    
    saveServiceFeedback($chatId, $tempData);
}

function saveServiceFeedback($chatId, $data) {
    try {
        $id = bin2hex(random_bytes(16));
        
        // Fetch recent order details for client name & phone if available
        $stmtOrder = db()->prepare("
            SELECT order_number, client_name, phone_number 
            FROM pre_orders 
            WHERE chat_id = ? 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmtOrder->execute([(string)$chatId]);
        $recentOrder = $stmtOrder->fetch();
        
        // Fetch Telegram User Name
        $stmtTg = db()->prepare("SELECT first_name, last_name, phone_number FROM telegram_users WHERE chat_id = ? LIMIT 1");
        $stmtTg->execute([(string)$chatId]);
        $tgUser = $stmtTg->fetch();
        
        $orderNumber = $recentOrder['order_number'] ?? null;
        $clientName  = $recentOrder['client_name'] ?? (trim(($tgUser['first_name'] ?? '') . ' ' . ($tgUser['last_name'] ?? '')) ?: 'Valued Customer');
        $phoneNumber = $recentOrder['phone_number'] ?? ($tgUser['phone_number'] ?? '');
        
        if (!empty($orderNumber)) {
            $checkStmt = db()->prepare("SELECT id FROM feedback WHERE order_number = ? LIMIT 1");
            $checkStmt->execute([$orderNumber]);
            if ($checkStmt->fetch()) {
                sendMessage($chatId, "⚠️ You have already submitted feedback for Order #" . $orderNumber . ". Thank you!");
                return;
            }
        }

        $stmt = db()->prepare("
            INSERT INTO feedback (id, order_number, chat_id, phone_number, client_name, branch_id, service_rating, product_rating, delivery_rating, written_feedback, created_at)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $id,
            $orderNumber,
            (string)$chatId,
            $phoneNumber,
            $clientName,
            $data['service_rating'] ?? null,
            $data['product_rating'] ?? null,
            $data['delivery_rating'] ?? null,
            $data['service_feedback'] ?? null
        ]);
        
        // Admin Telegram Notification matching webapp format
        if (!empty(ADMIN_CHAT_ID)) {
            $serviceRating  = $data['service_rating'] ?? null;
            $productRating  = $data['product_rating'] ?? null;
            $deliveryRating = $data['delivery_rating'] ?? null;
            $writtenFeedback = $data['service_feedback'] ?? null;
            
            $msg  = "⭐ <b>New Order Rating & Review!</b>\n━━━━━━━━━━━━━━\n";
            if ($orderNumber) {
                $msg .= "📋 <b>Order:</b> <code>" . htmlspecialchars($orderNumber) . "</code>\n";
            }
            $msg .= "👤 <b>Customer:</b> " . htmlspecialchars($clientName) . ($phoneNumber ? " (" . htmlspecialchars($phoneNumber) . ")" : "") . "\n━━━━━━━━━━━━━━\n";
            $msg .= "🏆 <b>Service Quality:</b> " . ($serviceRating ? str_repeat("⭐", $serviceRating) . " ({$serviceRating}/5)" : "N/A") . "\n";
            $msg .= "☕ <b>Product Quality:</b> " . ($productRating ? str_repeat("⭐", $productRating) . " ({$productRating}/5)" : "N/A") . "\n";
            $msg .= "🛵 <b>Waiter Delivery & Response:</b> " . ($deliveryRating ? str_repeat("⭐", $deliveryRating) . " ({$deliveryRating}/5)" : "N/A") . "\n";
            if (!empty($writtenFeedback)) {
                $msg .= "━━━━━━━━━━━━━━\n💬 <b>Customer Comment:</b>\n<i>" . htmlspecialchars($writtenFeedback) . "</i>\n";
            }
            $msg .= "🕐 " . date('M j, g:i A');
            
            sendMessage(ADMIN_CHAT_ID, $msg);
        }
        
        clearUserState($chatId);
        
        $message = "<b>⭐ Thank You for Your Feedback!</b>\n\n" .
                   "We truly appreciate you taking the time to rate your experience.\n\n" .
                   "Your feedback helps us improve our coffee, food, and office delivery service.\n\n" .
                   "— Kaldis Coffee ECA Branch Team ☕";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]
            ]
        ];
        
        sendMessage($chatId, $message, $keyboard);
        
    } catch (Exception $e) {
        error_log("Save service feedback error: " . $e->getMessage());
        sendMessage($chatId, "Error saving feedback. Please try again later.");
    }
}

function startComplaint($chatId) {
    setUserState($chatId, 'awaiting_complaint_type');
    
    $message = "<b>📝 File a Complaint</b>\n\n" .
               "Please select the type of complaint:";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🚚 Delivery Issue', 'callback_data' => 'complaint_delivery'],
                ['text' => '☕ Product Quality', 'callback_data' => 'complaint_product']
            ],
            [
                ['text' => '📱 Technical Issue', 'callback_data' => 'complaint_technical'],
                ['text' => '🔄 Order Cancellation', 'callback_data' => 'complaint_cancellation']
            ],
            [
                ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function handleComplaintType($chatId, $complaintType) {
    $state = getUserState($chatId);
    $tempData = $state['temp_data'];
    $tempData['complaint_type'] = $complaintType;
    
    setUserState($chatId, 'awaiting_complaint_details', $tempData);
    
    $typeNames = [
        'delivery' => 'Delivery Issue',
        'product' => 'Product Quality',
        'payment' => 'Payment Problem',
        'technical' => 'Technical Issue',
        'cancellation' => 'Order Cancellation'
    ];
    
    $message = "<b>📝 File a Complaint</b>\n\n" .
               "Complaint Type: " . $typeNames[$complaintType] . "\n\n" .
               "Please describe your complaint in detail:\n" .
               "• What happened?\n" .
               "• When did it happen?\n" .
               "• What is your expected resolution?\n\n" .
               "You can also attach a photo if needed.";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📷 Add Photo', 'callback_data' => 'add_photo_complaint']
            ],
            [
                ['text' => '🔙 Back', 'callback_data' => 'back_to_feedback']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function handleComplaintText($chatId, $text) {
    $state = getUserState($chatId);
    $tempData = $state['temp_data'];
    $tempData['complaint_description'] = $text;
    
    setUserState($chatId, 'awaiting_complaint_confirmation', $tempData);
    
    $message = "<b>📝 Confirm Your Complaint</b>\n\n" .
               "Type: " . $tempData['complaint_type'] . "\n\n" .
               "Details:\n" . htmlspecialchars($text) . "\n\n" .
               "Is this information correct?";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '✅ Submit Complaint', 'callback_data' => 'submit_complaint'],
                ['text' => '❌ Cancel', 'callback_data' => 'back_to_feedback']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function handleComplaintPhoto($chatId, $photoUrl) {
    $state = getUserState($chatId);
    $tempData = $state['temp_data'];
    $tempData['complaint_photo'] = $photoUrl;
    
    setUserState($chatId, 'awaiting_complaint_details', $tempData);
    
    $message = "<b>📝 File a Complaint</b>\n\n" .
               "Photo received! Now please describe your complaint in detail:\n" .
               "• What happened?\n" .
               "• When did it happen?\n" .
               "• What is your expected resolution?";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔙 Back', 'callback_data' => 'back_to_feedback']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function submitComplaint($chatId) {
    $state = getUserState($chatId);
    $tempData = $state['temp_data'];
    
    // Validate required data
    if (empty($tempData['complaint_type']) || empty($tempData['complaint_description'])) {
        sendMessage($chatId, "Please provide all required information for the complaint.");
        return;
    }
    
    try {
        $id = bin2hex(random_bytes(16));
        $stmt = db()->prepare("
            INSERT INTO feedback 
            (id, chat_id, complaint_type, complaint_description, complaint_status, complaint_photo, created_at)
            VALUES (?, ?, ?, ?, 'open', ?, NOW())
        ");
        $stmt->execute([
            $id, 
            (string)$chatId,
            $tempData['complaint_type'],
            $tempData['complaint_description'],
            $tempData['complaint_photo'] ?? null
        ]);
        
        logBotActivity($chatId, 'COMPLAINT_SUBMITTED', [
            'complaint_id' => $id,
            'type' => $tempData['complaint_type']
        ]);
        
        notifyAdminComplaint($id, $tempData);
        clearUserState($chatId);
        
        $message = "<b>📝 Complaint Submitted</b>\n\n" .
                   "Thank you for your feedback! Your complaint has been submitted and we'll get back to you soon.\n\n" .
                   "Complaint ID: <code>" . $id . "</code>\n\n" .
                   "Our support team will review your complaint and contact you within 24 hours.";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];
        
        sendMessage($chatId, $message, $keyboard);
        
    } catch (Exception $e) {
        error_log("Submit complaint error: " . $e->getMessage());
        error_log("Temp data: " . json_encode($tempData));
        sendMessage($chatId, "Error submitting complaint. Please try again later.");
    }
}

function notifyAdminComplaint($id, $data) {
    if (empty(ADMIN_CHAT_ID)) return;
    
    $typeNames = [
        'delivery' => 'Delivery Issue',
        'product' => 'Product Quality',
        'payment' => 'Payment Problem',
        'technical' => 'Technical Issue',
        'cancellation' => 'Order Cancellation'
    ];
    
    $message = "<b>📝 New Complaint Received</b>\n\n" .
               "Complaint ID: <code>" . $id . "</code>\n" .
               "Type: " . ($typeNames[$data['complaint_type']] ?? $data['complaint_type']) . "\n" .
               "Details:\n" . htmlspecialchars($data['complaint_description']) . "\n\n";
    
    if (!empty($data['complaint_photo'])) {
        $message .= "Photo: " . $data['complaint_photo'] . "\n\n";
    }
    
    $message .= "Please review and respond to this complaint.";
    
    sendMessage(ADMIN_CHAT_ID, $message);
}

function showUserFeedback($chatId) {
    try {
        $stmt = db()->prepare("
            SELECT id, service_rating, product_rating, delivery_rating, written_feedback, complaint_type, complaint_description, complaint_status, complaint_photo, created_at
            FROM feedback
            WHERE chat_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$chatId]);
        $feedbacks = $stmt->fetchAll();
        
        if (empty($feedbacks)) {
            $message = "<b>📋 Your Feedback & Reviews</b>\n\n" .
                       "You haven't submitted any feedback or complaints yet.\n\n" .
                       "Need help? Submit a review or file a complaint:";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '⭐ Rate Experience', 'callback_data' => 'rate_service'],
                        ['text' => '📝 File Complaint', 'callback_data' => 'file_complaint']
                    ],
                    [
                        ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
                    ]
                ]
            ];
            sendMessage($chatId, $message, $keyboard);
            return;
        }
        
        $message = "<b>📋 Your Reviews & Feedback</b>\n\n";
        foreach ($feedbacks as $fb) {
            if ($fb['service_rating'] || $fb['product_rating'] || $fb['delivery_rating']) {
                $message .= "⭐ <b>Order Rating</b>\n";
                if ($fb['service_rating'])  $message .= "   🏆 Service: " . getStarDisplay($fb['service_rating']) . " ({$fb['service_rating']}/5)\n";
                if ($fb['product_rating'])  $message .= "   ☕ Product: " . getStarDisplay($fb['product_rating']) . " ({$fb['product_rating']}/5)\n";
                if ($fb['delivery_rating']) $message .= "   🛵 Delivery: " . getStarDisplay($fb['delivery_rating']) . " ({$fb['delivery_rating']}/5)\n";
                $message .= "   📅 Date: " . date('M d, Y', strtotime($fb['created_at'])) . "\n";
                if (!empty($fb['written_feedback'])) {
                    $message .= "   💬 Comment: " . mb_substr($fb['written_feedback'], 0, 60) . (mb_strlen($fb['written_feedback']) > 60 ? '...' : '') . "\n";
                }
                $message .= "\n";
            }
            
            if ($fb['complaint_type']) {
                $statusEmoji = ['open' => '⏳', 'in_progress' => '🔄', 'resolved' => '✅', 'closed' => '🔒'];
                $e = $statusEmoji[$fb['complaint_status']] ?? '📝';
                
                $message .= $e . " <b>Complaint</b>\n";
                $message .= "   Type: " . ucfirst($fb['complaint_type']) . "\n";
                $message .= "   Status: " . ucfirst($fb['complaint_status']) . "\n";
                $message .= "   Date: " . date('M d, Y', strtotime($fb['created_at'])) . "\n";
                $message .= "   " . mb_substr($fb['complaint_description'], 0, 50) . (mb_strlen($fb['complaint_description']) > 50 ? '...' : '') . "\n\n";
            }
        }
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '⭐ Rate Experience', 'callback_data' => 'rate_service'],
                    ['text' => '📝 File Complaint', 'callback_data' => 'file_complaint']
                ],
                [
                    ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];
        sendMessage($chatId, $message, $keyboard);
        
    } catch (Exception $e) {
        error_log("Show user feedback error: " . $e->getMessage());
        sendMessage($chatId, "Error loading your feedback. Please try again.");
    }
}

// ============================================================
// MAINTENANCE MODE MESSAGES & MENUS
// ============================================================
function showMaintenanceMessage($chatId, $user) {
    $firstName = $user['first_name'] ?? 'Valued Customer';
    
    $message = "<b>⛔ Pre-Order is Currently Closed</b>\n\n" .
               "We are not accepting orders at this time.\n" .
               "Please check back later!\n\n" .
               "However, you can still:\n" .
               "⭐ Give us feedback & reviews\n" .
               "📝 File a complaint\n" .
               "❓ Request help\n\n" .
               "Thank you for choosing Kaldis Coffee - ECA Branch, " . htmlspecialchars($firstName) . " ☕";
    
    $keyboard = getMaintenanceKeyboard();
    sendMessage($chatId, $message, $keyboard);
}

function getMaintenanceKeyboard() {
    return [
        'inline_keyboard' => [
            [
                ['text' => '📝 File Complaint', 'callback_data' => 'file_complaint'],
                ['text' => '❓ Help', 'callback_data' => 'show_help']
            ]
        ]
    ];
}

function showMaintenanceBlocked($chatId, $feature) {
    $message = "<b>⚠️ Pre-Order is Closed</b>\n\n" .
               "The <b>" . htmlspecialchars($feature) . "</b> feature is currently unavailable.\n" .
               "We are not accepting orders at this time.\n\n" .
               "Please check back later! ☕";
    
    $keyboard = getMaintenanceKeyboard();
    sendMessage($chatId, $message, $keyboard);
}

// ============================================================
// CORE LOGIC
// ============================================================
ensureBotTablesExist();

$rawInput = file_get_contents("php://input");
if (!empty($rawInput)) {
    @file_put_contents(__DIR__ . '/bot_webhook_log.txt', date('[Y-m-d H:i:s] ') . $rawInput . "\n", FILE_APPEND);
}

$update = json_decode($rawInput, true);
if (!$update) {
    if (PHP_SAPI !== 'cli') {
        // Friendly web response when accessing bot.php directly in browser
        header('Content-Type: text/html; charset=utf-8');
        echo '<div style="font-family: sans-serif; padding: 40px; text-align: center; max-width: 600px; margin: 0 auto; line-height: 1.6;">';
        echo '<h2>☕ Kaldis Coffee ECA Branch - Telegram Bot</h2>';
        echo '<p>Bot webhook endpoint is running properly!</p>';
        echo '<p><a href="?webhook_info=1" style="color: #007bff; text-decoration: none;">🔍 View Webhook Status Info</a> | ';
        echo '<a href="?set_webhook=1" style="color: #28a745; text-decoration: none;">⚙️ Set / Register Webhook Now</a></p>';
        echo '</div>';
        exit;
    }
} else {
    processUpdate($update);
}

// ============================================================
// ADMIN TELEGRAM CALLBACK HANDLERS (Approve / Reject)
// ============================================================
function handleAdminOrderApprove($cq) {
    $data = $cq['data'] ?? '';
    $orderId = intval(str_replace('approve_', '', $data));
    $callbackId = $cq['id'] ?? '';
    if ($orderId <= 0) return;
    
    try {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM pre_orders WHERE id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            apiRequest("answerCallbackQuery", ['callback_query_id' => $callbackId, 'text' => 'Order not found', 'show_alert' => true]);
            return;
        }
        
        if ($order['status'] !== 'Pending') {
            apiRequest("answerCallbackQuery", ['callback_query_id' => $callbackId, 'text' => "Order #{$order['order_number']} is already {$order['status']}", 'show_alert' => true]);
            return;
        }
        
        $db->prepare("UPDATE pre_orders SET status = 'Confirmed', updated_at = NOW() WHERE id = ?")->execute([$orderId]);
        apiRequest("answerCallbackQuery", ['callback_query_id' => $callbackId, 'text' => "✅ Order #{$order['order_number']} Confirmed!", 'show_alert' => true]);
        
        // Notify customer
        if (!empty($order['chat_id'])) {
            $escNum  = htmlspecialchars($order['order_number']);
            $escDate = htmlspecialchars($order['delivery_date']);
            $escAddr = htmlspecialchars($order['delivery_address']);
            
            $msg = "✅ <b>Order Confirmed!</b>\n\n"
                 . "Your Kaldis Coffee order <code>{$escNum}</code> has been confirmed! ☕🎉\n\n"
                 . "📅 <b>Delivery:</b> {$escDate}\n"
                 . "🏢 <b>Office Desk:</b> {$escAddr}\n\n"
                 . "Our runner is preparing to deliver your order to your office desk. Thank you! ☕";
                 
            sendMessage($order['chat_id'], $msg);
        }
        
        // Update Admin Telegram message
        if (isset($cq['message']['chat']['id']) && isset($cq['message']['message_id'])) {
            $origText = $cq['message']['text'] ?? '';
            $newText = "✅ <b>[CONFIRMED & APPROVED BY ADMIN]</b>\n━━━━━━━━━━━━━━\n" . htmlspecialchars($origText);
            apiRequest("editMessageText", [
                'chat_id' => $cq['message']['chat']['id'],
                'message_id' => $cq['message']['message_id'],
                'text' => $newText,
                'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => []]
            ]);
        }
    } catch (Exception $e) {
        error_log("Admin approve error: " . $e->getMessage());
    }
}

function handleAdminOrderRejectPrompt($cq) {
    $data = $cq['data'] ?? '';
    $orderId = intval(str_replace('reject_', '', $data));
    $callbackId = $cq['id'] ?? '';
    if ($orderId <= 0) return;
    
    try {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM pre_orders WHERE id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            apiRequest("answerCallbackQuery", ['callback_query_id' => $callbackId, 'text' => 'Order not found', 'show_alert' => true]);
            return;
        }
        
        if ($order['status'] !== 'Pending') {
            apiRequest("answerCallbackQuery", ['callback_query_id' => $callbackId, 'text' => "Order #{$order['order_number']} is already {$order['status']}", 'show_alert' => true]);
            return;
        }
        
        $msg = "❌ <b>Reject Order #{$order['order_number']}</b>\n\n"
             . "Please select the reason for rejection to send to customer:";
             
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📦 Your order is out of stock (እቃ አልቋል)', 'callback_data' => 'reject_reason_' . $orderId . '_out_of_stock']],
                [['text' => '🚚 Delivery unavailable (ማድረስ አንችልም)', 'callback_data' => 'reject_reason_' . $orderId . '_no_delivery']],
                [['text' => '💳 Payment issue (የክፍያ ችግር)', 'callback_data' => 'reject_reason_' . $orderId . '_payment_issue']],
                [['text' => '⏰ Kitchen closed / Past working hours', 'callback_data' => 'reject_reason_' . $orderId . '_kitchen_closed']],
                [['text' => '✏️ Other reason', 'callback_data' => 'reject_reason_' . $orderId . '_custom']]
            ]
        ];
        
        if (isset($cq['message']['chat']['id']) && isset($cq['message']['message_id'])) {
            apiRequest("editMessageText", [
                'chat_id' => $cq['message']['chat']['id'],
                'message_id' => $cq['message']['message_id'],
                'text' => $msg,
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard
            ]);
        }
    } catch (Exception $e) {
        error_log("Admin reject prompt error: " . $e->getMessage());
    }
}

function handleAdminOrderRejectSubmit($cq) {
    $data = $cq['data'] ?? '';
    $callbackId = $cq['id'] ?? '';
    
    $parts = explode('_', $data);
    if (count($parts) < 4) return;
    
    $orderId = intval($parts[2]);
    $reasonCode = implode('_', array_slice($parts, 3));
    
    $reasonMap = [
        'out_of_stock' => 'Your order is out of stock (የታዘዙት እቃ አልቋል)',
        'no_delivery'  => 'Office desk delivery is currently unavailable for this location/time',
        'payment_issue'=> 'Payment verification could not be completed',
        'kitchen_closed'=> 'Order was placed outside kitchen operating hours',
        'custom'       => 'Order could not be accepted'
    ];
    
    $reasonText = $reasonMap[$reasonCode] ?? 'Order could not be accepted';
    
    try {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM pre_orders WHERE id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            apiRequest("answerCallbackQuery", ['callback_query_id' => $callbackId, 'text' => 'Order not found', 'show_alert' => true]);
            return;
        }
        
        $db->prepare("UPDATE pre_orders SET status = 'Rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ?")->execute([$reasonText, $orderId]);
        apiRequest("answerCallbackQuery", ['callback_query_id' => $callbackId, 'text' => "❌ Order #{$order['order_number']} Rejected", 'show_alert' => true]);
        
        // Notify Customer
        if (!empty($order['chat_id'])) {
            $escNum = htmlspecialchars($order['order_number']);
            $escReason = htmlspecialchars($reasonText);
            
            $msg = "❌ <b>Order Update</b>\n\n"
                 . "Your Kaldis Coffee order <code>{$escNum}</code> could not be accepted.\n\n"
                 . "<b>Reason:</b> {$escReason}\n\n"
                 . "If payment was made, a refund will be processed promptly.\n\n"
                 . "For support, contact Kaldis ECA Support:\n"
                 . "📞 0992098459 | 💬 @ECAKB\n\n"
                 . "Thank you for your understanding. ☕";
                 
            sendMessage($order['chat_id'], $msg);
        }
        
        // Update Admin Telegram Message
        if (isset($cq['message']['chat']['id']) && isset($cq['message']['message_id'])) {
            $newText = "❌ <b>[REJECTED BY ADMIN]</b>\n"
                     . "📋 Order: <code>" . htmlspecialchars($order['order_number']) . "</code>\n"
                     . "<b>Reason:</b> " . htmlspecialchars($reasonText);
                     
            apiRequest("editMessageText", [
                'chat_id' => $cq['message']['chat']['id'],
                'message_id' => $cq['message']['message_id'],
                'text' => $newText,
                'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => []]
            ]);
        }
    } catch (Exception $e) {
        error_log("Admin reject submit error: " . $e->getMessage());
    }
}

function processUpdate($update) {
    
    // ===== CALLBACK QUERIES =====
    if (isset($update['callback_query'])) {
        $cq = $update['callback_query'];
        $chatId = $cq['message']['chat']['id'] ?? ($cq['from']['id'] ?? null);
        $messageId = $cq['message']['message_id'] ?? null;
        $data = $cq['data'] ?? '';
        $fromId = $cq['from']['id'] ?? 0;
        
        apiRequest("answerCallbackQuery", ['callback_query_id' => $cq['id']]);
        logBotActivity($chatId, 'BOT_CALLBACK', ['callback' => $data, 'from_id' => $fromId]);

        // ===== ADMIN ACTION CALLBACKS =====
        if (strpos($data, 'approve_') === 0) {
            handleAdminOrderApprove($cq);
            return;
        }
        if (strpos($data, 'reject_reason_') === 0) {
            handleAdminOrderRejectSubmit($cq);
            return;
        }
        if (strpos($data, 'reject_') === 0) {
            handleAdminOrderRejectPrompt($cq);
            return;
        }
        
        // ===== MAINTENANCE: Block order-related callbacks =====
        if (MAINTENANCE_MODE) {
            // ALLOWED callbacks during maintenance
            $allowedCallbacks = [
                'show_feedback',
                'show_help',
                'back_to_menu',
                'file_complaint',
                'my_feedback',
                'rate_service',
                'rate_service_1','rate_service_2','rate_service_3','rate_service_4','rate_service_5',
                'rate_product_1','rate_product_2','rate_product_3','rate_product_4','rate_product_5',
                'rate_delivery_1','rate_delivery_2','rate_delivery_3','rate_delivery_4','rate_delivery_5',
                'skip_service_feedback'
            ];
            
            // Complaint flow callbacks - always allowed
            if (strpos($data, 'complaint_') === 0 || in_array($data, [
                'add_photo_complaint', 'submit_complaint', 'back_to_feedback'
            ])) {
                // Let these fall through to normal processing below
            }
            
            if (!in_array($data, $allowedCallbacks)) {
                // BLOCKED callback
                $featureNames = [
                    'show_my_orders' => 'My Orders',
                    'track_order' => 'Track Order'
                ];
                $featureName = $featureNames[$data] ?? $data;
                apiRequest("answerCallbackQuery", [
                    'callback_query_id' => $cq['id'], 
                    'text' => '⛔ Pre-Order is currently closed.',
                    'show_alert' => true
                ]);
                showMaintenanceBlocked($chatId, $featureName);
                return;
            }
            
            // For back_to_menu during maintenance, show maintenance menu
            if ($data === 'back_to_menu') {
                showMaintenanceMessage($chatId, $cq['from'] ?? []);
                return;
            }
        }
        
        // ===== NORMAL CALLBACK PROCESSING =====
        switch ($data) {
            case 'show_my_orders': 
                logBotActivity($chatId, 'BOT_MENU_MY_ORDERS'); 
                showUserOrders($chatId); 
                break;
            case 'track_order': 
                logBotActivity($chatId, 'BOT_MENU_TRACK'); 
                handleTrackOrder($chatId); 
                break;
            case 'show_help': 
                logBotActivity($chatId, 'BOT_MENU_HELP'); 
                showHelp($chatId); 
                break;
            case 'back_to_menu': 
                showMainMenu($chatId); 
                break;
            case 'show_feedback': 
                logBotActivity($chatId, 'BOT_MENU_FEEDBACK'); 
                showFeedbackMenu($chatId); 
                break;
            
            // Feedback rating steps
            case 'rate_service':
                logBotActivity($chatId, 'BOT_RATE_SERVICE');
                startServiceRating($chatId);
                break;
            case 'rate_service_1': case 'rate_service_2': case 'rate_service_3': case 'rate_service_4': case 'rate_service_5':
                $rating = (int)str_replace('rate_service_', '', $data);
                handleServiceRatingStep($chatId, $rating);
                break;
            case 'rate_product_1': case 'rate_product_2': case 'rate_product_3': case 'rate_product_4': case 'rate_product_5':
                $rating = (int)str_replace('rate_product_', '', $data);
                handleProductRatingStep($chatId, $rating);
                break;
            case 'rate_delivery_1': case 'rate_delivery_2': case 'rate_delivery_3': case 'rate_delivery_4': case 'rate_delivery_5':
                $rating = (int)str_replace('rate_delivery_', '', $data);
                handleDeliveryRatingStep($chatId, $rating);
                break;
            case 'skip_service_feedback':
                $state = getUserState($chatId);
                $tempData = $state['temp_data'] ?? [];
                saveServiceFeedback($chatId, $tempData);
                break;
            
            case 'file_complaint':
                logBotActivity($chatId, 'BOT_FILE_COMPLAINT');
                startComplaint($chatId);
                break;
            case 'my_feedback':
                logBotActivity($chatId, 'BOT_MY_FEEDBACK');
                showUserFeedback($chatId);
                break;
            
            // Complaint handling
            case 'complaint_delivery':
            case 'complaint_product':
            case 'complaint_payment':
            case 'complaint_technical':
            case 'complaint_cancellation':
                handleComplaintType($chatId, str_replace('complaint_', '', $data));
                break;
            case 'submit_complaint':
                submitComplaint($chatId);
                break;
            case 'add_photo_complaint':
                logBotActivity($chatId, 'BOT_ADD_PHOTO');
                sendMessage($chatId, "Please send a photo with your complaint.");
                setUserState($chatId, 'awaiting_complaint_photo');
                break;
            case 'back_to_feedback':
                showFeedbackMenu($chatId);
                break;
        }
        return;
    }
    
    // ===== MESSAGES =====
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'] ?? ($message['from']['id'] ?? null);
        if (!$chatId) return;

        $user = $message['from'] ?? [];
        $rawText = trim($message['text'] ?? '');

        // Command extraction and normalization (handles /strat, /start@bot, /STRAT, /start ref)
        $cleanText = strtolower($rawText);
        $firstWord = preg_split('/\s+/', $cleanText)[0] ?? '';
        if (($atPos = strpos($firstWord, '@')) !== false) {
            $firstWord = substr($firstWord, 0, $atPos);
        }

        // Common command typos and aliases
        $commandAliases = [
            '/strat' => '/start',
            '/strt' => '/start',
            '/stat' => '/start',
            '/st' => '/start',
            '/orders' => '/myorders',
            '/order_status' => '/track',
            '/tracking' => '/track',
            '/complaint' => '/complaints'
        ];
        $command = $commandAliases[$firstWord] ?? $firstWord;
        
        // Handle photos
        if (isset($message['photo']) && FEEDBACK_PHOTOS) {
            $photoFileId = $message['photo'][0]['file_id'];
            $photoUrl = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . apiRequest("getFile", ['file_id' => $photoFileId])['result']['file_path'];
            
            $state = getUserState($chatId);
            if ($state['state'] === 'awaiting_complaint_photo') {
                handleComplaintPhoto($chatId, $photoUrl);
                return;
            }
        }
        
        $userState = getUserState($chatId);
        
        // If user sends a command starting with '/', clear any active dialogue state
        if (strpos($rawText, '/') === 0) {
            clearUserState($chatId);
            $userState = ['state' => null, 'temp_data' => []];
        } else {
            // Always allow feedback written input if in that state
            if ($userState['state'] === 'awaiting_service_details') {
                handleServiceFeedback($chatId, $rawText);
                return;
            }
            
            // Always allow complaint input if in that state
            if ($userState['state'] === 'awaiting_complaint_details') {
                handleComplaintText($chatId, $rawText);
                return;
            }
            
            // Always allow tracking input if in that state
            if ($userState['state'] === 'tracking') {
                trackOrderByNumber($chatId, $rawText);
                return;
            }
        }
        
        // ===== MAINTENANCE: Handle commands selectively =====
        if (MAINTENANCE_MODE) {
            if (!empty($user['id'])) {
                saveTelegramUser($user);
            }
            
            // ALLOWED commands during maintenance
            $allowedCommands = ['/start', '/feedback', '/help', '/complaints'];
            
            if (in_array($command, $allowedCommands)) {
                switch ($command) {
                    case '/start':
                        logBotActivity($chatId, 'BOT_START_MAINTENANCE', ['name' => ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')]);
                        showMaintenanceMessage($chatId, $user);
                        break;
                    case '/feedback':
                    case '/complaints':
                        logBotActivity($chatId, 'BOT_CMD_FEEDBACK_MAINTENANCE');
                        showFeedbackMenu($chatId);
                        break;
                    case '/help':
                        logBotActivity($chatId, 'BOT_CMD_HELP_MAINTENANCE');
                        showHelp($chatId);
                        break;
                }
                return;
            }
            
            // BLOCKED commands during maintenance
            $blockedCommands = ['/order', '/myorders', '/track'];
            $blockedFeatures = [
                '/order' => 'Order Now',
                '/myorders' => 'My Orders',
                '/track' => 'Track Order'
            ];
            
            if (in_array($command, $blockedCommands)) {
                $featureName = $blockedFeatures[$command] ?? $command;
                logBotActivity($chatId, 'BOT_BLOCKED_MAINTENANCE', ['command' => $command]);
                showMaintenanceBlocked($chatId, $featureName);
                return;
            }
            
            // Any other text during maintenance
            logBotActivity($chatId, 'BOT_UNKNOWN_MAINTENANCE', ['text' => mb_substr($rawText, 0, 100)]);
            showMaintenanceMessage($chatId, $user);
            return;
        }
        
        // ===== NORMAL COMMAND PROCESSING =====
        if ($command === '/start' || $command === '/order') {
            saveTelegramUser($user);
            logBotActivity($chatId, 'BOT_START', ['command' => $command, 'name' => ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')]);
            showWelcomeMessage($chatId, $user);
        } elseif ($command === '/myorders') {
            logBotActivity($chatId, 'BOT_CMD_MYORDERS');
            showUserOrders($chatId);
        } elseif ($command === '/help') {
            logBotActivity($chatId, 'BOT_CMD_HELP');
            showHelp($chatId);
        } elseif ($command === '/track') {
            logBotActivity($chatId, 'BOT_CMD_TRACK');
            handleTrackOrder($chatId);
        } elseif ($command === '/feedback' || $command === '/complaints') {
            logBotActivity($chatId, 'BOT_CMD_FEEDBACK');
            showFeedbackMenu($chatId);
        } else {
            showMainMenu($chatId);
        }
    }
}

// ============================================================
// WELCOME & MAIN MENU
// ============================================================

function showWelcomeMessage($chatId, $user) {
    $firstName = $user['first_name'] ?? 'Valued Customer';
    
    $welcomeText = "<b>Welcome to Kaldis Coffee - ECA Branch, " . htmlspecialchars($firstName) . "! ☕</b>\n\n" .
                   "Order fresh coffee, hot bakery, delicious meals, and celebration cakes delivered straight to your office desk inside UNECA!\n\n" .
                   "☕ Fresh Espresso & Macchiato\n🧋 Iced Frappes & Cold Drinks\n🥐 Butter Croissants & Pastries\n🥪 Club Sandwiches & Burgers\n🍰 Celebration Whole Cakes\n\n" .
                   "How can our barista team serve you today?";
    
    $keyboard = getMainKeyboard($chatId);
    sendMessage($chatId, $welcomeText, $keyboard);
}

function showMainMenu($chatId) {
    $message = "<b>☕ Kaldis Coffee — ECA Branch</b>\n\n" .
               "Fresh coffee and food delivered directly to your office desk inside UNECA compound.\n\n" .
               "What would you like to do?";
    
    $keyboard = getMainKeyboard($chatId);
    sendMessage($chatId, $message, $keyboard);
}

function getMainKeyboard($chatId) {
    $miniAppUrl  = MINI_APP_URL . '?chat_id=' . $chatId;
    $trackAppUrl = MINI_APP_URL . '?chat_id=' . $chatId . '&action=track';
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🛒 Order Now', 'web_app' => ['url' => $miniAppUrl]]
            ],
            [
                ['text' => '📋 My Orders', 'callback_data' => 'show_my_orders'],
                ['text' => '🔍 Track Order', 'web_app' => ['url' => $trackAppUrl]]
            ],
            [
                ['text' => '📝 File Complaint', 'callback_data' => 'file_complaint'],
                ['text' => '❓ Help', 'callback_data' => 'show_help']
            ]
        ]
    ];
    
    return $keyboard;
}

// ============================================================
// ORDERS HANDLING
// ============================================================

function showUserOrders($chatId) {
    try {
        $stmt = db()->prepare("
            SELECT po.order_number, po.total_amount, po.status, po.created_at, po.delivery_address,
                   b.name as branch_name, cd.name as date_name
            FROM pre_orders po
            LEFT JOIN branches b ON po.collection_branch_id = b.id
            LEFT JOIN collection_days cd ON po.collection_day_id = cd.id
            WHERE po.chat_id = ?
            ORDER BY po.created_at DESC LIMIT 5
        ");
        $stmt->execute([$chatId]);
        $orders = $stmt->fetchAll();
        
        if (empty($orders)) {
            $message = "<b>No Orders Found</b>\n\nYou haven't placed any orders yet.\n\nClick 'Order Now' to get started with fresh coffee and food delivered to your office desk!";
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🛒 Order Now', 'web_app' => ['url' => MINI_APP_URL . '?chat_id=' . $chatId]]],
                    [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]
                ]
            ];
            sendMessage($chatId, $message, $keyboard);
            return;
        }
        
        $emoji = ['Pending' => '⏳', 'Confirmed' => '✅', 'Cancelled' => '❌', 'Completed' => '🎉', 'Out for Delivery' => '🚚'];
        
        $message = "<b>☕ My Recent Orders</b>\n\n";
        foreach ($orders as $o) {
            $e = $emoji[$o['status']] ?? '📦';
            $date = date('M d, Y', strtotime($o['created_at']));
            $message .= $e . " <b>" . $o['order_number'] . "</b>\n";
            $message .= "   " . $o['status'] . " · " . ($o['branch_name'] ?? 'N/A') . "\n";
            $message .= "   📅 " . ($o['date_name'] ?? 'N/A') . " · 💰 " . number_format($o['total_amount']) . " ETB\n";
            if (!empty($o['delivery_address'])) {
                $message .= "   🏢 " . mb_substr($o['delivery_address'], 0, 40) . (mb_strlen($o['delivery_address']) > 40 ? '...' : '') . "\n";
            }
            $message .= "\n";
        }
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🛒 Place New Order', 'web_app' => ['url' => MINI_APP_URL . '?chat_id=' . $chatId]]],
                [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]
            ]
        ];
        sendMessage($chatId, $message, $keyboard);
    } catch (Exception $e) {
        sendMessage($chatId, "Error loading your orders. Please try again.");
    }
}

function handleTrackOrder($chatId) {
    $message = "<b>☕ Track Your Office Delivery</b>\n\n" .
               "Please enter your Order Number to check its preparation & delivery status.\n\n" .
               "<i>Example: KLD-00001</i>\n\n" .
               "Type /cancel to go back to menu.";
    sendMessage($chatId, $message);
    setUserState($chatId, 'tracking');
}

function trackOrderByNumber($chatId, $orderNumber) {
    try {
        clearUserState($chatId);
        
        if (strtolower($orderNumber) === '/cancel') {
            if (MAINTENANCE_MODE) {
                showMaintenanceMessage($chatId, []);
            } else {
                showMainMenu($chatId);
            }
            return;
        }

        $stmt = db()->prepare("
            SELECT po.*, b.name as branch_name, cd.name as date_name
            FROM pre_orders po
            LEFT JOIN branches b ON po.collection_branch_id = b.id
            LEFT JOIN collection_days cd ON po.collection_day_id = cd.id
            WHERE po.order_number = ? LIMIT 1
        ");
        $stmt->execute([$orderNumber]);
        $order = $stmt->fetch();
        
        if (!$order) {
            $message = "<b>Order Not Found</b>\n\n" .
                       "We couldn't find an order with number:\n<code>" . htmlspecialchars($orderNumber) . "</code>\n\n" .
                       "Please check and try again.";
            $keyboard = ['inline_keyboard' => [[['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]]];
            sendMessage($chatId, $message, $keyboard);
            return;
        }
        
        $stmt = db()->prepare("
            SELECT poi.quantity, pop.product_name, poi.subtotal 
            FROM pre_order_items poi
            JOIN dairy_products pop ON poi.pre_order_product_id = pop.id
            WHERE poi.pre_order_id = ?
        ");
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll();
        
        $statusEmoji = [
            'Pending' => '⏳', 
            'Confirmed' => '✅', 
            'Processing' => '🔄', 
            'Out for Delivery' => '🚚', 
            'Delivered' => '🏠', 
            'Cancelled' => '❌', 
            'Completed' => '🎉'
        ];
        $e = $statusEmoji[$order['status']] ?? '📦';
        
        $message = "<b>☕ Delivery Status - Kaldis Coffee ECA</b>\n\n" .
                   $e . " <b>Status:</b> " . $order['status'] . "\n" .
                   "📝 <b>Order No:</b> <code>" . $order['order_number'] . "</code>\n" .
                   "📍 <b>From Branch:</b> " . ($order['branch_name'] ?? 'Kaldis Coffee - ECA Branch') . "\n" .
                   "📅 <b>Delivery Date:</b> " . ($order['date_name'] ?? 'N/A') . "\n" .
                   "🏢 <b>Office Desk:</b>\n" . ($order['delivery_address'] ?? 'Not specified') . "\n\n" .
                   "💰 <b>Total:</b> " . number_format($order['total_amount']) . " ETB\n\n";
        
        if (!empty($items)) {
            $message .= "<b>☕ Ordered Items:</b>\n";
            foreach ($items as $item) {
                $pLower = strtolower($item['product_name']);
                $productIcon = (strpos($pLower, 'coffee') !== false || strpos($pLower, 'espresso') !== false || strpos($pLower, 'macchiato') !== false || strpos($pLower, 'latte') !== false) ? '☕' : 
                              ((strpos($pLower, 'frappe') !== false || strpos($pLower, 'smoothie') !== false || strpos($pLower, 'tea') !== false || strpos($pLower, 'juice') !== false) ? '🧋' : 
                              ((strpos($pLower, 'cake') !== false || strpos($pLower, 'torta') !== false) ? '🍰' : 
                              ((strpos($pLower, 'sandwich') !== false || strpos($pLower, 'burger') !== false || strpos($pLower, 'wrap') !== false) ? '🥪' : '🥐')));
                $message .= " $productIcon " . $item['quantity'] . "x " . $item['product_name'] . " = " . number_format($item['subtotal']) . " ETB\n";
            }
        }
        
        $message .= "\n<i>Need help? Contact us at " . SUPPORT_PHONE . "</i>";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '⭐ Rate This Order', 'callback_data' => 'show_feedback']],
                [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]
            ]
        ];
        sendMessage($chatId, $message, $keyboard);
    } catch (Exception $e) {
        sendMessage($chatId, "Error loading order details. Please try again.");
    }
}

// ============================================================
// HELP & COMMANDS
// ============================================================

function showHelp($chatId) {
    $message = "<b>☕ Help & Support - Kaldis Coffee ECA Branch</b>\n\n";
    
    if (MAINTENANCE_MODE) {
        $message .= "<b>⚠️ Pre-Order is Currently Closed</b>\n\n" .
                   "The following features are still available:\n\n" .
                   "<b>Available Now</b>\n" .
                   "⭐ /feedback - Give us feedback\n" .
                   "📝 /complaints - File a complaint\n\n" .
                   "<b>Unavailable During Maintenance</b>\n" .
                   "🛒 /order - Start ordering coffee & food\n" .
                   "📋 /myorders - View orders\n" .
                   "🔍 /track - Track your delivery\n\n";
    } else {
        $message .= "<b>Ordering Coffee & Food for Office Delivery</b>\n" .
                   "• Click 'Order Now' to browse our full menu\n" .
                   "• Select your UNECA building, floor, and desk number\n" .
                   "• Choose your preferred delivery time\n" .
                   "• Pay via Telebirr, CBE Birr, or Pay at Office Desk\n\n" .
                   "<b>Office Desk Delivery</b>\n" .
                   "• Direct delivery to Africa Hall, Secretariat, Congo, Niger, Zambezi, Limpopo, etc.\n" .
                   "• Fast preparation and hot desk delivery\n" .
                   "• Sealed & hygienic packaging\n\n" .
                   "<b>Menu Highlights</b>\n" .
                   "☕ Hot & Iced Coffees\n🥐 Fresh Bakery & Pastries\n🥪 Club Sandwiches & Burgers\n🍰 Celebration Whole Cakes\n\n" .
                   "<b>Commands</b>\n" .
                   "/start - Open main menu\n" .
                   "/order - Start ordering\n" .
                   "/myorders - View orders\n" .
                   "/track - Track your delivery\n" .
                   "/complaints - File a complaint\n" .
                   "/help - Show this help\n\n";
    }
    
    $message .= "<b>Need Help? Contact Kaldis ECA Support:</b>\n" .
                "📞 Mobile: " . SUPPORT_PHONE . "\n" .
                "☎ Extension Phone: " . EXTENSION_PHONE . "\n" .
                "🏢 Extension Short No: " . EXTENSION_SHORT . "\n" .
                "💬 Telegram: @ECAKB";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '💬 Contact Support', 'url' => 'https://t.me/ECAKB']
            ],
            [
                ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

// ============================================================
// ALL COMMANDS LIST
// ============================================================

function showAllCommands($chatId) {
    $message = "<b>📋 All Available Commands</b>\n\n" .
               "Here's a complete list of all commands you can use:\n\n" .
               "<b>🛒 Ordering & Orders</b>\n" .
               "/start - Open main menu\n" .
               "/order - Start ordering process\n" .
               "/myorders - View your order history\n" .
               "/track - Track your delivery status\n\n" .
               "<b>📝 Feedback & Support</b>\n" .
               "/feedback - Give service rating or file complaint\n" .
               "/complaints - File a complaint (same as /feedback)\n" .
               "/help - Show help information\n\n" .
               "<b>⚠️ During Maintenance</b>\n" .
               "Some commands may be unavailable during maintenance mode.\n" .
               "Use /help to see available features during maintenance.";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}
?>