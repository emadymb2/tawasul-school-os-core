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
use TawasulOS\Tables\View\GridView;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\Departments\DepartmentGateway;
use TawasulOS\Domain\Departments\DepartmentStaffGateway;
use TawasulOS\Domain\Departments\DepartmentResourceGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$makeDepartmentsPublic = $container->get(SettingGateway::class)->getSettingByScope('Departments', 'makeDepartmentsPublic');
if (isActionAccessible($guid, $connection2, '/modules/TawasulDepartments/department.php') == false and $makeDepartmentsPublic != 'Y') {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $tawasulDepartmentID = $_GET['tawasulDepartmentID'] ?? '';
    if ($tawasulDepartmentID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $result = $container->get(DepartmentGateway::class)->selectBy(['tawasulDepartmentID' => $tawasulDepartmentID]);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record does not exist.'));
        } else {
            $row = $result->fetch();

            //Get role within learning area
            $role = null;
            if ($session->has('username')) {
                $role = getRole($session->get('tawasulPersonID'), $tawasulDepartmentID, $connection2);
            }

            $urlParams = ['tawasulDepartmentID' => $tawasulDepartmentID];

            $page->breadcrumbs
                    ->add(__('Departments'), $session->has('username') ? 'departments.php' : '/modules/TawasulDepartments/departments.php')
                    ->add($row['name'], $session->has('username') ? 'departments.php' : '/modules/TawasulDepartments/departments.php', $urlParams);

            //Print overview
            if ($row['blurb'] != '' or $role == 'Coordinator' or $role == 'Assistant Coordinator' or $role == 'Teacher (Curriculum)' or $role == 'Director' or $role == 'Manager') {
                echo '<h2>';
                echo __('Overview');
                if ($role == 'Coordinator' or $role == 'Assistant Coordinator' or $role == 'Teacher (Curriculum)' or $role == 'Director' or $role == 'Manager') {
                    echo "<a href='".$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/department_edit.php&tawasulDepartmentID=$tawasulDepartmentID'>".icon('solid', 'edit', 'size-6 text-gray-600')."</a> ";
                }
                echo '</h2>';
                echo '<p>';
                echo $row['blurb'];
                echo '</p>';
            }

            // Custom fields
            $table = DataTable::createDetails('fields');
            $container->get(CustomFieldHandler::class)->addCustomFieldsToTable($table, 'Department', [], $row['fields']);
            echo $table->render([$row]);

            //Print staff
            $result = $container->get(DepartmentStaffGateway::class)->seletStaffListByDepartment($tawasulDepartmentID);

            $staff = $result->toDataSet();

            // Data Table
            $gridRenderer = new GridView($container->get('twig'));
            $table = $container->get(DataTable::class)->setRenderer($gridRenderer);
            $table->setTitle(__('Staff'));
            $table->addMetaData('gridClass', 'rounded-sm bg-blue-50 border py-2');
            $table->addMetaData('gridItemClass', 'w-1/2 sm:w-1/4 md:w-1/5 my-2 text-center');

            $canViewProfile = isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_view_details.php');
            $table->addColumn('image_240')
                ->format(function ($person) use ($canViewProfile) {
                    $class = !empty($person['roleOrder'])? 'bg-blue-300' : '';
                    $userPhoto = Format::userPhoto($person['image_240'], 'sm', $class);
                    $title = !empty($person['roleOrder'])? __('Department {role}', ['role' => __($person['role'])]): '';
                    $url = './index.php?q=/modules/TawasulStaff/staff_view_details.php&tawasulPersonID='.$person['tawasulPersonID'];

                    return $canViewProfile
                        ? Format::link($url, $userPhoto, ['title' => $title])
                        : $userPhoto;
                });

            $table->addColumn('name')
                ->setClass('text-xs font-bold mt-1')
                ->format(function ($person) use ($canViewProfile) {
                    $name = Format::name($person['title'], $person['preferredName'], $person['surname'], 'Staff');
                    $title = !empty($person['roleOrder'])? __('Department {role}', ['role' => __($person['role'])]): '';
                    $url = './index.php?q=/modules/TawasulStaff/staff_view_details.php&tawasulPersonID='.$person['tawasulPersonID'];

                    return $canViewProfile
                        ? Format::link($url, $name, ['title' => $title])
                        : $name;
                });

            $table->addColumn('jobTitle')
                ->setClass('text-xs text-gray-600 italic leading-snug')
                ->format(function ($person) {
                    $jobTitle = !empty($person['jobTitle']) ? $person['jobTitle'] : __($person['role']);
                    return !empty($person['jobTitle']) ? $person['jobTitle'] : __($person['role']);
                });

            echo $table->render($staff);


            //Print sidebar
            $sidebarExtra = '';

            //Print subject list
            if ($row['subjectListing'] != '') {
                $sidebarExtra .= '<div class="column-no-break">';
                $sidebarExtra .= '<h4>';
                $sidebarExtra .= __('Subject List');
                $sidebarExtra .= '</h4>';

                $sidebarExtra .= '<ul>';
                $subjects = explode(',', $row['subjectListing']);
                for ($i = 0;$i < count($subjects);++$i) {
                    $sidebarExtra .= '<li>'.$subjects[$i].'</li>';
                }
                $sidebarExtra .= '</ul>';
                $sidebarExtra .= '</div>';
            }

            //Print current course list

                
                $resultCourse = $container->get(CourseGateway::class)->selectCurrentCoursesByDepartment($tawasulDepartmentID);

            if ($resultCourse->rowCount() > 0) {
                $sidebarExtra .= '<div class="column-no-break">';
                if ($role == 'Coordinator' or $role == 'Assistant Coordinator' or $role == 'Teacher (Curriculum)') {
                    $sidebarExtra .= '<h4>';
                    $sidebarExtra .= __('Current Courses');
                    $sidebarExtra .= '</h4>';
                } else {
                    $sidebarExtra .= '<h4>';
                    $sidebarExtra .= __('Course List');
                    $sidebarExtra .= '</h4>';
                }

                $sidebarExtra .= '<ul>';
                while ($rowCourse = $resultCourse->fetch()) {
                    $sidebarExtra .= "<li><a href='".$session->get('absoluteURL')."/index.php?q=/modules/TawasulDepartments/department_course.php&tawasulDepartmentID=$tawasulDepartmentID&tawasulCourseID=".$rowCourse['tawasulCourseID']."'>".$rowCourse['nameShort']."</a> <span style='font-size: 85%; font-style: italic'>".$rowCourse['name'].'</span></li>';
                }
                $sidebarExtra .= '</ul>';
                $sidebarExtra .= '</div>';
            }

            //Print other courses
            if ($role == 'Coordinator' or $role == 'Assistant Coordinator' or $role == 'Teacher (Curriculum)' or $role == 'Teacher') {
                 
                $result = $container->get(CourseGateway::class)->selectPastCoursesByDepartment($tawasulDepartmentID, $session->get('tawasulSchoolYearID'));

                $courses = ($result->rowCount() > 0)? $result->fetchAll() : array();
                $courses = array_reduce($courses, function($carry, $item) {
                    $carry[$item['year']][$item['value']] = $item['name'];
                    return $carry;
                }, array());

                if (!empty($courses)) {
                    $form = Form::create('courseSelect', $session->get('absoluteURL').'/index.php', 'get');
                    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/department_course.php');
                    $form->addHiddenValue('tawasulDepartmentID', $tawasulDepartmentID);

                    $row = $form->addRow()->addClass('items-center');
                        $row->addSelect('tawasulCourseID')
                            ->fromArray($courses)
                            ->placeholder()
                            ->setClass('w-32 float-none');
                    $row->addSubmit(__('Go'));

                    $sidebarExtra .= '<div class="column-no-break">';
                    $sidebarExtra .= '<h4>';
                    $sidebarExtra .= __('Non-Current Courses');
                    $sidebarExtra .= '</h4>';

                    $sidebarExtra .= $form->getOutput();
                    $sidebarExtra .= '</div>';
                }
            }

            //Print useful reading
            $resultReading = $container->get(DepartmentResourceGateway::class)->selectBy(['tawasulDepartmentID' => $tawasulDepartmentID]);
    
            if ($resultReading->rowCount() > 0 or $role == 'Coordinator' or $role == 'Assistant Coordinator' or $role == 'Teacher (Curriculum)' or $role == 'Director' or $role == 'Manager') {
                $sidebarExtra .= '<div class="column-no-break">';
                $sidebarExtra .= '<h4>';
                $sidebarExtra .= __('Useful Reading');
                if ($role == 'Coordinator' or $role == 'Assistant Coordinator' or $role == 'Teacher (Curriculum)' or $role == 'Director' or $role == 'Manager') {
                    $sidebarExtra .= "<a href='".$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/department_edit.php&tawasulDepartmentID=$tawasulDepartmentID'>".icon('solid', 'edit', 'size-6 text-gray-600')."</a> ";
                }
                $sidebarExtra .= '</h4>';

                $sidebarExtra .= '<ul>';
                while ($rowReading = $resultReading->fetch()) {
                    if ($rowReading['type'] == 'Link') {
                        $sidebarExtra .= "<li><a target='_blank' href='".$rowReading['url']."'>".$rowReading['name'].'</a></li>';
                    } else {
                        $sidebarExtra .= "<li><a href='".$session->get('absoluteURL').'/'.$rowReading['url']."'>".$rowReading['name'].'</a></li>';
                    }
                }
                $sidebarExtra .= '</ul>';
                $sidebarExtra .= '</div>';
            }

            $session->set('sidebarExtra', $sidebarExtra);
        }
    }
}
