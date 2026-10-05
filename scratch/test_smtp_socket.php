<?php
$timeout = 10;
$socket = @fsockopen('ssl://smtp.gmail.com', 465, $errno, $errstr, $timeout);
if (!$socket) {
    echo "Connection error: $errstr ($errno)\n";
    exit;
}
$greeting = fgets($socket, 512);
echo "Greeting: " . trim($greeting) . "\n";
fwrite($socket, "EHLO localhost\r\n");
$response = '';
while ($line = fgets($socket, 512)) {
    $response .= $line;
    if (substr($line, 3, 1) === ' ') break;
}
echo "EHLO response:\n" . $response;
fwrite($socket, "QUIT\r\n");
fclose($socket);
