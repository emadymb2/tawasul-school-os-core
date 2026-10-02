<?php
/*
Gibbon REST API — Manage API Keys
Licensed under the GNU General Public License v3 or later.
*/

use TawasulOS\Http\Url;
use TawasulOS\Tables\DataTable;
use Gibbon\Module\RestAPI\Domain\ApiKeyGateway;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/Rest API/keys_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Manage API Keys'));

    // A newly created secret is read from session once and cleared, so it is
    // never placed in a URL (which would land in access logs and browser history).
    $secret = $session->get('restApiNewKeySecret');
    if (!empty($secret)) {
        $session->set('restApiNewKeySecret', null);
        $page->addWarning(__('This is the only time the key will be shown. Copy it now and store it somewhere safe.'));
        echo '<div class="p-4 my-4 bg-gray-100 border rounded font-mono text-sm break-all">'.htmlspecialchars($secret).'</div>';
    }

    $gateway = new ApiKeyGateway($pdo->getConnection());
    $keys = $gateway->selectAll();

    echo '<p>'.__('API keys authenticate machine-to-machine callers. Each key carries its own scopes, so an integration only ever receives the data it needs.').'</p>';

    $table = DataTable::create('apiKeys');
    $table->setTitle(__('API Keys'));

    $table->addHeaderAction('add', __('Add API Key'))
        ->setURL('/modules/Rest API/keys_manage_add.php')
        ->displayLabel();

    $table->addColumn('name', __('Name'))
        ->format(function ($key) {
            return '<b>'.htmlspecialchars($key['name']).'</b>'
                .(!empty($key['description']) ? '<br/><span class="text-xs italic">'.htmlspecialchars($key['description']).'</span>' : '');
        });

    $table->addColumn('keyPrefix', __('Key'))
        ->format(function ($key) {
            return '<span class="font-mono text-xs">gib_'.htmlspecialchars($key['keyPrefix']).'&hellip;</span>';
        });

    $table->addColumn('scopes', __('Scopes'))
        ->format(function ($key) {
            return $key['scopes'] === '*'
                ? '<span class="tag success">'.__('Full access').'</span>'
                : '<span class="text-xs">'.htmlspecialchars($key['scopes']).'</span>';
        });

    $table->addColumn('active', __('Status'))
        ->format(function ($key) {
            if ($key['active'] !== 'Y') {
                return '<span class="tag dull">'.__('Disabled').'</span>';
            }
            if (!empty($key['dateExpiry']) && strtotime($key['dateExpiry']) < time()) {
                return '<span class="tag error">'.__('Expired').'</span>';
            }
            return '<span class="tag success">'.__('Active').'</span>';
        });

    $table->addColumn('lastAccess', __('Last Used'))
        ->format(function ($key) {
            return empty($key['lastAccess']) ? __('Never') : date('j M Y H:i', strtotime($key['lastAccess']));
        });

    $table->addActionColumn()
        ->addParam('restApiKeyID')
        ->format(function ($key, $actions) {
            $actions->addAction('edit', __('Edit'))->setURL('/modules/Rest API/keys_manage_edit.php');
            $actions->addAction('delete', __('Delete'))->setURL('/modules/Rest API/keys_manage_delete.php');
        });

    echo $table->render(new \TawasulOS\Domain\DataSet($keys));
}
