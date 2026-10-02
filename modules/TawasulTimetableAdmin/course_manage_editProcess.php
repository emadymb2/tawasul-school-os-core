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

$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/course_manage_edit.php&tawasulCourseID='.$tawasulCourseID.'&tawasulSchoolYearID='.($_POST['tawasulSchoolYearID'] ?? '');

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/course_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if special day specified
    if ($tawasulCourseID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulCourseID' => $tawasulCourseID);
            $sql = 'SELECT * FROM tawasulCourse WHERE tawasulCourseID=:tawasulCourseID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($result->rowCount() != 1) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            // Fetch old record for file comparison
            $oldCourseRecord = $result->fetch();

            //Validate Inputs
            $tawasulDepartmentID = !empty($_POST['tawasulDepartmentID']) ? $_POST['tawasulDepartmentID'] : null;
            $name = $_POST['name'] ?? '';
            $nameShort = $_POST['nameShort'] ?? '';
            $orderBy = $_POST['orderBy'] ?? '';
            $description = $_POST['description'] ?? '';
            $map = $_POST['map'] ?? '';
            $tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
            $tawasulYearGroupIDList = implode(',', $_POST['tawasulYearGroupIDList'] ?? []);

            if ($name == '' or $nameShort == '' or $tawasulSchoolYearID == '' or $map == '') {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                //Check unique inputs for uniquness
                try {
                    $data = array('name' => $name, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulCourseID' => $tawasulCourseID);
                    $sql = 'SELECT * FROM tawasulCourse WHERE (name=:name AND tawasulSchoolYearID=:tawasulSchoolYearID) AND NOT (tawasulCourseID=:tawasulCourseID)';
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
                        $data = array('tawasulDepartmentID' => $tawasulDepartmentID, 'name' => $name, 'nameShort' => $nameShort, 'orderBy' => $orderBy, 'description' => $description, 'map' => $map, 'tawasulYearGroupIDList' => $tawasulYearGroupIDList, 'fields' => $fields, 'tawasulCourseID' => $tawasulCourseID);
                        $sql = 'UPDATE tawasulCourse SET tawasulDepartmentID=:tawasulDepartmentID, name=:name, nameShort=:nameShort, orderBy=:orderBy, description=:description, map=:map, tawasulYearGroupIDList=:tawasulYearGroupIDList, fields=:fields WHERE tawasulCourseID=:tawasulCourseID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    // Manage custom field file uploads
                    if (!empty($fields) && !empty($tawasulCourseID)) {
                        $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('Course', [], $fields, 'tawasulCourse', $tawasulCourseID, $oldCourseRecord['fields'] ?? null);
                    }

                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
