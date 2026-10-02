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

use TawasulOS\Domain\DataSet;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

// get session object
$session = $container->get('session');

$page->breadcrumbs->add(__('View Daily Attendance'));

// show access denied message, if needed
if (!isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/attendance.php')) {
    $page->addError(__("You do not have access to this action."));
    return;
}

// rendering parameters
$currentDate = isset($_GET['currentDate']) ? Format::dateConvert($_GET['currentDate']) : date('Y-m-d');
$today = date("Y-m-d");
$lastNSchoolDays = getLastNSchoolDays($guid, $connection2, $currentDate, 10, true);
$accessNotRegistered = isActionAccessible($guid, $connection2, "/modules/TawasulAttendance/report_formGroupsNotRegistered_byDate.php")
    && isActionAccessible($guid, $connection2, "/modules/TawasulAttendance/report_courseClassesNotRegistered_byDate.php");
$tawasulPersonID = ($accessNotRegistered && isset($_GET['tawasulPersonID'])) ?
    $_GET['tawasulPersonID'] : $session->get('tawasulPersonID');

// define attendance filter form, if user is permit to view it
$form = Form::create('action', $session->get('absoluteURL') . '/index.php', 'get');

$form->setTitle(__('View Daily Attendance'));
$form->setFactory(DatabaseFormFactory::create($pdo));
$form->setClass('noIntBorder w-full');

$form->addHiddenValue('q', '/modules/' . $session->get('module') . '/attendance.php');

$row = $form->addRow();
$row->addLabel('currentDate', __('Date'));
$row->addDate('currentDate')->setValue(Format::date($currentDate))->required();

if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/report_formGroupsNotRegistered_byDate.php')) {
    $row = $form->addRow();
    $row->addLabel('tawasulPersonID', __('Staff'));
    $row->addSelectStaff('tawasulPersonID')->selected($tawasulPersonID)->placeholder()->required();
} else {
    $form->addHiddenValue('tawasulPersonID', $session->get('tawasulPersonID'));
}

$row = $form->addRow();
$row->addFooter();
$row->addSearchSubmit($session);

$page->write($form->getOutput());

