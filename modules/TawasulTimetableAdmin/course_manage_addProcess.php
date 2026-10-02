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

use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['description' => 'HTML']);

$tawasulDepartmentID = !empty($_POST['tawasulDepartmentID']) ? $_POST['tawasulDepartmentID'] : null;
$name = $_POST['name'] ?? '';
$nameShort = $_POST['nameShort'] ?? '';
$orderBy = $_POST['orderBy'] ?? '';
$description = $_POST['description'] ?? '';
$map = $_POST['map'] ?? 'N';
$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
$tawasulYearGroupIDList = implode(',', $_POST['tawasulYearGroupIDList'] ?? []);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/course_manage_add.php&tawasulSchoolYearID=$tawasulSchoolYearID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/course_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Validate Inputs
    if ($tawasulSchoolYearID == '' or $name == '' or $nameShort == '' or $map == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        //Check unique inputs for uniquness
        try {
            $data = array('name' => $name, 'tawasulSchoolYearID' => $tawasulSchoolYearID);
            $sql = 'SELECT * FROM tawasulCourse WHERE (name=:name AND tawasulSchoolYearID=:tawasulSchoolYearID)';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        $customRequireFail = false;
        $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('Course', [], $customRequireFail);

        if ($customRequireFail) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        if ($result->rowCount() > 0) {
            $URL .= '&return=error3';
            header("Location: {$URL}");
        } else {
            //Write to database
            try {
                $data = array('tawasulDepartmentID' => $tawasulDepartmentID, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'name' => $name, 'nameShort' => $nameShort, 'orderBy' => $orderBy, 'description' => $description, 'map' => $map, 'tawasulYearGroupIDList' => $tawasulYearGroupIDList, 'fields' => $fields);
                $sql = 'INSERT INTO tawasulCourse SET tawasulDepartmentID=:tawasulDepartmentID, tawasulSchoolYearID=:tawasulSchoolYearID, name=:name, nameShort=:nameShort, orderBy=:orderBy, description=:description, map=:map, tawasulYearGroupIDList=:tawasulYearGroupIDList, fields=:fields';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            //Last insert ID
            $AI = str_pad($connection2->lastInsertID(), 8, '0', STR_PAD_LEFT);

            // Manage custom field file uploads
            if (!empty($fields) && !empty($AI)) {
                $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('Course', [], $fields, 'tawasulCourse', $AI);
            }

            $URL .= "&return=success0&editID=$AI";
            header("Location: {$URL}");
        }
    }
}
