<?php
/**
 * TawasulOS — Router
 *
 * Routes incoming HTTP requests to the appropriate module and page.
 * Serves the React SPA frontend for non-API routes.
 */

namespace Tos;

class Router
{
    private $container;
    private string $basePath;

    public function __construct($container)
    {
        $this->container = $container;
        $this->basePath = '';
    }

    public function dispatch(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH);

        $scriptDir = dirname($scriptName);
        if ($scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        $path = ltrim($path, '/');
        $segments = explode('/', $path);
        $firstSegment = $segments[0] ?? '';

        // API requests
        if ($firstSegment === 'api' || $firstSegment === 'v2') {
            $this->dispatchApi($segments);
            return;
        }

        // Static assets
        if ($firstSegment === 'tawasul-os' && isset($segments[1]) && $segments[1] === 'public') {
            $this->serveStatic($path);
            return;
        }

        // Module page requests
        $modules = $this->container->getModules();
        if (isset($modules[$firstSegment])) {
            $this->dispatchModule($firstSegment, array_slice($segments, 1));
            return;
        }

        // Default: serve the welcome page
        $this->serveWelcome();
    }

    private function dispatchApi(array $segments): void
    {
        $apiFile = TAWASUL_ROOT . '/modules/TawasulCore/api.php';
        if (file_exists($apiFile)) {
            require $apiFile;
            return;
        }

        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API module not found']);
    }

    private function dispatchModule(string $moduleName, array $segments): void
    {
        $moduleDir = TAWASUL_ROOT . '/modules/' . $moduleName;
        $page = $segments[0] ?? 'dashboard';
        $pageFile = $moduleDir . '/' . $page . '.php';

        if (!file_exists($pageFile)) {
            $page = 'dashboard';
            $pageFile = $moduleDir . '/' . $page . '.php';
        }

        if (file_exists($pageFile)) {
            require $pageFile;
        } else {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            echo "Module page not found: {$moduleName}/{$page}";
        }
    }

    private function serveStatic(string $path): void
    {
        $filePath = TAWASUL_ROOT . '/' . $path;
        if (file_exists($filePath) && is_file($filePath)) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'js' => 'application/javascript',
                'css' => 'text/css',
                'png' => 'image/png',
                'ico' => 'image/x-icon',
                'svg' => 'image/svg+xml',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'json' => 'application/json',
            ];
            $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
        } else {
            http_response_code(404);
            echo "File not found";
        }
    }

    private function serveWelcome(): void
    {
        $welcomeFile = TAWASUL_ROOT . '/public/welcome.html';
        if (file_exists($welcomeFile)) {
            $content = file_get_contents($welcomeFile);
            header('Content-Type: text/html; charset=utf-8');
            echo $content;
        } else {
            http_response_code(500);
            echo "TawasulOS frontend not found.";
        }
    }
}