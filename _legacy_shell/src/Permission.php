<?php
/**
 * TawasulOS — Permission System
 *
 * Manages role-based access control (RBAC). Users have roles, roles have
 * permissions on modules and actions. Permissions are checked against
 * the action map defined in each module's manifest.
 */

namespace Tos;

class Permission
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Check if the current user has permission to perform an action
     */
    public function hasPermission(string $actionName, ?int $personId = null): bool
    {
        $user = $this->container->getCurrentUser();
        if (!$user) {
            return false;
        }

        $personId = $personId ?? $user['person_id'];
        $roleName = $user['role_name'] ?? 'Student';

        // Admin has all permissions
        if ($roleName === 'Admin' || $roleName === 'System Admin') {
            return true;
        }

        // Check specific permission
        try {
            $sql = "SELECT COUNT(*) FROM tos_permission
                    WHERE person_id = ? AND action_name = ?
                    AND (can_access = 'Y' OR can_write = 'Y')";

            $count = $this->container->getPdo()
                ->query($sql, [$personId, $actionName])
                ->fetchColumn();

            return $count > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Check if the current user has a specific role
     */
    public function hasRole(string $roleName): bool
    {
        $user = $this->container->getCurrentUser();
        if (!$user) {
            return false;
        }
        return ($user['role_name'] ?? '') === $roleName;
    }

    /**
     * Check if the current user can access a module
     */
    public function canAccessModule(string $moduleName): bool
    {
        $user = $this->container->getCurrentUser();
        if (!$user) {
            return false;
        }

        $roleName = $user['role_name'] ?? 'Student';
        if ($roleName === 'Admin' || $roleName === 'System Admin') {
            return true;
        }

        try {
            $sql = "SELECT COUNT(*) FROM tos_permission
                    WHERE person_id = ? AND module_name = ?
                    AND can_access = 'Y'";

            $count = $this->container->getPdo()
                ->query($sql, [$user['person_id'], $moduleName])
                ->fetchColumn();

            return $count > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Get all permissions for a user
     */
    public function getPermissions(int $personId): array
    {
        try {
            return $this->container->getPdo()
                ->query("SELECT * FROM tos_permission WHERE person_id = {$personId}")
                ->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
    }
}