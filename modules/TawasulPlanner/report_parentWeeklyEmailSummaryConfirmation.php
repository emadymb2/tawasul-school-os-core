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
use TawasulOS\Services\Format;
use TawasulOS\Domain\User\FamilyGateway;
use TawasulOS\Forms\DatabaseFormFactory;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Parent Weekly Email Summary'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/report_parentWeeklyEmailSummaryConfirmation.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    echo '<p>';
    echo __('This report shows responses to the weekly summary email, organised by calendar week and role group.');
    echo '</p>';

    echo '<h2>';
    echo __('Choose Form Group & Week');
    echo '</h2>';

    $familyGateway = $container->get(FamilyGateway::class);
    $tawasulFormGroupID = isset($_GET['tawasulFormGroupID'])? $_GET['tawasulFormGroupID'] : null;
    $weekOfYear = isset($_GET['weekOfYear'])? $_GET['weekOfYear'] : null;

    $form = Form::create('searchForm', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/report_parentWeeklyEmailSummaryConfirmation.php');

    $row = $form->addRow();
        $row->addLabel('tawasulFormGroupID', __('Form Group'));
        $row->addSelectFormGroup('tawasulFormGroupID', $session->get('tawasulSchoolYearID'))->required()->selected($tawasulFormGroupID);

    $begin = new DateTime($session->get('tawasulSchoolYearFirstDay'));
    $end = new DateTime();
    $dateRange = new DatePeriod($begin, new DateInterval('P1W'), $end);

    $weeks = array();
    foreach ($dateRange as $date) {
        $weeks[$date->format('W')] = __('Week').' '.$date->format('W').': '.$date->format($session->get('i18n')['dateFormatPHP']);
    }
    $weeks = array_reverse($weeks, true);

    $row = $form->addRow();
        $row->addLabel('weekOfYear', __('Calendar Week'));
        $row->addSelect('weekOfYear')->fromArray($weeks)->selected($weekOfYear);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    if ($tawasulFormGroupID != '') {
        echo '<h2>';
        echo __('Report Data');
        echo '</h2>';


            $data = array('tawasulFormGroupID' => $tawasulFormGroupID);
            $sql = "SELECT student.surname AS studentSurname, student.preferredName AS studentPreferredName, parent.surname AS parentSurname, parent.preferredName AS parentPreferredName, parent.title AS parentTitle, tawasulFormGroup.name, student.tawasulPersonID AS tawasulPersonIDStudent, parent.tawasulPersonID AS tawasulPersonIDParent FROM tawasulPerson AS student JOIN tawasulStudentEnrolment ON (student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) LEFT JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=student.tawasulPersonID) LEFT JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) LEFT JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) LEFT JOIN tawasulPerson AS parent ON (tawasulFamilyAdult.tawasulPersonID=parent.tawasulPersonID) WHERE (tawasulFamilyAdult.contactPriority=1 OR tawasulFamilyAdult.contactPriority IS NULL) AND student.status='Full' AND parent.status='Full' AND (student.dateStart IS NULL OR student.dateStart<='".date('Y-m-d')."') AND (student.dateEnd IS NULL OR student.dateEnd>='".date('Y-m-d')."') AND tawasulStudentEnrolment.tawasulFormGroupID=:tawasulFormGroupID ORDER BY student.surname, student.preferredName, parent.surname, parent.preferredName";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        echo "<table cellspacing='0' style='width: 100%'>";
        echo "<tr class='head'>";
        echo '<th>';
        echo __('Student');
        echo '</th>';
        echo '<th>';
        echo __('Parents');
        echo '</th>';
        echo '<th>';
        echo __('Sent');
        echo '</th>';
        echo '<th>';
        echo __('Confirmed');
        echo '</th>';
        echo '</tr>';

        $count = 0;
        $rowNum = 'odd';
        while ($row = $result->fetch()) {
            if ($count % 2 == 0) {
                $rowNum = 'even';
            } else {
                $rowNum = 'odd';
            }
            ++$count;

            //COLOR ROW BY STATUS!
            echo "<tr class=$rowNum>";
            echo '<td>';
            echo "<a href='index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID=".$row['tawasulPersonIDStudent']."&subpage=Homework'>".Format::name('', $row['studentPreferredName'], $row['studentSurname'], 'Student', true).'</a>';
            echo '</td>';

            $dataData = array('tawasulPersonIDStudent' => $row['tawasulPersonIDStudent'],  'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'weekOfYear' => $weekOfYear);
            $sqlData = 'SELECT tawasulPlannerParentWeeklyEmailSummary.*, tawasulPerson.tawasulPersonID, tawasulPerson.title, tawasulPerson.preferredName, tawasulPerson.surname FROM tawasulPlannerParentWeeklyEmailSummary LEFT JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulPlannerParentWeeklyEmailSummary.tawasulPersonIDParent) WHERE tawasulPersonIDStudent=:tawasulPersonIDStudent AND tawasulSchoolYearID=:tawasulSchoolYearID AND weekOfYear=:weekOfYear';

            $rowData = $pdo->selectOne($sqlData, $dataData);

            $familyAdults = $familyGateway->selectFamilyAdultsByStudent($row['tawasulPersonIDStudent'])->fetchAll();
            $familyAdults = array_filter($familyAdults, function ($parent) {
                return $parent['contactEmail'] == 'Y';
            });

            echo '<td>';
            foreach ($familyAdults as $parent) {
                echo Format::name($parent['title'], $parent['preferredName'], $parent['surname'], 'Parent', true);

                echo !empty($rowData) && $parent['tawasulPersonID'] == $rowData['tawasulPersonID'] && $rowData['confirmed'] == 'Y'
                    ? ' ('.__('Confirmed') . ')<br/>'
                    : '<br/>';
            }
            echo '</td>';

            echo "<td style='width:15%'>";

            echo !empty($rowData)
                ? Format::tooltip(icon('solid', 'check', 'size-6 fill-current text-green-600'),  __('Sent'))
                : Format::tooltip(icon('solid', 'cross', 'size-6 fill-current text-red-700'),  __('Not Sent'));
            
            echo '</td>';
            echo "<td style='width:15%'>";
            if (empty($rowData)) {
                echo __('NA');
            } else {
                echo $rowData['confirmed'] == 'Y'
                    ? Format::tooltip(icon('solid', 'check', 'size-6 fill-current text-green-600'),  __('Confirmed'))
                    : Format::tooltip(icon('solid', 'cross', 'size-6 fill-current text-red-700'),  __('Not Confirmed'));
            }
            echo '</td>';
            echo '</tr>';
        }
        if ($count == 0) {
            echo "<tr class=$rowNum>";
            echo '<td colspan=4>';
            echo __('There are no records to display.');
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}
?>
