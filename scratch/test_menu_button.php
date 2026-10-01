<?php
$token = '8575284682:AAGbp6ZDaj1T2vPk1GSRrFC7rXCPbO8vyX8';
$url = 'https://api.telegram.org/bot' . $token . '/setChatMenuButton';
$payload = json_encode([
    'menu_button' => [
        'type' => 'web_app',
        'text' => '☕ Order Now',
        'web_app' => [
            'url' => 'https://ordering.kaldisbunnaet.com/eca/miniapp/app.html'
        ]
    ]
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

echo "Telegram Response: " . $response . "\n";
