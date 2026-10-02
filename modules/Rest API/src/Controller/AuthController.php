<?php
namespace Gibbon\Module\RestAPI\Controller;

use PDO;
use Gibbon\Module\RestAPI\Auth\Credential;
use Gibbon\Module\RestAPI\Domain\ApiTokenGateway;
use Gibbon\Module\RestAPI\Http\ApiException;
use Gibbon\Module\RestAPI\Http\Request;
use Gibbon\Module\RestAPI\Resource\Registry;
use Gibbon\Module\RestAPI\Support\Settings;

/**
 * Username and password login, token refresh, logout and identity.
 *
 * Passwords are verified exactly the way Gibbon core does it in
 * Auth\Adapter\DefaultAdapter: sha256 over the per-user salt concatenated
 * with the password, compared in constant time. Failed attempts increment the
 * same failCount column the web login uses, so lockout policy still applies.
 */
class AuthController
{
    protected $pdo;
    protected $settings;
    protected $tokenGateway;

    public function __construct(PDO $pdo, Settings $settings, ApiTokenGateway $tokenGateway)
    {
        $this->pdo = $pdo;
        $this->settings = $settings;
        $this->tokenGateway = $tokenGateway;
    }

    public function login(Request $request): array
    {
        if (!$this->settings->isOn('allowPasswordGrant', false)) {
            throw ApiException::forbidden('Password login is disabled. Use an API key instead.');
        }

        $input = $request->require(['username', 'password']);

        $loginMethod = $this->settings->get('loginMethod', 'username');
        $allowPhone = in_array($loginMethod, ['phone', 'all'], true);

        if ($allowPhone) {
            $stmt = $this->pdo->prepare("SELECT tawasulPersonID, username, passwordStrong, passwordStrongSalt, status, canLogin, failCount, tawasulRoleIDPrimary, tawasulRoleIDAll, surname, preferredName, email
                FROM tawasulPerson WHERE username=:username OR email=:username OR phone1=:username OR phone2=:username OR phone3=:username OR phone4=:username LIMIT 1");
            $stmt->execute(['username' => $input['username']]);
        } else {
            $stmt = $this->pdo->prepare("SELECT tawasulPersonID, username, passwordStrong, passwordStrongSalt, status, canLogin, failCount, tawasulRoleIDPrimary, tawasulRoleIDAll, surname, preferredName, email
                FROM tawasulPerson WHERE username=:username OR email=:username LIMIT 1");
            $stmt->execute(['username' => $input['username']]);
        }
        $person = $stmt->fetch(PDO::FETCH_ASSOC);

        // One generic message for unknown user and wrong password, so the API
        // cannot be used to discover which usernames exist.
        $failure = ApiException::unauthorized('Those credentials were not accepted.');

        if (empty($person) || empty($person['passwordStrong']) || empty($person['passwordStrongSalt'])) {
            throw $failure;
        }
        if ($person['status'] !== 'Full' || $person['canLogin'] !== 'Y') {
            throw ApiException::forbidden('This account is not permitted to sign in.');
        }
        if ((int) $person['failCount'] >= 3) {
            throw ApiException::forbidden('This account is locked after too many failed attempts. An administrator must reset it.');
        }

        $candidate = hash('sha256', $person['passwordStrongSalt'].$input['password']);
        if (!hash_equals($person['passwordStrong'], $candidate)) {
            $this->pdo->prepare("UPDATE tawasulPerson SET failCount=failCount+1, lastFailTimestamp=NOW(), lastFailIPAddress=:ip WHERE tawasulPersonID=:id")
                ->execute(['id' => $person['tawasulPersonID'], 'ip' => $request->getClientIP()]);
            throw $failure;
        }

        $this->pdo->prepare("UPDATE tawasulPerson SET failCount=0, lastTimestamp=NOW(), lastIPAddress=:ip WHERE tawasulPersonID=:id")
            ->execute(['id' => $person['tawasulPersonID'], 'ip' => $request->getClientIP()]);

        // A password-grant token gets the scopes the caller asks for, but only
        // those its Gibbon roles also permit — so a user cannot grant themselves
        // * scope and bypass the role layer when enforceRolePermissions is off.
        $scopes = trim((string) $request->input('scopes', '*'));
        if ($scopes === '*') {
            $scopes = $this->resolveScopesFromRoles(
                $person['tawasulRoleIDAll'] ?: $person['tawasulRoleIDPrimary']
            );
        }

        $token = $this->tokenGateway->issue(
            $person['tawasulPersonID'],
            $scopes,
            $this->settings->getInt('tokenLifetime', 120),
            $this->settings->getInt('refreshLifetime', 20160),
            $request->getClientIP(),
            $request->getUserAgent()
        );

        return ['data' => $token + ['user' => [
            'tawasulPersonID' => $person['tawasulPersonID'],
            'username' => $person['username'],
            'surname' => $person['surname'],
            'preferredName' => $person['preferredName'],
            'email' => $person['email'],
        ]]];
    }

    public function refresh(Request $request): array
    {
        $input = $request->require(['refreshToken']);

        $existing = $this->tokenGateway->selectByRefreshToken($input['refreshToken']);
        if (empty($existing)) {
            throw ApiException::unauthorized('That refresh token is not recognised.');
        }

        $refreshLifetime = $this->settings->getInt('refreshLifetime', 20160);
        if (strtotime($existing['timestampCreated']) + ($refreshLifetime * 60) < time()) {
            $this->tokenGateway->revoke($existing['restApiTokenID']);
            throw ApiException::unauthorized('That refresh token has expired. Sign in again.');
        }

        // Rotate: the old pair is destroyed as the new pair is issued.
        $this->tokenGateway->revoke($existing['restApiTokenID']);

        $token = $this->tokenGateway->issue(
            $existing['tawasulPersonID'],
            $existing['scopes'],
            $this->settings->getInt('tokenLifetime', 120),
            $refreshLifetime,
            $request->getClientIP(),
            $request->getUserAgent()
        );

        return ['data' => $token];
    }

    public function logout(Credential $credential): array
    {
        if ($credential->getType() !== 'token') {
            throw ApiException::badRequest('Only tokens issued by /v2/auth/login can be logged out. Revoke API keys in Gibbon.');
        }

        $this->tokenGateway->revoke($credential->getTokenID());

        return ['data' => ['loggedOut' => true]];
    }

    /**
     * Who the current credential is, what it may do, and where it points.
     */
    public function me(Credential $credential): array
    {
        $person = null;

        if (!empty($credential->getPersonID())) {
            $stmt = $this->pdo->prepare("SELECT tawasulPerson.tawasulPersonID, tawasulPerson.username, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.firstName, tawasulPerson.preferredName, tawasulPerson.email, tawasulPerson.image_240, tawasulPerson.status, tawasulRole.name AS rolePrimary, tawasulRole.category AS roleCategory
                FROM tawasulPerson LEFT JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
                WHERE tawasulPerson.tawasulPersonID=:id LIMIT 1");
            $stmt->execute(['id' => $credential->getPersonID()]);
            $person = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $currentYear = $this->pdo->query("SELECT tawasulSchoolYearID, name, firstDay, lastDay FROM tawasulSchoolYear WHERE status='Current' LIMIT 1")
            ->fetch(PDO::FETCH_ASSOC) ?: null;

        return ['data' => [
            'credentialType' => $credential->getType(),
            'name' => $credential->getKeyName(),
            'scopes' => $credential->getScopes(),
            'person' => $person,
            'schoolYear' => $currentYear,
        ]];
    }

    /**
     * Expands a * scope into the concrete resource scopes that the person's
     * Gibbon roles actually grant, so a token can never hold a scope its owner
     * could not reach through the web interface.
     */
    protected function resolveScopesFromRoles(?string $roleID): string
    {
        $granted = [];
        $roleIDs = array_filter(array_map('trim', explode(',', (string) $roleID)));

        if (empty($roleIDs)) {
            return '';
        }

        $placeholders = [];
        $params = [];
        foreach ($roleIDs as $i => $roleID) {
            $placeholders[] = ':role'.$i;
            $params['role'.$i] = $roleID;
        }

        $sql = 'SELECT DISTINCT tawasulAction.name
                FROM tawasulPermission
                JOIN tawasulAction ON (tawasulPermission.tawasulActionID = tawasulAction.tawasulActionID)
                JOIN tawasulModule ON (tawasulAction.tawasulModuleID = tawasulModule.tawasulModuleID)
                WHERE tawasulPermission.tawasulRoleID IN ('.implode(',', $placeholders).')
                AND tawasulModule.active = :active';
        $params['active'] = 'Y';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach (Registry::all() as $resource) {
            if (empty($resource['action'])) {
                continue;
            }

            $readAction = $resource['action'];
            $writeAction = $resource['writeAction'] ?? $resource['action'];

            $hasRead = false;
            $hasWrite = false;
            foreach ($actions as $action) {
                if ($action === $readAction || strpos($action, $readAction.'_') === 0) {
                    $hasRead = true;
                }
                if ($action === $writeAction || strpos($action, $writeAction.'_') === 0) {
                    $hasWrite = true;
                }
            }

            if ($hasRead) {
                $granted[] = $resource['scope'].'.read';
            }
            if ($hasWrite && in_array('POST', $resource['methods'], true)) {
                $granted[] = $resource['scope'].'.write';
            }
        }

        $granted[] = 'meta.read';

        return implode(',', array_unique($granted));
    }
}
