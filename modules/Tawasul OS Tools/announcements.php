<?php
use TawasulOS\Forms\Form;

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Tools/announcements.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Announcement Writer'));
    $page->stylesheets->add('tos-module', 'modules/Tawasul OS Tools/css/module.css');

    echo '<p>'.__('Enter the announcement details. AI drafts polished Arabic and English versions that you can edit and copy into Messenger.').'</p>';

    $form = Form::create('tawasulAnnouncement', '#');
    $form->setAttribute('onsubmit', 'return false;');
    $form->addHiddenValue('tosAjaxURL', $session->get('absoluteURL').'/modules/Tawasul OS Tools/announcementsAjax.php');

    $row = $form->addRow();
        $row->addLabel('title', __('Title'));
        $row->addTextField('title')->required()->maxLength(200);
    $row = $form->addRow();
        $row->addLabel('audience', __('Audience'));
        $row->addTextField('audience')->setValue(__('All staff'))->maxLength(120);
    $row = $form->addRow();
        $row->addLabel('date', __('Date'));
        $row->addTextField('date')->maxLength(60)->placeholder('Saturday 19 September, 10:00');
    $row = $form->addRow();
        $row->addLabel('tone', __('Tone'));
        $row->addSelect('tone')->fromArray(['formal' => __('Formal'), 'friendly' => __('Friendly'), 'urgent' => __('Urgent')])->selected('formal');
    $row = $form->addRow();
        $row->addLabel('details', __('Details'))->description(__('Key points, location and what staff must do.'));
        $row->addTextArea('details')->setRows(6)->required()->maxLength(3000);
    $row = $form->addRow();
        $row->addContent('<button type="button" id="tosDraftButton" class="tos-primary">'.__('Draft with AI').'</button>');

    echo $form->getOutput();
    ?>
    <p id="tosDraftStatus" class="tos-status" role="status" aria-live="polite"></p>
    <div id="tosDraftResult" class="tos-drafts" hidden>
        <section class="tos-draft" dir="rtl" lang="ar">
            <input type="text" id="tosArTitle" aria-label="العنوان بالعربية">
            <textarea id="tosArBody" rows="8" aria-label="النص بالعربية"></textarea>
            <button type="button" class="tos-copy" data-target="ar">نسخ</button>
        </section>
        <section class="tos-draft" dir="ltr" lang="en">
            <input type="text" id="tosEnTitle" aria-label="English title">
            <textarea id="tosEnBody" rows="8" aria-label="English text"></textarea>
            <button type="button" class="tos-copy" data-target="en">Copy</button>
        </section>
    </div>
    <?php
    $send = Form::create('tosSend', $session->get('absoluteURL').'/modules/Tawasul OS Tools/announcementsSendProcess.php');
    $send->setFactory(TawasulOS\Forms\DatabaseFormFactory::create($pdo));
    $send->addHiddenValue('address', $session->get('address'));
    foreach (['titleAr', 'bodyAr', 'titleEn', 'bodyEn'] as $h) {
        $send->addHiddenValue($h, '');
    }
    $send->addRow()->addHeading('send', __('Send to staff'));
    $row = $send->addRow();
        $row->addLabel('allStaff', __('All Staff'))->description(__('Send to every active staff member.'));
        $row->addYesNo('allStaff')->selected('N');
    $row = $send->addRow();
        $row->addLabel('recipients', __('Staff Members'))->description(__('Or choose specific people.'));
        $row->addSelectStaff('recipients')->selectMultiple();
    $row = $send->addRow();
        $row->addFooter();
        $row->addSubmit(__('Send announcement'));
    echo '<div id="tosSendWrap" hidden>'.$send->getOutput().'</div>';
    ?>
    <script>
    (function () {
        var sendWrap = document.getElementById('tosSendWrap');
        var sendForm = document.getElementById('tosSend');
        new MutationObserver(function () { sendWrap.hidden = document.getElementById('tosDraftResult').hidden; })
            .observe(document.getElementById('tosDraftResult'), { attributes: true });
        sendForm.addEventListener('submit', function () {
            [['titleAr', 'tosArTitle'], ['bodyAr', 'tosArBody'], ['titleEn', 'tosEnTitle'], ['bodyEn', 'tosEnBody']].forEach(function (p) {
                sendForm.querySelector('[name="' + p[0] + '"]').value = document.getElementById(p[1]).value;
            });
        });
    })();
    </script>
    <script>
    (function () {
        var btn = document.getElementById('tosDraftButton');
        var status = document.getElementById('tosDraftStatus');
        var result = document.getElementById('tosDraftResult');
        function val(n) { var el = document.querySelector('#tawasulAnnouncement [name="' + n + '"]'); return el ? el.value.trim() : ''; }
        btn.addEventListener('click', function () {
            if (val('title').length < 2 || val('details').length < 5) { status.textContent = 'Please enter a title and some details.'; return; }
            btn.disabled = true; status.textContent = 'Writing Arabic and English versions…';
            var body = new FormData();
            ['title', 'audience', 'date', 'tone', 'details', 'csrftoken'].forEach(function (n) { body.append(n, val(n)); });
            fetch(val('tosAjaxURL'), { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || d.error || !d.draft) { status.textContent = (d && d.error) || 'The draft could not be created.'; return; }
                    document.getElementById('tosArTitle').value = d.draft.arabicTitle;
                    document.getElementById('tosArBody').value = d.draft.arabicBody;
                    document.getElementById('tosEnTitle').value = d.draft.englishTitle;
                    document.getElementById('tosEnBody').value = d.draft.englishBody;
                    result.hidden = false; status.textContent = 'Draft ready. Review and edit before sending.';
                })
                .catch(function () { status.textContent = 'The drafting service could not be reached.'; })
                .finally(function () { btn.disabled = false; });
        });
        result.addEventListener('click', function (e) {
            var t = e.target.getAttribute('data-target'); if (!t) return;
            var p = t === 'ar' ? 'tosAr' : 'tosEn';
            navigator.clipboard && navigator.clipboard.writeText(document.getElementById(p + 'Title').value + '\n\n' + document.getElementById(p + 'Body').value);
            status.textContent = t === 'ar' ? 'تم النسخ' : 'Copied';
        });
    })();
    </script>
    <?php
}
