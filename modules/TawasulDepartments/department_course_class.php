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

use TawasulOS\Domain\DataSet;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Timetable\CourseClassGateway;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Tables\Prefab\ClassGroupTable;
use TawasulOS\Tables\View\GridView;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$settingGateway = $container->get(SettingGateway::class);
$courseGateway = $container->get(CourseGateway::class);
$courseClassGateway = $container->get(CourseClassGateway::class);

$makeDepartmentsPublic = $settingGateway->getSettingByScope('Departments', 'makeDepartmentsPublic');
if (isActionAccessible($guid, $connection2, '/modules/TawasulDepartments/department_course_class.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
    $tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
    $tawasulDepartmentID = $_GET['tawasulDepartmentID'] ?? '';
    $currentDate = $_GET['currentDate'] ?? Format::date(date('Y-m-d'));

    if (empty($tawasulCourseClassID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        if (!empty($tawasulDepartmentID)) {
            $result = $courseGateway->getCourseClassDetails($tawasulCourseClassID);
        } else {
            $result = $courseGateway->getCourseClassInfoByID($tawasulCourseClassID);
        }

        $row = $result;

        if (empty($row)) {
            $page->addError(__('The specified record does not exist.'));
        } else {
            //Get role within learning area
            $role = null;
            if ($tawasulDepartmentID != '' and ($session->get('username'))) {
                $role = getRole($session->get('tawasulPersonID'), $tawasulDepartmentID, $connection2);
            }

            $extra = '';
            if (($role == 'Coordinator' or $role == 'Assistant Coordinator' or $role == 'Teacher (Curriculum)' or $role == 'Teacher') and $row['tawasulSchoolYearID'] != $session->get('tawasulSchoolYearID')) {
                $extra = ' '.$row['year'];
            }
            if ($tawasulDepartmentID != '') {
                $urlParams = ['tawasulDepartmentID' => $tawasulDepartmentID, 'tawasulCourseID' => $tawasulCourseID];
                $page->breadcrumbs
                    ->add($row['department'], 'department.php', $urlParams)
                    ->add($row['courseLong'].$extra, 'department_course.php', $urlParams)
                    ->add(Format::courseClassName($row['course'], $row['class']));
            } else {
                $page->breadcrumbs
                    ->add(__('Departments'), 'departments.php')
                    ->add(Format::courseClassName($row['course'], $row['class']));
            }

            // CHECK & STORE WHAT TO DISPLAY
            $menuItems = [];

            // Attendance
            if ($row['attendance'] == 'Y' && isActionAccessible($guid, $connection2, "/modules/TawasulAttendance/attendance_take_byCourseClass.php")) {
                $menuItems[] = [
                    'name' => __('Attendance'),
                    'url'  => './index.php?q=/modules/TawasulAttendance/attendance_take_byCourseClass.php&tawasulCourseClassID='.$tawasulCourseClassID.'&currentDate='.$currentDate,
                    'icon' => 'users',
                ];
            }
            // Planner
            if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner.php')) {
                $menuItems[] = [
                    'name' => __('Planner'),
                    'url'  => './index.php?q=/modules/TawasulPlanner/planner.php&tawasulCourseClassID='.$tawasulCourseClassID.'&viewBy=class',
                    'icon' => 'calendar',
                ];
            }
            // Markbook
            if (getHighestGroupedAction($guid, '/modules/TawasulMarkbook/markbook_view.php', $connection2) == 'View Markbook_allClassesAllData') {
                $menuItems[] = [
                    'name' => __('Markbook'),
                    'url'  => './index.php?q=/modules/TawasulMarkbook/markbook_view.php&tawasulCourseClassID='.$tawasulCourseClassID,
                    'icon' => 'markbook',
                ];
            }
            // Homework
            if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_deadlines.php')) {
                $homeworkNamePlural = $settingGateway->getSettingByScope('Planner', 'homeworkNamePlural');
                $menuItems[] = [
                    'name' => __($homeworkNamePlural),
                    'url'  => './index.php?q=/modules/TawasulPlanner/planner_deadlines.php&tawasulCourseClassIDFilter='.$tawasulCourseClassID,
                    'icon' => 'homework',
                ];
            }
            // Internal Assessment
            if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/internalAssessment_write.php')) {
                $menuItems[] = [
                    'name' => __('Internal Assessment'),
                    'url'  => './index.php?q=/modules/TawasulFormalAssessment/internalAssessment_write.php&tawasulCourseClassID='.$tawasulCourseClassID,
                    'icon' => 'internal-assessment',
                ];
            }
            
            // Menu Items Table
            if (!empty($menuItems)) {
                $gridRenderer = new GridView($container->get('twig'));
                $table = $container->get(DataTable::class)->setRenderer($gridRenderer);
                $table->setTitle($row['courseLong']." - ".$row['classLong']);
                $table->setDescription(Format::courseClassName($row['course'], $row['class']));

                $table->addMetaData('gridClass', 'rounded-md bg-gray-100 border py-4 gap-6 sm:flex-nowrap justify-around');
                $table->addMetaData('gridItemClass', 'w-24 sm:flex-1 text-center text-gray-500 hover:text-gray-700');
                $table->addMetaData('hidePagination', true);

                $table->addColumn('icon')
                    ->format(function ($menu) {
                        return Format::link($menu['url'], icon('solid', $menu['icon'], 'size-8 sm:size-12'), ['class' => 'no-underline text-inherit']);
                    });

                $table->addColumn('name')
                    ->setClass('font-bold text-xs')
                    ->format(function ($menu) {
                        return Format::link($menu['url'], $menu['name'], ['class' => 'no-underline text-gray-700']);
                    });

                echo $table->render(new DataSet($menuItems));
            }

            // Custom fields
            $table = DataTable::createDetails('fields');
            $container->get(CustomFieldHandler::class)->addCustomFieldsToTable($table, 'Class', [], $row['fields']);
            echo $table->render([$row]);

            // Participants
            $table = $container->get(ClassGroupTable::class);
            $table->build($session->get('tawasulSchoolYearID'), $tawasulCourseClassID);

            echo $table->getOutput();

            //Print sidebar
            if ($session->get('username')) {
                $sidebarExtra = '';

                //Print related class list
                $resultCourse = $courseClassGateway->selectClassesByCourseID($row['tawasulCourseID']);

                if ($resultCourse->rowCount() > 0) {
                    $sidebarExtra .= '<div class="column-no-break">';
                    $sidebarExtra .= '<h2>';
                    $sidebarExtra .= __('Related Classes');
                    $sidebarExtra .= '</h2>';

                    $sidebarExtra .= '<ul>';
                    while ($rowCourse = $resultCourse->fetch()) {
                        $sidebarExtra .= "<li><a href='".$session->get('absoluteURL')."/index.php?q=/modules/TawasulDepartments/department_course_class.php&tawasulDepartmentID=$tawasulDepartmentID&tawasulCourseID=".$row['tawasulCourseID'].'&tawasulCourseClassID='.$rowCourse['tawasulCourseClassID']."'>".$rowCourse['course'].'.'.$rowCourse['class'].'</a></li>';
                    }
                    $sidebarExtra .= '</ul>';
                    $sidebarExtra .= '</div>';
                }

                //Print list of all classes
                $sidebarExtra .= '<div class="column-no-break">';

                $form = Form::create('classSelect', $session->get('absoluteURL').'/index.php', 'get');
                $form->setTitle(__('Current Classes'));
                $form->setClass('smallIntBorder w-full');

                $form->addHiddenValue('q', '/modules/'.$session->get('module').'/department_course_class.php');

                $resultClasses = $courseGateway->selectCoursesAndClassesBySchoolYear($session->get('tawasulSchoolYearID'));

                $row = $form->addRow();
                    $row->addSelect('tawasulCourseClassID')
                        ->fromResults($resultClasses)
                        ->selected($tawasulCourseClassID)
                        ->placeholder()
                        ->setClass('w-full');
                    $row->addSubmit(__('Go'));

                $sidebarExtra .= $form->getOutput();
                $sidebarExtra .= '</div>';

                $session->set('sidebarExtra', $session->get('sidebarExtra'). $sidebarExtra);
            }
        }
    }
}
