<?php
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

// Group modules by category
$groups = [
    'admin' => 'الإدارة',
    'care' => 'رعاية تربوية',
    'assess' => 'التقييم',
    'learn' => 'تعلم',
    'people' => 'ال/people',
    'other' => 'أخرى',
];

// Count pages per module (based on files in module dir)
function countPages($moduleDir) {
    $count = 0;
    $files = glob($moduleDir . '/*.php');
    foreach ($files as $f) {
        $b = basename($f, '.php');
        if (strpos($b, 'manifest') === false && strpos($b, 'install') === false && strpos($b, 'uninstall') === false) {
            $count++;
        }
    }
    return max($count, 1);
}

echo '<div class="mx-auto max-w-6xl space-y-5">';
echo '<nav class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground"><span class="font-semibold text-gold hover:underline">الincipal</span><span class="mx-1">/</span><span>أقسام النظام</span></nav>';
echo '<div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0"><h1 class="text-2xl font-extrabold text-foreground sm:text-3xl">أقسام النظام</h1><p class="mt-1 max-w-3xl text-sm leading-relaxed text-muted-foreground">جميع أقسام النظام وصفحاته (' . count($modules) . ' قسم) مطابقة لنظام المدرسة، جاهزة للربط لاحقاً ب facingة البرمجة الحقيقية.</p></div></div>';

foreach ($groups as $key => $title) {
    $mods = [];
    foreach ($modules as $name => $m) {
        $group = 'other';
        $mf = TAWASUL_ROOT . '/modules/' . $name . '/manifest.php';
        if (file_exists($mf)) {
            $c = file_get_contents($mf);
            if (preg_match("/\\\$group\s*=\s*['\"]([^'\"]*)['\"]/", $c, $mg)) $group = $mg[1];
        }
        if ($group === $key) $mods[$name] = $m;
    }
    if (empty($mods)) continue;
    echo '<section class="space-y-3"><h2 class="text-sm font-extrabold tracking-[0.2em] text-muted-foreground">' . $title . '</h2><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">';
    foreach ($mods as $name => $m) {
        $pageCount = countPages(TAWASUL_ROOT . '/modules/' . $name);
        echo '<a href="/tawasul-os/tawasul.php?module=' . urlencode($name) . '" class="tile flex items-center justify-between gap-3 p-4 transition hover:border-gold"><span class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-mint text-mint-foreground"><span class="h-5 w-5"></span></span><span class="font-bold text-foreground">' . htmlspecialchars($m['name']) . '</span></span><span class="text-xs text-muted-foreground">' . $pageCount . ' page</span></a>';
    }
    echo '</div></section>';
}
echo '</div>';
