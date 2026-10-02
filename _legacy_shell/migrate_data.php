<?php
/**
 * Migrate data from gibbon database to tos database
 * Handles column mismatches, default values, and duplicate keys
 */

$gibbonDb = 'fiksu_school';
$tosDb = 'tos';

$gibbonPdo = new PDO("mysql:host=localhost;dbname=$gibbonDb;charset=utf8mb4", 'root', '');
$tosPdo = new PDO("mysql:host=localhost;dbname=$tosDb;charset=utf8mb4", 'root', '');

$gibbonPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$tosPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get all tables from gibbon
$gibbonTables = $gibbonPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$tosTables = $tosPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

echo "Gibbon tables: " . count($gibbonTables) . "\n";
echo "TOS tables: " . count($tosTables) . "\n\n";

$migrated = 0;
$skipped = 0;
$totalRows = 0;

// Process in dependency order (settings, roles, school years first)
$priorityTables = ['gibbonSetting', 'gibbonRole', 'gibbonSchoolYear', 'gibbonRoleCategory', 'gibbonPermission'];
$sortedTables = $priorityTables;
foreach ($gibbonTables as $t) {
    if (!in_array($t, $priorityTables)) {
        $sortedTables[] = $t;
    }
}

foreach ($sortedTables as $gibbonTable) {
    $tosTable = transformTableName($gibbonTable);
    
    if (!in_array($tosTable, $tosTables)) {
        $skipped++;
        continue;
    }
    
    // Get column names from both tables
    $gibbonCols = $gibbonPdo->query("SHOW COLUMNS FROM `$gibbonTable`")->fetchAll(PDO::FETCH_COLUMN);
    $tosCols = $tosPdo->query("SHOW COLUMNS FROM `$tosTable`")->fetchAll(PDO::FETCH_COLUMN);
    
    // Get tos column types for default value handling
    $tosColTypes = [];
    $tosColInfo = $tosPdo->query("SHOW FULL COLUMNS FROM `$tosTable`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tosColInfo as $col) {
        $tosColTypes[$col['Field']] = $col;
    }
    
    // Build column mapping - map ALL columns (not just gibbon-prefixed ones)
    $colMap = [];
    foreach ($gibbonCols as $gCol) {
        $tCol = transformColumnName($gCol);
        if (in_array($tCol, $tosCols)) {
            $colMap[$gCol] = $tCol;
        }
    }
    
    if (empty($colMap)) {
        if ($gibbonTable === 'gibbonPerson') {
            echo "  DEBUG: No column mapping for $gibbonTable\n";
            echo "  Gibbon cols: " . implode(', ', $gibbonCols) . "\n";
            echo "  TOS cols: " . implode(', ', $tosCols) . "\n";
        }
        $skipped++;
        continue;
    }
    
if ($gibbonTable === 'gibbonPerson') {
            echo "  DEBUG: Column map for $gibbonTable:\n";
            foreach ($colMap as $g => $t) {
                echo "    $g → $t\n";
            }
        }
        
        // Get data from gibbon
        $gibbonColList = '`' . implode('`, `', array_keys($colMap)) . '`';
        
        if ($gibbonTable === 'gibbonPerson') {
            $countCheck = $gibbonPdo->query("SELECT COUNT(*) FROM `$gibbonTable`")->fetchColumn();
            echo "  DEBUG: Total rows in gibbonPerson: $countCheck\n";
        }
    
    try {
        $stmt = $gibbonPdo->query("SELECT $gibbonColList FROM `$gibbonTable`");
        $rowCount = 0;
        
        // Build INSERT with ON DUPLICATE KEY UPDATE
        $tosColList = '`' . implode('`, `', array_values($colMap)) . '`';
        $placeholders = implode(', ', array_fill(0, count($colMap), '?'));
        
        // Build update part for ON DUPLICATE KEY
        $updateParts = [];
        foreach (array_values($colMap) as $tCol) {
            $updateParts[] = "`$tCol` = VALUES(`$tCol`)";
        }
        $updateSql = implode(', ', $updateParts);
        
        $insertSql = "INSERT INTO `$tosTable` ($tosColList) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateSql";
        $insertStmt = $tosPdo->prepare($insertSql);
        
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            try {
                $insertStmt->execute($row);
                $rowCount++;
            } catch (Exception $e) {
                if ($gibbonTable === 'gibbonPerson') {
                    echo "  DEBUG person row error: " . $e->getMessage() . "\n";
                }
                // Skip duplicate rows silently
            }
        }
        
        if ($rowCount > 0) {
            echo "MIGRATED: $gibbonTable → $tosTable ($rowCount rows)\n";
            $migrated++;
            $totalRows += $rowCount;
        } else {
            if ($gibbonTable === 'gibbonPerson') {
                echo "  DEBUG: 0 rows migrated for $gibbonTable\n";
            }
            $skipped++;
        }
    } catch (Exception $e) {
        if ($gibbonTable === 'gibbonPerson') {
            echo "  DEBUG person table error: " . $e->getMessage() . "\n";
        }
        echo "ERROR: $gibbonTable → $tosTable: " . $e->getMessage() . "\n";
        $skipped++;
    }
}

