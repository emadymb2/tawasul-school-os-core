<?php
/**
 * Generates the SQL that merges the former SchoolAccounting module into
 * TawasulFinance, and can apply it.
 *
 *   php tools/merge/merge_sql.php            # print the SQL
 *   php tools/merge/merge_sql.php --apply    # apply it to tos1
 *   php tools/merge/merge_sql.php --check    # report only, change nothing
 *
 * The account-code remapping is read from tools/merge/chart_map_data.php rather
 * than hard-coded, so the data migration and the generated documentation cannot
 * disagree.
 */

$root = dirname(__DIR__, 2);
$map = require __DIR__.'/chart_map_data.php';

$mode = $argv[1] ?? '';
$apply = ($mode === '--apply');
$check = ($mode === '--check');

$sql = [];

// ---- 1. settings -----------------------------------------------------------
// The 13 SchoolAccounting settings move to the Finance scope. The 6 that stored
// an account code get the TawasulFinance code that code resolved to; the rest
// carry across unchanged. The unique key is (scope, nameDisplay), so the scope
// move is an UPDATE rather than an insert/delete pair.
$sql[] = "-- 1. Settings: move the SchoolAccounting scope into Finance";
$sql[] = "UPDATE `tawasulSetting` SET scope = 'Finance' WHERE scope = 'School Accounting'";

foreach ($map['settings'] as $setting => $code) {
    $sql[] = "UPDATE `tawasulSetting` SET value = '{$code}'"
        ." WHERE scope = 'Finance' AND name = '{$setting}' AND value <> '{$code}'";
}

// ---- 2. column reconciliation ---------------------------------------------
// SchoolAccounting's tables were empty apart from the chart and the sequences,
// so no rows are copied. What the merge does carry across is schema: the columns
// SchoolAccounting had that TawasulFinance lacks are added to the surviving
// table, so the ported pages and services keep working against one set of
// tables. Each entry is [financeTable, [column => definition]].
//
// Populated by tools/merge/column_map.php.

$extra = require __DIR__.'/column_map.php';
$sql[] = '';
$sql[] = '-- 2. Schema: add the columns TawasulFinance lacks';
foreach ($extra as $table => $columns) {
    foreach ($columns as $column => $definition) {
        $sql[] = "ALTER TABLE `tawasulFinance{$table}` ADD COLUMN `{$column}` {$definition}";
    }
}

// ---- 3. drop the SchoolAccounting tables ----------------------------------
$sql[] = '';
$sql[] = '-- 3. Drop the merged module\'s own tables';
$schoolTables = ['Account', 'Asset', 'AuditLog', 'BankReconciliation', 'Budget',
    'CashAccount', 'CostCenter', 'Depreciation', 'Discount', 'FeeItem', 'FeePlan',
    'FeePlanItem', 'FiscalYear', 'Invoice', 'InvoiceLine', 'JournalEntry',
    'JournalLine', 'PaymentVoucher', 'PayrollLine', 'PayrollRun', 'Period',
    'PurchaseBill', 'Receipt', 'SalaryComponent', 'Sequence', 'StaffSalary',
    'StudentDiscount', 'Supplier', 'Transfer'];

$sql[] = 'DROP TABLE IF EXISTS '.implode(', ', array_map(
    fn ($t) => '`tawasulSchoolAccounting'.$t.'`',
    $schoolTables
));

$sql[] = "DELETE FROM `tawasulSetting` WHERE scope = 'School Accounting'";

$text = implode("\n", $sql)."\n";

if (!$apply) {
    echo $text;
    exit(0);
}


if (getenv('DB_PASSWORD') === false || getenv('DB_PASSWORD') === '') {
    fwrite(STDERR, "Set DB_PASSWORD (and optionally DB_HOST, DB_NAME, DB_USER) first.\n");
    exit(2);
}
$pdo = new PDO((sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: 'localhost', getenv('DB_NAME') ?: 'tos1')), (getenv('DB_USER') ?: 'tos'), (getenv('DB_PASSWORD') ?: ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if ($check) {
    echo "check mode: nothing applied\n";
}

$statements = array_values(array_filter(array_map('trim', explode(";\n", $text)), 'strlen'));
$errors = [];
foreach ($statements as $i => $stmt) {
    if (str_starts_with($stmt, '--')) {
        continue;
    }
    try {
        $pdo->query($stmt);
        echo "  ok   ".substr($stmt, 0, 72)."\n";
    } catch (PDOException $e) {
        $errors[] = $stmt."\n     -> ".$e->getMessage();
        echo "  FAIL ".substr($stmt, 0, 72)."\n";
    }
}

if ($errors) {
    echo "\nERRORS (".count($errors)."):\n";
    foreach ($errors as $e) {
        echo "  $e\n";
    }
    exit(1);
}
echo "\napplied ".count($statements)." statements with no errors\n";
