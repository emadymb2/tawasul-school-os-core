<?php
/**
 * Regression tests for the shared resource machinery: the endpoints every
 * resource inherits (/aggregate, /distinct, /export, discovery documents).
 *
 * Each check here corresponds to a bug that made an endpoint fail for the whole
 * API rather than for one resource, which is why they were not caught by
 * testing accounting alone.
 */
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../modules/TawasulFinance/src/bootstrap.php';
require __DIR__ . '/../../modules/TawasulCore/src/bootstrap.php';

use Tos\Module\TawasulCore\Http\Request;
use Tos\Module\TawasulCore\Resource\Registry;
use Tos\Module\TawasulCore\Support\OpenApi;
use Tos\Module\TawasulCore\Support\QueryBuilder;

$admin = new PDO('mysql:host=localhost', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$scratch = 'tawasul_api_shared_test';
$admin->exec("DROP DATABASE IF EXISTS `$scratch`");
$admin->exec("CREATE DATABASE `$scratch`");
$admin->exec("USE `$scratch`");

$moduleTables = [];
require __DIR__ . '/../../modules/TawasulFinance/manifest.php';
foreach ($moduleTables as $sql) { $admin->exec($sql); }

$conn = new \Tos\Module\TawasulFinance\Support\ConnectionAdapter($admin);
(new \Tos\Module\TawasulFinance\Domain\ChartOfAccounts($conn))->seed();

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = '') {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? "  PASS  " : "  FAIL  ") . $label . ($detail ? "  -- {$detail}" : '') . "\n";
}

/**
 * Builds a Request with a query string, so the real QueryBuilder paths run
 * rather than a stand-in.
 */
function withQuery(array $query): Request
{
    $reflection = new ReflectionClass(Request::class);

    $request = $reflection->newInstanceWithoutConstructor();
    foreach (['method' => 'GET', 'path' => '/v2/test', 'query' => $query] as $property => $value) {
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($request, $value);
    }

    return $request;
}

// --- /distinct used to be unusable on every resource -----------------------
// It read ?field= itself and then handed the whole request to QueryBuilder,
// which rejected "field" as an unknown filter. So the endpoint answered 400
// "Unknown filter \"field\"" no matter which resource or field was asked for.
echo "\nControl parameters\n";

check('"field" is reserved, not treated as a filter', in_array('field', QueryBuilder::RESERVED, true));
check('"group_by" is reserved', in_array('group_by', QueryBuilder::RESERVED, true));
check('"metrics" is reserved', in_array('metrics', QueryBuilder::RESERVED, true));

foreach (['page', 'pageSize', 'sort', 'search', 'fields', 'format'] as $param) {
    check("\"{$param}\" is reserved", in_array($param, QueryBuilder::RESERVED, true));
}

$builder = new QueryBuilder(Registry::get('accounts'));
try {
    $builder->applyRequest(withQuery(['field' => 'type']), 50, 500, null);
    check('a ?field= request builds a WHERE clause', true);
} catch (Throwable $e) {
    check('a ?field= request builds a WHERE clause', false, $e->getMessage());
}

// A genuine typo must still be refused, or reserving "field" would have
// silently opened an unfiltered query.
$builder = new QueryBuilder(Registry::get('accounts'));
try {
    $builder->applyRequest(withQuery(['bogus' => '1']), 50, 500, null);
    check('an unknown filter is still refused', false, 'no exception');
} catch (\Tos\Module\TawasulCore\Http\ApiException $e) {
    check('an unknown filter is still refused', $e->getStatusCode() === 400, 'got '.$e->getStatusCode());
}

// A real filter must still apply alongside the reserved control parameter.
$builder = new QueryBuilder(Registry::get('accounts'));
try {
    $builder->applyRequest(withQuery(['field' => 'type', 'active' => 'Y']), 50, 500, null);
    check('a real filter still applies with ?field=', $builder->getWhereSQL() !== '');
} catch (Throwable $e) {
    check('a real filter still applies with ?field=', false, $e->getMessage());
}

// --- /distinct must select the exposed column name -------------------------
// The definition's select is wrapped as a derived table, so it exposes bare
// column names only. Using the table-qualified name out here was a 500.
echo "\nDerived-table column resolution\n";

