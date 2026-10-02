<?php
/**
 * Registers this module's own PSR-4 classes (Tos\Module\TawasulChat\*).
 *
 * composer.json maps only TawasulOS\ => src/, and src/tos_aliases.php
 * deliberately skips Tos\Module\*, so without this loader
 * Tos\Module\TawasulChat\Service\MessageService cannot be found and the chat
 * pages fatal with "Class not found". This mirrors
 * modules/TawasulFinance/src/bootstrap.php.
 */
spl_autoload_register(function ($class) {
    $prefix = 'Tos\\Module\\TawasulChat\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $path = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (file_exists($path)) {
        require $path;
    }
});
