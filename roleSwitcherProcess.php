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
use TawasulOS\Domain\User\RoleGateway;

// TawasulOS system-wide include
require_once __DIR__ . '/tawasul.php';

$tawasulRoleID = $_GET['tawasulRoleID'] ?? '';
$tawasulRoleID = str_pad(intval($tawasulRoleID), 3, '0', STR_PAD_LEFT);

$session->set('pageLoads', null);
$URL = Url::fromRoute();

//Check for parameter
if (!$session->has('tawasulPersonID') || !$session->has('tawasulRoleIDCurrent')) {
    header("Location: {$URL->withReturn('error0')}");
    exit;
} elseif (empty(intval($tawasulRoleID))) {
    header("Location: {$URL->withReturn('error0')}");
    exit;
} else {
    // Check for access to role
    $roleGateway = $container->get(RoleGateway::class);
    $role = $roleGateway->getAvailableUserRoleByID($session->get('tawasulPersonID'), $tawasulRoleID);

    if (empty($role) || empty($role['category'])) {
        header("Location: {$URL->withReturn('error0')}");
        exit;
    }

    //Make the switch
    $session->set('tawasulRoleIDCurrent', $tawasulRoleID);
    $session->set('tawasulRoleIDCurrentCategory', $role['category']);

    // Clear cached FF actions
    $session->forget('fastFinderActions');

    // Clear the main menu from session cache
    $session->forget('menuMainItems');

    $URL = Url::fromRoute()->withReturn('success0');
    header("Location: {$URL}");
    exit;
}
