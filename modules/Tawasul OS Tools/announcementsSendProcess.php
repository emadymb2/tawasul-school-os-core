<?php
use TawasulOS\Data\Validator;

require_once '../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['bodyAr' => 'RAW', 'bodyEn' => 'RAW']);
$URL = $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Tools/announcements.php';

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Tools/announcements.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$clean = function ($v, $max) { return mb_substr(trim(strip_tags((string) $v)), 0, $max); };
$titleAr = $clean($_POST['titleAr'] ?? '', 255);
$titleEn = $clean($_POST['titleEn'] ?? '', 255);
$bodyAr  = $clean($_POST['bodyAr'] ?? '', 5000);
$bodyEn  = $clean($_POST['bodyEn'] ?? '', 5000);
if (($titleAr === '' && $titleEn === '') || ($bodyAr === '' && $bodyEn === '')) {
    header("Location: {$URL}&return=error1");
    exit;
}

// Only active staff can receive announcements.
if (($_POST['allStaff'] ?? 'N') === 'Y') {
    $people = $pdo->select("SELECT tawasulStaff.tawasulPersonID FROM tawasulStaff JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulStaff.tawasulPersonID) WHERE tawasulPerson.status='Full'")->fetchAll(\PDO::FETCH_COLUMN);
} else {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['recipients'] ?? [])))));
    $people = [];
    if (!empty($ids)) {
        $people = $pdo->select("SELECT tawasulStaff.tawasulPersonID FROM tawasulStaff JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulStaff.tawasulPersonID) WHERE tawasulPerson.status='Full' AND FIND_IN_SET(tawasulStaff.tawasulPersonID, :ids)", ['ids' => implode(',', $ids)])->fetchAll(\PDO::FETCH_COLUMN);
    }
}
if (empty($people)) {
    header("Location: {$URL}&return=error3");
    exit;
}

$id = $pdo->insert(
    'INSERT INTO tawasulAnnouncement (titleAr, bodyAr, titleEn, bodyEn, tawasulPersonIDCreator) VALUES (:titleAr, :bodyAr, :titleEn, :bodyEn, :creator)',
    ['titleAr' => $titleAr, 'bodyAr' => $bodyAr, 'titleEn' => $titleEn, 'bodyEn' => $bodyEn, 'creator' => $session->get('tawasulPersonID')]
);
if (empty($id)) {
    header("Location: {$URL}&return=error2");
    exit;
}

foreach (array_unique($people) as $personID) {
    $pdo->insert('INSERT IGNORE INTO tawasulAnnouncementRecipient (tawasulAnnouncementID, tawasulPersonID) VALUES (:a, :p)', ['a' => $id, 'p' => $personID]);
}

header('Location: '.$session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Tools/announcements_sent.php&return=success0');
