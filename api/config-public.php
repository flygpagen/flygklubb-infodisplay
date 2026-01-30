<?php
/**
 * Public configuration endpoint
 * Exposes non-sensitive configuration to the frontend
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: max-age=3600'); // Cache for 1 hour

// Load config
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

// Only expose non-sensitive configuration
echo json_encode([
    'icao' => defined('ICAO_CODE') ? ICAO_CODE : 'ESMK',
    'location' => [
        'lat' => defined('LOCATION_LAT') ? LOCATION_LAT : 55.92,
        'lon' => defined('LOCATION_LON') ? LOCATION_LON : 14.08,
        'timezone' => defined('LOCATION_TIMEZONE') ? LOCATION_TIMEZONE : 'Europe/Stockholm',
    ],
]);
