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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Prefab\DeleteForm;
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/weighting_manage_delete.php') == false) {
    //Acess denied
    $page->addError(__('Your request failed because you do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {

        if ($container->get(SettingGateway::class)->getSettingByScope('Markbook', 'enableColumnWeighting') != 'Y') {
            //Acess denied
            $page->addError(__('Your request failed because you do not have access to this action.'));
        }

        //Get class variable
        $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';

        if ($tawasulCourseClassID == '') {
            echo '<h1>';
            echo __('Delete Markbook Weighting');
            echo '</h1>';
            echo "<div class='warning'>";
            echo __('The selected record does not exist, or you do not have access to it.');
            echo '</div>';

            return;
        }
        //Check existence of and access to this class.
        else {
            try {
                if ($highestAction == 'Manage Weightings_everything') {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse, tawasulCourseClass WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                } else {
                    $data = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class";
                }
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
            }

            if ($result->rowCount() != 1) {
                echo '<h1>';
                echo __('Delete Markbook Weighting');
                echo '</h1>';
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                $tawasulMarkbookWeightID = (isset($_GET['tawasulMarkbookWeightID']))? $_GET['tawasulMarkbookWeightID'] : null;

                    $data2 = array('tawasulMarkbookWeightID' => $tawasulMarkbookWeightID);
                    $sql2 = 'SELECT * FROM tawasulMarkbookWeight WHERE tawasulMarkbookWeightID=:tawasulMarkbookWeightID';
                    $result2 = $connection2->prepare($sql2);
                    $result2->execute($data2);

                if ($result2->rowCount() != 1) {
                    echo '<h1>';
                    echo __('Delete Markbook Weighting');
                    echo '</h1>';
                    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                } else {
                    $row = $result->fetch();
                    $row2 = $result2->fetch();

                    $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/weighting_manage_deleteProcess.php");
                    $form->addHiddenValue('tawasulCourseClassID', $tawasulCourseClassID);
                    echo $form->getOutput();
                }
            }
        }
    }
}
