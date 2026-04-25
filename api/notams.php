<?php
/**
 * PHP proxy for Autorouter NOTAM API
 * Fetches NOTAMs for the configured ICAO airport with OAuth2 + file-based caching
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: max-age=600'); // 10 minutes browser cache

$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

$clientId = defined('AUTOROUTER_CLIENT_ID') ? AUTOROUTER_CLIENT_ID : getenv('AUTOROUTER_CLIENT_ID');
$clientSecret = defined('AUTOROUTER_CLIENT_SECRET') ? AUTOROUTER_CLIENT_SECRET : getenv('AUTOROUTER_CLIENT_SECRET');
$icao = defined('ICAO_CODE') ? ICAO_CODE : 'ESMK';

if (empty($clientId) || empty($clientSecret)) {
    http_response_code(500);
    echo json_encode(['error' => 'Autorouter credentials not configured', 'notams' => []]);
    exit;
}

// Server-side NOTAM cache (10 min)
$cacheFile = sys_get_temp_dir() . "/notams_{$icao}.json";
$cacheTtl = 600;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    readfile($cacheFile);
    exit;
}

/**
 * Get a valid Autorouter OAuth2 access token (cached until expiry).
 */
function autorouter_get_token($clientId, $clientSecret) {
    $tokenFile = sys_get_temp_dir() . '/autorouter_token.json';

    if (file_exists($tokenFile)) {
        $cached = json_decode(@file_get_contents($tokenFile), true);
        if (is_array($cached) && isset($cached['access_token'], $cached['expires_at'])
            && $cached['expires_at'] > (time() + 30)) {
            return $cached['access_token'];
        }
    }

    $postData = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 10,
            'ignore_errors' => true,
        ]
    ]);

    $resp = @file_get_contents('https://api.autorouter.aero/v1.0/oauth2/token', false, $ctx);
    if ($resp === false) return null;

    $data = json_decode($resp, true);
    if (!is_array($data) || empty($data['access_token'])) return null;

    $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;
    $payload = [
        'access_token' => $data['access_token'],
        'expires_at' => time() + $expiresIn,
    ];
    @file_put_contents($tokenFile, json_encode($payload));
    return $data['access_token'];
}

$token = autorouter_get_token($clientId, $clientSecret);

if (!$token) {
    $fallback = json_encode(['notams' => [], 'icao' => $icao, 'fetchedAt' => date('c'), 'error' => 'Auth failed']);
    echo $fallback;
    exit;
}

// Fetch NOTAMs
$itemas = urlencode('["' . $icao . '"]');
$url = "https://api.autorouter.aero/v1.0/notam?itemas={$itemas}&offset=0&limit=20";

$ctx = stream_context_create([
    'http' => [
        'header' => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
        'timeout' => 10,
        'ignore_errors' => true,
    ]
]);

$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    $fallback = json_encode(['notams' => [], 'icao' => $icao, 'fetchedAt' => date('c'), 'error' => 'Upstream fetch failed']);
    echo $fallback;
    exit;
}


$data = json_decode($response, true);
$notams = [];

// Autorouter may return either a bare array or {rows: [...]}
$items = [];
if (is_array($data)) {
    if (isset($data['rows']) && is_array($data['rows'])) {
        $items = $data['rows'];
    } elseif (array_keys($data) === range(0, count($data) - 1)) {
        $items = $data;
    } elseif (isset($data['data']) && is_array($data['data'])) {
        $items = $data['data'];
    }
}

foreach ($items as $item) {
    // Body of NOTAM (item E)
    $body = $item['iteme'] ?? $item['itemE'] ?? $item['ItemE'] ?? $item['All'] ?? $item['all'] ?? $item['raw'] ?? '';
    $body = trim((string) $body);

    // NOTAM number — Autorouter uses series + number + /year (e.g. "B0726/26")
    $series = trim((string) ($item['series'] ?? ''));
    $number = $item['number'] ?? $item['Number'] ?? $item['id'] ?? '';
    $year = $item['year'] ?? null;
    if ($series !== '' && $number !== '' && $year !== null) {
        $id = sprintf('%s%04d/%02d', $series, (int) $number, (int) $year);
    } else {
        $id = (string) $number;
    }

    // Validity timestamps — Autorouter sends Unix epoch seconds; 2147483647 = PERM (int32 max)
    $startTs = $item['startvalidity'] ?? $item['StartValidity'] ?? $item['startValidity'] ?? null;
    $endTs = $item['endvalidity'] ?? $item['EndValidity'] ?? $item['endValidity'] ?? null;

    $effective = is_numeric($startTs) ? gmdate('c', (int) $startTs) : $startTs;
    $isPerm = is_numeric($endTs) && (int) $endTs >= 2147483647;
    if ($isPerm) {
        $expires = 'PERM';
    } elseif (is_numeric($endTs)) {
        $expires = gmdate('c', (int) $endTs);
    } else {
        $expires = $endTs;
    }

    $traffic = trim((string) ($item['traffic'] ?? $item['Traffic'] ?? ''));
    $purpose = trim((string) ($item['purpose'] ?? $item['Purpose'] ?? ''));
    $classification = $traffic !== '' ? $traffic : ($purpose !== '' ? $purpose : null);

    // Detect TWR/aerodrome hours-of-service NOTAM (always keep these, even if PERM)
    $isHoursOfService = preg_match('/HOURS?\s+OF\s+SERVICE|HRS?\s+OF\s+SVC/i', $body)
        && preg_match('/\bTWR\b|\bTOWER\b|\bAERODROME\b|\bAD\b/i', $body);

    // Skip permanent NOTAMs unless they describe operating hours
    if ($isPerm && !$isHoursOfService) {
        continue;
    }

    // Build summary from body (collapse whitespace, truncate)
    $summary = '';
    if ($body !== '') {
        $summary = trim(preg_replace('/\s+/', ' ', $body));
        if (mb_strlen($summary) > 240) {
            $summary = mb_substr($summary, 0, 240) . '…';
        }
    }

    $notams[] = [
        'id' => (string) $id,
        'raw' => $body,
        'summary' => $summary,
        'effective' => $effective,
        'expires' => $expires,
        'classification' => $classification,
    ];
}

$result = [
    'icao' => $icao,
    'notams' => $notams,
    'fetchedAt' => date('c'),
];

$json = json_encode($result);
@file_put_contents($cacheFile, $json);
echo $json;
