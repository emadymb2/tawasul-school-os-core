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
use TawasulOS\Domain\Timetable\CourseGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$urlParams = [
    'tawasulSchoolYearID' => $_GET['tawasulSchoolYearID'] ?? '',
    'tawasulCourseID' => $_GET['tawasulCourseID'] ?? '',
    'tawasulCourseClassID' => $_GET['tawasulCourseClassID'] ?? '',
    'tawasulUnitID' => $_GET['tawasulUnitID'] ?? '',
    'tawasulUnitBlockID' => $_GET['tawasulUnitBlockID'] ?? '',
    'tawasulUnitClassBlockID' => $_GET['tawasulUnitClassBlockID'] ?? '',
    'tawasulUnitClassID' => $_GET['tawasulUnitClassID'] ?? '',
];

$page->breadcrumbs
    ->add(__('Unit Planner'), 'units.php', $urlParams)
    ->add(__('Edit Unit'), 'units_edit.php', $urlParams)
    ->add(__('Edit Working Copy'), 'units_edit_working.php', $urlParams)
    ->add(__('Copy Back Block'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_edit_working_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    }

    // Proceed!
    // Check if course & school year specified
    if ($urlParams['tawasulCourseID'] == '' or $urlParams['tawasulSchoolYearID'] == '' or $urlParams['tawasulCourseClassID'] == '' or $urlParams['tawasulUnitClassID'] == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $courseGateway = $container->get(CourseGateway::class);

    // Check access to specified course
    if ($highestAction == 'Unit Planner_all') {
        $result = $courseGateway->selectCourseDetailsByClass($urlParams['tawasulCourseClassID']);
    } elseif ($highestAction == 'Unit Planner_learningAreas') {
        $result = $courseGateway->selectCourseDetailsByClassAndPerson($urlParams['tawasulCourseClassID'], $session->get('tawasulPersonID'));
    }

    if ($result->rowCount() != 1) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }

    $values = $result->fetch();

    // Check if unit specified
    if ($urlParams['tawasulUnitID'] == '' or $urlParams['tawasulUnitBlockID'] == '' or $urlParams['tawasulUnitClassBlockID'] == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $data = ['tawasulUnitID' => $urlParams['tawasulUnitID'], 'tawasulCourseID' => $urlParams['tawasulCourseID'], 'tawasulUnitBlockID' => $urlParams['tawasulUnitBlockID'], 'tawasulUnitClassBlockID' => $urlParams['tawasulUnitClassBlockID']];
    $sql = 'SELECT tawasulUnitClassBlock.title AS block, tawasulCourse.nameShort AS courseName, tawasulUnit.name as unit FROM tawasulUnit JOIN tawasulCourse ON (tawasulUnit.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulUnitBlock ON (tawasulUnitBlock.tawasulUnitID=tawasulUnit.tawasulUnitID) JOIN tawasulUnitClassBlock ON (tawasulUnitClassBlock.tawasulUnitBlockID=tawasulUnitBlock.tawasulUnitBlockID) WHERE tawasulUnitClassBlockID=:tawasulUnitClassBlockID AND tawasulUnitBlock.tawasulUnitBlockID=:tawasulUnitBlockID AND tawasulUnit.tawasulUnitID=:tawasulUnitID AND tawasulUnit.tawasulCourseID=:tawasulCourseID';
    $result = $pdo->select($sql, $data);

    if ($result->rowCount() != 1) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
    $values += $result->fetch();

    // DETAILS
    $table = DataTable::createDetails('unit');

    $table->addColumn('schoolYear', __('School Year'));
    $table->addColumn('course', __('Class'))->format(Format::using('courseClassName', ['course', 'class']));
    $table->addColumn('unit', __('Unit'));

    $table->addColumn('block', __('Block Title'));

    echo $table->render([$values]);

    // FORM
    $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module').'/units_edit_working_copybackProcess.php?'.http_build_query($urlParams));

    $form->setTitle(__('Copy Back Block'));
    $form->setDescription(__('This action will use the selected block to replace the equivalent block in the master unit. The option below also lets you replace the equivalent block in all other working units within the unit.'));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValues($urlParams);

    $row = $form->addRow();
        $row->addLabel('working', __('Include Working Units?'));
        $row->addYesNo('working')->required()->selected('N');

    $form->addRow()->addConfirmSubmit();

    echo $form->getOutput();

    // Print sidebar
    $session->set('sidebarExtra', sidebarExtraUnits($guid, $connection2, $urlParams['tawasulCourseID'], $urlParams['tawasulSchoolYearID']));
}
