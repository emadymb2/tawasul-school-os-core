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
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\Students\StudentNoteGateway;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Domain\School\YearGroupGateway;
use TawasulOS\Domain\FormGroups\FormGroupGateway;
use TawasulOS\Domain\Timetable\CourseEnrolmentGateway;
use TawasulOS\Domain\IndividualNeeds\INAssistantGateway;
use TawasulOS\Domain\User\UserStatusLogGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulAdmissions/student_withdraw.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/student_withdraw.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $userGateway = $container->get(UserGateway::class);
    $studentGateway = $container->get(StudentGateway::class);

    $data = [
        'status'          => $_POST['status'] ?? '',
        'dateEnd'         => isset($_POST['dateEnd']) ? Format::dateConvert($_POST['dateEnd']) : null,
        'departureReason' => $_POST['departureReason'] ?? '',
        'nextSchool'      => $_POST['nextSchool'] ?? '',
    ];

    // Validate the required values are present
    if (empty($tawasulPersonID) || empty($data['status']) || empty($data['dateEnd']) || empty($data['departureReason'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $person = $userGateway->getByID($tawasulPersonID);
    $student = $studentGateway->selectActiveStudentByPerson($session->get('tawasulSchoolYearID'), $tawasulPersonID)->fetch();

    if (empty($person) || empty($student)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Update the user data
    $updated = $userGateway->update($tawasulPersonID, $data);
    $partialFail = !$updated;

    if ($updated) {
        $withdrawNote = $_POST['withdrawNote'] ?? '';
        if (!empty($withdrawNote)) {
            $noteGateway = $container->get(StudentNoteGateway::class);
            $inserted = $noteGateway->insert([
                'title'                       => __('Student Withdrawn'),
                'note'                        => $withdrawNote,
                'tawasulPersonID'              => $tawasulPersonID,
                'tawasulPersonIDCreator'       => $session->get('tawasulPersonID'),
                'tawasulStudentNoteCategoryID' => $noteGateway->getNoteCategoryIDByName('Academic') ?? null,
                'timestamp'                   => date('Y-m-d H:i:s', time()),
            ]);

            $partialFail &= !$inserted;
        }

        if ($data['status'] != $person['status']) {
            $statusReason = !empty($data['departureReason']) 
                ? __('Student Withdrawn').': '.$data['departureReason'] 
                : __('Student Withdrawn');

            $userStatusLogGateway = $container->get(UserStatusLogGateway::class);
            $userStatusLogGateway->insert(['tawasulPersonID' => $tawasulPersonID, 'statusOld' => $person['status'], 'statusNew' => $data['status'], 'reason' => $statusReason, 'tawasulPersonIDModified' => $session->get('tawasulPersonID')]);
        }

        $notify = $_POST['notify'] ?? [];
        $notificationList = isset($_POST['notificationList'])? explode(',', $_POST['notificationList']) : [];

        if (!empty($notify) || !empty($notificationList)) {
            // Create the notification body
            $studentName = Format::name('', $student['preferredName'], $student['surname'], 'Student', false, true);
                
            $today = date("Y-m-d"); 
            if ($today > $data['dateEnd']) {
                $notificationString = __('{student} {formGroup} has withdrawn from {school} on {date}.', [
                    'student'   => $studentName,
                    'formGroup'   => $student['formGroup'],
                    'school'    => $session->get('organisationNameShort'),
                    'date'      => Format::date($data['dateEnd']),
                ]);
            } else {
                $notificationString = __('{student} {formGroup} will withdraw from {school}, effective from {date}.', [
                    'student'   => $studentName,
                    'formGroup'   => $student['formGroup'],
                    'school'    => $session->get('organisationNameShort'),
                    'date'      => Format::date($data['dateEnd']),
                ]);
            }
            
            if (!empty($withdrawNote)) {
                $notificationString .= '<br/><br/>'.__('Withdraw Note').': '.$withdrawNote;
            }

            // Raise a new notification event
            $event = new NotificationEvent('Admissions', 'Student Withdrawn');
            $event->addScope('tawasulPersonIDStudent', $tawasulPersonID);
            $event->addScope('tawasulYearGroupID', $student['tawasulYearGroupID']);
            $event->setNotificationText($notificationString);
            $event->setActionLink('/index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID='.$tawasulPersonID.'&search=&sort=&allStudents=on');

            // Notify Additional People
            foreach ($notificationList as $tawasulPersonIDNotify) {
                $event->addRecipient($tawasulPersonIDNotify);
            }

            // Admissions Administrator
            if (in_array('admin', $notify)) {
                $event->addRecipient($session->get('organisationAdmissions'));
            }

            // Head of Year
            if (in_array('HOY', $notify)) {
                $yearGroup = $container->get(YearGroupGateway::class)->getByID($student['tawasulYearGroupID']);
                $event->addRecipient($yearGroup['tawasulPersonIDHOY']);
            }

            // Form Tutors
            if (in_array('tutors', $notify)) {
                $formGroup = $container->get(FormGroupGateway::class)->getByID($student['tawasulFormGroupID']);
                $event->addRecipient($formGroup['tawasulPersonIDTutor']);
                $event->addRecipient($formGroup['tawasulPersonIDTutor2']);
                $event->addRecipient($formGroup['tawasulPersonIDTutor3']);
            }

            // Class Teachers
            if (in_array('teachers', $notify)) {
                $teachers = $container->get(CourseEnrolmentGateway::class)->selectClassTeachersByStudent($session->get('tawasulSchoolYearID'), $tawasulPersonID);
                foreach ($teachers as $teacher) {
                    $event->addRecipient($teacher['tawasulPersonID']);
                }
            }

            // Educational Assistants
            if (in_array('EAs', $notify)) {
                $EAs = $container->get(INAssistantGateway::class)->selectINAssistantsByStudent($tawasulPersonID);
                foreach ($EAs as $EA) {
                    $event->addRecipient($EA['tawasulPersonID']);
                }
            }

            // Add event listeners to the notification sender
            $event->sendNotifications($pdo, $session);
        }
    }

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
}
