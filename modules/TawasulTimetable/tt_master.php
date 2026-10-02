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
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\Timetable\TimetableGateway;
use TawasulOS\Domain\Timetable\TimetableDayGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/tt_master.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('View Master Timetable'));

    echo '<h2>';
    echo __('Choose Timetable');
    echo '</h2>';

    $tawasulTTID = null;
    if (isset($_GET['tawasulTTID'])) {
        $tawasulTTID = $_GET['tawasulTTID'] ?? '';
    }
    if ($tawasulTTID == null) { //If TT not set, get the first timetable in the current year, and display that
        
            $dataSelect = array();
            $sqlSelect = "SELECT tawasulTTID FROM tawasulTT JOIN tawasulSchoolYear ON (tawasulTT.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) WHERE tawasulSchoolYear.status='Current' ORDER BY tawasulTT.name LIMIT 0, 1";
            $resultSelect = $connection2->prepare($sqlSelect);
            $resultSelect->execute($dataSelect);
        if ($resultSelect->rowCount() == 1) {
            $rowSelect = $resultSelect->fetch();
            $tawasulTTID = $rowSelect['tawasulTTID'];
        }
    }

    $form = Form::create('ttMaster', $session->get('absoluteURL').'/index.php', 'get');

    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/tt_master.php');

    $sql = "SELECT tawasulSchoolYear.name as groupedBy, tawasulTTID as value, tawasulTT.name AS name FROM tawasulTT JOIN tawasulSchoolYear ON (tawasulTT.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) ORDER BY tawasulSchoolYear.sequenceNumber, tawasulTT.name";
    $result = $pdo->executeQuery(array(), $sql);

    // Transform into an option list grouped by Year
    $ttList = ($result && $result->rowCount() > 0)? $result->fetchAll() : array();
    $ttList = array_reduce($ttList, function($list, $item) {
        $list[$item['groupedBy']][$item['value']] = $item['name'];
        return $list;
    }, array());

    $row = $form->addRow();
        $row->addLabel('tawasulTTID', __('Timetable'));
        $row->addSelect('tawasulTTID')->fromArray($ttList)->required()->selected($tawasulTTID);

    $row = $form->addRow();
        $row->addSearchSubmit($session);


    echo $form->getOutput();

    if ($tawasulTTID != '') {

        $timetableGateway = $container->get(TimetableGateway::class);
        $timetableDayGateway = $container->get(TimetableDayGateway::class);
        
        $values = $timetableGateway->getTTByID($tawasulTTID);
        $ttDays = $timetableDayGateway->selectTTDaysByID($tawasulTTID)->fetchAll();

        if (empty($values) || empty($ttDays)) {
            echo $page->getBlankSlate();;
        } else {
            foreach ($ttDays as $ttDay) {
                echo '<h2 style="margin-top: 40px">';
                echo __($ttDay['name']);
                echo '</h2>';

                $ttDayRows = $timetableDayGateway->selectTTDayRowsByID($ttDay['tawasulTTDayID'])->fetchAll();
                
                foreach ($ttDayRows as $ttDayRow) {
                    echo '<h5 style="margin-top: 25px">';
                    echo __($ttDayRow['name']).'<span style=\'font-weight: normal\'> ('.Format::timeRange($ttDayRow['timeStart'], $ttDayRow['timeEnd']).')</span>';
                    echo '</h5>';

                    $ttDayRowClasses = $timetableDayGateway->selectTTDayRowClassesByID($ttDay['tawasulTTDayID'], $ttDayRow['tawasulTTColumnRowID']);

                    if ($ttDayRowClasses->isEmpty()) {
                        echo '<div class="warning">';
                        echo __('There are no classes associated with this period on this day.');
                        echo '</div>';
                    } else {
                        $table = DataTable::create('timetableDayRowClasses');

                        $table->modifyRows(function ($data, $row) {
                            return $row->addClass('compactRow');
                        });

                        $table->addColumn('class', __('Class'))->format(Format::using('courseClassName', ['courseName', 'className']));
                        $table->addColumn('location', __('Location'));
                        $table->addColumn('teachers', __('Teachers'))->format(function($class) use ($timetableDayGateway) {
                            $teachers = $timetableDayGateway->selectTTDayRowClassTeachersByID($class['tawasulTTDayRowClassID'])->fetchAll();
                            return Format::nameList($teachers, 'Staff', false, true);
                        });

                        echo $table->render($ttDayRowClasses->toDataSet());
                    }
                }
            }
        }
    }
}

