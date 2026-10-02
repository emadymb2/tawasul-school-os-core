<?php
namespace Tos\Module\TawasulCore\Support;

use PDO;
use Tos\Module\TawasulCore\Http\ApiException;

/**
 * Per-address throttling for the credential endpoints.
 *
 * TawasulOS core locks an account after three failed passwords, which protects
 * one account but does nothing about an attacker walking a username list from
 * a single machine: each miss simply locks somebody else out. The request log
 * already records every failed /auth/login with its IP address, so it can be
 * used as the counter here — no extra table, and the same window is visible to
 * an administrator in View Request Log.
 */
class LoginThrottle
{
    protected $pdo;
    protected $settings;

    public function __construct(PDO $pdo, Settings $settings)
    {
        $this->pdo = $pdo;
        $this->settings = $settings;
    }

    /**
     * Called before a password or refresh token is checked.
     */
    public function assertAllowed(string $ipAddress, string $endpoint = 'auth'): void
    {
        $max = $this->settings->getInt('loginMaxAttempts', 10);
        $minutes = $this->settings->getInt('loginLockoutMinutes', 15);

        if ($max <= 0 || $minutes <= 0 || $ipAddress === '') {
            return;
        }

        $failures = $this->countRecentFailures($ipAddress, $minutes);

        if ($failures >= $max) {
            throw ApiException::rateLimited(
                'Too many failed sign-in attempts from this address. Try again in '.$minutes.' minutes.',
                ['failedAttempts' => $failures, 'windowMinutes' => $minutes]
            );
        }
    }

    public function countRecentFailures(string $ipAddress, int $minutes): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tos_api_log
                WHERE ipAddress=:ip
                    AND endpoint LIKE '%auth/%'
                    AND statusCode IN (401, 403)
                    AND timestamp > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)");
            $stmt->bindValue(':ip', $ipAddress);
            $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
            $stmt->execute();

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            // A missing log table must not lock everybody out mid-upgrade.
            return 0;
        }
    }

    /**
     * Requests made in the last minute by the person behind a user token, used
     * to give password-grant credentials the same ceiling API keys have.
     */
    public function countRecentRequestsForPerson(string $tawasulPersonID): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tos_api_log
                WHERE tawasulPersonID=:id AND tos_api_keyID IS NULL
                    AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
            $stmt->execute(['id' => $tawasulPersonID]);

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            return 0;
        }
    }
}
