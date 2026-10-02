<?php
if (!defined('TAWASUL_ROOT')) {
    define('TAWASUL_ROOT', realpath(__DIR__ . '/..'));
}
require_once TAWASUL_ROOT . '/src/Auth.php';

use Tos\Auth;

error_reporting(E_ALL);
ini_set('display_errors', 1);

$auth = new Auth($pdo, $session);
$user = $auth->user();
if (!$user) { header('Location: /tawasul-os/public/welcome.html'); exit; }

$preferredName = $user['preferredName'] ?? $user['firstName'] ?? 'User';
$surname = $user['surname'] ?? '';
$fullName = trim($preferredName . ' ' . $surname);
$personId = $user['person_id'];
$role = (int)trim($user['role_id_primary']);

$roleNames = [1 => 'Admin', 2 => 'Teacher', 3 => 'Student', 4 => 'Parent', 5 => 'Assistant'];
$roleName = $roleNames[$role] ?? 'User';

$totalPersons = $pdo->query("SELECT COUNT(*) FROM tos_person")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM tos_student_enrolment")->fetchColumn();
$totalModules = count(glob(TAWASUL_ROOT . '/modules/*', GLOB_ONLYDIR));

$initials = '';
foreach (array_slice(explode(' ', $preferredName), 0, 2) as $p) $initials .= mb_strtoupper(mb_substr($p, 0, 1));

$portalTitle = ($role == 3 ? 'portal الطالب' : ($role == 4 ? 'portal ولي Argument' : ($role == 2 ? 'portal المعلم' : ($role == 1 ? 'portalدير' : 'portal/system'))));
$portalSubtitle = ($role == 3 ? 'Student Portal' : ($role == 4 ? 'Parent Portal' : ($role == 2 ? 'Teacher Portal' : ($role == 1 ? 'Admin Portal' : 'System Portal'))));

$modules = [];
$moduleDir = TAWASUL_ROOT . '/modules';
if (is_dir($moduleDir)) {
    foreach (glob($moduleDir . '/*', GLOB_ONLYDIR) as $dir) {
        $name = basename($dir);
        $mf = $dir . '/manifest.php';
        if (file_exists($mf)) {
            $c = file_get_contents($mf);
            $m = [];
            if (preg_match("/\\\$name\s*=\s*['\"]([^'\"]*)['\"]/", $c, $mm)) $m['name'] = $mm[1]; else $m['name'] = $name;
            if (preg_match("/\\\$description\s*=\s*['\"]([^'\"]*)['\"]/", $c, $mm2)) $m['description'] = $mm2[1]; else $m['description'] = '';
            $modules[$name] = $m;
        }
    }
}
ksort($modules);

$settingsCount = 0;
try { $settingsCount = $pdo->query("SELECT COUNT(*) FROM tos_setting")->fetchColumn(); } catch (\Exception $e) {}

$permissionsCount = 0;
try { $permissionsCount = $pdo->query("SELECT COUNT(*) FROM tos_permission")->fetchColumn(); } catch (\Exception $e) {}

$schoolYearCount = 0;
try { $schoolYearCount = $pdo->query("SELECT COUNT(*) FROM tos_school_year")->fetchColumn(); } catch (\Exception $e) {}

$scaleGradesCount = 0;
try { $scaleGradesCount = $pdo->query("SELECT COUNT(*) FROM tos_scale_grade")->fetchColumn(); } catch (\Exception $e) {}

$alerts = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM tos_alert WHERE person_id = ? ORDER BY dateCreated DESC LIMIT 5");
    $stmt->execute([$personId]);
    $alerts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

$recentActivity = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM tos_planner_entry WHERE creator_person_id = ? ORDER BY dateCreated DESC LIMIT 5");
    $stmt->execute([$personId]);
    $recentActivity = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

$studentEnrolments = [];
try {
    $stmt = $pdo->prepare("SELECT se.*, sy.name as year_name FROM tos_student_enrolment se LEFT JOIN tos_school_year sy ON se.school_year_id = sy.school_year_id WHERE se.person_id = ? ORDER BY se.dateStart DESC LIMIT 5");
    $stmt->execute([$personId]);
    $studentEnrolments = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

$currentYear = '2025-2026';
try {
    $stmt = $pdo->query("SELECT name FROM tos_school_year ORDER BY school_year_id DESC LIMIT 1");
    $currentYear = $stmt->fetchColumn() ?: $currentYear;
} catch (\Exception $e) {}

$pageTitle = $portalTitle . ' - TawasulOS';
$schoolName = 'TawasulOS';
$schoolNameArabic = 'نظام المدرسة';

/* View inlined */
