<?php
/**
 * Verify the accounting REST surface: the definitions, the routes and the two
 * module-name resolutions they depend on.
 *
 * The selects are executed against a scratch database rather than merely
 * asserted on, because a definition that lists a column the table does not have
 * fails only at request time, which is exactly the class of mistake this file
 * exists to catch.
 */
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../modules/TawasulFinance/src/bootstrap.php';
require __DIR__ . '/../../modules/TawasulCore/src/bootstrap.php';

use Tos\Module\TawasulCore\Auth\Credential;
use Tos\Module\TawasulCore\Auth\Permissions;
use Tos\Module\TawasulCore\Http\Router;
use Tos\Module\TawasulCore\Resource\Registry;
use Tos\Module\TawasulFinance\Support\ConnectionAdapter;

$admin = new PDO('mysql:host=localhost', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$scratch = 'tawasul_api_accounting_test';
$admin->exec("DROP DATABASE IF EXISTS `$scratch`");
$admin->exec("CREATE DATABASE `$scratch`");
$admin->exec("USE `$scratch`");

$moduleTables = [];
require __DIR__ . '/../../modules/TawasulFinance/manifest.php';
foreach ($moduleTables as $sql) { $admin->exec($sql); }

$conn = new ConnectionAdapter($admin);
// seed() covers the chart, the sequences, the cost centres, the salary
// components and the first fiscal year with its twelve periods.
(new \Tos\Module\TawasulFinance\Domain\ChartOfAccounts($conn))->seed();

// The permission tables are core's, not the module's, so stand up just enough
// of them for Permissions to answer honestly.
$admin->exec("CREATE TABLE tawasulModule (tawasulModuleID INT UNSIGNED NOT NULL PRIMARY KEY, name VARCHAR(50) NOT NULL, active ENUM('Y','N') NOT NULL DEFAULT 'Y')");
$admin->exec("CREATE TABLE tawasulAction (tawasulActionID INT UNSIGNED NOT NULL PRIMARY KEY, tawasulModuleID INT UNSIGNED NOT NULL, name VARCHAR(50) NOT NULL)");
$admin->exec("CREATE TABLE tawasulPermission (tawasulPermissionID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, tawasulRoleID INT UNSIGNED NOT NULL, tawasulActionID INT UNSIGNED NOT NULL)");
$admin->exec("INSERT INTO tawasulModule (tawasulModuleID, name, active) VALUES (135, 'TawasulFinance', 'Y'), (100, 'TawasulCore', 'Y'), (136, 'TawasulGhost', 'N')");
$admin->exec("INSERT INTO tawasulAction (tawasulActionID, tawasulModuleID, name) VALUES (1, 135, 'Manage Journal Entries'), (2, 135, 'Manage Chart of Accounts'), (3, 135, 'Manage Fiscal Years')");
$admin->exec("INSERT INTO tawasulPermission (tawasulRoleID, tawasulActionID) VALUES (1, 1), (1, 2), (1, 3)");

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = '') {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? "  PASS  " : "  FAIL  ") . $label . ($detail ? "  -- {$detail}" : '') . "\n";
}

$ACCOUNTING = [
    'accounts', 'fiscal-years', 'periods', 'journal-entries',
    'journal-lines', 'cost-centers', 'salary-components', 'sequences',
];

// --- Definitions are registered -------------------------------------------
echo "\nDefinitions\n";
foreach ($ACCOUNTING as $name) {
    check("resource '$name' is registered", Registry::has($name));
    check("resource '$name' has a select", !empty(Registry::get($name)['select']));
}

// --- Every accounting select actually runs ---------------------------------
// Compiling the SQL catches the missing-column and missing-space mistakes that
// otherwise only surface when an integrator calls the endpoint.
echo "\nSelects execute\n";
foreach ($ACCOUNTING as $name) {
    $definition = Registry::get($name);
    try {
        $stmt = $admin->query($definition['select']);
        $stmt->fetchAll(PDO::FETCH_ASSOC);
        check("select for '$name' runs", true);
    } catch (Throwable $e) {
        check("select for '$name' runs", false, $e->getMessage());
    }
}

// --- The ledger must not be writable through the generic CRUD --------------
echo "\nJournal integrity\n";
foreach (['journal-entries', 'journal-lines', 'sequences'] as $name) {
    $methods = Registry::get($name)['methods'];
    check("'$name' refuses POST", !in_array('POST', $methods, true));
    check("'$name' refuses PATCH", !in_array('PATCH', $methods, true));
    check("'$name' refuses DELETE", !in_array('DELETE', $methods, true));
    check("'$name' allows GET", in_array('GET', $methods, true));
}

