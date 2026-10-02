<?php
use TawasulOS\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Tools/inbox_view.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('Announcements Inbox'), 'inbox.php')
        ->add(__('Announcement'));
    $page->stylesheets->add('tos-module', 'modules/Tawasul OS Tools/css/module.css');

    $id = (int) ($_GET['tawasulAnnouncementID'] ?? 0);
    $personID = $session->get('tawasulPersonID');

    // Only the addressed staff member can open it.
    $a = $pdo->select(
        'SELECT a.*, r.timestampRead FROM tawasulAnnouncementRecipient r JOIN tawasulAnnouncement a ON (a.tawasulAnnouncementID=r.tawasulAnnouncementID)
         WHERE r.tawasulAnnouncementID=:a AND r.tawasulPersonID=:p',
        ['a' => $id, 'p' => $personID]
    )->fetch();

    if (empty($a)) {
        $page->addError(__('The specified record cannot be found.'));
    } else {
        if (empty($a['timestampRead'])) {
            $pdo->update('UPDATE tawasulAnnouncementRecipient SET timestampRead=NOW() WHERE tawasulAnnouncementID=:a AND tawasulPersonID=:p AND timestampRead IS NULL', ['a' => $id, 'p' => $personID]);
        }
        echo '<p class="tos-status">'.Format::dateTime($a['timestampCreated']).'</p>';
        echo '<div class="tos-drafts">';
        echo '<article class="tos-draft" dir="rtl" lang="ar"><h3>'.htmlspecialchars($a['titleAr']).'</h3><p>'.nl2br(htmlspecialchars($a['bodyAr'])).'</p></article>';
        echo '<article class="tos-draft" dir="ltr" lang="en"><h3>'.htmlspecialchars($a['titleEn']).'</h3><p>'.nl2br(htmlspecialchars($a['bodyEn'])).'</p></article>';
        echo '</div>';
    }
}
