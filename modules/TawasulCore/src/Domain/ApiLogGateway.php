<?php
namespace Tos\Module\TawasulCore\Domain;

use PDO;

/**
 * The request log. Every API call lands here, including failed authentication
 * attempts, which makes it both an audit trail and the rate-limit counter.
 */
class ApiLogGateway
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function record(array $entry): void
    {
        $sql = "INSERT INTO tos_api_log (tos_api_keyID, tawasulPersonID, method, endpoint, resource, statusCode, durationMS, message, ipAddress)
                VALUES (:tos_api_keyID, :tawasulPersonID, :method, :endpoint, :resource, :statusCode, :durationMS, :message, :ipAddress)";

        try {
            $this->pdo->prepare($sql)->execute([
                'tos_api_keyID' => $entry['tos_api_keyID'] ?? null,
                'tawasulPersonID' => $entry['tawasulPersonID'] ?? null,
                'method' => mb_substr((string) ($entry['method'] ?? ''), 0, 8),
                'endpoint' => mb_substr((string) ($entry['endpoint'] ?? ''), 0, 255),
                'resource' => mb_substr((string) ($entry['resource'] ?? ''), 0, 60),
                'statusCode' => (int) ($entry['statusCode'] ?? 200),
                'durationMS' => (int) ($entry['durationMS'] ?? 0),
                'message' => mb_substr((string) ($entry['message'] ?? ''), 0, 255),
                'ipAddress' => mb_substr((string) ($entry['ipAddress'] ?? ''), 0, 45),
            ]);
        } catch (\PDOException $e) {
            // Logging must never break a response, for example while the
            // module is mid-upgrade and the table has not been created yet.
        }
    }

    public function prune(int $retentionDays): void
    {
        if ($retentionDays < 1) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM tos_api_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL :days DAY)");
            $stmt->bindValue(':days', $retentionDays, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            // Non-fatal.
        }
    }

    public function summary(): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(statusCode >= 400) AS errors,
                    ROUND(AVG(durationMS)) AS averageDuration,
                    SUM(timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY)) AS last24Hours,
                    SUM(statusCode >= 400 AND timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY)) AS errors24Hours,
                    SUM(statusCode >= 500 AND timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY)) AS serverErrors24Hours
                FROM tos_api_log";

        try {
            return $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            return [];
        }
    }
}
