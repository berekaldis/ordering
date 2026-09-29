<?php
/**
 * Kaldis Coffee - ECA Branch Mini App Entry Point
 */
$chatId = $_GET['chat_id'] ?? '';
$lang = $_GET['lang'] ?? 'en';
header('Location: app.html?chat_id=' . urlencode($chatId) . '&lang=' . urlencode($lang));
exit;
?>