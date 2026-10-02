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

use TawasulOS\Tables\DataTable;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Domain\Planner\UnitGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\Departments\DepartmentGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$makeDepartmentsPublic = $container->get(SettingGateway::class)->getSettingByScope('Departments', 'makeDepartmentsPublic');
if (isActionAccessible($guid, $connection2, '/modules/TawasulDepartments/department_course.php') == false and $makeDepartmentsPublic != 'Y') {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $tawasulDepartmentID = $_GET['tawasulDepartmentID'] ?? '';
    $tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
    if ($tawasulDepartmentID == '' or $tawasulCourseID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $result = $container->get(DepartmentGateway::class)->getCourseByDepartment($tawasulDepartmentID, $tawasulCourseID);
          
        if (empty($result)) {
            $page->addError(__('The specified record does not exist.'));
        } else {
            $row = $result;

            //Get role within learning area
            $role = null;
            if ($session->has('username')) {
                $role = getRole($session->get('tawasulPersonID'), $tawasulDepartmentID, $connection2);
            }

            $extra = '';
            if (($role == 'Coordinator' or $role == 'Assistant Coordinator' or $role == 'Teacher (Curriculum)' or $role == 'Teacher') and $row['tawasulSchoolYearID'] != $session->get('tawasulSchoolYearID')) {
                $extra = ' '.$row['year'];
            }

            $urlParams = ['tawasulDepartmentID' => $tawasulDepartmentID];

            $page->breadcrumbs
                ->add(__('Departments'), $session->has('username') ? 'departments.php' : '/modules/TawasulDepartments/departments.php')
                ->add($row['department'], $session->has('username') ? 'department.php' : '/modules/TawasulDepartments/department.php', $urlParams)
                ->add($row['name'].$extra);

            //Print overview
            if ($row['description'] != '' or $role == 'Coordinator' or $role == 'Assistant Coordinator' or $role == 'Teacher (Curriculum)') {
                echo '<h2>';
                echo __('Overview');
                if ($role == 'Coordinator' or $role == 'Assistant Coordinator' or $role == 'Teacher (Curriculum)') {
                    echo "<a href='".$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/department_course_edit.php&tawasulCourseID=$tawasulCourseID&tawasulDepartmentID=$tawasulDepartmentID'><img style='margin-left: 5px' title='".__('Edit')."' src='./themes/".$session->get('tawasulThemeName')."/img/config.png'/></a> ";
                }
                echo '</h2>';
                echo '<p>';
                echo $row['description'];
                echo '</p>';
            }

            // Custom fields
            $table = DataTable::createDetails('fields');
            $container->get(CustomFieldHandler::class)->addCustomFieldsToTable($table, 'Course', [], $row['fields']);
            echo $table->render([$row]);

            //Print Units
            echo '<h2>';
            echo __('Units');
            echo '</h2>';

            $resultUnit = $container->get(UnitGateway::class)->selectActiveUnitsByCourse($tawasulCourseID);

            while ($rowUnit = $resultUnit->fetch()) {
                echo '<h4>';
                echo $rowUnit['name'];
                echo '</h4>';
                echo '<p>';
                echo $rowUnit['description'];
                if ($rowUnit['attachment'] != '') {
                    echo "<br/><br/><a href='".$session->get('absoluteURL').'/'.$rowUnit['attachment']."'>".__('Download Unit Outline').'</a></li>';
                }
                echo '</p>';
            }

            //Print sidebar
            $sidebarExtra = '';

            if (isActionAccessible($guid, $connection2, '/modules/TawasulDepartments/department_course_class.php')) {
                //Print class list
                
                $resultCourse = $container->get(CourseGateway::class)->selectClassesByCourse($tawasulCourseID);

                if ($resultCourse->rowCount() > 0) {
                    $sidebarExtra .= '<div class="column-no-break">';
                    $sidebarExtra .= '<h2>';
                    $sidebarExtra .= __('Class List');
                    $sidebarExtra .= '</h2>';

                    $sidebarExtra .= '<ul>';
                    while ($rowCourse = $resultCourse->fetch()) {
                        $sidebarExtra .= "<li><a href='".$session->get('absoluteURL')."/index.php?q=/modules/TawasulDepartments/department_course_class.php&tawasulDepartmentID=$tawasulDepartmentID&tawasulCourseID=$tawasulCourseID&tawasulCourseClassID=".$rowCourse['tawasulCourseClassID']."'>".$rowCourse['course'].'.'.$rowCourse['class'].'</a></li>';
                    }
                    $sidebarExtra .= '</ul>';
                    $sidebarExtra .= '</div>';

                    $session->set('sidebarExtra', $sidebarExtra);
                }
            }
        }
    }
}
