<?php
/*
Tawasul OS Branches — shared helpers.

These functions are the seam between this module and the rest of the platform.
Other modules should not query tawasulBranch directly; they call
tosBranchContext() once and then hand the result to tosBranchScopeSQL() so
that filtering is switched off consistently by a single setting.

    $branch = tosBranchContext($session, $container, $pdo);
    if ($branch['active']) {
        $where .= tosBranchScopeSQL($branch, 's', $params);
    }
*/

use TawasulOS\Domain\System\SettingGateway;

const TOS_BRANCH_SCOPE = 'Tawasul OS Branches';

/**
 * Per-request memo of the active branch count, so a page that builds several
 * branch-aware queries only counts the register once.
 */
function tosBranchActiveCount($pdo)
{
    global $tosBranchActiveCountCache;

    if (!isset($tosBranchActiveCountCache)) {
        $tosBranchActiveCountCache = (int) $pdo->select('SELECT COUNT(*) FROM tawasulBranch WHERE active=\'Y\'')->fetchColumn();
    }

    return $tosBranchActiveCountCache;
}

/**
 * Drops the memo above. Needed by long-running CLI jobs that create a branch
 * mid-run, where the cached count would otherwise go stale.
 */
function tosBranchResetCache()
{
    global $tosBranchActiveCountCache;

    unset($tosBranchActiveCountCache);
}

/**
 * Normalises a role id for comparison.
 *
 * tawasulRoleID is declared ZEROFILL, so the same role can arrive as the
 * integer 1, the string "1" or the string "001" depending on whether it came
 * from a form post, the session or a SELECT. Comparing those directly would
 * silently deny access, so everything is padded to three digits first.
 */
function tosBranchNormaliseRoleID($roleID)
{
    $roleID = trim((string) $roleID);

    if ($roleID === '') {
        return '';
    }

    return str_pad((string) (int) $roleID, 3, '0', STR_PAD_LEFT);
}

/**
 * Whether this user may create, edit or deactivate branches.
 *
 * Administrators always may. Everyone else needs their role listed in the
 * branchManagerRoles setting, which is what makes the module delegable to a
 * branch manager who is not a system administrator.
 */
function tosBranchCanManage($session, $container)
{
    $roleID = tosBranchNormaliseRoleID($session->get('tawasulRoleIDCurrent'));
    $primaryRoleID = tosBranchNormaliseRoleID($session->get('tawasulRoleIDPrimary'));

    // 001 is the built-in Administrator role.
    if ($roleID === '001' || $primaryRoleID === '001') {
        return true;
    }

    if ($roleID === '') {
        return false;
    }

    $allowed = (string) $container->get(SettingGateway::class)->getSettingByScope(TOS_BRANCH_SCOPE, 'branchManagerRoles');

    if (trim($allowed) === '') {
        return false;
    }

    $ids = array_filter(array_map(function ($v) { return tosBranchNormaliseRoleID($v); }, explode(',', $allowed)), function ($v) { return $v !== ''; });

    return in_array($roleID, $ids, true);
}

/**
 * Resolves the branch the current user is working in.
 *
 * Filtering is deliberately inert until the school actually has more than one
 * active branch. With a single branch the platform behaves exactly as it did
 * before this module was installed, which keeps existing installs — and any
 * records still carrying a NULL branch — visible rather than silently hidden.
 *
 * @return array{enabled:bool, single:bool, activeID:int, active:bool}
 */
function tosBranchContext($session, $container, $pdo)
{
    $enabled = ((string) $container->get(SettingGateway::class)->getSettingByScope(TOS_BRANCH_SCOPE, 'branchFilterEnabled')) === 'Y';
    $count = tosBranchActiveCount($pdo);
    $single = ($count <= 1);

    if (!$enabled || $single) {
        return ['enabled' => false, 'single' => $single, 'activeID' => 0, 'active' => false];
    }

    $activeID = (int) $session->get('tawasulBranchActive');

    if ($activeID <= 0) {
        $defaultID = (int) $container->get(SettingGateway::class)->getSettingByScope(TOS_BRANCH_SCOPE, 'branchDefaultID');
        $activeID = $defaultID > 0 ? $defaultID : (int) $pdo->select('SELECT MIN(tawasulBranchID) FROM tawasulBranch WHERE active=\'Y\'')->fetchColumn();
    }

    // Guard against a stale session pointing at a branch that has since been
    // deleted or deactivated.
    if ($activeID > 0 && !$pdo->select('SELECT tawasulBranchID FROM tawasulBranch WHERE tawasulBranchID=:b AND active=\'Y\'', ['b' => $activeID])->fetchColumn()) {
        $activeID = (int) $pdo->select('SELECT MIN(tawasulBranchID) FROM tawasulBranch WHERE active=\'Y\'')->fetchColumn();
        $session->set('tawasulBranchActive', $activeID);
    }

    return ['enabled' => true, 'single' => false, 'activeID' => $activeID, 'active' => true];
}

