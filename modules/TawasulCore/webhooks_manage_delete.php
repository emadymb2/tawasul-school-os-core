<?php
/*
TawasulOS REST API — Delete Webhook
Licensed under the GNU General Public License v3 or later.
*/

use Tos\Module\TawasulCore\Domain\WebhookGateway;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulCore/webhooks_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $tos_api_webhookID = $_GET['tos_api_webhookID'] ?? '';
    $webhook = (new WebhookGateway($pdo->getConnection()))->selectByID($tos_api_webhookID);

    if (empty($webhook)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $page->breadcrumbs
        ->add(__('Manage Webhooks'), 'webhooks_manage.php')
        ->add(__('Delete Webhook'));

    $form = \Tos\Forms\DeleteForm::createForm(
        $session->get('absoluteURL').'/modules/TawasulCore/webhooks_manage_deleteProcess.php?tos_api_webhookID='.urlencode($tos_api_webhookID),
        __('Deleting this webhook will stop all change events from being sent to it.')
    );

    echo $form->getOutput();
}
