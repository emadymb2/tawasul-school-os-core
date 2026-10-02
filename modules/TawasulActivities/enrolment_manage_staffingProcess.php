<?php
/*
TawasulOS, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Activities\ActivityStaffGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$params = [
    'tawasulActivityCategoryID' => $_POST['tawasulActivityCategoryID'] ?? '',
    'sidebar'             => 'false',
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulActivities/enrolment_manage_staffing.php&'.http_build_query($params);

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/enrolment_manage_staffing.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $activityGateway = $container->get(ActivityGateway::class);
    $staffGateway = $container->get(ActivityStaffGateway::class);

    $staffingList = $_POST['person'] ?? [];
    $roleList = $_POST['role'] ?? [];

    if (empty($params['tawasulActivityCategoryID']) || empty($staffingList)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $assigned = [];

    // Update staffing
    foreach ($staffingList as $person => $personActivities) {
        list($tawasulPersonID, $enrolmentID) = array_pad(explode('-', $person, 2), 2, '');

        foreach ($personActivities as $listIndex => $tawasulActivityID) {
            if (empty($tawasulActivityID)) {
                continue;
            }

            $staffing = $staffGateway->selectBy(['tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $tawasulPersonID])->fetch();

            if (!empty($staffing)) {
                // Update and existing staffing
                $updated = $staffGateway->update($staffing['tawasulActivityStaffID'], [
                    'role' => $roleList[$person][$listIndex] ?? 'Assistant',
                ]);
            } else {
                // Add a new staffing
                $inserted = $staffGateway->insert([
                    'tawasulActivityID' => $tawasulActivityID,
                    'tawasulPersonID'   => $tawasulPersonID,
                    'role'             => $roleList[$person][$listIndex] ?? 'Assistant',
                ]);
                $partialFail &= !$inserted;
            }

            $assigned[$tawasulActivityID][] = $tawasulPersonID;
        }
    }

    // Remove staffing that have been unassigned
    $activitiesByCategory = $activityGateway->selectActivitiesByCategory($params['tawasulActivityCategoryID'])->fetchKeyPair();

    foreach ($activitiesByCategory as $tawasulActivityID => $activityName) {
        $staffList = $assigned[$tawasulActivityID] ?? [];
        $staffGateway->deleteStaffNotInList($tawasulActivityID, $staffList);
    }

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";
    header("Location: {$URL}");
}
