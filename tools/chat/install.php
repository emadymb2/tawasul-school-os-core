<?php
/**
 * Install the chat half of TawasulMessenger the way modules/TawasulSystemAdmin does, but from the
 * command line so the manifest can be proved installable without a browser.
 *
 * This deliberately mirrors module_manage_installProcess.php step for step:
 * lock the module table, refuse to install twice, insert the module row, run
 * the manifest statements, then the settings, then the actions and the role
 * grants. If it can install this module it can install any other, and if it
 * cannot the real installer would have failed the same way.
 *
 *   php tools/chat/install.php [--db=tos1] [--uninstall]
 */

require_once __DIR__.'/../../tawasul.php';

$options = getopt('', ['db::', 'uninstall', 'quiet']);
$dbName = $options['db'] ?? 'tos1';
$quiet = isset($options['quiet']);

if ($dbName !== ($session->get('databaseName') ?? $dbName) && !in_array('--allow-other-db', $_SERVER['argv'], true)) {
    // A scratch database still needs the platform's tawasul* tables to exist for
    // the module row and actions to be written, so install into a copy.
    fwrite(STDERR, "  refusing to install into '$dbName': it is not the configured database.\n");
    fwrite(STDERR, "  copy a schema-only copy of the core tables first, then pass --allow-other-db.\n");
    exit(1);
}

$moduleDir = __DIR__.'/../../modules/TawasulMessenger';
$manifest = $moduleDir.'/manifest.php';

include $manifest;

// The installer reads these from the including scope, exactly as above.
if (empty($name) || empty($description) || empty($type) || $type !== 'Core' || empty($version)) {
    fwrite(STDERR, "  manifest failed validation.\n");
    exit(1);
}
if ($name !== 'TawasulMessenger') {
    fwrite(STDERR, "  manifest \$name ('$name') does not match the folder.\n");
    exit(1);
}

function out(string $message): void
{
    global $quiet;
    if (!$quiet) {
        echo $message."\n";
    }
}

$moduleGateway = $container->get(\TawasulOS\Domain\System\ModuleGateway::class);
$actionGateway = $container->get(\TawasulOS\Domain\System\ActionGateway::class);

if (isset($options['uninstall'])) {
    $module = $moduleGateway->selectBy(['name' => $name])->fetch();
    if (empty($module)) {
        out('  not installed.');
        exit(0);
    }

    foreach ($actionGateway->selectBy(['tawasulModuleID' => $module['tawasulModuleID']]) as $action) {
        $actionGateway->deletePermissionByAction($action['tawasulActionID']);
    }
    $actionGateway->deleteWhere(['tawasulModuleID' => $module['tawasulModuleID']]);
    $moduleGateway->delete($module['tawasulModuleID']);
    $connection2->exec("DELETE FROM `tawasulSetting` WHERE scope = 'TawasulMessenger'");

    // Derived from the same DDL the installer used, so the uninstall cannot
    // drop a different set of tables than the install created.
    foreach (require $moduleDir.'/schema.php' as $statement) {
        if (preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/', $statement, $m)) {
            $connection2->exec("DROP TABLE IF EXISTS `{$m[1]}`");
        }
    }

    out("  uninstalled $name, dropping its tables.");
    exit(0);
}

$existing = $moduleGateway->selectBy(['name' => $name])->fetch();
if (!empty($existing)) {
    out("  $name is already installed as module {$existing['tawasulModuleID']}.");
    out('  nothing to do. Use --uninstall first, or run the CHANGEDB migration via System Admin.');
    exit(0);
}

$connection2->exec('LOCK TABLES tawasulModule WRITE');
$dataModule = [
    'name' => $name, 'description' => $description, 'entryURL' => $entryURL,
    'type' => $type, 'category' => $category, 'version' => $version,
    'author' => $author, 'url' => $url,
];
$moduleID = $moduleGateway->insertAndUpdate($dataModule, $dataModule);
$connection2->exec('UNLOCK TABLES');
out("  module row {$moduleID} ($name)");

$partialFail = false;
if (isset($moduleTables)) {
    foreach ($moduleTables as $statement) {
        try {
            $connection2->query($statement);
        } catch (PDOException $e) {
            fwrite(STDERR, '  TABLE ERROR: '.substr($e->getMessage(), 0, 200)."\n");
            $partialFail = true;
        }
    }
    out('  tables: '.count($moduleTables).' statements');
}

if (isset($tawasulSetting)) {
    foreach ($tawasulSetting as $statement) {
        try {
            $connection2->query($statement);
        } catch (PDOException $e) {
            fwrite(STDERR, '  SETTING ERROR: '.substr($e->getMessage(), 0, 200)."\n");
            $partialFail = true;
        }
    }
    out('  settings: '.count($tawasulSetting).' rows');
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
    foreach ($hooks as $statement) {
        try {
            $connection2->query($statement);
        } catch (PDOException $e) {
            fwrite(STDERR, '  HOOK ERROR: '.substr($e->getMessage(), 0, 200)."\n");
            $partialFail = true;
        }
    }
}

if ($partialFail) {
    out('  installed with errors: the module was added but is NOT active. Fix and re-run.');
    exit(1);
}

$moduleGateway->update($moduleID, ['active' => 'Y']);
out("  $name installed and active at version $version.");