// define attendance tables, if user is permit to view them
if ($session->has('username')) {
    // generator of basic attendance table
    $getDailyAttendanceTable = function ($guid, $connection2, $currentDate, $rowID, $takeAttendanceURL) use ($session) {

        // proto attendance table with columns for both
        // form group and course class
        $dailyAttendanceTable = DataTable::create('dailyAttendanceTable');

        // column definitions
        $dailyAttendanceTable->addColumn('group', __('Group'))
            ->context('primary')
            ->format(function ($row) use ($session, $rowID) {
                return Format::link(
                    $session->get('absoluteURL') . '/index.php?' .
                        http_build_query(['q' => $row['groupQuery'], $rowID => $row[$rowID]]),
                    $row['groupName']
                );
            });
        $dailyAttendanceTable->addColumn('recent-history', __('Recent History'))
            ->width('40%')
            ->format(function ($row) use ($takeAttendanceURL, $rowID, $session) {
                $dayTable = "<table class='historyCalendarMini rounded-sm overflow-hidden' cellspacing='0'>";

                $l = sizeof($row['recentHistory']);
                for ($i = 0; $i < $l; $i++) {
                    $dayTable .= '<tr>';
                    for ($j = 0; ($j < 10) && ($i + $j < $l); $j++) {
                        // grouping 10 days as a row
                        $day = $row['recentHistory'][$i + $j];
                        $link = '';
                        $content = '';

                        // default link and content
                        if (!empty($day['currentDate']) && !empty($day['currentDayTimestamp'])) {
                            // link and date content of a cell
                            $link = $session->get('absoluteURL') . '/index.php?' . http_build_query([
                                'q' => $takeAttendanceURL,
                                $rowID => $row[$rowID],
                                'currentDate' => $day['currentDate'],
                            ]);
                            $content =
                                '<div class="day text-xs">' . Format::date($day['currentDate'], 'd') . '</div>' .
                                '<div class="month text-xxs mt-px">' . Format::monthName($day['currentDate'], true) . '</div>';
                        }

                        // determine how to display link and content
                        // according to status
                        switch ($day['status']) {
                            case 'na':
                                $class = 'highlightNoData';
                                $content = __('NA');
                                break;
                            case 'present':
                                $class = 'highlightPresent';
                                $content = Format::link($link, $content);
                                break;
                            case 'absent':
                                $class = 'highlightAbsent';
                                $content = Format::link($link, $content);
                                break;
                            default:
                                $class = 'highlightNoData';
                                break;
                        }

                        $dayTable .= "<td class=\"{$class}\" style=\"padding: 12px !important;\">{$content}</td>";
                    }
                    $i += $j;
                    $dayTable .= '</tr>';
                }

                $dayTable .= '</table>';
                return $dayTable;
            });
        $dailyAttendanceTable->addColumn('today', __('Today'))
            ->context('primary')
            ->width('6%')
            ->format(function ($row) {
                switch ($row['today']) {
                    case 'taken':
                        // attendance taken
                        return icon('solid', 'check', 'size-6 fill-current text-green-600');
                    case 'not taken':
                        // attendance not taken
                        return icon('solid', 'cross', 'size-6 fill-current text-red-700');
                    case 'not timetabled':
                        // class not timetabled on the day
                        return '<span title="' . __('This class is not timetabled to run on the specified date. Attendance may still be taken for this group however it currently falls outside the regular schedule for this class.') . '">' .
                            __('N/A') . '</span>';
                }
            });
        $dailyAttendanceTable->addColumn('in', __('In'))
            ->context('primary')
            ->width('6%');

        $dailyAttendanceTable->addColumn('out', __('Out'))
            ->context('primary')
            ->width('6%');

        // action column, if user has the permission, and if this is a school day.
        if (isActionAccessible($guid, $connection2, $takeAttendanceURL) && isSchoolOpen($guid, $currentDate, $connection2)) {
            $dailyAttendanceTable->addActionColumn()
                ->addParam($rowID)
                ->addParam('currentDate')
                ->addAction('takeAttendance')
                ->setLabel(__('Take Attendance'))
                ->setIcon('attendance')
                ->setURL($takeAttendanceURL);
        }

        return $dailyAttendanceTable;
    };

    if ($currentDate > $today) {
        $page->write(Format::alert(__("The specified date is in the future: it must be today or earlier.")));
        return;
    } elseif (isSchoolOpen($guid, $currentDate, $connection2)==false) {
        $page->write(Format::alert(__("School is closed on the specified date, and so attendance information cannot be recorded.")));
        return;
    }

    if (isActionAccessible($guid, $connection2, "/modules/TawasulAttendance/attendance_take_byFormGroup.php")) {
        // Show My Form Groups
        try {
            $result = $connection2->prepare("SELECT tawasulFormGroupID, tawasulFormGroup.nameShort as name, firstDay, lastDay FROM tawasulFormGroup JOIN tawasulSchoolYear ON (tawasulFormGroup.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) WHERE (tawasulPersonIDTutor=:tawasulPersonIDTutor1 OR tawasulPersonIDTutor2=:tawasulPersonIDTutor2 OR tawasulPersonIDTutor3=:tawasulPersonIDTutor3) AND tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFormGroup.attendance = 'Y'");
            $result->execute([
                'tawasulPersonIDTutor1' => $tawasulPersonID,
                'tawasulPersonIDTutor2' => $tawasulPersonID,
                'tawasulPersonIDTutor3' => $tawasulPersonID,
                'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'),
            ]);
        } catch (PDOException $e) {
        }

        if ($result->rowCount() > 0) {
            $attendanceByFormGroup = [];
            while ($row = $result->fetch()) {
                //Produce array of attendance data
                try {
                    $resultAttendance = $connection2->prepare('SELECT date, tawasulFormGroupID, UNIX_TIMESTAMP(timestampTaken) FROM tawasulAttendanceLogFormGroup WHERE tawasulFormGroupID=:tawasulFormGroupID AND date>=:dateStart AND date<=:dateEnd ORDER BY date');
                    $resultAttendance->execute([
                        'tawasulFormGroupID' => $row["tawasulFormGroupID"],
                        'dateStart' => $lastNSchoolDays[count($lastNSchoolDays) - 1],
                        'dateEnd' => $lastNSchoolDays[0],
                    ]);
                } catch (PDOException $e) {
                }
                $logHistory = array();
                while ($rowAttendance = $resultAttendance->fetch()) {
                    $logHistory[$rowAttendance['date']] = true;
                }

                //Grab attendance log for the group & current day
                try {
                    $resultLog = $connection2->prepare("SELECT DISTINCT tawasulAttendanceLogFormGroupID, tawasulAttendanceLogFormGroup.timestampTaken as timestamp,
                        COUNT(DISTINCT tawasulAttendanceLogPerson.tawasulPersonID) AS total,
                        COUNT(DISTINCT CASE WHEN tawasulAttendanceLogPerson.direction = 'Out' THEN tawasulAttendanceLogPerson.tawasulPersonID END) AS absent
                        FROM tawasulAttendanceLogPerson
                        JOIN tawasulAttendanceLogFormGroup ON (tawasulAttendanceLogFormGroup.date = tawasulAttendanceLogPerson.date)
                        JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulAttendanceLogPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulFormGroupID=tawasulAttendanceLogFormGroup.tawasulFormGroupID)
                        WHERE tawasulAttendanceLogFormGroup.tawasulFormGroupID=:tawasulFormGroupID
                        AND tawasulAttendanceLogPerson.date LIKE :date
                        AND tawasulAttendanceLogPerson.context = 'Form Group'
                        GROUP BY tawasulAttendanceLogFormGroup.tawasulAttendanceLogFormGroupID
                        ORDER BY tawasulAttendanceLogPerson.timestampTaken");
                    $resultLog->execute([
                        'tawasulFormGroupID' => $row['tawasulFormGroupID'],
                        'date' => $currentDate . '%'
                    ]);
                } catch (PDOException $e) {
                }

                $log = $resultLog->fetch();

                // general row variables
                $row['currentDate'] = Format::date($currentDate);

                // render group link variables
                $row['groupQuery'] = '/modules/TawasulFormGroups/formGroups_details.php';
                $row['groupName'] = $row['name'];

                // render recentHistory into the row
                for ($i = count($lastNSchoolDays) - 1; $i >= 0; --$i) {
                    if ($i > (count($lastNSchoolDays) - 1)) {
                        $dayData = [
                            'currentDate' => null,
                            'currentDayTimestamp' => null,
                            'status' => 'na',
                        ];
                    } else {
                        $dayData = [
                            'currentDate' => Format::dateConvert($lastNSchoolDays[$i]),
                            'currentDayTimestamp' => Format::timestamp($lastNSchoolDays[$i]),
                            'status' => isset($logHistory[$lastNSchoolDays[$i]]) ? 'present' : 'absent',
                        ];
                    }
                    $row['recentHistory'][] = $dayData;
                }

                // Attendance not taken
                $row['today'] = ($resultLog->rowCount() < 1) ? 'not taken' : 'taken';
                $row['in'] = ($resultLog->rowCount() < 1) ? "" : ($log["total"] - $log["absent"]);
                $row['out'] = $log["absent"] ?? '';

                $attendanceByFormGroup[] = $row;
            }

            // define DataTable
            $takeAttendanceURL = '/modules/TawasulAttendance/attendance_take_byFormGroup.php';
            $attendanceByFormGroupTable = $getDailyAttendanceTable(
                $guid,
                $connection2,
                $currentDate,
                'tawasulFormGroupID',
                $takeAttendanceURL
            );
            $attendanceByFormGroupTable->setTitle(__('My Form Group'));
            $attendanceByFormGroupTable->withData(new DataSet($attendanceByFormGroup));
        }
    }

    if (isActionAccessible($guid, $connection2, "/modules/TawasulAttendance/attendance_take_byCourseClass.php")) {
        // Produce array of attendance data
        try {
            $result = $connection2->prepare("SELECT date, tawasulCourseClassID FROM tawasulAttendanceLogCourseClass WHERE date>=:dateStart AND date<=:dateEnd ORDER BY date");
            $result->execute([
                'dateStart' => $lastNSchoolDays[count($lastNSchoolDays) - 1],
                'dateEnd' => $lastNSchoolDays[0],
            ]);
        } catch (PDOException $e) {
        }
        $logHistory = array();
        while ($row = $result->fetch()) {
            $logHistory[$row['tawasulCourseClassID']][$row['date']] = true;
        }

        // Produce an array of scheduled classes
        try {
            $result = $connection2->prepare("SELECT tawasulTTDayRowClass.tawasulCourseClassID, tawasulTTDayDate.date FROM tawasulTTDayRowClass JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID) JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID) WHERE tawasulCourseClass.attendance = 'Y' AND tawasulTTDayDate.date>=:dateStart AND tawasulTTDayDate.date<=:dateEnd ORDER BY tawasulTTDayDate.date");
            $result->execute([
                'dateStart' => $lastNSchoolDays[count($lastNSchoolDays) - 1],
                'dateEnd' => $lastNSchoolDays[0],
            ]);
        } catch (PDOException $e) {
        }
        $ttHistory = array();
        while ($row = $result->fetch()) {
            $ttHistory[$row['tawasulCourseClassID']][$row['date']] = true;
        }

        //Show My Classes
        try {
            $result = $connection2->prepare("SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID,
                (SELECT count(*) FROM tawasulCourseClassPerson WHERE role='Student' AND tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) as studentCount
                FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson
                WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID
                AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID
                AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND NOT role LIKE '% - Left%'
                AND tawasulCourseClass.attendance = 'Y'
                ORDER BY course, class");
            $result->execute([
                'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'),
                'tawasulPersonID' => $tawasulPersonID,
            ]);
        } catch (PDOException $e) {
            //
        }

        if ($result->rowCount() > 0) {
            $count = 0;

            $attendanceByCourseClass = [];
            while ($row = $result->fetch()) {
                // Skip classes with no students
                if ($row['studentCount'] <= 0) {
                    continue;
                }

                $count++;

                //Grab attendance log for the class & current day
                try {
                    $resultLog = $connection2->prepare("SELECT tawasulAttendanceLogCourseClass.timestampTaken as timestamp,
                        COUNT(tawasulAttendanceLogPerson.tawasulPersonID) AS total, SUM(tawasulAttendanceLogPerson.direction = 'Out') AS absent
                        FROM tawasulAttendanceLogCourseClass
                        JOIN tawasulAttendanceLogPerson ON tawasulAttendanceLogPerson.tawasulCourseClassID = tawasulAttendanceLogCourseClass.tawasulCourseClassID
                        WHERE tawasulAttendanceLogCourseClass.tawasulCourseClassID=:tawasulCourseClassID
                        AND tawasulAttendanceLogPerson.context='Class'
                        AND tawasulAttendanceLogCourseClass.date LIKE :date AND tawasulAttendanceLogPerson.date LIKE :date
                        GROUP BY tawasulAttendanceLogCourseClass.tawasulAttendanceLogCourseClassID
                        ORDER BY tawasulAttendanceLogCourseClass.timestampTaken");
                    $resultLog->execute([
                        'tawasulCourseClassID' => $row['tawasulCourseClassID'],
                        'date' => $currentDate . '%',
                    ]);
                } catch (PDOException $e) {
                }

                $log = $resultLog->fetch();

                // general row variables
                $row['currentDate'] = Format::date($currentDate);

                // render group link variables
                $row['groupQuery'] = '/modules/TawasulDepartments/department_course_class.php';
                $row['groupName'] = $row["course"] . "." . $row["class"];

                // render recentHistory into the row
                for ($i = count($lastNSchoolDays) - 1; $i >= 0; --$i) {
                    if ($i > (count($lastNSchoolDays) - 1)) {
                        $dayData = [
                            'currentDate' => null,
                            'currentDayTimestamp' => null,
                            'status' => 'na',
                        ];
                    } else {
                        $dayData = [
                            'currentDate' => Format::dateConvert($lastNSchoolDays[$i]),
                            'currentDayTimestamp' => Format::timestamp($lastNSchoolDays[$i]),
                        ];
                        if (isset($logHistory[$row['tawasulCourseClassID']][$lastNSchoolDays[$i]]) == true) {
                            $dayData['status'] = 'present';
                        } else {
                            $dayData['status'] =
                            isset($ttHistory[$row['tawasulCourseClassID']][$lastNSchoolDays[$i]]) ?
                            $dayData['status'] = 'absent' :
                            $dayData['status'] = null;
                        }
                    }
                    $row['recentHistory'][] = $dayData;
                }

                // attendance today, if timetabled
                $row['today'] = null;
                if (isset($ttHistory[$row['tawasulCourseClassID']][$currentDate])) {
                    $row['today'] = ($resultLog->rowCount() < 1) ? 'not taken' : 'taken';
                } elseif (isset($logHistory[$row['tawasulCourseClassID']][$currentDate])) {
                    // class is not timetabled to run on the specified date
                    $row['today'] = 'not timetabled';
                }
                $row['in'] = ($resultLog->rowCount() < 1) ? "" : ($log["total"] - $log["absent"]);
                $row['out'] = $log["absent"] ?? '';

                $attendanceByCourseClass[] = $row;
            }

            // define DataTable
            $takeAttendanceURL = '/modules/TawasulAttendance/attendance_take_byCourseClass.php';
            $attendanceByCourseClassTable = $getDailyAttendanceTable(
                $guid,
                $connection2,
                $currentDate,
                'tawasulCourseClassID',
                $takeAttendanceURL
            );
            $attendanceByCourseClassTable->setTitle(__('My Classes'));
            $attendanceByCourseClassTable->withData(new DataSet($attendanceByCourseClass));
        }
    }
}

//
// write page outputs
//
if (isset($attendanceByFormGroupTable)) {
    $page->write($attendanceByFormGroupTable->getOutput());
}
if (isset($attendanceByCourseClassTable)) {
    $page->write($attendanceByCourseClassTable->getOutput());
}

if (empty($attendanceByFormGroupTable) && empty($attendanceByCourseClassTable)) {
    echo DataTable::create('blank')->setDescription('<br/>')->withData([])->getOutput();
}