echo "\n=== Summary ===\n";
echo "Migrated: $migrated tables\n";
echo "Skipped: $skipped tables\n";
echo "Total rows: $totalRows\n";

// Verify key tables
echo "\n=== Verification ===\n";
$verifyTables = ['tos_setting', 'tos_role', 'tos_school_year', 'tos_person', 'tos_student_enrolment'];
foreach ($verifyTables as $table) {
    try {
        $count = $tosPdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "$table: $count rows\n";
    } catch (Exception $e) {
        echo "$table: ERROR - " . $e->getMessage() . "\n";
    }
}

function transformTableName(string $name): string
{
    $entity = preg_replace('/^gibbon/i', '', $name);
    
    if (preg_match('/^IN/', $entity)) {
        $entity = 'individual_needs' . substr($entity, 2);
    }
    if (preg_match('/^TT/', $entity)) {
        $entity = 'timetable' . substr($entity, 2);
    }
    
    return 'tos_' . toSnakeCase($entity);
}

function transformColumnName(string $name): string
{
    // Only transform gibbon-prefixed columns; leave others as-is
    if (!preg_match('/gibbon/i', $name)) {
        return $name;
    }
    
    $entity = $name;
    
    // Expand special abbreviations
    if (preg_match('/^IN/', $entity)) {
        $entity = 'individual_needs' . substr($entity, 2);
    }
    if (preg_match('/^TT/', $entity)) {
        $entity = 'timetable' . substr($entity, 2);
    }
    
    $entity = preg_replace('/gibbon/i', '', $entity);
    
    if (preg_match('/^IN/', $entity)) {
        $entity = 'individual_needs' . substr($entity, 2);
    }
    if (preg_match('/^TT/', $entity)) {
        $entity = 'timetable' . substr($entity, 2);
    }
    
    // Transform CamelCase to snake_case
    $result = toSnakeCase($entity);
    
    // Handle ID suffix
    if (preg_match('/^(.+?)ID(\d*)$/', $result, $m)) {
        return $m[1] . '_id' . ($m[2] !== '' ? $m[2] : '');
    }
    
    return $result;
}

function toSnakeCase(string $input): string
{
    if (preg_match('/^[a-z0-9_]+$/', $input)) {
        return $input;
    }
    
    // Handle ID suffix: insert underscore before ID
    $result = preg_replace('/ID(\d*)$/', '_id$1', $input);
    
    // Convert CamelCase to snake_case
    $result = preg_replace('/(?<=[a-z\d])([A-Z])/', '_$1', $result);
    $result = preg_replace('/(?<=[A-Z])([A-Z])(?=[a-z])/', '_$0', $result);
    $result = strtolower($result);
    $result = preg_replace('/_+/', '_', $result);
    $result = trim($result, '_');
    
    return $result;
}