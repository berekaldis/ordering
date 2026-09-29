<?php

require_once __DIR__ . '/config.php';

// Handle webhook set/test requests
if (isset($_GET['test'])) {
    header('Content-Type: text/plain');
    echo "Bot is running! Time: " . date('Y-m-d H:i:s');
    exit;
}

if (isset($_GET['webhook_info'])) {
    header('Content-Type: application/json');
    $info = [
        'status' => 'active',
        'time' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ];
    echo json_encode($info);
    exit;
}

function loadBotSettings() {
    $defaults = [
        'bot_token' => '',
        'admin_chat_id' => '',
        'mini_app_url' => SITE_URL . '/miniapp/app.html',
        'support_phone' => '0911000000',
        'telegram_channel' => 'https://t.me/KaldisCoffeeEthiopia',
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
            if (isset($defaults[$row['key']])) {
                $defaults[$row['key']] = $row['value'];
            }
        }
        
        // Ensure mini app URL is correct
        if (strpos($defaults['mini_app_url'], 'index.php') !== false || strpos($defaults['mini_app_url'], 'loniagro') !== false) {
            $defaults['mini_app_url'] = SITE_URL . '/miniapp/app.html';
        }
    } catch (Exception $e) {
        error_log("Failed to load bot settings: " . $e->getMessage());
    }
    
    return $defaults;
}

 $settings = loadBotSettings();

define('BOT_TOKEN', $settings['bot_token']);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN);
define('MINI_APP_URL', $settings['mini_app_url']);
define('SUPPORT_PHONE', $settings['support_phone']);
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

if (empty(BOT_TOKEN)) {
    die("Error: Bot token not found in settings table.");
}

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
            CURLOPT_TIMEOUT => 20,        // Increased to 20 seconds
            CURLOPT_CONNECTTIMEOUT => 10,
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
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    
    $result = apiRequest("sendMessage", $params);
    
    if (isset($result['ok']) && !$result['ok']) {
        error_log("Telegram API Error: " . json_encode($result));
    }
    
    return $result;
}

/**
 * Ensure tables exist and have all required columns.
 */
function ensureBotTablesExist() {
    $flag = __DIR__ . '/.bot_tables_ok';
    
    // Create tables if they don't exist (only once)
    if (!file_exists($flag)) {
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
            
            // Add new tables for subscriptions
            db()->exec("CREATE TABLE IF NOT EXISTS subscriptions (
                id VARCHAR(32) PRIMARY KEY,
                chat_id VARCHAR(20) NOT NULL,
                notification_type VARCHAR(50) NOT NULL,
                active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY (chat_id, notification_type)
            )");
            
            // Add telegram_users table
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
            
            @file_put_contents($flag, '1');
        } catch (Exception $e) {
            error_log("Table creation error: " . $e->getMessage());
        }
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
        
        foreach ($complaintColumns as $col => $def) {
            if (!in_array($col, $feedbackColumns)) {
                db()->exec("ALTER TABLE feedback ADD COLUMN $col $def");
                error_log("Added missing column '$col' to feedback");
                $columnsAdded = true;
            }
        }
        
        if ($columnsAdded) {
            error_log("Table structure updated successfully.");
        }
    } catch (Exception $e) {
        error_log("Failed to check/add columns: " . $e->getMessage());
    }
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
// FEEDBACK SYSTEM (Combined Ratings and Complaints)
// ============================================================

