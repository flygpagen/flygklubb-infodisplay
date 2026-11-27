<?php
/**
 * PHP proxy for MyWebLog bookings using Main API v5
 * Fetches booking data and returns structured JSON
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

// Load API credentials from config
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

// Token from myWebLog (Integrations, API). Optional Client ID enables OAuth 2.0 (recommended in v5).
$bearerToken = defined('MYWEBLOG_BEARER_TOKEN') ? MYWEBLOG_BEARER_TOKEN : getenv('MYWEBLOG_BEARER_TOKEN');
$clientId = defined('MYWEBLOG_CLIENT_ID') ? MYWEBLOG_CLIENT_ID : getenv('MYWEBLOG_CLIENT_ID');

if (empty($bearerToken)) {
    http_response_code(500);
    echo json_encode(['error' => 'MyWebLog API credentials not configured. Add MYWEBLOG_BEARER_TOKEN to config.php']);
    exit;
}

define('MWL_BASE', 'https://api.myweblog.se/main/v5');
define('MWL_TOKEN_CACHE', sys_get_temp_dir() . '/myweblog_v5_token_' . md5((string)$bearerToken) . '.json');

/** Fetch (or reuse cached) OAuth 2.0 access token. Falls back to the raw token if OAuth fails. */
function getAccessToken($clientId, $token, $forceRefresh = false) {
    if (empty($clientId)) return $token; // direct token, accepted until 2027-12-31
    if (!$forceRefresh && is_readable(MWL_TOKEN_CACHE)) {
        $c = json_decode(@file_get_contents(MWL_TOKEN_CACHE), true);
        if (!empty($c['access_token']) && ($c['expires_at'] ?? 0) > time() + 60) return $c['access_token'];
    }
    $ch = curl_init(MWL_BASE . '/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']),
        CURLOPT_USERPWD => $clientId . ':' . $token,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$resp, true);
    if ($code === 200 && !empty($data['access_token'])) {
        @file_put_contents(MWL_TOKEN_CACHE, json_encode([
            'access_token' => $data['access_token'],
            'expires_at' => time() + (int)($data['expires_in'] ?? 3600),
        ]), LOCK_EX);
        return $data['access_token'];
    }
    return $token;
}

/** GET a v5 resource. No trailing slash (v5 returns 404 for that). */
function callMyWebLogAPIv5($endpoint, $token, $clientId, $queryParams = [], $retry = true) {
    $url = MWL_BASE . '/' . trim($endpoint, '/');
    if (!empty($queryParams)) $url .= '?' . http_build_query($queryParams);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . getAccessToken($clientId, $token, !$retry),
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) return ['error' => 'cURL error: ' . $error];
    if ($httpCode === 401 && $retry && !empty($clientId)) {
        return callMyWebLogAPIv5($endpoint, $token, $clientId, $queryParams, false);
    }
    $data = json_decode((string)$response, true);
    if ($httpCode !== 200) {
        $msg = $data['detail'] ?? $data['title'] ?? ('HTTP ' . $httpCode);
        return ['error' => $msg, 'raw_response' => substr((string)$response, 0, 500)];
    }
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON response', 'raw_response' => substr((string)$response, 0, 1000), 'json_error' => json_last_error_msg()];
    }
    return $data;
}

/** Fetch every page of a v5 list endpoint. */
function fetchAllV5($endpoint, $token, $clientId, $params) {
    $all = [];
    $limit = 100;
    for ($offset = 0, $page = 0; $page < 20; $page++, $offset += $limit) {
        $res = callMyWebLogAPIv5($endpoint, $token, $clientId, $params + ['limit' => $limit, 'offset' => $offset]);
        if (isset($res['error'])) return $page === 0 ? $res : ['data' => $all];
        $items = $res['data'] ?? [];
        $all = array_merge($all, $items);
        if (count($items) < $limit || empty($res['links']['next'])) break;
    }
    return ['data' => $all];
}

// Get today's date
$today = date('Y-m-d');
// MyWebLog returns all bookings whose period overlaps the from/to window,
// so requesting just today also includes multi-day bookings active today.
// API requires plain YYYY-MM-DD format (no time component).
$dateFrom = $today;
$dateTo   = $today;

// Fetch today's bookings (incl. overlapping multi-day bookings)
$bookingsData = fetchAllV5('bookings', $bearerToken, $clientId, [
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);

// Fetch object status for maintenance info
$objectStatusData = fetchAllV5('objects', $bearerToken, $clientId, ['category_id' => 0, 'include' => 'status']);

// Check for errors
if (isset($bookingsData['error'])) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Failed to fetch bookings: ' . $bookingsData['error'],
        'debug' => [
            'raw_response' => $bookingsData['raw_response'] ?? null,
            'json_error' => $bookingsData['json_error'] ?? null,
        ]
    ]);
    exit;
}

