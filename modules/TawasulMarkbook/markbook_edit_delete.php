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
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_edit_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Check if tawasulCourseClassID and tawasulMarkbookColumnID specified
        $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
        $tawasulMarkbookColumnID = $_GET['tawasulMarkbookColumnID'] ?? '';
        if ($tawasulCourseClassID == '' or $tawasulMarkbookColumnID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            try {
                if ($highestAction == 'Edit Markbook_everything') {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse, tawasulCourseClass WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                } elseif ($highestAction == 'Edit Markbook_multipleClassesInDepartment') {
                    $data = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList
                    FROM tawasulCourse
                    JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                    LEFT JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulCourse.tawasulDepartmentID AND tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID)
                    LEFT JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID)
                    WHERE ((tawasulCourseClassPerson.tawasulCourseClassPersonID IS NOT NULL AND tawasulCourseClassPerson.role='Teacher')
                        OR (tawasulDepartmentStaff.tawasulDepartmentStaffID IS NOT NULL AND (tawasulDepartmentStaff.role = 'Coordinator' OR tawasulDepartmentStaff.role = 'Assistant Coordinator' OR tawasulDepartmentStaff.role= 'Teacher (Curriculum)'))
                        )
                    AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class";
                } else {
                    $data = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class";
                }
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
            }

            if ($result->rowCount() != 1) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {

                    $data2 = array('tawasulMarkbookColumnID' => $tawasulMarkbookColumnID);
                    $sql2 = 'SELECT * FROM tawasulMarkbookColumn WHERE tawasulMarkbookColumnID=:tawasulMarkbookColumnID';
                    $result2 = $connection2->prepare($sql2);
                    $result2->execute($data2);

                if ($result2->rowCount() != 1) {
                    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                } else {
                    //Let's go!
                    $row = $result->fetch();
                    $row2 = $result2->fetch();

                    if ($row2['groupingID'] != '' && ($row2['tawasulPersonIDCreator'] != $session->get('tawasulPersonID') && $highestAction != 'Edit Markbook_everything' && $highestAction != 'Edit Markbook_multipleClassesAcrossSchool' && $highestAction != 'Edit Markbook_multipleClassesInDepartment')) {
                        echo "<div class='error'>";
                        echo __('This column is part of a set of columns, and so cannot be individually deleted.');
                        echo '</div>';
                    } else {
                        $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/markbook_edit_deleteProcess.php");
                        $form->addHiddenValue('tawasulMarkbookColumnID', $tawasulMarkbookColumnID);
                        echo $form->getOutput();
                    }
                }
            }
        }
    }
}
