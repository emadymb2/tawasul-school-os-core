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

use TawasulOS\Forms\Prefab\DeleteForm;
use TawasulOS\Domain\Timetable\CourseGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

// common variables
$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
$tawasulUnitID = $_GET['tawasulUnitID'] ?? '';

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_delete.php') == false) {
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
        if ($tawasulCourseID == '' or $tawasulSchoolYearID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            $courseGateway = $container->get(CourseGateway::class);

            // Check access to specified course
            if ($highestAction == 'Unit Planner_all') {
                $resultCourse = $courseGateway->selectCourseDetailsByCourse($tawasulCourseID);
            } elseif ($highestAction == 'Unit Planner_learningAreas') {
                $resultCourse = $courseGateway->selectCourseDetailsByCourseAndPerson($tawasulCourseID, $session->get('tawasulPersonID'));
            }

            if ($resultCourse->rowCount() != 1) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                //Check if unit specified
                if ($tawasulUnitID == '') {
                    $page->addError(__('You have not specified one or more required parameters.'));
                } else {
                    
                        $data = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseID' => $tawasulCourseID);
                        $sql = 'SELECT * FROM tawasulUnit WHERE tawasulUnitID=:tawasulUnitID AND tawasulCourseID=:tawasulCourseID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);

                    if ($result->rowCount() != 1) {
                        $page->addError(__('The specified record cannot be found.'));
                    } else {
                        $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/units_deleteProcess.php");
                        $form->addHiddenValue('tawasulUnitID', $tawasulUnitID);
                        $form->addHiddenValue('tawasulCourseID', $tawasulCourseID);
                        $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);
                        echo $form->getOutput();
                    }
                }
            }
        }
    }
}
?>
