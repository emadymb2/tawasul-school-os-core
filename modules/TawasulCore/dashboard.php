<?php
/**
 * TawasulOS — Dashboard
 * Rendered after successful login via tawasul.php
 */

if (!defined('TAWASUL_ROOT')) {
    define('TAWASUL_ROOT', realpath(__DIR__ . '/..'));
}

// User is already authenticated by tawasul.php
$firstName = $user['firstName'] ?? 'User';
$preferredName = $user['preferredName'] ?? $firstName;
$surname = $user['surname'] ?? '';
$fullName = trim($preferredName . ' ' . $surname);
$personId = $user['person_id'];
$role = $user['role_id_primary'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TawasulOS Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .bg-primary { background-color: #1e3a5f; }
        .text-primary-foreground { color: #ffffff; }
        .bg-coral { background-color: #e0654a; }
        .bg-gold { background-color: #d4a84a; }
        .bg-card { background-color: #ffffff; }
        .bg-secondary { background-color: #f5f5f5; }
        .bg-accent { background-color: #e8f0fe; }
        .text-foreground { color: #1a1a1a; }
        .text-muted-foreground { color: #6b7280; }
        .text-accent-foreground { color: #1e3a5f; }
        .border-border { border-color: #e5e7eb; }
    </style>
</head>
<body class="min-h-screen bg-gray-50" dir="rtl">
    <!-- Header -->
    <header class="bg-primary text-primary-foreground">
        <div class="flex items-center justify-between px-6 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-card">
                    <span class="text-xl font-bold text-primary">T</span>
                </span>
                <div>
                    <div class="text-lg font-extrabold">TawasulOS</div>
                    <div class="text-xs text-primary-foreground/65">نظام المدرسة</div>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm"><?php echo htmlspecialchars($fullName); ?></span>
                <a href="/tawasul-os/tawasul.php?logout=1" class="text-sm bg-coral hover:bg-coral/80 px-3 py-1 rounded">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="mx-auto max-w-6xl px-6 py-10">
        <div class="mb-8">
            <h1 class="text-4xl font-extrabold text-foreground">
                مرحبا <?php echo htmlspecialchars($preferredName); ?>،
            </h1>
            <p class="mt-2 text-muted-foreground">System Dashboard</p>
        </div>

        <!-- Module Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php
            $moduleDir = TAWASUL_ROOT . '/modules';
            $modules = [];
            if (is_dir($moduleDir)) {
                $dirs = glob($moduleDir . '/*', GLOB_ONLYDIR);
                foreach ($dirs as $dir) {
                    $name = basename($dir);
                    $manifestFile = $dir . '/manifest.php';
                    if (file_exists($manifestFile)) {
                        $content = file_get_contents($manifestFile);
                        $manifest = [];
                        if (preg_match("/\\\$name\s*=\s*['\"]([^'\"]*)['\"]/", $content, $m)) {
                            $manifest['name'] = $m[1];
                        }
                        if (preg_match("/\\\$description\s*=\s*['\"]([^'\"]*)['\"]/", $content, $m)) {
                            $manifest['description'] = $m[1];
                        }
                        if (!empty($manifest)) {
                            $modules[$name] = $manifest;
                        }
                    }
                }
            }
            ksort($modules);
            foreach ($modules as $dir => $manifest):
            ?>
            <a href="/tawasul-os/tawasul.php?module=<?php echo $dir; ?>" 
               class="block bg-card rounded-2xl p-6 border border-border hover:shadow-lg transition-shadow">
                <h3 class="text-lg font-bold text-foreground"><?php echo htmlspecialchars($manifest['name']); ?></h3>
                <p class="mt-1 text-sm text-muted-foreground"><?php echo htmlspecialchars($manifest['description']); ?></p>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Quick Stats -->
        <div class="mt-10 grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-card rounded-xl p-4 border border-border">
                <div class="text-2xl font-bold text-primary">117</div>
                <div class="text-xs text-muted-foreground">Users</div>
            </div>
            <div class="bg-card rounded-xl p-4 border border-border">
                <div class="text-2xl font-bold text-coral">34</div>
                <div class="text-xs text-muted-foreground">Modules</div>
            </div>
            <div class="bg-card rounded-xl p-4 border border-border">
                <div class="text-2xl font-bold text-gold">2</div>
                <div class="text-xs text-muted-foreground">School Years</div>
            </div>
            <div class="bg-card rounded-xl p-4 border border-border">
                <div class="text-2xl font-bold text-accent-foreground">209</div>
                <div class="text-xs text-muted-foreground">Tables</div>
            </div>
        </div>
    </main>
</body>
</html>