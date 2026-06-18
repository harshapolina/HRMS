<?php
// batch_quality_range.php
// Wrapper to concurrently fetch get_user_quality_range.php for multiple users

session_start();

if (!isset($_SESSION['tablename'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// CRITICAL: Release session lock so that loopback requests do not deadlock waiting for session files!
session_write_close();

$user_ids_raw = isset($_GET['user_ids']) ? $_GET['user_ids'] : '';
$user_ids = array_filter(array_map('trim', explode(',', $user_ids_raw)));

$results = [];

if (empty($user_ids)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

// Determine protocol and host for loopback
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = dirname($_SERVER['PHP_SELF']);
if ($path === '\\' || $path === '/') {
    $path = '';
}
$base_url = "$protocol://$host$path/get_user_quality_range.php";

// Forward all GET parameters except 'user_ids'
$query_params = $_GET;
unset($query_params['user_ids']);

$mh = curl_multi_init();

// Reconstruct cookies for authentication
$cookies = [];
foreach ($_COOKIE as $name => $value) {
    $cookies[] = "$name=$value";
}
$cookie_str = implode('; ', $cookies);

// Process in chunks of 50
$chunked_users = array_chunk($user_ids, 50);

foreach ($chunked_users as $chunk) {
    $ch_map = [];

    foreach ($chunk as $uid) {
        $params = $query_params;
        $params['user_id'] = $uid;
        $url = $base_url . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, $cookie_str); // forward auth
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Localhost loopback protections
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        }

        curl_multi_add_handle($mh, $ch);
        $ch_map[(int)$ch] = $uid;
    }

    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($mh) != -1) {
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }

    while ($info = curl_multi_info_read($mh)) {
        $ch = $info['handle'];
        $uid = $ch_map[(int)$ch];
        $response = curl_multi_getcontent($ch);
        
        $decoded = json_decode($response, true);
        if ($decoded && isset($decoded['status']) && $decoded['status'] === 'success') {
            $results[$uid] = $decoded['quality_range'] ?? 0;
        } else {
            $results[$uid] = 0;
        }
        
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
}

curl_multi_close($mh);

// Return full compiled map
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'data' => $results
]);
