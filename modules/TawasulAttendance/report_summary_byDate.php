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
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Forms\DatabaseFormFactory;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

// set page breadcrumb
$page->breadcrumbs->add(__('Attendance Summary by Date'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/report_summary_byDate.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    echo '<h2>';
    echo __('Choose Date');
    echo '</h2>';

    $today = date('Y-m-d');

    $settingGateway = $container->get(SettingGateway::class);
    $countClassAsSchool = $settingGateway->getSettingByScope('Attendance', 'countClassAsSchool');
    $dateEnd = (isset($_REQUEST['dateEnd']))? $_REQUEST['dateEnd'] : date('Y-m-d');
    $dateStart = (isset($_REQUEST['dateStart']))? $_REQUEST['dateStart'] : date('Y-m-d', strtotime( $dateEnd.' -1 month') );

    // Correct inverse date ranges rather than generating an error
    if ($dateStart > $dateEnd) {
        $swapDates = $dateStart;
        $dateStart = $dateEnd;
        $dateEnd = $swapDates;
    }

    // Limit date range to the current school year
    if ($dateStart < $session->get('tawasulSchoolYearFirstDay')) {
        $dateStart = $session->get('tawasulSchoolYearFirstDay');
    }

    if ($dateEnd > $session->get('tawasulSchoolYearLastDay')) {
        $dateEnd = $session->get('tawasulSchoolYearLastDay');
    }

    $group = !empty($_REQUEST['group'])? $_REQUEST['group'] : '';
    $sort = !empty($_REQUEST['sort'])? $_REQUEST['sort'] : 'surname';

    $tawasulCourseClassID = (isset($_REQUEST["tawasulCourseClassID"]))? $_REQUEST["tawasulCourseClassID"] : 0;
    $tawasulFormGroupID = (isset($_REQUEST["tawasulFormGroupID"]))? $_REQUEST["tawasulFormGroupID"] : 0;

    $tawasulAttendanceCodeID = (isset($_REQUEST["tawasulAttendanceCodeID"]))? $_REQUEST["tawasulAttendanceCodeID"] : 0;
    $reportType = (empty($tawasulAttendanceCodeID))? 'types' : 'reasons';

    $form = Form::create('action', $session->get('absoluteURL').'/index.php','get');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', "/modules/".$session->get('module')."/report_summary_byDate.php");

    $row = $form->addRow();
        $row->addLabel('dateStart', __('Start Date'));
        $row->addDate('dateStart')->setValue($dateStart)->required();

    $row = $form->addRow();
        $row->addLabel('dateEnd', __('End Date'));
        $row->addDate('dateEnd')->setValue($dateEnd)->required();

    $options = array("all" => __('All Students'));
    if (isActionAccessible($guid, $connection2, "/modules/TawasulAttendance/attendance_take_byCourseClass.php")) {
        $options["class"] = __('Class');
    }
    if (isActionAccessible($guid, $connection2, "/modules/TawasulAttendance/attendance_take_byFormGroup.php")) {
        $options["formGroup"] = __('Form Group');
    }
    $row = $form->addRow();
        $row->addLabel('group', __('Group By'));
        $row->addSelect('group')->fromArray($options)->selected($group)->required();

    $form->toggleVisibilityByClass('class')->onSelect('group')->when('class');
    $row = $form->addRow()->addClass('class');
        $row->addLabel('tawasulCourseClassID', __('Class'));
        $row->addSelectClass('tawasulCourseClassID', $session->get('tawasulSchoolYearID'))->selected($tawasulCourseClassID)->placeholder()->required();

    $form->toggleVisibilityByClass('formGroup')->onSelect('group')->when('formGroup');
    $row = $form->addRow()->addClass('formGroup');
        $row->addLabel('tawasulFormGroupID', __('Form Group'));
        $row->addSelectFormGroup('tawasulFormGroupID', $session->get('tawasulSchoolYearID'))->selected($tawasulFormGroupID)->placeholder()->required();

    $row = $form->addRow();
        $row->addLabel('sort', __('Sort By'));
        $row->addSelect('sort')->fromArray(array('surname' => __('Surname'), 'preferredName' => __('Preferred Name'), 'formGroup' => __('Form Group')))->selected($sort)->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    // Stop outputting if the form hasn't been submitted yet
    if (empty($group) || empty($sort)) {
        return;
    }

    // Get attendance codes
    try {
        if (!empty($tawasulAttendanceCodeID)) {
            $dataCodes = array( 'tawasulAttendanceCodeID' => $tawasulAttendanceCodeID);
            $sqlCodes = "SELECT direction as groupBy, tawasulAttendanceCode.* FROM tawasulAttendanceCode WHERE tawasulAttendanceCodeID=:tawasulAttendanceCodeID";
        } else {
            $dataCodes = array();
            $sqlCodes = "SELECT direction as groupBy, tawasulAttendanceCode.* FROM tawasulAttendanceCode WHERE active = 'Y' AND reportable='Y' ORDER BY sequenceNumber ASC, name";
        }

        $resultCodes = $pdo->select($sqlCodes, $dataCodes);
    } catch (PDOException $e) {
    }

    $attendanceCodes = $resultCodes->fetchGrouped();
    $attendanceReasons = explode(',', $settingGateway->getSettingByScope('Attendance', 'attendanceReasons') );
    $attendanceReasons[] = 'No Reason';

    if ($resultCodes->rowCount() == 0) {
        echo "<div class='error'>";
        echo __('There are no attendance codes defined.');
        echo '</div>';
    }
    else if ( empty($dateStart) || empty($group)) {
        echo $page->getBlankSlate();
    } else if ($dateStart > $today || $dateEnd > $today) {
            echo "<div class='error'>";
            echo __('The specified date is in the future: it must be today or earlier.');
            echo '</div>';
    } else {
        echo '<h2>';
        echo __('Report Data').': '. Format::dateRangeReadable($dateStart, $dateEnd);
        echo '</h2>';


            $dataSchoolDays = array( 'dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
            $sqlSchoolDays = "SELECT 
                COUNT(DISTINCT CASE WHEN tawasulAttendanceLogPerson.date>=tawasulSchoolYear.firstDay AND tawasulAttendanceLogPerson.date<=tawasulSchoolYear.lastDay THEN tawasulAttendanceLogPerson.date END) as total, COUNT(DISTINCT CASE WHEN tawasulAttendanceLogPerson.date>=:dateStart AND tawasulAttendanceLogPerson.date <=:dateEnd THEN tawasulAttendanceLogPerson.date END) as dateRange 
            FROM tawasulAttendanceLogPerson
                JOIN tawasulSchoolYearTerm ON (tawasulAttendanceLogPerson.date>=tawasulSchoolYearTerm.firstDay AND tawasulAttendanceLogPerson.date <= tawasulSchoolYearTerm.lastDay)
                JOIN tawasulSchoolYear ON (tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID )
                LEFT JOIN tawasulSchoolYearSpecialDay ON (tawasulSchoolYearSpecialDay.tawasulSchoolYearTermID=tawasulSchoolYearTerm.tawasulSchoolYearTermID AND tawasulSchoolYearSpecialDay.date = tawasulAttendanceLogPerson.date AND tawasulSchoolYearSpecialDay.type='School Closure')
            WHERE  
                tawasulAttendanceLogPerson.date <= NOW() 
                AND tawasulSchoolYear.tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulSchoolYearSpecialDay.tawasulSchoolYearSpecialDayID IS NULL";

            $resultSchoolDays = $connection2->prepare($sqlSchoolDays);
            $resultSchoolDays->execute($dataSchoolDays);
        $schoolDayCounts = $resultSchoolDays->fetch();

        echo '<p style="color:#666;">';
            echo '<strong>' . __('Total number of school days to date:').' '.$schoolDayCounts['total'].'</strong><br/>';
            echo __('Total number of school days in date range:').' '.$schoolDayCounts['dateRange'];
        echo '</p>';

        $data = array('dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));

        //Produce array of attendance data
        try {
            $orderBy = 'ORDER BY surname, preferredName, tawasulAttendanceLogPerson.date, tawasulAttendanceLogPerson.timestampTaken';
            if ($sort == 'preferredName')
                $orderBy = 'ORDER BY preferredName, surname, tawasulAttendanceLogPerson.date, tawasulAttendanceLogPerson.timestampTaken';
            if ($sort == 'formGroup')
                $orderBy = ' ORDER BY LENGTH(formGroup), formGroup, surname, preferredName, tawasulAttendanceLogPerson.date, tawasulAttendanceLogPerson.timestampTaken';

            if ($group == 'all') {
                $sql = "SELECT tawasulPerson.tawasulPersonID, tawasulFormGroup.nameShort AS formGroup, surname, preferredName, tawasulAttendanceLogPerson.*, tawasulAttendanceCode.nameShort as code FROM tawasulAttendanceLogPerson JOIN tawasulAttendanceCode ON (tawasulAttendanceLogPerson.type=tawasulAttendanceCode.name) JOIN tawasulPerson ON (tawasulAttendanceLogPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE date>=:dateStart AND date<=:dateEnd AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID";
            }
            else if ($group == 'class') {
                $data['tawasulCourseClassID'] = $tawasulCourseClassID;
                $sql = "SELECT tawasulPerson.tawasulPersonID, tawasulFormGroup.nameShort AS formGroup, surname, preferredName, tawasulAttendanceLogPerson.*, tawasulAttendanceCode.nameShort as code FROM tawasulAttendanceLogPerson JOIN tawasulAttendanceCode ON (tawasulAttendanceLogPerson.type=tawasulAttendanceCode.name) JOIN tawasulPerson ON (tawasulAttendanceLogPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE date>=:dateStart AND date<=:dateEnd AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulAttendanceLogPerson.context='Class' AND tawasulAttendanceLogPerson.tawasulCourseClassID=:tawasulCourseClassID";
            }
            else if ($group == 'formGroup') {
                $data['tawasulFormGroupID'] = $tawasulFormGroupID;
                $sql = "SELECT tawasulPerson.tawasulPersonID, tawasulFormGroup.nameShort AS formGroup, surname, preferredName, tawasulAttendanceLogPerson.*, tawasulAttendanceCode.nameShort as code FROM tawasulAttendanceLogPerson JOIN tawasulAttendanceCode ON (tawasulAttendanceLogPerson.type=tawasulAttendanceCode.name) JOIN tawasulPerson ON (tawasulAttendanceLogPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE date>=:dateStart AND date<=:dateEnd AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulStudentEnrolment.tawasulFormGroupID=:tawasulFormGroupID";
            }

            if ( !empty($tawasulAttendanceCodeID) ) {
                $data['tawasulAttendanceCodeID'] = $tawasulAttendanceCodeID;
                $sql .= ' AND tawasulAttendanceCode.tawasulAttendanceCodeID=:tawasulAttendanceCodeID';
            }

            if ($countClassAsSchool == 'N' && $group != 'class') {
                $sql .= " AND NOT context='Class'";
            }

            $sql .= ' '. $orderBy;

            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
        }

        if ($result->rowCount() < 1) {
            echo $page->getBlankSlate();
        } else {

            if (empty($daysOfWeek)) {
                $sql = "SELECT nameShort, name FROM tawasulDaysOfWeek where schoolDay='Y'";
                $daysOfWeek = $pdo->select($sql)->fetchKeyPair();
            }
    
            if (empty($schoolClosures)) {
                $data = ['dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID')];
                $sql = "SELECT tawasulSchoolYearSpecialDay.date, tawasulSchoolYearSpecialDay.name 
                        FROM tawasulSchoolYear 
                        JOIN tawasulSchoolYearTerm ON (tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                        JOIN tawasulSchoolYearSpecialDay ON (tawasulSchoolYearTerm.tawasulSchoolYearTermID=tawasulSchoolYearSpecialDay.tawasulSchoolYearTermID)
                        WHERE tawasulSchoolYear.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulSchoolYearSpecialDay.type='School Closure' AND tawasulSchoolYearSpecialDay.date BETWEEN :dateStart AND :dateEnd
                        ORDER BY date";
                $schoolClosures = $pdo->select($sql, $data)->fetchKeyPair();
            }

            $dateRange = new DatePeriod(
                new DateTimeImmutable($dateStart),
                new DateInterval('P1D'),
                (new DateTimeImmutable($dateEnd))->modify('+1 day')
            );

            // Group the results by person first, then by date
            $attendanceResult = array_reduce($result->fetchAll(), function ($group, $item) {
                if (!isset($group[$item['tawasulPersonID']])) {
                    $group[$item['tawasulPersonID']] = [];
                }
                $group[$item['tawasulPersonID']][$item['date']][] = $item;
                return $group;
            }, []);

            $attendanceData = [];

            foreach ($attendanceResult as $tawasulPersonID => $values) {

                if (!isset($attendanceData[$tawasulPersonID])) {
                    $log = current(current($values));
                    $attendanceData[$tawasulPersonID]['formGroup'] = $log['formGroup'];
                    $attendanceData[$tawasulPersonID]['preferredName'] = $log['preferredName'];
                    $attendanceData[$tawasulPersonID]['surname'] = $log['surname'];
                }

                foreach ($dateRange as $date) {
                    if ($date->format('Y-m-d') > date('Y-m-d')) continue;
    
                    // Skip non-school days and school closures
                    if (!isset($daysOfWeek[$date->format('D')])) continue;
                    if (isset($schoolClosures[$date->format('Y-m-d')])) continue;
    
                    $logs = $values[$date->format('Y-m-d')] ?? [];

                    if (empty($logs)) continue;

                    if ($group == 'class') {
                        // Count all class logs
                        foreach ($logs as $log) {
                            $attendanceData[$tawasulPersonID][$log['code']] = ($attendanceData[$tawasulPersonID][$log['code']] ?? 0) + 1;

                            $reason = !empty($log['reason']) ? $log['reason'] : 'No Reason';
                            $attendanceData[$tawasulPersonID][$reason] = ($attendanceData[$tawasulPersonID][$reason] ?? 0) + 1;
                        }
                    } else {
                        // Count only the end of day logs
                        $endOfDay = end($logs);

                        $attendanceData[$tawasulPersonID][$endOfDay['code']] = ($attendanceData[$tawasulPersonID][$endOfDay['code']] ?? 0) + 1;

                        $reason = !empty($endOfDay['reason']) ? $endOfDay['reason'] : 'No Reason';
                        $attendanceData[$tawasulPersonID][$reason] = ($attendanceData[$tawasulPersonID][$reason] ?? 0) + 1;
                    }

                    $attendanceData[$tawasulPersonID]['total'] = ($attendanceData[$tawasulPersonID]['total'] ?? 0) + 1;
                }
            }

            echo '<table cellspacing="0" class="w-full colorOddEven" >';

            echo "<tr class='head'>";
            echo '<th style="width:80px" rowspan=2>';
            echo __('Form Group');
            echo '</th>';
            echo '<th rowspan=2>';
            echo __('Name');
            echo '</th>';

            if ($reportType == 'types') {
                echo '<th colspan='.count($attendanceCodes['In']).' class="columnDivider" style="text-align:center;">';
                echo __('IN');
                echo '</th>';
                echo '<th colspan='.count($attendanceCodes['Out']).' class="columnDivider" style="text-align:center;">';
                echo __('OUT');
                echo '</th>';
                echo '<th colspan=1 class="columnDivider" style="text-align:center;">';
                // echo ;
                echo '</th>';
            } else if ($reportType == 'reasons') {
                $attendanceCodeName = $pdo->selectOne("SELECT name FROM tawasulAttendanceCode WHERE tawasulAttendanceCodeID=:tawasulAttendanceCodeID", ['tawasulAttendanceCodeID' => $tawasulAttendanceCodeID]);
                echo '<th colspan='.count($attendanceReasons).' class="columnDivider" style="text-align:center;">';
                echo __($attendanceCodeName);
                echo '</th>';
            }
            echo '</tr>';


            echo '<tr class="head" style="min-height:80px;">';

            if ($reportType == 'types') {

                $href= $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/report_summary_byDate.php&dateStart='.$dateStart.'&dateEnd='.$dateEnd.'&tawasulCourseClassID='.$tawasulCourseClassID.'&tawasulFormGroupID='.$tawasulFormGroupID.'&group=' . $group . '&sort=' . $sort;

                for( $i = 0; $i < count($attendanceCodes['In']); $i++ ) {
                    echo '<th class="'.( $i == 0? 'verticalHeader columnDivider' : 'verticalHeader').'" title="'.__($attendanceCodes['In'][$i]['scope']).'">';
                        echo '<a class="verticalText" href="'.$href.'&tawasulAttendanceCodeID='.$attendanceCodes['In'][$i]['tawasulAttendanceCodeID'].'">';
                        echo __($attendanceCodes['In'][$i]['name']);
                        echo '</a>';
                    echo '</th>';
                }

                for( $i = 0; $i < count($attendanceCodes['Out']); $i++ ) {
                    echo '<th class="'.( $i == 0? 'verticalHeader columnDivider' : 'verticalHeader').'" title="'.__($attendanceCodes['Out'][$i]['scope']).'">';
                        echo '<a class="verticalText" href="'.$href.'&tawasulAttendanceCodeID='.$attendanceCodes['Out'][$i]['tawasulAttendanceCodeID'].'">';
                        echo __($attendanceCodes['Out'][$i]['name']);
                        echo '</a>';
                    echo '</th>';
                }

                echo '<th class="verticalHeader columnDivider" title="'.__('Total').'">';
                    echo '<div class="verticalText">';
                    echo __('Total');
                    echo '</div>';
                echo '</th>';

            } else if ($reportType == 'reasons') {
                for( $i = 0; $i < count($attendanceReasons); $i++ ) {
                    echo '<th class="'.( $i == 0? 'verticalHeader columnDivider' : 'verticalHeader').'">';
                        echo '<div class="verticalText">';
                        echo $attendanceReasons[$i] ?? '';
                        echo '</div>';
                    echo '</th>';
                }
            }

            echo '</tr>';

            foreach ($attendanceData as $tawasulPersonID => $values) {

                // ROW
                echo "<tr>";
                echo '<td>';
                    echo $values['formGroup'];
                echo '</td>';
                echo '<td>';
                    echo '<a href="index.php?q=/modules/TawasulAttendance/report_studentHistory.php&tawasulPersonID='.$tawasulPersonID.'" target="_blank">';
                    echo Format::name('', $values['preferredName'], $values['surname'], 'Student', ($sort != 'preferredName') );
                    echo '</a>';
                echo '</td>';

                if ($reportType == 'types') {
                    for( $i = 0; $i < count($attendanceCodes['In']); $i++ ) {
                        echo '<td class="center '.( $i == 0? 'columnDivider' : '').'">';
                            echo $values[ $attendanceCodes['In'][$i]['nameShort'] ] ?? 0;
                        echo '</td>';
                    }

                    for( $i = 0; $i < count($attendanceCodes['Out']); $i++ ) {
                        echo '<td class="center '.( $i == 0? 'columnDivider' : '').'">';
                            echo $values[ $attendanceCodes['Out'][$i]['nameShort'] ] ?? 0;
                        echo '</td>';
                    }

                    echo '<td class="center '.( $i == 0? 'columnDivider' : '').'">';
                        echo $values[ 'total' ] ?? 0;
                    echo '</td>';
                } else if ($reportType == 'reasons') {
                    for( $i = 0; $i < count($attendanceReasons); $i++ ) {
                        echo '<td class="center '.( $i == 0? 'columnDivider' : '').'">';
                            echo $values[ $attendanceReasons[$i] ] ?? 0;
                        echo '</td>';
                    }
                }
                echo '</tr>';

            }

            echo '</table>';


        }
    }
}
?>
