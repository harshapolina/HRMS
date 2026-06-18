<?php
include '../config.php';

// Initialize database connection
$config = new Config();
$db = $config->getConnection();

// Set timezone
date_default_timezone_set('Asia/Kolkata');
$updated_at = date("Y-m-d H:i:s");
// Debug log file
$log_file = 'fb_debug_log.txt';
file_put_contents($log_file, "\n--- Callback hit at: " . date("Y-m-d H:i:s") . " ---\n", FILE_APPEND);

// Check for 'code'
if (!isset($_GET['code'])) {
    file_put_contents($log_file, "Error: Missing Facebook 'code' parameter.\n", FILE_APPEND);
    die('Missing Facebook code parameter.');
}

$code = $_GET['code'];
$client_id = "1322640825707670";
$client_secret = "e02259ea4b03c94126ce903e18719545";
$redirect_uri = "https://www.searchhomesindia.in/superadmin_new/myapicontainer/facebook/callback";

$token_url = "https://graph.facebook.com/v15.0/oauth/access_token?" .
             "client_id=$client_id&redirect_uri=$redirect_uri&client_secret=$client_secret&code=$code";

file_put_contents($log_file, "Token URL: $token_url\n", FILE_APPEND);

try {
    $response = file_get_contents($token_url);
    $data = json_decode($response, true);
    file_put_contents($log_file, "Token Response: " . print_r($data, true) . "\n", FILE_APPEND);

    if (!isset($data['access_token'])) {
        file_put_contents($log_file, "Error: Access token not found.\n", FILE_APPEND);
        die("Error: Access token not found.");
    }

    $access_token = $data['access_token'];
    $expires_in = $data['expires_in'] ?? (60 * 60 * 24 * 60);
    $expires_at = date("Y-m-d H:i:s", time() + $expires_in);
    $refresh_token = $data['refresh_token'] ?? '';

    // Prepare DB statement
    $stmt = $db->prepare("
        INSERT INTO facebook_tokens (user_id, access_token, refresh_token, page_id, page_access_token, expires_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        access_token = VALUES(access_token), 
        refresh_token = VALUES(refresh_token), 
        page_access_token = VALUES(page_access_token), 
        expires_at = VALUES(expires_at),
        updated_at = VALUES(updated_at)

    ");

    // Fetch pages using pagination
    $pages_url = "https://graph.facebook.com/v15.0/me/accounts?access_token=$access_token";
    $page_count = 0;

    do {
        file_put_contents($log_file, "Fetching pages from: $pages_url\n", FILE_APPEND);

        $pages_response = file_get_contents($pages_url);
        $pages_data = json_decode($pages_response, true);
        file_put_contents($log_file, "Pages Response: " . print_r($pages_data, true) . "\n", FILE_APPEND);

        if (isset($pages_data['data'])) {
            foreach ($pages_data['data'] as $page) {
                $page_id = $page['id'];
                $page_access_token = $page['access_token'];

                try {
                    $stmt->execute([1, $access_token, $refresh_token, $page_id, $page_access_token, $expires_at, $updated_at]);
                    $page_count++;
                    file_put_contents($log_file, "Inserted Page ID: $page_id\n", FILE_APPEND);
                } catch (PDOException $e) {
                    file_put_contents($log_file, "DB Insert Error for Page ID $page_id: " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
        }

        $pages_url = $pages_data['paging']['next'] ?? null;

    } while ($pages_url);

    file_put_contents($log_file, "Total Pages Inserted: $page_count\n", FILE_APPEND);

    // Redirect after success
    header("Location: /superadmin_new/myapicontainer/create-api-view/manage_apis");
    exit;

} catch (Exception $e) {
    $error_msg = "Exception occurred: " . $e->getMessage();
    file_put_contents($log_file, $error_msg . "\n", FILE_APPEND);
    die($error_msg);
}
?>