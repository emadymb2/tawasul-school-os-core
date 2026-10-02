<?php
/*
 * Some Tawasul modules import classes as Tos\Forms\..., Tos\Services\... etc.
 * The core namespace is TawasulOS\. This loader makes both names point to
 * the same class. Tos\Module\* is left alone (real module namespaces).
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'Tos\\') !== 0 || strpos($class, 'Tos\\Module\\') === 0) {
        return;
    }
    $target = 'TawasulOS\\'.substr($class, 4);
    if (class_exists($target) || interface_exists($target) || trait_exists($target)) {
        class_alias($target, $class);
    }
});
