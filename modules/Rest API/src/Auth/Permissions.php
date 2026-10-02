<?php
namespace Gibbon\Module\RestAPI\Auth;

use PDO;
use Gibbon\Module\RestAPI\Http\ApiException;

/**
 * Two independent gates stand in front of every resource.
 *
 * 1. Scopes. The credential must hold "<resource>.read" or "<resource>.write"
 *    (or the bare resource scope, or "*").
 * 2. Gibbon role permissions. When "Enforce Role Permissions" is on and the
 *    credential is linked to a person, that person's roles must also hold the
 *    Gibbon action that owns the resource, exactly as if they had opened the
 *    matching screen in the web interface.
 *
 * A key with no linked person can never pass gate 2, so such keys are refused
 * whenever enforcement is on. That is deliberate: an unattributed key with
 * broad scopes is the main way an API turns into a data breach.
 */
class Permissions
{
    protected $pdo;
    protected $enforceRoles;
    protected $moduleCache = null;
    protected $permissionCache = [];

    public function __construct(PDO $pdo, bool $enforceRoles)
    {
        $this->pdo = $pdo;
        $this->enforceRoles = $enforceRoles;
    }

    public function authorise(Credential $credential, array $resource, string $method): void
    {
        $scope = $resource['scope'].($method === 'GET' ? '.read' : '.write');

        if (!$credential->hasScope($scope)) {
            throw ApiException::forbidden(
                'This credential does not hold the "'.$scope.'" scope.',
                ['requiredScope' => $scope, 'grantedScopes' => $credential->getScopes()]
            );
        }

        if (!empty($resource['module']) && !$this->isModuleActive($resource['module'])) {
            throw ApiException::notFound('The "'.$resource['module'].'" module is not installed or not active in this Gibbon.');
        }

        $isWrite = ($method !== 'GET');
        $writeModule = $resource['writeModule'] ?? null;
        if ($isWrite && !empty($writeModule) && !$this->isModuleActive($writeModule)) {
            throw ApiException::notFound('The "'.$writeModule.'" module is not installed or not active in this Gibbon.');
        }

        if (!$this->enforceRoles) {
            return;
        }

        // Writes are checked against the action that owns the editing screen
        // in Gibbon, reads against the viewing screen. Generated resources get
        // theirs from actionMap.php via the Registry.
        $action = $isWrite && !empty($resource['writeAction'])
            ? $resource['writeAction']
            : ($resource['action'] ?? null);

        $module = $isWrite && !empty($resource['writeModule'])
            ? $resource['writeModule']
            : $resource['module'];

        // Nothing in Gibbon owns this table, so there is no permission matrix
        // to consult. Require a credential attached to a real person — an
        // anonymous key can never reach it.
        if (empty($action)) {
            if (empty($credential->getPersonID())) {
                throw ApiException::forbidden(
                    'Role permissions are enforced, so this key must be linked to a Gibbon user before it can reach '.$resource['name'].'.',
                    ['resource' => $resource['name'], 'reason' => 'unmapped_resource_requires_user']
                );
            }

            return;
        }

        if (empty($credential->getPersonID())) {
            throw ApiException::forbidden(
                'Role permissions are enforced, so this key must be linked to a Gibbon user before it can reach '.$resource['name'].'.'
            );
        }

        if (!$this->roleHasAction($credential->getRoleIDs(), $module, $action)) {
            throw ApiException::forbidden(
                'The linked user does not have permission for the Gibbon action "'.$action.'" in '.$module.'.',
                [
                    'action' => $action,
                    'module' => $module,
                    'method' => $method,
                    'resource' => $resource['name'],
                ]
            );
        }

    }

    public function isModuleActive(string $moduleName): bool
    {
        if ($this->moduleCache === null) {
            $this->moduleCache = [];
            foreach ($this->pdo->query("SELECT name FROM tawasulModule WHERE active='Y'")->fetchAll(PDO::FETCH_COLUMN) as $name) {
                $this->moduleCache[$name] = true;
            }
        }

        return isset($this->moduleCache[$moduleName]);
    }

    /**
     * Mirrors the join Gibbon core uses in isActionAccessible().
     */
    public function roleHasAction(array $roleIDs, ?string $moduleName, string $actionName): bool
    {
        if (empty($roleIDs)) {
            return false;
        }

        $cacheKey = implode(',', $roleIDs).'|'.$moduleName.'|'.$actionName;
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }

        $rolePlaceholders = [];
        $data = ['actionName' => $actionName];
        foreach (array_values($roleIDs) as $i => $roleID) {
            $rolePlaceholders[] = ':role'.$i;
            $data['role'.$i] = $roleID;
        }

        $sql = "SELECT COUNT(*) FROM tawasulPermission
                JOIN tawasulAction ON (tawasulPermission.tawasulActionID=tawasulAction.tawasulActionID)
                JOIN tawasulModule ON (tawasulAction.tawasulModuleID=tawasulModule.tawasulModuleID)
                WHERE tawasulPermission.tawasulRoleID IN (".implode(',', $rolePlaceholders).")
                AND tawasulModule.active='Y'
                AND (tawasulAction.name=:actionName OR tawasulAction.name LIKE CONCAT(:actionName, '_%'))";

        if (!empty($moduleName)) {
            $sql .= " AND tawasulModule.name=:moduleName";
            $data['moduleName'] = $moduleName;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return $this->permissionCache[$cacheKey] = ((int) $stmt->fetchColumn() > 0);
    }
}
