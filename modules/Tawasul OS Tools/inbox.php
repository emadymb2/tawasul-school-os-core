<?php
use TawasulOS\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Tools/inbox.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Announcements Inbox'));
    $page->stylesheets->add('tos-module', 'modules/Tawasul OS Tools/css/module.css');

    $rows = $pdo->select(
        'SELECT a.tawasulAnnouncementID, a.titleAr, a.titleEn, a.timestampCreated, r.timestampRead
         FROM tawasulAnnouncementRecipient r JOIN tawasulAnnouncement a ON (a.tawasulAnnouncementID=r.tawasulAnnouncementID)
         WHERE r.tawasulPersonID=:p ORDER BY a.timestampCreated DESC',
        ['p' => $session->get('tawasulPersonID')]
    )->fetchAll();

    $unread = count(array_filter($rows, function ($r) { return empty($r['timestampRead']); }));
    echo '<p class="tos-inbox-summary">'.sprintf(__('%1$s unread of %2$s'), $unread, count($rows)).'</p>';

    if (empty($rows)) {
        echo '<div class="message">'.__('No announcements yet.').'</div>';
    } else {
        echo '<ul class="tos-inbox">';
        foreach ($rows as $r) {
            $isRead = !empty($r['timestampRead']);
            $link = $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Tools/inbox_view.php&tawasulAnnouncementID='.$r['tawasulAnnouncementID'];
            echo '<li class="'.($isRead ? 'tos-read' : 'tos-unread').'"><a href="'.htmlspecialchars($link).'">'
                .'<span class="tos-dot" aria-hidden="true"></span>'
                .'<span class="tos-inbox-titles"><strong dir="rtl" lang="ar">'.htmlspecialchars($r['titleAr']).'</strong><span>'.htmlspecialchars($r['titleEn']).'</span></span>'
                .'<span class="tos-inbox-meta"><span class="tos-state">'.($isRead ? __('Read') : __('Unread')).'</span>'.Format::dateTime($r['timestampCreated']).'</span>'
                .'</a></li>';
        }
        echo '</ul>';
    }
}
