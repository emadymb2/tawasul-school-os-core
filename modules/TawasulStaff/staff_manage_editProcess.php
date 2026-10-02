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
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulStaffID = $_GET['tawasulStaffID'] ?? '';
$allStaff = $_GET['allStaff'] ?? '';
$search = $_GET['search'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/staff_manage_edit.php&tawasulStaffID=$tawasulStaffID&search=$search&allStaff=$allStaff";

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulStaffID specified
    if ($tawasulStaffID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulStaffID' => $tawasulStaffID);
            $sql = 'SELECT * FROM tawasulStaff WHERE tawasulStaffID=:tawasulStaffID';
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
            // Get old record for file deletion tracking
            $oldStaffRecord = $result->fetch();

            //Validate Inputs
            $initials = $_POST['initials'] ?? '';
            if ($initials == '') {
                $initials = null;
            }
            $type = $_POST['type'] ?? '';
            $jobTitle = $_POST['jobTitle'] ?? '';
            $dateStart = $_POST['dateStart'] ?? '';
            if ($dateStart == '') {
                $dateStart = null;
            } else {
                $dateStart = Format::dateConvert($dateStart);
            }
            $dateEnd = $_POST['dateEnd'] ?? '';
            if ($dateEnd == '') {
                $dateEnd = null;
            } else {
                $dateEnd = Format::dateConvert($dateEnd);
            }
            $firstAidQualified = $_POST['firstAidQualified'] ?? '';
            $firstAidQualification = $_POST['firstAidQualification'] ?? null;
            $firstAidExpiry = ($firstAidQualified == 'Y' and !empty($_POST['firstAidExpiry'])) ? Format::dateConvert($_POST['firstAidExpiry']) : null;
            $countryOfOrigin = $_POST['countryOfOrigin'] ?? '';
            $qualifications = $_POST['qualifications'] ?? '';
            $biographicalGrouping = $_POST['biographicalGrouping'] ?? '';
            $biographicalGroupingPriority = $_POST['biographicalGroupingPriority'] ?? '';
            $biography = $_POST['biography'] ?? '';
            $coverageExclude = $_POST['coverageExclude'] ?? 'N';
            $coveragePriority = $_POST['coveragePriority'] ?? 0;

            if ($type == '') {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                //Check unique inputs for uniquness
                try {
                    $data = array('tawasulStaffID' => $tawasulStaffID, 'initials' => $initials);
                    $sql = "SELECT * FROM tawasulStaff WHERE initials=:initials AND NOT tawasulStaffID=:tawasulStaffID AND NOT initials=''";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                }

                $customRequireFail = false;
                $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('Staff', ['requiredOverride' => 'N'], $customRequireFail);

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
                        $data = array('initials' => $initials, 'type' => $type, 'jobTitle' => $jobTitle, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'firstAidQualified' => $firstAidQualified, 'firstAidQualification' => $firstAidQualification, 'firstAidExpiry' => $firstAidExpiry, 'countryOfOrigin' => $countryOfOrigin, 'qualifications' => $qualifications, 'biographicalGrouping' => $biographicalGrouping, 'biographicalGroupingPriority' => $biographicalGroupingPriority, 'biography' => $biography, 'coveragePriority' => $coveragePriority, 'coverageExclude' => $coverageExclude, 'fields' => $fields, 'tawasulStaffID' => $tawasulStaffID);
                        $sql = 'UPDATE tawasulStaff JOIN tawasulPerson ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) SET initials=:initials, type=:type, tawasulStaff.jobTitle=:jobTitle, dateStart=:dateStart, dateEnd=:dateEnd, firstAidQualified=:firstAidQualified, firstAidQualification=:firstAidQualification, firstAidExpiry=:firstAidExpiry, countryOfOrigin=:countryOfOrigin, qualifications=:qualifications, biographicalGrouping=:biographicalGrouping, biographicalGroupingPriority=:biographicalGroupingPriority, biography=:biography, coveragePriority=:coveragePriority, coverageExclude=:coverageExclude, tawasulStaff.fields=:fields WHERE tawasulStaffID=:tawasulStaffID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    // Manage custom field file uploads and deletions
                    if (!empty($fields) and !empty($tawasulStaffID)) {
                        $filesRecorded = $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('Staff', ['requiredOverride' => 'N'], $fields, 'tawasulStaff', $tawasulStaffID, $oldStaffRecord['fields']);
                    }

                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
