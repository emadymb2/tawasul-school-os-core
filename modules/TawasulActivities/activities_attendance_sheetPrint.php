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
use TawasulOS\Forms\Form;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_attendance_sheet.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $tawasulActivityID = $_GET['tawasulActivityID'] ?? '';
    $numberOfColumns = (isset($_GET['columns']) && $_GET['columns'] <= 20 ) ? $_GET['columns'] : 20;

    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulSchoolYearID2' => $session->get('tawasulSchoolYearID'), 'tawasulActivityID' => $tawasulActivityID);
    $sql = "SELECT name, programStart, programEnd, tawasulPerson.tawasulPersonID, surname, preferredName, tawasulFormGroupID, tawasulActivityStudent.status FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID2 AND tawasulActivityStudent.status='Accepted' AND tawasulActivity.tawasulActivityID=:tawasulActivityID ORDER BY tawasulActivityStudent.status, surname, preferredName";
    $result = $connection2->prepare($sql);
    $result->execute($data);

    if (empty($tawasulActivityID) || $result->rowCount() < 1) {
        echo $page->getBlankSlate();
    } else {
        $output = '';

        $results = $result->fetchAll();
        $row = current($results);

        $dateType = $container->get(SettingGateway::class)->getSettingByScope('Activities', 'dateType');
        $date = '';
        if ($dateType == 'Date') {
            if (substr($row['programStart'], 0, 4) == substr($row['programEnd'], 0, 4)) {
                if (substr($row['programStart'], 5, 2) == substr($row['programEnd'], 5, 2)) {
                    $date = ' ('.date('F', mktime(0, 0, 0, substr($row['programStart'], 5, 2))).' '.substr($row['programStart'], 0, 4).')';
                } else {
                    $date = ' ('.date('F', mktime(0, 0, 0, substr($row['programStart'], 5, 2))).' - '.date('F', mktime(0, 0, 0, substr($row['programEnd'], 5, 2))).' '.substr($row['programStart'], 0, 4).')';
                }
            } else {
                $date = ' ('.date('F', mktime(0, 0, 0, substr($row['programStart'], 5, 2))).' '.substr($row['programStart'], 0, 4).' - '.date('F', mktime(0, 0, 0, substr($row['programEnd'], 5, 2))).' '.substr($row['programEnd'], 0, 4).')';
            }
        }

        echo '<h2>';
        echo __('Participants for').' '.$row['name'].$date;
        echo '</h2>';

        $form = Form::createBlank('buttons');
        $form->addHeaderAction('print', __('Print'))
            ->setURL('#')
            ->onClick('javascript:window.print(); return false;');
        echo $form->getOutput();

        $lastPerson = '';
        $count = 0;

        $pages = array_chunk($results, 30);
        $pageCount = 1;
        foreach ($pages as $pagenum => $page) {

            echo "<table class='mini colorOddEven' cellspacing='0' style='width: 100%'>";
            echo "<tr class='head'>";
            echo '<th>';
            echo __('Student');
            echo '</th>';
            echo "<th colspan=$numberOfColumns>";
            echo __('Attendance');
            echo '</th>';
            echo '</tr>';
            echo "<tr style='height: 75px' class='odd'>";
            echo "<td style='vertical-align:top; width: 120px'>".__('Date')."</td>";
            for ($i = 1; $i <= $numberOfColumns; ++$i) {
                echo "<td style='color: #bbb; vertical-align:top; width: 15px'>$i</td>";
            }
            echo '</tr>';

            $rowNum = 'odd';

            $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulActivityID' => $tawasulActivityID);
            $sql = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName, tawasulFormGroupID, tawasulActivityStudent.status FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulActivityStudent.status='Accepted' AND tawasulActivityID=:tawasulActivityID ORDER BY tawasulActivityStudent.status, surname, preferredName";
            $result = $connection2->prepare($sql);
            $result->execute($data);
            while ($row = $result->fetch()) {
                ++$count;

                //COLOR ROW BY STATUS!
                echo '<tr>';
                echo '<td>';
                echo $count.'. '.Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
                echo '</td>';
                for ($i = 1; $i <= $numberOfColumns; ++$i) {
                    echo '<td></td>';
                }
                echo '</tr>';

                $lastPerson = $row['tawasulPersonID'];
            }

            echo '</table>';

            if ($pageCount < count($pages)) {
                echo "<div class='page-break'></div>";
            }
            ++$pageCount;
        }

    }
}
