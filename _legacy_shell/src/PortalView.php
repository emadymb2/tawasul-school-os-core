<?php
/**
 * TawasulOS Portal View — exact PHP port of emadymb1/tawasul-school-os-ui
 * Renders role-based dashboards matching the React reference design.
 */
$role = (int)trim($user['role_id_primary'] ?? 0);
$preferredName = $user['preferredName'] ?? $user['firstName'] ?? 'User';
$surname = $user['surname'] ?? '';
$fullName = trim($preferredName . ' ' . $surname);
$personId = $user['person_id'];
$roleNames = [1 => 'Admin', 2 => 'Teacher', 3 => 'Student', 4 => 'Parent', 5 => 'Assistant'];
$roleName = $roleNames[$role] ?? 'User';
$portalTitle = ($role == 3 ? 'portal الطالب' : ($role == 4 ? 'portal ولي Argument' : ($role == 2 ? 'portal المعلم' : ($role == 1 ? 'portalدير' : 'portal/system'))));
$portalSubtitle = ($role == 3 ? 'Student Portal' : ($role == 4 ? 'Parent Portal' : ($role == 2 ? 'Teacher Portal' : ($role == 1 ? 'Admin Portal' : 'System Portal'))));
$initials = '';
foreach (array_slice(explode(' ', $preferredName), 0, 2) as $p) $initials .= mb_strtoupper(mb_substr($p, 0, 1));
$schoolName = 'TawasulOS';
$schoolNameArabic = 'نظام المدرسة';

// Load modules
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

// Stats
$totalPersons = $pdo->query("SELECT COUNT(*) FROM tos_person")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM tos_student_enrolment")->fetchColumn();
$totalModules = count($modules);
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

// Render the view
echo '<!DOCTYPE html>';
echo '<html lang="ar" dir="rtl">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>' . htmlspecialchars($pageTitle) . '</title>';
echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
echo '<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@500;600;700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">';
echo '<link rel="stylesheet" href="/tawasul-os/public/assets/tawasul-design.css">';
echo '<script src="https://cdn.tailwindcss.com"></script>';
echo '</head>';
echo '<body class="min-h-screen bg-background text-foreground">';
echo '<div class="flex min-h-screen w-full bg-background">';

// Sidebar
echo '<aside class="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0 lg:left-0 lg:border-r lg:border-border lg:bg-card">';
echo '<div class="flex h-16 items-center gap-3 border-b border-border px-4">';
echo '<span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gold">';
echo '<span class="text-lg font-bold text-gold-foreground">T</span>';
echo '</span>';
echo '<div class="min-w-0">';
echo '<div class="font-extrabold leading-tight text-foreground">' . htmlspecialchars($schoolName) . '</div>';
echo '<div class="truncate text-[10px] tracking-[0.2em] text-muted-foreground">S.I.Y KOULU</div>';
echo '</div></div>';

$sidebarGroups = [
    ['label' => 'التدريس', 'items' => [
        ['title' => 'لوحة الموظف', 'url' => '/', 'icon' => 'layout'],
        ['title' => 'نظرة عامة', 'url' => '/', 'icon' => 'layout'],
        ['title' => 'جدول الدوام', 'url' => '/schedule', 'icon' => 'calendar'],
        ['title' => 'ال attendance', 'url' => '/attendance', 'icon' => 'clipboard'],
        ['title' => 'ال درجات', 'url' => '/assignments', 'icon' => 'book'],
        ['title' => 'مساعد التحضير', 'url' => '/prep', 'icon' => 'sparkles'],
    ]],
    ['label' => 'مدرسة', 'items' => [
        ['title' => 'الطلاب', 'url' => '/students', 'icon' => 'users'],
        ['title' => 'أولياء الأمور', 'url' => '/parents', 'icon' => 'users'],
        ['title' => 'الرسائل', 'url' => '/messages', 'icon' => 'messages'],
        ['title' => 'الإشعارات', 'url' => '/notifications', 'icon' => 'bell'],
    ]],
];
if ($role == 1) {
    $sidebarGroups[] = ['label' => 'الإدارة', 'items' => [
        ['title' => 'لوحة إدارة', 'url' => '/admin', 'icon' => 'graduation'],
        ['title' => 'الaccounts', 'url' => '/admin', 'icon' => 'receipt'],
        ['title' => 'الأدوار', 'url' => '/roles', 'icon' => 'shield'],
        ['title' => 'مصفوفة الصلاحيات', 'url' => '/permissions', 'icon' => 'shield'],
        ['title' => 'التواصل مع إدارة', 'url' => '/admin-contact', 'icon' => 'mail'],
    ]];
    $sidebarGroups[] = ['label' => 'أقسام النظام', 'items' => [
        ['title' => 'جميع الأقسام', 'url' => '/m', 'icon' => 'layout'],
    ]];
}

