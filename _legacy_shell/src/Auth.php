<?php
/**
 * TawasulOS Authentication
 *
 * Handles login, logout, password verification, and session management.
 */

namespace Tos;

class Auth
{
    private $pdo;
    private $session;

    public function __construct($pdo, Session $session)
    {
        $this->pdo = $pdo;
        $this->session = $session;
    }

    /**
     * Attempt login with username/email and password.
     */
    public function attempt(string $username, string $password, bool $remember = false): ?array
    {
        $username = trim($username);
        if (empty($username) || empty($password)) {
            return null;
        }

        // Look up by username or email
        $stmt = $this->pdo->prepare("
            SELECT * FROM tos_person 
            WHERE username = ? OR email = ?
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        // Check if login is allowed
        if ($user['canLogin'] !== 'Y') {
            return null;
        }

        // Verify password
        $salt = $user['passwordStrongSalt'] ?? '';
        $hash = hash('sha256', $password . $salt);
        
        if (!hash_equals($hash, $user['passwordStrong'])) {
            return null;
        }

        // Regenerate session ID for security
        $this->session->regenerate();

        // Store user info in session
        $_SESSION['tos_person_id'] = $user['person_id'];
        $_SESSION['tos_username'] = $user['username'];
        $_SESSION['tos_name'] = $user['firstName'] . ' ' . $user['surname'];
        $_SESSION['tos_role'] = $user['role_id_primary'];
        $_SESSION['tos_logged_in'] = true;
        $_SESSION['tos_login_time'] = time();

        return $user;
    }

    /**
     * Check if user is authenticated.
     */
    public function check(): bool
    {
        return isset($_SESSION['tos_logged_in']) && $_SESSION['tos_logged_in'] === true;
    }

    /**
     * Get current authenticated user.
     */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        $personId = $_SESSION['tos_person_id'] ?? null;
        if (!$personId) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM tos_person WHERE person_id = ? LIMIT 1");
        $stmt->execute([$personId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Logout current user.
     */
    public function logout(): void
    {
        $this->session->destroy();
    }

    /**
     * Hash a password with salt.
     */
    public static function hashPassword(string $password, string $salt = ''): string
    {
        return hash('sha256', $password . $salt);
    }

    /**
     * Generate a random salt.
     */
    public static function generateSalt(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}