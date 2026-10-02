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

use TawasulOS\Domain\System\ModuleGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulModuleID = $_GET['tawasulModuleID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/module_manage_edit.php&tawasulModuleID='.$tawasulModuleID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/module_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    // Check if module specified
    if (empty($tawasulModuleID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        $moduleGateway = $container->get(ModuleGateway::class);
        $module = $moduleGateway->getByID($tawasulModuleID);

        if (empty($module)) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            // Validate Inputs
            $category = $_POST['category'] ?? '';
            $active = $_POST['active'] ?? '';

            if (empty($category) or empty($active)) {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                // Write to database
                $data = ['category' => $category, 'active' => $active];
                $moduleGateway->update($module['tawasulModuleID'], $data);

                // Reset cache to force top-menu reload
                $session->set('pageLoads', null);

                $URL .= '&return=success0';
                header("Location: {$URL}");
            }
        }
    }
}
