<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use TawasulOS\Http\Url;
use TawasulOS\Data\Validator;
use TawasulOS\Session\TokenHandler;
use TawasulOS\Services\ModuleLoader;

// Handle fatal errors more gracefully
register_shutdown_function(function () {
    $lastError = error_get_last();
    if ($lastError && ($lastError['type'] === E_ERROR || $lastError['type'] === E_CORE_ERROR || $lastError['type'] === E_COMPILE_ERROR) ) {
        // The friendly page below deliberately reveals nothing, so without this
        // line a fatal is unreproducible after the fact: the request ends with a
        // generic message and no record of what actually failed. Log it first,
        // naming the address so it can be traced back to a page.
        $address = $_GET['q'] ?? $_POST['address'] ?? '(unknown)';
        error_log(sprintf(
            'TawasulOS fatal at %s in %s:%d — %s',
            $address,
            $lastError['file'] ?? '(unknown file)',
            $lastError['line'] ?? 0,
            $lastError['message'] ?? '(no message)'
        ));
        include __DIR__.'/error.php';
    }
    exit;
});

// Check for the autoloader file
if (!file_exists(__DIR__.'/vendor/autoload.php')) {
    $message = 'Fatal Error: Missing composer autoloader. Your vendor folder is likely not installed. If you are running cutting edge code, navigate to your base dir in a terminal window and run the "composer install" command. See the Cutting Edge Code documentation for more information: https://docs.tos.fiksutiliratkaisut.fi/introduction/installation-options/cutting-edge-code';
    include __DIR__.'/error.php';
    exit;
}

// Setup the composer autoloader
$autoloader = require_once __DIR__.'/vendor/autoload.php';

// Require the system-wide functions
require_once __DIR__.'/functions.php';

// Core Services
$container = new League\Container\Container();
$container->delegate(new League\Container\ReflectionContainer);
$container->share('autoloader', $autoloader);

$container->inflector(\League\Container\ContainerAwareInterface::class)
          ->invokeMethod('setContainer', [$container]);

$container->inflector(\TawasulOS\Services\BackgroundProcess::class)
          ->invokeMethod('setProcessor', [\TawasulOS\Services\BackgroundProcessor::class]);

$container->addServiceProvider(new TawasulOS\Services\CoreServiceProvider(__DIR__));
$container->addServiceProvider(new TawasulOS\Services\ViewServiceProvider());
$container->addServiceProvider(new TawasulOS\Services\AuthServiceProvider());

// Globals for backwards compatibility
$tawasul = $container->get('config');
$tawasul->locale = $container->get('locale');
$guid = $tawasul->getConfig('guid');
$caching = $tawasul->getConfig('caching');
$version = $tawasul->getConfig('version');

// Handle TawasulOS installation redirect
if (!$tawasul->isInstalled() && !$tawasul->isInstalling()) {
    define('SESSION_TABLE_AVAILABLE', false);
    header("Location: ./installer/install.php");
    exit;
}

// Initialize the database connection
if ($tawasul->isInstalled()) {
    $mysqlConnector = new TawasulOS\Database\MySqlConnector();

    // Display a static error message for database connections after install.
    if ($pdo = $mysqlConnector->connect($tawasul->getConfig())) {
        // Add the database to the container
        $connection2 = $pdo->getConnection();
        $container->add('db', $pdo);
        $container->share(TawasulOS\Contracts\Database\Connection::class, $pdo);

        // Add a feature flag here to prevent errors before updating
        // TODO: this can likely be removed in v24+
        if (!defined('SESSION_TABLE_AVAILABLE')) {
            $hasSessionTable = $pdo->selectOne("SHOW TABLES LIKE 'tawasulSession'");
            define('SESSION_TABLE_AVAILABLE', !empty($hasSessionTable));
        }

        // Initialize core
        try {
            $tawasul->initializeCore($container);
        } catch (\Exception $e) {
            $message = __('Configuration Error: there is a problem accessing the current Academic Year from the database.');
            include __DIR__.'/error.php';
            exit;
        }
        
    } else {
        if (!$tawasul->isInstalling()) {
            $message = sprintf(__('A database connection could not be established. Please %1$stry again%2$s.'), '', '');
            include __DIR__.'/error.php';
            exit;
        }
    }
}

if (!defined('SESSION_TABLE_AVAILABLE')) {
    define('SESSION_TABLE_AVAILABLE', false);
}

// Globals for backwards compatibility
$session = $container->get('session');
$tawasul->session = $session;
$container->share(\TawasulOS\Contracts\Services\Session::class, $session);

// Setup global absoluteURL for all urls.
if ($tawasul->isInstalled() && $session->has('absoluteURL')) {
    Url::setBaseUrl($session->get('absoluteURL'));
} else {
    // TODO: put this absoluteURL detection somewhere?
    $absoluteURL = (function () {
        // Find out the base installation URL path.
        $prefixLength = strlen(realpath($_SERVER['DOCUMENT_ROOT']));
        $baseDir = realpath(__DIR__) . '/';
        $urlBasePath = substr($baseDir, $prefixLength);

        // Construct the full URL to the base URL path.
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        return "{$protocol}://{$host}{$urlBasePath}";
    })();
    Url::setBaseUrl($absoluteURL);
}

// Autoload the current module namespace
if (!empty($session->get('module'))) {
    $container->get(ModuleLoader::class)->registerModuleNamespace($session->get('module'));
}

// Sanitize incoming user-supplied GET variables
$validator = $container->get(Validator::class);
$_GET = $validator->sanitizeUrlParams($_GET);
$tokenHandler = $container->get(TokenHandler::class);

// Check for CSRF token and nonce when posting any form
if (!empty($_POST) && count($_POST) > 1 && stripos($_SERVER['PHP_SELF'], 'Process.php') !== false) {
    
    // Validate CSRF token
    if (!$tokenHandler->validateCsrfToken()) {
        $URL = $_SERVER['HTTP_REFERER'].'&return=error9';
        header("Location: {$URL}");
        exit;
    }

    // Validate nonce
    if (!$tokenHandler->validateNonce()) {
        $URL = $_SERVER['HTTP_REFERER'].'&return=error10';
        header("Location: {$URL}");
        exit;
    }
}


