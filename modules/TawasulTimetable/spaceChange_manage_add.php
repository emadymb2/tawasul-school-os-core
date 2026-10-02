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

use TawasulOS\Domain\Timetable\CourseEnrolmentGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;

use TawasulOS\Forms\DatabaseFormFactory;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/spaceChange_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Proceed!
        $page->breadcrumbs
            ->add(__('Manage Facility Changes'), 'spaceChange_manage.php')
            ->add(__('Add Facility Change'));

        $step = null;
        if (isset($_GET['step'])) {
            $step = $_GET['step'] ?? '';
        }
        if ($step != 1 and $step != 2) {
            $step = 1;
        }

        //Step 1
        if ($step == 1) {
            $form = Form::create('spaceChangeStep1', $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/spaceChange_manage_add.php&step=2');
            $form->setTitle(__('Step 1 - Choose Class'));

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('source', isset($_REQUEST['source'])? $_REQUEST['source'] : '');

            $classes = array();

            // My Classes
            $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'));
            $sql = "SELECT tawasulCourseClass.tawasulCourseClassID as value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS name FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID ORDER BY name";
            $results = $pdo->executeQuery($data, $sql);
            if ($results->rowCount() > 0) {
                $classes['--'.__('My Classes').'--'] = $results->fetchAll(\PDO::FETCH_KEY_PAIR);
            }

            // All Classes, if we have access
            if ($highestAction == 'Manage Facility Changes_allClasses') {
                $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                $sql = "SELECT tawasulCourseClass.tawasulCourseClassID as value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS name FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY name";
                $results = $pdo->executeQuery($data, $sql);
                if ($results->rowCount() > 0) {
                    $classes['--'.__('All Classes').'--'] = $results->fetchAll(\PDO::FETCH_KEY_PAIR);
                }
            }

            // Classed by Department, if we have access
            if ($highestAction == 'Manage Facility Changes_myDepartment') {
                $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'));
                $sql = "SELECT tawasulCourseClass.tawasulCourseClassID as value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS name FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND (tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID AND role='Coordinator') ORDER BY name";
                $results = $pdo->executeQuery($data, $sql);
                if ($results->rowCount() > 0) {
                    $classes['--'.__('My Department').'--'] = $results->fetchAll(\PDO::FETCH_KEY_PAIR);
                }
            }

            $row = $form->addRow();
                $row->addLabel('tawasulCourseClassID', __('Class'));
                $row->addSelect('tawasulCourseClassID')->fromArray($classes)->required()->placeholder();

            $row = $form->addRow();
                $row->addSubmit(__('Proceed'));

            echo $form->getOutput();

        } elseif ($step == 2) {
            echo '<h2>';
            echo __('Step 2 - Choose Options');
            echo '</h2>';
            echo '<p>';
            echo __('When choosing a facility, remember that they are not mutually exclusive: you can change two classes into one facility, change one class to join another class in their normal room, or assign no facility at all. The facilities listed below are not necessarily free at the requested time: please use the View Available Facilities report to check availability.');
            echo '</p>';

            $tawasulCourseClassID = $_REQUEST['tawasulCourseClassID'] ?? null;
            $tawasulTTDayRowClassID = $_REQUEST['tawasulTTDayRowClassID'] ?? null;

            try {
                if ($highestAction == 'Manage Facility Changes_allClasses') {
                    $dataSelect = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sqlSelect = 'SELECT tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                } else if ($highestAction == 'Manage Facility Changes_myDepartment') {
                    $dataSelect = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulSchoolYearID2' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID2' => $session->get('tawasulPersonID'), 'tawasulCourseClassID2' => $tawasulCourseClassID);
                    $sqlSelect = '(SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)
                    UNION
                    (SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID2 AND (tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID2 AND role=\'Coordinator\') AND tawasulCourseClassID=:tawasulCourseClassID2)';
                } else {
                    $dataSelect = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sqlSelect = 'SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                }
                $resultSelect = $connection2->prepare($sqlSelect);
                $resultSelect->execute($dataSelect);
            } catch (PDOException $e) {
                $page->addError(__('Your request failed due to a database error.'));
            }

            if ($resultSelect->rowCount() != 1) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                $rowSelect = $resultSelect->fetch();

                $studentCount = $container->get(CourseEnrolmentGateway::class)->getClassStudentCount($tawasulCourseClassID);

                $form = Form::create('spaceChangeStep2', $session->get('absoluteURL').'/modules/'.$session->get('module').'/spaceChange_manage_addProcess.php');
                $form->setFactory(DatabaseFormFactory::create($pdo));

                $form->addHiddenValue('address', $session->get('address'));
                $form->addHiddenValue('tawasulCourseClassID', $tawasulCourseClassID);
                $form->addHiddenValue('source', isset($_REQUEST['source'])? $_REQUEST['source'] : '');

                $row = $form->addRow();
                    $row->addLabel('class', __('Class'));
                    $row->addTextField('class')->readonly()->setValue($rowSelect['course'].'.'.$rowSelect['class']);

                $row = $form->addRow();
                    $row->addLabel('students', __('Students'));
                    $row->addTextField('students')->readonly()->setValue($studentCount);

                $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'date1' => date('Y-m-d'), 'date2' => date('Y-m-d'), 'time' => date('H:i:s'));
                $sql = 'SELECT tawasulTTDayRowClass.tawasulTTDayRowClassID, tawasulTTColumnRow.name AS period, timeStart, timeEnd, tawasulTTDay.name AS day, tawasulTTDayDate.date, tawasulTTSpaceChangeID FROM tawasulTTDayRowClass JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID) JOIN tawasulTTDay ON (tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) LEFT JOIN tawasulTTSpaceChange ON (tawasulTTSpaceChange.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTSpaceChange.date=tawasulTTDayDate.date) WHERE tawasulTTDayRowClass.tawasulCourseClassID=:tawasulCourseClassID AND (tawasulTTDayDate.date>:date1 OR (tawasulTTDayDate.date=:date2 AND timeEnd>:time)) ORDER BY tawasulTTDayDate.date, timeStart';
                $results = $pdo->executeQuery($data, $sql);
                $classSlots = array_reduce($results->fetchAll(), function($array, $item) use ($guid, $connection2) {
                    if (!isSchoolOpen($guid, $item['date'], $connection2)) return $array;

                    $key = $item['tawasulTTDayRowClassID'].'-'.$item['date'];
                    $array[$key] = Format::date($item['date']).' ('.$item['day'].' - '.$item['period'].')';
                    return $array;
                }, array());

                $row = $form->addRow();
                    $row->addLabel('tawasulTTDayRowClassID', __('Upcoming Class Slots'));
                    $row->addSelect('tawasulTTDayRowClassID')->fromArray($classSlots)->required()->placeholder()->selected($tawasulTTDayRowClassID);

                $row = $form->addRow();
                    $row->addLabel('tawasulSpaceID', __('Facility'));
                    $col = $row->addColumn()->addClass('flex-col');
                    $col->addSelectSpace('tawasulSpaceID')->addClass('flex-1 w-full');
                    $col->addContent('<br/><div id="facilityStatus" class="w-full"></div>');

                $row = $form->addRow();
                    $row->addFooter();
                    $row->addSubmit();

                echo $form->getOutput();
            }
        }
    }
}
?>

<script>

$(document).ready(function() {
    $('#tawasulSpaceID').on('change', function() {
        $.ajax({
            url: './modules/TawasulTimetable/spaceChange_manage_addAjax.php',
            data: {
                tawasulTTDayRowClassID: $('#tawasulTTDayRowClassID').val(),    
                tawasulSpaceID: $('#tawasulSpaceID').val(),
            },
            type: 'POST',
            success: function(data) {
                $('#facilityStatus').html(data);
            }
        });
    });
}) ;

</script>
