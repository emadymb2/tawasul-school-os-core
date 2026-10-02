<?php
ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/php_diag.log');

chdir('/home/se/public_html');
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

set_error_handler(function($severity, $message, $file, $line) {
    echo "PHP ERROR: $message in $file:$line\n";
    error_log("PHP ERROR: $message in $file:$line");
    return true;
});

register_shutdown_function(function() {
    $lastError = error_get_last();
    if ($lastError) {
        echo "FATAL: " . $lastError['message'] . " in " . $lastError['file'] . ":" . $lastError['line'] . "\n";
        error_log("FATAL: " . $lastError['message'] . " in " . $lastError['file'] . ":" . $lastError['line']);
    }
});

try {
    // Simulate the exact code path from index.php
    require_once __DIR__ . '/gibbon.php';
    
    $theme = $container->get('theme');
    $page = $container->get('page');
    $session = $container->get('session');
    
    $isLoggedIn = $session->has('username') && $session->has('gibbonRoleIDCurrent');
    
    $localeCode = str_replace('_', '-', $session->get('i18n')['code']);
    $page->addData([
        'isLoggedIn' => $isLoggedIn,
        'isHomePage' => empty($page->getAddress()),
        'organisationLogo' => $session->get('organisationLogo'),
        'organisationName' => $session->get('organisationName'),
        'organisationNameShort' => $session->get('organisationNameShort'),
        'indexText' => $session->get('indexText'),
        'cacheString' => $session->get('cacheString'),
        'version' => $gibbon->getVersion(),
        'versionName' => 'v'.$gibbon->getVersion().($session->get('cuttingEdgeCode') == 'Y'? 'dev' : ''),
        'rightToLeft' => $session->get('i18n')['rtl'] == 'Y',
        'lang' => $localeCode,
        'address' => $page->getAddress(),
    ]);
    
    echo "Data added OK, isLoggedIn=$isLoggedIn\n";
    
    // Now simulate the non-logged-in path
    if (!$isLoggedIn && !empty($_GET['i18n'])) {
        echo "Would set i18n to: " . htmlspecialchars((string) $_GET['i18n']) . "\n";
    }
    
    if (!$session->has('address')) {
        if (!$isLoggedIn) {
            echo "Rendering welcome page\n";
            echo "a: " . $session->get('indexText') . "\n";
            
            $settingGateway = $container->get(\Gibbon\Domain\System\SettingGateway::class);
            echo "settingGateway OK\n";
            echo "b: " . $settingGateway->getSettingByScope('Admissions', 'admissionsEnabled') . "\n";
            
            $templateData = [
                'indexText' => $session->get('indexText'),
                'organisationName' => $session->get('organisationName'),
                'admissionsEnabled' => $settingGateway->getSettingByScope('Admissions', 'admissionsEnabled') == 'Y',
                'admissionsLinkText' => $settingGateway->getSettingByScope('Admissions', 'admissionsLinkText'),
                'admissionsLinkName' => $settingGateway->getSettingByScope('Admissions', 'admissionsLinkName'),
                'publicRegistration' => $settingGateway->getSettingByScope('User Admin', 'enablePublicRegistration') == 'Y',
                'publicStudentApplications' => $settingGateway->getSettingByScope('Application Form', 'publicApplications') == 'Y',
                'publicStaffApplications' => $settingGateway->getSettingByScope('Staff Application Form', 'staffApplicationFormPublicApplications') == 'Y',
                'makeDepartmentsPublic' => $settingGateway->getSettingByScope('Departments', 'makeDepartmentsPublic') == 'Y',
                'makeUnitsPublic' => $settingGateway->getSettingByScope('Planner', 'makeUnitsPublic') == 'Y',
                'privacyPolicy' => $settingGateway->getSettingByScope('System Admin', 'privacyPolicy'),
            ];
            
            echo "templateData created OK\n";
            foreach ($templateData as $k => $v) {
                if (is_array($v)) {
                    echo "  $k: array\n";
                } elseif (is_bool($v)) {
                    echo "  $k: " . ($v ? 'true' : 'false') . "\n";
                } else {
                    echo "  $k: " . substr($v ?? 'null', 0, 50) . "\n";
                }
            }
        }
    }
    
    echo "\nAll checks passed!\n";
} catch (\Throwable $e) {
    echo "CAUGHT: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "TRACE:\n" . $e->getTraceAsString() . "\n";
}
