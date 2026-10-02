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

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_attendance_sheet.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Printable Attendance Sheet'));

    echo '<h2>';
    echo __('Choose Activity');
    echo '</h2>';

    $tawasulActivityID = null;
    if (isset($_GET['tawasulActivityID'])) {
        $tawasulActivityID = $_GET['tawasulActivityID'] ?? '';
    }

    $numberOfColumns = (isset($_GET['numberOfColumns']) && $_GET['numberOfColumns'] <= 20 ) ? $_GET['numberOfColumns'] : 20;

    $form = Form::create('action', $session->get('absoluteURL').'/index.php','get');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', "/modules/".$session->get('module')."/activities_attendance_sheet.php");

    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
    $sql = "SELECT tawasulActivityID AS value, name FROM tawasulActivity WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND active='Y' ORDER BY name, programStart";
    $row = $form->addRow();
        $row->addLabel('tawasulActivityID', __('Activity'));
        $row->addSearchSelect('tawasulActivityID')->fromQuery($pdo, $sql, $data)->selected($tawasulActivityID)->required()->placeholder();

    $row = $form->addRow();
        $row->addLabel('numberOfColumns', __('Number of Columns'));
        $row->addSelect('numberOfColumns')->fromArray(range(1, 20))->selected($numberOfColumns)->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    if ($tawasulActivityID != '') {
        $output = '';
        echo '<h2>';
        echo __('Report Data');
        echo '</h2>';

        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulActivityID' => $tawasulActivityID);
        $sql = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName, tawasulFormGroupID, tawasulActivityStudent.status FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulActivityStudent.status='Accepted' AND tawasulActivityID=:tawasulActivityID ORDER BY tawasulActivityStudent.status, surname, preferredName";
        $result = $connection2->prepare($sql);
        $result->execute($data);

        if ($result->rowCount() < 1) {
            echo $page->getBlankSlate();
        } else {
            $form = Form::createBlank('buttons');
            $form->addHeaderAction('print', __('Print'))
                ->setURL('/report.php')
                ->addParam('q', '/modules/TawasulActivities/activities_attendance_sheetPrint.php')
                ->addParam('tawasulActivityID', $tawasulActivityID)
                ->addParam('numberOfColumns', $numberOfColumns)
                ->addParam('format', 'print')
                ->setTarget('_blank')
                ->directLink();
            echo $form->getOutput();

            $lastPerson = '';

            echo "<table class='mini' cellspacing='0' style='width: 100%'>";
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
                echo $count.'. '.Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
                echo '</td>';
                for ($i = 1; $i <= $numberOfColumns; ++$i) {
                    echo '<td></td>';
                }
                echo '</tr>';

                $lastPerson = $row['tawasulPersonID'];
            }
            if ($count == 0) {
                echo "<tr class=$rowNum>";
                echo '<td colspan=16>';
                echo __('There are no records to display.');
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    }
}
?>
