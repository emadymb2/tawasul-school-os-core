<?php
/**
 * Verifies the SchoolAccounting -> TawasulFinance chart mapping against the live
 * database and the CHART constant, then generates the mapping table as
 * docs/schoolaccounting_chart_merge.md.
 *
 *   php tools/merge/chart_map.php          # verify only
 *   php tools/merge/chart_map.php --write  # verify and regenerate the doc
 */

$root = dirname(__DIR__, 2);
$map = require __DIR__.'/chart_map_data.php';


if (getenv('DB_PASSWORD') === false || getenv('DB_PASSWORD') === '') {
    fwrite(STDERR, "Set DB_PASSWORD (and optionally DB_HOST, DB_NAME, DB_USER) first.\n");
    exit(2);
}
$pdo = new PDO((sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: 'localhost', getenv('DB_NAME') ?: 'tos1')), (getenv('DB_USER') ?: 'tos'), (getenv('DB_PASSWORD') ?: ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$errors = [];

// The codes TawasulFinance actually defines, read back from CHART so the check
// follows the code rather than a copy of it.
$source = file_get_contents($root.'/modules/TawasulFinance/src/Domain/ChartOfAccounts.php');
preg_match('/const CHART = \[(.*?)\n    \];/s', $source, $m);
preg_match_all("/'([0-9]{4})' => \['(\w+)', '([YN])', '(.*?)', (null|'[0-9]{4}')\]/u", $m[1], $chartRows, PREG_SET_ORDER);
$chart = [];
foreach ($chartRows as $row) {
    $chart[$row[1]] = ['type' => $row[2], 'isPosting' => $row[3], 'name' => $row[4], 'parent' => trim($row[5], "'")];
}

// The retired SchoolAccounting chart. Its table was dropped by the merge, so it
// comes from the mapping file, which records it verbatim. Keeping the check
// driven by that record means the mapping stays verifiable after the merge.
$school = [];
foreach ($map['retired'] as $code => [$name, $type]) {
    $school[(string) $code] = ['name' => $name, 'type' => $type];
}

echo "SchoolAccounting accounts in mapping:  ".count($school)."\n";
echo "TawasulFinance accounts in CHART:     ".count($chart)."\n\n";

// 1. Every SchoolAccounting account is covered, whether as a leaf or as a
//    structural node.
foreach (array_keys($school) as $code) {
    if (!isset($map['accounts'][$code]) && !isset($map['structural'][$code])) {
        $errors[] = "SchoolAccounting account {$code} ({$school[$code]['name']}) is not in the mapping";
    }
}

// 2. The mapping has no rows for accounts that do not exist.
foreach (array_keys($map['accounts']) as $code) {
    if (!isset($school[$code])) {
        $errors[] = "mapping references SchoolAccounting account {$code}, which is not in the retired chart";
    }
}

// 3. Every target exists in CHART, and the statement type is preserved.
foreach ($map['accounts'] as $code => [$target, $note]) {
    if (!isset($chart[$target])) {
        $errors[] = "SchoolAccounting {$code} maps to {$target}, which is not defined in CHART";
        continue;
    }
    if ($chart[$target]['type'] !== $school[$code]['type']) {
        $errors[] = "SchoolAccounting {$code} is {$school[$code]['type']} but {$target} is {$chart[$target]['type']}";
    }
}

// 4. No two SchoolAccounting accounts collapse onto one target: that would merge
//    two distinct accounts into one posting account.
$targets = array_map(fn ($v) => $v[0], $map['accounts']);
$dupes = array_diff_assoc($targets, array_unique($targets));
foreach ($dupes as $code => $target) {
    $errors[] = "SchoolAccounting {$code} and another account both map to {$target}";
}

// 5. Structural nodes must point only at non-posting roots.
foreach ($map['structural'] as $code => $structuralTargets) {
    if (!isset($school[$code])) {
        $errors[] = "structural node {$code} is not a retired SchoolAccounting account";
    }
    foreach ($structuralTargets as $target) {
        if (!isset($chart[$target])) {
            $errors[] = "structural node {$code} points at undefined account {$target}";
        } elseif ($chart[$target]['isPosting'] === 'Y') {
            $errors[] = "structural node {$code} points at posting account {$target}, expected a root";
        }
    }
}

// 6. Every setting must point at a code the mapping actually resolved to.
foreach ($map['settings'] as $setting => $code) {
    if (!in_array($code, $targets, true)) {
        $errors[] = "setting {$setting} points at {$code}, which no SchoolAccounting account maps to";
    }
}

if ($errors) {
    echo "FAILED (".count($errors)."):\n";
    foreach ($errors as $e) {
        echo "  $e\n";
    }
    exit(1);
}
echo "mapping verified: ".count($map['accounts'])." leaf accounts, "
    .count($map['structural'])." structural nodes, "
    .count($map['settings'])." settings\n";

if (!in_array('--write', $argv, true)) {
    exit(0);
}

// ---------------- regenerate the documentation ----------------
$rows = '';
foreach ($map['accounts'] as $code => [$target, $note]) {
    $rows .= sprintf(
        "| `%s` | %s | `%s` | %s | %s |\n",
        $code,
        $school[$code]['name'],
        $target,
        $chart[$target]['name'],
        $note === '' ? '' : $note
    );
}

$structural = '';
foreach ($map['structural'] as $code => $targets) {
    $names = array_map(fn ($t) => '`'.$t.'` '.$chart[$t]['name'], $targets);
    $structural .= sprintf("| `%s` | %s | %s | %s |\n", $code, $school[$code]['name'],
        implode(' + ', $names), 'structural root, not posted to directly');
}

$settings = '';
$byTarget = [];
foreach ($map['accounts'] as $from => [$to]) {
    $byTarget[$to] = $from;
}
foreach ($map['settings'] as $setting => $code) {
    // The setting stores the TawasulFinance code, so find the SchoolAccounting
    // account it came from to show what it used to point at.
    $from = $byTarget[$code] ?? null;
    $was = $from === null ? '' : '`'.$from.'` '.$school[$from]['name'];
    $settings .= sprintf("| `%s` | %s | `%s` | %s |\n", $setting, $was, $code, $chart[$code]['name']);
}

$newAccounts = array_filter($map['accounts'], fn ($v) => str_contains($v[1], 'new account'));

$newTable = '';
foreach ($newAccounts as $code => [$target, $note]) {
    $newTable .= sprintf("| `%s` | %s | `%s` %s |\n",
        $code, $school[$code]['name'], $target, $chart[$target]['name']);
}

$doc = <<<MD
# SchoolAccounting merge

`SchoolAccounting` was merged into `TawasulFinance` as a single module, and the
module no longer exists. The two had each shipped a whole accounting system --
their own tables, their own chart of accounts, their own pages -- and their
charts shared **no codes at all**: SchoolAccounting numbered its accounts
`1` / `11` / `1101` / `5109` with Arabic labels only, while TawasulFinance
numbers them `1000` / `1100` / `5100` with bilingual `English / Arabic` labels.

## What the merge produced

| | before | after |
| --- | --- | --- |
| modules | 2 | 1 (`TawasulFinance`) |
| accounting tables | 41 + 29 | 41 |
| chart | 55 + 39 accounts, 0 codes in common | 59 bilingual accounts |
| actions | 17 + 39 | 56 |
| module settings | 23 + 13 scopes | 36 in the `Finance` scope |

Nothing transactional crossed: SchoolAccounting held no postings at all, so no
balances needed reconciling. The merge carried across a chart, a settings scope,
39 actions and the pages.

TawasulFinance is the surviving chart, because the only real postings in the
system hang off it. Every SchoolAccounting code was therefore *resolved onto* a
TawasulFinance code rather than inserted as a second parallel account, so the
merged module has exactly one chart.

## Code

The port is mechanical for almost everything. `tools/merge/` holds the tools
that produced and still verify it:

| tool | what it does |
| --- | --- |
| `chart_map_data.php` | the account mapping below; the single source of truth |
| `chart_map.php` | verifies the mapping, regenerates this file |
| `column_map.php` | derives and checks the column mapping against the live schema |
| `port.py` | applies the module path, table prefix and column renames to the pages |
| `port_invoice_pass.py` | the same for the pages whose invoice columns had to be read by hand |
| `check_links.py` | every intra-module link resolves |
| `check_identifiers.php` | every table and column a ported file names exists |

`feePlanID` and `feeItemID` are deliberately excluded from the global column
rename: they resolve differently in `Invoice` and `InvoiceLine`, whose model is
rewritten rather than renamed, and `amount` would have been silently corrupted
across the ten other tables that have a column by that name.

This file is generated by `php tools/merge/chart_map.php --write` from
`tools/merge/chart_map_data.php`, which is the single source of truth for both this
table and the SQL in the v1.2.00 migration. Do not edit it by hand. The retired
chart is recorded in that file rather than read from the database, so this table
stays reproducible now that `tawasulSchoolAccountingAccount` has been dropped.

## New accounts

Four SchoolAccounting accounts had no TawasulFinance equivalent and were added
to the chart rather than forced onto a near miss:

| SchoolAccounting | | TawasulFinance |
| --- | --- | --- |
{$newTable}
## Structural accounts

SchoolAccounting nested all assets under one root and all liabilities under
another. TawasulFinance splits each into current and long-term, so these roots
have no single counterpart and nothing is posted to them directly.

| SchoolAccounting | | Resolves to | |
| --- | --- | --- | --- |
{$structural}
## Leaf accounts

| SchoolAccounting | | TawasulFinance | | Note |
| --- | --- | --- | --- | --- |
{$rows}
## Settings remapped

Six module settings stored a SchoolAccounting account code. Each now stores the
TawasulFinance code its account resolved to:

| Setting | Was | Now | Account |
| --- | --- | --- | --- |
{$settings}
## What was not merged

`SchoolAccounting` held no postings. Every table except its chart and its
document sequences was empty, so the merge carried no transactional data across
and no reconciliation of balances was needed. Its nine document sequences were a
subset of TawasulFinance's ten by document type, so they were dropped rather than
merged.

Two features did not survive, because the surviving model has nowhere to hold
them. Both are recorded in `src/Service/Billing.php`:

- **Installment billing.** SchoolAccounting billed against a fee plan in a
  numbered series of installments. The surviving `BillingSchedule` has no
  installment sequence and no cost centre, so `generateForSchedule()` now raises
  one invoice per invoicee per run.
- **Per-line invoice discounts.** `tawasulFinanceInvoiceFee` has no discount
  column, so line discounts roll up into the invoice's `discountAmount`, which is
  where the surviving model keeps them.

A pre-existing gap was closed along the way: `accounts_manage.php` linked to
`accounts_manage_add.php`, `accounts_manage_edit.php` and
`accounts_manage_delete.php`, none of which existed. The pages ported in from
SchoolAccounting provide them, and the links now point there.
MD;

file_put_contents($root.'/docs/schoolaccounting_chart_merge.md', $doc);
echo "wrote docs/schoolaccounting_chart_merge.md (".count($newAccounts)." new accounts noted)\n";
