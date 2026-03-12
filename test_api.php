<?php
$key = 'sk-ant-api03-yQ4OhDvwQhfTvDDoxt33UZYW7DuWnONvlQTikr4QSmuP_2mHD6USkvi_yzyA6BkIk61idf8vqAA';

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 20,
        'messages'   => [['role'=>'user','content'=>'say hello']]
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $key,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 30,
]);
$response = curl_exec($ch);
$error    = curl_error($ch);
curl_close($ch);

echo "<pre>";
echo "CURL ERROR: " . ($error ?: 'none') . "\n\n";
echo "RESPONSE: " . $response . "\n";
echo "</pre>";
