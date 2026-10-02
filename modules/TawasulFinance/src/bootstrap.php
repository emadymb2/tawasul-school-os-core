<?php
/**
 * Registers this module's own PSR-4 classes (Tos\Module\TawasulFinance\*) for
 * the TawasulFinance admin pages.
 *
 * composer.json maps only TawasulOS\ => src/, and src/tos_aliases.php
 * deliberately skips Tos\Module\*, so without this loader
 * Tos\Module\TawasulFinance\Forms\FinanceFormFactory cannot be found and the
 * pages that use it fatal with "Class not found".
 *
 * This mirrors modules/TawasulCore/src/bootstrap.php.
 */
spl_autoload_register(function ($class) {
    $prefix = 'Tos\\Module\\TawasulFinance\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $path = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (file_exists($path)) {
        require $path;
    }
});
