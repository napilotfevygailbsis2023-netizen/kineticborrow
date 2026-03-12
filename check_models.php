<?php
$key = 'AIzaSyBuXz08BJ_DJpCA-qptMuPBfdXgIPcdE88'; // paste your key here

$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?key=' . $key);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 15,
]);
$r = curl_exec($ch);
curl_close($ch);

$data = json_decode($r, true);
echo "<pre>";
foreach ($data['models'] ?? [] as $m) {
    echo $m['name'] . " — " . implode(', ', $m['supportedGenerationMethods'] ?? []) . "\n";
}
echo "</pre>";