/**
 * Builds the SQL fragment that restricts a query to the active branch.
 *
 * Records with no branch assigned are included alongside the active branch, so
 * that turning multi-branch on does not hide anything that has not been
 * triaged yet. Pass $includeUnassigned = false once a school has tidied up its
 * data and wants strict isolation.
 *
 * @param string $alias Table alias that owns the tawasulBranchID column.
 * @param array  $params PDO bind parameters, appended to by reference.
 * @param string $suffix Unique suffix for the generated bind name.
 * @return string SQL fragment beginning with " AND ".
 */
function tosBranchScopeSQL($context, $alias, &$params, $suffix = 'Branch', $includeUnassigned = true)
{
    if (empty($context['active']) || $context['activeID'] <= 0) {
        return '';
    }

    $name = 'branch'.preg_replace('/[^A-Za-z0-9]/', '', (string) $suffix);

    if ($includeUnassigned) {
        $params[$name] = $context['activeID'];
        $sql = " AND ({$alias}.`tawasulBranchID` = :{$name} OR {$alias}.`tawasulBranchID` IS NULL)";
    } else {
        $params[$name] = $context['activeID'];
        $sql = " AND {$alias}.`tawasulBranchID` = :{$name}";
    }

    return $sql;
}

/**
 * Convenience wrapper for callers outside this module.
 *
 * Returns null when no filtering should happen — the module is off, or the
 * school has a single branch — and a descriptor otherwise:
 *
 *   cond    the bare predicate, for the fluent Aura builder's where()
 *   sql     the same predicate with a leading " AND ", for concatenating onto
 *           hand-written SQL
 *   params  bind parameters for either form
 *
 * Two forms because the two call styles differ: Aura.SqlQuery joins its own
 * where() clauses with AND and rejects a leading " AND ", whereas raw SQL
 * concatenation needs one.
 *
 * The real implementation; core functions.php declares a tosBranchScope()
 * dispatcher that forwards here and returns null when this module is absent.
 *
 * @param string $alias Table (or table alias) owning tawasulBranchID.
 * @return array{cond:string, sql:string, params:array}|null
 */
function tosBranchScopeImpl($session, $container, $pdo, $alias, $suffix = 'Branch', $includeUnassigned = true)
{
    $context = tosBranchContext($session, $container, $pdo);

    if (empty($context['active'])) {
        return null;
    }

    $params = [];
    $sql = tosBranchScopeSQL($context, $alias, $params, $suffix, $includeUnassigned);

    if ($sql === '') {
        return null;
    }

    return [
        'cond' => preg_replace('/^\s*AND\s+/i', '', $sql),
        'sql' => $sql,
        'params' => $params,
    ];
}

/**
 * Validates and normalises a branch code: upper case, letters, digits and
 * dashes only. Returns null when the input is unusable, so callers can turn
 * that into a field-level validation message.
 */
function tosBranchNormaliseCode($code)
{
    $code = strtoupper(trim((string) $code));
    $code = preg_replace('/[^A-Z0-9\-]/', '', $code);

    if ($code === '' || strlen($code) > 20) {
        return null;
    }

    return $code;
}

/**
 * Branches visible in the switcher. Inactive branches are only included when
 * explicitly asked for, so a deactivated campus stops appearing in the picker
 * but is still editable from the register.
 */
function tosBranchSelectAll($pdo, $includeInactive = true)
{
    $sql = 'SELECT tawasulBranchID, code, nameAr, nameEn, active
            FROM tawasulBranch';

    if (!$includeInactive) {
        $sql .= " WHERE active='Y'";
    }

    $sql .= ' ORDER BY active DESC, nameEn, nameAr';

    return $pdo->select($sql)->fetchAll();
}

/**
 * Head-count per branch, used by the register screen. Counts only rows that
 * carry the branch id, so records still on NULL are reported separately
 * rather than inflating the total.
 */
function tosBranchCounts($pdo)
{
    $sql = 'SELECT b.tawasulBranchID,
                   (SELECT COUNT(*) FROM tawasulStaff s WHERE s.tawasulBranchID = b.tawasulBranchID) AS staffCount,
                   (SELECT COUNT(*) FROM tawasulStudentEnrolment e WHERE e.tawasulBranchID = b.tawasulBranchID) AS studentCount,
                   (SELECT COUNT(*) FROM tawasulCourseClass c WHERE c.tawasulBranchID = b.tawasulBranchID) AS classCount
            FROM tawasulBranch b';

    $out = [];
    foreach ($pdo->select($sql)->fetchAll() as $row) {
        $out[(int) $row['tawasulBranchID']] = $row;
    }

    return $out;
}

/**
 * Total records still carrying no branch. Shown as a warning on the register
 * so an administrator can see what strict isolation would still exclude.
 */
