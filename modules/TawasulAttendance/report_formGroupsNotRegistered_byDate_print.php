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
use TawasulOS\Forms\Form;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/report_formGroupsNotRegistered_byDate_print.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {

    $today = date('Y-m-d');

    $dateEnd = (isset($_GET['dateEnd']))? $_GET['dateEnd'] : date('Y-m-d');
    $dateStart = (isset($_GET['dateStart']))? $_GET['dateStart'] : date('Y-m-d', strtotime( $dateEnd.' -4 days') );

    $datediff = strtotime($dateEnd) - strtotime($dateStart);
    $daysBetweenDates = floor($datediff / (60 * 60 * 24)) + 1;

    $lastSetOfSchoolDays = getLastNSchoolDays($guid, $connection2, $dateEnd, $daysBetweenDates, true);

    $lastNSchoolDays = array();
    for($i = 0; $i < count($lastSetOfSchoolDays); $i++) {
        if ( $lastSetOfSchoolDays[$i] >= $dateStart  ) $lastNSchoolDays[] = $lastSetOfSchoolDays[$i];
    }

    //Proceed!
    echo '<h2>';
    if ($dateStart != $dateEnd) {
        echo __('Form Groups Not Registered').', '.Format::date($dateStart).'-'.Format::date($dateEnd);
    } else {
        echo __('Form Groups Not Registered').', '.Format::date($dateStart);
    }
    echo '</h2>';

    //Produce array of attendance data
    $data = array('dateStart' => $lastNSchoolDays[count($lastNSchoolDays)-1], 'dateEnd' => $lastNSchoolDays[0] );
    $sql = 'SELECT date, tawasulFormGroupID, UNIX_TIMESTAMP(timestampTaken) FROM tawasulAttendanceLogFormGroup WHERE date>=:dateStart AND date<=:dateEnd ORDER BY date';
    $result = $connection2->prepare($sql);
    $result->execute($data);
    $log = array();
    while ($row = $result->fetch()) {
        $log[$row['tawasulFormGroupID']][$row['date']] = true;
    }

    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
    $sql = "SELECT tawasulFormGroupID, name, tawasulPersonIDTutor, tawasulPersonIDTutor2, tawasulPersonIDTutor3 FROM tawasulFormGroup WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND attendance='Y' ORDER BY LENGTH(name), name";
    $result = $connection2->prepare($sql);
    $result->execute($data);

    if ( count($lastNSchoolDays) == 0 ) {
        echo "<div class='error'>";
        echo __('School is closed on the specified date, and so attendance information cannot be recorded.');
        echo '</div>';
    } else if ($result->rowCount() < 1) {
        echo $page->getBlankSlate();
    } else if ($dateStart > $today || $dateEnd > $today) {
        echo "<div class='error'>";
        echo __('The specified date is in the future: it must be today or earlier.');
        echo '</div>';
    } else {
        //Produce array of form groups
        $formGroups = $result->fetchAll();

        $form = Form::createBlank('buttons');
        $form->addHeaderAction('print', __('Print'))
            ->setURL('#')
            ->onClick('javascript:window.print(); return false;');
        echo $form->getOutput();

        echo "<table cellspacing='0' style='width: 100%'>";
        echo "<tr class='head'>";
        echo '<th>';
        echo __('Form Group');
        echo '</th>';
        echo '<th >';
        echo __('Date');
        echo '</th>';
        echo '<th width="164px">';
        echo __('History');
        echo '</th>';
        echo '<th>';
        echo __('Tutor');
        echo '</th>';
        echo '</tr>';

        $count = 0;

        foreach ($formGroups as $row) {

            //Output row only if not registered on specified date
            if ( isset($log[$row['tawasulFormGroupID']]) == false || count($log[$row['tawasulFormGroupID']]) < count($lastNSchoolDays) ) {
                ++$count;

                //COLOR ROW BY STATUS!
                echo "<tr>";
                echo '<td>';
                echo $row['name'];
                echo '</td>';
                echo '<td>';
                echo Format::dateRangeReadable($dateStart, $dateEnd);
                echo '</td>';
                echo '<td style="padding: 0;">';

                    echo "<table cellspacing='0' class='historyCalendarMini' style='width:160px;margin:0;' >";
                    echo '<tr>';
                    $historyCount = 0;
                    for ($i = count($lastNSchoolDays)-1; $i >= 0; --$i) {

                        $link = '';
                        if ($i > ( count($lastNSchoolDays) - 1)) {
                            echo "<td class='highlightNoData'>";
                            echo '<i>'.__('NA').'</i>';
                            echo '</td>';
                        } else {
                            if (isset($log[$row['tawasulFormGroupID']][$lastNSchoolDays[$i]]) == false) {
                                //$class = 'highlightNoData';
                                $class = 'highlightAbsent';
                            } else {
                                $link = './index.php?q=/modules/TawasulAttendance/attendance_take_byFormGroup.php&tawasulFormGroupID='.$row['tawasulFormGroupID'].'&currentDate='.$lastNSchoolDays[$i];
                                $class = 'highlightPresent';
                            }

                            echo "<td class='$class' style='padding: 12px !important;'>";
                            if ($link != '') {
                                echo "<a href='$link'>";
                                echo Format::date($lastNSchoolDays[$i], 'd').'<br/>';
                                echo "<span>".Format::monthName($lastNSchoolDays[$i], true).'</span>';
                                echo '</a>';
                            } else {
                                echo Format::date($lastNSchoolDays[$i], 'd').'<br/>';
                                echo "<span>".Format::monthName($lastNSchoolDays[$i], true).'</span>';
                            }
                            echo '</td>';
                        }

                        // Wrap to a new line every 10 dates
                        if (  ($historyCount+1) % 10 == 0 ) {
                            echo '</tr><tr>';
                        }

                        $historyCount++;
                    }

                    echo '</tr>';
                    echo '</table>';

                echo '</td>';
                echo '<td>';
                if ($row['tawasulPersonIDTutor'] == '' and $row['tawasulPersonIDTutor2'] == '' and $row['tawasulPersonIDTutor3'] == '') {
                    echo '<i>Not set</i>';
                } else {

                    $dataTutor = array('tawasulPersonID1' => $row['tawasulPersonIDTutor'], 'tawasulPersonID2' => $row['tawasulPersonIDTutor2'], 'tawasulPersonID3' => $row['tawasulPersonIDTutor3']);
                    $sqlTutor = "SELECT surname, preferredName FROM tawasulPerson WHERE (tawasulPersonID=:tawasulPersonID1 OR tawasulPersonID=:tawasulPersonID2 OR tawasulPersonID=:tawasulPersonID3) AND tawasulPerson.status='Full'";
                    $resultTutor = $connection2->prepare($sqlTutor);
                    $resultTutor->execute($dataTutor);

                    while ($rowTutor = $resultTutor->fetch()) {
                        echo Format::name('', $rowTutor['preferredName'], $rowTutor['surname'], 'Staff', true, true).'<br/>';
                    }
                }
                echo '</td>';
                echo '</tr>';
            }
        }

        if ($count == 0) {
            echo "<tr class=$rowNum>";
            echo '<td colspan=4>';
            echo __('All form groups have been registered.');
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}