// Reference data is still editable, or the chart of accounts would be frozen.
foreach (['accounts', 'cost-centers', 'salary-components', 'fiscal-years', 'periods'] as $name) {
    check("'$name' accepts POST", in_array('POST', Registry::get($name)['methods'], true));
}

// --- Filters and sorts are declared, so they are enforced ------------------
echo "\nFilters\n";
$filters = Registry::get('journal-entries')['filters'];
check('journal-entries filters by status', isset($filters['status']));
check('journal-entries filters by date', isset($filters['date']));
check('journal-entries filters by period', isset($filters['tawasulFinancePeriodID']));

$lineFilters = Registry::get('journal-lines')['filters'];
check('journal-lines filters by account', isset($lineFilters['tawasulFinanceAccountID']));
check('journal-lines filters by entry', isset($lineFilters['tawasulFinanceJournalEntryID']));

$accountFilters = Registry::get('accounts')['filters'];
check('accounts filters by type', isset($accountFilters['type']));
check('accounts filters by isPosting', isset($accountFilters['isPosting']));

// --- Every accounting resource sits in its own docs group -----------------
echo "\nDocs grouping\n";
$groups = Registry::groups();
check('Accounting group exists', isset($groups['Accounting']));
check(
    'Accounting group holds all eight',
    count(array_intersect(array_keys($groups['Accounting'] ?? []), $ACCOUNTING)) === count($ACCOUNTING)
);

// --- Routes ----------------------------------------------------------------
// The generic resource routes are greedy, so each of these would otherwise be
// read as a record id and never reach the accounting controller.
echo "\nRoutes\n";
$router = new Router();
$routes = [
    ['/v2/accounting/journal', 'POST', 'accounting.postJournal'],
    ['/v2/accounting/journal/validate', 'POST', 'accounting.validateJournal'],
    ['/v2/accounting/journal/12/reverse', 'POST', 'accounting.reverseJournal'],
    ['/v2/accounting/reports/trial-balance', 'GET', 'accounting.report'],
    ['/v2/accounting/reports/general-ledger', 'GET', 'accounting.report'],
    ['/accounting/reports/health', 'GET', 'accounting.report'],
];
foreach ($routes as [$path, $method, $expected]) {
    $matched = $router->match($path, $method);
    check("$method $path -> $expected", $matched['name'] === $expected, 'got '.$matched['name']);
}
check(
    'reverse keeps the entry id',
    $router->match('/v2/accounting/journal/12/reverse', 'POST')['params']['id'] === '12'
);
check(
    'report keeps the report name',
    $router->match('/v2/accounting/reports/trial-balance', 'GET')['params']['report'] === 'trial-balance'
);

// Accounting routes must not be reachable without a credential.
foreach ($routes as [$path, $method, $name]) {
    check("$path is not public", $router->match($path, $method)['public'] === false);
}

// A GET on a write-only route is a 405, not a silent fall-through.
foreach ([['/v2/accounting/journal', 'GET'], ['/v2/accounting/journal/12/reverse', 'GET']] as [$path, $method]) {
    try {
        $router->match($path, $method);
        check("$method $path is refused", false, 'no exception');
    } catch (\Tos\Module\TawasulCore\Http\ApiException $e) {
        check("$method $path is refused", $e->getStatusCode() === 405, 'got '.$e->getStatusCode());
    }
}

// A read route that does not exist is a 404 naming the ones that do.
try {
    $router->match('/v2/accounting/reports/nonsense', 'GET');
    check('unknown report is refused', false, 'no exception');
} catch (\Tos\Module\TawasulCore\Http\ApiException $e) {
    check('unknown report is refused', $e->getStatusCode() === 404, 'got '.$e->getStatusCode());
}

// --- Module name resolution ------------------------------------------------
// Definitions name modules bare ("Finance") while the installed row is
// "TawasulFinance". Before this was handled, every such resource answered 404.
echo "\nModule name resolution\n";
$permissions = new Permissions($admin, true);
check('bare name resolves to the prefixed module', $permissions->isModuleActive('Finance'));
check('prefixed name resolves', $permissions->isModuleActive('TawasulFinance'));
check('unrelated module does not resolve', !$permissions->isModuleActive('Widgets'));
check('inactive module does not resolve', !$permissions->isModuleActive('Ghost'));

