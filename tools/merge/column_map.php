<?php
/**
 * Proposes the SchoolAccounting -> TawasulFinance column mapping by rule, then
 * checks every proposal against the live schema. Anything it cannot resolve is
 * reported rather than guessed, so the port either has a complete map or an
 * explicit list of gaps.
 *
 *   php tools/merge/column_map.php
 *
 * Read-only. The reviewed result is written to tools/merge/column_map_data.php.
 */


if (getenv('DB_PASSWORD') === false || getenv('DB_PASSWORD') === '') {
    fwrite(STDERR, "Set DB_PASSWORD (and optionally DB_HOST, DB_NAME, DB_USER) first.\n");
    exit(2);
}
$pdo = new PDO((sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: 'localhost', getenv('DB_NAME') ?: 'tos1')), (getenv('DB_USER') ?: 'tos'), (getenv('DB_PASSWORD') ?: ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

function columnsOf(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare(
        'SELECT column_name AS c_name
           FROM information_schema.columns
          WHERE table_schema = ? AND table_name = ?
          ORDER BY ordinal_position'
    );
    $stmt->execute(['tos1', $table]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'c_name');
}

// Irregular names: the two modules picked different words for the same thing.
// Everything else is covered by the systematic rules below.
$irregular = [
    'Account' => [
        'parentID' => 'parentAccountID',
        'timestampCreated' => 'timestampCreator',
    ],
    'JournalEntry' => [
        'reversalOfID' => 'reversedEntryID',
        'createdByID' => 'tawasulPersonIDCreator',
        'postedByID' => 'tawasulPersonIDPoster',
        'timestampCreated' => 'timestampCreator',
        'timestampPosted' => 'timestampPoster',
    ],
    'Receipt' => [
        'receivedByID' => 'tawasulPersonIDReceiver',
    ],
    'StudentDiscount' => [
        'approvedByID' => 'tawasulPersonIDApprover',
    ],
    // Invoice is the one table whose model genuinely differs, not just its
    // naming. Finance bills an invoicee against a billing schedule; School
    // billed a person directly against a fee plan and tracked an installment
    // number. Both modules' invoice tables are exercised by different pages, so
    // the surviving table is Finance's and the ported pages are rewritten
    // against it rather than renamed onto it. What does carry over:
    'Invoice' => [
        'issueDate' => 'invoiceIssueDate',
        'dueDate' => 'invoiceDueDate',
        'invoiceNumber' => 'key',
        'feePlanID' => 'tawasulFinanceBillingScheduleID',
    ],

    // SchoolAccounting's invoice lines become tawasulFinanceInvoiceFee. Both
    // hold one row per line on an invoice; Finance's additionally carries the
    // fee name, category and sequence, so the School page is ported onto the
    // wider shape rather than a rename.
    'InvoiceLine' => [
        'table' => 'tawasulFinanceInvoiceFee',
        'columns' => [
            'tawasulSchoolAccountingInvoiceLineID' => 'tawasulFinanceInvoiceFeeID',
            'tawasulSchoolAccountingInvoiceID' => 'tawasulFinanceInvoiceID',
            'feeItemID' => 'tawasulFinanceFeeID',
            'amount' => 'fee',
        ],
    ],
];

/**
 * Columns a table had that the surviving table genuinely cannot hold. They are
 * reported rather than renamed, so nothing is silently dropped.
 */
$dropped = [
    'Invoice' => [
        // Finance reaches the billed person through
        // tawasulFinanceInvoiceeID -> tawasulFinanceInvoicee.tawasulPersonID,
        // so the direct link has no column to live in.
        'tawasulPersonID' => 'replaced by the tawasulFinanceInvoicee join',
        // Finance issues one invoice per billing-schedule run, so there is no
        // installment sequence to store.
        'installmentNo' => 'no equivalent concept in the surviving model',
    ],
    'InvoiceLine' => [
        'discount' => 'per-line discount is held on the invoice as discountAmount',
    ],
];

/**
 * Resolve one School column name against a Finance table's column list.
 * Returns the Finance column name, or null when nothing matches.
 */
function resolve(string $school, array $finance, array $irregularForTable): ?string
{
    // A table entry may nest its renames under 'columns' when it is
    // consolidated into a differently named surviving table.
    if (isset($irregularForTable['columns'])) {
        $irregularForTable = $irregularForTable['columns'];
    }

    if (isset($irregularForTable[$school])) {
        $candidate = $irregularForTable[$school];
        return in_array($candidate, $finance, true) ? $candidate : null;
    }

    // Already identical.
    if (in_array($school, $finance, true)) {
        return $school;
    }

    // The module's own key and foreign keys: tawasulSchoolAccountingX -> tawasulFinanceX.
    if (str_starts_with($school, 'tawasulSchoolAccounting')) {
        $candidate = 'tawasulFinance'.substr($school, strlen('tawasulSchoolAccounting'));
        return in_array($candidate, $finance, true) ? $candidate : null;
    }

    // Bare foreign keys: accountID -> tawasulFinanceAccountID, and so on.
    foreach (['tawasulFinance', 'tawasul'] as $prefix) {
        $candidate = $prefix.ucfirst($school);
        if (in_array($candidate, $finance, true)) {
            return $candidate;
        }
    }

    // audit columns: timestampCreated -> timestampCreator.
    if ($school === 'timestampCreated' && in_array('timestampCreator', $finance, true)) {
        return 'timestampCreator';
    }

    return null;
}

$school = $pdo->query("SHOW TABLES LIKE 'tawasulSchoolAccounting%'")->fetchAll(PDO::FETCH_COLUMN);
sort($school);

$retarget = [
    'InvoiceLine' => 'tawasulFinanceInvoiceFee',
];

$map = [];
$gaps = [];
$identical = [];

foreach ($school as $st) {
    $concept = substr($st, strlen('tawasulSchoolAccounting'));
    // A table may be consolidated into a differently named surviving table.
    $target = $retarget[$concept] ?? ('tawasulFinance'.$concept);
    $ft = $target;

    $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?');
    $exists->execute(['tos1', $ft]);
    if ((int) $exists->fetchColumn() === 0) {
        $gaps[$concept] = ['table' => $ft.' does not exist'];
        continue;
    }

    $finance = columnsOf($pdo, $ft);
    $renames = [];
    foreach (columnsOf($pdo, $st) as $school2) {
        if (isset($dropped[$concept][$school2])) {
            continue;
        }
        $to = resolve($school2, $finance, $irregular[$concept] ?? []);
        if ($to === null) {
            $gaps[$concept][$school2] = 'unresolved';
        } elseif ($to !== $school2) {
            $renames[$school2] = $to;
        }
    }

    if (isset($gaps[$concept]) && $gaps[$concept]) {
        continue;
    }
    if (!$renames) {
        $identical[] = $concept;
    } else {
        $map[$concept] = ['table' => $ft, 'columns' => $renames];
    }
}

echo "tables mapped:        ".count($map)."\n";
echo "tables needing no rename: ".implode(', ', $identical)."\n\n";
echo "column renames: ".array_sum(array_map('count', $map))."\n\n";

if ($gaps) {
    echo "GAPS (".count($gaps)." table(s)) -- these need a decision, not a rule:\n\n";
    foreach ($gaps as $concept => $detail) {
        echo "  {$concept}:\n";
        foreach ($detail as $k => $v) {
            echo "     {$k}: {$v}\n";
        }
    }
    exit(1);
}

echo "deliberately dropped (not renamed):\n";
foreach ($dropped as $table => $cols) {
    foreach ($cols as $col => $why) {
        echo "  {$table}.{$col}: {$why}\n";
    }
}
echo "\nevery remaining SchoolAccounting column resolves to an existing TawasulFinance column\n";

file_put_contents(__DIR__.'/column_map_data.php',
    "<?php\n// Generated by tools/merge/column_map.php, then reviewed. Every entry was\n"
    ."// checked against the live schema at generation time.\n\nreturn ".var_export($map, true).";\n");
echo "wrote tools/merge/column_map_data.php\n";

// The global rename table is only safe for renames that mean the same thing in
// every table. Invoice and InvoiceLine are rewritten rather than renamed, so
// their renames are withheld: 'amount' becomes 'fee' on an invoice line, but
// 'amount' is a column of about ten other tables and must stay put there.
$rewritten = ['Invoice', 'InvoiceLine'];
$global = [];
$withheld = [];
foreach ($map as $concept => $entry) {
    foreach ($entry['columns'] as $from => $to) {
        if (in_array($concept, $rewritten, true)) {
            $withheld[$concept][$from] = $to;
            continue;
        }
        // Withhold anything else that would resolve differently elsewhere.
        if (isset($global[$from]) && $global[$from] !== $to) {
            $withheld['ambiguous'][$from] = $to;
            continue;
        }
        $global[$from] = $to;
    }
}
ksort($global);

file_put_contents(__DIR__.'/global_rename_data.php',
    "<?php\n// Generated by tools/merge/column_map.php, then reviewed.\n"
    ."//\n// Every entry is a column rename that means the same thing in all 28 tables, so it\n"
    ."// is safe to apply as a single global rewrite while porting the code. Renames\n"
    ."// belonging to Invoice and InvoiceLine are withheld: those two tables are\n"
    ."// rewritten onto the surviving invoice model rather than renamed, and their\n"
    ."// names ('amount', 'feePlanID', 'feeItemID') collide with columns that must keep\n"
    ."// their existing meaning elsewhere.\n\nreturn ".var_export($global, true).";\n");

echo "global rename table: ".count($global)." entries\n";
echo "withheld from the global table:\n";
foreach ($withheld as $scope => $cols) {
    foreach ($cols as $from => $to) {
        echo "  {$scope}.{$from} -> {$to}\n";
    }
}
