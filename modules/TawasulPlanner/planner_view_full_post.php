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

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_view_full_post.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
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
            $date = $_GET['date'] ?? '';
            if (isset($_GET['dateHuman'])) {
                $date = Format::dateConvert($_GET['dateHuman']);
            }
            if ($date == '') {
                $date = date('Y-m-d');
            }
            list($dateYear, $dateMonth, $dateDay) = explode('-', $date);
            $dateStamp = mktime(0, 0, 0, $dateMonth, $dateDay, $dateYear);
        } elseif ($viewBy == 'class') {
            $class = null;
            if (isset($_GET['class'])) {
                $class = $_GET['class'] ?? '';
            }
            $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
        }
        $replyTo = null;
        if (isset($_GET['replyTo'])) {
            $replyTo = $_GET['replyTo'] ?? '';
        }
        $search = $_GET['search'] ?? '';

        //Get class variable
        $tawasulPlannerEntryID = $_GET['tawasulPlannerEntryID'] ?? '';

        if ($tawasulPlannerEntryID == '') {
            echo "<div class='warning'>";
            echo __('You have not specified one or more required parameters.');
            echo '</div>';
        }
        //Check existence of and access to this class.
        else {
            if ($highestAction == 'Lesson Planner_viewMyChildrensClasses') {
                if ($_GET['search'] == '') {
                    echo "<div class='warning'>";
                    echo __('You have not specified one or more required parameters.');
                    echo '</div>';
                } else {
                    $tawasulPersonID = $_GET['search'] ?? '';
                    
                        $dataChild = array('tawasulPersonID1' => $tawasulPersonID, 'tawasulPersonID2' => $session->get('tawasulPersonID'));
                        $sqlChild = "SELECT * FROM tawasulFamilyChild JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND tawasulFamilyChild.tawasulPersonID=:tawasulPersonID1 AND tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID2 AND childDataAccess='Y'";
                        $resultChild = $connection2->prepare($sqlChild);
                        $resultChild->execute($dataChild);
                    if ($resultChild->rowCount() != 1) {
                        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                    } else {
                        $data = array('date' => $date, 'tawasulPersonID' => $tawasulPersonID, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                        $sql = "(SELECT tawasulPlannerEntry.tawasulPlannerEntryID, tawasulCourseClass.tawasulCourseClassID, tawasulUnitID, tawasulPlannerEntry.tawasulCourseClassID, tawasulPlannerEntry.name, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, date, timeStart, timeEnd, summary, tawasulPlannerEntry.description, teachersNotes, homework, homeworkDueDateTime, homeworkDetails, viewableStudents, viewableParents, role, homeworkSubmission, homeworkSubmissionDateOpen, homeworkSubmissionDrafts, homeworkSubmissionType FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND NOT role='Student - Left' AND NOT role='Teacher - Left' AND tawasulPlannerEntry.tawasulPlannerEntryID=:tawasulPlannerEntryID) UNION (SELECT tawasulPlannerEntry.tawasulPlannerEntryID, tawasulCourseClass.tawasulCourseClassID, tawasulUnitID, tawasulPlannerEntry.tawasulCourseClassID, tawasulPlannerEntry.name, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, date, timeStart, timeEnd, summary, tawasulPlannerEntry.description, teachersNotes, homework, homeworkDueDateTime, homeworkDetails, viewableStudents, viewableParents, role, homeworkSubmission, homeworkSubmissionDateOpen, homeworkSubmissionDrafts, homeworkSubmissionType FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulPlannerEntryGuest ON (tawasulPlannerEntryGuest.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE date=:date AND tawasulPlannerEntryGuest.tawasulPersonID=:tawasulPersonID AND tawasulPlannerEntry.tawasulPlannerEntryID=:tawasulPlannerEntryID) ORDER BY date, timeStart";
                    }
                }
            } elseif ($highestAction == 'Lesson Planner_viewMyClasses' or $highestAction == 'Lesson Planner_viewAllEditMyClasses') {
                $data = array('date' => $date, 'tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                $sql = "(SELECT tawasulPlannerEntry.tawasulPlannerEntryID, tawasulCourseClass.tawasulCourseClassID, tawasulUnitID, tawasulPlannerEntry.tawasulCourseClassID, tawasulPlannerEntry.name, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, date, timeStart, timeEnd, summary, tawasulPlannerEntry.description, teachersNotes, homework, homeworkDueDateTime, homeworkDetails, viewableStudents, viewableParents, role, homeworkSubmission, homeworkSubmissionDateOpen, homeworkSubmissionDrafts, homeworkSubmissionType FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND NOT role='Student - Left' AND NOT role='Teacher - Left' AND tawasulPlannerEntry.tawasulPlannerEntryID=:tawasulPlannerEntryID) UNION (SELECT tawasulPlannerEntry.tawasulPlannerEntryID, tawasulCourseClass.tawasulCourseClassID, tawasulUnitID, tawasulPlannerEntry.tawasulCourseClassID, tawasulPlannerEntry.name, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, date, timeStart, timeEnd, summary, tawasulPlannerEntry.description, teachersNotes, homework, homeworkDueDateTime, homeworkDetails, viewableStudents, viewableParents, role, homeworkSubmission, homeworkSubmissionDateOpen, homeworkSubmissionDrafts, homeworkSubmissionType FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulPlannerEntryGuest ON (tawasulPlannerEntryGuest.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE date=:date AND tawasulPlannerEntryGuest.tawasulPersonID=:tawasulPersonID AND tawasulPlannerEntry.tawasulPlannerEntryID=:tawasulPlannerEntryID) ORDER BY date, timeStart";
            } elseif ($highestAction == 'Lesson Planner_viewEditAllClasses') {
                $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                $sql = "SELECT tawasulPlannerEntry.tawasulPlannerEntryID, tawasulCourseClass.tawasulCourseClassID, tawasulUnitID, tawasulPlannerEntry.tawasulCourseClassID, tawasulPlannerEntry.name, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, date, timeStart, timeEnd, summary, tawasulPlannerEntry.description, teachersNotes, homework, homeworkDueDateTime, homeworkDetails, viewableStudents, viewableParents, 'Teacher' AS role, homeworkSubmission, homeworkSubmissionDateOpen, homeworkSubmissionDrafts, homeworkSubmissionType FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulPlannerEntry.tawasulPlannerEntryID=:tawasulPlannerEntryID ORDER BY date, timeStart";
            }
            
                $result = $connection2->prepare($sql);
                $result->execute($data);

            if ($result->rowCount() != 1) {
                echo "<div class='warning'>";
                echo __('The selected record does not exist, or you do not have access to it.');
                echo '</div>';
            } else {
                $row = $result->fetch();

                // target of the planner
                $target = ($viewBy === 'class') ? $row['course'].'.'.$row['class'] : Format::date($date);

                // planner's parameters
                $params = [];
                if ($date != '') {
                    $params['date'] = $_GET['date'] ?? '';
                }
                if ($viewBy != '') {
                    $params['viewBy'] = $_GET['viewBy'] ?? '';
                }
                if ($tawasulCourseClassID != '') {
                    $params['tawasulCourseClassID'] = $tawasulCourseClassID;
                }
                $params['subView'] = $subView;
                $paramsVar = '&' . http_build_query($params); // for backward compatibile uses below (should be get rid of)

                $page->breadcrumbs
                    ->add(__('Planner for {classDesc}', [
                        'classDesc' => $target,
                    ]), 'planner.php', $params)
                    ->add(__('View Lesson Plan'), 'planner_view_full.php', $params + ['tawasulPlannerEntryID' => $tawasulPlannerEntryID])
                    ->add(__('Add Comment'));

                if (($row['role'] == 'Student' and $row['viewableStudents'] == 'N') and ($highestAction == 'Lesson Planner_viewMyChildrensClasses' and $row['viewableParents'] == 'N')) {
                    echo "<div class='warning'>";
                    echo __('The selected record does not exist, or you do not have access to it.');
                    echo '</div>';
                } else {
                    echo '<h2>';
                    echo __('Planner Discussion Post');
                    echo '</h2>';

                    $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module').'/planner_view_full_postProcess.php');

                    $form->addHiddenValue('search', $search);
                    $form->addHiddenValue('replyTo', $replyTo);
                    $form->addHiddenValue('params', $paramsVar);
                    $form->addHiddenValue('tawasulPlannerEntryID', $tawasulPlannerEntryID);
                    $form->addHiddenValue('address', $session->get('address'));

                    $row = $form->addRow();
                        $column = $row->addColumn();
                        $column->addLabel('comment', __('Write your comment below:'));
                        $column->addEditor('comment', $guid)->setRows(20)->showMedia();

                    $row = $form->addRow();
                        $row->addFooter();
                        $row->addSubmit();

                    echo $form->getOutput();
                }
            }
        }
    }
}