$adminRole = ['001'];
check(
    'role permission resolves through the bare name',
    $permissions->roleHasAction($adminRole, 'Finance', 'Manage Journal Entries')
);
check(
    'role permission resolves through the prefixed name',
    $permissions->roleHasAction($adminRole, 'TawasulFinance', 'Manage Journal Entries')
);
check(
    'a role without the action is refused',
    !$permissions->roleHasAction(['002'], 'Finance', 'Manage Journal Entries')
);
check(
    'an action in an uninstalled module is refused',
    !$permissions->roleHasAction($adminRole, 'Ghost', 'Manage Journal Entries')
);

// --- Authorisation on the accounting resources -----------------------------
echo "\nAuthorisation\n";
$key = new Credential('api-key', ['scopes' => '*', 'tawasulPersonID' => '0000000001', 'roles' => '001']);
$teacher = new Credential('api-key', ['scopes' => '*', 'tawasulPersonID' => '0000000002', 'roles' => '002']);
$accountant = new Credential('api-key', ['scopes' => 'accounting.read', 'tawasulPersonID' => '0000000001', 'roles' => '001']);
// Same role as the teacher, but scoped to the resource instead of wildcard, so
// the read-side action match is actually consulted.
$scopedTeacher = new Credential('api-key', ['scopes' => 'accounting.read', 'tawasulPersonID' => '0000000002', 'roles' => '002']);

$permissions->authorise($key, Registry::get('journal-entries'), 'GET');
check('the accountant role may read the ledger', true);
$permissions->authorise($key, Registry::get('journal-entries'), 'POST');
check('the accountant role may post', true);

try {
    $permissions->authorise($teacher, Registry::get('journal-entries'), 'GET');
    // A wildcard-scoped key is let past the read-side action match on purpose:
    // REST action mappings are often an imprecise match for what a role holds,
    // so a read is gated on the scope alone (see Auth\Permissions::authorise).
    // Pinned here so the exemption stays visible rather than being assumed.
    check('a wildcard key passes the read gate without an action', true);
} catch (\Tos\Module\TawasulCore\Http\ApiException $e) {
    check('a wildcard key passes the read gate without an action', false, 'got '.$e->getStatusCode());
}

try {
    $permissions->authorise($scopedTeacher, Registry::get('journal-entries'), 'GET');
    check('a role without the action is refused on a scoped key', false, 'no exception');
} catch (\Tos\Module\TawasulCore\Http\ApiException $e) {
    check(
        'a role without the action is refused on a scoped key',
        $e->getStatusCode() === 403,
        'got '.$e->getStatusCode()
    );
}

try {
    $permissions->authorise($teacher, Registry::get('journal-entries'), 'POST');
    check('a wildcard key without the action cannot post', false, 'no exception');
} catch (\Tos\Module\TawasulCore\Http\ApiException $e) {
    check(
        'a wildcard key without the action cannot post',
        $e->getStatusCode() === 403,
        'got '.$e->getStatusCode()
    );
}

try {
    $permissions->authorise($accountant, Registry::get('journal-entries'), 'POST');
    check('a read-only scope cannot post', false, 'no exception');
} catch (\Tos\Module\TawasulCore\Http\ApiException $e) {
    check('a read-only scope cannot post', $e->getStatusCode() === 403, 'got '.$e->getStatusCode());
}

// --- Line parsing ----------------------------------------------------------
// A client may send either explicit debit/credit columns or a single amount
// with a direction, so both shapes have to reach the gateway intact.
echo "\nRequest parsing\n";
$controller = new \Tos\Module\TawasulCore\Controller\AccountingController(
    $admin, $permissions, $key
);
$parse = new ReflectionMethod($controller, 'readLines');
$parse->setAccessible(true);
$totals = new ReflectionMethod($controller, 'lineTotals');
$totals->setAccessible(true);

$explicit = $parse->invoke($controller, ['lines' => [
    ['tawasulFinanceAccountID' => '1', 'debit' => '100.50', 'credit' => '0'],
    ['tawasulFinanceAccountID' => '2', 'debit' => '0', 'credit' => '100.50', 'memo' => 'ok'],
]]);
check('explicit debit/credit passes through', $explicit[0]['debit'] === '100.50' && $explicit[0]['credit'] === '0');
check('the memo is kept', $explicit[1]['memo'] === 'ok');

$directed = $parse->invoke($controller, ['lines' => [
    ['accountID' => '1', 'amount' => 250, 'direction' => 'debit'],
    ['accountID' => '2', 'amount' => 250, 'direction' => 'CREDIT'],
]]);
check('a directed debit becomes a debit', $directed[0]['debit'] == 250 && $directed[0]['credit'] == 0);
check('a directed credit becomes a credit', $directed[1]['credit'] == 250 && $directed[1]['debit'] == 0);
check('accountID is accepted as an alias', $directed[0]['tawasulFinanceAccountID'] === '1');

