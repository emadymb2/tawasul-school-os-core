<?php
use TawasulOS\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Tools/announcements_sent.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Sent Announcements'));
    $page->stylesheets->add('tos-module', 'modules/Tawasul OS Tools/css/module.css');

    $announcements = $pdo->select(
        'SELECT a.tawasulAnnouncementID, a.titleAr, a.titleEn, a.timestampCreated,
                COUNT(r.tawasulAnnouncementRecipientID) AS total, SUM(r.timestampRead IS NOT NULL) AS readCount
         FROM tawasulAnnouncement a LEFT JOIN tawasulAnnouncementRecipient r ON (r.tawasulAnnouncementID=a.tawasulAnnouncementID)
         GROUP BY a.tawasulAnnouncementID ORDER BY a.timestampCreated DESC LIMIT 100'
    )->fetchAll();

    if (empty($announcements)) {
        echo '<div class="message">'.__('No announcements have been sent yet.').'</div>';
        return;
    }

    foreach ($announcements as $a) {
        $people = $pdo->select(
            'SELECT p.title, p.preferredName, p.surname, r.timestampRead FROM tawasulAnnouncementRecipient r
             JOIN tawasulPerson p ON (p.tawasulPersonID=r.tawasulPersonID) WHERE r.tawasulAnnouncementID=:a ORDER BY r.timestampRead IS NULL DESC, p.surname',
            ['a' => $a['tawasulAnnouncementID']]
        )->fetchAll();

        echo '<details class="tos-sent"><summary><span><strong dir="rtl" lang="ar">'.htmlspecialchars($a['titleAr']).'</strong> · '.htmlspecialchars($a['titleEn']).'</span>'
            .'<span class="tos-badge">'.sprintf(__('%1$s of %2$s read'), (int) $a['readCount'], (int) $a['total']).'</span></summary><ul>';
        foreach ($people as $p) {
            $name = Format::name($p['title'], $p['preferredName'], $p['surname'], 'Staff', false, true);
            echo '<li class="'.($p['timestampRead'] ? 'tos-read' : 'tos-unread').'"><span class="tos-dot" aria-hidden="true"></span>'.htmlspecialchars($name)
                .' — '.($p['timestampRead'] ? __('Read').' '.Format::dateTime($p['timestampRead']) : __('Unread')).'</li>';
        }
        echo '</ul><small>'.Format::dateTime($a['timestampCreated']).'</small></details>';
    }
}
