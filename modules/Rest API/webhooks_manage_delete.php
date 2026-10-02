<?php
/*
Gibbon REST API — Delete Webhook
Licensed under the GNU General Public License v3 or later.
*/

use Gibbon\Module\RestAPI\Domain\WebhookGateway;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/Rest API/webhooks_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $restApiWebhookID = $_GET['restApiWebhookID'] ?? '';
    $webhook = (new WebhookGateway($pdo->getConnection()))->selectByID($restApiWebhookID);

    if (empty($webhook)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $page->breadcrumbs
        ->add(__('Manage Webhooks'), 'webhooks_manage.php')
        ->add(__('Delete Webhook'));

    $form = \TawasulOS\Forms\Prefab\DeleteForm::createForm(
        $session->get('absoluteURL').'/modules/Rest API/webhooks_manage_deleteProcess.php?restApiWebhookID='.urlencode($restApiWebhookID),
        __('Deleting this webhook will stop all change events from being sent to it.')
    );

    echo $form->getOutput();
}
