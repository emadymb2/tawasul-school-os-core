<?php
namespace Tos\Module\TawasulCore\Domain;

use PDO;

/**
 * Short-lived bearer tokens issued by POST /v2/auth/login.
 *
 * Tokens and refresh tokens are stored hashed. Expired rows are pruned
 * opportunistically whenever a new token is issued, so no cron job is needed.
 */
class ApiTokenGateway
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function issue(string $tawasulPersonID, string $scopes, int $lifetimeMinutes, int $refreshMinutes, string $ipAddress, string $userAgent): array
    {
        $this->pruneExpired();

        $token = 'tok_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $refresh = 'ref_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $sql = "INSERT INTO tos_api_token (tawasulPersonID, tokenHash, refreshHash, scopes, ipAddress, userAgent, timestampExpiry, timestampLastUse)
                VALUES (:tawasulPersonID, :tokenHash, :refreshHash, :scopes, :ipAddress, :userAgent, DATE_ADD(NOW(), INTERVAL :lifetime MINUTE), NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tawasulPersonID', $tawasulPersonID);
        $stmt->bindValue(':tokenHash', self::hash($token));
        $stmt->bindValue(':refreshHash', self::hash($refresh));
        $stmt->bindValue(':scopes', $scopes);
        $stmt->bindValue(':ipAddress', $ipAddress);
        $stmt->bindValue(':userAgent', $userAgent);
        $stmt->bindValue(':lifetime', $lifetimeMinutes, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'accessToken' => $token,
            'refreshToken' => $refresh,
            'tokenType' => 'Bearer',
            'expiresIn' => $lifetimeMinutes * 60,
            'refreshExpiresIn' => $refreshMinutes * 60,
            'scopes' => $scopes,
        ];
    }

    public function selectByToken(string $token): ?array
    {
        $sql = "SELECT tos_api_token.*, tawasulPerson.username, tawasulPerson.status, tawasulPerson.canLogin, tawasulPerson.tawasulRoleIDPrimary, tawasulPerson.tawasulRoleIDAll
                FROM tos_api_token
                JOIN tawasulPerson ON (tos_api_token.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tos_api_token.tokenHash=:hash LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['hash' => self::hash($token)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function selectByRefreshToken(string $refresh): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tos_api_token WHERE refreshHash=:hash LIMIT 1");
        $stmt->execute(['hash' => self::hash($refresh)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function touch(string $tos_api_tokenID): void
    {
        $this->pdo->prepare("UPDATE tos_api_token SET timestampLastUse=NOW() WHERE tos_api_tokenID=:id")
            ->execute(['id' => $tos_api_tokenID]);
    }

    public function revoke(string $tos_api_tokenID): void
    {
        $this->pdo->prepare("DELETE FROM tos_api_token WHERE tos_api_tokenID=:id")->execute(['id' => $tos_api_tokenID]);
    }

    public function revokeAllForPerson(string $tawasulPersonID): void
    {
        $this->pdo->prepare("DELETE FROM tos_api_token WHERE tawasulPersonID=:id")->execute(['id' => $tawasulPersonID]);
    }

    public function pruneExpired(): void
    {
        $this->pdo->exec("DELETE FROM tos_api_token WHERE timestampExpiry IS NOT NULL AND timestampExpiry < DATE_SUB(NOW(), INTERVAL 14 DAY)");
    }
}
