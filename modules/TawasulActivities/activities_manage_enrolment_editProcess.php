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
use TawasulOS\Data\Validator;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Activities\ActivityStudentGateway;
use TawasulOS\Domain\Activities\ActivityStaffGateway;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Services\Format;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$logGateway = $container->get(LogGateway::class);
$tawasulActivityID = $_GET['tawasulActivityID'] ?? '';
$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage_enrolment_edit.php') == false) { 
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/activities_manage_enrolment_edit.php&tawasulPersonID=$tawasulPersonID&tawasulActivityID=$tawasulActivityID&search=".$_GET['search']."&tawasulSchoolYearTermID=".$_GET['tawasulSchoolYearTermID'];

    if ($tawasulActivityID == '' or $tawasulPersonID == '') {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    } 

    $student = $container->get(UserGateway::class)->getUserDetails($tawasulPersonID, $session->get('tawasulSchoolYearID'));
    if (empty($student)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }
    
    // Check if status specified
    $status = $_POST['status'] ?? '';
    if (empty($status)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $activityStudentGateway = $container->get(ActivityStudentGateway::class);
    $activityStaffGateway = $container->get(ActivityStaffGateway::class);

    $activity = $container->get(ActivityGateway::class)->getByID($tawasulActivityID);
    $activityStudent = $activityStudentGateway->selectBy(['tawasulPersonID' => $tawasulPersonID, 'tawasulActivityID' => $tawasulActivityID])->fetch();

    if (empty($activity) || empty($activityStudent)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }
    
    $statusOld = $activity['status'];

    // Write to database
    $data = ['tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $tawasulPersonID, 'status' => $status];
    $activityStudentGateway->update($activityStudent['tawasulActivityStudentID'], $data);

    
    if ($statusOld != $status) {
        // Raise a new notification event
        $event = new NotificationEvent('Activities', 'Activity Status Changed');
        $studentName = Format::name('', $student['preferredName'], $student['surname'], 'Student', false, false).' ('.$student['formGroup'].')';
        
        $notificationText = __('The following participants have been set to {status} in {name}', ['name' => $activity['name'], 'status' => __($status) ]).':<br/>'.Format::list([$studentName]);
        
        $event->setNotificationText($notificationText);
        $event->setActionLink('/index.php?q=/modules/TawasulActivities/activities_manage_enrolment.php&tawasulActivityID='.$tawasulActivityID.'&search=&tawasulSchoolYearTermID=');

        $activityStaff = $activityStaffGateway->selectActivityStaff($tawasulActivityID)->fetchAll();
        foreach ($activityStaff as $staff) {
            $event->addRecipient($staff['tawasulPersonID']);
        }

        $event->sendNotifications($pdo, $session);
        
        // Set log
        $logGateway->addLog($session->get('tawasulSchoolYearIDCurrent'), 'Activities', $session->get('tawasulPersonID'), 'Activities - Student Status Changed', array('tawasulPersonIDStudent' => $tawasulPersonID, 'statusOld' => $statusOld, 'statusNew' => $status));
    }

    $URL .= '&return=success0';
    header("Location: {$URL}");
}
