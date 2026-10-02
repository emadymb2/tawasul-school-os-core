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
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$allStaff = '';
if (isset($_GET['allStaff'])) {
    $allStaff = $_GET['allStaff'] ?? '';
}
$search = '';
if (isset($_GET['search'])) {
    $search = $_GET['search'] ?? '';
}
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/staff_manage_add.php&search=$search&allStaff=$allStaff";

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    $tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
    $initials = $_POST['initials'] ?? '';
    if ($initials == '') {
        $initials = null;
    }
    $type = $_POST['type'] ?? '';
    $jobTitle = $_POST['jobTitle'] ?? '';
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

    //Validate Inputs
    if ($tawasulPersonID == '' or $type == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        //Check unique inputs for uniquness
        try {
            if ($initials == '') {
                $data = array('tawasulPersonID' => $tawasulPersonID);
                $sql = 'SELECT * FROM tawasulStaff WHERE tawasulPersonID=:tawasulPersonID';
            } else {
                $data = array('tawasulPersonID' => $tawasulPersonID, 'initials' => $initials);
                $sql = 'SELECT * FROM tawasulStaff WHERE tawasulPersonID=:tawasulPersonID OR initials=:initials';
            }
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        $customRequireFail = false;
        $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('Staff', ['requiredOverride' => 'N'], $customRequireFail);

        if ($customRequireFail) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        if ($result->rowCount() > 0) {
            $URL .= '&return=error7';
            header("Location: {$URL}");
        } else {
            //Write to database
            try {
                $data = array('tawasulPersonID' => $tawasulPersonID, 'initials' => $initials, 'type' => $type, 'jobTitle' => $jobTitle, 'firstAidQualified' => $firstAidQualified, 'firstAidQualification' => $firstAidQualification, 'firstAidExpiry' => $firstAidExpiry, 'countryOfOrigin' => $countryOfOrigin, 'qualifications' => $qualifications, 'biographicalGrouping' => $biographicalGrouping, 'biographicalGroupingPriority' => $biographicalGroupingPriority, 'biography' => $biography, 'coveragePriority' => $coveragePriority,  'coverageExclude' => $coverageExclude, 'fields' => $fields);
                $sql = 'INSERT INTO tawasulStaff SET tawasulPersonID=:tawasulPersonID, initials=:initials, type=:type, jobTitle=:jobTitle, firstAidQualified=:firstAidQualified, firstAidQualification=:firstAidQualification, firstAidExpiry=:firstAidExpiry, countryOfOrigin=:countryOfOrigin, qualifications=:qualifications, biographicalGrouping=:biographicalGrouping, biographicalGroupingPriority=:biographicalGroupingPriority, biography=:biography, coveragePriority=:coveragePriority, coverageExclude=:coverageExclude, fields=:fields';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            //Last insert ID
            $AI = str_pad($connection2->lastInsertID(), 10, '0', STR_PAD_LEFT);

            // Manage custom field file uploads
            if (!empty($fields) and !empty($AI)) {
                $filesRecorded = $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('Staff', ['requiredOverride' => 'N'], $fields, 'tawasulStaff', $AI);
            }

            // Raise a new notification event
            $event = new NotificationEvent('Staff', 'New Staff');

            $person = $container->get(UserGateway::class)->getByID($tawasulPersonID);
            $event->setNotificationText(__('A new staff member has been added: {name} ({username}) {jobTitle}', [
                'name' => Format::name('', $person['preferredName'], $person['surname'], 'Staff', false, true),
                'username' => $person['username'],
                'jobTitle' => $jobTitle,
            ]));
            $event->setActionLink('/index.php?q=/modules/TawasulStaff/staff_view_details.php&tawasulPersonID='.$tawasulPersonID.'&allStaff=&search=');

            // Send notifications
            $event->sendNotifications($pdo, $session);

            $URL .= "&return=success0&editID=$AI";
            header("Location: {$URL}");
        }
    }
}
