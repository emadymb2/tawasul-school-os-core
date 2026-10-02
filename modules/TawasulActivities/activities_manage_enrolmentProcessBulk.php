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

use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Activities\ActivityStudentGateway;
use TawasulOS\Domain\Activities\ActivityStaffGateway;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Services\Format;
use TawasulOS\Domain\System\LogGateway;

require_once __DIR__ . '/../../tawasul.php';

$action = $_POST['action'] ?? '';
$tawasulActivityID = $_POST['tawasulActivityID'] ?? '';

$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulActivities/activities_manage_enrolment.php&tawasulActivityID=$tawasulActivityID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage_enrolment.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    $enrolments = $_POST['tawasulActivityStudentID'] ?? [];

    if (empty($action) || ($action != 'Mark as Left' && $action != 'Mark as Accepted' && $action != 'Delete')) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } 
    
    // Check if person specified
    if (empty($enrolments)) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    } 

    $activity = $container->get(ActivityGateway::class)->getByID($tawasulActivityID);
    if (empty($activity)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    } 

    $activityStudentGateway = $container->get(ActivityStudentGateway::class);
    $activityStaffGateway = $container->get(ActivityStaffGateway::class);
    $userGateway = $container->get(UserGateway::class);
    $logGateway = $container->get(LogGateway::class);

    $partialFail = false;
    $students = [];
    
    foreach ($enrolments AS $tawasulActivityStudentID) {
        $activityStudent = $activityStudentGateway->getByID($tawasulActivityStudentID);
        $student = $userGateway->getUserDetails($activityStudent['tawasulPersonID'] ?? '', $session->get('tawasulSchoolYearID'));

        if (empty($activityStudent) || empty($student)) {
            $partialFail = true;
            continue;
        }

        $studentName = Format::name('', $student['preferredName'], $student['surname'], 'Student', false, false).' ('.$student['formGroup'].')';
        $students[] = $studentName;

        if ($action == 'Mark as Accepted') {
            $data = ['tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $activityStudent['tawasulPersonID'], 'status' => 'Accepted'];
            $activityStudentGateway->update($activityStudent['tawasulActivityStudentID'], $data);
        } elseif ($action == 'Mark as Left') {
            $data = ['tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $activityStudent['tawasulPersonID'], 'status' => 'Left'];
            $activityStudentGateway->update($activityStudent['tawasulActivityStudentID'], $data);
        } else if ($action == 'Delete') {
            $activityStudentGateway->delete($activityStudent['tawasulActivityStudentID']);
        }
    }

    // Raise a new notification event
    $event = new NotificationEvent('Activities', 'Activity Status Changed');
    
    if ($action == 'Mark as Accepted') {
        $notificationText =  __('The following participants have been set to {status} in {name}', ['name' => $activity['name'], 'status' => __('Accepted') ]).':<br/>'.Format::list($students);
    } elseif ($action == 'Mark as Left') {
        $notificationText =  __('The following participants have been set to {status} in {name}', ['name' => $activity['name'], 'status' => __('Left') ]).':<br/>'.Format::list($students);
    } else if ($action == 'Delete') {
        $notificationText = __('The following participants have been removed from the activity {name}', ['name' => $activity['name']]).':<br/>'.Format::list($students);
    }
    
    $event->setNotificationText($notificationText);
    $event->setActionLink('/index.php?q=/modules/TawasulActivities/activities_manage_enrolment.php&tawasulActivityID='.$tawasulActivityID.'&search=&tawasulSchoolYearTermID=');

    $activityStaff = $activityStaffGateway->selectActivityStaff($tawasulActivityID)->fetchAll();
    foreach ($activityStaff as $staff) {
        $event->addRecipient($staff['tawasulPersonID']);
    }

    $event->sendNotifications($pdo, $session);
    
    // Set log
    $logGateway->addLog($session->get('tawasulSchoolYearIDCurrent'), 'Activities', $session->get('tawasulPersonID'), $action == 'Delete' ? 'Activities - Student Deleted' : 'Activities - Student Status Changed', ['students' => implode(',', $students)]);

    $URL .= $partialFail
        ? '&return=warning1'
        : '&return=success0';
    header("Location: {$URL}");
}
