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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\FormGroups\FormGroupGateway;
use TawasulOS\Domain\School\YearGroupGateway;
use TawasulOS\Domain\StudentAlerts\AlertGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\Timetable\CourseClassGateway;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Http\Url;
use TawasulOS\Services\Format;
use TawasulOS\Support\Facades\Access;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulAlertID = $_POST['tawasulAlertID'] ?? '';

$URL = Url::fromModuleRoute('TawasulStudentAlerts', 'studentAlerts_manage_status')->withQueryParams(['tawasulAlertID' => $tawasulAlertID]);
$URLSuccess = Url::fromModuleRoute('TawasulStudentAlerts', 'studentAlerts_manage');

if (!isActionAccessible($guid, $connection2, '/modules/TawasulStudentAlerts/studentAlerts_manage_status.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
}  else {
    // Proceed!
    $data = [
        'status'               => $_POST['status'] ?? '',
        'timestampStatus'      => date('Y-m-d H:i:s'),
        'notesStatus'          => $_POST['notesStatus'] ?? '',
        'tawasulPersonIDStatus' => $session->get('tawasulPersonID') ?? '',
    ];

    // Check for required values
    if (empty($tawasulAlertID) || empty($data['status'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Check if alert exists
    $alertGateway = $container->get(AlertGateway::class);
    $alert = $alertGateway->getByID($tawasulAlertID);
    if (empty($alert)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Check for access to Approval status
    $canApprove = Access::get('Student Alerts', 'studentAlerts_manage')->allowsAny('Manage Student Alerts_all', 'Manage Student Alerts_headOfYear');
    if ($data['status'] == 'Approved' && !$canApprove) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    // Check for existence of student
    $student = $container->get(StudentGateway::class)->selectActiveStudentByPerson($session->get('tawasulSchoolYearID'), $alert['tawasulPersonID'])->fetch();
    if (empty($student)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Check for alert creator
    $user = $container->get(UserGateway::class)->getByID($alert['tawasulPersonIDCreated'], ['preferredName', 'surname']);
    if (empty($user)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $updated = $alertGateway->update($tawasulAlertID, $data);
    if (!$updated) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Raise a new notification event
    $notificationData = [
        'user'      => Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true),
        'creator'   => Format::name('', $user['preferredName'], $user['surname'], 'Staff', false, true),
        'student'   => Format::name('', $student['preferredName'], $student['surname'], 'Student', false, true),
        'actioned'  => strtolower(__($data['status'])),
        'formGroup' => $student['formGroup'],
        'type'      => __($alert['type']),
    ];

    $notificationDetails = [
        __('Student')    => $notificationData['student'],
        __('Type')       => __($alert['type']),
        __('Level')      => __($alert['level']) ?? __('N/A'),
        __('Status')     => __($data['status']),
        __('Created By') => $notificationData['creator'],
        __('Comment')    => $alert['comment'],
        __('Updated By') => $notificationData['user'],
        __('Notes')      => $data['notesStatus'],
    ];

    if (!empty($alert['tawasulCourseClassID'])) {
        $class = $container->get(CourseClassGateway::class)->getCourseClassByID($alert['tawasulCourseClassID']);
        $notificationDetails = [
            __('Class') => Format::courseClassName($class['courseNameShort'] ?? '', $class['nameShort'] ?? ''),
        ] + $notificationDetails;
    }

    if ($data['status'] == 'Approved') {
        $event = new NotificationEvent('Student Alerts', !empty($alert['tawasulCourseClassID']) ? 'New Class Alert' : 'New Global Alert');
        $event->setNotificationDetails($notificationDetails);
        $event->setNotificationText(__('{user} has approved a {type} alert for {student} ({formGroup})', $notificationData));
        $event->setActionLink(Url::fromModuleRoute('TawasulStudentAlerts', 'studentAlerts_manage_view')->withQueryParams([
            'tawasulAlertID' => $tawasulAlertID,
        ])->withPath(''));
    } else {
        $event = new NotificationEvent('Student Alerts', 'Updated Student Alert');
        $event->setNotificationDetails($notificationDetails);
        $event->setNotificationText(__('{user} has {actioned} the {type} alert for {student} ({formGroup})', $notificationData));
        $event->setActionLink(Url::fromModuleRoute('TawasulStudentAlerts', 'studentAlerts_manage_view')->withQueryParams([
            'tawasulAlertID' => $tawasulAlertID,
        ])->withPath(''));
    }

    // Form Tutors
    $formGroup = $container->get(FormGroupGateway::class)->getByID($student['tawasulFormGroupID']);
    $event->addRecipient($formGroup['tawasulPersonIDTutor'] ?? '');
    $event->addRecipient($formGroup['tawasulPersonIDTutor2'] ?? '');
    $event->addRecipient($formGroup['tawasulPersonIDTutor3'] ?? '');

    // Head of Year
    $yearGroup = $container->get(YearGroupGateway::class)->getByID($student['tawasulYearGroupID']);
    if ($yearGroup['tawasulPersonIDHOY'] != $session->get('tawasulPersonID')) {
        $event->addRecipient($yearGroup['tawasulPersonIDHOY'] ?? '');
    }

    $event->addScope('tawasulPersonIDStudent',  $student['tawasulPersonID']);
    $event->addScope('tawasulYearGroupID', $student['tawasulYearGroupID']);

    $event->sendNotifications($pdo, $session);


    $URLSuccess .= '&return=success0';
    header("Location: {$URLSuccess}");
}




