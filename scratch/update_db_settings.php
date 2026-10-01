<?php
require_once __DIR__ . '/../config.php';
try {
    $db = db();
    $db->exec("UPDATE settings SET value = '0992098459' WHERE key = 'support_phone'");
    $db->exec("UPDATE settings SET value = 'https://t.me/ECAKB' WHERE key = 'telegram_channel'");
    echo "DB settings updated successfully.\n";
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
