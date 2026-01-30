<?php
/**
 * PHP proxy for CheckWX weather data
 * Fetches METAR and TAF for ESMK
 * 
 * API Key should be set in config.php or as environment variable
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: max-age=120'); // Cache for 2 minutes

// Load API key from config
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

// Get API key from config or environment
$apiKey = defined('CHECKWX_API_KEY') ? CHECKWX_API_KEY : getenv('CHECKWX_API_KEY');

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'API key not configured. Create config.php with CHECKWX_API_KEY constant.']);
    exit;
}

$icao = defined('ICAO_CODE') ? ICAO_CODE : 'ESMK';
$metarUrl = "https://api.checkwx.com/metar/{$icao}/decoded";
$tafUrl = "https://api.checkwx.com/taf/{$icao}/decoded";

// Function to fetch from CheckWX API
function fetchCheckWX($url, $apiKey) {
    $context = stream_context_create([
        'http' => [
            'header' => "X-API-Key: {$apiKey}\r\n",
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    return json_decode($response, true);
}

// Fetch METAR
$metarData = fetchCheckWX($metarUrl, $apiKey);
$tafData = fetchCheckWX($tafUrl, $apiKey);

// Extract raw strings and decoded data
$result = [
    'metar' => null,
    'metarRaw' => null,
    'taf' => null,
    'tafRaw' => null,
    'fetchedAt' => date('c')
];

if ($metarData && isset($metarData['data']) && count($metarData['data']) > 0) {
    $result['metar'] = $metarData['data'][0];
    $result['metarRaw'] = $metarData['data'][0]['raw_text'] ?? null;
}

if ($tafData && isset($tafData['data']) && count($tafData['data']) > 0) {
    $result['taf'] = $tafData['data'][0];
    $result['tafRaw'] = $tafData['data'][0]['raw_text'] ?? null;
}

// If both failed, return error
if ($result['metar'] === null && $result['taf'] === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch weather data from CheckWX']);
    exit;
}

echo json_encode($result);
