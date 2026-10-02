<?php
/*
Gibbon REST API — Edit API Key
Licensed under the GNU General Public License v3 or later.
*/

use TawasulOS\Forms\Form;
use Gibbon\Module\RestAPI\Domain\ApiKeyGateway;
use Gibbon\Module\RestAPI\Resource\Registry;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/Rest API/keys_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('Manage API Keys'), 'keys_manage.php')
        ->add(__('Edit API Key'));

    $restApiKeyID = $_GET['restApiKeyID'] ?? '';
    $key = (new ApiKeyGateway($pdo->getConnection()))->selectByID($restApiKeyID);

    if (empty($key)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    echo '<p>'.__('The key itself cannot be shown again. If it has been lost, delete this key and create a new one.').'</p>';

    $form = Form::create('apiKeyEdit', $session->get('absoluteURL').'/modules/Rest API/keys_manage_editProcess.php');
    $form->setFactory(\TawasulOS\Forms\DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('restApiKeyID', $restApiKeyID);

    $row = $form->addRow();
        $row->addLabel('name', __('Name'));
        $row->addTextField('name')->maxLength(60)->required();

    $row = $form->addRow();
        $row->addLabel('description', __('Description'));
        $row->addTextArea('description')->setRows(2);

    $row = $form->addRow();
        $row->addLabel('tawasulPersonID', __('Act As'));
        $row->addSelectUsers('tawasulPersonID')->placeholder('');

    $row = $form->addRow();
        $row->addLabel('tawasulSchoolYearID', __('Default School Year'));
        $row->addSelectSchoolYear('tawasulSchoolYearID')->placeholder('');

    $scopes = [];
    foreach (Registry::scopes() as $scope => $label) {
        $scopes[$scope] = $scope.' — '.$label;
    }

    $row = $form->addRow();
        $row->addLabel('scopes', __('Scopes'));
        $row->addSelect('scopes')->fromArray($scopes)->selectMultiple()->setSize(14)->required();

    $row = $form->addRow();
        $row->addLabel('ipAddresses', __('Allowed IP Addresses'));
        $row->addTextField('ipAddresses')->maxLength(255);

    $row = $form->addRow();
        $row->addLabel('rateLimit', __('Rate Limit'))->description(__('Requests per minute.'));
        $row->addNumber('rateLimit')->minimum(0)->maximum(100000);

    $row = $form->addRow();
        $row->addLabel('dateExpiry', __('Expiry Date'));
        $row->addDate('dateExpiry');

    $row = $form->addRow();
        $row->addLabel('active', __('Active'));
        $row->addYesNo('active')->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    $key['scopes'] = array_filter(array_map('trim', explode(',', (string) $key['scopes'])));
    if (!empty($key['dateExpiry'])) {
        $key['dateExpiry'] = dateConvertBack($guid, $key['dateExpiry']);
    }

    $form->loadAllValuesFrom($key);

    echo $form->getOutput();
}
