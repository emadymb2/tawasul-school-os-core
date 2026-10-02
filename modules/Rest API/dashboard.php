<?php
/*
Gibbon Deep REST API — Control Dashboard

Shows the live shape of the school (school structure, students, staff) and the
sync status of the API against Gibbon core: keys, traffic, errors and webhook
deliveries. Read-only; nothing on this page writes.

Licensed under the GNU General Public License v3 or later.
*/

use Gibbon\Module\RestAPI\Domain\DashboardGateway;
use Gibbon\Module\RestAPI\Support\Settings;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/Rest API/dashboard.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('API Dashboard'));

    $connection = $pdo->getConnection();
    $settings = new Settings($connection);
    $snapshot = (new DashboardGateway($connection))->snapshot($settings->isOn('apiEnabled', false));

    $e = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };

    $statusColours = [
        'ok' => 'bg-green-100 border-green-400',
        'idle' => 'bg-blue-100 border-blue-400',
        'degraded' => 'bg-orange-100 border-orange-400',
        'off' => 'bg-red-100 border-red-400',
    ];
    $statusLabels = [
        'ok' => __('Syncing'),
        'idle' => __('Idle'),
        'degraded' => __('Attention needed'),
        'off' => __('Switched off'),
    ];
    $state = $snapshot['status']['state'];

    // Live sync banner.
    echo '<div class="p-4 my-4 border rounded '.($statusColours[$state] ?? 'bg-gray-100 border-gray-400').'">';
    echo '<div class="text-lg font-bold">'.__('Sync status').': '.($statusLabels[$state] ?? $e($state)).'</div>';
    echo '<div class="text-sm">'.$e($snapshot['status']['message']).'</div>';
    echo '<div class="text-xs mt-1 italic">'
        .__('Current school year').': '.$e($snapshot['schoolYear']['name'] ?? __('None set'))
        .' · '.__('Snapshot taken').' '.date('j M Y H:i').'</div>';
    echo '</div>';

    $card = function ($value, $label, $note = '') use ($e) {
        echo '<div class="p-4 bg-gray-100 border rounded text-center">'
            .'<div class="text-3xl font-bold">'.$e($value).'</div>'
            .'<div class="text-xs uppercase mt-1">'.$e($label).'</div>'
            .($note !== '' ? '<div class="text-xs text-gray-600 mt-1">'.$e($note).'</div>' : '')
            .'</div>';
    };

    // Headline numbers.
    echo '<h3>'.__('School').'</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 my-4">';
    $card($snapshot['students']['enrolled'], __('Students enrolled'), __('Current year, full status'));
    $card($snapshot['staff']['total'], __('Staff'), __('Active staff records'));
    $card($snapshot['schools']['formGroups'], __('Form groups'), __('Current year'));
    $card($snapshot['schools']['yearGroups'], __('Year groups'), __('Across the school'));
    echo '</div>';

    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 my-4">';
    $card($snapshot['schools']['schoolYears'], __('School years'), $snapshot['schools']['schoolYearsUpcoming'].' '.__('upcoming'));
    $card($snapshot['schools']['departments'], __('Departments'));
    $card($snapshot['schools']['facilities'], __('Facilities'));
    $card($snapshot['schools']['houses'], __('Houses'));
    echo '</div>';

    // Live sync detail.
    echo '<h3>'.__('API sync (last 24 hours)').'</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 my-4">';
    $card($snapshot['sync']['requests24h'], __('Requests'), $snapshot['sync']['writes24h'].' '.__('writes'));
    $card($snapshot['sync']['errors24h'], __('Failed calls'));
    $card($snapshot['sync']['averageDuration'].' ms', __('Average response'));
    $card($snapshot['sync']['keysActive'].'/'.$snapshot['sync']['keysTotal'], __('Usable keys'), $snapshot['sync']['tokensLive'].' '.__('live tokens'));
    echo '</div>';

    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 my-4">';
    $card($snapshot['sync']['webhooksActive'], __('Active webhooks'));
    $card($snapshot['sync']['deliveries24h'], __('Webhook deliveries'), $snapshot['sync']['deliveryFailures24h'].' '.__('failed'));
    $card($snapshot['students']['expected'], __('Expected students'), __('Not yet started'));
    $card($snapshot['students']['applications'], __('Admissions applications'));
    echo '</div>';

    // Coverage against core, produced by tools/coverage-report.mjs.
    $coveragePath = __DIR__.'/src/Resource/coverage.json';
    if (is_readable($coveragePath)) {
        $coverage = json_decode(file_get_contents($coveragePath), true);
        if (is_array($coverage)) {
            echo '<h3>'.__('Coverage of Gibbon core').'</h3>';
            echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 my-4">';
            $card($coverage['coveredTables'].'/'.$coverage['coreTables'], __('Core tables exposed'), count($coverage['missingTables'] ?? []).' '.__('missing'));
            $card($coverage['resources'], __('Endpoints'), $coverage['curated'].' '.__('curated'));
            $card($coverage['writable'], __('Writable endpoints'));
            $card($coverage['mappedActions'].'/'.$coverage['actions'], __('Actions permission-mapped'));
            echo '</div>';
            if (!empty($coverage['missingTables'])) {
                echo '<p class="text-xs italic">'.__('Core tables with no endpoint').': '
                    .$e(implode(', ', $coverage['missingTables'])).'</p>';
            }
        }
    }

    // Students by year group.
    if (!empty($snapshot['students']['byYearGroup'])) {
        echo '<h3>'.__('Students by year group').'</h3>';
        echo '<table class="fullWidth colorOddEven" cellspacing="0">';
        echo '<tr class="head"><th>'.__('Year group').'</th><th>'.__('Students').'</th></tr>';
        foreach ($snapshot['students']['byYearGroup'] as $row) {
            echo '<tr><td>'.$e($row['label']).'</td><td>'.(int) $row['total'].'</td></tr>';
        }
        echo '</table>';
    }

    // Staff by type.
    if (!empty($snapshot['staff']['byType'])) {
        echo '<h3>'.__('Staff by type').'</h3>';
        echo '<table class="fullWidth colorOddEven" cellspacing="0">';
        echo '<tr class="head"><th>'.__('Type').'</th><th>'.__('Staff').'</th></tr>';
        foreach ($snapshot['staff']['byType'] as $row) {
            echo '<tr><td>'.$e($row['label'] ?: __('Unspecified')).'</td><td>'.(int) $row['total'].'</td></tr>';
        }
        echo '</table>';
    }

    // Per-key sync state.
    echo '<h3>'.__('Clients syncing').'</h3>';
    if (empty($snapshot['sync']['keys'])) {
        echo '<div class="warning">'.__('No API keys have been created yet.').'</div>';
    } else {
        echo '<table class="fullWidth colorOddEven" cellspacing="0">';
        echo '<tr class="head"><th>'.__('Key').'</th><th>'.__('Active').'</th><th>'.__('Last call').'</th><th>'.__('Last IP').'</th><th>'.__('Expires').'</th></tr>';
        foreach ($snapshot['sync']['keys'] as $key) {
            echo '<tr>';
            echo '<td>'.$e($key['name']).'</td>';
            echo '<td>'.($key['active'] === 'Y' ? __('Yes') : __('No')).'</td>';
            echo '<td class="text-xs">'.($key['lastAccess'] ? date('j M H:i', strtotime($key['lastAccess'])) : __('Never')).'</td>';
            echo '<td class="text-xs font-mono">'.$e($key['lastIPAddress']).'</td>';
            echo '<td class="text-xs">'.($key['dateExpiry'] ? $e($key['dateExpiry']) : __('Never')).'</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    // Busiest endpoints.
    if (!empty($snapshot['sync']['busiestResources'])) {
        echo '<h3>'.__('Busiest endpoints (24h)').'</h3>';
        echo '<table class="fullWidth colorOddEven" cellspacing="0">';
        echo '<tr class="head"><th>'.__('Resource').'</th><th>'.__('Calls').'</th></tr>';
        foreach ($snapshot['sync']['busiestResources'] as $row) {
            echo '<tr><td class="font-mono text-xs">/'.$e($row['label']).'</td><td>'.(int) $row['total'].'</td></tr>';
        }
        echo '</table>';
    }

    echo '<p class="text-xs italic mt-4">'
        .__('The same figures are available to integrations at').' <code>GET /v2/dashboard</code> '
        .__('with the meta.read scope.').'</p>';
}
