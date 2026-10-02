<?php
/*
TawasulOS REST API — Delete API Key
Licensed under the GNU General Public License v3 or later.
*/

use Tos\Module\TawasulCore\Domain\ApiKeyGateway;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulCore/keys_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $tos_api_keyID = $_GET['tos_api_keyID'] ?? '';
    $key = (new ApiKeyGateway($pdo->getConnection()))->selectByID($tos_api_keyID);

    if (empty($key)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $page->breadcrumbs
        ->add(__('Manage API Keys'), 'keys_manage.php')
        ->add(__('Delete API Key'));

    $form = \Tos\Forms\DeleteForm::createForm(
        $session->get('absoluteURL').'/modules/TawasulCore/keys_manage_deleteProcess.php?tos_api_keyID='.urlencode($tos_api_keyID),
        __('Any integration using this key will stop working immediately.')
    );

    echo $form->getOutput();
}
