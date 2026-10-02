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
use TawasulOS\Domain\Timetable\CourseGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

// common variables
$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
$tawasulUnitID = $_GET['tawasulUnitID'] ?? '';

$page->breadcrumbs
    ->add(__('Unit Planner'), 'units.php', [
        'tawasulSchoolYearID' => $tawasulSchoolYearID,
        'tawasulCourseID' => $tawasulCourseID,
    ])
    ->add(__('Duplicate Unit'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_duplicate.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Check if courseschool year specified
        if ($tawasulCourseID == '' or $tawasulSchoolYearID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            $courseGateway = $container->get(CourseGateway::class);

            // Check access to specified course
            if ($highestAction == 'Unit Planner_all') {
                $result = $courseGateway->selectCourseDetailsByCourse($tawasulCourseID);
            } elseif ($highestAction == 'Unit Planner_learningAreas') {
                $result = $courseGateway->selectCourseDetailsByCourseAndPerson($tawasulCourseID, $session->get('tawasulPersonID'));
            }

            if ($result->rowCount() != 1) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                $values = $result->fetch();
                $courseName = $values['name'];
                $yearName = $values['schoolYear'];

                //Check if unit specified
                if ($tawasulUnitID == '') {
                    $page->addError(__('You have not specified one or more required parameters.'));
                } else {
                    if ($tawasulUnitID == '') {
                        $page->addError(__('You have not specified one or more required parameters.'));
                    } else {

                            $data = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseID' => $tawasulCourseID);
                            $sql = "SELECT tawasulCourse.nameShort AS courseName, tawasulSchoolYearID, tawasulUnit.* FROM tawasulUnit JOIN tawasulCourse ON (tawasulUnit.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulUnitID=:tawasulUnitID AND tawasulUnit.tawasulCourseID=:tawasulCourseID";
                            $result = $connection2->prepare($sql);
                            $result->execute($data);

                        if ($result->rowCount() != 1) {
                            $page->addError(__('The specified record cannot be found.'));
                        } else {
                            //Let's go!
                            $values = $result->fetch();

                            $step = null;
                            if (isset($_GET['step'])) {
                                $step = $_GET['step'] ?? '';
                            }
                            if ($step != 1 and $step != 2 and $step != 3) {
                                $step = 1;
                            }

                            //Step 1
                            if ($step == 1) {
                                echo '<h2>';
                                echo __('Step 1');
                                echo '</h2>';

                                $form = Form::create('action', $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/units_duplicate.php&step=2&tawasulUnitID=$tawasulUnitID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseID");
                                $form->setFactory(DatabaseFormFactory::create($pdo));

                                $form->addHiddenValue('address', $session->get('address'));

                                $form->addRow()->addHeading('Source', __('Source'));

                                $row = $form->addRow();
                                    $row->addLabel('yearName', __('School Year'));
                                    $row->addTextField('yearName')->readonly()->setValue($yearName);

                                $row = $form->addRow();
                                    $row->addLabel('courseName', __('Course'));
                                    $row->addTextField('courseName')->readonly()->setValue($values['courseName']);

                                $row = $form->addRow();
                                    $row->addLabel('unitName', __('Unit'));
                                    $row->addTextField('unitName')->readonly()->setValue($values['name']);

                                $form->addRow()->addHeading('Target', __('Target'));

                                $row = $form->addRow();
                                    $row->addLabel('tawasulSchoolYearIDCopyTo', __('School Year'));
                                    $row->addSelectSchoolYear('tawasulSchoolYearIDCopyTo', 'Active')->required();

                                if ($highestAction == 'Unit Planner_all') {
                                    $data = array();
                                    $sql = 'SELECT tawasulCourse.tawasulSchoolYearID as chainedTo, tawasulCourseID AS value, tawasulCourse.nameShort AS name FROM tawasulCourse JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) ORDER BY nameShort';
                                } elseif ($highestAction == 'Unit Planner_learningAreas') {
                                    $data = array('tawasulPersonID' => $session->get('tawasulPersonID'));
                                    $sql = "SELECT tawasulCourse.tawasulSchoolYearID as chainedTo, tawasulCourseID AS value, tawasulCourse.nameShort AS name FROM tawasulCourse JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID AND (role='Coordinator' OR role='Assistant Coordinator' OR role='Teacher (Curriculum)') ORDER BY tawasulCourse.nameShort";
                                }
                                $row = $form->addRow();
                                    $row->addLabel('tawasulCourseIDTarget', __('Course'));
                                    $row->addSelect('tawasulCourseIDTarget')->fromQueryChained($pdo, $sql, $data, 'tawasulSchoolYearIDCopyTo')->required()->placeholder();

                                $row = $form->addRow();
                                    $row->addLabel('unitName', __('Unit'));
                                    $row->addTextField('unitName')->readonly()->setValue($values['name']);

                                $row = $form->addRow();
                                    $row->addFooter();
                                    $row->addSubmit();

                                echo $form->getOutput();

                            } elseif ($step == 2) {
                                echo '<h2>';
                                echo __('Step 2');
                                echo '</h2>';

                                $tawasulCourseIDTarget = $_POST['tawasulCourseIDTarget'] ?? '';

                                if ($tawasulCourseIDTarget == '') {
                                    $page->addError(__('You have not specified one or more required parameters.'));
                                } else {


                                        $dataSelect2 = array('tawasulCourseID' => $tawasulCourseIDTarget);
                                        $sqlSelect2 = 'SELECT tawasulCourse.name AS course, tawasulSchoolYear.name AS year FROM tawasulCourse JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) WHERE tawasulCourseID=:tawasulCourseID';
                                        $resultSelect2 = $connection2->prepare($sqlSelect2);
                                        $resultSelect2->execute($dataSelect2);
                                    if ($resultSelect2->rowCount() == 1) {
                                        $rowSelect2 = $resultSelect2->fetch();
                                        $access = true;
                                        $course = $rowSelect2['course'];
                                        $year = $rowSelect2['year'];
                                    }

                                    $form = Form::create('action', $session->get('absoluteURL') . "/modules/" . $session->get('module') ."/units_duplicateProcess.php?tawasulUnitID=$tawasulUnitID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseID&address=".$_GET['q']);
                                    $form->setFactory(DatabaseFormFactory::create($pdo));

                                    $form->addHiddenValue('address', $session->get('address'));
                                    $form->addHiddenValue('tawasulCourseIDTarget', $tawasulCourseIDTarget);

                                    $row = $form->addRow();
                                        $row->addLabel('copyLessons', __('Copy Lessons?'));
                                        $row->addYesNoRadio('copyLessons')->required()->setClass('copyLessons right');

                                    $form->toggleVisibilityByClass('targetClass')->onRadio('copyLessons')->when('Y');

                                    $form->addRow()->addHeading('Source', __('Source'));

                                    $row = $form->addRow();
                                        $row->addLabel('yearName', __('School Year'));
                                        $row->addTextField('yearName')->readonly()->setValue($yearName);

                                    $row = $form->addRow();
                                        $row->addLabel('courseName', __('Course'));
                                        $row->addTextField('courseName')->readonly()->setValue($values['courseName']);

                                    $row = $form->addRow();
                                        $row->addLabel('unitName', __('Unit'));
                                        $row->addTextField('unitName')->readonly()->setValue($values['name']);

                                    $dataSelectClassSource= array('tawasulCourseID' => $tawasulCourseID);
                                    $sqlSelectClassSource = "SELECT tawasulCourseClassID as value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS name FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulCourseClass.tawasulCourseID=:tawasulCourseID ORDER BY name";

                                    $row = $form->addRow()->addClass('targetClass');
                                        $row->addLabel('tawasulCourseClassIDSource', __('Source Class'));
                                        $row->addSelect('tawasulCourseClassIDSource')->fromQuery($pdo, $sqlSelectClassSource, $dataSelectClassSource)->required()->placeholder();

                                    $form->addRow()->addHeading('Target', __('Target'));

                                    $row = $form->addRow();
                                        $row->addLabel('year', __('School Year'));
                                        $row->addTextField('year')->readonly()->setValue($year);

                                    $row = $form->addRow();
                                        $row->addLabel('course', __('Course'));
                                        $row->addTextField('course')->readonly()->setValue($course);

                                    $row = $form->addRow();
                                        $row->addLabel('unitName', __('Unit'));
                                        $row->addTextField('unitName')->readonly()->setValue($values['name']);

                                    $dataSelectClassTarget= array('tawasulCourseID' => $tawasulCourseIDTarget);
                                    $sqlSelectClassTarget = "SELECT tawasulCourseClassID as value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS name FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulCourseClass.tawasulCourseID=:tawasulCourseID ORDER BY name";

                                    $row = $form->addRow()->addClass('targetClass');
                                        $row->addLabel('tawasulCourseClassIDTarget[]', __('Classes'));
                                        $row->addSelect('tawasulCourseClassIDTarget[]')->fromQuery($pdo, $sqlSelectClassTarget, $dataSelectClassTarget)->required()->selectMultiple();

                                    $row = $form->addRow();
                                        $row->addFooter();
                                        $row->addSubmit();

                                    echo $form->getOutput();

                                }
                            }
                        }
                    }
                }
            }
        }
    }
    //Print sidebar
    $session->set('sidebarExtra', sidebarExtraUnits($guid, $connection2, $tawasulCourseID, $tawasulSchoolYearID));
}
?>
