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

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage_enrolment_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulActivityID = (isset($_GET['tawasulActivityID']))? $_GET['tawasulActivityID'] : null;

    $highestAction = getHighestGroupedAction($guid, '/modules/TawasulActivities/activities_manage_enrolment.php', $connection2);
    if ($highestAction == 'My Activities_viewEditEnrolment') {


            $data = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulActivityID' => $tawasulActivityID);
            $sql = "SELECT tawasulActivity.*, NULL as status, tawasulActivityStaff.role FROM tawasulActivity JOIN tawasulActivityStaff ON (tawasulActivity.tawasulActivityID=tawasulActivityStaff.tawasulActivityID) WHERE tawasulActivity.tawasulActivityID=:tawasulActivityID AND tawasulActivityStaff.tawasulPersonID=:tawasulPersonID AND tawasulActivityStaff.role='Organiser' AND tawasulSchoolYearID=:tawasulSchoolYearID AND active='Y' ORDER BY name";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if (!$result || $result->rowCount() == 0) {
            //Acess denied
            $page->addError(__('You do not have access to this action.'));
            return;
        }
    }

    //Check if tawasulActivityID and tawasulPersonID specified
    $tawasulActivityID = $_GET['tawasulActivityID'] ?? '';
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    if ($tawasulPersonID == '' or $tawasulActivityID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $tawasulPersonID);
            $sql = 'SELECT tawasulActivity.*, tawasulActivityStudent.*, surname, preferredName FROM tawasulActivity JOIN tawasulActivityStudent ON (tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID) JOIN tawasulPerson ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulActivityStudent.tawasulActivityID=:tawasulActivityID AND tawasulActivityStudent.tawasulPersonID=:tawasulPersonID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $row = $result->fetch();

            $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/activities_manage_enrolment_deleteProcess.php?search=".$_GET['search']."&tawasulSchoolYearTermID=".$_GET['tawasulSchoolYearTermID']);
            $form->addHiddenValue('tawasulActivityID', $tawasulActivityID);
            $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
            echo $form->getOutput();
        }
    }
}
?>
