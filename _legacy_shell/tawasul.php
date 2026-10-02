<?php
if (!defined('TAWASUL_ROOT')) {
    define('TAWASUL_ROOT', realpath(__DIR__));
}
require_once TAWASUL_ROOT . '/config/config.php';
require_once TAWASUL_ROOT . '/src/Database.php';
require_once TAWASUL_ROOT . '/src/Session.php';
require_once TAWASUL_ROOT . '/src/Auth.php';

use Tos\Database;
use Tos\Session;
use Tos\Auth;

try {
    $db = Database::getInstance(['host' => DB_HOST, 'name' => DB_NAME, 'user' => DB_USER, 'pass' => DB_PASS]);
    $pdo = $db->getPDO();
} catch (PDOException $e) {
    http_response_code(500);
    die("Database connection failed");
}

$session = new Session(
    defined('TAWASUL_SESSION_NAME') ? TAWASUL_SESSION_NAME : 'TAWASULSESSION',
    defined('TAWASUL_SESSION_LIFETIME') ? TAWASUL_SESSION_LIFETIME : 3600
);
$session->start();

$auth = new Auth($pdo, $session);

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $user = $auth->attempt($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($user) {
        header('Location: /tawasul-os/tawasul.php');
        exit;
    } else {
        header('Location: /tawasul-os/public/welcome.html?error=invalid');
        exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: /tawasul-os/public/welcome.html');
    exit;
}

// Parse request path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = ltrim($path, '/');
$segments = explode('/', $path);

// Handle module page requests: /m or /m/{module} or /m/{module}/{page}
if (isset($segments[0]) && $segments[0] === 'm') {
    if ($auth->check()) {
        $user = $auth->user();
        if ($user) {
            $moduleSlug = $segments[1] ?? null;
            $pageSlug = $segments[2] ?? null;

            if ($moduleSlug === null) {
                // Modules index
                require TAWASUL_ROOT . '/src/views/modules-index.php';
                exit;
            } else {
                // Generic module page
                require TAWASUL_ROOT . '/src/views/module-page.php';
                exit;
            }
        }
    }
    // Redirect to login if not authenticated
    header('Location: /tawasul-os/public/welcome.html');
    exit;
}

// If logged in, render portal inline
if ($auth->check()) {
    $user = $auth->user();
    if ($user) {
        require TAWASUL_ROOT . '/src/PortalView.php';
        exit;
    }
}

// Not logged in - serve welcome page
$welcomeFile = TAWASUL_ROOT . '/public/welcome.html';
if (file_exists($welcomeFile)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($welcomeFile);
} else {
    http_response_code(500);
    echo "TawasulOS frontend not found.";
}
