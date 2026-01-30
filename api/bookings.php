<?php
/**
 * PHP proxy for MyWebLog bookings using Main API v4
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

// Get Bearer token from config or environment
$bearerToken = defined('MYWEBLOG_BEARER_TOKEN') ? MYWEBLOG_BEARER_TOKEN : getenv('MYWEBLOG_BEARER_TOKEN');

if (empty($bearerToken)) {
    http_response_code(500);
    echo json_encode(['error' => 'MyWebLog API credentials not configured. Add MYWEBLOG_BEARER_TOKEN to config.php']);
    exit;
}

/**
 * Make API call to MyWebLog Main API v4
 * Uses Bearer token authentication and RESTful endpoints
 */
function callMyWebLogAPIv4($endpoint, $bearerToken, $queryParams = []) {
    $url = 'https://api.myweblog.se/main/v4/' . $endpoint . '/';
    
    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }
    
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $bearerToken,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => 'cURL error: ' . $error];
    }
    
    if ($httpCode !== 200) {
        return ['error' => 'HTTP ' . $httpCode, 'raw_response' => substr($response, 0, 500)];
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'error' => 'Invalid JSON response',
            'raw_response' => substr($response, 0, 1000),
            'json_error' => json_last_error_msg()
        ];
    }
    
    // Check for v4 error format
    if (isset($data['errors']) && is_array($data['errors'])) {
        $errorMsg = $data['errors'][0]['message'] ?? 'Unknown API error';
        return ['error' => $errorMsg];
    }
    
    return $data;
}

// Get today's date
$today = date('Y-m-d');
// Look back 365 days to catch long-term maintenance bookings
$lookbackDate = date('Y-m-d', strtotime('-365 days'));
// Look forward 30 days to catch upcoming bookings
$futureDate = date('Y-m-d', strtotime('+30 days'));

// Fetch bookings using v4 API with extended date range
$bookingsData = callMyWebLogAPIv4('bookings', $bearerToken, [
    'date_from' => $lookbackDate,
    'date_to' => $futureDate,
]);

// Fetch object status for maintenance info
$objectStatusData = callMyWebLogAPIv4('objects/status', $bearerToken, []);

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

// Build maintenance status map from objectStatus
$maintenanceObjects = [];
if (isset($objectStatusData['objects']) && is_array($objectStatusData['objects'])) {
    foreach ($objectStatusData['objects'] as $obj) {
        // status 2 = red/maintenance, status 1 = yellow/limited
        if (isset($obj['status']) && ($obj['status'] == 2 || $obj['status'] == 1)) {
            $reg = $obj['registration'] ?? '';
            if ($reg) {
                $maintenanceObjects[$reg] = $obj['status'] == 2 ? 'maintenance' : 'reserved';
            }
        }
    }
}
// Fallback: try legacy structure if 'objects' key doesn't exist
if (empty($maintenanceObjects) && isset($objectStatusData['status']) && $objectStatusData['status'] === 'ok' && isset($objectStatusData['response'])) {
    foreach ($objectStatusData['response'] as $obj) {
        if (isset($obj['status']) && ($obj['status'] == 2 || $obj['status'] == 1)) {
            $reg = $obj['registration'] ?? '';
            if ($reg) {
                $maintenanceObjects[$reg] = $obj['status'] == 2 ? 'maintenance' : 'reserved';
            }
        }
    }
}

// Process bookings - v4 returns bookings directly in 'bookings' array
$bookings = [];
$now = new DateTime('now', new DateTimeZone('Europe/Stockholm'));
$currentMinutes = (int)$now->format('H') * 60 + (int)$now->format('i');

// Get all bookings from v4 response
$allBookings = $bookingsData['bookings'] ?? [];

foreach ($allBookings as $booking) {
    // v4 API uses nested structure
    $aircraft = $booking['object']['registration'] ?? '';
    
    // Filter: Only include aircraft (Swedish registration format SE-XXX)
    if (!preg_match('/^SE-[A-Z]{3}$/', $aircraft)) {
        continue;
    }
    
    // Always show booking owner (teacher) name
    $pilot = $booking['users']['booking_owner']['name'] ?? '';
    $startTime = $booking['times']['start_local'] ?? '';
    $endTime = $booking['times']['end_local'] ?? '';
    
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
