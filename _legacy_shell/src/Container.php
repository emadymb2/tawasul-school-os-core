<?php
/**
 * TawasulOS Service Container
 *
 * Manages shared services and module loading. Acts as the dependency
 * injection container for the entire application.
 */

namespace Tos;

class Container
{
    private $pdo;
    private array $settings = [];
    private array $modules = [];
    private ?array $user = null;
    private array $services = [];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function loadSettings(): void
    {
        try {
            $rows = $this->pdo->query("SELECT name, value FROM tos_setting")->fetchAll(\PDO::FETCH_KEY_PAIR);
            foreach ($rows as $name => $value) {
                $this->settings[$name] = $value;
            }
        } catch (\PDOException $e) {
            // Table may not exist yet
        }
    }

    public function getSetting(string $name, $default = null)
    {
        return $this->settings[$name] ?? $default;
    }

    public function setSetting(string $name, $value): void
    {
        $this->settings[$name] = $value;
    }

    public function loadModules(): void
    {
        $moduleDir = TAWASUL_ROOT . '/modules';
        if (!is_dir($moduleDir)) {
            return;
        }

        $dirs = glob($moduleDir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $moduleName = basename($dir);
            $manifestFile = $dir . '/manifest.php';
            if (file_exists($manifestFile)) {
                try {
                    // Only read the top of the manifest for metadata
                    // to avoid executing SQL and other heavy code
                    $content = file_get_contents($manifestFile);
                    $manifest = $this->parseManifestMetadata($content);
                    if (!empty($manifest)) {
                        $this->modules[$moduleName] = $manifest;
                    }
                } catch (\Throwable $e) {
                    // Skip modules with broken manifests
                }
            }
        }
    }

    private function parseManifestMetadata(string $content): array
    {
        $manifest = [];
        
        // Extract simple variable assignments from the top of the file
        // Look for patterns like: $name = 'value'; or $name = "value";
        $patterns = [
            'name' => "/\\\$name\s*=\s*['\"]([^'\"]*)['\"]/",
            'description' => "/\\\$description\s*=\s*['\"]([^'\"]*)['\"]/",
            'entryURL' => "/\\\$entryURL\s*=\s*['\"]([^'\"]*)['\"]/",
            'type' => "/\\\$type\s*=\s*['\"]([^'\"]*)['\"]/",
            'category' => "/\\\$category\s*=\s*['\"]([^'\"]*)['\"]/",
            'version' => "/\\\$version\s*=\s*['\"]([^'\"]*)['\"]/",
            'author' => "/\\\$author\s*=\s*['\"]([^'\"]*)['\"]/",
            'url' => "/\\\$url\s*=\s*['\"]([^'\"]*)['\"]/",
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $manifest[$key] = $matches[1];
            }
        }

        return $manifest;
    }

    public function getModules(): array
    {
        return $this->modules;
    }

    public function getModule(string $name): ?array
    {
        return $this->modules[$name] ?? null;
    }

    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(defined('TAWASUL_SESSION_NAME') ? TAWASUL_SESSION_NAME : 'TAWASULSESSION');
            session_set_cookie_params(defined('TAWASUL_SESSION_LIFETIME') ? TAWASUL_SESSION_LIFETIME : 3600);
            session_start();
        }
    }

    public function getCurrentUser(): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $personId = $_SESSION['tos_person_id'] ?? null;
        if ($personId) {
            try {
                $this->user = $this->pdo->query("SELECT * FROM tos_person WHERE person_id = {$personId} LIMIT 1")->fetch();
            } catch (\PDOException $e) {
                $this->user = null;
            }
        }

        return $this->user;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        $this->user = null;
    }

    public function register(string $name, object $service): void
    {
        $this->services[$name] = $service;
    }

    public function get(string $name): ?object
    {
        return $this->services[$name] ?? null;
    }

    public function getPdo()
    {
        return $this->pdo;
    }

    public function render(string $template, array $data = []): string
    {
        extract($data);
        $templatePath = TAWASUL_ROOT . "/templates/{$template}.php";
        if (!file_exists($templatePath)) {
            return "Template not found: {$template}";
        }
        ob_start();
        require $templatePath;
        return ob_get_clean();
    }
}