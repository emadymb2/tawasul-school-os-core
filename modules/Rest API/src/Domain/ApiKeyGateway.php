<?php
namespace Gibbon\Module\RestAPI\Domain;

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
     * Generates a key in the form gib_<prefix>_<secret>. The prefix is stored
     * in clear so a key can be recognised in the UI and in logs.
     */
    public static function generate(): array
    {
        $prefix = bin2hex(random_bytes(4));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $full = 'gib_'.$prefix.'_'.$secret;

        return ['prefix' => $prefix, 'secret' => $full, 'hash' => self::hash($full)];
    }

    public function selectByHash(string $hash): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM restApiKey WHERE keyHash=:hash LIMIT 1");
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function selectAll(): array
    {
        $sql = "SELECT restApiKey.*, tawasulPerson.surname, tawasulPerson.preferredName, tawasulSchoolYear.name AS schoolYearName,
                    (SELECT COUNT(*) FROM restApiLog WHERE restApiLog.restApiKeyID=restApiKey.restApiKeyID) AS requestCount
                FROM restApiKey
                LEFT JOIN tawasulPerson ON (restApiKey.tawasulPersonID=tawasulPerson.tawasulPersonID)
                LEFT JOIN tawasulSchoolYear ON (restApiKey.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                ORDER BY restApiKey.active DESC, restApiKey.name";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectByID(string $restApiKeyID): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM restApiKey WHERE restApiKeyID=:id LIMIT 1");
        $stmt->execute(['id' => $restApiKeyID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insert(array $data): string
    {
        $sql = "INSERT INTO restApiKey (name, description, keyPrefix, keyHash, tawasulPersonID, tawasulSchoolYearID, scopes, ipAllowList, rateLimit, active, dateExpiry, tawasulPersonIDCreator)
                VALUES (:name, :description, :keyPrefix, :keyHash, :tawasulPersonID, :tawasulSchoolYearID, :scopes, :ipAllowList, :rateLimit, :active, :dateExpiry, :tawasulPersonIDCreator)";
        $this->pdo->prepare($sql)->execute($data);

        return $this->pdo->lastInsertId();
    }

    public function update(string $restApiKeyID, array $data): bool
    {
        $data['restApiKeyID'] = $restApiKeyID;
        $sql = "UPDATE restApiKey SET name=:name, description=:description, tawasulPersonID=:tawasulPersonID, tawasulSchoolYearID=:tawasulSchoolYearID,
                    scopes=:scopes, ipAllowList=:ipAllowList, rateLimit=:rateLimit, active=:active, dateExpiry=:dateExpiry
                WHERE restApiKeyID=:restApiKeyID";

        return $this->pdo->prepare($sql)->execute($data);
    }

    public function delete(string $restApiKeyID): bool
    {
        return $this->pdo->prepare("DELETE FROM restApiKey WHERE restApiKeyID=:id")->execute(['id' => $restApiKeyID]);
    }

    public function touch(string $restApiKeyID, string $ipAddress): void
    {
        $this->pdo->prepare("UPDATE restApiKey SET lastAccess=NOW(), lastIPAddress=:ip WHERE restApiKeyID=:id")
            ->execute(['id' => $restApiKeyID, 'ip' => $ipAddress]);
    }

    /**
     * Requests made by this key in the last sixty seconds, used for rate limiting.
     */
    public function countRecentRequests(string $restApiKeyID): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM restApiLog WHERE restApiKeyID=:id AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        $stmt->execute(['id' => $restApiKeyID]);

        return (int) $stmt->fetchColumn();
    }
}
