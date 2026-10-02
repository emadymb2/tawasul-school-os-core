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
use Tos\Module\TawasulAttendance\AttendanceView;
use TawasulOS\Domain\Attendance\AttendanceLogPersonGateway;

//TawasulOS system-wide includes
require __DIR__ . '/../../tawasul.php';

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$tawasulFormGroupID = $_POST['tawasulFormGroupID'] ?? '';
$currentDate = $_POST['currentDate'] ?? '';
$today = date('Y-m-d');
$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulAttendance/attendance_take_byFormGroup.php&tawasulFormGroupID=$tawasulFormGroupID&currentDate=".Format::date($currentDate);

if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/attendance_take_byFormGroup.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, '/modules/TawasulAttendance/attendance_take_byFormGroup.php', $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Proceed!
        //Check if tawasulFormGroupID and currentDate specified
        if ($tawasulFormGroupID == '' and $currentDate == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                if ($highestAction == 'Attendance By Form Group_all') {
                    $data = array('tawasulFormGroupID' => $tawasulFormGroupID);
                    $sql = 'SELECT * FROM tawasulFormGroup WHERE tawasulFormGroupID=:tawasulFormGroupID';
                }
                else {
                    $data = array('tawasulFormGroupID' => $tawasulFormGroupID, 'tawasulPersonIDTutor1' => $session->get('tawasulPersonID'), 'tawasulPersonIDTutor2' => $session->get('tawasulPersonID'), 'tawasulPersonIDTutor3' => $session->get('tawasulPersonID'), 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                    $sql = "SELECT * FROM tawasulFormGroup WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND (tawasulPersonIDTutor=:tawasulPersonIDTutor1 OR tawasulPersonIDTutor2=:tawasulPersonIDTutor2 OR tawasulPersonIDTutor3=:tawasulPersonIDTutor3) AND tawasulFormGroup.attendance = 'Y' AND tawasulFormGroupID=:tawasulFormGroupID ORDER BY LENGTH(name), name";
                }
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

                        try {
                            $data = array('tawasulPersonIDTaker' => $session->get('tawasulPersonID'), 'tawasulFormGroupID' => $tawasulFormGroupID, 'date' => $currentDate, 'timestampTaken' => date('Y-m-d H:i:s'));
                            $sql = 'INSERT INTO tawasulAttendanceLogFormGroup SET tawasulPersonIDTaker=:tawasulPersonIDTaker, tawasulFormGroupID=:tawasulFormGroupID, date=:date, timestampTaken=:timestampTaken';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }

                        $attendanceLogGateway = $container->get(AttendanceLogPersonGateway::class);

                        $count = $_POST['count'] ?? '';
                        $partialFail = false;

                        for ($i = 0; $i < $count; ++$i) {
                            $tawasulPersonID = $_POST[$i.'-tawasulPersonID'] ?? '';
                            $type = $_POST[$i.'-type'] ?? '';
                            $reason = $_POST[$i.'-reason'] ?? '';
                            $comment = $_POST[$i.'-comment'] ?? '';

                            $attendanceCode = $attendance->getAttendanceCodeByType($type);
                            $direction = $attendanceCode['direction'];

                            // Check for last record on same day
                             $result = $container->get(AttendanceLogPersonGateway::class)->selectAttendanceLogsByPersonAndDate($tawasulPersonID, $currentDate.'%', 'N');

                            // Check context and type, updating only if not a match
                            $existing = false ;
                            $tawasulAttendanceLogPersonID = '';
                            if ($result->rowCount() > 0) {
                                $row = $result->fetch() ;
                                if ($row['context'] == 'Form Group' && $row['type'] == $type && $row['direction'] == $direction ) {
                                    $existing = true ;
                                    $tawasulAttendanceLogPersonID = $row['tawasulAttendanceLogPersonID'];
                                }
                            }

                            $data = [
                                'tawasulAttendanceCodeID' => $attendanceCode['tawasulAttendanceCodeID'],
                                'tawasulPersonID'         => $tawasulPersonID,
                                'context'                => 'Form Group',
                                'direction'              => $direction,
                                'type'                   => $type,
                                'reason'                 => $reason,
                                'comment'                => $comment,
                                'tawasulPersonIDTaker'    => $session->get('tawasulPersonID'),
                                'tawasulFormGroupID'      => $tawasulFormGroupID,
                                'date'                   => $currentDate,
                                'timestampTaken'         => date('Y-m-d H:i:s'),
                            ];

                            if (!$existing) {
                                // If no records then create one
                                $inserted = $attendanceLogGateway->insert($data);
                                $partialFail &= !$inserted;

                            } else {
                                $updated = $attendanceLogGateway->update($tawasulAttendanceLogPersonID, $data);
                                $partialFail &= !$updated;
                            }
                        }

                        if ($partialFail == true) {
                            $URL .= '&return=warning1';
                            header("Location: {$URL}");
                        } else {
                            $URL .= '&return=success0&time='.date('H-i-s');
                            header("Location: {$URL}");
                        }
                    }
                }
            }
        }
    }
}
