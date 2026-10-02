<?php
/**
 * Registers the module's own PSR-4 classes for the in-TawasulOS admin pages.
 * api.php registers its own autoloader, so it does not need this file.
 */
spl_autoload_register(function ($class) {
    $prefix = 'Tos\\Module\\TawasulCore\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $path = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (file_exists($path)) {
        require $path;
    }
});