$resource = Registry::get('accounts');
$column = $resource['filters']['type'];
check('the filter is qualified', $column === 'tawasulFinanceAccount.type', $column);
$exposed = substr(strrchr('.'.$column, '.'), 1);
check('the derived table exposes it bare', $exposed === 'type', $exposed);

// Reproduce the endpoint's own SQL rather than trusting the string surgery.
// The chart grew when the SchoolAccounting chart was merged in, so the
// expected collection size is read rather than written down.
$collectionSize = (int) $admin->query('SELECT COUNT(*) FROM tawasulFinanceAccount')->fetchColumn();
$builder = new QueryBuilder($resource);
$sql = 'SELECT DISTINCT '.$exposed.' AS value FROM ('.$resource['select'].$builder->getWhereSQL().') AS data ORDER BY '.$exposed;
try {
    $stmt = $admin->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    check('the distinct query runs', true);

    $values = array_column($rows, 'value');
    sort($values);
    check('it returns one row per distinct value', count($values) === count(array_unique($values)), implode(',', $values));
    check('it is smaller than the collection', count($values) < $collectionSize, count($values).' of '.$collectionSize);

    $expected = $admin->query('SELECT DISTINCT type FROM tawasulFinanceAccount')->fetchAll(PDO::FETCH_COLUMN);
    sort($expected);
    check('it matches a direct DISTINCT', $values === $expected, implode(',', $expected));
} catch (Throwable $e) {
    check('the distinct query runs', false, $e->getMessage());
}

// --- /aggregate used to 500 on every resource -----------------------------
// AggregateController calls QueryBuilder::getWhereSQL(), which was protected,
// so the call raised a fatal Error that surfaced as "server_error".
echo "\nAggregate access\n";

$reflection = new ReflectionClass(QueryBuilder::class);
check('getWhereSQL() is public', $reflection->getMethod('getWhereSQL')->isPublic());
check('getBindings() is public', $reflection->getMethod('getBindings')->isPublic());
check('getPage() is public', $reflection->getMethod('getPage')->isPublic());
check('getPageSize() is public', $reflection->getMethod('getPageSize')->isPublic());

// The count sub-query the aggregate endpoint builds first.
$builder = new QueryBuilder($resource);
$countSQL = 'SELECT COUNT(*) FROM ('.$resource['select'].$builder->getWhereSQL().') AS total';
try {
    $count = (int) $admin->query($countSQL)->fetchColumn();
    check('the aggregate count query runs', $count === $collectionSize, 'got '.$count.', expected '.$collectionSize);
} catch (Throwable $e) {
    check('the aggregate count query runs', false, $e->getMessage());
}

// The grouped form, which is what a dashboard actually asks for.
$groupSQL = 'SELECT type AS type, COUNT(*) AS count FROM ('
    .$resource['select'].") AS data GROUP BY type ORDER BY type";
try {
    $rows = $admin->query($groupSQL)->fetchAll(PDO::FETCH_ASSOC);
    check('the grouped aggregate query runs', count($rows) === 5, count($rows).' groups');
} catch (Throwable $e) {
    check('the grouped aggregate query runs', false, $e->getMessage());
}

// --- Discovery documents must name the scopes that are enforced ------------
// authorise() checks "<definition scope>.read", but routeTable() derived the
// scope from the resource name. Every resource that shares a base scope
// therefore advertised a scope that would be refused.
echo "\nScope reporting\n";

$table = [];
foreach (OpenApi::routeTable() as $group => $rows) {
    foreach ($rows as $row) { $table[$row['resource']] = $row; }
}

$mismatched = [];
foreach ($table as $name => $row) {
    $expected = Registry::get($name)['scope'];
    if ($row['scopeRead'] !== $expected.'.read' || $row['scopeWrite'] !== $expected.'.write') {
        $mismatched[] = $name;
    }
}
check('every resource reports its real scope', empty($mismatched), implode(', ', array_slice($mismatched, 0, 5)));

check('accounts reports accounting.read', $table['accounts']['scopeRead'] === 'accounting.read', $table['accounts']['scopeRead']);
check('journal-entries reports accounting.read', $table['journal-entries']['scopeRead'] === 'accounting.read');
check('fees reports finance.read', $table['fees']['scopeRead'] === 'finance.read', $table['fees']['scopeRead']);

