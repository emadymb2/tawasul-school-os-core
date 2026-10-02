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
use TawasulOS\Services\Format;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Activities\ActivityChoiceGateway;
use TawasulOS\Domain\Activities\ActivityStaffGateway;
use TawasulOS\Domain\Activities\ActivityTripGateway;
use TawasulOS\Domain\Activities\ActivityStudentGateway;
use TawasulOS\Domain\Activities\ActivityCategoryGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$params = [
    'tawasulActivityCategoryID' => $_POST['tawasulActivityCategoryID'] ?? '',
    'sidebar'             => 'false',
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulActivities/enrolment_manage.php&'.http_build_query($params);

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/enrolment_manage.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $activityCategoryGateway = $container->get(ActivityCategoryGateway::class);
    $activityGateway = $container->get(ActivityGateway::class);
    $activityStudentGateway = $container->get(ActivityStudentGateway::class);
    $activityChoiceGateway = $container->get(ActivityChoiceGateway::class);
    $studentGateway = $container->get(StudentGateway::class);

    $enrolmentList = $_POST['person'] ?? [];

    // Validate the required values are present
    if (empty($params['tawasulActivityCategoryID']) || empty($enrolmentList)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $categoryDetails = $activityCategoryGateway->getByID($params['tawasulActivityCategoryID']);
    if (empty($categoryDetails)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $activities = [];
    $unassigned = [];
    $changeList = [];

    // Update student enrolments
    foreach ($enrolmentList as $person => $tawasulActivityID) {
        list($tawasulPersonID, $enrolmentID) = array_pad(explode('-', $person, 2), 2, '');
        $change = '';

        // Get any existing enrolment
        $enrolment = $activityStudentGateway->getEnrolmentByCategoryAndPerson($params['tawasulActivityCategoryID'], $tawasulPersonID);
        $student = $studentGateway->selectActiveStudentByPerson($categoryDetails['tawasulSchoolYearID'], $tawasulPersonID)->fetch();

        if (empty($student)) {
            $partialFail = true;
            continue;
        }

        if (empty($tawasulActivityID)) {
            // Record this removal so it can be updated in the database
            if (!empty($enrolment['tawasulActivityID'])) {
                $unassigned[] = $tawasulPersonID;
                $activities[] = $enrolment['tawasulActivityID'];
                $activityName = $enrolment['activityName'];
                $change = __('Removed from');
            }
        } else {
            // Connect the choice to the enrolment, for future queries and weighting
            $activity = $activityGateway->getByID($tawasulActivityID, ['name']);
            $choice = $activityChoiceGateway->getChoiceByActivityAndPerson($tawasulActivityID, $tawasulPersonID);
            $choiceNumber = intval($choice['choice'] ?? 0);

            if (!empty($enrolment)) {
                // Update and existing enrolment
                $data = [
                    'tawasulActivityID'       => $tawasulActivityID,
                    'tawasulActivityChoiceID' => $choice['tawasulActivityChoiceID'] ?? null,
                    'timestamp'              => date('Y-m-d H:i:s'),
                ];

                $updated = $activityStudentGateway->update($enrolment['tawasulActivityStudentID'], $data);

                if ($enrolment['tawasulActivityID'] != $data['tawasulActivityID']) {
                    $activities[] = $data['tawasulActivityID'];
                    $activities[] = $enrolment['tawasulActivityID'];
                    $activityName = $activity['name'];
                    $change = __('Moved to');
                }
            } else {
                // Add a new enrolment
                $data = [
                    'tawasulActivityID'       => $tawasulActivityID,
                    'tawasulActivityChoiceID' => $choice['tawasulActivityChoiceID'] ?? null,
                    'tawasulPersonID'         => $tawasulPersonID,
                    'status'                 => 'Accepted',
                    'timestamp'              => date('Y-m-d H:i:s'),
                ];

                $inserted = $activityStudentGateway->insert($data);
                $partialFail &= !$inserted;

                $activityName = $activity['name'];
                $activities[] = $tawasulActivityID;
                $change = __('Added to');
            }
        }

        if (!empty($change)) {
            $changeList[] = __('{student} ({formGroup}) - <i>{change} {activity}</i>', [
                'student'    => Format::name('', $student['preferredName'], $student['surname'], 'Student', false, true),
                'formGroup'  => $student['formGroup'],
                'change'     => $change,
                'activity' => $activityName ?? __('Unknown'),
            ]);
        }
    }

    $activities = array_unique($activities);

    // Remove enrolments that have been unassigned
    foreach ($unassigned as $tawasulPersonID) {
        $activityStudentGateway->deleteEnrolmentByCategoryAndPerson($params['tawasulActivityCategoryID'], $tawasulPersonID);
    }

    // Raise a new notification category
    if (!empty($changeList)) {
        $event = new NotificationEvent('Activities', 'Activity Status Changed');
        $event->setNotificationText(__('{person} has made the following changes to {category} enrolment:', [
            'person' => Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true),
            'category' => $categoryDetails['name'] ?? __('Activities'),
        ]).'<br/>'.Format::list($changeList));

        // Notify activity leaders
        $staff = $container->get(ActivityStaffGateway::class)->selectStaffByActivity($activities);
        foreach ($staff as $person) {
            $event->addRecipient($person['tawasulPersonID']);
        }

        $event->setActionLink("/index.php?q=/modules/TawasulActivities/report_overview.php&tawasulActivityCategoryID=".$params['tawasulActivityCategoryID']);
        $event->sendNotifications($pdo, $session);
    }
    

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";
    header("Location: {$URL}");
}
