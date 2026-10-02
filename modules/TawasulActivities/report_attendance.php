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

use TawasulOS\Forms\Form;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/report_attendance.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Attendance History by Activity'));

    echo '<h2>';
    echo __('Choose Activity');
    echo '</h2>';

    $tawasulActivityID = null;
    if (isset($_GET['tawasulActivityID'])) {
        $tawasulActivityID = $_GET['tawasulActivityID'] ?? '';
    }
    $allColumns = (isset($_GET['allColumns'])) ? $_GET['allColumns'] : false;

    $form = Form::create('action', $session->get('absoluteURL').'/index.php','get');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', "/modules/".$session->get('module')."/report_attendance.php");

    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
    $sql = "SELECT tawasulActivityID AS value, name FROM tawasulActivity WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND active='Y' ORDER BY name, programStart";
    $row = $form->addRow();
        $row->addLabel('tawasulActivityID', __('Activity'));
        $row->addSearchSelect('tawasulActivityID')->fromQuery($pdo, $sql, $data)->selected($tawasulActivityID)->required()->placeholder();

    $row = $form->addRow();
        $row->addLabel('allColumns', __('All Columns'))->description(__('Include empty columns with unrecorded attendance.'));
        $row->addCheckbox('allColumns')->checked($allColumns);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    // Cancel out early if we have no tawasulActivityID
    if (empty($tawasulActivityID)) {
        return;
    }


        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulActivityID' => $tawasulActivityID);
        $sql = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName, tawasulFormGroup.tawasulFormGroupID, tawasulActivityStudent.status, tawasulFormGroup.nameShort as formGroup FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID) JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulActivityStudent.status='Accepted' AND tawasulActivityID=:tawasulActivityID ORDER BY tawasulActivityStudent.status, surname, preferredName";
        $studentResult = $connection2->prepare($sql);
        $studentResult->execute($data);


        $data = array('tawasulActivityID' => $tawasulActivityID);
        $sql = "SELECT tawasulSchoolYearTermIDList, maxParticipants, programStart, programEnd, (SELECT COUNT(*) FROM tawasulActivityStudent JOIN tawasulPerson ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID AND tawasulActivityStudent.status='Waiting List' AND tawasulPerson.status='Full') AS waiting FROM tawasulActivity WHERE tawasulActivityID=:tawasulActivityID";
        $activityResult = $connection2->prepare($sql);
        $activityResult->execute($data);

    if ($studentResult->rowCount() < 1 || $activityResult->rowCount() < 1) {
        echo $page->getBlankSlate();

        return;
    }


        $data = array('tawasulActivityID' => $tawasulActivityID);
        $sql = 'SELECT tawasulActivityAttendance.date, tawasulActivityAttendance.timestampTaken, tawasulActivityAttendance.attendance, tawasulPerson.preferredName, tawasulPerson.surname FROM tawasulActivityAttendance, tawasulPerson WHERE tawasulActivityAttendance.tawasulPersonIDTaker=tawasulPerson.tawasulPersonID AND tawasulActivityAttendance.tawasulActivityID=:tawasulActivityID';
        $attendanceResult = $connection2->prepare($sql);
        $attendanceResult->execute($data);

    // Gather the existing attendance data (by date and not index, should the time slots change)
    $sessionAttendanceData = array();

    while ($attendance = $attendanceResult->fetch()) {
        $sessionAttendanceData[ $attendance['date'] ] = array(
            'data' => (!empty($attendance['attendance'])) ? unserialize($attendance['attendance']) : array(),
            'info' => sprintf(__('Recorded at %1$s on %2$s by %3$s.'), substr($attendance['timestampTaken'], 11), Format::date(substr($attendance['timestampTaken'], 0, 10)), Format::name('', $attendance['preferredName'], $attendance['surname'], 'Staff', false, true)),
        );
    }

    $today = date('Y-m-d');
    $activity = $activityResult->fetch();
    $activity['participants'] = $studentResult->rowCount();

    // Get the week days that match time slots for this activity
    $activityWeekDays = getActivityWeekdays($connection2, $tawasulActivityID);

    // Get the start and end date of the activity, depending on which dateType we're using
    $activityTimespan = getActivityTimespan($connection2, $tawasulActivityID, $activity['tawasulSchoolYearTermIDList']);

    // Use the start and end date of the activity, along with time slots, to get the activity sessions
    $activitySessions = getActivitySessions($guid, $connection2, ($allColumns) ? $activityWeekDays : array(), $activityTimespan, $sessionAttendanceData);

    echo '<h2>';
    echo __('Activity');
    echo '</h2>';

    echo "<table class='smallIntBorder' style='width: 100%;' cellspacing='0'><tbody>";
    echo '<tr>';
    echo "<td style='width: 33%; vertical-align: top'>";
    echo "<span class='infoTitle'>".__('Start Date').'</span><br>';
    if (!empty($activityTimespan['start'])) {
        echo date($session->get('i18n')['dateFormatPHP'], $activityTimespan['start']);
    }
    echo '</td>';

    echo "<td style='width: 33%; vertical-align: top'>";
    echo "<span class='infoTitle'>".__('End Date').'</span><br>';
    if (!empty($activityTimespan['end'])) {
        echo date($session->get('i18n')['dateFormatPHP'], $activityTimespan['end']);
    }
    echo '</td>';

    echo "<td style='width: 33%; vertical-align: top'>";
    printf("<span class='infoTitle' title=''>%s</span><br>%s", __('Number of Sessions'), count($activitySessions));
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo "<td style='width: 33%; vertical-align: top'>";
    printf("<span class='infoTitle'>%s</span><br>%s", __('Participants'), $activity['participants']);
    echo '</td>';

    echo "<td style='width: 33%; vertical-align: top'>";
    printf("<span class='infoTitle'>%s</span><br>%s", __('Maximum Participants'), $activity['maxParticipants']);
    echo '</td>';

    echo "<td style='width: 33%; vertical-align: top'>";
    printf("<span class='infoTitle' title=''>%s</span><br>%s", __('Waiting'), $activity['waiting']);
    echo '</td>';
    echo '</tr>';
    echo '</tbody></table>';

    echo '<h2>';
    echo __('Attendance');
    echo '</h2>';

    if ($allColumns == false && $attendanceResult->rowCount() < 1) {
        echo $page->getBlankSlate();

        return;
    }

    if (empty($activityWeekDays) || empty($activityTimespan)) {
        echo "<div class='error'>";
        echo __('There are no time slots assigned to this activity, or the start and end dates are invalid. New attendance values cannot be entered until the time slots and dates are added.');
        echo '</div>';
    }

    if (count($activitySessions) <= 0) {
        echo $page->getBlankSlate();
    } else {
        if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/report_attendanceExport.php')) {
            echo "<div class='linkTop'>";
            echo "<a href='".$session->get('absoluteURL').'/modules/'.$session->get('module').'/report_attendanceExport.php?tawasulActivityID='.$tawasulActivityID."'>".__('Export to Excel')."<img style='margin-left: 5px' title='".__('Export to Excel')."' src='./themes/".$session->get('tawasulThemeName')."/img/download.png'/></a>";
            echo '</div>';
        }

        echo "<div id='attendance' class='block max-w-full'>";
        echo "<div class='doublescroll-wrapper'>";

        echo "<table class='mini' cellspacing='0' style='width:100%; border: 0; margin:0;'>";
        echo "<tr class='head' style='height:60px; '>";
        echo "<th style='width:190px;'>";
        echo __('Student');
        echo '</th>';
        echo '<th>';
        echo __('Attendance');
        echo '</th>';
        echo "<th class='italic subdued' style='text-align:right'>";
        printf(__('Sessions Recorded: %s of %s'), count($sessionAttendanceData), count($activitySessions));
        echo '</th>';
        echo '</tr>';
        echo '</table>';
        echo "<div class='doublescroll-top'><div class='doublescroll-top-tablewidth'></div></div>";

        $columnCount = ($allColumns) ? count($activitySessions) : count($sessionAttendanceData);

        echo "<div class='doublescroll-container overflow-x-scroll'>";
        echo "<table class='mini colorOddEven border-0' cellspacing='0' style='width: ".(($columnCount * 56)+175)."px'>";

        echo "<tr style='height: 55px'>";
        echo "<td style='vertical-align:top;height:55px;width:175px'>".__('Date').'</td>';

        foreach ($activitySessions as $sessionDate => $sessionTimestamp) {
            if (isset($sessionAttendanceData[$sessionDate]['data'])) {
                // Handle instances where the time slot has been deleted after creating an attendance record
                        if (!in_array(date('D', $sessionTimestamp), $activityWeekDays) || ($sessionTimestamp < $activityTimespan['start']) || ($sessionTimestamp > $activityTimespan['end'])) {
                            echo "<td style='vertical-align:top; width: 50px;  white-space: nowrap;' class='warning' title='".__('Does not match the time slots for this activity.')."'>";
                        } else {
                            echo "<td style='vertical-align:top; width: 50px;  white-space: nowrap;'>";
                        }

                printf("<span title='%s'>%s <br/> %s</span><br/>&nbsp;<br/>",
                    $sessionAttendanceData[$sessionDate]['info'],
                    Format::dayOfWeekName($sessionDate, true),
                    Format::dateReadable($sessionDate, Format::MEDIUM_NO_YEAR)
                );
            } else {
                echo "<td style='color: #bbb; vertical-align:top; width: 50px; white-space: nowrap;'>";
                echo Format::dayOfWeekName($sessionDate).' <br/> '.
                    Format::dateReadable($sessionDate, Format::MEDIUM_NO_YEAR).'<br/>&nbsp;<br/>';
            }
            echo '</td>';
        }

        echo '</tr>';

        $count = 0;
        // Build an empty array of attendance count data for each session
        $attendanceCount = array_combine(array_keys($activitySessions), array_fill(0, count($activitySessions), 0));

        while ($row = $studentResult->fetch()) {
            ++$count;
            $student = $row['tawasulPersonID'];

            echo "<tr data-student='$student'>";
            echo '<td>';
            echo $count.'. '.Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
            echo ' &nbsp;&nbsp;'.Format::small($row['formGroup'] ?? '');
            echo '</td>';

            foreach ($activitySessions as $sessionDate => $sessionTimestamp) {
                echo "<td class='col'>";
                if (isset($sessionAttendanceData[$sessionDate]['data'])) {
                    if (isset($sessionAttendanceData[$sessionDate]['data'][$student])) {
                        echo '✓';
                        $attendanceCount[$sessionDate]++;
                    }
                }
                echo '</td>';
            }

            echo '</tr>';

            $lastPerson = $row['tawasulPersonID'];
        }

            // Output a total attendance per column
            echo '<tr>';
        echo "<td class='right'>";
        echo __('Total students:');
        echo '</td>';

        foreach ($activitySessions as $sessionDate => $sessionTimestamp) {
            echo '<td>';
            if (!empty($attendanceCount[$sessionDate]) || $sessionDate <= $today) {
                echo $attendanceCount[$sessionDate].' / '.$activity['participants'];
            }
            echo '</td>';
        }

        echo '</tr>';

        if ($count == 0) {
            echo "<tr class=$rowNum>";
            echo '<td colspan=16>';
            echo __('There are no records to display.');
            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '</div><br/>';
    }
}

?>
