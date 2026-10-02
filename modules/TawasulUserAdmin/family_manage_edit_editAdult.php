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

if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/family_manage_edit_editAdult.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $urlParams = ['tawasulFamilyID' => $_GET['tawasulFamilyID']];
    
    $page->breadcrumbs
        ->add(__('Manage Families'), 'family_manage.php')
        ->add(__('Edit Family'), 'family_manage_edit.php', $urlParams)
        ->add(__('Edit Adult'));  

    //Check if tawasulPersonID and tawasulFamilyID specified
    $tawasulFamilyID = $_GET['tawasulFamilyID'] ?? '';
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    $search = $_GET['search'] ?? '';
    if ($tawasulPersonID == '' or $tawasulFamilyID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        
            $data = array('tawasulFamilyID' => $tawasulFamilyID, 'tawasulPersonID' => $tawasulPersonID);
            $sql = "SELECT * FROM tawasulPerson, tawasulFamily, tawasulFamilyAdult WHERE tawasulFamily.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID AND tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulFamily.tawasulFamilyID=:tawasulFamilyID AND tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/family_manage_edit_editAdultProcess.php?tawasulPersonID=$tawasulPersonID&tawasulFamilyID=$tawasulFamilyID&search=$search");

            $form->addHiddenValue('address', $session->get('address'));
            
            if ($search != '') {
                $params = [
                    "search" => $search,
                    "tawasulFamilyID" => $tawasulFamilyID
                ];
                $form->addHeaderAction('back', __('Back'))
                    ->setURL('/modules/TawasulUserAdmin/family_manage_edit.php')
                    ->addParams($params);
            }

            $form->addRow()->addHeading('Edit Adult', __('Edit Adult'));

            $row = $form->addRow();
                $row->addLabel('adult', __('Adult\'s Name'));
                $row->addTextField('adult')->setValue(Format::name(htmlPrep($values['title']), htmlPrep($values['preferredName']), htmlPrep($values['surname']), 'Parent'))->required()->readonly();

            $row = $form->addRow();
                $row->addLabel('comment', __('Comment'))->description(__('Data displayed in full Student Profile'));
                $row->addTextArea('comment')->setRows(8);

            $row = $form->addRow();
                $row->addLabel('childDataAccess', __('Data Access?'))->description(__('Access data on family\'s children?'));
                $row->addYesNo('childDataAccess')->required();

            $priorities = array(
                '1' => __('1'),
                '2' => __('2'),
                '3' => __('3')
            );
            $row = $form->addRow();
                $row->addLabel('contactPriority', __('Contact Priority'))->description(__('The order in which school should contact family members.'));
                $row->addSelect('contactPriority')->fromArray($priorities)->required();

            $row = $form->addRow()->addClass('contact');
                $row->addLabel('contactCall', __('Call?'))->description(__('Receive non-emergency phone calls from school?'));
                $row->addYesNo('contactCall')->required();

            $row = $form->addRow()->addClass('contact');
                $row->addLabel('contactSMS', __('SMS?'))->description(__('Receive non-emergency SMS messages from school?'));
                $row->addYesNo('contactSMS')->required();

            $row = $form->addRow()->addClass('contact');
                $row->addLabel('contactEmail', __('Email?'))->description(__('Receive non-emergency emails from school?'));
                $row->addYesNo('contactEmail')->required();

            $row = $form->addRow()->addClass('contact');
                $row->addLabel('contactMail', __('Mail?'))->description(__('Receive postage mail from school?'));
                $row->addYesNo('contactMail')->required();

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            $form->loadAllValuesFrom($values);

            echo $form->getOutput();

            echo "<script type=\"text/javascript\">
                $(document).ready(function(){
                    $(\"#contactCall\").attr(\"disabled\", \"disabled\");
                    $(\"#contactSMS\").attr(\"disabled\", \"disabled\");
                    $(\"#contactEmail\").attr(\"disabled\", \"disabled\");
                    $(\"#contactMail\").attr(\"disabled\", \"disabled\");
                    $(\"#contactPriority\").change(function(){
                        if ($('#contactPriority').val()==\"1\" ) {
                            $(\"#contactCall\").attr(\"disabled\", \"disabled\");
                            $(\"#contactCall\").val(\"Y\");
                            $(\"#contactSMS\").attr(\"disabled\", \"disabled\");
                            $(\"#contactSMS\").val(\"Y\");
                            $(\"#contactEmail\").attr(\"disabled\", \"disabled\");
                            $(\"#contactEmail\").val(\"Y\");
                            $(\"#contactMail\").attr(\"disabled\", \"disabled\");
                            $(\"#contactMail\").val(\"Y\");
                        }
                        else {
                            $(\"#contactCall\").removeAttr(\"disabled\");
                            $(\"#contactSMS\").removeAttr(\"disabled\");
                            $(\"#contactEmail\").removeAttr(\"disabled\");
                            $(\"#contactMail\").removeAttr(\"disabled\");
                        }
                    });
                    $(\"#contactPriority\").change();
                });
            </script>";

        }
    }
}
?>
