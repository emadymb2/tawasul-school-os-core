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

use TawasulOS\Domain\Departments\DepartmentResourceGateway;

require_once __DIR__ . '/../../tawasul.php';

//Module includes
include './moduleFunctions.php';

$tawasulDepartmentID = $_GET['tawasulDepartmentID'] ?? '';
$tawasulDepartmentResourceID = $_GET['tawasulDepartmentResourceID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/department_edit.php&tawasulDepartmentID=$tawasulDepartmentID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulDepartments/department_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!

    //Check if tawasulDepartmentID specified
    if ($tawasulDepartmentID == '' or $tawasulDepartmentResourceID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {

            $result = $container->get(DepartmentResourceGateway::class)->selectBy(['tawasulDepartmentResourceID' => $tawasulDepartmentResourceID, 'tawasulDepartmentID' => $tawasulDepartmentID]);
            
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }
        if ($result->rowCount() != 1) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            //Get role within learning area
            $role = getRole($session->get('tawasulPersonID'), $tawasulDepartmentID, $connection2);

            if ($role != 'Coordinator' and $role != 'Assistant Coordinator' and $role != 'Teacher (Curriculum)' and $role != 'Director' and $role != 'Manager') {
                $URL .= '&return=error0';
                header("Location: {$URL}");
            } else {
                //Write to database
                try {
                    $data = array('tawasulDepartmentResourceID' => $tawasulDepartmentResourceID);
                    $sql = 'DELETE FROM tawasulDepartmentResource WHERE tawasulDepartmentResourceID=:tawasulDepartmentResourceID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                $URL .= '&return=success0';
                header("Location: {$URL}");
            }
        }
    }
}
