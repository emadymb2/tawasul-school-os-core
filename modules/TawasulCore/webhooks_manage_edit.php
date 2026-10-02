<?php
/*
TawasulOS REST API — Edit Webhook
Licensed under the GNU General Public License v3 or later.
*/

use Tos\Forms\Form;
use Tos\Module\TawasulCore\Domain\WebhookGateway;
use Tos\Module\TawasulCore\Resource\Registry;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulCore/webhooks_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('Manage Webhooks'), 'webhooks_manage.php')
        ->add(__('Edit Webhook'));

    $gateway = new WebhookGateway($pdo->getConnection());
    $webhook = $gateway->selectByID((string) ($_GET['tos_api_webhookID'] ?? ''));

    if (empty($webhook)) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }

    $events = [];
    foreach (Registry::events() as $event => $label) {
        $events[$event] = $event.' — '.$label;
    }

    $form = Form::create('webhookEdit', $session->get('absoluteURL').'/modules/TawasulCore/webhooks_manage_editProcess.php');
    $form->setFactory(\Tos\Forms\DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tos_api_webhookID', $webhook['tos_api_webhookID']);

    $row = $form->addRow();
        $row->addLabel('name', __('Name'));
        $row->addTextField('name')->maxLength(60)->required()->setValue($webhook['name']);

    $row = $form->addRow();
        $row->addLabel('url', __('URL'));
        $row->addURL('url')->maxLength(500)->required()->setValue($webhook['url']);

    $row = $form->addRow();
        $row->addLabel('events', __('Events'));
        $row->addSelect('events')->fromArray($events)->selectMultiple()->setSize(14)->required()
            ->selected(explode(',', (string) $webhook['events']));

    $row = $form->addRow();
        $row->addLabel('headers', __('Extra Headers'))
            ->description(__('One header per line, in the form Name: value.'));
        $row->addTextArea('headers')->setRows(3)->setValue($webhook['headers']);

    $row = $form->addRow();
        $row->addLabel('active', __('Active'));
        $row->addYesNo('active')->required()->selected($webhook['active']);

    // The receiving system needs this exact value to verify the signature, so
    // unlike an API key it is shown rather than hashed.
    $row = $form->addRow();
        $row->addLabel('secretDisplay', __('Signing Secret'))
            ->description(__('Give this to the receiving system. Each call carries X-TawasulOS-Signature: sha256=HMAC of "timestamp.body" using this secret.'));
        $row->addTextField('secretDisplay')->readonly()->setValue($webhook['secret']);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
