<?php
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Form;

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Tools/settings.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Announcement Service Settings'));

    $settingGateway = $container->get(SettingGateway::class);
    $form = Form::create('tosSettings', $session->get('absoluteURL').'/modules/Tawasul OS Tools/settingsProcess.php');
    $form->addHiddenValue('address', $session->get('address'));

    $setting = $settingGateway->getSettingByScope('Tawasul OS Tools', 'aiServiceURL', true);
    $row = $form->addRow();
        $row->addLabel('aiServiceURL', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addURL('aiServiceURL')->setValue($setting['value'])->required()->maxLength(255);

    $setting = $settingGateway->getSettingByScope('Tawasul OS Tools', 'aiServiceToken', true);
    $row = $form->addRow();
        $row->addLabel('aiServiceToken', __($setting['nameDisplay']))->description(__('Leave blank to keep the current token.'));
        $row->addPassword('aiServiceToken')->maxLength(255)->setAttribute('autocomplete', 'new-password');

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
