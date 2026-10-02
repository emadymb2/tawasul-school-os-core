<?php
namespace Tos\Module\TawasulCore\Domain;

use PDO;

/**
 * Data access for API keys.
 *
 * A key is shown to the administrator exactly once, at creation. Only a
 * SHA-256 hash is stored, alongside a short non-secret prefix used to display
 * and identify the key afterwards.
 */
class ApiKeyGateway
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    /**
     * Generates a key in the form tws_<prefix>_<secret>. The prefix is stored
     * in clear so a key can be recognised in the UI and in logs.
     */
    public static function generate(): array
    {
        $prefix = bin2hex(random_bytes(4));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $full = 'tws_'.$prefix.'_'.$secret;

        return ['prefix' => $prefix, 'secret' => $full, 'hash' => self::hash($full)];
    }

    public function selectByHash(string $hash): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tos_api_key WHERE keyHash=:hash LIMIT 1");
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function selectAll(): array
    {
        $sql = "SELECT tos_api_key.*, tawasulPerson.surname, tawasulPerson.preferredName, tawasulSchoolYear.name AS schoolYearName,
                    (SELECT COUNT(*) FROM tos_api_log WHERE tos_api_log.tos_api_keyID=tos_api_key.tos_api_keyID) AS requestCount
                FROM tos_api_key
                LEFT JOIN tawasulPerson ON (tos_api_key.tawasulPersonID=tawasulPerson.tawasulPersonID)
                LEFT JOIN tawasulSchoolYear ON (tos_api_key.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                ORDER BY tos_api_key.active DESC, tos_api_key.name";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectByID(string $tos_api_keyID): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tos_api_key WHERE tos_api_keyID=:id LIMIT 1");
        $stmt->execute(['id' => $tos_api_keyID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insert(array $data): string
    {
        $sql = "INSERT INTO tos_api_key (name, description, keyPrefix, keyHash, tawasulPersonID, tawasulSchoolYearID, scopes, ipAllowList, rateLimit, active, dateExpiry, tawasulPersonIDCreator)
                VALUES (:name, :description, :keyPrefix, :keyHash, :tawasulPersonID, :tawasulSchoolYearID, :scopes, :ipAllowList, :rateLimit, :active, :dateExpiry, :tawasulPersonIDCreator)";
        $this->pdo->prepare($sql)->execute($data);

        return $this->pdo->lastInsertId();
    }

    public function update(string $tos_api_keyID, array $data): bool
    {
        $data['tos_api_keyID'] = $tos_api_keyID;
        $sql = "UPDATE tos_api_key SET name=:name, description=:description, tawasulPersonID=:tawasulPersonID, tawasulSchoolYearID=:tawasulSchoolYearID,
                    scopes=:scopes, ipAllowList=:ipAllowList, rateLimit=:rateLimit, active=:active, dateExpiry=:dateExpiry
                WHERE tos_api_keyID=:tos_api_keyID";

        return $this->pdo->prepare($sql)->execute($data);
    }

    public function delete(string $tos_api_keyID): bool
    {
        return $this->pdo->prepare("DELETE FROM tos_api_key WHERE tos_api_keyID=:id")->execute(['id' => $tos_api_keyID]);
    }

    public function touch(string $tos_api_keyID, string $ipAddress): void
    {
        $this->pdo->prepare("UPDATE tos_api_key SET lastAccess=NOW(), lastIPAddress=:ip WHERE tos_api_keyID=:id")
            ->execute(['id' => $tos_api_keyID, 'ip' => $ipAddress]);
    }

    /**
     * Requests made by this key in the last sixty seconds, used for rate limiting.
     */
    public function countRecentRequests(string $tos_api_keyID): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tos_api_log WHERE tos_api_keyID=:id AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        $stmt->execute(['id' => $tos_api_keyID]);

        return (int) $stmt->fetchColumn();
    }
}
