<?php
$preferredName = $user['preferredName'] ?? $user['firstName'] ?? 'Parent';
$personId = $user['person_id'];
$preferredNameParts = explode(' ', $preferredName);
$parentInitials = '';
foreach (array_slice($preferredNameParts, 0, 2) as $p) $parentInitials .= mb_strtoupper(mb_substr($p, 0, 1));

// Get children linked via family
$children = [];
try {
    $stmt = $pdo->prepare("SELECT DISTINCT p.person_id, p.preferredName, p.surname FROM tos_family_adult fa LEFT JOIN tos_family_child fc ON fa.family_id = fc.family_id LEFT JOIN tos_person p ON fc.person_id = p.person_id WHERE fa.person_id = ? AND fc.person_id IS NOT NULL");
    $stmt->execute([$personId]);
    $children = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// If no children found, use current user as child
if (empty($children)) {
    $children = [['person_id' => $personId, 'preferredName' => $preferredName, 'surname' => $user['surname'] ?? '']];
}

$activeChild = $children[0];
$childId = $activeChild['person_id'];
$childName = trim(($activeChild['preferredName'] ?? '') . ' ' . ($activeChild['surname'] ?? ''));

// Get child enrolment
$grade = 'Fifth';
$section = 'A';
try {
    $stmt = $pdo->prepare("SELECT se.*, sy.name as year_name, yg.name as year_group_name, fg.name as form_group_name FROM tos_student_enrolment se LEFT JOIN tos_school_year sy ON se.school_year_id = sy.school_year_id LEFT JOIN tos_year_group yg ON se.year_group_id = yg.year_group_id LEFT JOIN tos_form_group fg ON se.form_group_id = fg.form_group_id WHERE se.person_id = ? ORDER BY se.dateStart DESC LIMIT 1");
    $stmt->execute([$childId]);
    $childData = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($childData) { $grade = $childData['year_group_name'] ?? $grade; $section = $childData['form_group_name'] ?? $section; }
} catch (\Exception $e) {}

// Get child attendance
$present = 0; $total = 0;
try {
    $stmt = $pdo->prepare("SELECT alp.*, ac.name as code_name FROM tos_attendance_log_person alp LEFT JOIN tos_attendance_code ac ON alp.attendance_code_id = ac.attendance_code_id WHERE alp.person_id = ? ORDER BY alp.date DESC LIMIT 20");
    $stmt->execute([$childId]);
    $attendance = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($attendance as $a) { $total++; $code = strtolower($a['code_name'] ?? ''); if (strpos($code, 'present') !== false && strpos($code, 'late') === false) $present++; }
} catch (\Exception $e) {}
$attendanceRate = $total ? round(($present / $total) * 100) : 0;

// Get child grades
$grades = [];
$score = 0; $max = 0;
try {
    $stmt = $pdo->prepare("SELECT mbe.*, mbc.name as column_name FROM tos_markbook_entry mbe LEFT JOIN tos_markbook_column mbc ON mbe.markbook_column_id = mbc.markbook_column_id WHERE mbe.person_id_student = ? ORDER BY mbc.date DESC LIMIT 10");
    $stmt->execute([$childId]);
    $grades = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($grades as $g) { $val = floatval($g['attainmentValue'] ?? 0); $valMax = floatval($g['attainmentRawMax'] ?? 100); $score += $val; $max += $valMax; }
} catch (\Exception $e) {}
$average = $max ? round(($score / $max) * 100) : 0;

// Get invoices
$invoices = [];
$pendingAmount = 0;
try {
    $stmt = $pdo->prepare("SELECT fi.*, fi.invoiceIssueDate, fi.invoiceDueDate, fi.paidAmount, fi.status FROM tos_finance_invoice fi WHERE fi.invoiceTo = 'Family' AND fi.person_id_creator = ? ORDER BY fi.invoiceIssueDate DESC LIMIT 5");
    $stmt->execute([$personId]);
    $invoices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($invoices as $inv) { if ($inv['status'] == 'Pending') $pendingAmount++; }
} catch (\Exception $e) {}

// Today's lessons
$todayLessons = [
    ['time' => '08:00', 'subject' => 'القرآن الكريم', 'group' => 'الصف Fifth · أ', 'room' => 'قاعة ٢٠٤', 'tone' => 'coral'],
    ['time' => '10:00', 'subject' => 'اللغة العربية', 'group' => 'الصف Fifth · أ', 'room' => 'قاعة ٢٠٤', 'tone' => 'leaf'],
    ['time' => '11:30', 'subject' => 'التربية الإسلامية', 'group' => 'الصف Sixth · أ', 'room' => 'قاعة ١١٨', 'tone' => 'mint'],
    ['time' => '12:30', 'subject' => 'التلاوة والتجويد', 'group' => 'الصف Sixth · أ', 'room' => 'قاعة ١١٨', 'tone' => 'gold'],
];

// School notices (alerts for this child)
$schoolNotices = [];
try {
    $stmt = $pdo->prepare("SELECT al.*, alv.name as level_name FROM tos_alert al LEFT JOIN tos_alert_level alv ON al.alert_level_id = alv.alert_level_id WHERE al.person_id = ? ORDER BY al.timestampCreated DESC LIMIT 4");
    $stmt->execute([$childId]);
    $schoolNotices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

echo '<div class="mx-auto max-w-3xl space-y-4">';
echo '<div><div class="text-[11px] font-bold tracking-[0.2em] text-muted-foreground">أبنائي</div><h1 class="text-3xl font-extrabold text-foreground"> portals ولي Argument</h1></div>';

echo '<div class="flex flex-wrap gap-2">';
foreach ($children as $c) {
    $cName = trim(($c['preferredName'] ?? '') . ' ' . ($c['surname'] ?? ''));
    $isActive = ($c['person_id'] == $activeChild['person_id']) ? 'bg-primary text-primary-foreground' : 'border border-border bg-card text-foreground';
    echo '<button type="button" class="rounded-full px-5 py-2.5 text-sm font-bold transition-colors ' . $isActive . '">' . htmlspecialchars($cName) . '</button>';
}
echo '</div>';

echo '<div class="relative overflow-hidden rounded-[2.5rem] bg-primary p-6 text-primary-foreground">';
echo '<div class="pointer-events-none absolute -bottom-10 -left-12 h-44 w-44 rounded-full bg-coral"></div>';
echo '<div class="pointer-events-none absolute -top-8 left-16 h-24 w-24 rounded-full bg-gold/80"></div>';
echo '<div class="relative z-10 flex items-center gap-4">';
echo '<span class="flex h-16 w-16 items-center justify-center rounded-full bg-gold text-lg font-extrabold text-gold-foreground">' . htmlspecialchars($parentInitials) . '</span>';
echo '<div><div class="text-2xl font-extrabold">' . htmlspecialchars($childName) . '</div><div class="text-sm text-primary-foreground/70">الصف ' . htmlspecialchars($grade) . ' · ' . htmlspecialchars($section) . '</div></div>';
echo '</div></div>';

echo '<div class="grid grid-cols-2 gap-3">';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--mint);color:var(--mint-foreground)"><div class="text-sm font-medium opacity-80">نسبة الحضور</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . $attendanceRate . '</span><span class="pb-1 text-lg font-bold opacity-80">%</span></div></div>';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--sage);color:var(--sage-foreground)"><div class="text-sm font-medium opacity-80">متوسط الدرجات</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . $average . '</span></div></div>';
echo '</div>';

echo '<div class="grid grid-cols-2 gap-3">';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--coral);color:var(--coral-foreground)"><div class="text-sm font-medium opacity-80">الفواتير</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . $pendingAmount . '</span></div><div class="mt-auto pt-4 text-xs opacity-70">معلقة</div></div>';
echo '<div class="tile flex flex-col rounded-[1.75rem] p-5" style="background:var(--gold);color:var(--gold-foreground)"><div class="text-sm font-medium opacity-80">الإشعارات</div><div class="mt-2 flex items-end gap-1"><span class="text-4xl font-extrabold leading-none">' . count($schoolNotices) . '</span></div><div class="mt-auto pt-4 text-xs opacity-70">غير مقروءة</div></div>';
echo '</div>';