foreach ($sidebarGroups as $g) {
    echo '<div class="border-b border-border px-3 py-3">';
    echo '<div class="text-[11px] tracking-[0.2em] text-muted-foreground">' . htmlspecialchars($g['label']) . '</div>';
    echo '<div class="mt-2 space-y-1">';
    foreach ($g['items'] as $item) {
        echo '<a href="' . htmlspecialchars($item['url']) . '" class="flex items-center gap-3 rounded-full px-3 py-2 text-sm text-foreground hover:bg-accent">';
        echo '<span class="h-4 w-4"></span>';
        echo '<span>' . htmlspecialchars($item['title']) . '</span>';
        echo '</a>';
    }
    echo '</div></div>';
}

echo '<div class="mt-auto border-t border-border p-3">';
echo '<div class="rounded-2xl bg-accent p-3">';
echo '<div class="text-xs font-bold text-foreground">' . htmlspecialchars($fullName) . '</div>';
echo '<div class="mt-0.5 text-[11px] text-muted-foreground">' . htmlspecialchars($roleName) . '</div>';
echo '</div></div>';
echo '</aside>';

// Main content
echo '<div class="flex min-w-0 flex-1 flex-col lg:pl-64">';
echo '<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-border bg-background/95 px-4 backdrop-blur">';
echo '<span class="lg:hidden flex h-10 w-10 items-center justify-center rounded-full border border-border bg-card text-lg font-extrabold text-primary">T</span>';
echo '<div class="leading-tight">';
echo '<div class="text-[10px] font-semibold tracking-[0.2em] text-muted-foreground">' . htmlspecialchars($portalSubtitle) . '</div>';
echo '<div class="text-base font-extrabold text-foreground">' . htmlspecialchars($portalTitle) . '</div>';
echo '</div>';
echo '<div class="mr-auto flex items-center gap-2">';
echo '<span class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold" style="background:var(--gold);color:var(--gold-foreground)">' . htmlspecialchars($initials) . '</span>';
echo '<a href="/tawasul-os/tawasul.php?logout=1" class="text-sm rounded-full px-4 py-2 font-bold hover:opacity-90" style="background:var(--coral);color:var(--coral-foreground)">Logout</a>';
echo '</div></header>';

echo '<div class="bg-accent px-4 py-2 text-center text-xs font-semibold text-accent-foreground">بيانات تجريبية —你可以 navigate between portals</div>';
echo '<main class="flex-1 p-4 sm:p-6 lg:p-8">';

// Role-based content
if ($role == 3) {
    require TAWASUL_ROOT . '/src/views/student-dashboard.php';
} elseif ($role == 4) {
    require TAWASUL_ROOT . '/src/views/parent-dashboard.php';
} elseif ($role == 1) {
    require TAWASUL_ROOT . '/src/views/admin-dashboard.php';
} else {
    require TAWASUL_ROOT . '/src/views/teacher-dashboard.php';
}

echo '</main>';
echo '</div>';
echo '</div>';
echo '</body>';
echo '</html>';
exit;
