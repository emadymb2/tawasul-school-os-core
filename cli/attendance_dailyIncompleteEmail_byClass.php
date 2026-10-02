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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Services\Format;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Comms\NotificationSender;
use TawasulOS\Domain\School\SchoolYearSpecialDayGateway;
use TawasulOS\Domain\System\NotificationGateway;

require getcwd().'/../tawasul.php';

$settingGateway = $container->get(SettingGateway::class);

//Check for CLI, so this cannot be run through browser
$remoteCLIKey = $settingGateway->getSettingByScope('System Admin', 'remoteCLIKey');
$remoteCLIKeyInput = $_GET['remoteCLIKey'] ?? null;
if (!(isCommandLineInterface() OR ($remoteCLIKey != '' AND $remoteCLIKey == $remoteCLIKeyInput))) {
    echo __('This script cannot be run from a browser, only via CLI.');
} else {
    $currentDate = date('Y-m-d');

    if (isSchoolOpen($guid, $currentDate, $connection2, true)) {
        $report = '';
        $reportInner = '';

        $partialFail = false;

        $userReport = array();
        $adminReport = array( 'classes' => array(), 'classCount' => 0 );

        $enabledByClass = $settingGateway->getSettingByScope('Attendance', 'attendanceCLINotifyByClass');
        $additionalUsersList = $settingGateway->getSettingByScope('Attendance', 'attendanceCLIAdditionalUsers');

        if ($enabledByClass != 'Y') {
            die('Attendance CLI cancelled: Notifications not enabled in Attendance Settings.');
        }

        $specialDay = $container->get(SchoolYearSpecialDayGateway::class)->getSpecialDayByDate($currentDate);
        $tawasulYearGroupIDList = !empty($specialDay) && $specialDay['type'] == 'Off Timetable' ? $specialDay['tawasulYearGroupIDList']  : '';
        $tawasulFormGroupIDList = !empty($specialDay) && $specialDay['type'] == 'Off Timetable'  ? $specialDay['tawasulFormGroupIDList'] : '';
            

        //Produce array of attendance data for Classes ------------------------------------------------------------------------------------------------------
        if ($enabledByClass == 'Y') {
            try {
                $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'date' => $currentDate, 'time' => date("H:i:s"));

                // Looks for only courses that are scheduled on the current day and have attendance='Y', also grabs tutor name
                $sql = "SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourseClass.name as class, tawasulCourse.name as course, tawasulCourse.nameShort as courseShort, tawasulCourse.tawasulYearGroupIDList, tawasulCourseClassPerson.tawasulPersonID, tawasulPerson.preferredName, tawasulPerson.surname
                FROM tawasulCourseClass
                JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID)
                JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulTTDayDate.date=:date
                AND tawasulTTColumnRow.timeStart<=:time
                AND tawasulCourseClassPerson.role='Teacher'
                AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulCourseClass.attendance='Y'
                AND tawasulTTDayRowClassException.tawasulTTDayRowClassExceptionID IS NULL
                AND tawasulPerson.status='Full'
                ORDER BY tawasulPerson.surname, tawasulCourse.nameShort, tawasulCourseClass.nameShort";

                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $partialFail = true;
            }

            // Proceed if we have attendance-able Classes
            if ($result->rowCount() > 0) {

                try {
                    $data = array('date' => $currentDate);
                    $sql = 'SELECT tawasulCourseClassID FROM tawasulAttendanceLogCourseClass WHERE date=:date';
                    $resultLog = $connection2->prepare($sql);
                    $resultLog->execute($data);
                } catch (PDOException $e) {
                    $partialFail = true;
                }

                // Gather the current Class logs for the day
                $log = array();
                while ($row = $resultLog->fetch()) {
                    $log[$row['tawasulCourseClassID']] = true;
                }

                while ($row = $result->fetch()) {
                    // Check for students who are active and do not have Off Timetable days by year group or form group
                    $dataClassCheck = ['tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulCourseClassID' => $row['tawasulCourseClassID'], 'tawasulYearGroupIDList' => $tawasulYearGroupIDList, 'tawasulFormGroupIDList' => $tawasulFormGroupIDList, 'date' => $currentDate];
                    $sqlClassCheck = "SELECT count(*) FROM tawasulCourseClassPerson 
                        JOIN tawasulPerson AS student ON (tawasulCourseClassPerson.tawasulPersonID=student.tawasulPersonID) 
                        JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=student.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID) 
                        WHERE tawasulCourseClassPerson.role='Student' AND student.status='Full' 
                        AND tawasulCourseClassPerson.tawasulCourseClassID=:tawasulCourseClassID 
                        AND (student.dateStart IS NULL OR student.dateStart<=:date) 
                        AND (student.dateEnd IS NULL OR student.dateEnd>=:date) 
                        AND NOT FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, :tawasulYearGroupIDList) 
                        AND NOT FIND_IN_SET(tawasulStudentEnrolment.tawasulFormGroupID, :tawasulFormGroupIDList)";
                    
                    // Skip classes with no students
                    $studentCount = $pdo->selectOne($sqlClassCheck, $dataClassCheck);
                    if ($studentCount <= 0) continue;

                    // Check for a current log
                    if (isset($log[$row['tawasulCourseClassID']]) == false) {

                        $className = $row['course'].' ('.$row['courseShort'].'.'.$row['class'].')';
                        $classInfo = array( 'tawasulCourseClassID' => $row['tawasulCourseClassID'], 'name' => $className );

                        // Compile info for Admin report
                        $adminReport['classes'][ $row['preferredName'].' '.$row['surname'] ][] = $className;

                        // Compile info for User reports
                        if ($row['tawasulPersonID'] != '') {
                            $userReport[ $row['tawasulPersonID'] ]['classes'][] = $classInfo;
                        }
                    }
                }

                // Use the class counts to generate reports
                if ( isset($adminReport['classes']) && count($adminReport['classes']) > 0) {
                    $reportInner = '';

                    // Output the reports grouped by teacher
                    foreach ($adminReport['classes'] as $teacherName => $classes) {
                        $reportInner .= '<b>' . $teacherName;
                        $reportInner .= (count($classes) > 1)? ' ('.count($classes).')</b><br/>' : '</b><br/>';
                        foreach ($classes as $className) {
                            $adminReport['classCount']++;
                            $reportInner .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $className .'<br/>';
                        }
                        $reportInner .= '<br>';
                    }

                    $report .= '<br/><br/>';
                    $report .= sprintf(__('%1$s classes have not been registered today (%2$s).'), $adminReport['classCount'], Format::date($currentDate)).'<br/><br/>'.$reportInner;
                } else {
                    $report .= '<br/><br/>';
                    $report .= sprintf(__('All classes have been registered today (%1$s).'), Format::date($currentDate));
                }
            }
        }

        // Initialize the notification sender & gateway objects
        $notificationGateway = $container->get(NotificationGateway::class);
        $notificationSender = $container->get(NotificationSender::class);

        // Raise a new notification event
        $event = new NotificationEvent('Attendance', 'Daily Attendance Summary');

        if ($event->getEventDetails($notificationGateway, 'active') == 'Y' && $partialFail == false) {
            //Notify non-completing tutors
            foreach ($userReport as $tawasulPersonID => $items ) {
                $notificationText = __('You have not taken attendance yet today. Please do so as soon as possible.');

                if ($enabledByClass == 'Y') {
                    // Output the classes the particular user is a part of
                    if ( isset($items['classes']) && count($items['classes']) > 0) {
                        $notificationText .= '<br/><br/>';
                        $notificationText .= '<b>'.__('Classes').':</b><br/>';
                        foreach ($items['classes'] as $class) {
                            $notificationText .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $class['name'] .'<br/>';
                        }
                        $notificationText .= '<br/>';
                    }
                }

                $notificationSender->addNotification($tawasulPersonID, $notificationText, 'Attendance', '/index.php?q=/modules/TawasulAttendance/attendance.php&currentDate='.Format::date(date('Y-m-d')));
            }

            // Notify Additional Users
            if (!empty($additionalUsersList)) {
                $additionalUsers = explode(',', $additionalUsersList);

                if (is_array($additionalUsers) && count($additionalUsers) > 0) {
                    foreach ($additionalUsers as $tawasulPersonID) {
                        // Confirm that this user still has permission to access these reports
                        try {
                            $data = array( 'tawasulPersonID' => $tawasulPersonID, 'action1' => '%report_formGroupsNotRegistered_byDate.php%', 'action2' => '%report_courseClassesNotRegistered_byDate.php%' );
                            $sql = "SELECT tawasulAction.name FROM tawasulAction, tawasulPermission, tawasulRole, tawasulPerson WHERE (tawasulAction.URLList LIKE :action1 OR tawasulAction.URLList LIKE :action2) AND (tawasulAction.tawasulActionID=tawasulPermission.tawasulActionID) AND (tawasulPermission.tawasulRoleID=tawasulRole.tawasulRoleID) AND (tawasulPermission.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary) AND (tawasulPerson.tawasulPersonID=:tawasulPersonID) AND (tawasulAction.tawasulModuleID=(SELECT tawasulModuleID FROM tawasulModule WHERE name='TawasulAttendance'))";
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        }  catch (PDOException $e) {}

                        if ($result->rowCount() > 0) {
                            $event->addRecipient($tawasulPersonID);
                        }
                    }
                }
            }

        } else if ($partialFail) {
            // Notify admin if there was an error in the report
            $report = __('Your request failed due to a database error.') . '<br/><br/>' . $report;
        }

        $event->setNotificationText(__('An Attendance CLI script has run.').' '.$report);
        $event->setActionLink('/index.php?q=/modules/TawasulAttendance/report_courseClassesNotRegistered_byDate.php');

        // Add admin, then push the event to the notification sender
        $event->addRecipient($session->get('organisationAdministrator'));
        $event->pushNotifications($notificationGateway, $notificationSender);

        // Send all notifications
        $sendReport = $notificationSender->sendNotifications();

        // Output the result to terminal
        echo sprintf('Sent %1$s notifications: %2$s inserts, %3$s updates, %4$s emails sent, %5$s emails failed.', $sendReport['count'], $sendReport['inserts'], $sendReport['updates'], $sendReport['emailSent'], $sendReport['emailFailed'])."\n";
    }
}
