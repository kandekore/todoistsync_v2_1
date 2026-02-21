<?php
/**
 * Todoist Webhook Receiver
 */
require_once __DIR__ . '/../../../init.php'; // Load WHMCS
require_once __DIR__ . '/lib/SyncService.php';

use WHMCS\Database\Capsule;

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_TODOIST_HMAC_SHA256'] ?? '';

$secret = Capsule::table('tbladdonmodules')
    ->where('module', 'todoistsync')
    ->where('setting', 'webhook_secret')
    ->value('value');

// Verify Signature
if (base64_encode(hash_hmac('sha256', $payload, $secret, true)) !== $signature) {
    header('HTTP/1.1 401 Unauthorized');
    die('Invalid signature');
}

$data = json_decode($payload, true);

if ($data['event_name'] === 'item:completed' || $data['event_name'] === 'item:deleted') {
    $todoistId = $data['event_data']['id'];
    (new TodoistSyncService())->completeFromTodoist($todoistId);
}

echo "OK";