echo '<div class="tile rounded-[1.75rem] bg-card p-5 shadow-sm"><h2 class="mb-4 text-lg font-extrabold text-foreground">حصص اليوم</h2><ul class="space-y-3">';
foreach (array_slice($todayLessons, 0, 2) as $l) {
    echo '<li class="flex items-center gap-3"><span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold" style="background:var(--' . $l['tone'] . ');color:var(--' . $l['tone'] . '-foreground)">' . $l['time'] . '</span><div><div class="text-sm font-bold">' . $l['subject'] . '</div><div class="text-[11px] text-muted-foreground">' . $l['group'] . '</div></div></li>';
}
echo '</ul></div>';

echo '<div class="tile rounded-[1.75rem] bg-card p-5 shadow-sm"><div class="mb-4 flex items-center justify-between gap-3"><h2 class="text-lg font-extrabold text-foreground">الدرجات</h2><a href="#" class="text-xs font-bold text-primary">ملف الابن</a></div><ul class="space-y-3">';
if (!empty($grades)) {
    foreach (array_slice($grades, 0, 4) as $g) {
        $val = floatval($g['attainmentValue'] ?? 0); $valMax = floatval($g['attainmentRawMax'] ?? 100);
        $pct = $valMax > 0 ? round(($val / $valMax) * 100) : 0;
        echo '<li class="flex items-center justify-between gap-3"><span class="text-sm">' . htmlspecialchars($g['column_name'] ?? 'Subject') . '</span><span class="flex items-center gap-2"><span class="text-base font-extrabold">' . $pct . '</span><span class="flex h-8 w-8 items-center justify-center rounded-full bg-leaf text-xs font-bold text-leaf-foreground">A</span></span></li>';
    }
} else {
    echo '<p class="text-sm text-muted-foreground">لا توجد درجات.</p>';
}
echo '</ul></div>';

