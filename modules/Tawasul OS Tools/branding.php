<?php
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Form;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Tools/branding.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Login Branding'));
    $page->stylesheets->add('tos-module', 'modules/Tawasul OS Tools/css/module.css');

    $settingGateway = $container->get(SettingGateway::class);
    $name      = $settingGateway->getSettingByScope('System', 'organisationName');
    $logo      = $settingGateway->getSettingByScope('System', 'organisationLogo');
    $useCustom = $settingGateway->getSettingByScope('Tawasul OS Tools', 'brandUseCustom');
    $primary   = tosValidHex($settingGateway->getSettingByScope('Tawasul OS Tools', 'brandPrimary'), '#1f4a33');
    $accent    = tosValidHex($settingGateway->getSettingByScope('Tawasul OS Tools', 'brandAccent'), '#e8664f');
    $highlight = tosValidHex($settingGateway->getSettingByScope('Tawasul OS Tools', 'brandHighlight'), '#e9b949');
    $logoURL   = !empty($logo) ? $session->get('absoluteURL').'/'.ltrim($logo, '/') : '';

    echo '<p>'.__('Changes appear in the live preview immediately and apply to the login page only when you press Submit.').'</p>';
    echo '<div class="tos-branding">';

    $form = Form::create('tosBranding', $session->get('absoluteURL').'/modules/Tawasul OS Tools/brandingProcess.php');
    $form->setAttribute('enctype', 'multipart/form-data');
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addLabel('organisationName', __('School Name'))->description(__('Shown on the login page, header and emails.'));
        $row->addTextField('organisationName')->setValue($name)->required()->maxLength(50);

    $row = $form->addRow();
        $row->addLabel('organisationLogoFile', __('School Logo'))->description(__('JPG, GIF or PNG.'));
        $row->addFileUpload('organisationLogoFile')->accepts('.jpg,.jpeg,.gif,.png')
            ->setAttachment('organisationLogo', $session->get('absoluteURL'), $logo);

    $presets = '';
    foreach (tosPresets() as $key => $p) {
        $presets .= '<button type="button" class="tos-preset" data-primary="'.$p['primary'].'" data-accent="'.$p['accent'].'" data-highlight="'.$p['highlight'].'">'
            .'<i style="background:'.$p['primary'].'"></i><i style="background:'.$p['accent'].'"></i><i style="background:'.$p['highlight'].'"></i>'
            .htmlspecialchars(__($p['label'])).'</button>';
    }
    $row = $form->addRow();
        $row->addLabel('presets', __('Colour Presets'));
        $row->addContent('<div class="tos-presets">'.$presets.'</div>');

    $row = $form->addRow();
        $row->addLabel('brandUseCustom', __('Use Custom Colours'))->description(__('When off, the Theme Colour from Display Settings is used.'));
        $row->addYesNo('brandUseCustom')->selected($useCustom === 'Y' ? 'Y' : 'N');

    foreach (['brandPrimary' => [__('Main Colour'), $primary], 'brandAccent' => [__('Accent Colour'), $accent], 'brandHighlight' => [__('Highlight Colour'), $highlight]] as $field => $info) {
        $row = $form->addRow();
            $row->addLabel($field, $info[0]);
            $row->addContent('<input type="color" name="'.$field.'" id="'.$field.'" value="'.$info[1].'" class="tos-color">');
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
    ?>
    <section class="tos-preview-panel" aria-label="<?php echo __('Live preview'); ?>">
        <h3><?php echo __('Live preview'); ?> <span id="tosDirty" class="tos-badge" hidden><?php echo __('Not saved'); ?></span></h3>
        <div id="tosPreview" class="tos-login-stage" style="--p:<?php echo $primary; ?>;--a:<?php echo $accent; ?>;--h:<?php echo $highlight; ?>">
            <i class="tos-shape tos-shape-a"></i><i class="tos-shape tos-shape-h"></i>
            <div class="tos-login-card">
                <div class="tos-login-logo"><img id="tosPreviewLogo" src="<?php echo htmlspecialchars($logoURL); ?>" alt="" <?php echo $logoURL ? '' : 'hidden'; ?>></div>
                <strong id="tosPreviewName"><?php echo htmlspecialchars($name); ?></strong>
                <span class="tos-field">اسم المستخدم · Username</span>
                <span class="tos-field">كلمة المرور · Password</span>
                <span class="tos-login-button">تسجيل الدخول · Login</span>
            </div>
        </div>
    </section>
    </div>
    <script>
    (function () {
        var form = document.getElementById('tosBranding');
        var preview = document.getElementById('tosPreview');
        var saved = { p: '<?php echo $primary; ?>', a: '<?php echo $accent; ?>', h: '<?php echo $highlight; ?>' };
        var defaults = { p: '#1f4a33', a: '#e8664f', h: '#e9b949' };
        function f(n) { return form.querySelector('[name="' + n + '"]'); }
        function custom() { var el = form.querySelector('[name="brandUseCustom"]:checked') || f('brandUseCustom'); return el && el.value === 'Y'; }
        function render() {
            var on = custom();
            preview.style.setProperty('--p', on ? f('brandPrimary').value : defaults.p);
            preview.style.setProperty('--a', on ? f('brandAccent').value : defaults.a);
            preview.style.setProperty('--h', on ? f('brandHighlight').value : defaults.h);
            document.getElementById('tosPreviewName').textContent = f('organisationName').value;
            document.getElementById('tosDirty').hidden = false;
        }
        form.addEventListener('input', render);
        form.addEventListener('change', function (e) {
            if (e.target.name === 'organisationLogoFile' && e.target.files && e.target.files[0]) {
                var img = document.getElementById('tosPreviewLogo');
                img.src = URL.createObjectURL(e.target.files[0]); img.hidden = false;
            }
            render();
        });
        document.querySelectorAll('.tos-preset').forEach(function (b) {
            b.addEventListener('click', function () {
                f('brandPrimary').value = b.dataset.primary; f('brandAccent').value = b.dataset.accent; f('brandHighlight').value = b.dataset.highlight;
                var yes = form.querySelector('[name="brandUseCustom"][value="Y"]') || f('brandUseCustom');
                if (yes.type === 'radio') yes.checked = true; else yes.value = 'Y';
                render();
            });
        });
    })();
    </script>
    <?php
}
