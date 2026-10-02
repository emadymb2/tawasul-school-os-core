<?php
/*
Gibbon REST API — Delete API Key
Licensed under the GNU General Public License v3 or later.
*/

use Gibbon\Module\RestAPI\Domain\ApiKeyGateway;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/Rest API/keys_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $restApiKeyID = $_GET['restApiKeyID'] ?? '';
    $key = (new ApiKeyGateway($pdo->getConnection()))->selectByID($restApiKeyID);

    if (empty($key)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $page->breadcrumbs
        ->add(__('Manage API Keys'), 'keys_manage.php')
        ->add(__('Delete API Key'));

    $form = \TawasulOS\Forms\Prefab\DeleteForm::createForm(
        $session->get('absoluteURL').'/modules/Rest API/keys_manage_deleteProcess.php?restApiKeyID='.urlencode($restApiKeyID),
        __('Any integration using this key will stop working immediately.')
    );

    echo $form->getOutput();
}
