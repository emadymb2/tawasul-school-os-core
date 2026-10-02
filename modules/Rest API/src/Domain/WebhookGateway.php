<?php
namespace Gibbon\Module\RestAPI\Domain;

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
        $sql = "SELECT restApiWebhook.*,
                    (SELECT COUNT(*) FROM restApiWebhookDelivery WHERE restApiWebhookDelivery.restApiWebhookID=restApiWebhook.restApiWebhookID) AS deliveryCount,
                    (SELECT COUNT(*) FROM restApiWebhookDelivery WHERE restApiWebhookDelivery.restApiWebhookID=restApiWebhook.restApiWebhookID AND restApiWebhookDelivery.success='N') AS failureCount
                FROM restApiWebhook
                ORDER BY restApiWebhook.active DESC, restApiWebhook.name";

        try {
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    public function selectByID(string $restApiWebhookID): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM restApiWebhook WHERE restApiWebhookID=:id LIMIT 1");
        $stmt->execute(['id' => $restApiWebhookID]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Active subscriptions whose event list matches the given event, honouring
     * "resource.*" and bare "*" wildcards.
     */
    public function selectForEvent(string $event): array
    {
        try {
            $rows = $this->pdo->query("SELECT * FROM restApiWebhook WHERE active='Y'")->fetchAll(PDO::FETCH_ASSOC);
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
        $sql = "INSERT INTO restApiWebhook (name, url, events, secret, active, headers, tawasulPersonIDCreator)
                VALUES (:name, :url, :events, :secret, :active, :headers, :tawasulPersonIDCreator)";
        $this->pdo->prepare($sql)->execute($data);

        return $this->pdo->lastInsertId();
    }

    public function update(string $restApiWebhookID, array $data): bool
    {
        $data['restApiWebhookID'] = $restApiWebhookID;
        $sql = "UPDATE restApiWebhook SET name=:name, url=:url, events=:events, active=:active, headers=:headers
                WHERE restApiWebhookID=:restApiWebhookID";

        return $this->pdo->prepare($sql)->execute($data);
    }

    public function delete(string $restApiWebhookID): bool
    {
        $this->pdo->prepare("DELETE FROM restApiWebhookDelivery WHERE restApiWebhookID=:id")->execute(['id' => $restApiWebhookID]);

        return $this->pdo->prepare("DELETE FROM restApiWebhook WHERE restApiWebhookID=:id")->execute(['id' => $restApiWebhookID]);
    }

    public function recordDelivery(array $delivery): void
    {
        $sql = "INSERT INTO restApiWebhookDelivery (restApiWebhookID, event, payload, statusCode, success, durationMS, response)
                VALUES (:restApiWebhookID, :event, :payload, :statusCode, :success, :durationMS, :response)";

        try {
            $this->pdo->prepare($sql)->execute([
                'restApiWebhookID' => $delivery['restApiWebhookID'],
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
            $stmt = $this->pdo->prepare("SELECT restApiWebhookDelivery.*, restApiWebhook.name, restApiWebhook.url
                FROM restApiWebhookDelivery
                JOIN restApiWebhook ON (restApiWebhookDelivery.restApiWebhookID=restApiWebhook.restApiWebhookID)
                ORDER BY restApiWebhookDelivery.timestamp DESC LIMIT :limit");
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
            $stmt = $this->pdo->prepare("DELETE FROM restApiWebhookDelivery WHERE timestamp < DATE_SUB(NOW(), INTERVAL :days DAY)");
            $stmt->bindValue(':days', $retentionDays, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            // Non-fatal.
        }
    }
}
