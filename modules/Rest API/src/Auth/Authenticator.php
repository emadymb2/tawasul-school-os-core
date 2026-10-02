<?php
namespace Gibbon\Module\RestAPI\Auth;

use PDO;
use Gibbon\Module\RestAPI\Domain\ApiKeyGateway;
use Gibbon\Module\RestAPI\Domain\ApiTokenGateway;
use Gibbon\Module\RestAPI\Http\ApiException;
use Gibbon\Module\RestAPI\Http\Request;

/**
 * Resolves the Authorization header into a Credential.
 *
 * Two credential types are accepted:
 *   gib_...  a long-lived API key, optionally bound to a person, a school
 *            year, an IP allow list and a per-minute rate limit
 *   tok_...  a short-lived bearer token from POST /v2/auth/login
 *
 * Both are compared by hash, so a database leak never yields a usable key.
 */
class Authenticator
{
    protected $pdo;
    protected $keyGateway;
    protected $tokenGateway;

    /** Populated during authentication so the Kernel can send X-RateLimit-*. */
    protected $rateLimit = null;
    protected $rateRemaining = null;

    public function __construct(PDO $pdo, ApiKeyGateway $keyGateway, ApiTokenGateway $tokenGateway)
    {
        $this->pdo = $pdo;
        $this->keyGateway = $keyGateway;
        $this->tokenGateway = $tokenGateway;
    }

    public function authenticate(Request $request): Credential
    {
        $secret = $request->getBearerToken();
        if (empty($secret)) {
            throw ApiException::unauthorized('No credential supplied. Send Authorization: Bearer <key>.');
        }

        return strpos($secret, 'tok_') === 0
            ? $this->authenticateToken($secret, $request)
            : $this->authenticateKey($secret, $request);
    }

    protected function authenticateKey(string $secret, Request $request): Credential
    {
        $key = $this->keyGateway->selectByHash(ApiKeyGateway::hash($secret));
        if (empty($key)) {
            throw ApiException::unauthorized('The supplied API key is not recognised.');
        }

        if ($key['active'] !== 'Y') {
            throw ApiException::unauthorized('This API key has been deactivated.');
        }

        if (!empty($key['dateExpiry']) && $key['dateExpiry'] < date('Y-m-d')) {
            throw ApiException::unauthorized('This API key expired on '.$key['dateExpiry'].'.');
        }

        $this->checkIPAllowList($key['ipAllowList'], $request->getClientIP());

        $limit = (int) $key['rateLimit'];
        if ($limit > 0) {
            $used = $this->keyGateway->countRecentRequests($key['restApiKeyID']);
            $this->rateLimit = $limit;
            $this->rateRemaining = max(0, $limit - $used - 1);

            if ($used >= $limit) {
                $this->rateRemaining = 0;
                throw ApiException::rateLimited('Rate limit of '.$limit.' requests per minute exceeded for this key.');
            }
        }

        $this->keyGateway->touch($key['restApiKeyID'], $request->getClientIP());

        return new Credential('key', [
            'restApiKeyID' => $key['restApiKeyID'],
            'tawasulPersonID' => $key['tawasulPersonID'],
            'tawasulSchoolYearID' => $key['tawasulSchoolYearID'],
            'scopes' => $key['scopes'],
            'keyName' => $key['name'],
            'roles' => $this->getRolesForPerson($key['tawasulPersonID']),
        ]);
    }

    protected function authenticateToken(string $secret, Request $request): Credential
    {
        $token = $this->tokenGateway->selectByToken($secret);
        if (empty($token)) {
            throw ApiException::unauthorized('The supplied token is not recognised.');
        }

        if (!empty($token['timestampExpiry']) && strtotime($token['timestampExpiry']) < time()) {
            throw ApiException::unauthorized('This token has expired. Use the refresh token to obtain a new one.');
        }

        if ($token['status'] !== 'Full' || $token['canLogin'] !== 'Y') {
            throw ApiException::unauthorized('The account behind this token can no longer sign in.');
        }

        $this->tokenGateway->touch($token['restApiTokenID']);

        return new Credential('token', [
            'restApiTokenID' => $token['restApiTokenID'],
            'tawasulPersonID' => $token['tawasulPersonID'],
            'scopes' => $token['scopes'],
            'keyName' => 'User token',
            'roles' => $token['tawasulRoleIDAll'] ?: $token['tawasulRoleIDPrimary'],
        ]);
    }

    /**
     * Accepts plain addresses and CIDR ranges. An empty list means any address.
     */
    protected function checkIPAllowList(string $allowList, string $ipAddress): void
    {
        $allowList = trim($allowList);
        if ($allowList === '') {
            return;
        }

        foreach (array_filter(array_map('trim', explode(',', $allowList)), 'strlen') as $allowed) {
            if ($allowed === $ipAddress) {
                return;
            }
            if (strpos($allowed, '/') !== false && $this->ipInRange($ipAddress, $allowed)) {
                return;
            }
        }

        throw ApiException::forbidden('Requests from '.$ipAddress.' are not permitted for this key.');
    }

    protected function ipInRange(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    protected function getRolesForPerson(?string $tawasulPersonID): string
    {
        if (empty($tawasulPersonID)) {
            return '';
        }

        $stmt = $this->pdo->prepare("SELECT tawasulRoleIDAll, tawasulRoleIDPrimary FROM tawasulPerson WHERE tawasulPersonID=:id");
        $stmt->execute(['id' => $tawasulPersonID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? ($row['tawasulRoleIDAll'] ?: $row['tawasulRoleIDPrimary']) : '';
    }

    /**
     * Rate-limit state for the credential just authenticated, so the response
     * can advertise X-RateLimit-Limit and X-RateLimit-Remaining.
     */
    public function getRateLimitInfo(): array
    {
        return ['limit' => $this->rateLimit, 'remaining' => $this->rateRemaining];
    }
}
