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
use TawasulOS\Http\Url;
use TawasulOS\Services\Format;
use TawasulOS\Domain\FormGroups\FormGroupGateway;
use TawasulOS\Domain\School\YearGroupGateway;
use TawasulOS\Domain\StudentAlerts\AlertGateway;
use TawasulOS\Domain\StudentAlerts\AlertTypeGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\System\AlertLevelGateway;
use TawasulOS\Domain\Timetable\CourseClassGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['comment' => 'HTML']);

$params = [
    'tawasulFormGroupID'   => $_POST['tawasulFormGroupID'] ?? '',
    'tawasulYearGroupID'   => $_POST['tawasulYearGroupID'] ?? '',
    'tawasulCourseClassID' => $_POST['tawasulCourseClassID'] ?? '',
    'source'              => $_POST['source'] ?? '',
];

$URL = $URLSuccess = Url::fromModuleRoute('TawasulStudentAlerts', 'studentAlerts_add')->withQueryParams($params);

if (!empty($params['source']) && $params['source'] == 'class') {
    $URLSuccess = Url::fromModuleRoute('TawasulStudentAlerts', 'report_alertsByClass')->withQueryParams($params);
} elseif (!empty($params['source']) && $params['source'] == 'formGroup') {
    $URLSuccess = Url::fromModuleRoute('TawasulStudentAlerts', 'report_alertsByFormGroup')->withQueryParams($params);
}

if (!isActionAccessible($guid, $connection2, '/modules/TawasulStudentAlerts/studentAlerts_add.php')) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    $partialFail = false;
    $alertGateway = $container->get(AlertGateway::class);
    $alertLevelGateway = $container->get(AlertLevelGateway::class);
    $alertTypeGateway = $container->get(AlertTypeGateway::class);

    $data = [
        'tawasulSchoolYearID'    => $session->get('tawasulSchoolYearID') ?? '',
        'tawasulPersonID'        => $_POST['tawasulPersonID'] ?? '',
        'tawasulCourseClassID'   => $_POST['tawasulCourseClassID'] ?? null,
        'type'                  => $_POST['type'] ?? '',
        'level'                 => $_POST['level'] ?? null,
        'comment'               => $_POST['comment'] ?? '',
        'status'                => $_POST['status'] ?? 'Pending',
        'context'               => 'Manual',
        'dateStart'             => !empty($_POST['dateStart']) ? Format::dateConvert($_POST['dateStart']) : null,
        'dateEnd'               => !empty($_POST['dateEnd']) ? Format::dateConvert($_POST['dateEnd']) : null,
        'tawasulPersonIDCreated' => $session->get('tawasulPersonID') ?? '',
    ];

    // Check required values
    $alertType = $alertTypeGateway->selectBy(['name' => $data['type']])->fetch();
    if (empty($alertType) || empty($data['type']) || empty($data['tawasulPersonID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Check for existence of student
    $student = $container->get(StudentGateway::class)->selectActiveStudentByPerson($session->get('tawasulSchoolYearID'), $data['tawasulPersonID'])->fetch();
    if (empty($student)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Ensure levels are turned off if not in use
    $data['tawasulAlertTypeID'] = $alertType['tawasulAlertTypeID'];
    if ($alertType['useLevels'] == 'N') {
        $data['tawasulAlertLevelID'] = null;
        $data['level'] = null;
    }

    // Set level ID based on level selected
    if (!empty($data['level'])) {
        if ($alertLevel = $alertLevelGateway->selectBy(['name' => $data['level']])->fetch()) {
            $data['tawasulAlertLevelID'] = $alertLevel['tawasulAlertLevelID'];
        }
    }

    // Create the alert
    $tawasulAlertID = $alertGateway->insert($data);
    if (empty($tawasulAlertID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Raise a new notification event
    $notificationData = [
        'user'      => Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true),
        'student'   => Format::name('', $student['preferredName'], $student['surname'], 'Student', false, true),
        'formGroup' => $student['formGroup'],
        'type'      => __($data['type']),
    ];

    $notificationDetails = [
        __('Student')    => $notificationData['student'],
        __('Type')       => __($data['type']),
        __('Level')      => __($data['level']) ?? __('N/A'),
        __('Created By') => $notificationData['user'],
        __('Comment')    => $data['comment'],
    ];

    if (!empty($data['tawasulCourseClassID'])) {
        $class = $container->get(CourseClassGateway::class)->getCourseClassByID($data['tawasulCourseClassID']);
        $notificationDetails = [
            __('Class') => Format::courseClassName($class['courseNameShort'] ?? '', $class['nameShort'] ?? ''),
        ] + $notificationDetails;
    }

    if ($data['status'] == 'Pending') {
        $event = new NotificationEvent('Student Alerts', 'Pending Student Alert');
        $event->setNotificationDetails($notificationDetails);
        $event->setNotificationText(__('{user} has added a pending {type} alert for {student} ({formGroup}), which requires approval', $notificationData));
        $event->setActionLink(Url::fromModuleRoute('TawasulStudentAlerts', 'studentAlerts_manage_status')->withQueryParams([
            'tawasulAlertID' => $tawasulAlertID,
            'status'        => 'Approved',
        ])->withPath(''));

        // Head of Year
        $yearGroup = $container->get(YearGroupGateway::class)->getByID($student['tawasulYearGroupID']);
        $event->addRecipient($yearGroup['tawasulPersonIDHOY'] ?? '');
    } else {
        $event = new NotificationEvent('Student Alerts', !empty($data['tawasulCourseClassID']) ? 'New Class Alert' : 'New Global Alert');
        $event->setNotificationDetails($notificationDetails);
        $event->setNotificationText(!empty($data['tawasulCourseClassID'])
                ? __('{user} has added a new class-level {type} alert for {student} ({formGroup})', $notificationData)
                : __('{student} ({formGroup}) has a new {type} alert', $notificationData)
            );
        $event->setActionLink(Url::fromModuleRoute('TawasulStudentAlerts', 'studentAlerts_manage_view')->withQueryParams([
            'tawasulAlertID' => $tawasulAlertID,
        ])->withPath(''));

        // Form Tutors
        $formGroup = $container->get(FormGroupGateway::class)->getByID($student['tawasulFormGroupID']);
        $event->addRecipient($formGroup['tawasulPersonIDTutor'] ?? '');
        $event->addRecipient($formGroup['tawasulPersonIDTutor2'] ?? '');
        $event->addRecipient($formGroup['tawasulPersonIDTutor3'] ?? '');

        // Head of Year
        $yearGroup = $container->get(YearGroupGateway::class)->getByID($student['tawasulYearGroupID']);
        $event->addRecipient($yearGroup['tawasulPersonIDHOY'] ?? '');
    }

    $event->addScope('tawasulPersonIDStudent',  $student['tawasulPersonID']);
    $event->addScope('tawasulYearGroupID', $student['tawasulYearGroupID']);

    $event->sendNotifications($pdo, $session);

    $URLSuccess .= $partialFail
        ? "&return=warning1"
        : "&return=success0&editID=$tawasulAlertID";

    header("Location: {$URLSuccess}");     
}
