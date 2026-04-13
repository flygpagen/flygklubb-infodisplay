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
    'runway' => [
        'heading' => defined('RUNWAY_HEADING') ? RUNWAY_HEADING : 10,
    ],
    'radar' => [
        'url' => defined('RADAR_URL') ? RADAR_URL : '',
        'urls' => defined('PANEL_CAROUSEL_URLS') && PANEL_CAROUSEL_URLS !== ''
            ? array_map('trim', explode(',', PANEL_CAROUSEL_URLS))
            : [],
        'interval' => defined('PANEL_CAROUSEL_INTERVAL') ? (int) PANEL_CAROUSEL_INTERVAL : 15,
    ],
    'display' => [
        'format' => defined('DISPLAY_FORMAT') ? DISPLAY_FORMAT : '16:9',
        'theme' => defined('THEME_MODE') ? THEME_MODE : 'auto',
        'title' => defined('DISPLAY_TITLE') ? DISPLAY_TITLE : 'Flight Information',
    ],
    'carousel' => [
        'enabled' => defined('CAROUSEL_ENABLED') ? (bool) CAROUSEL_ENABLED : false,
        'interval' => defined('CAROUSEL_INTERVAL') ? (int) CAROUSEL_INTERVAL : 30,
        'pages' => defined('CAROUSEL_PAGES') && CAROUSEL_PAGES !== ''
            ? array_map('trim', explode(',', CAROUSEL_PAGES))
            : [],
    ],
]);
