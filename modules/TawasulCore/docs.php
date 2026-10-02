<?php
/*
TawasulOS REST API — Documentation
Licensed under the GNU General Public License v3 or later.
*/

use Tos\Module\TawasulCore\Support\OpenApi;
use Tos\Module\TawasulCore\Resource\Registry;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulCore/docs.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('API Documentation'));

    $baseUrl = $session->get('absoluteURL').'/modules/TawasulCore/api.php/v2';
    $table = OpenApi::routeTable();

    echo '<h2>'.__('Getting Started').'</h2>';
    echo '<p>'.sprintf(__('Every endpoint lives under %1$s. Send your key in an Authorization header:'), '<code>'.htmlspecialchars($baseUrl).'</code>').'</p>';
    echo '<pre class="p-4 bg-gray-100 border rounded text-xs overflow-x-auto">'
        .htmlspecialchars("curl -H 'Authorization: Bearer tws_your_key_here' \\\n  '".$baseUrl."/students?pageSize=25&sort=surname'")
        .'</pre>';

    echo '<p>'.__('Collections accept page, pageSize, sort, search and fields, plus the filters listed for each resource. Add school_year_id to any request to work in a year other than the current one.').'</p>';
    echo '<p>'.sprintf(__('The full machine-readable specification is available at %1$s, ready to paste into Postman, Insomnia or Swagger UI.'), '<a href="'.htmlspecialchars($baseUrl).'/openapi.json"><code>/openapi.json</code></a>').'</p>';

    echo '<h2>'.__('Authentication').'</h2>';
    echo '<ul>';
    echo '<li>'.__('API keys (tws_...) are created under Manage API Keys and suit server-to-server integrations.').'</li>';
    echo '<li>'.__('User tokens (tok_...) come from POST /auth/login and act as that person, subject to their TawasulOS roles.').'</li>';
    echo '<li>'.__('A token can be renewed with POST /auth/refresh and cancelled with POST /auth/logout.').'</li>';
    echo '</ul>';

    echo '<h2>'.sprintf(__('Resources (%1$s)'), count(Registry::all())).'</h2>';

    foreach ($table as $group => $resources) {
        echo '<h3>'.htmlspecialchars($group).'</h3>';
        echo '<table class="fullWidth colorOddEven" cellspacing="0">';
        echo '<tr class="head"><th style="width:20%">'.__('Endpoint').'</th><th style="width:15%">'.__('Methods').'</th><th style="width:20%">'.__('Scopes').'</th><th>'.__('Filters').'</th></tr>';

        foreach ($resources as $resource) {
            echo '<tr>';
            echo '<td><b class="font-mono text-xs">/'.htmlspecialchars($resource['resource']).'</b>';
            if (!empty($resource['description'])) {
                echo '<br/><span class="text-xs italic">'.htmlspecialchars($resource['description']).'</span>';
            }
            if (!empty($resource['module'])) {
                echo '<br/><span class="text-xs">'.sprintf(__('Requires the %1$s module'), htmlspecialchars($resource['module'])).'</span>';
            }
            echo '</td>';
            echo '<td class="text-xs font-mono">'.implode(' ', $resource['methods']).'</td>';
            echo '<td class="text-xs font-mono">'.htmlspecialchars($resource['scopeRead']);
            if (count($resource['methods']) > 1) {
                echo '<br/>'.htmlspecialchars($resource['scopeWrite']);
            }
            echo '</td>';
            echo '<td class="text-xs">'.(empty($resource['filters']) ? '—' : htmlspecialchars(implode(', ', $resource['filters']))).'</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}
