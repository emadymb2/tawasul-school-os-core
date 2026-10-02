<?php
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = ltrim($path, '/');
$segments = explode('/', $path);
$moduleSlug = $segments[2] ?? null;
$pageSlug = $segments[3] ?? null;

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

$mod = $modules[$moduleSlug] ?? null;
$pageTitle = $mod ? $mod['name'] : 'Page not found';

// Get users for the table
$users = [];
try { $stmt = $pdo->query("SELECT p.person_id, p.preferredName, p.surname, p.username, p.email, r.name as role_name FROM tos_person p LEFT JOIN tos_role r ON p.role_id_primary = r.role_id ORDER BY p.preferredName LIMIT 10"); $users = $stmt->fetchAll(\PDO::FETCH_ASSOC); } catch (\Exception $e) {}

$todayLabel = (new DateTime())->format('Y-m-d');

echo '<div class="mx-auto max-w-6xl space-y-5">';
echo '<nav class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground"><a href="#" class="font-semibold text-gold hover:underline">الincipal</a><span class="mx-1">/</span><a href="#" class="font-semibold text-gold hover:underline">أقسام النظام</a><span class="mx-1">/</span><span>' . htmlspecialchars($pageTitle) . '</span></nav>';

if ($mod) {
    echo '<div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0"><h1 class="text-2xl font-extrabold text-foreground sm:text-3xl">' . htmlspecialchars($mod['name']) . '</h1><p class="mt-1 max-w-3xl text-sm leading-relaxed text-muted-foreground">' . htmlspecialchars($mod['description']) . ' — يتم ربط هذه Page ب数据 نظام المدرسة عبر facingة البرمجة.</p></div><a href="#" class="rounded-full border border-border bg-card px-4 py-2 text-xs font-bold hover:bg-accent">تصدير</a></div>';

    // Search
    echo '<section class="rounded-3xl border border-border/60 bg-muted/40 p-4 sm:p-5"><div class="flex flex-wrap items-center gap-3"><span class="text-sm font-semibold text-muted-foreground">البحث عن</span><div class="relative min-w-[220px] flex-1"><input type="text" placeholder="ابحث…" class="w-full rounded-full border border-border bg-card px-4 py-2 text-sm"></div><button class="rounded-full bg-primary px-5 py-2 text-sm font-bold text-primary-foreground">إذهب</button></div><p class="mt-2 text-xs text-muted-foreground">الاسم المفضل، اسم العائلة، اسم المستخدم، الدور، رقم الطالب، البريد الإلكتروني</p></section>';

    // Filters
    echo '<section class="rounded-3xl border border-border/60 bg-muted/40 p-4 sm:p-5"><h2 class="mb-3 text-base font-extrabold text-foreground">التصفيات</h2><div class="grid gap-3 sm:grid-cols-2"><label class="flex items-center gap-3"><span class="w-32 shrink-0 text-sm font-semibold text-muted-foreground">الحالة</span><input type="text" placeholder="الحالة" class="flex-1 rounded-full border border-border bg-card px-4 py-2 text-sm"></label><label class="flex items-center gap-3"><span class="w-32 shrink-0 text-sm font-semibold text-muted-foreground">الphase</span><input type="text" placeholder="الphase" class="flex-1 rounded-full border border-border bg-card px-4 py-2 text-sm"></label></div><div class="mt-4 flex items-center gap-3"><button class="rounded-full bg-primary px-5 py-2 text-sm font-bold text-primary-foreground">إذهب</button><button type="button" class="text-sm text-muted-foreground hover:underline">ultz التصفية</button></div></section>';

    // Toolbar
    echo '<div class="flex items-center justify-between gap-3"><h2 class="text-lg font-extrabold text-foreground">معاينة</h2><button class="flex items-center gap-2 rounded-full border border-border bg-card px-4 py-2 text-sm font-bold hover:bg-accent"><span>＋</span> إضافة</button></div>';

    // Table
    echo '<div class="tile overflow-x-auto p-0 rounded-[1.75rem]"><table class="w-full min-w-[620px] text-right text-sm"><thead><tr class="border-b border-border/60 text-xs text-muted-foreground"><th class="px-4 py-3 font-bold">الاسم</th><th class="px-4 py-3 font-bold">الحالة</th><th class="px-4 py-3 font-bold">الدور الأساسي</th><th class="px-4 py-3 font-bold">اسم المستخدم</th><th class="px-4 py-3 font-bold">ال-horizontal</th></tr></thead><tbody>';
    if (!empty($users)) {
        foreach ($users as $u) {
            $uName = trim(($u['preferredName'] ?? '') . ' ' . ($u['surname'] ?? ''));
            $roleName = $u['role_name'] ?? '—';
            echo '<tr class="border-b border-border/40 last:border-0"><td class="px-4 py-3 align-middle">' . htmlspecialchars($uName) . '</td><td class="px-4 py-3 align-middle"><span class="inline-flex items-center rounded-full bg-mint px-3 py-1 text-xs font-bold text-mint-foreground">كامل</span></td><td class="px-4 py-3 align-middle">' . htmlspecialchars($roleName) . '</td><td class="px-4 py-3 align-middle">' . htmlspecialchars($u['username'] ?? '—') . '</td><td class="px-4 py-3 align-middle"><button class="rounded-full border border-border px-4 py-1 text-xs font-bold hover:bg-accent">تعديل</button></td></tr>';
        }
    } else {
        echo '<tr><td colspan="5" class="px-4 py-6 text-center text-muted-foreground">لا توجد سجلات لعرضها.</td></tr>';
    }
    echo '</tbody></table></div>';

    echo '<div class="tile rounded-[1.75rem] bg-card p-4 sm:p-5"><p class="text-xs leading-relaxed text-muted-foreground">الsource في نظام المدرسة: <span class="font-mono">' . htmlspecialchars($mod['name']) . ' / ' . htmlspecialchars($moduleSlug) . '.php</span></p></div>';
} else {
    echo '<div class="tile rounded-[1.75rem] bg-card p-8 text-center"><h1 class="text-2xl font-extrabold text-foreground">الصفحة غير موجودة</h1><p class="mt-2 text-sm text-muted-foreground">لا توجد page بهذا الاسم.</p></div>';
}
echo '</div>';
