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

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/applicationForm_manage_reject.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulApplicationFormID = $_GET['tawasulApplicationFormID'] ?? '';
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $search = $_GET['search'] ?? '';

    $page->breadcrumbs
        ->add(__('Manage Applications'), 'applicationForm_manage.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
        ->add(__('Reject Application'));

    //Check if tawasulApplicationFormID and tawasulSchoolYearID specified
    if ($tawasulApplicationFormID == '' or $tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $data = array('tawasulApplicationFormID' => $tawasulApplicationFormID);
        $sql = 'SELECT * FROM tawasulApplicationForm WHERE tawasulApplicationFormID=:tawasulApplicationFormID';
        $result = $connection2->prepare($sql);
        $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record does not exist.'));
        } else {
            //Let's go!
            $values = $result->fetch();
            $proceed = true;

           if ($search != '') {
                $params = [
                    "search" => $search,
                    "tawasulSchoolYearID" => $tawasulSchoolYearID
                ];
                $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulStudents', 'applicationForm_manage.php')->withQueryParams($params));
            }

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/applicationForm_manage_rejectProcess.php?tawasulApplicationFormID=$tawasulApplicationFormID&search=$search");

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);
            $form->addHiddenValue('tawasulApplicationFormID', $tawasulApplicationFormID);

            $row = $form->addRow();
                $row->addContent(sprintf(__('Are you sure you want to reject the application for %1$s?'), Format::name('', $values['preferredName'], $values['surname'], 'Student')));

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit(__('Yes'));

            echo $form->getOutput();
        }
    }
}