echo '<div class="tile rounded-[1.75rem] bg-card p-5 shadow-sm"><h2 class="mb-4 text-lg font-extrabold text-foreground">إشعارات المدرسة</h2><ul class="space-y-3">';
if (!empty($schoolNotices)) {
    foreach ($schoolNotices as $n) {
        echo '<li class="flex items-start gap-3"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" style="background:var(--coral)"></span><div><div class="text-sm font-bold">' . htmlspecialchars($n['type'] ?? 'تنبيه') . '</div><div class="text-[11px] text-muted-foreground">' . htmlspecialchars($n['timestampCreated'] ?? '') . '</div></div></li>';
    }
} else {
    echo '<p class="text-sm text-muted-foreground">لا توجد إشعارات جديدة.</p>';
}
echo '</ul></div>';

echo '<div class="tile rounded-[1.75rem] bg-card p-5 shadow-sm"><h2 class="mb-4 text-lg font-extrabold text-foreground">التواصل مع المدرسة</h2><div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-full text-xs font-bold" style="background:var(--gold);color:var(--gold-foreground)">' . htmlspecialchars($parentInitials) . '</span><div class="min-w-0 flex-1"><div class="text-sm font-bold">' . htmlspecialchars($preferredName) . '</div><div class="text-[11px] text-muted-foreground">' . htmlspecialchars($user['email'] ?? '') . '</div></div><a href="#" class="rounded-full bg-primary px-4 py-2 text-xs font-bold text-primary-foreground">مراسلة إدارة</a></div></div>';
echo '</div>';
