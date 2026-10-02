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

use TawasulOS\Services\Format;
use TawasulOS\Domain\Timetable\FacilityChangeGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/spaceChange_manage_add.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/spaceChange_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    // Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0";
        header("Location: {$URL}");
        exit;
    }
    // Proceed!
    $tawasulCourseClassID = $_POST['tawasulCourseClassID'] ?? '';
    $tawasulTTDayRowClassID = substr($_POST['tawasulTTDayRowClassID'] ?? '', 0, 12);
    $date = substr($_POST['tawasulTTDayRowClassID'] ?? '', 13);
    $tawasulSpaceID = $_POST['tawasulSpaceID'] ?? '';

    $spaceChangeGateway = $container->get(FacilityChangeGateway::class);

    // Check for access
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
    $resultCheck = $pdo->selectOne($sqlSelect, $dataSelect);

    if (empty($resultCheck)) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    // Validate Inputs
    if (empty($tawasulTTDayRowClassID) || empty($date)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } 

    // Check unique inputs for uniquness
    $data = ['tawasulTTDayRowClassID' => $tawasulTTDayRowClassID, 'date' => $date, 'tawasulSpaceID' => $tawasulSpaceID, 'tawasulPersonID' => $session->get('tawasulPersonID')];

    if (!$spaceChangeGateway->unique($data, ['tawasulTTDayRowClassID', 'date'])) {
        $updated = $spaceChangeGateway->updateWhere(['tawasulTTDayRowClassID' => $tawasulTTDayRowClassID, 'date' => $date], $data);
    } else {
        $updated = $spaceChangeGateway->insert($data);
    }

    if (empty($updated)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }
    
    // Redirect back to View Timetable by Facility if we started there
    if (!empty($_POST['source'])) {
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulTimetable/tt_space_view.php&tawasulSpaceID='.$_POST['source'].'&ttDate='.Format::date($date);
    }

    $URL .= '&return=success0';
    header("Location: {$URL}");
    exit;
}
