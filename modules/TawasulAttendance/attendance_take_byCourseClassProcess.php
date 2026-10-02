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
use TawasulOS\Domain\Timetable\CourseClassGateway;
use TawasulOS\Domain\Attendance\AttendanceLogPersonGateway;
use TawasulOS\Domain\Attendance\AttendanceLogCourseClassGateway;

//TawasulOS system-wide includes
require __DIR__ . '/../../tawasul.php';

//Module includes
require_once __DIR__ . '/moduleFunctions.php' ;

$tawasulCourseClassID=$_POST['tawasulCourseClassID'] ?? '';
$tawasulTTDayRowClassID=!empty($_POST['tawasulTTDayRowClassID']) ? $_POST['tawasulTTDayRowClassID'] : null;
$currentDate=$_POST['currentDate'] ?? '';
$today=date('Y-m-d');

$moduleName = getModuleName($_POST['address'] ?? '');

if ($moduleName == 'Planner') {
    $tawasulPlannerEntryID = $_POST['tawasulPlannerEntryID'] ?? '';
    $URL=$session->get('absoluteURL') . "/index.php?q=/modules/" . $moduleName . "/planner_view_full.php&tawasulPlannerEntryID=$tawasulPlannerEntryID&viewBy=date&tawasulCourseClassID=$tawasulCourseClassID&date=" . $currentDate ;
} else {
    $URL=$session->get('absoluteURL') . "/index.php?q=/modules/" . $moduleName . "/attendance_take_byCourseClass.php&tawasulCourseClassID=$tawasulCourseClassID&tawasulTTDayRowClassID=$tawasulTTDayRowClassID&currentDate=" . Format::date($currentDate) ;
}

if (isActionAccessible($guid, $connection2, "/modules/TawasulAttendance/attendance_take_byCourseClass.php")==FALSE) {
    //Fail 0
    $URL.="&return=error0" ;
    header("Location: {$URL}");
    die();
}
else {
    //Proceed!
    //Check if tawasulCourseClassID and currentDate specified
    if ($tawasulCourseClassID=="" AND $currentDate=="") {
        //Fail1
        $URL.="&return=error1" ;
        header("Location: {$URL}");
        die();
    }
    else {
        $result = $container->get(CourseClassGateway::class)->getByID($tawasulCourseClassID);

        if (empty($result)) {
            // Fail 2
            $URL.="&return=error1" ;
            header("Location: {$URL}");
            die();
        }
        else {
            // Check that date is not in the future
            if ($currentDate>$today) {
                //Fail 4
                $URL.="&return=error3" ;
                header("Location: {$URL}");
                die();
            }
            else {
                //Check that date is a school day
                if (isSchoolOpen($guid, $currentDate, $connection2)==FALSE) {
                    //Fail 5
                    $URL.="&return=error3" ;
                    header("Location: {$URL}");
                    die();
                }
                else {
                    $settingGateway = $container->get(SettingGateway::class);
                    $attendanceLogCourseClassGateway = $container->get(AttendanceLogCourseClassGateway::class);

                    //Write to database
                    require_once __DIR__ . '/src/AttendanceView.php';
                    $attendance = new AttendanceView($tawasul, $pdo, $settingGateway);

                    if (!empty($tawasulTTDayRowClassID)) {
                        $classLog = $attendanceLogCourseClassGateway->selectBy(['tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $currentDate, 'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID])->fetch();
                    } else {
                        $classLog = $attendanceLogCourseClassGateway->selectBy(['tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $currentDate])->fetch();
                    }

                    if (!empty($classLog)) {
                        $tawasulAttendanceLogCourseClassID = $classLog['tawasulAttendanceLogCourseClassID'];
                        $attendanceLogCourseClassGateway->update($classLog['tawasulAttendanceLogCourseClassID'], [
                            'tawasulPersonIDTaker' => $session->get('tawasulPersonID'),
                            'timestampTaken'=>date('Y-m-d H:i:s'),
                        ]);
                    } else {
                        $tawasulAttendanceLogCourseClassID = $attendanceLogCourseClassGateway->insert([
                            'tawasulPersonIDTaker' => $session->get('tawasulPersonID'),
                            'tawasulCourseClassID'=>$tawasulCourseClassID,
                            'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID,
                            'date'=>$currentDate,
                            'timestampTaken'=>date('Y-m-d H:i:s'),
                        ]);
                    }

                    if (empty($tawasulAttendanceLogCourseClassID)) {
                        //Fail 2
                        $URL.="&return=error2" ;
                        header("Location: {$URL}");
                        die();
                    }

                    $recordFirstClassAsSchool = $settingGateway->getSettingByScope('Attendance', 'recordFirstClassAsSchool');
                    $attendanceLogGateway = $container->get(AttendanceLogPersonGateway::class);

                    $recordSchoolAttendance = $_POST['recordSchoolAttendance'] ?? 'N';
                    $count=$_POST["count"] ?? '';
                    $partialFail=FALSE ;

                    for ($i=0; $i<$count; $i++) {
                        $tawasulPersonID=$_POST[$i . "-tawasulPersonID"] ;

                        $type=$_POST[$i . "-type"] ?? '';
                        $reason=$_POST[$i . "-reason"] ?? '';
                        $comment=$_POST[$i . "-comment"] ?? '';
                        $prefilled=$_POST[$i . "-prefilled"] ?? '';

                        $attendanceCode = $attendance->getAttendanceCodeByType($type);
                        $direction = $attendanceCode['direction'];

                        // Check for last record on same day
                        $result = $container->get(AttendanceLogPersonGateway::class)->selectAttendanceLogsByPersonAndDate($tawasulPersonID, $currentDate.'%', 'N');
                        
                        // Check context, tawasulCourseClassID and type, updating only if not a match
                        $existing = false ;
                        $tawasulAttendanceLogPersonID = '';
                        if ($result->rowCount()>0) {
                            while ($row=$result->fetch()) {
                                if ($row['context'] == 'Class' && $row['tawasulCourseClassID'] == $tawasulCourseClassID && (empty($row['tawasulTTDayRowClassID']) || $row['tawasulTTDayRowClassID'] == $tawasulTTDayRowClassID) ) {
                                    $existing = true ;
                                    $tawasulAttendanceLogPersonID = $row['tawasulAttendanceLogPersonID'];
                                    break;
                                }
                            }
                        }

                        $data = [
                            'tawasulAttendanceCodeID' => $attendanceCode['tawasulAttendanceCodeID'],
                            'tawasulPersonID'         => $tawasulPersonID,
                            'context'                => 'Class',
                            'direction'              => $direction,
                            'type'                   => $type,
                            'reason'                 => $reason,
                            'comment'                => $comment,
                            'tawasulPersonIDTaker'    => $session->get('tawasulPersonID'),
                            'tawasulCourseClassID'    => $tawasulCourseClassID,
                            'tawasulTTDayRowClassID'  => $tawasulTTDayRowClassID,
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

                        if ($recordFirstClassAsSchool == 'Y' && empty($prefilled)) {
                            $data['context'] = 'Person';
                            $inserted = $attendanceLogGateway->insert($data);
                            $partialFail &= !$inserted;
                        }
                    }

                    if ($partialFail == true) {
                        //Fail 3
                        $URL.="&return=warning1" ;
                        header("Location: {$URL}");
                        die();
                    } else {
                        //Success 0
                        $URL.="&return=success0&time=" . date("H-i-s") ;
                        header("Location: {$URL}");
                    }
                }
            }
        }
    }
}
