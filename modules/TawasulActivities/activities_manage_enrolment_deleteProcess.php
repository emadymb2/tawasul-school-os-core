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

use TawasulOS\Domain\System\LogGateway;
use TawasulOS\Services\Format;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\Activities\ActivityStudentGateway;
use TawasulOS\Domain\Activities\ActivityStaffGateway;
use TawasulOS\Domain\Activities\ActivityGateway;

require_once __DIR__ . '/../../tawasul.php';

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$logGateway = $container->get(LogGateway::class);
$tawasulActivityID = $_POST['tawasulActivityID'] ?? '';
$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$tawasulActivityStudentID = $_POST['tawasulActivityStudentID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/activities_manage_enrolment_delete.php&tawasulPersonID=$tawasulPersonID&tawasulActivityID=$tawasulActivityID&search=".$_GET['search']."&tawasulSchoolYearTermID=".($_GET['tawasulSchoolYearTermID'] ?? '');
$URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/activities_manage_enrolment.php&tawasulActivityID=$tawasulActivityID&search=".$_GET['search']."&tawasulSchoolYearTermID=".($_GET['tawasulSchoolYearTermID'] ?? '');

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage_enrolment_delete.php') == false) { 
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    if (empty($tawasulActivityID) || empty($tawasulPersonID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
    
    $student = $container->get(UserGateway::class)->getUserDetails($tawasulPersonID, $session->get('tawasulSchoolYearID'));
    if (empty($student)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $activityStudentGateway = $container->get(ActivityStudentGateway::class);
    $activityStaffGateway = $container->get(ActivityStaffGateway::class);

    $activity = $container->get(ActivityGateway::class)->getByID($tawasulActivityID);
    $activityStudent = $activityStudentGateway->getByID($tawasulActivityStudentID);

    if (empty($activity) || empty($activityStudent)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Write to database
    $activityStudentGateway->delete($tawasulActivityStudentID);

    // Raise a new notification event
    $event = new NotificationEvent('Activities', 'Activity Enrolment Removed');
    $studentName = Format::name('', $student['preferredName'], $student['surname'], 'Student', false, false).' ('.$student['formGroup'].')';
    
    $notificationText = __('The following participants have been removed from the activity {name}', ['name' => $activity['name']]).':<br/>'.Format::list([$studentName]);
    
    $event->setNotificationText($notificationText);
    $event->setActionLink('/index.php?q=/modules/TawasulActivities/activities_manage_enrolment.php&tawasulActivityID='.$tawasulActivityID.'&search=&tawasulSchoolYearTermID=');

    $activityStaff = $activityStaffGateway->selectActivityStaff($tawasulActivityID)->fetchAll();
    foreach ($activityStaff as $staff) {
        $event->addRecipient($staff['tawasulPersonID']);
    }

    $event->sendNotifications($pdo, $session);

    // Set log
    $logGateway->addLog($session->get('tawasulSchoolYearIDCurrent'), 'Activities', $session->get('tawasulPersonID'), 'Activities - Student Deleted', ['tawasulPersonIDStudent' => $tawasulPersonID]);
    
    $URLDelete = $URLDelete.'&return=success0';
    header("Location: {$URLDelete}");
}
