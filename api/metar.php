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

$icao = defined('ICAO_CODE') ? ICAO_CODE : 'ESMK';
$refreshMin = defined('METAR_REFRESH_MINUTES') ? max(1, (int) METAR_REFRESH_MINUTES) : 15;
$cacheTtl = max(60, $refreshMin * 60);
$cacheKey = preg_replace('/[^A-Z0-9_-]/i', '', $icao);
$cacheFile = sys_get_temp_dir() . "/metar_{$cacheKey}.json";

// Browser-side cache for half the refresh interval to absorb accidental reloads
$maxAge = (int) max(30, ($refreshMin * 60) / 2);
header("Cache-Control: max-age={$maxAge}");

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    readfile($cacheFile);
    exit;
}

function serve_stale_metar_cache($cacheFile) {
    if (file_exists($cacheFile)) {
        readfile($cacheFile);
        return true;
    }
    return false;
}

$apiKey = defined('CHECKWX_API_KEY') ? CHECKWX_API_KEY : getenv('CHECKWX_API_KEY');
if (empty($apiKey)) {
    if (serve_stale_metar_cache($cacheFile)) exit;

    http_response_code(500);
    echo json_encode(['error' => 'API key not configured. Create config.php with CHECKWX_API_KEY constant.']);
    exit;
}

$url = "https://api.checkwx.com/v2/metar/{$icao}/decoded";
$context = stream_context_create([
    'http' => [
        'header' => "X-API-Key: {$apiKey}\r\n",
        'timeout' => 10,
        'ignore_errors' => true,
    ],
]);

$response = @file_get_contents($url, false, $context);
$statusCode = 0;
if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $headerLine) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headerLine, $matches)) {
            $statusCode = (int) $matches[1];
            break;
        }
    }
}

if ($response === false || ($statusCode !== 0 && $statusCode >= 400)) {
    if (serve_stale_metar_cache($cacheFile)) exit;

    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch METAR from CheckWX']);
    exit;
}

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
    if (serve_stale_metar_cache($cacheFile)) exit;

    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch METAR from CheckWX']);
    exit;
}

$json = json_encode($result);
@file_put_contents($cacheFile, $json);
echo $json;
