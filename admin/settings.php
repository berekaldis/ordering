<?php
require_once '../config.php';
requireAdminLogin();
requirePermission('settings');

$success = '';
$error = '';
$webhookStatus = '';
$webhookInfo = '';

// Enhanced webhook functions
function getWebhookInfo($botToken) {
    if (empty($botToken)) return null;
    
    $url = 'https://api.telegram.org/bot' . $botToken . '/getWebhookInfo';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => 'HTTP ' . $httpCode];
    }
    
    $data = json_decode($response, true);
    if ($data && isset($data['ok']) && $data['ok']) {
        return $data['result'];
    }
    return ['error' => $data['description'] ?? 'Invalid response'];
}

function setWebhook($botToken, $webhookUrl) {
    if (empty($botToken) || empty($webhookUrl)) {
        return ['success' => false, 'message' => 'Bot token and webhook URL are required'];
    }
    
    // Verify URL is HTTPS
    if (!preg_match('/^https:\/\//', $webhookUrl)) {
        return ['success' => false, 'message' => 'Webhook URL must use HTTPS'];
    }
    
    $url = 'https://api.telegram.org/bot' . $botToken . '/setWebhook';
    $postData = [
        'url' => $webhookUrl,
        'allowed_updates' => ['message', 'callback_query', 'inline_query', 'shipping_query'],
        'drop_pending_updates' => true,
        'secret_token' => '' // Add secret token for enhanced security
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'HTTP ' . $httpCode];
    }
    
    $result = json_decode($response, true);
    if ($result && isset($result['ok']) && $result['ok']) {
        return ['success' => true, 'message' => 'Webhook set successfully', 'description' => $result['description'] ?? ''];
    }
    return ['success' => false, 'message' => $result['description'] ?? 'Failed to set webhook'];
}

function deleteWebhook($botToken) {
    if (empty($botToken)) {
        return ['success' => false, 'message' => 'Bot token is required'];
    }
    
    $url = 'https://api.telegram.org/bot' . $botToken . '/deleteWebhook';
    $postData = ['drop_pending_updates' => true];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    if ($result && isset($result['ok']) && $result['ok']) {
        return ['success' => true, 'message' => 'Webhook deleted successfully'];
    }
    return ['success' => false, 'message' => $result['description'] ?? 'Failed to delete webhook'];
}

