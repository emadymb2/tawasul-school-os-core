<?php
$preferredName = $user['preferredName'] ?? $user['firstName'] ?? 'Student';
$personId = $user['person_id'];
$preferredNameParts = explode(' ', $preferredName);
$studentInitials = '';
foreach (array_slice($preferredNameParts, 0, 2) as $p) $studentInitials .= mb_strtoupper(mb_substr($p, 0, 1));

// Get student enrolment data
$grade = 'Fifth';
$section = 'A';
try {
    $stmt = $pdo->prepare("SELECT se.*, sy.name as year_name, yg.name as year_group_name, fg.name as form_group_name FROM tos_student_enrolment se LEFT JOIN tos_school_year sy ON se.school_year_id = sy.school_year_id LEFT JOIN tos_year_group yg ON se.year_group_id = yg.year_group_id LEFT JOIN tos_form_group fg ON se.form_group_id = fg.form_group_id WHERE se.person_id = ? ORDER BY se.dateStart DESC LIMIT 1");
    $stmt->execute([$personId]);
    $studentData = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($studentData) {
        $grade = $studentData['year_group_name'] ?? $grade;
        $section = $studentData['form_group_name'] ?? $section;
    }
} catch (\Exception $e) {}

// Get attendance data
$present = 0; $total = 0;
$attendanceRecords = [];
try {
    $stmt = $pdo->prepare("SELECT alp.*, ac.name as code_name, ac.nameShort as code_short FROM tos_attendance_log_person alp LEFT JOIN tos_attendance_code ac ON alp.attendance_code_id = ac.attendance_code_id WHERE alp.person_id = ? ORDER BY alp.date DESC LIMIT 20");
    $stmt->execute([$personId]);
    $attendanceRecords = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($attendanceRecords as $a) {
        $total++;
        $code = strtolower($a['code_name'] ?? '');
        if (strpos($code, 'present') !== false && strpos($code, 'late') === false) $present++;
    }
} catch (\Exception $e) {}
$attendanceRate = $total ? round(($present / $total) * 100) : 0;

// Get grades
$grades = [];
$score = 0; $max = 0;
try {
    $stmt = $pdo->prepare("SELECT mbe.*, mbc.name as column_name, mbc.type as column_type FROM tos_markbook_entry mbe LEFT JOIN tos_markbook_column mbc ON mbe.markbook_column_id = mbc.markbook_column_id WHERE mbe.person_id_student = ? ORDER BY mbc.date DESC LIMIT 10");
    $stmt->execute([$personId]);
    $grades = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($grades as $g) {
        $val = floatval($g['attainmentValue'] ?? 0);
        $valMax = floatval($g['attainmentRawMax'] ?? 100);
        $score += $val;
        $max += $valMax;
    }
} catch (\Exception $e) {}
$average = $max ? round(($score / $max) * 100) : 0;

