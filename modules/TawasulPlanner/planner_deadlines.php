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
use Tos\Module\TawasulPlanner\Tables\HomeworkTable;
use TawasulOS\Domain\Students\StudentGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$style = '';

$highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_deadlines.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Set variables
    $today = date('Y-m-d');
    $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');

    $plannerGateway = $container->get(PlannerEntryGateway::class);
    $homeworkNamePlural = $container->get(SettingGateway::class)->getSettingByScope('Planner', 'homeworkNamePlural');

    //Proceed!
    //Get viewBy, date and class variables
    $params = [];
    $viewBy = null;
    if (isset($_GET['viewBy'])) {
        $viewBy = $_GET['viewBy'] ?? '';
    }
    $subView = null;
    if (isset($_GET['subView'])) {
        $subView = $_GET['subView'] ?? '';
    }
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
        $params += [
            'viewBy' => 'date',
            'date' => $date,
        ];
    } elseif ($viewBy == 'class') {
        $class = null;
        if (isset($_GET['class'])) {
            $class = $_GET['class'] ?? '';
        }
        $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
        $params += [
            'viewBy' => 'class',
            'date' => $class,
            'tawasulCourseClassID' => $tawasulCourseClassID,
        ];
    }
    [$todayYear, $todayMonth, $todayDay] = explode('-', $today);
    $todayStamp = mktime(12, 0, 0, $todayMonth, $todayDay, $todayYear);
    $show = null;
    if (isset($_GET['show'])) {
        $show = $_GET['show'] ?? '';
    }

    if (isset($_GET['tawasulCourseClassIDFilter'])) {
        $tawasulCourseClassID = $_GET['tawasulCourseClassIDFilter'] ?? '';
        $params['tawasulCourseClassID'] = $tawasulCourseClassID;
    }
    $search = $_GET['search'] ?? '';
    
    // My children's classes
    if ($highestAction == 'Lesson Planner_viewMyChildrensClasses') {

        $page->breadcrumbs
            ->add(__('My Children\'s Classes'), 'planner.php')
            ->add(__('{homeworkName} + Due Dates', ['homeworkName' => __($homeworkNamePlural)]));


        // Test data access field for permission
        $studentGateway = $container->get(StudentGateway::class);
        $children = $studentGateway->selectActiveStudentsByFamilyAdult($tawasulSchoolYearID, $session->get('tawasulPersonID'))->fetchGroupedUnique();

        if (empty($children)) {
            echo $page->getBlankSlate();
        } elseif (count($children) == 1) {
            $tawasulPersonID = array_key_first($children);
        } else {
            //Get child list    
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
            $form->addHiddenValue('q', '/modules/'.$session->get('module').'/planner_deadlines.php');

            if (isset($tawasulCourseClassID) && $tawasulCourseClassID != '') {
                $form->addHiddenValue('tawasulCourseClassID', $tawasulCourseClassID);
                $form->addHiddenValue('viewBy', 'class');
            }
            else {
              $form->addHiddenValue('viewBy', 'date');
            }

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

        if (!empty($tawasulPersonID) && !empty($children) && !empty($children[$tawasulPersonID])) { 
            $proceed = true;
            if ($viewBy == 'class') {
                if ($tawasulCourseClassID == '') {
                    $proceed = false;
                } else {
                    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID, 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = "SELECT tawasulCourse.tawasulCourseID, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClassPerson JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID AND role='Teacher' ORDER BY course, class";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);

                    if ($result->rowCount() != 1) {
                        $proceed = false;
                    }
                }
            }

            if ($proceed == false) {
                echo Format::alert(__('Your request failed because you do not have access to this action.'));
            } else {
                // DEADLINES
                $deadlines = $plannerGateway->selectUpcomingHomeworkByStudent($session->get('tawasulSchoolYearID'), $tawasulPersonID, 'viewableParents')->fetchAll();

                echo $page->fetchFromTemplate('ui/upcomingDeadlines.twig.html', [
                    'tawasulPersonID' => $tawasulPersonID,
                    'deadlines' => $deadlines,
                    'heading' => 'h3',
                    'viewBy' => $viewBy,
                ]);

                // HOMEWORK TABLE
                $table = $container->get(HomeworkTable::class)->create($session->get('tawasulSchoolYearID'), $tawasulPersonID, 'Parent');
                $table->setTitle($homeworkNamePlural);

                echo $table->getOutput();
            }
        }
    } elseif ($highestAction == 'Lesson Planner_viewMyClasses' or $highestAction == 'Lesson Planner_viewAllEditMyClasses' or $highestAction == 'Lesson Planner_viewEditAllClasses' or $highestAction == 'Lesson Planner_viewOnly') {
        //Get current role category
        $category = $session->get('tawasulRoleIDCurrentCategory');

        $page->breadcrumbs
            ->add(__('Planner'), 'planner.php', $params)
            ->add(__('{homeworkName} + Due Dates', ['homeworkName' => __($homeworkNamePlural)]));

        //Proceed!
        $proceed = true;
        if ($viewBy == 'class') {
            if ($tawasulCourseClassID == '') {
                $proceed = false;
            } else {
                try {
                    if ($highestAction == 'Lesson Planner_viewEditAllClasses') {
                        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                        $sql = 'SELECT tawasulCourse.tawasulCourseID, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                    } else {
                        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                        $sql = "SELECT tawasulCourse.tawasulCourseID, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClassPerson JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID AND role='Teacher' ORDER BY course, class";
                    }
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                }
                if ($result->rowCount() != 1) {
                    $proceed = false;
                }
            }
        }

        if ($proceed == false) {
            $page->addError(__('Your request failed because you do not have access to this action.'));
        } else {
            // DEADLINES
            if ($highestAction == 'Lesson Planner_viewEditAllClasses' and $show == 'all') {
                $deadlines = $plannerGateway->selectAllUpcomingHomework($session->get('tawasulSchoolYearID'))->fetchAll();
            } else {
                $tawasulPersonID = $session->get('tawasulPersonID');
                $deadlines = $plannerGateway->selectUpcomingHomeworkByStudent($session->get('tawasulSchoolYearID'), $tawasulPersonID)->fetchAll();
            }

            echo $page->fetchFromTemplate('ui/upcomingDeadlines.twig.html', [
                'tawasulPersonID' => $tawasulPersonID,
                'deadlines' => $deadlines,
                'heading' => 'h3',
                'viewBy' => $viewBy,
            ]);

            // HOMEWORK TABLE
            $table = $container->get(HomeworkTable::class)->create($session->get('tawasulSchoolYearID'), $tawasulPersonID, $category, $tawasulCourseClassID);
            $table->setTitle($homeworkNamePlural);

            echo $table->getOutput();
        }
    }

    // Print sidebar
    $tawasulPersonID = empty($tawasulPersonID) ? $session->get('tawasulPersonID') : $tawasulPersonID ;
    $session->set('sidebarExtra', sidebarExtra($guid, $connection2, $todayStamp, $tawasulPersonID, $dateStamp, $tawasulCourseClassID));
}
