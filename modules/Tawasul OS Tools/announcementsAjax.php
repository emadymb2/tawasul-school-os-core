<?php
use TawasulOS\Domain\System\SettingGateway;

require_once '../../tawasul.php';

header('Content-Type: application/json; charset=utf-8');

function tosReply($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Tools/announcements.php') == false) {
    tosReply(['error' => __('You do not have access to this action.')], 403);
}

// This endpoint is not a *Process.php, so tawasul.php does not run its CSRF
// check for it. Validate the token the form already carries.
$csrf = $session->get('csrftoken');
if (empty($csrf) || !hash_equals((string) $csrf, (string) ($_POST['csrftoken'] ?? ''))) {
    tosReply(['error' => __('Your session has expired. Please reload the page.')], 403);
}

$tones = ['formal', 'friendly', 'urgent'];
$payload = [
    'title'    => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 200),
    'audience' => mb_substr(trim((string) ($_POST['audience'] ?? 'All staff')), 0, 120),
    'date'     => mb_substr(trim((string) ($_POST['date'] ?? '')), 0, 60),
    'details'  => mb_substr(trim((string) ($_POST['details'] ?? '')), 0, 3000),
    'tone'     => in_array($_POST['tone'] ?? '', $tones, true) ? $_POST['tone'] : 'formal',
];
if (mb_strlen($payload['title']) < 2 || mb_strlen($payload['details']) < 5) {
    tosReply(['error' => __('Please enter a title and some details.')], 400);
}

$settingGateway = $container->get(SettingGateway::class);
$serviceURL = $settingGateway->getSettingByScope('Tawasul OS Tools', 'aiServiceURL');
$token = $settingGateway->getSettingByScope('Tawasul OS Tools', 'aiServiceToken');
if (empty($serviceURL) || empty($token)) {
    tosReply(['error' => __('The announcement service is not set up yet. Ask an administrator to open Announcement Service Settings.')], 503);
}

$ch = curl_init($serviceURL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer '.$token],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$response = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    tosReply(['error' => __('The drafting service could not be reached.')], 502);
}
$data = json_decode($response, true);
if ($status === 401) {
    tosReply(['error' => __('The announcement service token is incorrect. Ask an administrator to update it.')], 502);
}
if (!is_array($data)) {
    tosReply(['error' => __('The drafting service returned an unexpected reply.')], 502);
}
tosReply(['draft' => $data['draft'] ?? null, 'error' => $data['error'] ?? null], $status >= 400 ? 502 : 200);
