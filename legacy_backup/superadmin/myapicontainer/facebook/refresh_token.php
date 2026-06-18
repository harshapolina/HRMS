<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include '../config.php';

// Initialize database connection
$config = new Config();
$db = $config->getConnection();

$stmt = $db->prepare("SELECT * FROM facebook_tokens WHERE expires_at <= NOW()");
$stmt->execute();
$tokens = $stmt->fetchAll();

foreach ($tokens as $token) {
    $refresh_token = $token['refresh_token'];
    $client_id = "1994857044364536";
    $client_secret = "51f66c4be26198f0af98197f7a12efeb";

    $refresh_url = "https://graph.facebook.com/v15.0/oauth/access_token?" .
                   "grant_type=fb_exchange_token&client_id=$client_id&client_secret=$client_secret&fb_exchange_token=$refresh_token";

    $response = file_get_contents($refresh_url);
    $data = json_decode($response, true);

    $new_access_token = $data['access_token'];
    $expires_in = $data['expires_in'];
    $expires_at = date("Y-m-d H:i:s", time() + $expires_in);

    $stmt = $db->prepare("UPDATE facebook_tokens SET access_token = ?, expires_at = ? WHERE id = ?");
    $stmt->execute([$new_access_token, $expires_at, $token['id']]);
}

echo "Tokens refreshed successfully.";
?>
