<?php
/**
 * Reports, for every table the merge consolidates, the columns
 * SchoolAccounting had and TawasulFinance did not.
 *
 *   php tools/merge/column_diff.php
 *
 * Read-only: it exists so the column decisions in tools/merge/column_map.php can
 * be made against the real diff rather than from memory.
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
    // information_schema reports its columns in upper case on MySQL 8 and lower
    // case on MariaDB, so alias them to names that are stable either way.
    $stmt = $pdo->prepare(
        'SELECT column_name AS c_name, column_type AS c_type, is_nullable AS c_nullable,
                column_default AS c_default, extra AS c_extra
           FROM information_schema.columns
          WHERE table_schema = ? AND table_name = ?
          ORDER BY ordinal_position'
    );
    $stmt->execute(['tos1', $table]);

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['c_name']] = $r;
    }
    return $out;
}

$school = $pdo->query("SHOW TABLES LIKE 'tawasulSchoolAccounting%'")->fetchAll(PDO::FETCH_COLUMN);
sort($school);

$report = [];
foreach ($school as $st) {
    $concept = substr($st, strlen('tawasulSchoolAccounting'));
    $ft = 'tawasulFinance'.$concept;

    $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?');
    $exists->execute(['tos1', $ft]);
    $financeExists = (int) $exists->fetchColumn() > 0;

    $schoolColumns = columnsOf($pdo, $st);
    $financeColumns = $financeExists ? columnsOf($pdo, $ft) : [];

    $renames = [];
    $adds = [];
    $same = [];
    foreach ($schoolColumns as $name => $c) {
        if (str_starts_with($name, 'tawasulSchoolAccounting')) {
            $renames[$name] = 'tawasulFinance'.substr($name, strlen('tawasulSchoolAccounting'));
        } elseif (isset($financeColumns[$name])) {
            $same[] = $name;
        } else {
            $adds[$name] = $c;
        }
    }

    $missingTargets = array_filter($renames, fn ($t) => !isset($financeColumns[$t]));

    $report[$concept] = [
        'financeExists' => $financeExists,
        'renames' => $renames,
        'missingTargets' => $missingTargets,
        'adds' => $adds,
        'sameCount' => count($same),
        'schoolCount' => count($schoolColumns),
    ];
}

// A compact summary, then the detail only for tables that need a decision.
echo "table".str_repeat(' ', 22).'fin  school  same  renames  add  missing-targets'."\n";
echo str_repeat('-', 78)."\n";
foreach ($report as $concept => $r) {
    printf("%-26s %-4s %-7d %-5d %-8d %-4d %d\n",
        $concept,
        $r['financeExists'] ? 'yes' : 'NO',
        $r['schoolCount'],
        $r['sameCount'],
        count($r['renames']),
        count($r['adds']),
        count($r['missingTargets'])
    );
}

$needWork = array_filter($report, fn ($r) => $r['adds'] || $r['missingTargets'] || !$r['financeExists']);
echo "\n".count($needWork)." table(s) need a decision:\n\n";
foreach ($needWork as $concept => $r) {
    echo "### {$concept} (tawasulFinance{$concept} ".($r['financeExists'] ? 'exists' : 'MISSING').")\n";
    if (!$r['financeExists']) {
        foreach ($r['adds'] as $n => $c) {
            echo "    new table column  {$n} {$c['c_type']}\n";
        }
    }
    foreach ($r['missingTargets'] as $from => $to) {
        echo "    !! key/fk target absent in Finance: {$from} -> {$to}\n";
    }
    foreach ($r['adds'] as $n => $c) {
        printf("    add  %-26s %-32s null=%-3s default=%-10s %s\n",
            $n, $c['c_type'], $c['c_nullable'],
            $c['c_default'] === null ? '-' : $c['c_default'], $c['c_extra']);
    }
    echo "\n";
}
