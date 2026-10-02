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

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

use TawasulOS\Forms\Form;
use TawasulOS\Domain\Timetable\CourseGateway;
use Tos\Module\TawasulPlanner\Forms\PlannerFormFactory;

// common variables
$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$tawasulUnitID = $_GET['tawasulUnitID'] ?? '';

$page->breadcrumbs
    ->add(__('Unit Planner'), 'units.php', [
        'tawasulSchoolYearID' => $tawasulSchoolYearID,
        'tawasulCourseID' => $tawasulCourseID,
    ])
    ->add(__('Edit Unit'), 'units_edit.php', [
        'tawasulSchoolYearID' => $tawasulSchoolYearID,
        'tawasulCourseID' => $tawasulCourseID,
        'tawasulUnitID' => $tawasulUnitID,
    ])
    ->add(__('Copy Unit Forward'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_edit_copyForward.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Proceed!
        //Check if courseschool year specified
        if ($tawasulCourseID == '' or $tawasulSchoolYearID == '' or $tawasulCourseClassID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {

            $courseGateway = $container->get(CourseGateway::class);

            // Check access to specified course
            if ($highestAction == 'Unit Planner_all') {
                $result = $courseGateway->selectCourseDetailsByClass($tawasulCourseClassID);
            } elseif ($highestAction == 'Unit Planner_learningAreas') {
                $result = $courseGateway->selectCourseDetailsByClassAndPerson($tawasulCourseClassID, $session->get('tawasulPersonID'));
            }

            if ($result->rowCount() != 1) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                $values = $result->fetch();
                $year = $values['schoolYear'];
                $course = $values['course'];
                $class = $values['class'];

                //Check if unit specified
                if ($tawasulUnitID == '') {
                    $page->addError(__('You have not specified one or more required parameters.'));
                } else {

                        $data = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseID' => $tawasulCourseID);
                        $sql = 'SELECT tawasulCourse.nameShort AS courseName, tawasulUnit.* FROM tawasulUnit JOIN tawasulCourse ON (tawasulUnit.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulUnitID=:tawasulUnitID AND tawasulUnit.tawasulCourseID=:tawasulCourseID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);

                    if ($result->rowCount() != 1) {
                        $page->addError(__('The specified record cannot be found.'));
                    } else {
                        //Let's go!
                        $values = $result->fetch();

                        echo '<p>';
                        echo sprintf(__('This function allows you to take the selected working unit (%1$s in %2$s) and use its blocks, and the master unit details, to create a new unit. In this way you can use your refined and improved unit as a new master unit whilst leaving your existing master unit untouched.'), $values['name'], "$course.$class");
                        echo '</p>';

                        $form = Form::create('unitsEditCopyForward', $session->get('absoluteURL').'/modules/'.$session->get('module')."/units_edit_copyForwardProcess.php?tawasulUnitID=$tawasulUnitID&tawasulCourseID=$tawasulCourseID&tawasulCourseClassID=$tawasulCourseClassID&tawasulSchoolYearID=$tawasulSchoolYearID");
                        $form->setFactory(PlannerFormFactory::create($pdo));
                        $form->addHiddenValue('address', $session->get('address'));
                        $form->addHiddenValue('tawasulCourseClassID', $tawasulCourseClassID);
                        $form->addHiddenValue('tawasulCourseID', $tawasulCourseID);
                        $form->addHiddenValue('tawasulUnitID', $tawasulUnitID);
                        $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

                        $form->addRow()->addHeading('Source', __('Source'));
                            $row = $form->addRow();
                            $row->addLabel('yearName', __('School Year'));
                            $row->addTextField('yearName')->readonly()->setValue($year)->required();

                            $row = $form->addRow();
                            $row->addLabel('class', __('Class'));
                            $row->addTextField('class')->readonly()->setValue($course.'.'.$class)->required();

                            $row = $form->addRow();
                            $row->addLabel('unit', __('Unit'));
                            $row->addTextField('unit')->readonly()->setValue($values['name'])->required();

                            $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                            $sql = "SELECT tawasulSchoolYearID as value,name FROM tawasulSchoolYear WHERE (status='Upcoming' OR status='Current') ORDER BY sequenceNumber";
                            $form->addRow()->addHeading('Target', __('Target'));
                            $row = $form->addRow();
                                $row->addLabel('tawasulSchoolYearIDCopyTo', __('Year'));
                                $row->addSearchSelect('tawasulSchoolYearIDCopyTo')->fromQuery($pdo, $sql)->placeholder()->isRequired();


                                            try {
                                                if ($highestAction == 'Unit Planner_all') {
                                                    $dataSelect = array();
                                                    $sqlSelect = 'SELECT tawasulCourse.nameShort AS name, tawasulCourseID AS value, tawasulSchoolYear.tawasulSchoolYearID AS chainedTo FROM tawasulCourse JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) ORDER BY nameShort';
                                                } elseif ($highestAction == 'Unit Planner_learningAreas') {
                                                    $dataSelect = array('tawasulPersonID' => $session->get('tawasulPersonID'));
                                                    $sqlSelect = "SELECT tawasulCourse.nameShort AS name, tawasulCourseID AS value, tawasulSchoolYear.tawasulSchoolYearID AS chainedTo FROM tawasulCourse JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID AND (role='Coordinator' OR role='Assistant Coordinator' OR role='Teacher (Curriculum)') ORDER BY tawasulCourse.nameShort";
                                                }
                                                $resultSelect = $connection2->prepare($sqlSelect);
                                                $resultSelect->execute($dataSelect);
                                            } catch (PDOException $e) {
                                            }

                                $row = $form->addRow();
                                    $row->addLabel('tawasulCourseIDTarget', __('Course'));
                                    $row->addSelect('tawasulCourseIDTarget')->fromQueryChained($pdo, $sqlSelect, $dataSelect, 'tawasulSchoolYearIDCopyTo')->placeholder()->isRequired();

                                $row = $form->addRow();
                                    $row->addLabel('nameTarget', __('New Unit Name'));
                                    $row->addTextField('nameTarget')->required()->setValue($values['name'])->maxLength(40);


                            $form->loadAllValuesFrom($values);
                            $row = $form->addRow();
                                $row->addFooter();
                                $row->addSubmit();

                        echo $form->getOutput();
                    }
                }
            }
        }
    }
    //Print sidebar
    $session->set('sidebarExtra', sidebarExtraUnits($guid, $connection2, $tawasulCourseID, $tawasulSchoolYearID));
}
