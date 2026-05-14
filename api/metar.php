<?php
/**
 * PHP proxy for CheckWX METAR data
 * Polled independently from TAF — see METAR_REFRESH_MINUTES in config.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

$apiKey = defined('CHECKWX_API_KEY') ? CHECKWX_API_KEY : getenv('CHECKWX_API_KEY');
if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'API key not configured. Create config.php with CHECKWX_API_KEY constant.']);
    exit;
}

$icao = defined('ICAO_CODE') ? ICAO_CODE : 'ESMK';
$refreshMin = defined('METAR_REFRESH_MINUTES') ? max(1, (int) METAR_REFRESH_MINUTES) : 15;

// Browser-side cache for half the refresh interval to absorb accidental reloads
$maxAge = (int) max(30, ($refreshMin * 60) / 2);
header("Cache-Control: max-age={$maxAge}");

$url = "https://api.checkwx.com/v2/metar/{$icao}/decoded";
$context = stream_context_create([
    'http' => [
        'header' => "X-API-Key: {$apiKey}\r\n",
        'timeout' => 10,
    ],
]);

$response = @file_get_contents($url, false, $context);
$data = $response === false ? null : json_decode($response, true);

$result = [
    'metar' => null,
    'metarRaw' => null,
    'fetchedAt' => date('c'),
];

if ($data && isset($data['data']) && count($data['data']) > 0) {
    $result['metar'] = $data['data'][0];
    $result['metarRaw'] = $data['data'][0]['raw_text'] ?? null;
} else {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch METAR from CheckWX']);
    exit;
}

echo json_encode($result);
