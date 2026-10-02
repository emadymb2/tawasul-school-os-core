<?php
/*
Gibbon REST API — Manage Webhooks
Licensed under the GNU General Public License v3 or later.
*/

use TawasulOS\Tables\DataTable;
use Gibbon\Module\RestAPI\Domain\WebhookGateway;
use Gibbon\Module\RestAPI\Support\Settings;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/Rest API/webhooks_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Manage Webhooks'));

    $connection = $pdo->getConnection();
    $settings = new Settings($connection);
    $gateway = new WebhookGateway($connection);

    if (!$settings->isOn('webhooksEnabled', false)) {
        $page->addWarning(__('Webhooks are currently switched off. Turn on "Enable Webhooks" in Manage Settings before events will be sent.'));
    }

    echo '<p>'.__('A webhook tells another system, straight away, that something changed in Gibbon. Every call is signed with the subscription secret so the receiving system can be sure it really came from here.').'</p>';

    $webhooks = $gateway->selectAll();

    $table = DataTable::create('webhooks');
    $table->setTitle(__('Webhooks'));

    $table->addHeaderAction('add', __('Add Webhook'))
        ->setURL('/modules/Rest API/webhooks_manage_add.php')
        ->displayLabel();

    $table->addColumn('name', __('Name'))
        ->format(function ($hook) {
            return '<b>'.htmlspecialchars($hook['name']).'</b><br/><span class="text-xs italic break-all">'.htmlspecialchars($hook['url']).'</span>';
        });

    $table->addColumn('events', __('Events'))
        ->format(function ($hook) {
            return '<span class="text-xs font-mono">'.htmlspecialchars($hook['events']).'</span>';
        });

    $table->addColumn('active', __('Status'))
        ->format(function ($hook) {
            return $hook['active'] === 'Y'
                ? '<span class="tag success">'.__('Active').'</span>'
                : '<span class="tag dull">'.__('Paused').'</span>';
        });

    $table->addColumn('deliveryCount', __('Deliveries'))
        ->format(function ($hook) {
            $failures = (int) $hook['failureCount'];

            return (int) $hook['deliveryCount'].($failures > 0
                ? ' <span class="tag error">'.sprintf(__('%1$s failed'), $failures).'</span>'
                : '');
        });

    $table->addActionColumn()
        ->addParam('restApiWebhookID')
        ->format(function ($hook, $actions) {
            $actions->addAction('edit', __('Edit'))->setURL('/modules/Rest API/webhooks_manage_edit.php');
            $actions->addAction('delete', __('Delete'))->setURL('/modules/Rest API/webhooks_manage_delete.php');
        });

    echo $table->render(new \TawasulOS\Domain\DataSet($webhooks));

    $deliveries = $gateway->recentDeliveries(50);

    $log = DataTable::create('deliveries');
    $log->setTitle(__('Recent Deliveries'));

    $log->addColumn('timestamp', __('When'))
        ->format(function ($row) {
            return date('j M Y H:i:s', strtotime($row['timestamp']));
        });

    $log->addColumn('name', __('Webhook'));

    $log->addColumn('event', __('Event'))
        ->format(function ($row) {
            return '<span class="text-xs font-mono">'.htmlspecialchars($row['event']).'</span>';
        });

    $log->addColumn('statusCode', __('Result'))
        ->format(function ($row) {
            $class = $row['success'] === 'Y' ? 'success' : 'error';
            $code = (int) $row['statusCode'];

            return '<span class="tag '.$class.'">'.($code > 0 ? $code : __('No response')).'</span>'
                .' <span class="text-xs">'.(int) $row['durationMS'].'ms</span>'
                .(!empty($row['response']) && $row['success'] !== 'Y'
                    ? '<br/><span class="text-xs italic break-all">'.htmlspecialchars(mb_substr($row['response'], 0, 160)).'</span>'
                    : '');
        });

    echo $log->render(new \TawasulOS\Domain\DataSet($deliveries));
}
