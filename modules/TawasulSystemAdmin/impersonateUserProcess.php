<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use TawasulOS\Http\Url;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\User\RoleGateway;
use TawasulOS\Domain\System\ThemeGateway;
use TawasulOS\Domain\System\I18nGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = Url::fromModuleRoute('TawasulSystemAdmin', 'impersonateUser');

if (isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/impersonateUser.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    $userGateway = $container->get(UserGateway::class);
    $roleGateway = $container->get(RoleGateway::class);

    // Validate the config settings
    $config = $container->get('config')->getConfig();
    if (empty($config['allowImpersonateUser']) || !in_array($session->get('username'), $config['allowImpersonateUser'])) {
        $page->addError(__('Access to this action must be manually enabled in the configuration file.'));
        return;
    }

    // Validate the current user and that the session data is correct
    $currentUser = $userGateway->getByID($session->get('tawasulPersonID'), ['tawasulRoleIDPrimary']);
    if (empty($currentUser) || $currentUser['tawasulRoleIDPrimary'] != $session->get('tawasulRoleIDCurrent')) {
        header("Location: {$URL->withReturn('error0')}");
        exit;
    }

    // Check that the current user had Administrator access
    $primaryRole = $roleGateway->selectBy(['tawasulRoleID' => $session->get('tawasulRoleIDPrimary')], ['name', 'tawasulRoleID'])->fetch();
    if (empty($primaryRole) || $primaryRole['name'] != 'Administrator' || $primaryRole['tawasulRoleID'] != '001') {
        header("Location: {$URL->withReturn('error0')}");
        exit;
    }

    $tawasulPersonIDAccountSwitch = $_POST['tawasulPersonIDAccountSwitch'] ?? '';
    $userData = $userGateway->getByID($tawasulPersonIDAccountSwitch);

    // Validate that this user exists
    if (empty($tawasulPersonIDAccountSwitch) || empty($userData)) {
        header("Location: {$URL->withReturn('error2')}");
        exit;
    }

    // Get user details to be loaded into the session
    $user = $userGateway->getSafeUserData($tawasulPersonIDAccountSwitch);

    // Setup essential role information
    $primaryRole = $roleGateway->getByID($userData['tawasulRoleIDPrimary']);
    $user['tawasulRoleIDPrimary'] = $primaryRole['tawasulRoleID'];
    $user['tawasulRoleIDCurrent'] = $primaryRole['tawasulRoleID'];
    $user['tawasulRoleIDCurrentCategory'] = $primaryRole['category'] ?? '';
    $user['tawasulRoleIDAll'] = $roleGateway->selectRoleListByIDs($userData['tawasulRoleIDAll'])->fetchAll();

    // Load user data into the session
    $session->set($user);

    // Clear cached FF actions and main menu
    $session->forget('googleAPIAccessToken');
    $session->forget('googleAPIRefreshToken');
    $session->forget('fastFinderActions');
    $session->forget(['menuMainItems', 'menuModuleItems', 'menuModuleName', 'menuItemActive']);

    // Update user personal theme
    if (!empty($userData['tawasulThemeIDPersonal'])) {
        if ($container->get(ThemeGateway::class)->exists($userData['tawasulThemeIDPersonal'])) {
            $session->set('tawasulThemeIDPersonal', $userData['tawasulThemeIDPersonal']);
        }
    }

    // Update user language using personal language choice
    if (!empty($userData['tawasuli18nIDPersonal'])) {
        if ($i18n = $container->get(I18nGateway::class)->getByID($userData['tawasuli18nIDPersonal'])) {
            $session->set('i18n', $i18n);
        }
    }

    $URL = Url::fromHandlerRoute('index.php');
    header("Location: {$URL->withReturn('success0')}");
}
