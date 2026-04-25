<?php
/**
 * Configuration file for API keys and location settings
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to config.php
 * 2. Replace the placeholders with your actual values
 * 3. Make sure config.php is NOT committed to version control
 */

// CheckWX API key for weather data
define('CHECKWX_API_KEY', 'din-apinyckel-här');

// MyWebLog API v4 credentials (get Bearer token from ADMIN > Integration/API)
define('MYWEBLOG_BEARER_TOKEN', 'din-apinyckel-här');

// Autorouter API credentials for NOTAM data
// Register at https://www.autorouter.aero and create an API client to get these
define('AUTOROUTER_CLIENT_ID', 'your-autorouter-client-id-here');
define('AUTOROUTER_CLIENT_SECRET', 'your-autorouter-client-secret-here');

// Airport/Location settings
define('ICAO_CODE', 'ESMK');                      // ICAO code for weather data f.ex. ESMK
define('LOCATION_LAT', 55.92);                    // Latitude for sun calculations
define('LOCATION_LON', 14.08);                    // Longitude for sun calculations
define('LOCATION_TIMEZONE', 'Europe/Stockholm');  // Timezone for display

// Runway settings
define('RUNWAY_HEADING', 10);                     // Primary runway heading in degrees (01 = 10°, 19 = 190°)

// Display settings
define('DISPLAY_FORMAT', '16:9');                  // '4:3' (1440x1080), '16:9' (1920x1080), or '9:16' (1080x1920 portrait)
define('THEME_MODE', 'auto');                     // 'day', 'night', or 'auto' (switches at sunrise/sunset)
define('RADAR_URL', 'https://embed.windy.com/embed.html?type=map&location=coordinates&metricRain=mm&metricTemp=°C&metricWind=kt&zoom=8&overlay=radar&product=radar&level=surface&lat=55.936&lon=14.182&message=true');      // Add url or IP to local ta1090 or other web service

// Panel carousel – rotate content in the radar panel area
define('PANEL_CAROUSEL_URLS', '');                  // Comma-separated URLs (if empty, only RADAR_URL is shown)
define('PANEL_CAROUSEL_INTERVAL', 30);              // Seconds per page in the panel

// Display title
define('DISPLAY_TITLE', 'Flight information');     // Header title text

// Carousel settings – rotate between kiosk and external pages
define('CAROUSEL_ENABLED', false);                 // true/false
define('CAROUSEL_INTERVAL', 30);                   // Seconds per page
define('CAROUSEL_PAGES', '');                      // Comma-separated URLs (kiosk is always page 1)

// NOTAM panel (under bookings)
define('NOTAM_ENABLED', true);                     // true/false – show NOTAM panel for ICAO_CODE
