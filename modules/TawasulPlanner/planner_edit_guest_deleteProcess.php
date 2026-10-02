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

$tawasulPlannerEntryID = $_GET['tawasulPlannerEntryID'] ?? '';
$tawasulPlannerEntryGuestID = $_GET['tawasulPlannerEntryGuestID'] ?? '';
$viewBy = $_GET['viewBy'] ?? '';
$subView = $_GET['subView'] ?? '';
if ($viewBy != 'date' and $viewBy != 'class') {
    $viewBy = 'date';
}
$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$date = $_GET['date'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/planner_edit.php&tawasulPlannerEntryID=$tawasulPlannerEntryID";

//Params to pass back (viewBy + date or classID)
if ($viewBy == 'date') {
    $params = "&viewBy=$viewBy&date=$date";
} else {
    $params = "&viewBy=$viewBy&tawasulCourseClassID=$tawasulCourseClassID&subView=$subView";
}

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_edit.php') == false) {
    $URL .= "&return=error0$params";
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!

        //Check if tawasulPlannerEntryID and tawasulCourseClassID specified
        if ($tawasulPlannerEntryID == '' or $tawasulPlannerEntryGuestID == '' or ($viewBy == 'class' and $tawasulCourseClassID == 'Y')) {
            $URL .= "&return=error1$params";
            header("Location: {$URL}");
        } else {
            try {
                if ($viewBy == 'date') {
                    if ($highestAction == 'Lesson Planner_viewEditAllClasses') {
                        $data = array('date' => $date, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                        $sql = 'SELECT tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE date=:date AND tawasulPlannerEntryID=:tawasulPlannerEntryID';
                    } else {
                        $data = array('date' => $date, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                        $sql = "SELECT tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, role FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND date=:date AND tawasulPlannerEntryID=:tawasulPlannerEntryID";
                    }
                } else {
                    if ($highestAction == 'Lesson Planner_viewEditAllClasses') {
                        $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                        $sql = 'SELECT tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPlannerEntryID=:tawasulPlannerEntryID';
                    } else {
                        $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                        $sql = "SELECT tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, role FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPlannerEntryID=:tawasulPlannerEntryID";
                    }
                }
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= "&return=error2$params";
                header("Location: {$URL}");
                exit();
            }

            if ($result->rowCount() != 1) {
                $URL .= "&return=error2$params";
                header("Location: {$URL}");
            } else {
                //Write to database
                try {
                    $data = array('tawasulPlannerEntryGuestID' => $tawasulPlannerEntryGuestID);
                    $sql = 'DELETE FROM tawasulPlannerEntryGuest WHERE tawasulPlannerEntryGuestID=:tawasulPlannerEntryGuestID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= "&return=error2$params";
                    header("Location: {$URL}");
                    exit();
                }

                $URL .= "&return=success0$params";
                header("Location: {$URL}");
            }
        }
    }
}
