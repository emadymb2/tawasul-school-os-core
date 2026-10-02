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

//Get variables
$tawasulSchoolYearID = '';
if (isset($_GET['tawasulSchoolYearID'])) {
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
}
$key = '';
if (isset($_GET['key'])) {
    $key = $_GET['key'] ?? '';
}
$tawasulPersonIDStudent = '';
if (isset($_GET['tawasulPersonIDStudent'])) {
    $tawasulPersonIDStudent = $_GET['tawasulPersonIDStudent'] ?? '';
}
$tawasulPersonIDParent = '';
if (isset($_GET['tawasulPersonIDParent'])) {
    $tawasulPersonIDParent = $_GET['tawasulPersonIDParent'] ?? '';
}

//Check variables
if ($tawasulSchoolYearID == '' or $key == '' or $tawasulPersonIDStudent == '' or $tawasulPersonIDParent == '') { $page->addError(__('You have not specified one or more required parameters.'));
} else {
    //Check for record
    $keyReadFail = false;
    try {
        $dataKeyRead = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonIDStudent' => $tawasulPersonIDStudent, 'key' => $key);
        $sqlKeyRead = 'SELECT * FROM tawasulPlannerParentWeeklyEmailSummary WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonIDStudent=:tawasulPersonIDStudent AND `key`=:key';
        $resultKeyRead = $connection2->prepare($sqlKeyRead);
        $resultKeyRead->execute($dataKeyRead);
    } catch (PDOException $e) {
        $page->addError(__('Your request failed due to a database error.'));
    }

    if ($resultKeyRead->rowCount() != 1) { //If not exists, report error
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
    } else {    //If exists check confirmed
        $rowKeyRead = $resultKeyRead->fetch();

        if ($rowKeyRead['confirmed'] == 'Y') { //If already confirmed, report success
            $page->addSuccess(__('Thank you for confirming receipt and reading of this email.'));
        } else { //If not confirmed, confirm
            $keyWriteFail = false;
            try {
                $dataKeyWrite = array('tawasulPersonIDStudent' => $tawasulPersonIDStudent, 'tawasulPersonIDParent' => $tawasulPersonIDParent, 'key' => $key);
                $sqlKeyWrite = "UPDATE tawasulPlannerParentWeeklyEmailSummary SET confirmed='Y', tawasulPersonIDParent=:tawasulPersonIDParent WHERE tawasulPersonIDStudent=:tawasulPersonIDStudent AND `key`=:key";
                $resultKeyWrite = $connection2->prepare($sqlKeyWrite);
                $resultKeyWrite->execute($dataKeyWrite);
            } catch (PDOException $e) {
                $keyWriteFail = true;
            }

            if ($keyWriteFail == true) { //Report error
                $page->addError(__('Your request failed due to a database error.'));
            } else { //Report success
                $page->addSuccess(__('Thank you for confirming receipt and reading of this email.'));
            }
        }
    }
}
