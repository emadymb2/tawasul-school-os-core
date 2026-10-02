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

if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/studentEnrolment_manage_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $tawasulStudentEnrolmentID = $_GET['tawasulStudentEnrolmentID'] ?? '';
    $search = $_GET['search'] ?? '';

    //Check if tawasulStudentEnrolmentID and tawasulSchoolYearID specified
    if ($tawasulStudentEnrolmentID == '' or $tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        
            $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID);
            $sql = 'SELECT tawasulFormGroup.tawasulFormGroupID, tawasulYearGroup.tawasulYearGroupID,tawasulStudentEnrolmentID, surname, preferredName, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.nameShort AS formGroup FROM tawasulPerson, tawasulStudentEnrolment, tawasulYearGroup, tawasulFormGroup WHERE (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) AND (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) AND (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) AND tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID ORDER BY surname, preferredName';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/studentEnrolment_manage_deleteProcess.php?search=$search", true);
            $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);
            echo $form->getOutput();
        }
    }
}