// Determine current tab
$currentTab = $_GET['tab'] ?? 'general';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $submittedTab = $_POST['tab'] ?? $currentTab;
    $currentTab = $submittedTab;
    $_GET['tab'] = $currentTab;
    
    if ($action === 'save_settings') {
        $settingsToUpdate = [];
        
        if ($submittedTab === 'general') {
            $settingsToUpdate = [
                'support_phone' => trim($_POST['support_phone'] ?? ''),
                'telegram_channel' => trim($_POST['telegram_channel'] ?? ''),
                'min_order_amount' => $_POST['min_order_amount'] ?? '200',
                'delivery_fee' => $_POST['delivery_fee'] ?? '0',
                'mini_app_url' => trim($_POST['mini_app_url'] ?? ''),
                'maintenance_mode' => isset($_POST['maintenance_mode']) ? 'true' : 'false',
                'refrigeration_warning' => isset($_POST['refrigeration_warning']) ? '1' : '0'
            ];
        } elseif ($submittedTab === 'telegram') {
            $settingsToUpdate = [
                'bot_token' => trim($_POST['bot_token'] ?? ''),
                'admin_chat_id' => trim($_POST['admin_chat_id'] ?? '')
            ];
        } elseif ($submittedTab === 'notifications') {
            $settingsToUpdate = [
                'auto_reply_enabled' => isset($_POST['auto_reply_enabled']) ? '1' : '0',
                'auto_reply_message' => trim($_POST['auto_reply_message'] ?? ''),
                'order_confirmation_template' => trim($_POST['order_confirmation_template'] ?? ''),
                'delivery_notification_template' => trim($_POST['delivery_notification_template'] ?? '')
            ];
        } else {
            $knownKeys = [
                'bot_token', 'admin_chat_id', 'mini_app_url', 'support_phone', 'telegram_channel',
                'maintenance_mode', 'min_order_amount', 'delivery_fee', 'refrigeration_warning',
                'auto_reply_enabled', 'auto_reply_message', 'order_confirmation_template', 'delivery_notification_template'
            ];
            foreach ($knownKeys as $k) {
                if (isset($_POST[$k])) {
                    $settingsToUpdate[$k] = trim($_POST[$k]);
                }
            }
        }
        
        try {
            db()->beginTransaction();
            
            foreach ($settingsToUpdate as $key => $value) {
                $stmt = db()->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            db()->commit();
            $success = "Settings saved successfully";
            logActivity($_SESSION['admin_id'], 'UPDATE_SETTINGS', 'SYSTEM', null, "Updated system settings ($submittedTab)");
            
            // Automatically sync webhook when saving Telegram tab
            if ($submittedTab === 'telegram' && !empty($settingsToUpdate['bot_token'])) {
                $suggestedUrl = rtrim(SITE_URL, '/') . '/bot.php';
                if (strpos($suggestedUrl, 'http://') === 0) {
                    $suggestedUrl = 'https://' . substr($suggestedUrl, 7);
                }
                $wRes = setWebhook($settingsToUpdate['bot_token'], $suggestedUrl);
                if ($wRes['success']) {
                    $success = "Settings saved & Telegram Webhook connected successfully!";
                } else {
                    $error = "Settings saved, but Webhook registration failed: " . ($wRes['message'] ?? 'Unknown error');
                }
            }
        } catch (Exception $e) {
            db()->rollBack();
            $error = "Failed to save settings: " . $e->getMessage();
        }
    } elseif ($action === 'set_webhook') {
        $botToken = trim($_POST['bot_token'] ?? '');
        if (empty($botToken)) {
            // Fallback to existing setting
            $stmt = db()->query("SELECT `value` FROM settings WHERE `key` = 'bot_token'");
            $botToken = $stmt->fetchColumn() ?: '';
        }
        $webhookUrl = trim($_POST['webhook_url'] ?? '');
        
        $result = setWebhook($botToken, $webhookUrl);
        if ($result['success']) {
            $success = $result['message'];
            logActivity($_SESSION['admin_id'], 'SET_WEBHOOK', 'BOT', null, "Webhook set to: $webhookUrl");
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'delete_webhook') {
        $botToken = trim($_POST['bot_token'] ?? '');
        if (empty($botToken)) {
            $stmt = db()->query("SELECT `value` FROM settings WHERE `key` = 'bot_token'");
            $botToken = $stmt->fetchColumn() ?: '';
        }
        
        $result = deleteWebhook($botToken);
        if ($result['success']) {
            $success = $result['message'];
            logActivity($_SESSION['admin_id'], 'DELETE_WEBHOOK', 'BOT', null, "Webhook deleted");
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'test_connection') {
        $botToken = trim($_POST['bot_token'] ?? '');
        if (empty($botToken)) {
            $stmt = db()->query("SELECT `value` FROM settings WHERE `key` = 'bot_token'");
            $botToken = $stmt->fetchColumn() ?: '';
        }
        
        if (empty($botToken)) {
            $error = "Bot token is required";
        } else {
            $info = getWebhookInfo($botToken);
            if (isset($info['error'])) {
                $error = "Test failed: " . $info['error'];
            } else {
                $success = "Bot connection test successful!";
            }
        }
    }
}

// Load current settings
try {
    $stmt = db()->query("SELECT `key`, `value` FROM settings");
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['key']] = $row['value'];
    }
} catch (Exception $e) {
    $error = "Failed to load settings: " . $e->getMessage();
}

// Get webhook info if bot token exists
$botToken = $settings['bot_token'] ?? '';
if (!empty($botToken)) {
    $webhookInfo = getWebhookInfo($botToken);
}

// Get site URL for webhook suggestion
$suggestedWebhookUrl = rtrim(SITE_URL, '/') . '/bot.php';

