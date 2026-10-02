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

require_once __DIR__ . '/../../tawasul.php';

include './moduleFunctions.php';

$filter2 = $_GET['filter2'] ?? '';


$tawasulOutcomeID = $_POST['tawasulOutcomeID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/outcomes_delete.php&tawasulOutcomeID=$tawasulOutcomeID&filter2=$filter2";
$URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/outcomes.php&filter2=$filter2";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/outcomes_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
    } else {
        if ($highestAction != 'Manage Outcomes_viewEditAll' and $highestAction != 'Manage Outcomes_viewAllEditLearningArea') {
            $URL .= '&return=error0';
            header("Location: {$URL}");
        } else {
            //Proceed!
            if ($tawasulOutcomeID == '') {
                $URL .= '&return=error1';
                header("Location: {$URL}");
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
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() != 1) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('tawasulOutcomeID' => $tawasulOutcomeID);
                        $sql = 'DELETE FROM tawasulOutcome WHERE tawasulOutcomeID=:tawasulOutcomeID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $URLDelete = $URLDelete.'&return=success0';
                    header("Location: {$URLDelete}");
                }
            }
        }
    }
}
