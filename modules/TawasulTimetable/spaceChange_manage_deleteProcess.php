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

require_once __DIR__ . '/../../tawasul.php';

$tawasulCourseClassID = $_POST['tawasulCourseClassID'] ?? '';
$tawasulTTSpaceChangeID = $_POST['tawasulTTSpaceChangeID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/spaceChange_manage_delete.php&tawasulTTSpaceChangeID='.$tawasulTTSpaceChangeID.'&tawasulCourseClassID='.$tawasulCourseClassID;
$URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/spaceChange_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/spaceChange_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tawasulTTSpaceChangeID and tawasulCourseClassID specified
        if ($tawasulTTSpaceChangeID == '' OR $tawasulCourseClassID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            //Check for access
            try {
                if ($highestAction == 'Manage Facility Changes_allClasses') {
                    $dataSelect = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sqlSelect = 'SELECT tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                } else if ($highestAction == 'Manage Facility Changes_myDepartment') {
                    $dataSelect = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulSchoolYearID2' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID2' => $session->get('tawasulPersonID'), 'tawasulCourseClassID2' => $tawasulCourseClassID);
                    $sqlSelect = '(SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)
                    UNION
                    (SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID2 AND (tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID2 AND role=\'Coordinator\') AND tawasulCourseClassID=:tawasulCourseClassID2)';
                } else {
                    $dataSelect = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sqlSelect = 'SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                }
                $resultSelect = $connection2->prepare($sqlSelect);
                $resultSelect->execute($dataSelect);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            if ($resultSelect->rowCount() != 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }
            else {
                try {
                    if ($highestAction == 'Manage Facility Changes_allClasses' OR $highestAction == 'Manage Facility Changes_myDepartment') {
                        $data = array('tawasulTTSpaceChangeID' => $tawasulTTSpaceChangeID);
                        $sql = 'SELECT tawasulTTSpaceChangeID, tawasulTTSpaceChange.date, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, spaceOld.name AS spaceOld, spaceNew.name AS spaceNew FROM tawasulTTSpaceChange JOIN tawasulTTDayRowClass ON (tawasulTTSpaceChange.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID) JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) LEFT JOIN tawasulSpace AS spaceOld ON (tawasulTTDayRowClass.tawasulSpaceID=spaceOld.tawasulSpaceID) LEFT JOIN tawasulSpace AS spaceNew ON (tawasulTTSpaceChange.tawasulSpaceID=spaceNew.tawasulSpaceID) WHERE tawasulTTSpaceChangeID=:tawasulTTSpaceChangeID ORDER BY date, course, class';
                    } else {
                        $data = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulTTSpaceChangeID' => $tawasulTTSpaceChangeID);
                        $sql = 'SELECT tawasulTTSpaceChangeID, tawasulTTSpaceChange.date, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, spaceOld.name AS spaceOld, spaceNew.name AS spaceNew FROM tawasulTTSpaceChange JOIN tawasulTTDayRowClass ON (tawasulTTSpaceChange.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID)  JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) LEFT JOIN tawasulSpace AS spaceOld ON (tawasulTTDayRowClass.tawasulSpaceID=spaceOld.tawasulSpaceID) LEFT JOIN tawasulSpace AS spaceNew ON (tawasulTTSpaceChange.tawasulSpaceID=spaceNew.tawasulSpaceID) WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND tawasulTTSpaceChangeID=:tawasulTTSpaceChangeID ORDER BY date, course, class';
                    }
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2a';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() != 1) {
                    $URL .= '&return=error2b';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('tawasulTTSpaceChangeID' => $tawasulTTSpaceChangeID);
                        $sql = 'DELETE FROM tawasulTTSpaceChange WHERE tawasulTTSpaceChangeID=:tawasulTTSpaceChangeID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $URLDelete = $URLDelete.'&return=success0';
                    header("Location: {$URLDelete}");
                }
            }
        }
    }
}