// A caller must be able to see that a shared scope covers more than one thing.
check(
    'accounts lists the resources sharing its scope',
    in_array('journal-entries', $table['accounts']['scopeSharedWith'] ?? [], true),
    implode(',', $table['accounts']['scopeSharedWith'] ?? [])
);
check('a resource does not list itself', !in_array('accounts', $table['accounts']['scopeSharedWith'] ?? [], true));

// The scope list must describe the whole scope, not one arbitrary member.
$scopes = Registry::scopes();
check('accounting.read exists', isset($scopes['accounting.read']));
check('finance.read exists', isset($scopes['finance.read']));

// Counted from the registry rather than hardcoded, so this stays true as
// accounting resources are added.
$accountingCount = 0;
foreach (Registry::all() as $definition) {
    if ($definition['scope'] === 'accounting') {
        $accountingCount++;
    }
}
check(
    sprintf('accounting.read names all %d resources', $accountingCount),
    strpos($scopes['accounting.read'], 'any of the '.$accountingCount.' accounting resources') !== false,
    $scopes['accounting.read']
);
check(
    'accounting.read does not read as a single resource',
    strpos($scopes['accounting.read'], 'Read Document Sequences') === false
);
// Most scopes cover exactly one resource, and naming it plainly reads better
// than the enumerated form. Checked against a scope that really is a singleton
// rather than a hardcoded one, so this stays true as the registry grows.
$singletons = [];
$scopeCounts = [];
foreach (Registry::all() as $name => $definition) {
    $scopeCounts[$definition['scope']][] = $name;
}
foreach ($scopeCounts as $scope => $names) {
    if (count($names) === 1) { $singletons[] = $scope; }
}
check('the registry has singleton scopes to check', !empty($singletons));

$singletonScope = $singletons[0].'.read';
check(
    'a single-resource scope keeps its simple description',
    isset($scopes[$singletonScope])
    && strpos($scopes[$singletonScope], 'any of the') === false
    && strpos($scopes[$singletonScope], 'Read ') === 0,
    $singletonScope.' => '.($scopes[$singletonScope] ?? '(missing)')
);

// And a shared scope must be enumerated, never collapsed to one member.
$shared = array_filter($scopeCounts, fn($v) => count($v) > 1);
check('the registry has shared scopes too', !empty($shared), count($shared).' shared');
// Keys are kept here: array_values() would turn the scope name into an index.
$sharedScope = array_key_first($shared).'.read';
check(
    'a shared scope enumerates its resources',
    strpos($scopes[$sharedScope] ?? '', 'any of the') !== false,
    $sharedScope.' => '.($scopes[$sharedScope] ?? '(missing)')
);

// The scopes actually enforced must exist in the documented list.
$declared = array_keys($scopes);
$missing = [];
foreach (Registry::all() as $name => $definition) {
    foreach ([$definition['scope'].'.read'] as $needed) {
        if (!in_array($needed, $declared, true)) { $missing[] = $needed; }
    }
}
check('every enforced scope is documented', empty($missing), implode(', ', array_slice(array_unique($missing), 0, 5)));

// --- Export --------------------------------------------------------------
echo "\nExport\n";
$builder = new QueryBuilder($resource);
$sql = $resource['select'].$builder->getWhereSQL();
try {
    $stmt = $admin->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    check('the export query runs', count($rows) === $collectionSize, count($rows).' of '.$collectionSize.' rows');
    check('the export carries joined labels', array_key_exists('parentCode', $rows[0]));
} catch (Throwable $e) {
    check('the export query runs', false, $e->getMessage());
}

// --- OpenAPI must describe the accounting routes --------------------------
echo "\nOpenAPI\n";
$document = OpenApi::build('https://example.test/modules/TawasulCore/api.php/v2');
check('the spec version comes from the Kernel', $document['info']['version'] === \Tos\Module\TawasulCore\Kernel::VERSION, $document['info']['version']);

foreach ([
    '/accounting/journal',
    '/accounting/journal/validate',
    '/accounting/journal/{id}/reverse',
    '/accounting/reports/trial-balance',
    '/accounting/reports/general-ledger',
    '/accounting/reports/account-types',
    '/accounting/reports/health',
] as $path) {
    check("the spec describes {$path}", isset($document['paths'][$path]));
}

