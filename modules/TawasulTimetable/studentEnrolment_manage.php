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
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\Timetable\CourseClassGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/studentEnrolment_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Student Enrolment'));

    $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');
    $tawasulPersonID = $session->get('tawasulPersonID');

    echo '<p>';
    echo __('This page allows departmental Coordinators and Assistant Coordinators to manage student enolment within their department.');
    echo '</p>';

    $courseGateway = $container->get(CourseGateway::class);
    $courseClassGateway = $container->get(CourseClassGateway::class);
    
    // QUERY
    $criteria = $courseGateway->newQueryCriteria()
        ->sortBy(['tawasulCourse.nameShort', 'tawasulCourse.name'])
        ->fromPOST();
        
    $courses = $courseGateway->queryCoursesByDepartmentStaff($criteria, $tawasulSchoolYearID, $tawasulPersonID)->toArray();

    if (empty($courses)) {
        echo $page->getBlankSlate();
        return;
    }

    foreach ($courses as $course) {
        $classes = $courseClassGateway->selectClassesByCourseID($course['tawasulCourseID'])->fetchAll();

        // DATA TABLE
        $table = DataTable::create('courseClassEnrolment');
        $table->setTitle($course['nameShort'].' ('.$course['name'].')');

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
            ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->addParam('tawasulCourseID')
            ->addParam('tawasulCourseClassID')
            ->format(function ($class, $actions) {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulTimetable/studentEnrolment_manage_edit.php');
            });

        echo $table->render($classes);
    }
}
