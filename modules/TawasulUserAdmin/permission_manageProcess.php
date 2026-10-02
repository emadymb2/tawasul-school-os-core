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
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulModuleID = $_POST['tawasulModuleID'] ?? '';
$tawasulRoleID = $_POST['tawasulRoleID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/permission_manage.php&tawasulModuleID='.$tawasulModuleID.'&tawasulRoleID='.$tawasulRoleID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/permission_manage.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    $permissions = $_POST['permission'] ?? [];
    $totalCount = $_POST['totalCount'] ?? [];
    $maxInputVars = ini_get('max_input_vars');

    if (empty($totalCount)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else if (is_null($maxInputVars) != false && $maxInputVars <= count($_POST, COUNT_RECURSIVE)) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    } else {
        $data = array();

        if (empty($tawasulModuleID) && empty($tawasulRoleID)) {
            $sql = "TRUNCATE TABLE tawasulPermission";
        } else {
            $where = array();

            if (!empty($tawasulModuleID)) {
                $data['tawasulModuleID'] = $tawasulModuleID;
                $where[] = "tawasulAction.tawasulModuleID=:tawasulModuleID";
            }

            if (!empty($tawasulRoleID)) {
                $data['tawasulRoleID'] = $tawasulRoleID;
                $where[] = "tawasulPermission.tawasulRoleID=:tawasulRoleID";
            }

            $sql = "DELETE tawasulPermission
                    FROM tawasulPermission
                    JOIN tawasulAction ON (tawasulPermission.tawasulActionID=tawasulAction.tawasulActionID)
                    WHERE ".implode(' AND ', $where);
        }

        try {
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        $insertFail = false;
        foreach ($permissions as $tawasulActionID => $roles) {
            if (empty($roles)) continue;

            foreach ($roles as $tawasulRoleID => $checked) {
                if ($checked != 'on') continue;

                try {
                    $data = array('tawasulActionID' => $tawasulActionID, 'tawasulRoleID' => $tawasulRoleID);
                    $sql = 'INSERT INTO tawasulPermission SET tawasulActionID=:tawasulActionID, tawasulRoleID=:tawasulRoleID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $insertFail = true;
                }
            }
        }

        if ($insertFail == true) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        } else {
            $session->set('pageLoads', null);

            //Success0
            $URL .= '&return=success0';
            header("Location: {$URL}");
            exit;
        }
    }
}