check(
    'every spec report path is routable',
    (function () use ($document) {
        foreach (array_keys($document['paths']) as $path) {
            if (strpos($path, '/accounting/reports/') !== 0) {
                continue;
            }
            $name = substr($path, strlen('/accounting/reports/'));
            if (!in_array($name, \Tos\Module\TawasulCore\Controller\AccountingController::REPORTS, true)) {
                return false;
            }
        }
        return true;
    })()
);

$ids = [];
foreach ($document['paths'] as $operations) {
    foreach ($operations as $operation) {
        if (isset($operation['operationId'])) { $ids[] = $operation['operationId']; }
    }
}
check('operation IDs are unique', count($ids) === count(array_unique($ids)), count($ids).' operations');

// The registry and the spec must not drift.
$specResources = 0;
foreach ($document['paths'] as $path => $operations) {
    if (isset($operations['get'], $operations['post'])) { $specResources++; }
}
check('every registry resource appears in the spec', $specResources >= count(Registry::curated()), $specResources.' documented');

// --- Every definition must reference a table that exists -------------------
// A generated definition once named the i18n table "i18n" while using the real
// "tawasuli18n" columns, and the mismatch only surfaced as a 500 on first call.
echo "\nDefinition integrity\n";

$existing = [];
foreach ($admin->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $existing[$t] = true;
}
check('the scratch schema has tables', !empty($existing));

// The i18n override is asserted against the definition rather than the live
// database, because the scratch schema has no core tables.
$i18n = Registry::get('i18ns');
check('i18ns names the real table', $i18n['table'] === 'tawasuli18n', (string) $i18n['table']);
check('i18ns selects from the real table', strpos($i18n['select'], 'FROM tawasuli18n') !== false, $i18n['select']);
check(
    'no i18ns clause still refers to the old table',
    preg_match('/\bi18n\./', $i18n['select']) === 0 && preg_match('/\bi18n\./', json_encode($i18n['filters'])) === 0
);
check('i18ns uses its own primary key', $i18n['primaryKey'] === 'tawasuli18nID', (string) $i18n['primaryKey']);
check('i18ns is read-only', $i18n['methods'] === ['GET']);

// Every accounting definition must name a primary table that really exists.
// Joins to core tables (tawasulPerson and friends) are not checked here,
// because this scratch schema is the module's own and has no core tables: the
// point of the check is to catch a definition pointing at the wrong table, as
// the generated i18ns entry did by naming "i18n" instead of "tawasuli18n".
$wrongPrimary = [];
foreach (Registry::curated() as $name => $resource) {
    if ($name === 'i18ns' || strpos($name, 'finance') === false) {
        continue;
    }
    if (empty($resource['table'])) {
        continue;
    }
    if (!isset($existing[$resource['table']])) {
        $wrongPrimary[$name] = $resource['table'];
    }
}
check('every accounting definition names a real table', empty($wrongPrimary), json_encode($wrongPrimary));

// And the primary key it declares must be a column of that table.
$wrongKey = [];
foreach (Registry::curated() as $name => $resource) {
    if ($name === 'i18ns' || strpos($name, 'finance') === false || empty($resource['table'])) {
        continue;
    }
    if (!isset($existing[$resource['table']])) {
        continue;
    }
    $columns = array_flip(array_column(
        $admin->query('SHOW COLUMNS FROM `'.$resource['table'].'`')->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    ));
    if (!isset($columns[$resource['primaryKey']])) {
        $wrongKey[$name] = $resource['primaryKey'];
    }
}
check('every accounting definition names a real primary key', empty($wrongKey), json_encode($wrongKey));

// The ledger link must never be caller-writable: it names the journal entry the
// posting engine produced, so a body that sets it is refused outright.
$ledgerLinked = [
    'purchase-bills' => 'tawasulFinanceJournalEntryID',
    'payment-vouchers' => 'tawasulFinanceJournalEntryID',
    'receipts' => 'tawasulFinanceJournalEntryID',
    'transfers' => 'tawasulFinanceJournalEntryID',
    'payroll-runs' => 'tawasulFinanceJournalEntryID',
    'depreciations' => 'tawasulFinanceJournalEntryID',
];
foreach ($ledgerLinked as $name => $column) {
    check("$name withholds $column from writes", !in_array($column, Registry::get($name)['writable'], true));
    check("$name still exposes $column for reading", strpos(Registry::get($name)['select'], $column) !== false);
}

