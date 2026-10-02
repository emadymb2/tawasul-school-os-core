<?php
/*
Gibbon REST API
Copyright (C) 2026

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

/**
 * The single entry point for the REST API.
 *
 * This file deliberately bypasses the index.php front controller. That
 * controller assumes a browser session, renders HTML, and runs the CSRF gate
 * in Process.php, none of which suit a stateless API. Instead it boots only
 * tawasul.php for configuration and the database connection, then hands the
 * request to the module's own kernel.
 *
 * Reachable at: /modules/Rest%20API/api.php/v2/{resource}
 * Where PATH_INFO is unavailable: /modules/Rest%20API/api.php?endpoint=/v2/{resource}
 */

// No HTML, no session cookie, no output before the JSON body.
@ini_set('display_errors', '0');
@ini_set('session.use_cookies', '0');

$moduleDir = __DIR__;
$systemRoot = realpath($moduleDir.'/../..');

if ($systemRoot === false || !file_exists($systemRoot.'/tawasul.php')) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    exit(json_encode(['error' => [
        'code' => 'not_installed',
        'message' => 'This module must live inside a Tawasul OS installation, in the modules folder.',
    ]]));
}

// tawasul.php resolves config, builds the container and opens the PDO
// connection. It expects to be included from a script inside the install.
require $systemRoot.'/tawasul.php';

header_remove('Set-Cookie');

// The module ships its own classes; register them before anything else runs.
spl_autoload_register(function ($class) use ($moduleDir) {
    $prefix = 'Gibbon\\Module\\RestAPI\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $path = $moduleDir.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (file_exists($path)) {
        require $path;
    }
});

$connection = null;
if (isset($pdo) && method_exists($pdo, 'getConnection')) {
    $connection = $pdo->getConnection();           // TawasulOS\Contracts\Database\Connection
} elseif (isset($connection2)) {
    $connection = $connection2;                    // legacy global PDO handle
}

if (!$connection instanceof PDO) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503);
    exit(json_encode(['error' => [
        'code' => 'database_unavailable',
        'message' => 'The API could not reach the Tawasul OS database.',
    ]]));
}

$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

(new Gibbon\Module\RestAPI\Kernel($connection))->handle();
