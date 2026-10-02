<?php
/**
 * TawasulOS Session Management
 *
 * Handles user sessions with configurable lifetime, flash messages,
 * and CSRF token management.
 */

namespace Tos;

class Session
{
    private string $name;
    private int $lifetime;

    public function __construct(string $name = 'TAWASULSESSION', int $lifetime = 3600)
    {
        $this->name = $name;
        $this->lifetime = $lifetime;
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name($this->name);
            session_set_cookie_params($this->lifetime);
            session_start();
        }
    }

    public function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function flash(string $key, $default = null)
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public function setFlash(string $key, $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function getCsrfToken(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public function validateCsrf(string $token): bool
    {
        return hash_equals($_SESSION['_csrf'] ?? '', $token);
    }
}