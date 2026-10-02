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

//TawasulOS system-wide includes
require_once __DIR__ . '/../../tawasul.php';

//Module includes
include './moduleFunctions.php';

$tawasulPlannerEntryID = $_GET['tawasulPlannerEntryID'] ?? '';
$tawasulPlannerEntryHomeworkID = $_GET['tawasulPlannerEntryHomeworkID'] ?? '';
$date = $_GET['date'] ?? '';
$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$viewBy = $_GET['viewBy'] ?? '';
$subView = $_GET['subView'] ?? '';
$search = $_POST['search'] ?? '';
$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulPlanner/planner_view_full.php&date=$date&viewBy=$viewBy&subView=$subView&tawasulCourseClassID=$tawasulCourseClassID&tawasulPlannerEntryID=$tawasulPlannerEntryID&search=$search";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_view_full.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if planner specified
    if ($tawasulPlannerEntryID == '' or $tawasulPlannerEntryHomeworkID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
            $sql = 'SELECT * FROM tawasulPlannerEntry WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
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
            //INSERT
            try {
                $data = array('tawasulPlannerEntryHomeworkID' => $tawasulPlannerEntryHomeworkID);
                $sql = 'DELETE FROM tawasulPlannerEntryHomework WHERE tawasulPlannerEntryHomeworkID=:tawasulPlannerEntryHomeworkID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            $URL .= '&return=success0';
            header("Location: {$URL}");
        }
    }
}
