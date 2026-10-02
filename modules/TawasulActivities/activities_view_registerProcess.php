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

use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Domain\System\LogGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Services\Format;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$logGateway = $container->get(LogGateway::class);
$mode = $_POST['mode'] ?? '';
$tawasulActivityID = $_POST['tawasulActivityID'] ?? '';
$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/activities_view_register.php&tawasulActivityID=$tawasulActivityID&tawasulPersonID=$tawasulPersonID&mode=$mode&search=".$_GET['search'];
$URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/activities_view.php&tawasulPersonID=$tawasulPersonID&search=".$_GET['search'];

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_view_register.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    $highestAction = getHighestGroupedAction($guid, '/modules/TawasulActivities/activities_view_register.php', $connection2);
    if ($highestAction == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    } else {
        $settingGateway = $container->get(SettingGateway::class);
        $activityGateway = $container->get(ActivityGateway::class);

        //Get current role category
        $roleCategory = $session->get('tawasulRoleIDCurrentCategory');

        //Check access controls
        $access = $settingGateway->getSettingByScope('Activities', 'access');

        if ($access != 'Register') {
            //Fail0
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        } else {
            //Proceed!
            //Check if tawasulActivityID and tawasulPersonID specified
            if ($tawasulActivityID == '' or $tawasulPersonID == '') {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit;
            } else {
                $today = date('Y-m-d');
                //Should we show date as term or date?
                $dateType = $settingGateway->getSettingByScope('Activities', 'dateType');

                try {
                    if ($dateType != 'Date') {
                        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID, 'tawasulActivityID' => $tawasulActivityID);
                        $sql = "SELECT DISTINCT tawasulActivity.*, tawasulStudentEnrolment.tawasulYearGroupID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulActivityType.access, tawasulActivityType.maxPerStudent, tawasulActivityType.enrolmentType, tawasulActivityType.waitingList, tawasulActivityType.backupChoice FROM tawasulActivity JOIN tawasulStudentEnrolment ON (tawasulActivity.tawasulYearGroupIDList LIKE concat( '%', tawasulStudentEnrolment.tawasulYearGroupID, '%' )) JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) LEFT JOIN tawasulActivityType ON (tawasulActivity.type=tawasulActivityType.name) WHERE tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID AND tawasulActivityID=:tawasulActivityID AND NOT tawasulSchoolYearTermIDList='' AND active='Y' AND registration='Y'";
                    } else {
                        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID, 'tawasulActivityID' => $tawasulActivityID, 'listingStart' => $today, 'listingEnd' => $today);
                        $sql = "SELECT DISTINCT tawasulActivity.*, tawasulStudentEnrolment.tawasulYearGroupID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulActivityType.access, tawasulActivityType.maxPerStudent, tawasulActivityType.enrolmentType, tawasulActivityType.waitingList, tawasulActivityType.backupChoice FROM tawasulActivity JOIN tawasulStudentEnrolment ON (tawasulActivity.tawasulYearGroupIDList LIKE concat( '%', tawasulStudentEnrolment.tawasulYearGroupID, '%' )) JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) LEFT JOIN tawasulActivityType ON (tawasulActivity.type=tawasulActivityType.name) WHERE tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID AND tawasulActivityID=:tawasulActivityID AND listingStart<=:listingStart AND listingEnd>=:listingEnd AND active='Y' AND registration='Y'";
                    }
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit;
                }

                if ($result->rowCount() < 1) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit;
                } else {
                    $row = $result->fetch();

                    // Grab organizer info for notifications
                    try {
                        $dataStaff = array('tawasulActivityID' => $tawasulActivityID);
                        $sqlStaff = "SELECT tawasulPersonID FROM tawasulActivityStaff WHERE tawasulActivityID=:tawasulActivityID AND role='Organiser'";
                        $resultStaff = $connection2->prepare($sqlStaff);
                        $resultStaff->execute($dataStaff);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit;
                    }

                    $tawasulActivityStaffIDs = ($resultStaff->rowCount() > 0)? $resultStaff->fetchAll(\PDO::FETCH_COLUMN, 0) : array();

                    //Check for existing registration
                    try {
                        $dataReg = array('tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $tawasulPersonID);
                        $sqlReg = 'SELECT tawasulActivityStudentID, status FROM tawasulActivityStudent WHERE tawasulActivityID=:tawasulActivityID AND tawasulPersonID=:tawasulPersonID';
                        $resultReg = $connection2->prepare($sqlReg);
                        $resultReg->execute($dataReg);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit;
                    }

                    if ($mode == 'register') {

                        if ($resultReg->rowCount() > 0) {
                            $URL .= '&return=error3';
                            header("Location: {$URL}");
                            exit;
                        } else {
                            // Load the backupChoice system setting, optionally override with the Activity Type setting
                            $backupChoice = $settingGateway->getSettingByScope('Activities', 'backupChoice');
                            $backupChoice = !empty($row['backupChoice'])? $row['backupChoice'] : $backupChoice;

                            $tawasulActivityIDBackup = ($backupChoice == 'Y')? $_POST['tawasulActivityIDBackup'] : '';
                            $activityCountByType = $activityGateway->getStudentActivityCountByType($row['type'], $tawasulPersonID);

                            if (!empty($row['access']) && $row['access'] != 'Register') {
                                $URL .= '&return=error0';
                                header("Location: {$URL}");
                                exit;
                            } else if ($row['maxPerStudent'] > 0 && $activityCountByType >= $row['maxPerStudent']) {
                                $URL .= '&return=error1';
                                header("Location: {$URL}");
                                exit;
                            } else if ($backupChoice == 'Y' and $tawasulActivityIDBackup == '') {
                                $URL .= '&return=error1';
                                header("Location: {$URL}");
                                exit;
                            } else {
                                $status = 'Not Accepted';

                                // Load the enrolmentType system setting, optionally override with the Activity Type setting
                                $enrolment = $settingGateway->getSettingByScope('Activities', 'enrolmentType');
                                $enrolment = !empty($row['enrolmentType'])? $row['enrolmentType'] : $enrolment;

                                if ($enrolment == 'Selection') {
                                    $status = 'Pending';
                                } else {
                                    //Check number of people registered for this activity (if we ignore status it stops people jumping the queue when someone unregisters)
                                    $dataNumberRegistered = array('tawasulActivityID' => $tawasulActivityID, 'today' => date('Y-m-d'));
                                    $sqlNumberRegistered = "SELECT * FROM tawasulActivityStudent JOIN tawasulPerson ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today) AND tawasulActivityID=:tawasulActivityID";
                                    $resultNumberRegistered = $connection2->prepare($sqlNumberRegistered);
                                    $resultNumberRegistered->execute($dataNumberRegistered);

                                    //If activity is full...
                                    if ($resultNumberRegistered->rowCount() >= $row['maxParticipants']) {
                                        if ($row['waitingList'] == 'Y') {
                                            $status = 'Waiting List';
                                        } else {
                                            $URL .= '&return=error1';
                                            header("Location: {$URL}");
                                            exit;
                                        }
                                    } else {
                                        $status = 'Accepted';
                                    }
                                }

                                //Write to database
                                try {
                                    $data = array('tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $tawasulPersonID, 'status' => $status, 'timestamp' => date('Y-m-d H:i:s', time()), 'tawasulActivityIDBackup' => $tawasulActivityIDBackup);
                                    $sql = 'INSERT INTO tawasulActivityStudent SET tawasulActivityID=:tawasulActivityID, tawasulPersonID=:tawasulPersonID, status=:status, timestamp=:timestamp, tawasulActivityIDBackup=:tawasulActivityIDBackup';
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $URL .= '&return=error2';
                                    header("Location: {$URL}");
                                    exit;
                                }

                                //Set log
                                $logGateway->addLog($session->get('tawasulSchoolYearIDCurrent'), 'Activities', $session->get('tawasulPersonID'), 'Activities - Student Registered', array('tawasulPersonIDStudent' => $tawasulPersonID));

                                // Get the start and end date of the activity, depending on which dateType we're using
                                $activityTimespan = getActivityTimespan($connection2, $tawasulActivityID, $row['tawasulSchoolYearTermIDList']);

                                // Is the activity running right now?
                                if (time() >= $activityTimespan['start'] && time() <= $activityTimespan['end']) {
                                    // Raise a new notification event
                                    $event = new NotificationEvent('Activities', 'New Activity Registration');

                                    $studentName = Format::name('', $row['preferredName'], $row['surname'], 'Student', false);
                                    $notificationText = sprintf(__('%1$s has registered for the activity %2$s (%3$s)'), $studentName, $row['name'], $status);

                                    $event->setNotificationText($notificationText);
                                    $event->setActionLink('/index.php?q=/modules/TawasulActivities/activities_manage_enrolment.php&tawasulActivityID='.$tawasulActivityID.'&search=&tawasulSchoolYearTermID=');

                                    $event->addScope('tawasulPersonIDStudent', $tawasulPersonID);
                                    $event->addScope('tawasulYearGroupID', $row['tawasulYearGroupID']);

                                    foreach ($tawasulActivityStaffIDs as $tawasulPersonIDStaff) {
                                        $event->addRecipient($tawasulPersonIDStaff);
                                    }

                                    $event->sendNotifications($pdo, $session);
                                }

                                if ($status == 'Waiting List') {
                                    $URLSuccess = $URLSuccess.'&return=success2';
                                    header("Location: {$URLSuccess}");
                                    exit;
                                } else {
                                    $URLSuccess = $URLSuccess.'&return=success0';
                                    header("Location: {$URLSuccess}");
                                    exit;
                                }
                            }
                        }
                    } elseif ($mode == 'unregister') {

                        if ($resultReg->rowCount() < 1) {
                            $URL .= '&return=error3';
                            header("Location: {$URL}");
                            exit;
                        } else {
                            if (!empty($row['access']) && $row['access'] != 'Register') {
                                $URL .= '&return=error0';
                                header("Location: {$URL}");
                                exit;
                            }

                            //Write to database
                            try {
                                $data = array('tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $tawasulPersonID);
                                $sql = 'DELETE FROM tawasulActivityStudent WHERE tawasulActivityID=:tawasulActivityID AND tawasulPersonID=:tawasulPersonID';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $URL .= '&return=error2';
                                header("Location: {$URL}");
                                exit;
                            }

                            //Set log
                            $logGateway->addLog($session->get('tawasulSchoolYearIDCurrent'), 'Activities', $session->get('tawasulPersonID'), 'Activities - Student Withdrawn', array('tawasulPersonIDStudent' => $tawasulPersonID));

                            $reg = $resultReg->fetch();

                            // Raise a new notification event
                            if ($reg['status'] == 'Accepted') {
                                // Get the start and end date of the activity, depending on which dateType we're using
                                $activityTimespan = getActivityTimespan($connection2, $tawasulActivityID, $row['tawasulSchoolYearTermIDList']);

                                // Is the activity running right now?
                                if (time() >= $activityTimespan['start'] && time() <= $activityTimespan['end']) {
                                    $event = new NotificationEvent('Activities', 'Student Withdrawn');

                                    $studentName = Format::name('', $row['preferredName'], $row['surname'], 'Student', false);
                                    $notificationText = sprintf(__('%1$s has withdrawn from the activity %2$s'), $studentName, $row['name']);

                                    $event->setNotificationText($notificationText);
                                    $event->setActionLink('/index.php?q=/modules/TawasulActivities/activities_manage_enrolment.php&tawasulActivityID='.$tawasulActivityID.'&search=&tawasulSchoolYearTermID=');

                                    $event->addScope('tawasulPersonIDStudent', $tawasulPersonID);
                                    $event->addScope('tawasulYearGroupID', $row['tawasulYearGroupID']);

                                    foreach ($tawasulActivityStaffIDs as $tawasulPersonIDStaff) {
                                        $event->addRecipient($tawasulPersonIDStaff);
                                    }

                                    $event->sendNotifications($pdo, $session);
                                }
                            }

                            //Bump up any waiting in competitive selection, to fill spaces available
                            $enrolment = $settingGateway->getSettingByScope('Activities', 'enrolmentType');
                            if ($enrolment == 'Competitive') {
                                //Check to see who is registering in system
                                $studentRegistration = false;
                                $parentRegistration = false ;

                                    $dataAccess = array();
                                    $sqlAccess = "SELECT
                                            tawasulAction.name, tawasulRole.category
                                        FROM tawasulAction
                                            JOIN tawasulPermission ON (tawasulPermission.tawasulActionID=tawasulAction.tawasulActionID)
                                            JOIN tawasulRole ON (tawasulPermission.tawasulRoleID=tawasulRole.tawasulRoleID)
                                        WHERE
                                            tawasulAction.name IN ('View Activities_studentRegister', 'View Activities_studentRegisterByParent')
                                            AND tawasulRole.category IN ('Parent','Student')";
                                    $resultAccess = $connection2->prepare($sqlAccess);
                                    $resultAccess->execute($dataAccess);
                                while ($rowAccess = $resultAccess->fetch()) {
                                    if ($rowAccess['name'] == 'View Activities_studentRegister' && $rowAccess['category'] == 'Student') {
                                        $studentRegistration = true;
                                    }
                                    else if ($rowAccess['name'] == 'View Activities_studentRegisterByParent' && $rowAccess['category'] == 'Parent') {
                                        $parentRegistration = true;
                                    }
                                }

                                //Count spaces
                                $dataNumberRegistered = array('tawasulActivityID' => $tawasulActivityID, 'today' => date('Y-m-d'));
                                $sqlNumberRegistered = "SELECT * FROM tawasulActivityStudent JOIN tawasulPerson ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today) AND tawasulActivityID=:tawasulActivityID AND tawasulActivityStudent.status='Accepted'";
                                $resultNumberRegistered = $connection2->prepare($sqlNumberRegistered);
                                $resultNumberRegistered->execute($dataNumberRegistered);

                                //If activity is not full...
                                $spaces = $row['maxParticipants'] - $resultNumberRegistered->rowCount();
                                if ($spaces > 0) {
                                    //Get top of waiting list
                                    $dataBumps = array('tawasulActivityID' => $tawasulActivityID, 'today' => date('Y-m-d'));
                                    $sqlBumps = "SELECT tawasulActivityStudentID, name, tawasulPerson.tawasulPersonID, surname, preferredName
                                        FROM tawasulActivityStudent
                                        JOIN tawasulActivity ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID)
                                        JOIN tawasulPerson ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID)
                                    WHERE tawasulPerson.status='Full'
                                        AND (dateStart IS NULL OR dateStart<=:today)
                                        AND (dateEnd IS NULL  OR dateEnd>=:today)
                                        AND tawasulActivityStudent.tawasulActivityID=:tawasulActivityID
                                        AND tawasulActivityStudent.status='Waiting List'
                                    ORDER BY timestamp ASC LIMIT 0, $spaces";
                                    $resultBumps = $connection2->prepare($sqlBumps);
                                    $resultBumps->execute($dataBumps);

                                    //Bump students up
                                    while ($rowBumps = $resultBumps->fetch()) {

                                        $dataBump = array('tawasulActivityStudentID' => $rowBumps['tawasulActivityStudentID']);
                                        $sqlBump = "UPDATE tawasulActivityStudent SET status='Accepted' WHERE tawasulActivityStudentID=:tawasulActivityStudentID";
                                        $resultBump = $connection2->prepare($sqlBump);
                                        $resultBump->execute($dataBump);

                                        //Set log
                                        $logGateway->addLog($session->get('tawasulSchoolYearIDCurrent'), 'Activities', $session->get('tawasulPersonID'), 'Activities - Student Bump', array('tawasulPersonIDStudent' => $rowBumps['tawasulPersonID']));

                                        //Raise notifications
                                        $event = new NotificationEvent('Activities', 'Student Bumped');

                                        $studentName = Format::name('', $rowBumps['preferredName'], $rowBumps['surname'], 'Student', false);
                                        $notificationText = sprintf(__('%1$s has been bumped into activity %2$s'), $studentName, $rowBumps['name']);

                                        $event->setNotificationText($notificationText);
                                        $event->setActionLink('/index.php?q=/modules/TawasulActivities/activities_view.php&tawasulPersonID='.$rowBumps['tawasulPersonID']);

                                        //DO WE WANT TO ADD STUDENT/PARENTS HERE, BASED ON ACCESS?
                                        if ($studentRegistration) { //Notify student
                                            $event->addRecipient($rowBumps['tawasulPersonID']);
                                        }
                                        if ($parentRegistration) { //Notify contact priority 1 parents in associated families

                                                $dataAdult = array('tawasulPersonID' => $rowBumps['tawasulPersonID']);
                                                $sqlAdult = "
                                                    SELECT
                                                        tawasulFamilyAdult.tawasulPersonID
                                                    FROM tawasulFamilyChild
                                                        JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                                                        JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                                                        JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID)
                                                    WHERE
                                                        tawasulFamilyChild.tawasulPersonID=:tawasulPersonID
                                                        AND childDataAccess='Y'
                                                        AND contactPriority=1
                                                        AND tawasulPerson.status='Full'";
                                                $resultAdult = $connection2->prepare($sqlAdult);
                                                $resultAdult->execute($dataAdult);
                                            while ($rowAdult = $resultAdult->fetch()) {
                                                $event->addRecipient($rowAdult['tawasulPersonID']);
                                            }
                                        }

                                        $event->sendNotifications($pdo, $session);
                                    }
                                }
                            }

                            $URLSuccess = $URLSuccess.'&return=success1';
                            header("Location: {$URLSuccess}");
                        }
                    }
                }
            }
        }
    }
}
