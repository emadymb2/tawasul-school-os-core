<?php
/**
 * Checks that every tawasul* table and column a PHP file names actually exists
 * in the schema. Used to catch ported code that still refers to a table the
 * merge dropped, or to a column that was renamed.
 *
 *   php tools/merge/check_identifiers.php <file.php> [more.php ...]
 *
 * Identifiers that appear only inside a comment are reported separately rather
 * than treated as errors, because the merge's documentation deliberately names
 * the old ones.
 */


if (getenv('DB_PASSWORD') === false || getenv('DB_PASSWORD') === '') {
    fwrite(STDERR, "Set DB_PASSWORD (and optionally DB_HOST, DB_NAME, DB_USER) first.\n");
    exit(2);
}
$pdo = new PDO((sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: 'localhost', getenv('DB_NAME') ?: 'tos1')), (getenv('DB_USER') ?: 'tos'), (getenv('DB_PASSWORD') ?: ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$exists = [];
// information_schema reports its own columns in upper case on MySQL 8 and
// lower case on MariaDB, so alias them to names that are stable either way.
foreach ($pdo->query(
    "SELECT table_name AS t, column_name AS c FROM information_schema.columns WHERE table_schema = 'tos1'"
)->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $exists[$r['c']] = true;
    $exists[$r['t']] = true;
}

$failed = 0;
foreach (array_slice($argv, 1) as $path) {
    $text = file_get_contents($path);
    // Blank out comments so documented old names are not counted as live code.
    $code = preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], ' ', $text);

    preg_match_all('/\btawasul[A-Za-z0-9_]+\b/', $code, $m);
    $missing = array_values(array_unique(array_filter(
        $m[0],
        fn ($n) => !isset($exists[$n])
    )));

    if ($missing) {
        $failed++;
        echo "  ".basename(dirname($path)).'/'.basename($path).": ".implode(', ', $missing)."\n";
    } else {
        echo "  ok  ".basename(dirname($path)).'/'.basename($path)."\n";
    }
}

exit($failed ? 1 : 0);
