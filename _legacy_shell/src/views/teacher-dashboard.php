<?php
$preferredName = $user['preferredName'] ?? $user['firstName'] ?? 'Teacher';
$personId = $user['person_id'];
$preferredNameParts = explode(' ', $preferredName);
$teacherInitials = '';
foreach (array_slice($preferredNameParts, 0, 2) as $p) $teacherInitials .= mb_strtoupper(mb_substr($p, 0, 1));
$todayLabel = (new DateTime())->format('Y-m-d');

// Get today's lessons from planner
$todayLessons = [];
try {
    $stmt = $pdo->prepare("SELECT pe.*, cc.name as course_name, cc.nameShort as course_short FROM tos_planner_entry pe LEFT JOIN tos_course_class cc ON pe.course_class_id = cc.course_class_id WHERE pe.date = CURDATE() ORDER BY pe.timeStart LIMIT 4");
    $stmt->execute();
    $todayLessons = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// Fallback sample lessons if no real data for today
if (empty($todayLessons)) {
    $todayLessons = [
        ['time' => '08:00', 'subject' => 'القرآن الكريم', 'group' => 'الصف Fifth · أ', 'room' => 'قاعة ٢٠٤', 'tone' => 'coral'],
        ['time' => '10:00', 'subject' => 'اللغة العربية', 'group' => 'الصف Fifth · أ', 'room' => 'قاعة ٢٠٤', 'tone' => 'leaf'],
        ['time' => '11:30', 'subject' => 'التربية الإسلامية', 'group' => 'الصف Sixth · أ', 'room' => 'قاعة ١١٨', 'tone' => 'mint'],
        ['time' => '12:30', 'subject' => 'التلاوة والتجويد', 'group' => 'الصف Sixth · أ', 'room' => 'قاعة ١١٨', 'tone' => 'gold'],
    ];
}

// Get students (from person table with role 3 = student)
$students = [];
try {
    $stmt = $pdo->query("SELECT person_id, preferredName, surname FROM tos_person WHERE role_id_primary = '003' ORDER BY preferredName LIMIT 5");
    $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// Get attendance stats
$attendanceRate = 0;
$presentCount = 0; $totalCount = 0;
try {
    $stmt = $pdo->query("SELECT alp.*, ac.name as code_name FROM tos_attendance_log_person alp LEFT JOIN tos_attendance_code ac ON alp.attendance_code_id = ac.attendance_code_id ORDER BY alp.date DESC LIMIT 100");
    $allAttendance = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($allAttendance as $a) {
        $totalCount++;
        $code = strtolower($a['code_name'] ?? '');
        if (strpos($code, 'present') !== false && strpos($code, 'late') === false) $presentCount++;
    }
    $attendanceRate = $totalCount ? round(($presentCount / $totalCount) * 100) : 0;
} catch (\Exception $e) {}

// Get average grade
$average = 0;
try {
    $stmt = $pdo->query("SELECT mbe.*, mbc.name as column_name FROM tos_markbook_entry mbe LEFT JOIN tos_markbook_column mbc ON mbe.markbook_column_id = mbc.markbook_column_id LIMIT 50");
    $allGrades = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $score = 0; $max = 0;
    foreach ($allGrades as $g) {
        $val = floatval($g['attainmentValue'] ?? 0);
        $valMax = floatval($g['attainmentRawMax'] ?? 100);
        $score += $val; $max += $valMax;
    }
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
echo '<div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">';
echo '<div class="relative overflow-hidden rounded-[2rem] bg-primary p-6 text-primary-foreground sm:p-8">';
echo '<div class="pointer-events-none absolute -top-16 left-1/4 h-44 w-44 rounded-full bg-coral sm:left-1/3"></div>';
echo '<div class="pointer-events-none absolute bottom-4 left-8 h-24 w-24 rounded-full bg-gold sm:bottom-6 sm:left-24"></div>';
echo '<div class="relative z-10 max-w-xl">';
echo '<span class="inline-flex rounded-full bg-primary-foreground/10 px-3 py-1 text-[11px] font-semibold tracking-widest text-primary-foreground/80">' . htmlspecialchars($todayLabel) . '</span>';
echo '<h1 class="mt-4 text-3xl font-extrabold leading-tight sm:text-4xl">السلام عليكم يا <span class="text-gold">' . htmlspecialchars($preferredName) . '</span></h1>';
echo '<p class="mt-3 text-sm text-primary-foreground/75 sm:text-base">لديك ' . count($todayLessons) . ' حصص اليوم و' . count($students) . ' طالباً في صفوفك.</p>';
echo '<div class="mt-6 flex flex-wrap items-center gap-3">';
echo '<a href="#" class="rounded-full bg-coral px-6 py-3 text-sm font-bold text-coral-foreground">سجل الحضور</a>';
echo '<a href="#" class="rounded-full border border-primary-foreground/25 px-6 py-3 text-sm font-bold text-primary-foreground">فتح سجل الدرجات</a>';
echo '</div></div>';

echo '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--mint);color:var(--mint-foreground)"><div class="text-sm font-medium opacity-80">نسبة الحضور</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . $attendanceRate . '</span><span class="pb-1 text-lg font-bold opacity-80">%</span></div><div class="mt-auto pt-4 text-xs opacity-70">فتح</div></div>';
echo '<div class="grid grid-cols-2 gap-4"><div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--gold);color:var(--gold-foreground)"><div class="text-sm font-medium opacity-80">حصص اليوم</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . count($todayLessons) . '</span></div></div><div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--sage);color:var(--sage-foreground)"><div class="text-sm font-medium opacity-80">متوسط الدرجات</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . $average . '</span></div></div></div>';
echo '</div></div>';

echo '<div class="grid gap-4 lg:grid-cols-2">';
echo '<div class="tile rounded-[1.75rem] bg-card p-5 shadow-sm"><div class="mb-4 flex items-center justify-between gap-3"><h2 class="text-lg font-extrabold text-foreground">جدول اليوم</h2><a href="#" class="rounded-full bg-secondary px-4 py-2 text-xs font-bold">عرض الأسبوع</a></div><ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">';
foreach ($todayLessons as $l) {
    $tone = in_array($l['tone'] ?? '', ['coral','leaf','mint','gold']) ? $l['tone'] : 'coral';
    echo '<li class="rounded-3xl border border-border bg-background p-4"><div class="flex items-center justify-between"><span class="text-sm font-bold text-muted-foreground">' . htmlspecialchars($l['timeStart'] ?? $l['time'] ?? '') . '</span><span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold" style="background:var(--' . $tone . ');color:var(--' . $tone . '-foreground)">' . htmlspecialchars($l['room'] ?? 'قاعة') . '</span></div><div class="mt-3 text-lg font-extrabold text-foreground">' . htmlspecialchars($l['subject'] ?? $l['name'] ?? '') . '</div><div class="mt-1 text-xs text-muted-foreground">' . htmlspecialchars($l['group'] ?? $l['course_name'] ?? '') . '</div></li>';
}
echo '</ul></div>';

echo '<div class="tile rounded-[1.75rem] bg-card p-5 shadow-sm"><h2 class="mb-4 text-lg font-extrabold text-foreground">إشعارات المدرسة</h2><ul class="divide-y divide-border rounded-3xl border border-border">';
foreach ($schoolNotices as $n) {
    $isNew = ($n['isNew'] ?? false);
    echo '<li class="flex items-start gap-3 p-4"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" style="background:' . ($isNew ? 'var(--coral)' : 'var(--muted-foreground)/30') . '"></span><div><div class="text-sm font-bold">' . htmlspecialchars($n['title'] ?? $n['type'] ?? '') . '</div><div class="text-[11px] text-muted-foreground">' . htmlspecialchars($n['meta'] ?? $n['timestampCreated'] ?? '') . '</div></div></li>';
}
echo '</ul><div class="mt-5 flex items-center gap-3"><span class="text-xs font-bold text-muted-foreground">متوسط الصف</span><div class="h-2 flex-1 overflow-hidden rounded-full bg-secondary"><div class="h-full rounded-full bg-coral" style="width:' . $average . '%"></div></div><span class="text-sm font-extrabold">' . $average . '</span></div></div>';
echo '</div>';

echo '<div class="tile rounded-[1.75rem] bg-card p-5 shadow-sm"><h2 class="mb-4 text-lg font-extrabold text-foreground">pler</h2><ul class="space-y-3">';
foreach ($students as $s) {
    $sName = trim(($s['preferredName'] ?? '') . ' ' . ($s['surname'] ?? ''));
    $sInitials = '';
    $sParts = explode(' ', $sName);
    foreach (array_slice($sParts, 0, 2) as $p) $sInitials .= mb_strtoupper(mb_substr($p, 0, 1));
    echo '<li class="flex items-center gap-3 rounded-3xl bg-background px-3 py-2.5"><span class="flex h-10 w-10 items-center justify-center rounded-full text-xs font-bold" style="background:var(--gold);color:var(--gold-foreground)">' . $sInitials . '</span><div class="min-w-0 flex-1"><div class="truncate text-sm font-bold">' . htmlspecialchars($sName) . '</div><div class="text-[11px] text-muted-foreground">الصف ٥ · أ</div></div><div class="flex gap-1.5"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-leaf text-xs font-bold text-leaf-foreground">ح</span><span class="flex h-8 w-8 items-center justify-center rounded-full bg-secondary text-xs font-bold text-secondary-foreground">غ</span></div></li>';
}
if (empty($students)) echo '<p class="text-sm text-muted-foreground">لا توجد طلاب.</p>';
echo '</ul><a href="#" class="mt-4 block rounded-full bg-primary py-3 text-center text-sm font-bold text-primary-foreground">حفظ الحضور</a></div>';
echo '</div>';
