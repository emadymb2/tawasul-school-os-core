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
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\Timetable\CourseClassGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Course Enrolment by Class'));

    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');

    if (empty($tawasulSchoolYearID)) {
        $page->addError(__('The specified record does not exist.'));
    } else {
        $page->navigator->addSchoolYearNavigation($tawasulSchoolYearID);

        $search = (isset($_GET['search']))? $_GET['search'] : '';
        $tawasulYearGroupID = (isset($_GET['tawasulYearGroupID']))? $_GET['tawasulYearGroupID'] : '';

        $courseGateway = $container->get(CourseGateway::class);
        $courseClassGateway = $container->get(CourseClassGateway::class);

        // CRITERIA
        $criteria = $courseGateway->newQueryCriteria()
            ->searchBy($courseGateway->getSearchableColumns(), $search)
            ->sortBy(['tawasulCourse.nameShort', 'tawasulCourse.name'])
            ->filterBy('yearGroup', $tawasulYearGroupID)
            ->fromPOST();

        echo '<h3>';
        echo __('Filters');
        echo '</h3>';

        $form = Form::create('searchForm', $session->get('absoluteURL').'/index.php', 'get');
        $form->setFactory(DatabaseFormFactory::create($pdo));

        $form->setClass('noIntBorder w-full');

        $form->addHiddenValue('q', '/modules/'.$session->get('module').'/courseEnrolment_manage.php');
        $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        $row = $form->addRow();
            $row->addLabel('search', __('Search For'));
            $row->addTextField('search')->setValue($criteria->getSearchText());

        $row = $form->addRow();
            $row->addLabel('tawasulYearGroupID', __('Year Group'));
            $row->addSelectYearGroup('tawasulYearGroupID')->selected($tawasulYearGroupID);

        $row = $form->addRow();
            $row->addSearchSubmit($session, __('Clear Search'), array('tawasulSchoolYearID'));

        echo $form->getOutput();

        // QUERY
        // Limit the grid to the branch being viewed, when multi-branch is on.
        tosBranchHelpers();
        $branch = tosBranchScope($session, $container, $pdo, 'tawasulCourseClass', 'Courses');

        $courses = $courseGateway->queryCoursesBySchoolYear(
            $criteria,
            $tawasulSchoolYearID,
            $branch['cond'] ?? '',
            $branch['params'] ?? []
        );

        if (count($courses) == 0) {
            echo $page->getBlankSlate();
            return;
        }

        foreach ($courses as $course) {
            echo '<h3>';
            echo $course['nameShort'].' ('.$course['name'].')';
            echo '</h3>';

            $classes = $courseClassGateway->selectClassesByCourseID($course['tawasulCourseID']);

            // DATA TABLE
            $table = DataTable::create('courseClassEnrolment');

            $table->addColumn('name', __('Name'));
            $table->addColumn('nameShort', __('Short Name'));
            $table->addColumn('teachersTotal', __('Teachers'));
            $table->addColumn('studentsActive', __('Students'))->description(__('Active'));
            $table->addColumn('studentsExpected', __('Students'))->description(__('Expected'));

            $table->addColumn('studentsTotal', __('Students'))
                ->description(__('Total'))
                ->format(function ($values) {
                   $return = $values['studentsTotal'];
                   if (is_numeric($values['enrolmentMin']) && $values['studentsTotal'] < $values['enrolmentMin']) {
                       $return .= Format::tag(__('Under Enrolled'), 'warning ml-2');
                   }
                   if (is_numeric($values['enrolmentMax']) && $values['studentsTotal'] > $values['enrolmentMax']) {
                       $return .= Format::tag(__('Over Enrolled'), 'error ml-2');
                   }
                   return $return;
                });

            // ACTIONS
            $table->addActionColumn()
                ->addParam('search', $criteria->getSearchText(true))
                ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                ->addParam('tawasulCourseID')
                ->addParam('tawasulCourseClassID')
                ->format(function ($class, $actions) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_class_edit.php');
                });

            echo $table->render($classes->toDataSet());
        }
    }
}
