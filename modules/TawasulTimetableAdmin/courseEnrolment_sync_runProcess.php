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

$tawasulYearGroupIDList = $_POST['tawasulYearGroupIDList'] ?? null;
$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? null;

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/courseEnrolment_sync_run.php&tawasulSchoolYearID='.$tawasulSchoolYearID.'&tawasulYearGroupIDList='.$tawasulYearGroupIDList;
$URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/courseEnrolment_sync.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_sync_run.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $syncData = (isset($_POST['syncData']))? $_POST['syncData'] : false;

    if (empty($tawasulYearGroupIDList) || empty($tawasulSchoolYearID) || empty($syncData)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $partialFail = false;

        foreach ($syncData as $tawasulFormGroupID => $usersToEnrol) {
            if (empty($usersToEnrol)) continue;

            foreach ($usersToEnrol as $tawasulPersonID => $role) {

                $data = array(
                    'tawasulFormGroupID' => $tawasulFormGroupID,
                    'tawasulPersonID' => $tawasulPersonID,
                    'role' => $role,
                    'dateEnrolled' => date('Y-m-d'),
                );

                // Update existing course enrolments
                $sql = "UPDATE tawasulCourseClassPerson
                        JOIN tawasulStudentEnrolment ON (tawasulCourseClassPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                        JOIN tawasulCourseClassMap ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClassMap.tawasulCourseClassID 
                            AND tawasulCourseClassMap.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
                        SET tawasulCourseClassPerson.role=:role, tawasulCourseClassPerson.dateEnrolled=:dateEnrolled, tawasulCourseClassPerson.dateUnenrolled=NULL, reportable='Y'
                        WHERE tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID
                        AND tawasulStudentEnrolment.tawasulFormGroupID=:tawasulFormGroupID
                        AND tawasulCourseClassPerson.tawasulCourseClassPersonID IS NOT NULL";
                $pdo->executeQuery($data, $sql);

                // Add course enrolments
                $sql = "INSERT INTO tawasulCourseClassPerson (`tawasulCourseClassID`, `tawasulPersonID`, `role`, `dateEnrolled`, `reportable`)
                        SELECT tawasulCourseClassMap.tawasulCourseClassID, :tawasulPersonID, :role, :dateEnrolled, 'Y'
                        FROM tawasulCourseClassMap
                        LEFT JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClassMap.tawasulCourseClassID AND tawasulCourseClassPerson.role=:role)
                        LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID)
                        WHERE tawasulCourseClassMap.tawasulFormGroupID=:tawasulFormGroupID
                        AND (:role='Teacher' OR tawasulCourseClassMap.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID)
                        AND tawasulCourseClassPerson.tawasulCourseClassPersonID IS NULL";
                $pdo->executeQuery($data, $sql);

                if (!$pdo->getQuerySuccess()) $partialFail = true;
            }
        }

        if ($partialFail) {
            $URL .= '&return=warning3';
            header("Location: {$URL}");
            exit;
        } else {
            $URLSuccess .= '&return=success0';
            header("Location: {$URLSuccess}");
            exit;
        }
    }
}