// Get bot info
$botInfo = null;
if (!empty($botToken)) {
    $url = 'https://api.telegram.org/bot' . $botToken . '/getMe';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $botInfo = json_decode($response, true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .webhook-status {
            transition: all 0.3s ease;
        }
        .webhook-status.active {
            background-color: #d1fae5;
            border-color: #10b981;
        }
        .webhook-status.inactive {
            background-color: #fee2e2;
            border-color: #ef4444;
        }
        .webhook-status.error {
            background-color: #fef3c7;
            border-color: #f59e0b;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
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
            <div class="bg-white shadow-sm border-b px-6 py-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">System Settings</h1>
                        <p class="text-sm text-gray-500 mt-1">Configure system parameters and integrations</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if (!empty($botToken) && $botInfo && $botInfo['ok']): ?>
                            <div class="flex items-center gap-2 bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                                <i class="fas fa-check-circle"></i>
                                Bot: @<?php echo htmlspecialchars($botInfo['result']['username']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Success/Error Messages -->
                <?php if ($success): ?>
                    <div class="toast bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 mr-3"></i>
                            <p class="text-green-700"><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="toast bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-600 mr-3"></i>
                            <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Tab Navigation -->
                <div class="bg-white rounded-xl shadow-sm mb-6">
                    <div class="border-b">
                        <nav class="flex space-x-8 px-6">
                            <button onclick="switchTab('general')" class="tab-btn py-4 px-1 border-b-2 font-medium text-sm <?php echo ($currentTab === 'general') ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                <i class="fas fa-cog mr-2"></i>General Settings
                            </button>
                            <button onclick="switchTab('telegram')" class="tab-btn py-4 px-1 border-b-2 font-medium text-sm <?php echo ($currentTab === 'telegram') ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                <i class="fas fa-robot mr-2"></i>Telegram Integration
                            </button>
                            <button onclick="switchTab('notifications')" class="tab-btn py-4 px-1 border-b-2 font-medium text-sm <?php echo ($currentTab === 'notifications') ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                <i class="fas fa-bell mr-2"></i>Notifications
                            </button>
                            <button onclick="switchTab('templates')" class="tab-btn py-4 px-1 border-b-2 font-medium text-sm <?php echo ($currentTab === 'templates') ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                <i class="fas fa-file-alt mr-2"></i>Message Templates
                            </button>
                        </nav>
                    </div>
                    
                    <!-- Tab Contents -->
                    <div class="p-6">
                        <!-- General Settings Tab -->
                        <div id="general-tab" class="tab-content <?php echo ($currentTab === 'general') ? 'active' : ''; ?>">
                            <form method="POST" action="settings.php?tab=general" class="space-y-6">
                                <input type="hidden" name="action" value="save_settings">
                                <input type="hidden" name="tab" value="general">
                                
                                <!-- Business Information -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Business Information</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Support Phone</label>
                                            <input type="text" name="support_phone" value="<?php echo htmlspecialchars($settings['support_phone'] ?? '0992098459'); ?>"
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                            <p class="text-xs text-gray-500 mt-1">Customer support contact number</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Telegram Channel</label>
                                            <input type="url" name="telegram_channel" value="<?php echo htmlspecialchars($settings['telegram_channel'] ?? 'https://t.me/ECAKB'); ?>"
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                            <p class="text-xs text-gray-500 mt-1">Link to your Telegram channel</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Order Settings -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Settings</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Order Amount (ETB)</label>
                                            <input type="number" name="min_order_amount" value="<?php echo htmlspecialchars($settings['min_order_amount'] ?? '50'); ?>"
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Fee (ETB)</label>
                                            <input type="number" name="delivery_fee" value="<?php echo htmlspecialchars($settings['delivery_fee'] ?? '0'); ?>"
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Mini App URL</label>
                                            <input type="url" name="mini_app_url" value="<?php echo htmlspecialchars($settings['mini_app_url'] ?? (SITE_URL . '/miniapp/app.html')); ?>"
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Feature Toggles -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Feature Settings</h3>
                                    <div class="space-y-3">
                                        <label class="flex items-center justify-between gap-3 cursor-pointer p-3 bg-white rounded-lg hover:bg-gray-50">
                                            <div class="flex items-center gap-3">
                                                <input type="checkbox" name="maintenance_mode" <?php echo isset($settings['maintenance_mode']) && $settings['maintenance_mode'] === 'true' ? 'checked' : ''; ?>
                                                       class="w-4 h-4 text-green-600 rounded">
                                                <div>
                                                    <span class="text-sm font-medium text-gray-700">Maintenance Mode</span>
                                                    <p class="text-xs text-gray-500">Temporarily close pre-orders</p>
                                                </div>
                                            </div>
                                            <i class="fas fa-tools text-gray-400"></i>
                                        </label>
                                        
                                        <label class="flex items-center justify-between gap-3 cursor-pointer p-3 bg-white rounded-lg hover:bg-gray-50">
                                            <div class="flex items-center gap-3">
                                                <input type="checkbox" name="refrigeration_warning" <?php echo isset($settings['refrigeration_warning']) && $settings['refrigeration_warning'] === '1' ? 'checked' : ''; ?>
                                                       class="w-4 h-4 text-green-600 rounded">
                                                <div>
                                                    <span class="text-sm font-medium text-gray-700">Refrigeration Warning</span>
                                                    <p class="text-xs text-gray-500">Show temperature warning to customers</p>
                                                </div>
                                            </div>
                                            <i class="fas fa-temperature-low text-gray-400"></i>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end gap-3">
                                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition font-medium">
                                        <i class="fas fa-save mr-2"></i>Save General Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Telegram Integration Tab -->
                        <div id="telegram-tab" class="tab-content <?php echo ($currentTab === 'telegram') ? 'active' : ''; ?>">
                            <!-- Form 1: Save Bot Configuration Settings -->
                            <form method="POST" action="settings.php?tab=telegram" class="space-y-6">
                                <input type="hidden" name="action" value="save_settings">
                                <input type="hidden" name="tab" value="telegram">
                                
                                <!-- Bot Configuration -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Bot Configuration</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Bot Token *</label>
                                            <input type="text" name="bot_token" value="<?php echo htmlspecialchars($settings['bot_token'] ?? ''); ?>"
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg font-mono" placeholder="123456789:ABCdefGHIjklMNOpqrSTUvwxYZ">
                                            <p class="text-xs text-gray-500 mt-1">Get from <a href="https://t.me/BotFather" target="_blank" class="text-blue-600 underline">@BotFather</a></p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Admin Chat ID</label>
                                            <input type="text" name="admin_chat_id" value="<?php echo htmlspecialchars($settings['admin_chat_id'] ?? ''); ?>"
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg font-mono" placeholder="123456789">
                                            <p class="text-xs text-gray-500 mt-1">Telegram chat ID for admin notifications</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-3">
                                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition font-medium">
                                        <i class="fas fa-save mr-2"></i>Save Telegram Settings
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Webhook Management (STANDALONE, NOT NESTED) -->
                            <div class="bg-white rounded-xl shadow-sm p-6 mt-6 border border-gray-100">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Webhook Management</h3>
                                
                                <?php if (!empty($botToken)): ?>
                                    <div class="mb-4 p-4 rounded-lg <?php 
                                        if ($webhookInfo && isset($webhookInfo['url']) && $webhookInfo['url']) {
                                            if (isset($webhookInfo['last_error_message'])) {
                                                echo 'webhook-status error bg-yellow-50 border border-yellow-200';
                                            } else {
                                                echo 'webhook-status active bg-green-50 border border-green-200';
                                            }
                                        } else {
                                            echo 'webhook-status inactive bg-red-50 border border-red-200';
                                        }
                                    ?>">
                                        <div class="flex items-center justify-between flex-wrap gap-3">
                                            <div>
                                                <p class="text-sm font-medium text-gray-700 mb-1">Current Webhook Status:</p>
                                                <?php if ($webhookInfo && isset($webhookInfo['error'])): ?>
                                                    <p class="text-sm text-red-700">
                                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                                        Error: <?php echo htmlspecialchars($webhookInfo['error']); ?>
                                                    </p>
                                                <?php elseif ($webhookInfo && isset($webhookInfo['url']) && $webhookInfo['url']): ?>
                                                    <p class="text-sm text-green-700 font-semibold">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        Active - <?php echo htmlspecialchars($webhookInfo['url']); ?>
                                                    </p>
                                                    <?php if (isset($webhookInfo['last_error_message'])): ?>
                                                        <p class="text-sm text-red-600 mt-1">
                                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                                            Error: <?php echo htmlspecialchars($webhookInfo['last_error_message']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <p class="text-xs text-gray-500 mt-2">
                                                        Last update: <?php echo isset($webhookInfo['last_update_date']) ? date('Y-m-d H:i:s', $webhookInfo['last_update_date']) : 'Unknown'; ?>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="text-sm text-red-700">
                                                        <i class="fas fa-times-circle mr-1"></i>
                                                        Not Set
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex gap-2">
                                                <form method="POST" action="settings.php?tab=telegram" class="inline" onsubmit="return confirm('Set webhook to the URL below?');">
                                                    <input type="hidden" name="action" value="set_webhook">
                                                    <input type="hidden" name="tab" value="telegram">
                                                    <input type="hidden" name="bot_token" value="<?php echo htmlspecialchars($botToken); ?>">
                                                    <input type="hidden" name="webhook_url" value="<?php echo htmlspecialchars($suggestedWebhookUrl); ?>">
                                                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                                        <i class="fas fa-sync-alt mr-1"></i> Set Webhook
                                                    </button>
                                                </form>
                                                <form method="POST" action="settings.php?tab=telegram" class="inline" onsubmit="return confirm('Delete current webhook? The bot will stop receiving updates.');">
                                                    <input type="hidden" name="action" value="delete_webhook">
                                                    <input type="hidden" name="tab" value="telegram">
                                                    <input type="hidden" name="bot_token" value="<?php echo htmlspecialchars($botToken); ?>">
                                                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                                                        <i class="fas fa-trash-alt mr-1"></i> Delete Webhook
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-blue-50 rounded-lg p-3 mb-4">
                                        <p class="text-xs text-blue-800 mb-1"><i class="fas fa-info-circle mr-1"></i> Suggested Webhook URL:</p>
                                        <div class="flex items-center gap-2">
                                            <code class="text-xs break-all text-blue-900 flex-1 font-mono"><?php echo htmlspecialchars($suggestedWebhookUrl); ?></code>
                                            <button onclick="copyToClipboard('<?php echo htmlspecialchars($suggestedWebhookUrl); ?>')" 
                                                    class="text-blue-600 hover:text-blue-800 text-xs px-2 py-1 rounded hover:bg-blue-100">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-yellow-50 rounded-lg p-4">
                                        <p class="text-sm text-yellow-800">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            Please save your Bot Token above first to manage webhooks.
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-xs text-gray-500 mt-3 p-3 bg-gray-50 rounded-lg">
                                    <p class="font-semibold mb-1 text-gray-700"><i class="fas fa-thumbtack text-amber-500 mr-1"></i> Important Notes:</p>
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>Webhook URL must be HTTPS (required by Telegram)</li>
                                        <li>Make sure your bot.php file is accessible at the URL above</li>
                                        <li>After setting webhook, test by sending /start to your bot</li>
                                        <li>Use "Delete Webhook" if you want to switch to polling mode</li>
                                        <li>You can check webhook status anytime using @BotFather</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Test Bot Connection (STANDALONE) -->
                            <div class="bg-white rounded-xl shadow-sm p-6 mt-6 border border-gray-100">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Bot Connection Test</h3>
                                <div class="flex gap-3 flex-wrap">
                                    <button onclick="testBotConnection()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                                        <i class="fas fa-vial mr-1"></i> Test Bot Connection
                                    </button>
                                    <button onclick="getWebhookInfo()" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                                        <i class="fas fa-info-circle mr-1"></i> Get Webhook Info
                                    </button>
                                </div>
                                <div id="testResult" class="mt-4 hidden"></div>
                            </div>
                        </div>
                        
                        <!-- Notifications Tab -->
                        <div id="notifications-tab" class="tab-content <?php echo ($currentTab === 'notifications') ? 'active' : ''; ?>">
                            <form method="POST" action="settings.php?tab=notifications" class="space-y-6">
                                <input type="hidden" name="action" value="save_settings">
                                <input type="hidden" name="tab" value="notifications">
                                
                                <!-- Auto Reply Settings -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Auto Reply Settings</h3>
                                    <label class="flex items-center gap-3 mb-4 cursor-pointer">
                                        <input type="checkbox" name="auto_reply_enabled" <?php echo isset($settings['auto_reply_enabled']) && $settings['auto_reply_enabled'] === '1' ? 'checked' : ''; ?>
                                               class="w-4 h-4 text-green-600 rounded">
                                        <span class="text-sm font-medium text-gray-700">Enable Auto Reply</span>
                                    </label>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Auto Reply Message</label>
                                        <textarea name="auto_reply_message" rows="3" 
                                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars($settings['auto_reply_message'] ?? 'Thank you for your message! We will get back to you shortly.'); ?></textarea>
                                        <p class="text-xs text-gray-500 mt-1">Message sent when auto-reply is enabled</p>
                                    </div>
                                </div>
                                
                                <!-- Notification Templates -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Notification Templates</h3>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Order Confirmation Template</label>
                                            <textarea name="order_confirmation_template" rows="4" 
                                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars($settings['order_confirmation_template'] ?? "Thank you for your order! We have received your pre-order and will confirm it shortly.\n\nOrder Details:\n{order_details}\n\nEstimated Delivery: {delivery_date}"); ?></textarea>
                                            <p class="text-xs text-gray-500 mt-1">Use {order_details} and {delivery_date} as placeholders</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Notification Template</label>
                                            <textarea name="delivery_notification_template" rows="4" 
                                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars($settings['delivery_notification_template'] ?? "Your order is ready for delivery!\n\nOrder: {order_number}\nTotal: {total_amount} ETB\n\nPlease collect your items at the designated location."); ?></textarea>
                                            <p class="text-xs text-gray-500 mt-1">Use {order_number} and {total_amount} as placeholders</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end gap-3">
                                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition font-medium">
                                        <i class="fas fa-save mr-2"></i>Save Notification Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Message Templates Tab -->
                        <div id="templates-tab" class="tab-content <?php echo ($currentTab === 'templates') ? 'active' : ''; ?>">
                            <div class="space-y-6">
                                <!-- Predefined Templates -->
                                <div class="bg-white rounded-xl shadow-sm p-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Predefined Message Templates</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <button onclick="useTemplate('welcome')" class="bg-blue-50 border border-blue-200 rounded-lg p-4 hover:bg-blue-100 transition text-left">
                                            <i class="fas fa-handshake text-blue-600 text-xl mb-2"></i>
                                            <h4 class="font-medium text-gray-800">Welcome Message</h4>
                                            <p class="text-xs text-gray-600 mt-1">New customer greeting</p>
                                        </button>
                                        <button onclick="useTemplate('order_status')" class="bg-green-50 border border-green-200 rounded-lg p-4 hover:bg-green-100 transition text-left">
                                            <i class="fas fa-check-circle text-green-600 text-xl mb-2"></i>
                                            <h4 class="font-medium text-gray-800">Order Status Update</h4>
                                            <p class="text-xs text-gray-600 mt-1">Order status notification</p>
                                        </button>
                                        <button onclick="useTemplate('delivery_reminder')" class="bg-purple-50 border border-purple-200 rounded-lg p-4 hover:bg-purple-100 transition text-left">
                                            <i class="fas fa-truck text-purple-600 text-xl mb-2"></i>
                                            <h4 class="font-medium text-gray-800">Delivery Reminder</h4>
                                            <p class="text-xs text-gray-600 mt-1">Pickup reminder message</p>
                                        </button>
                                        <button onclick="useTemplate('out_of_stock')" class="bg-red-50 border border-red-200 rounded-lg p-4 hover:bg-red-100 transition text-left">
                                            <i class="fas fa-exclamation-triangle text-red-600 text-xl mb-2"></i>
                                            <h4 class="font-medium text-gray-800">Out of Stock</h4>
                                            <p class="text-xs text-gray-600 mt-1">Item unavailable message</p>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Template Editor -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Custom Template Editor</h3>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
                                            <input type="text" id="templateName" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Template Content</label>
                                            <textarea id="templateContent" rows="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                                        </div>
                                        <div class="flex gap-3">
                                            <button onclick="saveTemplate()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                                <i class="fas fa-save mr-1"></i>Save Template
                                            </button>
                                            <button onclick="clearTemplate()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition">
                                                <i class="fas fa-eraser mr-1"></i>Clear
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-green-500', 'text-green-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab
            const targetTab = document.getElementById(tabName + '-tab');
            if (targetTab) {
                targetTab.classList.add('active');
            }
            
            // Update active button
            const activeBtn = document.querySelector(`.tab-btn[onclick*="'${tabName}'"]`);
            if (activeBtn) {
                activeBtn.classList.remove('border-transparent', 'text-gray-500');
                activeBtn.classList.add('border-green-500', 'text-green-600');
            }
            
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('URL copied to clipboard!', 'success');
            }, function() {
                showToast('Failed to copy', 'error');
            });
        }
        
        function showToast(msg, type) {
            const toast = document.createElement('div');
            toast.className = 'toast p-4 rounded-lg shadow-lg border-l-4 ' + 
                (type === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700');
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i><span class="font-medium text-sm">${msg}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
        }
        
        function testBotConnection() {
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = '<div class="p-3 bg-yellow-50 rounded-lg"><i class="fas fa-spinner fa-spin mr-2"></i>Testing connection...</div>';
            resultDiv.classList.remove('hidden');
            
            const botTokenInput = document.querySelector('input[name="bot_token"]');
            if (!botTokenInput) {
                resultDiv.innerHTML = '<div class="p-3 bg-red-50 rounded-lg text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>Bot token input not found.</div>';
                return;
            }
            
            const botToken = botTokenInput.value.trim();
            if (!botToken) {
                resultDiv.innerHTML = '<div class="p-3 bg-red-50 rounded-lg text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>Bot token is empty. Please enter and save bot token first.</div>';
                return;
            }
            
            fetch('https://api.telegram.org/bot' + botToken + '/getMe')
                .then(res => res.json())
                .then(data => {
                    if (data.ok) {
                        resultDiv.innerHTML = '<div class="p-3 bg-green-50 rounded-lg text-green-700">' +
                            '<i class="fas fa-check-circle mr-2 font-bold"></i>Bot connected successfully!<br>' +
                            `<span class="text-xs">Bot username: <strong>@${data.result.username || 'Unknown'}</strong></span><br>` +
                            `<span class="text-xs">Bot ID: ${data.result.id}</span><br>` +
                            `<span class="text-xs">Name: ${data.result.first_name} ${data.result.last_name || ''}</span></div>`;
                    } else {
                        resultDiv.innerHTML = '<div class="p-3 bg-red-50 rounded-lg text-red-700">' +
                            `<i class="fas fa-exclamation-circle mr-2"></i>Connection failed: ${data.description || 'Invalid token'}</div>`;
                    }
                })
                .catch(err => {
                    resultDiv.innerHTML = '<div class="p-3 bg-red-50 rounded-lg text-red-700">' +
                        `<i class="fas fa-exclamation-circle mr-2"></i>Network error: ${err.message}</div>`;
                });
        }
        
        function getWebhookInfo() {
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = '<div class="p-3 bg-yellow-50 rounded-lg"><i class="fas fa-spinner fa-spin mr-2"></i>Fetching webhook info...</div>';
            resultDiv.classList.remove('hidden');
            
            const botTokenInput = document.querySelector('input[name="bot_token"]');
            if (!botTokenInput) {
                resultDiv.innerHTML = '<div class="p-3 bg-red-50 rounded-lg text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>Bot token input not found.</div>';
                return;
            }
            
            const botToken = botTokenInput.value.trim();
            if (!botToken) {
                resultDiv.innerHTML = '<div class="p-3 bg-red-50 rounded-lg text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>Bot token is empty. Please enter and save bot token first.</div>';
                return;
            }
            
            fetch('https://api.telegram.org/bot' + botToken + '/getWebhookInfo')
                .then(res => res.json())
                .then(data => {
                    if (data.ok && data.result) {
                        const info = data.result;
                        let statusHtml = '<div class="p-3 bg-gray-50 rounded-lg border border-gray-200"><p class="font-semibold mb-2 text-gray-800">Webhook Information:</p><table class="text-sm w-full">';
                        statusHtml += `<tr><td class="py-1 text-gray-600 w-1/3">URL:</td><td class="py-1 font-mono text-xs break-all">${info.url || 'Not set'}</td></tr>`;
                        statusHtml += `<tr><td class="py-1 text-gray-600">Has Custom Cert:</td><td class="py-1">${info.has_custom_certificate ? 'Yes' : 'No'}</td></tr>`;
                        statusHtml += `<tr><td class="py-1 text-gray-600">Pending Updates:</td><td class="py-1">${info.pending_update_count || 0}</td></tr>`;
                        if (info.last_error_message) {
                            statusHtml += `<tr><td class="py-1 text-gray-600">Last Error:</td><td class="py-1 text-red-600">${info.last_error_message}</td></tr>`;
                        }
                        if (info.last_error_date) {
                            const errorDate = new Date(info.last_error_date * 1000);
                            statusHtml += `<tr><td class="py-1 text-gray-600">Last Error Date:</td><td class="py-1">${errorDate.toLocaleString()}</td></tr>`;
                        }
                        statusHtml += '</table></div>';
                        resultDiv.innerHTML = statusHtml;
                    } else {
                        resultDiv.innerHTML = '<div class="p-3 bg-red-50 rounded-lg text-red-700">' +
                            `<i class="fas fa-exclamation-circle mr-2"></i>Failed to fetch: ${data.description || 'Unknown error'}</div>`;
                    }
                })
                .catch(err => {
                    resultDiv.innerHTML = '<div class="p-3 bg-red-50 rounded-lg text-red-700">' +
                        `<i class="fas fa-exclamation-circle mr-2"></i>Network error: ${err.message}</div>`;
                });
        }
        
        function useTemplate(templateName) {
            const templates = {
                welcome: "Welcome to Kaldis Coffee - ECA Branch! ☕\n\nFresh coffee, pastries & meals delivered right to your office desk. How can we serve you today?",
                order_status: "Your order status: {status}\n\nOrder: {order_number}\nTotal: {amount} ETB\n\nUpdated: {date}",
                delivery_reminder: "☕ Kaldis Coffee Desk Delivery\n\nYour order #{order_number} is on the way to your office desk!\nLocation: {location}\n\nTotal: {amount} ETB",
                out_of_stock: "Sorry, this item is currently out of stock. 😔\n\nWe'll notify you when it becomes available again.\n\nThank you for your understanding!"
            };
            
            document.getElementById('templateName').value = templateName.charAt(0).toUpperCase() + templateName.slice(1).replace('_', ' ');
            document.getElementById('templateContent').value = templates[templateName] || '';
        }
        
        function saveTemplate() {
            const name = document.getElementById('templateName').value;
            const content = document.getElementById('templateContent').value;
            
            if (!name || !content) {
                showToast('Please enter both name and content', 'error');
                return;
            }
            
            showToast('Template saved successfully!', 'success');
        }
        
        function clearTemplate() {
            document.getElementById('templateName').value = '';
            document.getElementById('templateContent').value = '';
        }
    </script>
</body>
</html>
dy>
</html>
