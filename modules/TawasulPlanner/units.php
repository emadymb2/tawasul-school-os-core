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
use TawasulOS\Domain\Planner\UnitGateway;
use TawasulOS\Forms\Prefab\BulkActionForm;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\School\SchoolYearGateway;
use TawasulOS\Support\Facades\Access;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Unit Planner'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units.php') == false) {
    //Acess denied
    $page->addError(__('Your request failed because you do not have access to this action.'));
} else {
    // Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    }

    /** @var SchoolYearGateway */
    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $courseGateway = $container->get(CourseGateway::class);
    $unitGateway = $container->get(UnitGateway::class);

    // School Year Info
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
    $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? null;
    $tawasulCourseID = $_GET['tawasulCourseID'] ?? null;

    if (empty($tawasulSchoolYearID)) {
        $page->addError(__('Your request failed because your inputs were invalid.'));
        return;
    }

    $courseName = $_GET['courseName'] ?? $session->get('courseNameUnitPlanner') ?? '';

    if (empty($tawasulCourseID) && !empty($courseName)) {
        $row = $container->get(CourseGateway::class)->selectBy(['tawasulSchoolYearID' => $tawasulSchoolYearID, 'nameShort' => $courseName])->fetch();
        $tawasulCourseID = $row['tawasulCourseID'] ?? '';
    }

    if (empty($tawasulCourseID)) {
        if ($highestAction == 'Unit Planner_all') {
            $courseList = $courseGateway->selectCoursesBySchoolYear($tawasulSchoolYearID)->fetchKeyPair();
        } elseif ($highestAction == 'Unit Planner_learningAreas') {
            $courseList = $courseGateway->selectCoursesByPerson($tawasulSchoolYearID, $session->get('tawasulPersonID'))->fetchKeyPair();
        }
        
        $tawasulCourseID = key($courseList);
    }
    
    if ($tawasulCourseID != '') {
        $row = $container->get(CourseGateway::class)->getByID($tawasulCourseID);
    }

    $page->navigator->addSchoolYearNavigation($tawasulSchoolYearID, ['courseName' => $row['nameShort'] ?? '']);

    //Work out previous and next course with same name
    $tawasulCourseIDPrevious = '';
    $tawasulSchoolYearPrevious = $schoolYearGateway->getPreviousSchoolYearByID($tawasulSchoolYearID);
    if ($tawasulSchoolYearPrevious != false and isset($row['nameShort'])) {
        $dataPrevious = array('tawasulSchoolYearID' => $tawasulSchoolYearPrevious['tawasulSchoolYearID'], 'nameShort' => $row['nameShort']);
        $sqlPrevious = 'SELECT * FROM tawasulCourse WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND nameShort=:nameShort';
        $resultPrevious = $connection2->prepare($sqlPrevious);
        $resultPrevious->execute($dataPrevious);
        if ($resultPrevious->rowCount() == 1) {
            $rowPrevious = $resultPrevious->fetch();
            $tawasulCourseIDPrevious = $rowPrevious['tawasulCourseID'];
        }
    }
    $tawasulCourseIDNext = '';
    $tawasulSchoolYearNext = $schoolYearGateway->getNextSchoolYearByID($tawasulSchoolYearID);
    if ($tawasulSchoolYearNext != false and isset($row['nameShort'])) {

        $dataNext = array('tawasulSchoolYearID' => $tawasulSchoolYearNext['tawasulSchoolYearID'], 'nameShort' => $row['nameShort']);
        $sqlNext = 'SELECT * FROM tawasulCourse WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND nameShort=:nameShort';
        $resultNext = $connection2->prepare($sqlNext);
        $resultNext->execute($dataNext);
        if ($resultNext->rowCount() == 1) {
            $rowNext = $resultNext->fetch();
            $tawasulCourseIDNext = $rowNext['tawasulCourseID'];
        }
    }

    if (empty($tawasulCourseID)) {
        $page->addError(__('You do not have access to edit the unit planner for any active courses in the current school year. You may need to be added to the relevant Department or Learning Area for the courses you are trying to access, or those courses may not have been added to the necessary Department or Learning Area.'));
        return;
    }

    try {
        if ($highestAction == 'Unit Planner_all') {
            $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulCourseID' => $tawasulCourseID);
            $sql = 'SELECT * FROM tawasulCourse WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseID=:tawasulCourseID';
        } elseif ($highestAction == 'Unit Planner_learningAreas') {
                        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulCourseID' => $tawasulCourseID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
            $sql = "SELECT tawasulCourseID, tawasulCourse.name, tawasulCourse.nameShort FROM tawasulCourse JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID AND (role='Coordinator' OR role='Assistant Coordinator' OR role='Teacher (Curriculum)') AND tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseID=:tawasulCourseID ORDER BY tawasulCourse.nameShort";
        }
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }

    if ($result->rowCount() < 1) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }

    // CRITERIA
    $criteria = $unitGateway->newQueryCriteria(true)
        ->sortBy(['ordering', 'name'])
        ->fromPOST();

    $course = $courseGateway->getByID($tawasulCourseID);
    $units = $unitGateway->queryUnitsByCourse($criteria, $tawasulCourseID);

    // FORM
    $form = BulkActionForm::create('bulkAction', $session->get('absoluteURL').'/modules/TawasulPlanner/unitsProcessBulk.php');
    $form->setTitle($course['name']);
    $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);
    $form->addHiddenValue('tawasulCourseID', $tawasulCourseID);

    $bulkActions = array(
        'Duplicate' => __('Duplicate'),
    );

    $courses = $courseGateway->selectActiveAndUpcomingCourses($tawasulSchoolYearID);
    $col = $form->createBulkActionColumn($bulkActions);
        $col->addSelect('tawasulCourseIDCopyTo')
            ->fromResults($courses, 'groupBy')
            ->required()
            ->placeholder()
            ->setClass('shortWidth copyTo');
        $col->addSubmit(__('Go'));

    $form->toggleVisibilityByClass('copyTo')->onSelect('action')->when('Duplicate');

    // DATA TABLE
    $table = $form->addRow()->addDataTable('unitList', $criteria)->withData($units);

    if (!empty($tawasulCourseClassID) && Access::allows('Planner', 'planner')) {
        $table->addHeaderAction('lesson', __('Lesson Planner'))
            ->setURL('/modules/TawasulPlanner/planner.php')
            ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->addParam('tawasulCourseClassID', $tawasulCourseClassID)
            ->addParam('viewBy', 'class')
            ->addParam('subView', 'lesson')
            ->setIcon('book-open')
            ->displayLabel();
    }

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/TawasulPlanner/units_add.php')
        ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
        ->addParam('tawasulCourseID', $tawasulCourseID)
        ->displayLabel();

    $table->addMetaData('bulkActions', $col);
    $table->addMetaData('filterOptions', [
        'active:Y' => __('Active').': '.__('Yes'),
        'active:N' => __('Active').': '.__('No'),
    ]);

    $table->addColumn('name', __('Name'))
        ->context('Primary');
    $table->addColumn('description', __('Description'))
        ->context('Secondary')
        ->width('45%');
    $table->addColumn('active', __('Active'))
        ->width('10%')
        ->format(Format::using('yesNo', 'active'));

    // ACTIONS
    $table->addActionColumn()
        ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
        ->addParam('tawasulCourseID', $tawasulCourseID)
        ->addParam('tawasulCourseClassID', $tawasulCourseClassID)
        ->addParam('tawasulUnitID')
        ->format(function ($unit, $actions) {
            $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulPlanner/units_edit.php');

            $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/TawasulPlanner/units_delete.php');

            $actions->addAction('duplicate', __('Duplicate'))
                    ->setIcon('copy')
                    ->setURL('/modules/TawasulPlanner/units_duplicate.php');

            $actions->addAction('view', __('Overview'))
                    ->addParam('sidebar', 'false')
                    ->setURL('/modules/TawasulPlanner/units_dump.php');
        });

    $table->addCheckboxColumn('tawasulUnitID');

    echo $form->getOutput();

    // Print sidebar
    $session->set('sidebarExtra',sidebarExtraUnits($guid, $connection2, $tawasulCourseID, $tawasulSchoolYearID));
}