function tosBranchUnassignedCount($pdo)
{
    $total = 0;
    foreach (['tawasulStaff', 'tawasulStudentEnrolment', 'tawasulCourseClass', 'tawasulDepartment', 'tawasulUnit'] as $table) {
        $total += (int) $pdo->select("SELECT COUNT(*) FROM `{$table}` WHERE `tawasulBranchID` IS NULL")->fetchColumn();
    }

    return $total;
}

/**
 * Maps an entity type to the table it lives in and the columns needed to
 * label a row. Kept in one place so the assignment UI and the write path
 * cannot drift apart, and so nothing here interpolates a raw table name that
 * came from user input — tosBranchValidEntity() gates every use.
 *
 * Person names are deliberately *not* assembled in SQL: the platform orders and
 * abbreviates them through Format::name(), which honours the school's own
 * nameFormat settings and Arabic name order.
 */
function tosBranchEntities()
{
    return [
        'staff' => [
            'table' => 'tawasulStaff',
            'id' => 'tawasulStaffID',
            'title' => 'Staff',
            'from' => 'tawasulStaff t JOIN tawasulPerson p ON (p.tawasulPersonID = t.tawasulPersonID)',
            'cols' => 't.tawasulStaffID AS id, t.tawasulBranchID, t.tawasulPersonID, p.title, p.preferredName, p.surname, p.jobTitle, p.status',
            'roleCategory' => 'Staff',
        ],
        'students' => [
            'table' => 'tawasulStudentEnrolment',
            'id' => 'tawasulStudentEnrolmentID',
            'title' => 'Students',
            'from' => 'tawasulStudentEnrolment t JOIN tawasulPerson p ON (p.tawasulPersonID = t.tawasulPersonID)',
            'cols' => 't.tawasulStudentEnrolmentID AS id, t.tawasulBranchID, t.tawasulPersonID, t.tawasulSchoolYearID, p.title, p.preferredName, p.surname, p.studentID',
            'roleCategory' => 'Student',
        ],
        'classes' => [
            'table' => 'tawasulCourseClass',
            'id' => 'tawasulCourseClassID',
            'title' => 'Classes',
            'from' => 'tawasulCourseClass t',
            'cols' => 't.tawasulCourseClassID AS id, t.tawasulBranchID, t.tawasulCourseID, t.name, t.nameShort',
            'roleCategory' => null,
        ],
        'departments' => [
            'table' => 'tawasulDepartment',
            'id' => 'tawasulDepartmentID',
            'title' => 'Departments',
            'from' => 'tawasulDepartment t',
            'cols' => 't.tawasulDepartmentID AS id, t.tawasulBranchID, t.name, t.nameShort, t.type',
            'roleCategory' => null,
        ],
        'units' => [
            'table' => 'tawasulUnit',
            'id' => 'tawasulUnitID',
            'title' => 'Units',
            'from' => 'tawasulUnit t',
            'cols' => 't.tawasulUnitID AS id, t.tawasulBranchID, t.name, t.active',
            'roleCategory' => null,
        ],
    ];
}

/**
 * Validates an entity key against the allow-list above. Always use this before
 * interpolating into SQL; the key is never taken raw from user input.
 */
function tosBranchValidEntity($key)
{
    $entities = tosBranchEntities();

    return isset($entities[$key]) ? $key : null;
}

/**
 * Rows of one entity type, each carrying its current branch id.
 *
 * @param int|null $branchID Restrict to a single branch; null returns all.
 * @return array Rows with an `id` and `tawasulBranchID` key, plus the label
 *               columns described in tosBranchEntities().
 */
function tosBranchEntityRows($pdo, $key, $branchID = null, $search = '')
{
    $entity = tosBranchEntities()[$key] ?? null;

    if ($entity === null) {
        return [];
    }

    $where = '';
    $params = [];

    if ($branchID !== null && $branchID > 0) {
        $where .= ' AND t.`tawasulBranchID` = :branchFilter';
        $params['branchFilter'] = (int) $branchID;
    }

    if (trim($search) !== '') {
        // Only person-backed entities have name columns to search.
        if (in_array($key, ['staff', 'students'], true)) {
            $where .= ' AND (p.surname LIKE :search OR p.preferredName LIKE :search OR p.firstName LIKE :search)';
            $params['search'] = '%'.trim($search).'%';
        } elseif ($key === 'classes' || $key === 'units') {
            $where .= ' AND t.name LIKE :search';
            $params['search'] = '%'.trim($search).'%';
        }
    }

    $sql = 'SELECT '.$entity['cols'].' FROM '.$entity['from'].' WHERE 1=1'.$where;
    $sql .= in_array($key, ['classes', 'departments', 'units'], true) ? ' ORDER BY t.name' : ' ORDER BY p.surname, p.preferredName';

    return $pdo->select($sql, $params)->fetchAll();
}