<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../bot.php';

echo "=== Testing Bot Webhook Integration ===\n\n";

$mockUser = [
    'id' => 123456789,
    'is_bot' => false,
    'first_name' => 'TestUser',
    'username' => 'testuser'
];

// Test 1: Start Command
echo "[Test 1] Processing /start update...\n";
$startUpdate = [
    'update_id' => 10001,
    'message' => [
        'message_id' => 1,
        'from' => $mockUser,
        'chat' => ['id' => 123456789, 'type' => 'private'],
        'date' => time(),
        'text' => '/start'
    ]
];

try {
    processUpdate($startUpdate);
    echo "  ✔ /start command processed successfully.\n";
} catch (Exception $e) {
    echo "  ❌ /start command error: " . $e->getMessage() . "\n";
}

// Test 2: Typo Command Alias /strat
echo "\n[Test 2] Processing /strat typo alias update...\n";
$typoUpdate = [
    'update_id' => 10002,
    'message' => [
        'message_id' => 2,
        'from' => $mockUser,
        'chat' => ['id' => 123456789, 'type' => 'private'],
        'date' => time(),
        'text' => '/strat'
    ]
];

try {
    processUpdate($typoUpdate);
    echo "  ✔ /strat alias processed successfully.\n";
} catch (Exception $e) {
    echo "  ❌ /strat error: " . $e->getMessage() . "\n";
}

// Test 3: Callback Query - show_feedback
echo "\n[Test 3] Processing callback query 'show_feedback'...\n";
$cbUpdate = [
    'update_id' => 10003,
    'callback_query' => [
        'id' => 'cb_9999',
        'from' => $mockUser,
        'message' => [
            'message_id' => 3,
            'chat' => ['id' => 123456789, 'type' => 'private'],
            'date' => time()
        ],
        'data' => 'show_feedback'
    ]
];

try {
    processUpdate($cbUpdate);
    echo "  ✔ Callback query 'show_feedback' processed successfully.\n";
} catch (Exception $e) {
    echo "  ❌ Callback query error: " . $e->getMessage() . "\n";
}

// Test 4: Rating Step Callback
echo "\n[Test 4] Processing rate_service_5 rating step...\n";
$rateUpdate = [
    'update_id' => 10004,
    'callback_query' => [
        'id' => 'cb_9998',
        'from' => $mockUser,
        'message' => [
            'message_id' => 4,
            'chat' => ['id' => 123456789, 'type' => 'private'],
            'date' => time()
        ],
        'data' => 'rate_service_5'
    ]
];

try {
    processUpdate($rateUpdate);
    echo "  ✔ Callback query 'rate_service_5' processed successfully.\n";
} catch (Exception $e) {
    echo "  ❌ Rating step error: " . $e->getMessage() . "\n";
}

echo "\n=== All Webhook Integration Tests Completed ===\n";