// Get today's lessons from planner
$todayLessons = [];
try {
    $stmt = $pdo->prepare("SELECT pe.*, cc.name as course_name FROM tos_planner_entry pe LEFT JOIN tos_course_class cc ON pe.course_class_id = cc.course_class_id WHERE pe.date = CURDATE() ORDER BY pe.timeStart LIMIT 4");
    $stmt->execute();
    $todayLessons = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// Fallback sample lessons if no real data
if (empty($todayLessons)) {
    $todayLessons = [
        ['time' => '08:00', 'subject' => 'القرآن الكريم', 'group' => 'الصف Fifth · أ', 'room' => 'قاعة ٢٠٤', 'tone' => 'coral'],
        ['time' => '10:00', 'subject' => 'اللغة العربية', 'group' => 'الصف Fifth · أ', 'room' => 'قاعة ٢٠٤', 'tone' => 'leaf'],
        ['time' => '11:30', 'subject' => 'التربية الإسلامية', 'group' => 'الصف Sixth · أ', 'room' => 'قاعة ١١٨', 'tone' => 'mint'],
        ['time' => '12:30', 'subject' => 'التلاوة والتجويد', 'group' => 'الصف Sixth · أ', 'room' => 'قاعة ١١٨', 'tone' => 'gold'],
    ];
}

// Get assignments
$assignments = [];
try {
    $stmt = $pdo->prepare("SELECT pe.*, peh.homeworkDetails, peh.homeworkDueDateTime FROM tos_planner_entry pe LEFT JOIN tos_planner_entry_homework peh ON pe.planner_entry_id = peh.planner_entry_id WHERE pe.homework = 'Y' ORDER BY pe.date DESC LIMIT 4");
    $stmt->execute();
    $assignments = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// School notices (alerts for this student)
$schoolNotices = [];
try {
    $stmt = $pdo->prepare("SELECT al.*, alv.name as level_name FROM tos_alert al LEFT JOIN tos_alert_level alv ON al.alert_level_id = alv.alert_level_id WHERE al.person_id = ? ORDER BY al.timestampCreated DESC LIMIT 4");
    $stmt->execute([$personId]);
    $schoolNotices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

function gradeLetter($pct) {
    if ($pct >= 85) return 'A';
    if ($pct >= 70) return 'B';
    if ($pct >= 55) return 'C';
    return 'D';
}

// Render HTML
echo '<div class="mx-auto max-w-3xl space-y-4">';
echo '<div><div class="text-[11px] font-bold tracking-[0.2em] text-muted-foreground">مدرسة</div><h1 class="text-3xl font-extrabold text-foreground"> portals الطالب</h1></div>';

echo '<div class="relative overflow-hidden rounded-[2rem] bg-primary p-6 text-primary-foreground">';
echo '<div class="pointer-events-none absolute -bottom-12 -left-12 h-40 w-40 rounded-full bg-coral"></div>';
echo '<div class="pointer-events-none absolute -top-8 left-14 h-20 w-20 rounded-full bg-gold/80"></div>';
echo '<div class="relative z-10"><div class="text-sm text-primary-foreground/70">أهلاً،</div>';
echo '<div class="mt-1 text-2xl font-extrabold">' . htmlspecialchars($preferredName) . '</div>';
echo '<div class="mt-1 text-sm text-primary-foreground/70">الصف ' . htmlspecialchars($grade) . ' · ' . htmlspecialchars($section) . '</div></div></div>';

echo '<div class="grid grid-cols-2 gap-3">';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--mint);color:var(--mint-foreground)"><div class="text-sm font-medium opacity-80">نسبة الحضور</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . $attendanceRate . '</span><span class="pb-1 text-lg font-bold opacity-80">%</span></div></div>';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--sage);color:var(--sage-foreground)"><div class="text-sm font-medium opacity-80">متوسط الدرجات</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . $average . '</span></div></div>';
echo '</div>';

echo '<div class="tile overflow-x-auto p-0 rounded-[1.75rem]"><table class="w-full min-w-[620px] text-right text-sm"><thead><tr class="border-b border-border/60 text-xs text-muted-foreground"><th class="px-4 py-3 font-bold">الوقت</th><th class="px-4 py-3 font-bold">المادة</th><th class="px-4 py-3 font-bold">الصف</th><th class="px-4 py-3 font-bold">القاعة</th></tr></thead><tbody>';
foreach ($todayLessons as $l) {
    $tone = in_array($l['tone'] ?? '', ['coral','leaf','mint','gold']) ? $l['tone'] : 'coral';
    echo '<tr class="border-b border-border/40 last:border-0"><td class="px-4 py-3 align-middle"><span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold" style="background:var(--' . $tone . ');color:var(--' . $tone . '-foreground)">' . htmlspecialchars($l['time'] ?? '') . '</span></td><td class="px-4 py-3 align-middle">' . htmlspecialchars($l['subject'] ?? '') . '</td><td class="px-4 py-3 align-middle">' . htmlspecialchars($l['group'] ?? '') . '</td><td class="px-4 py-3 align-middle">' . htmlspecialchars($l['room'] ?? '') . '</td></tr>';
}
echo '</tbody></table></div>';

echo '<div class="tile rounded-[1.75rem] bg-card p-5 shadow-sm"><div class="mb-4 flex items-center justify-between gap-3"><h2 class="text-lg font-extrabold text-foreground">واجباتي</h2><a href="#" class="text-xs font-bold text-primary">عرض الكل</a></div><ul class="space-y-3">';
if (!empty($assignments)) {
    foreach (array_slice($assignments, 0, 4) as $a) {
        echo '<li class="flex items-center justify-between gap-3"><div class="min-w-0"><div class="truncate text-sm font-bold">' . htmlspecialchars($a['name'] ?? '') . '</div><div class="text-[11px] text-muted-foreground">' . htmlspecialchars($a['homeworkDueDateTime'] ?? $a['date'] ?? '') . '</div></div><span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold" style="background:var(--coral);color:var(--coral-foreground)">متأخر</span></li>';
    }
} else {
    echo '<p class="text-sm text-muted-foreground">لا توجد واجبات معلقة.</p>';
}
echo '</ul></div>';

echo '<div class="tile rounded-[1.75rem] bg-card p-5 shadow-sm"><h2 class="mb-4 text-lg font-extrabold text-foreground">سجل الدرجات</h2><ul class="space-y-3">';
if (!empty($grades)) {
    foreach (array_slice($grades, 0, 6) as $g) {
        $val = floatval($g['attainmentValue'] ?? 0);
        $valMax = floatval($g['attainmentRawMax'] ?? 100);
        $pct = $valMax > 0 ? round(($val / $valMax) * 100) : 0;
        echo '<li class="flex items-center justify-between gap-3"><span class="text-sm">' . htmlspecialchars($g['column_name'] ?? 'Subject') . '</span><span class="flex items-center gap-2"><span class="text-base font-extrabold">' . $pct . '</span><span class="flex h-8 w-8 items-center justify-center rounded-full bg-leaf text-xs font-bold text-leaf-foreground">' . gradeLetter($pct) . '</span></span></li>';
    }
} else {
    echo '<p class="text-sm text-muted-foreground">لا توجد درجات مسجّلة.</p>';
}
echo '</ul></div>';

echo '<div class="tile rounded-[1.75rem] bg-card p-5 shadow-sm"><h2 class="mb-4 text-lg font-extrabold text-foreground">attendance</h2><ul class="space-y-2">';
if (!empty($attendanceRecords)) {
    foreach (array_slice($attendanceRecords, 0, 6) as $a) {
        $code = strtolower($a['code_name'] ?? '');
        $date = $a['date'] ?? '';
        $comment = $a['comment'] ?? '';
        echo '<li class="flex items-center justify-between gap-3"><div><div class="text-sm font-bold">' . htmlspecialchars($date) . '</div><div class="text-[11px] text-muted-foreground">' . htmlspecialchars($comment) . '</div></div>';
        if (strpos($code, 'present') !== false && strpos($code, 'late') === false) echo '<span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold" style="background:var(--leaf);color:var(--leaf-foreground)">Absent</span>';
        else if (strpos($code, 'late') !== false) echo '<span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold" style="background:var(--gold);color:var(--gold-foreground)">late</span>';
        else echo '<span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold" style="background:var(--coral);color:var(--coral-foreground)">Absent</span>';
        echo '</li>';
    }
} else {
    echo '<p class="text-sm text-muted-foreground">لا توجد سجلات حضور.</p>';
}
echo '</ul></div>';

echo '<div class="tile rounded-[1.75rem] bg-card p-5 shadow-sm"><h2 class="mb-4 text-lg font-extrabold text-foreground">معلمي</h2><div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-full text-xs font-bold" style="background:var(--gold);color:var(--gold-foreground)">' . htmlspecialchars($studentInitials) . '</span><div><div class="text-sm font-bold">ولي Argument: ' . htmlspecialchars($preferredName) . '</div><div class="text-[11px] text-muted-foreground">System</div></div></div></div>';
echo '</div>';
