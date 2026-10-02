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

use TawasulOS\Services\Format;
use TawasulOS\Domain\Rubrics\RubricGateway;

//Rubric includes
require_once __DIR__ . '/../Rubrics/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_view.php') == false) {
    //Acess denied
    $page->addError(__('Your request failed because you do not have access to this action.'));
} else {
    //Proceed!
    $page->scripts->add('chart');

    //Check if tawasulCourseClassID and tawasulMarkbookColumnID and tawasulPersonID and tawasulRubricID specified
    $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
    $tawasulMarkbookColumnID = $_GET['tawasulMarkbookColumnID'] ?? '';
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    $tawasulRubricID = $_GET['tawasulRubricID'] ?? '';
    if ($tawasulCourseClassID == '' or $tawasulMarkbookColumnID == '' or $tawasulPersonID == '' or $tawasulRubricID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $roleCategory = $session->get('tawasulRoleIDCurrentCategory');
        $contextDBTableTawasulOSRubricIDField = 'tawasulRubricID';
        if ($_GET['type'] == 'attainment') {
            $contextDBTableTawasulOSRubricIDField = 'tawasulRubricIDAttainment';
        } elseif ($_GET['type'] == 'effort') {
            $contextDBTableTawasulOSRubricIDField = 'tawasulRubricIDEffort';
        }

        try {
            if ($roleCategory == 'Staff') {
                $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
                $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID FROM tawasulCourse, tawasulCourseClass WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
            } elseif ($roleCategory == 'Student') {
                $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulCourseClassID' => $tawasulCourseClassID);
                $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND (role='Student' OR role='Student - Left') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class";
            } elseif ($roleCategory == 'Parent') {
                $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulCourseClassID' => $tawasulCourseClassID);
                $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND (role='Student' OR role='Student - Left') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class";
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
                    $result3 = $container->get(RubricGateway::class)->selectBy(['tawasulRubricID' => $tawasulRubricID]);
                    
                if ($result3->rowCount() != 1) {
                    $page->addError(__('The specified record does not exist.'));
                } else {

                        $data4 = array('tawasulPersonID' => $tawasulPersonID, 'tawasulCourseClassID' => $tawasulCourseClassID);
                        $sql4 = "SELECT surname, preferredName, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID AND tawasulCourseClassID=:tawasulCourseClassID AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND (role='Student' OR role='Student - Left')";
                        $result4 = $connection2->prepare($sql4);
                        $result4->execute($data4);

                    if ($result4->rowCount() == 0) {
                        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                    } else {
                        //Let's go!
                        $row = $result->fetch();
                        $row2 = $result2->fetch();
                        $row3 = $result3->fetch();
                        $row4 = $result4->fetch();

                        echo "<h2 style='margin-bottom: 10px;'>";
                        echo $row3['name'].'<br/>';
                        echo "<span style='font-size: 65%; font-style: italic'>".Format::name('', $row4['preferredName'], $row4['surname'], 'Student', true).'</span>';
                        echo '</h2>';

                        $mark = $session->get('tawasulRoleIDCurrentCategory') == 'Staff';
                        if (isset($_GET['mark']) && $_GET['mark'] == 'FALSE') {
                            $mark = false;
                        }

                        echo rubricView($guid, $connection2, $tawasulRubricID, $mark, $row4['tawasulPersonID'], 'tawasulMarkbookColumn', 'tawasulMarkbookColumnID', $tawasulMarkbookColumnID,  $contextDBTableTawasulOSRubricIDField, 'name', 'completeDate');
                    }
                }
            }
        }
    }
}