// Build maintenance status map from v5 objects (status ids: 0 OK, 1 yellow, 2 red)
$maintenanceObjects = [];
foreach (($objectStatusData['data'] ?? []) as $obj) {
    $reg = $obj['registration'] ?? '';
    $st = $obj['status']['total_id'] ?? null;
    if ($reg && ($st === 2 || $st === 1)) {
        $maintenanceObjects[$reg] = $st === 2 ? 'maintenance' : 'reserved';
    }
}

// Process bookings
$bookings = [];
$now = new DateTime('now', new DateTimeZone('Europe/Stockholm'));
$currentMinutes = (int)$now->format('H') * 60 + (int)$now->format('i');

$allBookings = $bookingsData['data'] ?? [];

foreach ($allBookings as $booking) {
        $aircraft = $booking['object']['registration'] ?? '';
    
    // Filter: Only include aircraft from configuration
    $aircraftFilter = defined('MYWEBLOG_OBJECT_FILTER') ? MYWEBLOG_OBJECT_FILTER : getenv('MYWEBLOG_OBJECT_FILTER');
    if (!preg_match($aircraftFilter, $aircraft)) {
        continue;
    }
    
    // Always show booking owner (teacher) name
    $pilot = $booking['user']['name'] ?? '';
    $startTime = $booking['start_local'] ?? '';
    $endTime = $booking['end_local'] ?? '';
    
    // Filter: Only include bookings that cover today's date
    if ($startTime && $endTime) {
        $bookingStartDate = date('Y-m-d', strtotime($startTime));
        $bookingEndDate = date('Y-m-d', strtotime($endTime));
        
        // Skip if booking ended before today or starts after today
        if ($bookingEndDate < $today || $bookingStartDate > $today) {
            continue;
        }
    }
    $bookingType = $booking['type'] ?? '';
    $remark = $booking['comment'] ?? '';
    $bookingId = $booking['id'] ?? uniqid();
    
    // Format time display
    $displayTime = 'Okänd';
    $startMinutes = null;
    $endMinutes = null;
    
    if ($startTime && $endTime) {
        // Parse dates to check if multi-day booking
        $startDate = date('Y-m-d', strtotime($startTime));
        $endDate = date('Y-m-d', strtotime($endTime));
        
        if ($startDate !== $endDate) {
            // Multi-day booking - show as "Heldag"
            $displayTime = 'Heldag';
            $startMinutes = 0;
            $endMinutes = 24 * 60; // Entire day
        } else {
            // Same-day booking - extract HH:MM
            $startHM = date('H:i', strtotime($startTime));
            $endHM = date('H:i', strtotime($endTime));
            $displayTime = $startHM . '-' . $endHM;
            
            // Calculate minutes for status
            $startMinutes = (int)date('H', strtotime($startTime)) * 60 + (int)date('i', strtotime($startTime));
            $endMinutes = (int)date('H', strtotime($endTime)) * 60 + (int)date('i', strtotime($endTime));
        }
    }
    
    // Determine status
    $status = 'upcoming';
    
    // Check if aircraft is in maintenance
    if (isset($maintenanceObjects[$aircraft])) {
        $status = $maintenanceObjects[$aircraft];
    }
    // Check for maintenance keywords in remark or type
    elseif (preg_match('/maintenance|underhåll|kontroll|service|MAINT/i', $remark . ' ' . $bookingType)) {
        $status = 'maintenance';
    }
    // Check for reserved keywords
    elseif (preg_match('/reserved|reserverad/i', $bookingType)) {
        $status = 'reserved';
    }
    // Calculate time-based status
    elseif ($startMinutes !== null && $endMinutes !== null) {
        if ($currentMinutes >= $endMinutes) {
            $status = 'completed';
        } elseif ($currentMinutes >= $startMinutes && $currentMinutes < $endMinutes) {
            $status = 'active';
        }
    }
    
    $bookings[] = [
        'id' => (string)$bookingId,
        'time' => $displayTime,
        'aircraft' => $aircraft,
        'pilot' => $pilot,
        'status' => $status,
    ];
}

// Sort by time
usort($bookings, function($a, $b) {
    return strcmp($a['time'], $b['time']);
});

echo json_encode([
    'bookings' => $bookings,
    'fetchedAt' => date('c'),
    'count' => count($bookings),
]);
