<?php
/**
 * Seed the TawasulFinance accounting reference data.
 *
 * Run from the command line:
 *   php modules/TawasulFinance/src/Domain/seed_chart.php
 *
 * The work itself lives in ChartOfAccounts, which takes a Connection and is
 * therefore testable against any database. This file only supplies the real
 * connection for this installation.
 */

// The core TawasulOS\ autoloader is not registered under the CLI.
if (!class_exists(\TawasulOS\Contracts\Database\Connection::class)) {
    require_once __DIR__ . '/../../../../vendor/autoload.php';
}
require_once __DIR__ . '/../bootstrap.php';

// config.php assigns these as plain variables rather than returning them.
require_once __DIR__ . '/../../../../config.php';

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    $databaseServer ?? 'localhost',
    $databaseName
);

$pdo = new PDO($dsn, $databaseUsername, $databasePassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$connection = new \Tos\Module\TawasulFinance\Support\ConnectionAdapter($pdo);

$seeder = new \Tos\Module\TawasulFinance\Domain\ChartOfAccounts($connection);
$result = $seeder->seed();

printf("chart of accounts: %d inserted, %d updated (%d defined)\n",
    $result['accounts']['inserted'],
    $result['accounts']['updated'],
    $result['accounts']['defined']
);
printf("document sequences: %d   cost centres: %d   salary components: %d\n",
    $result['sequences'],
    $result['costCentres'],
    $result['salaryComponents']
);
printf("fiscal year: %s\n", $result['fiscalYear'] ? 'created' : 'already present');