<?php
/**
 * TawasulOS — Public Entry Point / Dashboard
 */

// Prevent direct access
if (!defined('TAWASUL_ROOT')) {
    define('TAWASUL_ROOT', realpath(__DIR__ . '/..'));
}

$modules = [];
$moduleDir = TAWASUL_ROOT . '/modules';
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
            if (preg_match("/\\\$entryURL\s*=\s*['\"]([^'\"]*)['\"]/", $content, $m)) {
                $manifest['entryURL'] = $m[1];
            }
            if (!empty($manifest)) {
                $modules[$name] = $manifest;
            }
        }
    }
}

// Sort modules by name
ksort($modules);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TawasulOS Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .module-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .module-card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; cursor: pointer; transition: transform 0.2s; text-decoration: none; color: inherit; display: block; }
        .module-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .module-card h3 { margin-top: 0; color: #2c3e50; }
        .module-card p { color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <header>
        <h1>TawasulOS</h1>
        <p>School Operating System</p>
    </header>
    <div class="container">
        <h2>Available Modules (<?php echo count($modules); ?>)</h2>
        <div class="module-grid">
            <?php foreach ($modules as $dir => $manifest): ?>
            <a class="module-card" href="/tawasul-os/modules/<?php echo $dir; ?>">
                <h3><?php echo htmlspecialchars($manifest['name']); ?></h3>
                <p><?php echo htmlspecialchars($manifest['description']); ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>