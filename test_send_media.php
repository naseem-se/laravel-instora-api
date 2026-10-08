<?php
$data = json_encode([
    'number' => '923361554837',
    'mediatype' => 'document',
    'mimetype' => 'text/plain',
    'fileName' => 'dummy.txt',
    'media' => 'data:text/plain;base64,' . base64_encode('dummy') // test data URI prefix
]);

$ch = curl_init('http://140.238.240.220:8080/message/sendMedia/session_3');
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: InstoraSaaS9921x',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$info = curl_getinfo($ch);
echo "STATUS: " . $info['http_code'] . "\n";
echo "RES: " . $res . "\n";