// The audit trail is evidence, so it must not be alterable through the API.
check('the finance audit log is read-only', Registry::get('finance-audit-log')['methods'] === ['GET']);

// --- A missing table must not be reported as a server fault ---------------
echo "\nMissing table handling\n";

$kernel = new ReflectionClass(\Tos\Module\TawasulCore\Kernel::class);

$extract = $kernel->getMethod('missingTable');
$extract->setAccessible(true);
$instance = $kernel->newInstanceWithoutConstructor();

check(
    'the table name is pulled from the driver message',
    $extract->invoke($instance, "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'tos1.timetable' doesn't exist") === 'timetable'
);
check(
    'an unrecognised message yields null rather than leaking it',
    $extract->invoke($instance, 'something else entirely') === null
);

// PDOException must be imported: inside a namespace an unqualified catch would
// resolve to <namespace>\PDOException and silently never match, so a missing
// table would keep surfacing as a 500.
$source = file_get_contents(dirname(__DIR__, 2).'/modules/TawasulCore/src/Kernel.php');
check('Kernel imports PDOException', preg_match('/^use PDOException;$/m', $source) === 1);
check(
    'Kernel catches PDOException before Throwable',
    strpos($source, 'catch (PDOException $e)') !== false
    && strpos($source, 'catch (PDOException $e)') < strpos($source, 'catch (Throwable $e)')
);

// --- Writes must be validated, not silently coerced -------------------------
// The accounting definitions were written with 'enums' but no 'types', so the
// validator had nothing to check dates and amounts against: a payload of
// {"date": "banana", "amount": "not-a-number"} was accepted with 201 and
// stored as the zero date and 0.00.
echo "\nWrite validation\n";

// Character columns legitimately have no 'types' entry, because the validator
// already treats an undeclared field as a string. What must not happen is a
// numeric or date column being left undeclared, which is what let "banana"
// through as a date.
$missingTypes = [];
foreach (Registry::all() as $name => $resource) {
    if ($resource['scope'] !== 'accounting' || empty($resource['table']) || empty($resource['writable'])) {
        continue;
    }
    $columns = [];
    foreach ($admin->query('SHOW COLUMNS FROM `'.$resource['table'].'`')->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[$column['Field']] = $column;
    }
    foreach ($resource['writable'] as $field) {
        if (!empty($resource['types'][$field])) {
            continue;
        }
        $column = $columns[$field] ?? null;
        if ($column === null) {
            continue;
        }
        if (preg_match('/^(?:var)?char|text|blob|json|enum\(/i', $column['Type'])) {
            continue; // a string by default, which is correct
        }
        $missingTypes[$name][] = $field.' ('.$column['Type'].')';
    }
}
check(
    'every non-character writable accounting field declares a type',
    empty($missingTypes),
    json_encode($missingTypes)
);

// The validator's own vocabulary, so a typo in a definition cannot pass as valid.
$validator = new ReflectionClass(\Tos\Module\TawasulCore\Support\Validator::class);
$coerce = $validator->getMethod('coerce');
$coerce->setAccessible(true);

$accepted = $coerce->invoke(null, 'date', 'banana', 'date', null);
check('a bad date is refused', isset($accepted['error']), json_encode($accepted['error'] ?? null));
$accepted = $coerce->invoke(null, 'date', '2026-02-30', 'date', null);
check('an impossible date is refused', isset($accepted['error']));
$accepted = $coerce->invoke(null, 'date', '2026-02-28', 'date', null);
check('a real date is accepted', ($accepted['value'] ?? null) === '2026-02-28');

$accepted = $coerce->invoke(null, 'amount', 'not-a-number', 'number', null);
check('a non-numeric amount is refused', isset($accepted['error']));
$accepted = $coerce->invoke(null, 'amount', '1250.75', 'number', null);
check('a decimal amount is accepted', ($accepted['value'] ?? null) == 1250.75);

