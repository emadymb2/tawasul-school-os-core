<?php
/**
 * Install Tawasul OS Branches the way modules/TawasulSystemAdmin does, but from
 * the command line, so the manifest can be proved installable without needing a
 * browser session.
 *
 * This deliberately mirrors module_manage_installProcess.php step for step:
 * lock the module table, refuse to install twice, insert the module row, run
 * the manifest statements, then the settings, then the actions and the role
 * grants, then the hooks. If it can install this module it can install any
 * other, and if it cannot the real installer would have failed the same way.
 *
 * Unlike the web installer this one reports every statement that fails and
 * exits non-zero, and it leaves the module inactive if anything went wrong.
 * The web installer resets $partialFail partway through, which can leave a
 * half-installed module marked active.
 *
 *   php tools/branches/install.php [--db=tos1] [--quiet]
 *
 * Back up the tables it ALTERs before running it:
 *   mysqldump -u USER -p DB tawasulStaff tawasulStudentEnrolment \
 *     tawasulCourseClass tawasulDepartment tawasulUnit > pre_install.sql
 */

require_once __DIR__.'/../../tawasul.php';

$options = getopt('', ['db::', 'quiet']);
$dbName = $options['db'] ?? 'tos1';
$quiet = isset($options['quiet']);

if ($dbName !== ($session->get('databaseName') ?? $dbName)) {
    fwrite(STDERR, "  refusing to install into '$dbName': it is not the configured database.\n");
    exit(1);
}

$moduleName = 'Tawasul OS Branches';
$moduleDir = __DIR__.'/../../modules/'.$moduleName;

include $moduleDir.'/manifest.php';

// The installer reads these from the including scope, exactly as the web path.
if (empty($name) || empty($description) || empty($type) || $type !== 'Additional' || empty($version)) {
    fwrite(STDERR, "  manifest failed validation.\n");
    exit(1);
}
if ($name !== $moduleName) {
    fwrite(STDERR, "  manifest \$name ('$name') does not match the folder.\n");
    exit(1);
}
if (strlen($name) > 30) {
    // tawasulModule.name is varchar(30).
    fwrite(STDERR, "  module name is longer than the 30 characters tawasulModule.name allows.\n");
    exit(1);
}

function out(string $message): void
{
    global $quiet;
    if (!$quiet) {
        echo $message."\n";
    }
}

function runAll(string $label, array $statements): bool
{
    $failed = 0;
    foreach ($statements as $statement) {
        try {
            $GLOBALS['connection2']->query($statement);
        } catch (PDOException $e) {
            fwrite(STDERR, "  {$label} ERROR: ".substr($e->getMessage(), 0, 200)."\n");
            $failed++;
        }
    }
    out("  {$label}: ".(count($statements) - $failed).'/'.count($statements).' ok');

    return $failed === 0;
}

$moduleGateway = $container->get(\TawasulOS\Domain\System\ModuleGateway::class);
$actionGateway = $container->get(\TawasulOS\Domain\System\ActionGateway::class);

$existing = $moduleGateway->selectBy(['name' => $name])->fetch();
if (!empty($existing)) {
    out("  $name is already installed as module {$existing['tawasulModuleID']}.");
    out('  nothing to do. Run the CHANGEDB migration via System Admin to upgrade it.');
    exit(0);
}

$partialFail = false;

$connection2->exec('LOCK TABLES tawasulModule WRITE');
$dataModule = [
    'name' => $name, 'description' => $description, 'entryURL' => $entryURL,
    'type' => $type, 'category' => $category, 'version' => $version,
    'author' => $author, 'url' => $url,
];
$moduleID = $moduleGateway->insertAndUpdate($dataModule, $dataModule);
$connection2->exec('UNLOCK TABLES');
out("  module row {$moduleID} ($name)");

if (isset($moduleTables)) {
    if (!runAll('tables', $moduleTables)) {
        $partialFail = true;
    }
}

if (isset($tawasulSetting)) {
    if (!runAll('settings', $tawasulSetting)) {
        $partialFail = true;
    }
}

// Actions. Every optional key is defaulted exactly as the installer does.
$roleDefaults = [
    '001' => 'defaultPermissionAdmin', '002' => 'defaultPermissionTeacher',
    '003' => 'defaultPermissionStudent', '004' => 'defaultPermissionParent',
    '006' => 'defaultPermissionSupport',
];
$categoryDefaults = [
    'categoryPermissionStaff', 'categoryPermissionStudent',
    'categoryPermissionParent', 'categoryPermissionOther',
];

foreach ($actionRows as $row) {
    $actionData = [
        'tawasulModuleID' => $moduleID,
        'name' => $row['name'],
        'precedence' => $row['precedence'],
        'category' => $row['category'],
        'description' => $row['description'],
        'URLList' => $row['URLList'],
        'entryURL' => $row['entryURL'],
        'entrySidebar' => $row['entrySidebar'] ?? 'Y',
        'menuShow' => $row['menuShow'] ?? 'Y',
    ];
    foreach ($roleDefaults as $key) {
        $actionData[$key] = $row[$key];
    }
    foreach ($categoryDefaults as $key) {
        $actionData[$key] = $row[$key] ?? 'Y';
    }

    // insert() returns the new action's primary key. It has to be captured
    // before the grants are written: insertPermissionByAction performs its own
    // INSERT, so reading lastInsertId afterwards would hand back the permission
    // row's id rather than the action's.
    $actionID = $actionGateway->insert($actionData);
    $actionID = (int) (is_object($actionID) ? (method_exists($actionID, 'getPrimaryKey') ? $actionID->getPrimaryKey() : (string) $actionID) : $actionID);

    foreach ($roleDefaults as $roleID => $key) {
        if ($row[$key] === 'Y') {
            $actionGateway->insertPermissionByAction($actionID, $roleID);
        }
    }
}
out('  actions: '.count($actionRows).' rows');

if (isset($hooks)) {
    if (!runAll('hooks', $hooks)) {
        $partialFail = true;
    }
}

if ($partialFail) {
    out('  installed with errors: the module was added but is NOT active. Fix and re-run.');
    exit(1);
}

$moduleGateway->update($moduleID, ['active' => 'Y']);
out("  $name installed and active at version $version.");