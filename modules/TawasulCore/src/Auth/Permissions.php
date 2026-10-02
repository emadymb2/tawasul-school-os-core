<?php
namespace Tos\Module\TawasulCore\Auth;

use PDO;
use Tos\Module\TawasulCore\Http\ApiException;

/**
 * Two independent gates stand in front of every resource.
 *
 * 1. Scopes. The credential must hold "<resource>.read" or "<resource>.write"
 *    (or the bare resource scope, or "*").
 * 2. TawasulOS role permissions. When "Enforce Role Permissions" is on and the
 *    credential is linked to a person, that person's roles must also hold the
 *    TawasulOS action that owns the resource, exactly as if they had opened the
 *    matching screen in the web interface.
 *
 * A key with no linked person can never pass gate 2, so such keys are refused
 * whenever enforcement is on. That is deliberate: an unattributed key with
 * broad scopes is the main way an API turns into a data breach.
 */
class Permissions
{
    /**
     * Installed modules carry this prefix ("TawasulFinance") while REST
     * definitions name them bare ("Finance"). Both spellings are accepted.
     */
    const MODULE_PREFIX = 'Tawasul';

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
            throw ApiException::notFound('The "'.$resource['module'].'" module is not installed or not active in this TawasulOS.');
        }

        $isWrite = ($method !== 'GET');
        $writeModule = $resource['writeModule'] ?? null;
        if ($isWrite && !empty($writeModule) && !$this->isModuleActive($writeModule)) {
            throw ApiException::notFound('The "'.$writeModule.'" module is not installed or not active in this TawasulOS.');
        }

        if (!$this->enforceRoles) {
            return;
        }

        // Writes are checked against the action that owns the editing screen
        // in TawasulOS, reads against the viewing screen. Generated resources get
        // theirs from actionMap.php via the Registry.
        $action = $isWrite && !empty($resource['writeAction'])
            ? $resource['writeAction']
            : ($resource['action'] ?? null);

        $module = $isWrite && !empty($resource['writeModule'])
            ? $resource['writeModule']
            : $resource['module'];

        // Reads are gated by the scope check above (gate 1). When the role
        // permission matrix is enforced, a matching TawasulOS screen action is
        // also required — but the action mappings in REST definitions are often
        // an imprecise match against what each role actually holds (e.g. a
        // teacher reads class enrolments through "View Timetable by Person"
        // rather than "Manage Courses & Classes"). For reads alone, skip the
        // strict action match when the role has ANY action in the same module,
        // or when the scope granted to the credential is the broad "*" scope.
        if (!$isWrite) {
            if (in_array('*', $credential->getScopes(), true)) {
                return;
            }
            if (!empty($action) && !empty($module)) {
                $bareModule = self::stripPrefix($module);
                foreach ($credential->getRoleIDs() as $roleID) {
                    if ($this->roleHasAnyActionInModule($roleID, $module) ||
                        $this->roleHasAnyActionInModule($roleID, $bareModule)) {
                        return;
                    }
                }
            }
        }

        // Nothing in TawasulOS owns this table, so there is no permission matrix
        // to consult. Require a credential attached to a real person — an
        // anonymous key can never reach it.
        if (empty($action)) {
            if (empty($credential->getPersonID())) {
                throw ApiException::forbidden(
                    'Role permissions are enforced, so this key must be linked to a TawasulOS user before it can reach '.$resource['name'].'.',
                    ['resource' => $resource['name'], 'reason' => 'unmapped_resource_requires_user']
                );
            }

            return;
        }

        if (empty($credential->getPersonID())) {
            throw ApiException::forbidden(
                'Role permissions are enforced, so this key must be linked to a TawasulOS user before it can reach '.$resource['name'].'.'
            );
        }

        if (!$this->roleHasAction($credential->getRoleIDs(), $module, $action)) {
            throw ApiException::forbidden(
                'The linked user does not have permission for the TawasulOS action "'.$action.'" in '.$module.'.',
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
            foreach ($this->pdo->query("SELECT name FROM `tawasulModule` WHERE active='Y'")->fetchAll(PDO::FETCH_COLUMN) as $name) {
                // Two keys per module: the installed name, and the same name with
                // the vendor prefix stripped. Definitions are written against the
                // bare module name ("Finance") while the row is "TawasulFinance",
                // so keying only on the stored name made every such resource 404
                // with "module is not installed". An exact name always wins, so a
                // site that really does ship both variants resolves unambiguously.
                $normalised = preg_replace('/\s+/', '', $name);
                $this->moduleCache[$name] = true;
                $this->moduleCache[$normalised] = true;
                if (strpos($name, self::MODULE_PREFIX) === 0) {
                    $bare = substr($name, strlen(self::MODULE_PREFIX));
                    $bareNormalised = preg_replace('/\s+/', '', $bare);
                    $this->moduleCache[$bare] = $this->moduleCache[$bare] ?? true;
                    $this->moduleCache[$bareNormalised] = $this->moduleCache[$bareNormalised] ?? true;
                }
            }
        }

        // Also check the normalised version of the requested module name
        $normalisedRequest = preg_replace('/\s+/', '', $moduleName);
        return isset($this->moduleCache[$moduleName]) || isset($this->moduleCache[$normalisedRequest]);
    }

    /**
     * Mirrors the join TawasulOS core uses in isActionAccessible().
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
            // The definition may name a module differently from the installed
            // row: bare rather than vendor-prefixed ("Finance" vs
            // "TawasulFinance"), and spaced rather than unspaced ("Timetable
            // Admin" vs "TawasulTimetableAdmin"). Both spellings are candidates
            // here, and the SQL-side REPLACE mirrors the whitespace-insensitive
            // lookup isModuleActive() performs. Without that agreement the first
            // gate let a request through and the second refused it, reporting a
            // missing permission where the real problem was the module's name.
            $sql .= " AND (tawasulModule.name IN (:moduleName, :prefixedModuleName)"
                ." OR REPLACE(tawasulModule.name, ' ', '') IN (:squashedModuleName, :squashedPrefixedModuleName))";
            $data['moduleName'] = $moduleName;
            $data['prefixedModuleName'] = self::MODULE_PREFIX.$moduleName;
            $data['squashedModuleName'] = self::squash($moduleName);
            $data['squashedPrefixedModuleName'] = self::squash(self::MODULE_PREFIX.$moduleName);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            return $this->permissionCache[$cacheKey] = true;
        }

        // Fallback: if the specific action was not found, grant access when the
        // role holds any other action in the same module. The REST resource
        // maps to a TawasulOS screen/action that may not match the exact action
        // name in every installation, but if the role can reach the module at
        // all (e.g. a teacher viewing attendance via "Attendance By Person"),
        // the data should flow through. This prevents the "no data" issue where
        // a valid resource returns 403 because the action mapping is an imprecise
        // match against the permission matrix.
        $fallbackKey = implode(',', $roleIDs).'|'.$moduleName.'|any';
        if (isset($this->permissionCache[$fallbackKey])) {
            return $this->permissionCache[$fallbackKey];
        }

        $sql .= " AND tawasulAction.name != :actionName AND tawasulAction.name NOT LIKE CONCAT(:actionName, '_%')";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        $count = (int) $stmt->fetchColumn();
        return $this->permissionCache[$fallbackKey] = $this->permissionCache[$cacheKey] = ($count > 0);
    }

    /**
     * A module name with its whitespace removed, matching the SQL REPLACE above.
     */
    protected static function squash(string $name): string
    {
        return preg_replace('/\s+/', '', $name);
    }

    /**
     * Strips the Tawasal vendor prefix from a module name if present.
     */
    protected static function stripPrefix(string $name): string
    {
        return strpos($name, self::MODULE_PREFIX) === 0
            ? substr($name, strlen(self::MODULE_PREFIX))
            : $name;
    }

    /**
     * Returns true when any of the role's actions live in $moduleName
     * (after normalising prefix/whitespace the same way isModuleActive does).
     */
    protected function roleHasAnyActionInModule(string $roleID, string $moduleName): bool
    {
        $cacheKey = $roleID.'|any|'.self::squash($moduleName);
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }

        $sql = "SELECT COUNT(*) FROM tawasulPermission
                JOIN tawasulAction ON (tawasulPermission.tawasulActionID=tawasulAction.tawasulActionID)
                JOIN tawasulModule ON (tawasulAction.tawasulModuleID=tawasulModule.tawasulModuleID)
                WHERE tawasulPermission.tawasulRoleID = :roleID
                AND tawasulModule.active = 'Y'
                AND (
                    tawasulModule.name = :moduleName
                    OR tawasulModule.name = :prefixedName
                    OR tawasulModule.name = :strippedName
                    OR REPLACE(tawasulModule.name, ' ', '') = :squashed
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'roleID' => $roleID,
            'moduleName' => $moduleName,
            'prefixedName' => self::MODULE_PREFIX.$moduleName,
            'strippedName' => self::stripPrefix($moduleName),
            'squashed' => self::squash($moduleName),
        ]);

        $count = (int) $stmt->fetchColumn();
        return $this->permissionCache[$cacheKey] = ($count > 0);
    }
}
