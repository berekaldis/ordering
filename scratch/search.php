<?php
$content = file_get_contents('miniapp/app.html');
$lines = explode("\n", $content);
foreach ($lines as $num => $line) {
    if (strpos($line, 'orderNum') !== false || strpos($line, 'order_number') !== false) {
        echo ($num + 1) . ": " . trim($line) . "\n";
    }
}
