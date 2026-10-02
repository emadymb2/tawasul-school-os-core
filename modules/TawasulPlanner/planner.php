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
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Planner\PlannerEntryGateway;
use Tos\Module\TawasulPlanner\Tables\LessonTable;
use TawasulOS\Domain\Students\StudentGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner.php') == false) {
    // Access denied
    $page->addError(__('Your request failed because you do not have access to this action.'));
} else {
    //Get action with highest precedence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        $plannerEntryGateway = $container->get(PlannerEntryGateway::class);

        //Set variables
        $today = date('Y-m-d');
        $settingGateway = $container->get(SettingGateway::class);
        $homeworkNameSingular = $settingGateway->getSettingByScope('Planner', 'homeworkNameSingular');
        $homeworkNamePlural = $settingGateway->getSettingByScope('Planner', 'homeworkNamePlural');
        $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');
        // Proceed!
        // Get viewBy, date and class variables
        $viewBy = $_GET['viewBy'] ?? '';
        $search = $_GET['search'] ?? '';
        $subView = $_GET['subView'] ?? '';

        if ($viewBy != 'date' and $viewBy != 'class') {
            $viewBy = 'date';
        }

        $tawasulCourseClassID = null;
        $date = null;
        $dateStamp = null;

        if ($viewBy == 'date') {
            if (isset($_GET['date'])) {
                $date = $_GET['date'] ?? '';
            }
            if (isset($_GET['dateHuman'])) {
                $date = Format::dateConvert($_GET['dateHuman']);
            }
            if ($date == '') {
                $date = date('Y-m-d');
            }
            [$dateYear, $dateMonth, $dateDay] = explode('-', $date);
            $dateStamp = mktime(0, 0, 0, $dateMonth, $dateDay, $dateYear);
        } elseif ($viewBy == 'class') {
            $class = null;
            if (isset($_GET['class'])) {
                $class = $_GET['class'] ?? '';
            }
            $tawasulCourseClassID = null;
            if (isset($_GET['tawasulCourseClassID'])) {
                $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
            }
        }

        [$todayYear, $todayMonth, $todayDay] = explode('-', $today);
        $todayStamp = mktime(12, 0, 0, $todayMonth, $todayDay, $todayYear);

        if ($viewBy == 'date' && isSchoolOpen($guid, date('Y-m-d', $dateStamp), $connection2) == false) {
            $page->addWarning(__('School is closed on the specified day.'));
        }

        if ($viewBy == 'class' && empty($tawasulCourseClassID)) {
            $page->addError(__('You have not specified one or more required parameters.'));
            return;
        }

        // My children's classes
        if ($highestAction == 'Lesson Planner_viewMyChildrensClasses') {

            $page->breadcrumbs->add(__('My Children\'s Classes'));
            $studentGateway = $container->get(StudentGateway::class);

            // Test data access field for permission
            $children = $studentGateway->selectActiveStudentsByFamilyAdult($tawasulSchoolYearID, $session->get('tawasulPersonID'))->fetchGroupedUnique();
            
            if (empty($children)) {
                echo $page->getBlankSlate();
            } elseif (count($children) == 1) {
                $tawasulPersonID = array_key_first($children);
            } else {
                $options = [];
                $count = 0;
                foreach($children as $child) {
                    $options[$child['tawasulPersonID']] = Format::name('', $child['preferredName'], $child['surname'], 'Student', true);
                    $tawasulPersonIDArray[$count] = $child['tawasulPersonID'];
                    ++$count;
                }

                $form = Form::create('action', $session->get('absoluteURL').'/index.php', 'get');
                $form->setTitle(__('Choose'));
                $form->setClass('noIntBorder w-full');

                $form->addHiddenValue('address', $session->get('address'));
                $form->addHiddenValue('q', '/modules/'.$session->get('module').'/planner.php');
                $form->addHiddenValue('tawasulCourseClassID', $tawasulCourseClassID ?? '');
                $form->addHiddenValue('viewBy', !empty($tawasulCourseClassID) ? 'class' : 'date');

                $row = $form->addRow();
                $row->addLabel('search', __('Student'));
                $row->addSelect('search')
                    ->fromArray(Format::nameListArray($children, 'Student'))
                    ->selected($search)
                    ->placeholder();

                $row = $form->addRow();
                    $row->addFooter();
                    $row->addSearchSubmit($session);

                echo $form->getOutput();

                $tawasulPersonID = $search;
            }
        }

        if (!empty($tawasulPersonID) && !empty($children) && !empty($children[$tawasulPersonID])) {
            $student = $container->get(StudentGateway::class)->selectActiveStudentByPerson($tawasulSchoolYearID, $tawasulPersonID)->fetch();

            if (empty($student)) {
                echo $page->getBlankSlate();
            } else {
                $table = $container->get(LessonTable::class)->create($tawasulSchoolYearID, $tawasulCourseClassID, $tawasulPersonID, $date, $viewBy);
                $table->setTitle(__('Lessons'));

                echo $table->getOutput();
            }
        }
        // My Classes
        elseif ($highestAction == 'Lesson Planner_viewMyClasses' or $highestAction == 'Lesson Planner_viewAllEditMyClasses' or $highestAction == 'Lesson Planner_viewEditAllClasses' or $highestAction == 'Lesson Planner_viewOnly') {
            $tawasulPersonID = $session->get('tawasulPersonID');

            $page->return->addReturns([
                'success1' =>  __('Bump was successful. It is possible that some lessons have not been moved (if there was no space for them), but a reasonable effort has been made.'),
            ]);

            if ($viewBy == 'date') {
                $page->breadcrumbs->add(__('Planner for {classDesc}', [
                    'classDesc' => Format::date($date),
                ]));
            } elseif ($viewBy == 'class') {
                $planner = $plannerEntryGateway->getPlannerClassDetails($tawasulCourseClassID);
                if (empty($planner)) {
                    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                    return;
                }

                $page->breadcrumbs->add(__('Planner for {classDesc}', [
                    'classDesc' => $planner['course'].'.'.$planner['class'],
                ]));
            }

            $viewBy = $subView == 'year' ? 'year' : $viewBy;
                    
            $table = $container->get(LessonTable::class)->create($tawasulSchoolYearID, $tawasulCourseClassID, $tawasulPersonID, $date, $viewBy);
            echo $table->getOutput(); 
        }
    }

    if (!empty($tawasulPersonID)) {
        // Print sidebar
        $session->set('sidebarExtra', sidebarExtra($guid, $connection2, $todayStamp, $tawasulPersonID, $dateStamp, $tawasulCourseClassID));
    }
}
