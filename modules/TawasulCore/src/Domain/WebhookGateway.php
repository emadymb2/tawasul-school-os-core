<?php
namespace Tos\Module\TawasulCore\Domain;

use PDO;

/**
 * Webhook subscriptions and their delivery history.
 *
 * A subscription is a URL plus a list of events such as "students.created" or
 * "attendance.*". The signing secret is stored in clear because the receiving
 * system needs the same value to verify the HMAC signature; it is only ever
 * shown to administrators who can already read every record through the UI.
 */
class WebhookGateway
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.bin2hex(random_bytes(24));
    }

    public function selectAll(): array
    {
        $sql = "SELECT tos_api_webhook.*,
                    (SELECT COUNT(*) FROM tos_api_webhookDelivery WHERE tos_api_webhookDelivery.tos_api_webhookID=tos_api_webhook.tos_api_webhookID) AS deliveryCount,
                    (SELECT COUNT(*) FROM tos_api_webhookDelivery WHERE tos_api_webhookDelivery.tos_api_webhookID=tos_api_webhook.tos_api_webhookID AND tos_api_webhookDelivery.success='N') AS failureCount
                FROM tos_api_webhook
                ORDER BY tos_api_webhook.active DESC, tos_api_webhook.name";

        try {
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    public function selectByID(string $tos_api_webhookID): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tos_api_webhook WHERE tos_api_webhookID=:id LIMIT 1");
        $stmt->execute(['id' => $tos_api_webhookID]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Active subscriptions whose event list matches the given event, honouring
     * "resource.*" and bare "*" wildcards.
     */
    public function selectForEvent(string $event): array
    {
        try {
            $rows = $this->pdo->query("SELECT * FROM tos_api_webhook WHERE active='Y'")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }

        [$resource] = array_pad(explode('.', $event, 2), 2, '');
        $matches = [];

        foreach ($rows as $row) {
            $events = array_filter(array_map('trim', explode(',', (string) $row['events'])), 'strlen');
            foreach ($events as $pattern) {
                if ($pattern === '*' || $pattern === $event || $pattern === $resource.'.*' || $pattern === '*.'.substr($event, strrpos($event, '.') + 1)) {
                    $matches[] = $row;
                    break;
                }
            }
        }

        return $matches;
    }

    public function insert(array $data): string
    {
        $sql = "INSERT INTO tos_api_webhook (name, url, events, secret, active, headers, tawasulPersonIDCreator)
                VALUES (:name, :url, :events, :secret, :active, :headers, :tawasulPersonIDCreator)";
        $this->pdo->prepare($sql)->execute($data);

        return $this->pdo->lastInsertId();
    }

    public function update(string $tos_api_webhookID, array $data): bool
    {
        $data['tos_api_webhookID'] = $tos_api_webhookID;
        $sql = "UPDATE tos_api_webhook SET name=:name, url=:url, events=:events, active=:active, headers=:headers
                WHERE tos_api_webhookID=:tos_api_webhookID";

        return $this->pdo->prepare($sql)->execute($data);
    }

    public function delete(string $tos_api_webhookID): bool
    {
        $this->pdo->prepare("DELETE FROM tos_api_webhookDelivery WHERE tos_api_webhookID=:id")->execute(['id' => $tos_api_webhookID]);

        return $this->pdo->prepare("DELETE FROM tos_api_webhook WHERE tos_api_webhookID=:id")->execute(['id' => $tos_api_webhookID]);
    }

    public function recordDelivery(array $delivery): void
    {
        $sql = "INSERT INTO tos_api_webhookDelivery (tos_api_webhookID, event, payload, statusCode, success, durationMS, response)
                VALUES (:tos_api_webhookID, :event, :payload, :statusCode, :success, :durationMS, :response)";

        try {
            $this->pdo->prepare($sql)->execute([
                'tos_api_webhookID' => $delivery['tos_api_webhookID'],
                'event' => mb_substr((string) $delivery['event'], 0, 60),
                'payload' => mb_substr((string) $delivery['payload'], 0, 60000),
                'statusCode' => (int) $delivery['statusCode'],
                'success' => !empty($delivery['success']) ? 'Y' : 'N',
                'durationMS' => (int) $delivery['durationMS'],
                'response' => mb_substr((string) $delivery['response'], 0, 500),
            ]);
        } catch (\PDOException $e) {
            // A webhook log failure must never break the API response.
        }
    }

    public function recentDeliveries(int $limit = 100): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT tos_api_webhookDelivery.*, tos_api_webhook.name, tos_api_webhook.url
                FROM tos_api_webhookDelivery
                JOIN tos_api_webhook ON (tos_api_webhookDelivery.tos_api_webhookID=tos_api_webhook.tos_api_webhookID)
                ORDER BY tos_api_webhookDelivery.timestamp DESC LIMIT :limit");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    public function pruneDeliveries(int $retentionDays): void
    {
        if ($retentionDays < 1) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM tos_api_webhookDelivery WHERE timestamp < DATE_SUB(NOW(), INTERVAL :days DAY)");
            $stmt->bindValue(':days', $retentionDays, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            // Non-fatal.
        }
    }
}
