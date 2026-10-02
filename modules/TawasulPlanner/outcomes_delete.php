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

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/outcomes_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        if ($highestAction != 'Manage Outcomes_viewEditAll' and $highestAction != 'Manage Outcomes_viewAllEditLearningArea') {
            $page->addError(__('You do not have access to this action.'));
        } else {
            //Proceed!
            $filter2 = '';
            if (isset($_GET['filter2'])) {
                $filter2 = $_GET['filter2'] ?? '';
            }

            //Check if tawasulOutcomeID specified
            $tawasulOutcomeID = $_GET['tawasulOutcomeID'] ?? '';
            if ($tawasulOutcomeID == '') {
                $page->addError(__('You have not specified one or more required parameters.'));
            } else {
                try {
                    if ($highestAction == 'Manage Outcomes_viewEditAll') {
                        $data = array('tawasulOutcomeID' => $tawasulOutcomeID);
                        $sql = 'SELECT * FROM tawasulOutcome WHERE tawasulOutcomeID=:tawasulOutcomeID';
                    } elseif ($highestAction == 'Manage Outcomes_viewAllEditLearningArea') {
                        $data = array('tawasulOutcomeID' => $tawasulOutcomeID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                        $sql = "SELECT * FROM tawasulOutcome JOIN tawasulDepartment ON (tawasulOutcome.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) AND NOT tawasulOutcome.tawasulDepartmentID IS NULL WHERE tawasulOutcomeID=:tawasulOutcomeID AND (role='Coordinator' OR role='Teacher (Curriculum)') AND tawasulPersonID=:tawasulPersonID AND scope='Learning Area'";
                    }
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                }

                if ($result->rowCount() != 1) {
                    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                } else {
                    $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/outcomes_deleteProcess.php?filter2=".$filter2);
                    $form->addHiddenValue('tawasulOutcomeID', $tawasulOutcomeID);
                    echo $form->getOutput();
                }
            }
        }
    }
}
