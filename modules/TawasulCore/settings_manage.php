<?php
/*
TawasulOS REST API — Manage Settings
Licensed under the GNU General Public License v3 or later.
*/

use Tos\Forms\Form;
use Tos\Domain\System\SettingGateway;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulCore/settings_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Manage Settings'));

    $settingGateway = $container->get(SettingGateway::class);

    $form = Form::create('apiSettings', $session->get('absoluteURL').'/modules/TawasulCore/settings_manageProcess.php');
    $form->setFactory(\Tos\Forms\DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));

    $form->addRow()->addHeading(__('Availability'));

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'apiEnabled', true);
    $row = $form->addRow();
        $row->addLabel('apiEnabled', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addYesNo('apiEnabled')->required()->selected($setting['value']);

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'allowWrites', true);
    $row = $form->addRow();
        $row->addLabel('allowWrites', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addYesNo('allowWrites')->required()->selected($setting['value']);

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'exposeModules', true);
    $row = $form->addRow();
        $row->addLabel('exposeModules', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addYesNo('exposeModules')->required()->selected($setting['value']);

    $form->addRow()->addHeading(__('Authentication'));

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'allowPasswordGrant', true);
    $row = $form->addRow();
        $row->addLabel('allowPasswordGrant', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addYesNo('allowPasswordGrant')->required()->selected($setting['value']);

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'loginMethod', true);
    $row = $form->addRow();
        $row->addLabel('loginMethod', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addSelect('loginMethod')
            ->addOption('', __('Username only'))
            ->addOption('username', __('Username only'))
            ->addOption('phone', __('Phone number only'))
            ->addOption('all', __('Username, email, and phone'))
            ->required()
            ->selected($setting['value']);

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'tokenLifetime', true);
    $row = $form->addRow();
        $row->addLabel('tokenLifetime', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addNumber('tokenLifetime')->minimum(1)->maximum(10080)->required()->setValue($setting['value']);

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'refreshLifetime', true);
    $row = $form->addRow();
        $row->addLabel('refreshLifetime', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addNumber('refreshLifetime')->minimum(0)->maximum(525600)->required()->setValue($setting['value']);

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'enforceRolePermissions', true);
    $row = $form->addRow();
        $row->addLabel('enforceRolePermissions', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addYesNo('enforceRolePermissions')->required()->selected($setting['value']);

    $form->addRow()->addHeading(__('Requests'));

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'defaultPageSize', true);
    $row = $form->addRow();
        $row->addLabel('defaultPageSize', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addNumber('defaultPageSize')->minimum(1)->maximum(1000)->required()->setValue($setting['value']);

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'maxPageSize', true);
    $row = $form->addRow();
        $row->addLabel('maxPageSize', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addNumber('maxPageSize')->minimum(1)->maximum(5000)->required()->setValue($setting['value']);

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'corsOrigins', true);
    $row = $form->addRow();
        $row->addLabel('corsOrigins', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addTextField('corsOrigins')->maxLength(255)->setValue($setting['value']);

    $form->addRow()->addHeading(__('Logging'));

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'logRequests', true);
    $row = $form->addRow();
        $row->addLabel('logRequests', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addYesNo('logRequests')->required()->selected($setting['value']);

    $setting = $settingGateway->getSettingByScope('TawasulCore', 'logRetentionDays', true);
    $row = $form->addRow();
        $row->addLabel('logRetentionDays', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addNumber('logRetentionDays')->minimum(1)->maximum(3650)->required()->setValue($setting['value']);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
