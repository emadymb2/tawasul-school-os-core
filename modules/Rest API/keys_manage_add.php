<?php
/*
Gibbon REST API — Add API Key
Licensed under the GNU General Public License v3 or later.
*/

use TawasulOS\Forms\Form;
use Gibbon\Module\RestAPI\Resource\Registry;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/Rest API/keys_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('Manage API Keys'), 'keys_manage.php')
        ->add(__('Add API Key'));

    $form = Form::create('apiKeyAdd', $session->get('absoluteURL').'/modules/Rest API/keys_manage_addProcess.php');
    $form->setFactory(\TawasulOS\Forms\DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addLabel('name', __('Name'))->description(__('A short label, such as the system that will use this key.'));
        $row->addTextField('name')->maxLength(60)->required();

    $row = $form->addRow();
        $row->addLabel('description', __('Description'));
        $row->addTextArea('description')->setRows(2);

    $row = $form->addRow();
        $row->addLabel('tawasulPersonID', __('Act As'))
            ->description(__('Optional. Requests made with this key are treated as this person, and are limited by their roles.'));
        $row->addSelectUsers('tawasulPersonID')->placeholder('')->selected('');

    $row = $form->addRow();
        $row->addLabel('tawasulSchoolYearID', __('Default School Year'))
            ->description(__('Used when a request does not name a school year.'));
        $row->addSelectSchoolYear('tawasulSchoolYearID')->placeholder('');

    $scopes = [];
    foreach (Registry::scopes() as $scope => $label) {
        $scopes[$scope] = $scope.' — '.$label;
    }

    $row = $form->addRow();
        $row->addLabel('scopes', __('Scopes'))
            ->description(__('Choose only what this integration needs. Selecting * grants everything.'));
        $row->addSelect('scopes')->fromArray($scopes)->selectMultiple()->setSize(14)->required();

    $row = $form->addRow();
        $row->addLabel('ipAddresses', __('Allowed IP Addresses'))
            ->description(__('Optional comma-separated list. Supports single addresses, ranges and CIDR notation.'));
        $row->addTextField('ipAddresses')->maxLength(255);

    $row = $form->addRow();
        $row->addLabel('rateLimit', __('Rate Limit'))->description(__('Requests allowed per minute. Leave blank for no limit.'));
        $row->addNumber('rateLimit')->minimum(0)->maximum(100000);

    $row = $form->addRow();
        $row->addLabel('dateExpiry', __('Expiry Date'))->description(__('Optional. The key stops working at the end of this day.'));
        $row->addDate('dateExpiry');

    $row = $form->addRow();
        $row->addLabel('active', __('Active'));
        $row->addYesNo('active')->selected('Y')->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
