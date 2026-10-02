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
use TawasulOS\Domain\System\SettingGateway;
use Tos\Module\TawasulAttendance\AttendanceView;
use TawasulOS\Domain\Attendance\AttendanceLogPersonGateway;

//TawasulOS system-wide includes
require __DIR__ . '/../../tawasul.php';

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
$currentDate = $_POST['currentDate'] ?? '';
$today = date('Y-m-d');
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/attendance_take_byPerson.php&tawasulPersonID=$tawasulPersonID&currentDate=".Format::date($currentDate);

if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/attendance_take_byPerson.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulPersonID and currentDate specified
    if ($tawasulPersonID == '' and $currentDate == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulPersonID' => $tawasulPersonID);
            $sql = 'SELECT * FROM tawasulPerson WHERE tawasulPersonID=:tawasulPersonID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($result->rowCount() != 1) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            //Check that date is not in the future
            if ($currentDate > $today) {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                //Check that date is a school day
                if (isSchoolOpen($guid, $currentDate, $connection2) == false) {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    require_once __DIR__ . '/src/AttendanceView.php';
                    $attendance = new AttendanceView($tawasul, $pdo, $container->get(SettingGateway::class));

                    $fail = false;
                    $type = $_POST['type'] ?? '';
                    $reason = $_POST['reason'] ?? '';
                    $comment = $_POST['comment'] ?? '';

                    $attendanceCode = $attendance->getAttendanceCodeByType($type);
                    $direction = $attendanceCode['direction'];

                    // Check for last record on same day
                    $result = $container->get(AttendanceLogPersonGateway::class)->selectAttendanceLogsByPersonAndDate($tawasulPersonID, $currentDate.'%', 'N');

                    // Check context and type, updating only if not a match
                    $existing = false ;
                    $tawasulAttendanceLogPersonID = '';
                    if ($result->rowCount() > 0) {
                        $row = $result->fetch();
                        if ($row['context'] == 'Person' && $row['type'] == $type) {
                            $existing = true ;
                            $tawasulAttendanceLogPersonID = $row['tawasulAttendanceLogPersonID'];
                        }
                    }

                    if (!$existing) {
                        // If no records then create one
                        try {
                            $dataUpdate = array('tawasulPersonID' => $tawasulPersonID, 'direction' => $direction, 'type' => $type, 'reason' => $reason, 'comment' => $comment, 'tawasulPersonIDTaker' => $session->get('tawasulPersonID'), 'date' => $currentDate, 'timestampTaken' => date('Y-m-d H:i:s'));
                            $sqlUpdate = 'INSERT INTO tawasulAttendanceLogPerson SET tawasulAttendanceCodeID=(SELECT tawasulAttendanceCodeID FROM tawasulAttendanceCode WHERE name=:type), tawasulPersonID=:tawasulPersonID, direction=:direction, type=:type, context=\'Person\', reason=:reason, comment=:comment, tawasulPersonIDTaker=:tawasulPersonIDTaker, date=:date, timestampTaken=:timestampTaken';
                            $resultUpdate = $connection2->prepare($sqlUpdate);
                            $resultUpdate->execute($dataUpdate);
                        } catch (PDOException $e) {
                            $fail = true;
                        }
                    } else {
                        //If direction same then update
                        if ($row['direction'] == $direction && $row['tawasulCourseClassID'] == 0) {
                            try {
                                $dataUpdate = array('tawasulPersonID' => $tawasulPersonID, 'direction' => $direction, 'type' => $type, 'reason' => $reason, 'comment' => $comment, 'tawasulPersonIDTaker' => $session->get('tawasulPersonID'), 'date' => $currentDate, 'timestampTaken' => date('Y-m-d H:i:s'), 'tawasulAttendanceLogPersonID' => $row['tawasulAttendanceLogPersonID']);
                                $sqlUpdate = 'UPDATE tawasulAttendanceLogPerson SET tawasulAttendanceCodeID=(SELECT tawasulAttendanceCodeID FROM tawasulAttendanceCode WHERE name=:type), tawasulPersonID=:tawasulPersonID, direction=:direction, type=:type, context=\'Person\', reason=:reason, comment=:comment, tawasulPersonIDTaker=:tawasulPersonIDTaker, date=:date, timestampTaken=:timestampTaken WHERE tawasulAttendanceLogPersonID=:tawasulAttendanceLogPersonID';
                                $resultUpdate = $connection2->prepare($sqlUpdate);
                                $resultUpdate->execute($dataUpdate);
                            } catch (PDOException $e) {
                                $fail = true;
                            }
                        }
                        //Else create a new record
                        else {
                            try {
                                $dataUpdate = array('tawasulPersonID' => $tawasulPersonID, 'direction' => $direction, 'type' => $type, 'reason' => $reason, 'comment' => $comment, 'tawasulPersonIDTaker' => $session->get('tawasulPersonID'), 'date' => $currentDate, 'timestampTaken' => date('Y-m-d H:i:s'));
                                $sqlUpdate = 'INSERT INTO tawasulAttendanceLogPerson SET tawasulAttendanceCodeID=(SELECT tawasulAttendanceCodeID FROM tawasulAttendanceCode WHERE name=:type), tawasulPersonID=:tawasulPersonID, direction=:direction, type=:type, context=\'Person\', reason=:reason, comment=:comment, tawasulPersonIDTaker=:tawasulPersonIDTaker, date=:date, timestampTaken=:timestampTaken';
                                $resultUpdate = $connection2->prepare($sqlUpdate);
                                $resultUpdate->execute($dataUpdate);
                            } catch (PDOException $e) {
                                $fail = true;
                            }
                        }
                    }
                }
            }

            if ($fail == true) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                $URL .= '&return=success0';
                header("Location: {$URL}");
            }
        }
    }
}