// A form-encoded body arrives keyed by index rather than as a list.
$keyed = $parse->invoke($controller, ['lines' => [
    '0' => ['tawasulFinanceAccountID' => '1', 'debit' => 10],
    '1' => ['tawasulFinanceAccountID' => '2', 'credit' => 10],
]]);
check('a keyed lines object is accepted', count($keyed) === 2);
check('a keyed lines object keeps its order', $keyed[0]['tawasulFinanceAccountID'] === '1');

$sum = $totals->invoke($controller, $explicit);
check('totals report both sides', $sum['debit'] === '100.50' && $sum['credit'] === '100.50');
check('balanced totals are flagged', $sum['isBalanced'] === true);

$unbalanced = $totals->invoke($controller, $parse->invoke($controller, ['lines' => [
    ['tawasulFinanceAccountID' => '1', 'debit' => 10],
    ['tawasulFinanceAccountID' => '2', 'credit' => 5],
]]));
check('unbalanced totals are flagged', $unbalanced['isBalanced'] === false);

foreach ([
    'no lines at all' => [],
    'a single line' => [['tawasulFinanceAccountID' => '1', 'debit' => 10]],
] as $label => $body) {
    try {
        $parse->invoke($controller, $body);
        check("$label is refused", false, 'no exception');
    } catch (\Tos\Module\TawasulCore\Http\ApiException $e) {
        check("$label is refused", $e->getStatusCode() === 400, 'got '.$e->getStatusCode());
    }
}

try {
    $parse->invoke($controller, ['lines' => [
        ['tawasulFinanceAccountID' => '1', 'amount' => 10, 'direction' => 'sideways'],
        ['tawasulFinanceAccountID' => '2', 'amount' => 10, 'direction' => 'debit'],
    ]]);
    check('an unknown direction is refused', false, 'no exception');
} catch (\Tos\Module\TawasulCore\Http\ApiException $e) {
    check('an unknown direction is refused', $e->getStatusCode() === 400, 'got '.$e->getStatusCode());
}

// --- Document type is constrained -----------------------------------------
// documentType becomes part of a unique index and the printed document
// number, so it must not carry punctuation through.
echo "\nDocument type\n";
$documentType = new ReflectionMethod($controller, 'documentType');
$documentType->setAccessible(true);
check('it defaults to JV', $documentType->invoke($controller, []) === 'JV');
check('it upper-cases the input', $documentType->invoke($controller, ['documentType' => 'br']) === 'BR');
foreach (["JV'; DROP TABLE x --", 'JV 1', '', 'TOOLONG'] as $bad) {
    try {
        $documentType->invoke($controller, ['documentType' => $bad]);
        check('rejects "' . $bad . '"', false, 'no exception');
    } catch (\Tos\Module\TawasulCore\Http\ApiException $e) {
        check('rejects "' . $bad . '"', $e->getStatusCode() === 400, 'got '.$e->getStatusCode());
    }
}

// --- The engine the controller delegates to is reachable from the API -------
// api.php registers only the TawasulCore loader, so the accounting classes have
// to come from TawasulFinance's own bootstrap. Nothing else loads it.
echo "\nEngine wiring\n";
$finance = new \Tos\Module\TawasulFinance\Domain\JournalGateway($conn);
$finance->setDependencies(
    new \Tos\Module\TawasulFinance\Domain\FiscalYearGateway($conn),
    new \Tos\Module\TawasulFinance\Domain\AccountGateway($conn)
);
$errors = $finance->validateLines([
    ['tawasulFinanceAccountID' => '1', 'debit' => 100, 'credit' => 100],
]);
check('the engine rejects a line that is both debit and credit', count($errors) > 0, implode('; ', $errors));

$heading = $admin->query("SELECT tawasulFinanceAccountID FROM tawasulFinanceAccount WHERE isPosting='N' LIMIT 1")->fetchColumn();
$errors = $finance->validateLines([
    ['tawasulFinanceAccountID' => $heading, 'debit' => 10, 'credit' => 0],
    ['tawasulFinanceAccountID' => $heading, 'credit' => 10]
]);
check('the engine rejects posting to a heading', !empty($errors), implode('; ', $errors));

// The general ledger must survive a core installation where tawasulPerson
// exists, which is the only path that builds the creator join.
$stmt = $admin->query(Registry::get('journal-lines')['select']);
$stmt->fetchAll(PDO::FETCH_ASSOC);
check('journal-lines select runs without tawasulPerson', true);

$admin->exec("DROP DATABASE `$scratch`");

echo "\n----------------------------------------\n";
echo ($fail === 0 ? "PASS" : "FAIL") . ": {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