$accepted = $coerce->invoke(null, 'supplierID', 'abc', 'integer', null);
check('a non-integer id is refused', isset($accepted['error']));

// Declared against the real columns, so the derived metadata cannot drift.
check('purchase-bills types its date as a date', (Registry::get('purchase-bills')['types']['date'] ?? null) === 'date');
check('purchase-bills types its amount as a number', (Registry::get('purchase-bills')['types']['amount'] ?? null) === 'number');
check('purchase-bills types its supplier as an integer', (Registry::get('purchase-bills')['types']['supplierID'] ?? null) === 'integer');
check('suppliers caps the name length', (Registry::get('suppliers')['maxLength']['name'] ?? null) === 150);
check('purchase-bills caps the bill number', (Registry::get('purchase-bills')['maxLength']['billNumber'] ?? null) === 30);

// A column the schema holds NOT NULL with no default must be declared required,
// or omitting it stores a zero that reads back as a real value.
$unrequired = [];
foreach (Registry::all() as $name => $resource) {
    if ($resource['scope'] !== 'accounting' || empty($resource['table']) || empty($resource['writable'])) {
        continue;
    }
    $columns = [];
    foreach ($admin->query('SHOW COLUMNS FROM `'.$resource['table'].'`')->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[$column['Field']] = $column;
    }
    foreach ($resource['writable'] as $field) {
        $column = $columns[$field] ?? null;
        if ($column === null
            || $column['Null'] !== 'NO'
            || $column['Default'] !== null
            || $column['Extra'] === 'auto_increment') {
            continue;
        }
        if (!in_array($field, $resource['required'] ?? [], true)) {
            $unrequired[$name][] = $field;
        }
    }
}
check(
    'every NOT NULL column without a default is required',
    empty($unrequired),
    json_encode($unrequired)
);

// --- CSV export must not emit executable formulas --------------------------
// A name stored as "=cmd|'/c calc'!A1" was written to the CSV verbatim, so a
// member of staff opening the export executed it (CWE-1236). Quoting does not
// help: Excel discards the quotes and evaluates the contents.
echo "\nCSV export safety\n";

$export = new ReflectionClass(\Tos\Module\TawasulCore\Controller\ExportController::class);
$flatten = $export->getMethod('flatten');
$flatten->setAccessible(true);

foreach (['=1+1', '+1+1', '-1+1', '@SUM(A1)', '=cmd|\'/c calc\'!A1'] as $attack) {
    $cell = $flatten->invoke(null, $attack, true);
    check("CSV guards \"{$attack}\"", strncmp($cell, "'", 1) === 0, $cell);
    check("JSON keeps \"{$attack}\" intact", $flatten->invoke(null, $attack, false) === $attack);
}

// Leading whitespace does not save an attacker: Excel ignores it.
check('a leading space does not bypass the guard', $flatten->invoke(null, ' =1+1', true)[0] === "'");

// Ordinary data and genuine negative numbers must pass through untouched,
// or the fix would silently corrupt a school's figures.
foreach (['Normal Ltd', 'Al Barsha School', 'Cafétermia', ''] as $safe) {
    check("CSV leaves \"{$safe}\" alone", $flatten->invoke(null, $safe, true) === $safe);
}
foreach (['-40.00', '0.00', '3.5', '1250.75', '1000'] as $number) {
    check("CSV leaves the number {$number} alone", $flatten->invoke(null, $number, true) === $number);
}

// Types and booleans have their own formatting and must be unaffected.
check('a boolean still exports as true', $flatten->invoke(null, true, true) === 'true');
check('a boolean still exports as false', $flatten->invoke(null, false, true) === 'false');
check('an array still exports as JSON', strpos($flatten->invoke(null, ['a' => 1], true), '{') === 0);

// The guard must be applied on the CSV path specifically. If it leaked into
// formatRow() it would corrupt the JSON every API client reads.
$source = file_get_contents(dirname(__DIR__, 2).'/modules/TawasulCore/src/Controller/ExportController.php');
check('only the CSV writer passes the flag', substr_count($source, 'self::flatten($formatted[$col] ?? \'\', true)') === 1);

$admin->exec("DROP DATABASE `$scratch`");

echo "\n----------------------------------------\n";
echo ($fail === 0 ? "PASS" : "FAIL") . ": {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
