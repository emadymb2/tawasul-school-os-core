<?php
$preferredName = $user['preferredName'] ?? $user['firstName'] ?? 'Admin';
$personId = $user['person_id'];
$preferredNameParts = explode(' ', $preferredName);
$adminInitials = '';
foreach (array_slice($preferredNameParts, 0, 2) as $p) $adminInitials .= mb_strtoupper(mb_substr($p, 0, 1));
$todayLabel = (new DateTime())->format('Y-m-d');

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

// Real stats from database
$totalPersons = $pdo->query("SELECT COUNT(*) FROM tos_person")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM tos_student_enrolment")->fetchColumn();
$settingsCount = $pdo->query("SELECT COUNT(*) FROM tos_setting")->fetchColumn();
$permissionsCount = $pdo->query("SELECT COUNT(*) FROM tos_permission")->fetchColumn();
$schoolYearCount = $pdo->query("SELECT COUNT(*) FROM tos_school_year")->fetchColumn();
$scaleGradesCount = $pdo->query("SELECT COUNT(*) FROM tos_scale_grade")->fetchColumn();

// Attendance rate
$attendanceRate = 0;
try {
    $present = $pdo->query("SELECT COUNT(*) FROM tos_attendance_log_person alp LEFT JOIN tos_attendance_code ac ON alp.attendance_code_id = ac.attendance_code_id WHERE ac.name LIKE 'Present%' AND ac.name NOT LIKE '%Late%'")->fetchColumn();
    $total = $pdo->query("SELECT COUNT(*) FROM tos_attendance_log_person")->fetchColumn();
    $attendanceRate = $total ? round(($present / $total) * 100) : 0;
} catch (\Exception $e) {}

// Average grade
$average = 0;
try {
    $score = $pdo->query("SELECT SUM(attainmentValue) FROM tos_markbook_entry WHERE attainmentValue IS NOT NULL")->fetchColumn() ?? 0;
    $max = $pdo->query("SELECT SUM(attainmentRawMax) FROM tos_markbook_entry WHERE attainmentRawMax IS NOT NULL")->fetchColumn() ?? 0;
    $average = $max ? round(($score / $max) * 100) : 0;
} catch (\Exception $e) {}

// School notices (alerts)
$schoolNotices = [];
try {
    $stmt = $pdo->query("SELECT al.*, alv.name as level_name FROM tos_alert al LEFT JOIN tos_alert_level alv ON al.alert_level_id = alv.alert_level_id ORDER BY al.timestampCreated DESC LIMIT 4");
    $schoolNotices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// Fallback notices if empty
if (empty($schoolNotices)) {
    $schoolNotices = [
        ['title' => 'اجتماع أولياء الأمور يوم السبت', 'meta' => 'فعالية · 2026-09-19', 'isNew' => true, 'type' => 'اجتماع', 'timestampCreated' => '2026-09-19'],
        ['title' => ' اختبار القرآن الكريم للصف الخامس', 'meta' => ' اختبار · 2026-09-22', 'isNew' => false, 'type' => ' اختبار', 'timestampCreated' => '2026-09-22'],
        ['title' => 'إجازة المولد النبوي', 'meta' => 'إجازة · 2026-09-26', 'isNew' => false, 'type' => 'إجازة', 'timestampCreated' => '2026-09-26'],
        ['title' => 'رحة مدرسية إلى المكتبة العامة', 'meta' => 'فعالية · 2026-10-02', 'isNew' => false, 'type' => 'فعالية', 'timestampCreated' => '2026-10-02'],
    ];
}

echo '<div class="mx-auto max-w-6xl space-y-6">';
echo '<div class="relative overflow-hidden rounded-[2rem] bg-primary p-6 text-primary-foreground sm:p-8">';
echo '<div class="pointer-events-none absolute -top-16 left-1/4 h-44 w-44 rounded-full bg-coral sm:left-1/3"></div>';
echo '<div class="pointer-events-none absolute bottom-4 left-8 h-24 w-24 rounded-full bg-gold sm:bottom-6 sm:left-24"></div>';
echo '<div class="relative z-10 max-w-xl">';
echo '<span class="inline-flex rounded-full bg-primary-foreground/10 px-3 py-1 text-[11px] font-semibold tracking-widest text-primary-foreground/80">' . htmlspecialchars($todayLabel) . '</span>';
echo '<h1 class="mt-4 text-3xl font-extrabold leading-tight sm:text-4xl">نظرة عامة على <span class="text-gold">المدرسة</span></h1>';
echo '<p class="mt-3 text-sm text-primary-foreground/75 sm:text-lg">الطلاب، الحضور، الحسابات والصوليات — كل ما يحتاجه إدارة في مكان واحد.</p>';
echo '<div class="mt-6 flex flex-wrap items-center gap-3">';
echo '<a href="#" class="rounded-full bg-coral px-6 py-3 text-sm font-bold text-coral-foreground">لوحة إدارة</a>';
echo '<a href="#" class="rounded-full border border-primary-foreground/25 px-6 py-3 text-sm font-bold text-primary-foreground">الأدوار والصوليات</a>';
echo '</div></div>';

echo '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--gold);color:var(--gold-foreground)"><div class="text-sm font-medium opacity-80">عددembedding</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . $totalStudents . '</span></div><div class="mt-auto pt-4 text-xs opacity-70">طلاب</div></div>';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--mint);color:var(--mint-foreground)"><div class="text-sm font-medium opacity-80">نسبة الحضور</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . $attendanceRate . '</span><span class="pb-1 text-lg font-bold opacity-80">%</span></div><div class="mt-auto pt-4 text-xs opacity-70">开凿</div></div>';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--sage);color:var(--sage-foreground)"><div class="text-sm font-medium opacity-80">متوسط الدرجات</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . $average . '</span></div><div class="mt-auto pt-4 text-xs opacity-70">开凿</div></div>';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--coral);color:var(--coral-foreground)"><div class="text-sm font-medium opacity-80">الaccounts</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . $settingsCount . '</span></div><div class="mt-auto pt-4 text-xs opacity-70">الفواتير والرسوم</div></div>';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--leaf);color:var(--leaf-foreground)"><div class="text-sm font-medium opacity-80">ال smoothing</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . $permissionsCount . '</span></div><div class="mt-auto pt-4 text-xs opacity-70">الأدوار</div></div>';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--mint);color:var(--mint-foreground)"><div class="text-sm font-medium opacity-80">إشعارات غير مقروءة</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">١</span></div><div class="mt-auto pt-4 text-xs opacity-70">开凿</div></div>';
echo '</div>';

echo '<div class="tile rounded-[1.75rem] bg-card p-5 shadow-sm"><h2 class="mb-4 text-lg font-extrabold text-foreground">إشعارات المدرسة</h2><ul class="divide-y divide-border rounded-3xl border border-border">';
foreach ($schoolNotices as $n) {
    $isNew = ($n['isNew'] ?? false);
    echo '<li class="flex items-start gap-3 p-4"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" style="background:' . ($isNew ? 'var(--coral)' : 'var(--muted-foreground)/30') . '"></span><div><div class="text-sm font-bold">' . htmlspecialchars($n['title'] ?? $n['type'] ?? '') . '</div><div class="text-[11px] text-muted-foreground">' . htmlspecialchars($n['meta'] ?? $n['timestampCreated'] ?? '') . '</div></div></li>';
}
echo '</ul></div>';
echo '</div>';