function showFeedbackMenu($chatId) {
    $message = "<b>📝 Feedback & Support</b>\n\n" .
               "How can we help you today?\n\n" .
               "Please select an option:";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '⭐ Rate Service', 'callback_data' => 'rate_service'],
                ['text' => '📝 File Complaint', 'callback_data' => 'file_complaint']
            ],
            [
                ['text' => '📋 My Feedback', 'callback_data' => 'my_feedback'],
                ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function startServiceRating($chatId) {
    setUserState($chatId, 'awaiting_service_rating');
    
    $message = "<b>⭐ Rate Our Service</b>\n\n" .
               "How would you rate your overall experience with Kaldis Coffee - ECA Branch?\n\n" .
               "Your feedback helps us improve our coffee, food, and office delivery service.";
    
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

function handleServiceRating($chatId, $rating) {
    setUserState($chatId, 'awaiting_service_details', ['service_rating' => $rating, 'product_rating' => $rating, 'delivery_rating' => $rating]);
    logBotActivity($chatId, 'BOT_SERVICE_RATING', ['rating' => $rating]);
    
    $stars = getStarDisplay($rating);
    $message = "<b>⭐ Rating Received (" . $stars . ")</b>\n\n" .
               "Please share any additional details to help us improve:\n" .
               "• ☕ <b>Product Quality</b> (Coffee taste, food freshness)\n" .
               "• ⏱️ <b>Delivery Time & Speed</b> (Desk delivery timing)\n" .
               "• 🏆 <b>Staff & Service</b> (Service experience)\n" .
               "• 💬 <b>Others / Suggestions</b>\n\n" .
               "Type your detailed feedback below or tap <b>Skip</b>:";
    
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
        $stmt = db()->prepare("
            INSERT INTO feedback (id, chat_id, service_rating, product_rating, delivery_rating, written_feedback, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $id, 
            (string)$chatId,
            $data['service_rating'] ?? null,
            $data['product_rating'] ?? null,
            $data['delivery_rating'] ?? null,
            $data['service_feedback'] ?? null
        ]);
        
        notifyAdminFeedback($id, $data, 'service');
        clearUserState($chatId);
        
        $message = "<b>⭐ Thank You for Your Feedback!</b>\n\n" .
                   "We truly appreciate you taking the time to share your experience.\n\n" .
                   "Your feedback helps us improve our coffee, food, and office delivery service.\n\n" .
                   "— Kaldis Coffee ECA Branch Team ☕";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']],
                [['text' => '👥 Join Community', 'url' => TELEGRAM_CHANNEL]]
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
                ['text' => '🥛 Product Quality', 'callback_data' => 'complaint_product']
            ],
            [
                ['text' => '💳 Payment Problem', 'callback_data' => 'complaint_payment'],
                ['text' => '📱 Technical Issue', 'callback_data' => 'complaint_technical']
            ],
            [
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
        
        notifyAdminFeedback($id, $tempData, 'complaint');
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

function notifyAdminFeedback($id, $data, $type) {
    if (empty(ADMIN_CHAT_ID)) return;
    
    if ($type === 'service') {
        $stars = getStarDisplay($data['service_rating'] ?? 0);
        $message = "<b>🥛 New Service Feedback Received</b>\n\n" .
                   "Feedback ID: <code>" . $id . "</code>\n" .
                   "Rating: " . $stars . "\n" .
                   "Feedback:\n" . ($data['service_feedback'] ?? 'No additional feedback') . "\n\n" .
                   "Please review this feedback.";
    } else {
        $typeNames = [
            'delivery' => 'Delivery Issue',
            'product' => 'Product Quality',
            'payment' => 'Payment Problem',
            'technical' => 'Technical Issue',
            'cancellation' => 'Order Cancellation'
        ];
        
        $message = "<b>📝 New Complaint Received</b>\n\n" .
                   "Complaint ID: <code>" . $id . "</code>\n" .
                   "Type: " . $typeNames[$data['complaint_type']] . "\n" .
                   "Details:\n" . htmlspecialchars($data['complaint_description']) . "\n\n";
        
        if (!empty($data['complaint_photo'])) {
            $message .= "Photo: " . $data['complaint_photo'] . "\n\n";
        }
        
        $message .= "Please review and respond to this complaint.";
    }
    
    sendMessage(ADMIN_CHAT_ID, $message);
}

function showUserFeedback($chatId) {
    try {
        $stmt = db()->prepare("
            SELECT id, service_rating, written_feedback, complaint_type, complaint_description, complaint_status, complaint_photo, created_at
            FROM feedback
            WHERE chat_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$chatId]);
        $feedbacks = $stmt->fetchAll();
        
        if (empty($feedbacks)) {
            $message = "<b>📋 Your Feedback</b>\n\n" .
                       "You haven't submitted any feedback or complaints yet.\n\n" .
                       "Need help? Submit feedback or file a complaint:";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '⭐ Rate Service', 'callback_data' => 'rate_service'],
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
        
        $message = "<b>📋 Your Feedback</b>\n\n";
        foreach ($feedbacks as $fb) {
            if ($fb['service_rating']) {
                $stars = getStarDisplay($fb['service_rating']);
                $message .= "⭐ <b>Service Rating</b>\n";
                $message .= "   Rating: " . $stars . "\n";
                $message .= "   Date: " . date('M d, Y', strtotime($fb['created_at'])) . "\n";
                if (!empty($fb['written_feedback'])) {
                    $message .= "   Comment: " . mb_substr($fb['written_feedback'], 0, 50) . (mb_strlen($fb['written_feedback']) > 50 ? '...' : '') . "\n";
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
                
                if (!empty($fb['complaint_photo'])) {
                    $message .= "   📷 Photo attached\n\n";
                }
            }
        }
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '⭐ Rate Service', 'callback_data' => 'rate_service'],
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
// SUBSCRIPTION SYSTEM
// ============================================================

function showSubscriptionOptions($chatId) {
    $message = "<b>🔔 Manage Your Subscriptions</b>\n\n" .
               "Choose what notifications you'd like to receive:";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🎁 Special Offers', 'callback_data' => 'subscribe_offers'],
                ['text' => '🥛 New Products', 'callback_data' => 'subscribe_products']
            ],
            [
                ['text' => '❌ Unsubscribe Offers', 'callback_data' => 'unsubscribe_offers'],
                ['text' => '❌ Unsubscribe Products', 'callback_data' => 'unsubscribe_products']
            ],
            [
                ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function showUnsubscriptionOptions($chatId) {
    $message = "<b>🔔 Unsubscribe from Notifications</b>\n\n" .
               "Select which notifications you'd like to unsubscribe from:";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '❌ Unsubscribe Offers', 'callback_data' => 'unsubscribe_offers'],
                ['text' => '❌ Unsubscribe Products', 'callback_data' => 'unsubscribe_products']
            ],
            [
                ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function handleSubscription($chatId, $type) {
    try {
        $stmt = db()->prepare("
            INSERT INTO subscriptions (chat_id, notification_type, active, created_at)
            VALUES (?, ?, TRUE, NOW())
            ON DUPLICATE KEY UPDATE active = TRUE, updated_at = NOW()
        ");
        $stmt->execute([$chatId, $type]);
        
        logBotActivity($chatId, 'SUBSCRIBED', ['type' => $type]);
        
        $typeNames = [
            'offers' => 'Special Offers',
            'products' => 'New Products'
        ];
        
        $message = "<b>🔔 Subscribed!</b>\n\n" .
                   "You're now subscribed to " . $typeNames[$type] . " notifications.\n\n" .
                   "You'll receive updates about " . strtolower($typeNames[$type]) . " directly in this chat.";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];
        
        sendMessage($chatId, $message, $keyboard);
        
    } catch (Exception $e) {
        error_log("Subscription error: " . $e->getMessage());
        sendMessage($chatId, "Error managing subscription. Please try again.");
    }
}

function handleUnsubscription($chatId, $type) {
    try {
        $stmt = db()->prepare("
            INSERT INTO subscriptions (chat_id, notification_type, active, created_at)
            VALUES (?, ?, FALSE, NOW())
            ON DUPLICATE KEY UPDATE active = FALSE, updated_at = NOW()
        ");
        $stmt->execute([$chatId, $type]);
        
        logBotActivity($chatId, 'UNSUBSCRIBED', ['type' => $type]);
        
        $typeNames = [
            'offers' => 'Special Offers',
            'products' => 'New Products'
        ];
        
        $message = "<b>🔔 Unsubscribed</b>\n\n" .
                   "You've unsubscribed from " . $typeNames[$type] . " notifications.";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];
        
        sendMessage($chatId, $message, $keyboard);
        
    } catch (Exception $e) {
        error_log("Unsubscription error: " . $e->getMessage());
        sendMessage($chatId, "Error managing subscription. Please try again.");
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
               "⭐ Give us feedback\n" .
               "ℹ️ Learn about us\n" .
               "📢 Share with friends\n" .
               "📝 File a complaint\n" .
               "🔔 Manage subscriptions\n\n" .
               "Thank you for choosing Kaldis Coffee - ECA Branch, " . htmlspecialchars($firstName) . " ☕";
    
    $keyboard = getMaintenanceKeyboard();
    sendMessage($chatId, $message, $keyboard);
}

function getMaintenanceKeyboard() {
    return [
        'inline_keyboard' => [
            [
                ['text' => '⭐ Give Feedback', 'callback_data' => 'show_feedback']
            ],
            [
                ['text' => '📢 Share Bot', 'callback_data' => 'show_share'],
                ['text' => 'ℹ️ About Us', 'callback_data' => 'show_about']
            ],
            [
                ['text' => '👥 Join Community', 'url' => TELEGRAM_CHANNEL],
                ['text' => '❓ Help', 'callback_data' => 'show_help']
            ],
            [
                ['text' => '📝 File Complaint', 'callback_data' => 'file_complaint']
            ],
            [
                ['text' => '🔔 Manage Subscriptions', 'callback_data' => 'subscribe']
            ]
        ]
    ];
}

function showMaintenanceBlocked($chatId, $feature) {
    $message = "<b>⚠️ Pre-Order is Closed</b>\n\n" .
               "The <b>" . htmlspecialchars($feature) . "</b> feature is currently unavailable.\n" .
               "We are not accepting orders at this time.\n\n" .
               "Please check back later! 🥛";
    
    $keyboard = getMaintenanceKeyboard();
    sendMessage($chatId, $message, $keyboard);
}

// ============================================================
// CORE LOGIC
// ============================================================
 $update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

// Ensure tables and columns are ready BEFORE processing
ensureBotTablesExist();
processUpdate($update);

function processUpdate($update) {
    
    // ===== CALLBACK QUERIES =====
    if (isset($update['callback_query'])) {
        $cq = $update['callback_query'];
        $chatId = $cq['message']['chat']['id'] ?? null;
        $messageId = $cq['message']['message_id'] ?? null;
        $data = $cq['data'] ?? '';
        $fromId = $cq['from']['id'] ?? 0;
        
        apiRequest("answerCallbackQuery", ['callback_query_id' => $cq['id']]);
        logBotActivity($chatId, 'BOT_CALLBACK', ['callback' => $data, 'from_id' => $fromId]);
        
        // ===== MAINTENANCE: Block order-related callbacks =====
        if (MAINTENANCE_MODE) {
            // ALLOWED callbacks during maintenance
            $allowedCallbacks = [
                'show_feedback',
                'show_share',
                'show_about',
                'show_help',
                'back_to_menu',
                'file_complaint',
                'my_feedback',
                'subscribe',
                'unsubscribe',
                'subscribe_offers',
                'subscribe_products',
                'unsubscribe_offers',
                'unsubscribe_products',
                'rate_service',
                'rate_service_1','rate_service_2','rate_service_3','rate_service_4','rate_service_5',
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
            case 'show_about': 
                logBotActivity($chatId, 'BOT_MENU_ABOUT'); 
                showAbout($chatId); 
                break;
            case 'show_share': 
                logBotActivity($chatId, 'BOT_MENU_SHARE'); 
                showShare($chatId); 
                break;
            
            // Feedback handling
            case 'rate_service':
                logBotActivity($chatId, 'BOT_RATE_SERVICE');
                startServiceRating($chatId);
                break;
            case 'file_complaint':
                logBotActivity($chatId, 'BOT_FILE_COMPLAINT');
                startComplaint($chatId);
                break;
            case 'my_feedback':
                logBotActivity($chatId, 'BOT_MY_FEEDBACK');
                showUserFeedback($chatId);
                break;
            case 'rate_service_1': case 'rate_service_2': case 'rate_service_3': case 'rate_service_4': case 'rate_service_5':
                $rating = (int)str_replace('rate_service_', '', $data);
                handleServiceRating($chatId, $rating);
                break;
            case 'skip_service_feedback':
                $state = getUserState($chatId);
                $tempData = $state['temp_data'];
                saveServiceFeedback($chatId, $tempData);
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
            
            // Subscription handling
            case 'subscribe':
                logBotActivity($chatId, 'BOT_MENU_SUBSCRIBE');
                showSubscriptionOptions($chatId);
                break;
            case 'unsubscribe':
                logBotActivity($chatId, 'BOT_MENU_UNSUBSCRIBE');
                showUnsubscriptionOptions($chatId);
                break;
            case 'subscribe_offers':
                handleSubscription($chatId, 'offers');
                break;
            case 'subscribe_products':
                handleSubscription($chatId, 'products');
                break;
            case 'unsubscribe_offers':
                handleUnsubscription($chatId, 'offers');
                break;
            case 'unsubscribe_products':
                handleUnsubscription($chatId, 'products');
                break;
        }
        return;
    }
    
    // ===== MESSAGES =====
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $user = $message['from'] ?? [];
        $text = trim($message['text'] ?? '');
        
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
        
        // Always allow feedback written input if in that state
        if ($userState['state'] === 'awaiting_service_details') {
            handleServiceFeedback($chatId, $text);
            return;
        }
        
        // Always allow complaint input if in that state
        if ($userState['state'] === 'awaiting_complaint_details') {
            handleComplaintText($chatId, $text);
            return;
        }
        
        // Always allow tracking input if in that state (even during maintenance for past orders)
        if ($userState['state'] === 'tracking') {
            trackOrderByNumber($chatId, $text);
            return;
        }
        
        // ===== MAINTENANCE: Handle commands selectively =====
        if (MAINTENANCE_MODE) {
            if (!empty($user['id'])) {
                saveTelegramUser($user);
            }
            
            // ALLOWED commands during maintenance
            $allowedCommands = ['/start', '/feedback', '/about', '/help', '/community', '/complaints', '/subscribe'];
            
            if (in_array($text, $allowedCommands)) {
                switch ($text) {
                    case '/start':
                        logBotActivity($chatId, 'BOT_START_MAINTENANCE', ['name' => ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')]);
                        showMaintenanceMessage($chatId, $user);
                        break;
                    case '/feedback':
                    case '/complaints':
                        logBotActivity($chatId, 'BOT_CMD_FEEDBACK_MAINTENANCE');
                        showFeedbackMenu($chatId);
                        break;
                    case '/about':
                        logBotActivity($chatId, 'BOT_CMD_ABOUT_MAINTENANCE');
                        showAbout($chatId);
                        break;
                    case '/help':
                        logBotActivity($chatId, 'BOT_CMD_HELP_MAINTENANCE');
                        showHelp($chatId);
                        break;
                    case '/community':
                        logBotActivity($chatId, 'BOT_CMD_COMMUNITY_MAINTENANCE');
                        showCommunityLink($chatId);
                        break;
                    case '/subscribe':
                        logBotActivity($chatId, 'BOT_CMD_SUBSCRIBE_MAINTENANCE');
                        showSubscriptionOptions($chatId);
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
            
            if (in_array($text, $blockedCommands)) {
                $featureName = $blockedFeatures[$text] ?? $text;
                logBotActivity($chatId, 'BOT_BLOCKED_MAINTENANCE', ['command' => $text]);
                showMaintenanceBlocked($chatId, $featureName);
                return;
            }
            
            // Any other text during maintenance
            logBotActivity($chatId, 'BOT_UNKNOWN_MAINTENANCE', ['text' => mb_substr($text, 0, 100)]);
            showMaintenanceMessage($chatId, $user);
            return;
        }
        
        // ===== NORMAL COMMAND PROCESSING =====
        if ($text === '/start' || $text === '/order') {
            saveTelegramUser($user);
            logBotActivity($chatId, 'BOT_START', ['command' => $text, 'name' => ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')]);
            showWelcomeMessage($chatId, $user);
        } elseif ($text === '/myorders') {
            logBotActivity($chatId, 'BOT_CMD_MYORDERS');
            showUserOrders($chatId);
        } elseif ($text === '/help') {
            logBotActivity($chatId, 'BOT_CMD_HELP');
            showHelp($chatId);
        } elseif ($text === '/track') {
            logBotActivity($chatId, 'BOT_CMD_TRACK');
            handleTrackOrder($chatId);
        } elseif ($text === '/feedback' || $text === '/complaints') {
            logBotActivity($chatId, 'BOT_CMD_FEEDBACK');
            showFeedbackMenu($chatId);
        } elseif ($text === '/about') {
            logBotActivity($chatId, 'BOT_CMD_ABOUT');
            showAbout($chatId);
        } elseif ($text === '/community') {
            logBotActivity($chatId, 'BOT_CMD_COMMUNITY');
            showCommunityLink($chatId);
        } elseif ($text === '/subscribe') {
            logBotActivity($chatId, 'BOT_CMD_SUBSCRIBE');
            showSubscriptionOptions($chatId);
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
    $miniAppUrl = MINI_APP_URL . '?chat_id=' . $chatId;
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🛒 Order Now', 'web_app' => ['url' => $miniAppUrl]]
            ],
            [
                ['text' => '📋 My Orders', 'callback_data' => 'show_my_orders'],
                ['text' => '🔍 Track Order', 'callback_data' => 'track_order']
            ],
            [
                ['text' => '⭐ Give Feedback', 'callback_data' => 'show_feedback']
            ],
            [
                ['text' => '👥 Join Community', 'url' => TELEGRAM_CHANNEL],
                ['text' => '📢 Share Bot', 'callback_data' => 'show_share']
            ],
            [
                ['text' => 'ℹ️ About Us', 'callback_data' => 'show_about'],
                ['text' => '❓ Help', 'callback_data' => 'show_help']
            ]
        ]
    ];
    
    // Add feedback button if enabled
    if (ENABLE_COMPLAINTS) {
        $keyboard['inline_keyboard'][] = [
            ['text' => '📝 File Complaint', 'callback_data' => 'file_complaint'],
            ['text' => '📋 My Feedback', 'callback_data' => 'my_feedback']
        ];
    }
    
    // Add subscription button if enabled
    if (NOTIFICATION_ENABLED && ENABLE_SUBSCRIPTIONS) {
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔔 Manage Subscriptions', 'callback_data' => 'subscribe']
        ];
    }
    
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
                [['text' => '⭐ Give Feedback', 'callback_data' => 'show_feedback'], ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]
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
// COMMUNITY & SHARE
// ============================================================

function showCommunityLink($chatId) {
    $message = "<b>☕ Join Kaldis Coffee Community!</b>\n\n" .
               "Stay updated with our latest ECA branch menu specials, breakfast combos, and announcements!\n\n" .
               "• Daily office breakfast & lunch combos\n• Seasonal specialty coffees\n• Fresh bakery & pastries\n• Fast desk delivery updates\n\n" .
               "Click below to join our Telegram channel:";
    
    $backText = MAINTENANCE_MODE ? '🔙 Back' : '🔙 Back to Menu';
    $keyboard = [
        'inline_keyboard' => [
            [['text' => 'Join @KaldisCoffeeEthiopia', 'url' => TELEGRAM_CHANNEL]],
            [['text' => $backText, 'callback_data' => 'back_to_menu']]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

function showShare($chatId) {
    $message = "<b>☕ Share Kaldis Coffee with Colleagues!</b>\n\n" .
               "Love getting fresh coffee and food delivered to your desk? Share it with colleagues in the UNECA compound!";
    
    $shareUrl = 'https://t.me/share/url?url=' . urlencode(TELEGRAM_CHANNEL) . '&text=' . urlencode("Order fresh coffee, breakfast, and meals delivered straight to your office desk from Kaldis Coffee - ECA Branch!");
    
    $backText = MAINTENANCE_MODE ? '🔙 Back' : '🔙 Back to Menu';
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '📤 Share with Colleagues', 'url' => $shareUrl]],
            [['text' => $backText, 'callback_data' => 'back_to_menu']]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
}

// ============================================================
// ABOUT US
// ============================================================

function showAbout($chatId) {
    $message = "<b>☕ About Kaldis Coffee — ECA Branch</b>\n\n" .
               "Kaldis Coffee is Ethiopia's premier coffee house chain. At our UNECA Branch in Addis Ababa, we are dedicated to serving UN staff, delegates, and guests with freshly prepared coffee, hot pastries, gourmet sandwiches, and treats delivered right to your office desk.\n\n" .
               "<b>Our Mission</b>\nTo provide authentic Ethiopian coffee tradition, exceptional food quality, and ultra-convenient desk delivery inside the UNECA compound.\n\n" .
               "<b>Our Menu</b>\n" .
               "• ☕ Handcrafted Espresso & Macchiato\n• 🧋 Iced Frappes, Smoothies & Cold Drinks\n• 🥐 Fresh Butter Croissants & Danish Pastries\n• 🥪 Club Sandwiches, Burgers & Savory Meals\n• 🍰 Signature Celebration & Birthday Cakes\n\n" .
               "<b>Office Delivery Promise</b>\n" .
               "• Direct desk delivery across all ECA compound buildings\n• Fast preparation (15-25 mins)\n• Sealed, hot, and hygienic packaging\n• Pay at Desk or mobile banking options\n\n" .
               "<b>Contact</b>\nPhone: " . SUPPORT_PHONE . "\nTelegram: @KaldisCoffeeEthiopia\nLocation: UNECA Compound, Addis Ababa";
    
    $backText = MAINTENANCE_MODE ? '🔙 Back' : '🔙 Back to Menu';
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '👥 Join Community', 'url' => TELEGRAM_CHANNEL]],
            [['text' => $backText, 'callback_data' => 'back_to_menu']]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
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
                   "ℹ️ /about - About Kaldis Coffee ECA\n" .
                   "👥 /community - Join our channel\n" .
                   "📝 /complaints - File a complaint\n" .
                   "🔔 /subscribe - Manage notifications\n\n" .
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
                   "/feedback - Give feedback\n" .
                   "/complaints - File a complaint\n" .
                   "/subscribe - Manage notifications\n" .
                   "/about - About Kaldis Coffee ECA\n" .
                   "/community - Join our channel\n" .
                   "/help - Show this help\n\n";
    }
    
    $message .= "<b>Need Help?</b>\nPhone: " . SUPPORT_PHONE . "\nTelegram: @KaldisCoffeeEthiopia";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '💬 Contact Support', 'url' => 'https://t.me/KaldisCoffeeEthiopia'],
                ['text' => '👥 Join Community', 'url' => TELEGRAM_CHANNEL]
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
               "<b>🔔 Notifications</b>\n" .
               "/subscribe - Manage notification subscriptions\n\n" .
               "<b>ℹ️ Information</b>\n" .
               "/about - About Kaldis Coffee ECA Branch\n" .
               "/community - Join our Telegram channel\n\n" .
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