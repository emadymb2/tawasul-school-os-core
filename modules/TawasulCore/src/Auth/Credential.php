<?php
namespace Tos\Module\TawasulCore\Auth;

/**
 * The authenticated caller: either an API key or a user bearer token.
 *
 * A credential carries its scopes and, when it is linked to a TawasulOS person,
 * that person's roles so TawasulOS's own permission matrix can be applied on top
 * of the scopes.
 */
class Credential
{
    protected $type;
    protected $tos_api_keyID;
    protected $tos_api_tokenID;
    protected $tawasulPersonID;
    protected $tawasulSchoolYearID;
    protected $scopes = [];
    protected $roleIDs = [];
    protected $keyName = '';

    public function __construct(string $type, array $attributes = [])
    {
        $this->type = $type;
        $this->tos_api_keyID = $attributes['tos_api_keyID'] ?? null;
        $this->tos_api_tokenID = $attributes['tos_api_tokenID'] ?? null;
        $this->tawasulPersonID = $attributes['tawasulPersonID'] ?? null;
        $this->tawasulSchoolYearID = $attributes['tawasulSchoolYearID'] ?? null;
        $this->keyName = $attributes['keyName'] ?? '';
        $this->scopes = self::parseList($attributes['scopes'] ?? '');
        $this->roleIDs = self::parseList($attributes['roles'] ?? '');
    }

    public static function parseList($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), 'strlen'));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getKeyID(): ?string
    {
        return $this->tos_api_keyID;
    }

    public function getTokenID(): ?string
    {
        return $this->tos_api_tokenID;
    }

    public function getPersonID(): ?string
    {
        return $this->tawasulPersonID;
    }

    public function getSchoolYearID(): ?string
    {
        return $this->tawasulSchoolYearID;
    }

    public function getKeyName(): string
    {
        return $this->keyName;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getRoleIDs(): array
    {
        return $this->roleIDs;
    }

    /**
     * A credential holding "*" may do anything; "users.read" also satisfies a
     * request for "users.read", and "users" on its own satisfies both the read
     * and write scope for that resource.
     */
    public function hasScope(string $scope): bool
    {
        if (in_array('*', $this->scopes, true) || in_array($scope, $this->scopes, true)) {
            return true;
        }

        $base = substr($scope, 0, (int) strrpos($scope, '.'));

        return $base !== '' && in_array($base, $this->scopes, true);
    }
}
