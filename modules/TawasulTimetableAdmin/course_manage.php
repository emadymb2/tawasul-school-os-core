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
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\School\SchoolYearGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/course_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Courses & Classes'));

    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
    $nextYear = $container->get(SchoolYearGateway::class)->getNextSchoolYearByID($tawasulSchoolYearID);

    if ($tawasulSchoolYearID != '') {
        $page->navigator->addSchoolYearNavigation($tawasulSchoolYearID);

        $search = (isset($_GET['search']))? $_GET['search'] : '';
        $tawasulYearGroupID = (isset($_GET['tawasulYearGroupID']))? $_GET['tawasulYearGroupID'] : '';

        $courseGateway = $container->get(CourseGateway::class);

        // CRITERIA
        $criteria = $courseGateway->newQueryCriteria(true)
            ->searchBy($courseGateway->getSearchableColumns(), $search)
            ->sortBy(['tawasulCourse.nameShort', 'tawasulCourse.name'])
            ->filterBy('yearGroup', $tawasulYearGroupID)
            ->fromPOST();

        echo '<h3>';
        echo __('Filters');
        echo '</h3>';

        $form = Form::create('action', $session->get('absoluteURL').'/index.php','get');

        $form->setFactory(DatabaseFormFactory::create($pdo));
        $form->setClass('noIntBorder w-full');

        $form->addHiddenValue('q', "/modules/".$session->get('module')."/course_manage.php");
        $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        $row = $form->addRow();
            $row->addLabel('search', __('Search For'));
            $row->addTextField('search')->setValue($criteria->getSearchText());

        $row = $form->addRow();
            $row->addLabel('tawasulYearGroupID', __('Year Group'));
            $row->addSelectYearGroup('tawasulYearGroupID')->selected($criteria->getFilterValue('yearGroup'));

        $row = $form->addRow();
            $row->addSearchSubmit($session, __('Clear Filters'), array('tawasulSchoolYearID'));

        echo $form->getOutput();

        echo '<h3>';
        echo __('View');
        echo '</h3>';

        // Limit the grid to the branch being viewed, when multi-branch is on.
        tosBranchHelpers();
        $branch = tosBranchScope($session, $container, $pdo, 'tawasulCourseClass', 'Courses');

        $courses = $courseGateway->queryCoursesBySchoolYear(
            $criteria,
            $tawasulSchoolYearID,
            $branch['cond'] ?? '',
            $branch['params'] ?? []
        );

        // DATA TABLE
        $table = DataTable::createPaginated('courseManage', $criteria);

        if (!empty($nextYear)) {
            $table->addHeaderAction('copy', __('Copy All To Next Year'))
                ->setURL('/modules/TawasulTimetableAdmin/course_manage_copyProcess.php')
                ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                ->addParam('tawasulSchoolYearIDNext', $nextYear['tawasulSchoolYearID'])
                ->addParam('search', $search)
                ->setIcon('copy')
                ->onCLick('return confirm("'.__('Are you sure you want to do this? All courses and classes, but not their participants, will be copied.').'");')
                ->displayLabel();
        }

        $table->addHeaderAction('add', __('Add'))
            ->setURL('/modules/TawasulTimetableAdmin/course_manage_add.php')
            ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->addParam('search', $search)
            ->displayLabel();

        // COLUMNS
        $table->addColumn('nameShort', __('Short Name'));
        $table->addColumn('name', __('Name'));
        $table->addColumn('department', __('Learning Area'));
        $table->addColumn('classCount', __('Classes'));

        // ACTIONS
        $table->addActionColumn()
            ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->addParam('tawasulCourseID')
            ->addParam('search', $criteria->getSearchText(true))
            ->format(function ($course, $actions) {
                $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulTimetableAdmin/course_manage_edit.php');

                $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/TawasulTimetableAdmin/course_manage_delete.php');
            });

        echo $table->render($courses);
    }
}
