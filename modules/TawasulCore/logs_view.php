<?php
/*
TawasulOS REST API — View Request Log
Licensed under the GNU General Public License v3 or later.
*/

use Tos\Forms\Form;
use Tos\Module\TawasulCore\Domain\ApiLogGateway;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulCore/logs_view.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('View Request Log'));

    $connection = $pdo->getConnection();
    $summary = (new ApiLogGateway($connection))->summary();

    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 my-4">';
    foreach ([
        __('Requests (24h)') => $summary['last24Hours'] ?? 0,
        __('Errors (24h)') => $summary['errors24Hours'] ?? 0,
        __('Average Response') => (int) ($summary['averageDuration'] ?? 0).' ms',
        __('Total Logged') => $summary['total'] ?? 0,
    ] as $label => $value) {
        echo '<div class="p-4 bg-gray-100 border rounded text-center">'
            .'<div class="text-2xl font-bold">'.htmlspecialchars((string) $value).'</div>'
            .'<div class="text-xs uppercase">'.$label.'</div></div>';
    }
    echo '</div>';

    // Filters are kept simple and are all applied server-side.
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $page1 = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = 50;

    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder fullWidth');
    $form->addHiddenValue('q', '/modules/TawasulCore/logs_view.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search'))->description(__('Endpoint or message.'));
        $row->addTextField('search')->setValue($search);

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')->fromArray([
            '' => __('All'),
            'success' => __('Successful (2xx)'),
            'client' => __('Client errors (4xx)'),
            'server' => __('Server errors (5xx)'),
        ])->selected($status);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    $where = [];
    $bindings = [];

    if ($search !== '') {
        $where[] = '(tos_api_log.endpoint LIKE :search OR tos_api_log.message LIKE :search)';
        $bindings['search'] = '%'.$search.'%';
    }
    if ($status === 'success') {
        $where[] = 'tos_api_log.statusCode < 400';
    } elseif ($status === 'client') {
        $where[] = 'tos_api_log.statusCode BETWEEN 400 AND 499';
    } elseif ($status === 'server') {
        $where[] = 'tos_api_log.statusCode >= 500';
    }

    $clause = empty($where) ? '' : ' WHERE '.implode(' AND ', $where);

    $count = $connection->prepare('SELECT COUNT(*) FROM tos_api_log'.$clause);
    $count->execute($bindings);
    $total = (int) $count->fetchColumn();

    $sql = 'SELECT tos_api_log.*, tawasulPerson.surname, tawasulPerson.preferredName, tos_api_key.name AS keyName
        FROM tos_api_log
        LEFT JOIN tawasulPerson ON (tos_api_log.tawasulPersonID=tawasulPerson.tawasulPersonID)
        LEFT JOIN tos_api_key ON (tos_api_log.tos_api_keyID=tos_api_key.tos_api_keyID)'
        .$clause.' ORDER BY tos_api_log.timestamp DESC LIMIT '.$pageSize.' OFFSET '.(($page1 - 1) * $pageSize);

    $stmt = $connection->prepare($sql);
    $stmt->execute($bindings);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($logs)) {
        echo '<div class="warning">'.__('There are no records to display.').'</div>';
        return;
    }

    echo '<table class="fullWidth colorOddEven" cellspacing="0">';
    echo '<tr class="head"><th>'.__('Time').'</th><th>'.__('Method').'</th><th>'.__('Endpoint').'</th><th>'.__('Caller').'</th><th>'.__('Status').'</th><th>'.__('Time Taken').'</th><th>'.__('Message').'</th></tr>';

    foreach ($logs as $log) {
        $caller = !empty($log['keyName'])
            ? htmlspecialchars($log['keyName'])
            : (!empty($log['surname']) ? htmlspecialchars($log['preferredName'].' '.$log['surname']) : __('Anonymous'));

        $statusClass = $log['statusCode'] < 400 ? 'success' : ($log['statusCode'] < 500 ? 'warning' : 'error');

        echo '<tr>';
        echo '<td class="text-xs">'.date('j M H:i:s', strtotime($log['timestamp'])).'</td>';
        echo '<td class="text-xs font-mono">'.htmlspecialchars($log['method']).'</td>';
        echo '<td class="text-xs font-mono">'.htmlspecialchars($log['endpoint']).'</td>';
        echo '<td class="text-xs">'.$caller.'</td>';
        echo '<td class="text-xs"><span class="tag '.$statusClass.'">'.(int) $log['statusCode'].'</span></td>';
        echo '<td class="text-xs">'.(int) $log['durationMS'].' ms</td>';
        echo '<td class="text-xs italic">'.htmlspecialchars((string) $log['message']).'</td>';
        echo '</tr>';
    }
    echo '</table>';

    $pages = (int) ceil($total / $pageSize);
    if ($pages > 1) {
        echo '<div class="my-4 text-xs">'.sprintf(__('Page %1$s of %2$s (%3$s records)'), $page1, $pages, $total).'</div>';
    }
}
