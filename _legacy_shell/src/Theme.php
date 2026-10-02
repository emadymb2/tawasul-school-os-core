<?php
/**
 * TawasulOS Theme Management
 *
 * Loads theme settings, manages CSS/JS asset loading, and provides
 * theme-aware template rendering.
 */

namespace Tos;

class Theme
{
    private Container $container;
    private array $settings;
    private string $currentTheme;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings = $container->getSetting('theme', []);
        $this->currentTheme = $this->settings['name'] ?? 'default';
    }

    public function render(string $view, array $data = []): string
    {
        $viewPath = TAWASUL_ROOT . "/themes/{$this->currentTheme}/{$view}.php";
        if (!file_exists($viewPath)) {
            $viewPath = TAWASUL_ROOT . "/themes/default/{$view}.php";
        }

        if (!file_exists($viewPath)) {
            return "Theme view not found: {$view}";
        }

        extract($data);
        ob_start();
        require $viewPath;
        return ob_get_clean();
    }

    public function asset(string $path): string
    {
        return TAWASUL_ASSETS_URL . '/' . ltrim($path, '/');
    }

    public function getCurrentTheme(): string
    {
        return $this->currentTheme;
    }

    public function getCssAssets(): array
    {
        $cssFile = TAWASUL_ROOT . "/themes/{$this->currentTheme}/css/theme.css";
        return file_exists($cssFile) ? [$this->asset("themes/{$this->currentTheme}/css/theme.css")] : [];
    }

    public function getJsAssets(): array
    {
        $jsFile = TAWASUL_ROOT . "/themes/{$this->currentTheme}/js/theme.js";
        return file_exists($jsFile) ? [$this->asset("themes/{$this->currentTheme}/js/theme.js")] : [];
    }
}