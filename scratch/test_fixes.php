<?php
function cleanPhone($phone) {
    $d = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strpos($d, '251') === 0) $d = substr($d, 3);
    if (strpos($d, '0') === 0) $d = substr($d, 1);
    if (strlen($d) === 9 && preg_match('/^[79][0-9]{8}$/', $d)) {
        return ['ok' => true, 'intl' => '+251' . $d, 'local' => '0' . $d];
    }
    return ['ok' => false];
}

$testPhones = [
    '0992098459',
    '+251992098459',
    '+2510992098459',
    '2510992098459',
    '251992098459',
    '0712345678',
    '+251712345678',
    'invalid123'
];

echo "--- TESTING CLEANPHONE --- \n";
foreach ($testPhones as $p) {
    $res = cleanPhone($p);
    echo "Phone '$p' => " . ($res['ok'] ? "OK: " . $res['intl'] . " / " . $res['local'] : "INVALID") . "\n";
}
