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

$tawasulYearGroupID = $_REQUEST['tawasulYearGroupID'] ?? null;
$tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? null;

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/courseEnrolment_sync_edit.php&tawasulYearGroupID='.$tawasulYearGroupID.'&tawasulSchoolYearID='.$tawasulSchoolYearID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_sync_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $syncEnabled = (isset($_POST['syncEnabled']))? $_POST['syncEnabled'] : null;
    $syncTo = (isset($_POST['syncTo']))? $_POST['syncTo'] : null;

    if (empty($tawasulYearGroupID) || empty($tawasulSchoolYearID) || empty($syncTo) || empty($syncEnabled)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $partialFail = false;

        foreach ($syncTo as $tawasulCourseClassID => $tawasulFormGroupID) {
            if (!empty($syncEnabled[$tawasulCourseClassID]) && !empty($tawasulFormGroupID)) {
                // Enabled and Set: insert or update
                $data = array(
                    'tawasulCourseClassID' => $tawasulCourseClassID,
                    'tawasulFormGroupID' => $tawasulFormGroupID,
                    'tawasulYearGroupID' => $tawasulYearGroupID,
                );

                $sql = "INSERT INTO tawasulCourseClassMap SET tawasulCourseClassID=:tawasulCourseClassID, tawasulFormGroupID=:tawasulFormGroupID, tawasulYearGroupID=:tawasulYearGroupID ON DUPLICATE KEY UPDATE tawasulFormGroupID=:tawasulFormGroupID, tawasulYearGroupID=:tawasulYearGroupID";
                $pdo->executeQuery($data, $sql);

                if (!$pdo->getQuerySuccess()) $partialFail = true;
            } else {
                // Not enabled or not set: delete record (if one exists)
                $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulYearGroupID' => $tawasulYearGroupID);
                $sql = "DELETE FROM tawasulCourseClassMap WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulYearGroupID=:tawasulYearGroupID";
                $pdo->executeQuery($data, $sql);

                if (!$pdo->getQuerySuccess()) $partialFail = true;
            }
        }

        if ($partialFail) {
            $URL .= '&return=warning3';
            header("Location: {$URL}");
            exit;
        } else {
            $URL .= '&return=success0';
            header("Location: {$URL}");
            exit;
        }
    }
}
