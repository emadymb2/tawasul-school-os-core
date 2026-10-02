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
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Services\Format;
use TawasulOS\Domain\Students\FirstAidGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/firstAidRecord_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    }

    $mode = $highestAction == 'First Aid Record_editAll'? 'edit' : 'view';

    //Proceed!
    $page->breadcrumbs
        ->add(__('First Aid Records'), 'firstAidRecord.php')
        ->add(__('Edit'));

    $tawasulFirstAidID = $_GET['tawasulFirstAidID'] ?? '';
    $tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
    $tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';

    if ($tawasulFirstAidID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulFirstAidID' => $tawasulFirstAidID);
            $sql = "SELECT tawasulFirstAid.*, patient.tawasulPersonID AS tawasulPersonIDPatient, patient.surname AS surnamePatient, patient.preferredName AS preferredNamePatient, firstAider.title, firstAider.surname AS surnameFirstAider, firstAider.preferredName AS preferredNameFirstAider
                FROM tawasulFirstAid
                    JOIN tawasulPerson AS patient ON (tawasulFirstAid.tawasulPersonIDPatient=patient.tawasulPersonID)
                    JOIN tawasulPerson AS firstAider ON (tawasulFirstAid.tawasulPersonIDFirstAider=firstAider.tawasulPersonID)
                    JOIN tawasulStudentEnrolment ON (patient.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                    JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                    JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFirstAidID=:tawasulFirstAidID";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/firstAidRecord_editProcess.php?tawasulFirstAidID=$tawasulFirstAidID&tawasulFormGroupID=".$tawasulFormGroupID.'&tawasulYearGroupID='.$tawasulYearGroupID);

            $form->setFactory(DatabaseFormFactory::create($pdo));
            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulPersonID', $values['tawasulPersonIDPatient']);

            $row = $form->addRow()->addHeading('Basic Information', __('Basic Information'));

            $row = $form->addRow();
                $row->addLabel('patient', __('Patient'));
                $row->addTextField('patient')->setValue(Format::name('', $values['preferredNamePatient'], $values['surnamePatient'], 'Student'))->required()->readonly();

            $row = $form->addRow();
                $row->addLabel('name', __('First Aider'));
                $row->addTextField('name')->setValue(Format::name('', $values['preferredNameFirstAider'], $values['surnameFirstAider'], 'Staff', false, true))->required()->readonly();

            $row = $form->addRow();
                $row->addLabel('date', __('Date'));
                $row->addDate('date')->setValue(Format::date($values['date']))->required()->readonly();

            $row = $form->addRow();
                $row->addLabel('timeIn', __('Time In'));
                $row->addTime('timeIn')->setValue(!empty($values['timeIn']) ? substr($values['timeIn'], 0, 5) : '')->required()->readonly();

            $row = $form->addRow();
                $row->addLabel('timeOut', __('Time Out'));
                $row->addTime('timeOut')->setValue(!empty($values['timeOut']) ? substr($values['timeOut'], 0, 5) : '')->chainedTo('timeIn')->readonly($mode != 'edit');

            $row = $form->addRow();
                $column = $row->addColumn();
                $column->addLabel('description', __('Description'));
                $column->addTextArea('description')->setValue($values['description'])->setRows(8)->setClass('w-full')->readonly();

            $row = $form->addRow();
                $column = $row->addColumn();
                $column->addLabel('actionTaken', __('Action Taken'));
                $column->addTextArea('actionTaken')->setValue($values['actionTaken'])->setRows(8)->setClass('w-full')->readonly();

            $row = $form->addRow()->addHeading('Follow Up', __('Follow Up'));

            if (!empty($values['tawasulPersonIDFollowUp']) && $highestAction == 'First Aid Record_editAll') {
                $row = $form->addRow();
                $row->addLabel('tawasulPersonIDFollowUpLabel', __('Follow up Request'))->description(__('A follow up request was sent to the selected user.'));
                $row->addSelectStaff('tawasulPersonIDFollowUp')->photo(true, 'small')->selected($values['tawasulPersonIDFollowUp'])->readOnly();
            }

            //Print old-style followup as first log entry
            if (!empty($values['followUp'])) {
                $row = $form->addRow();
                    $column = $row->addColumn();
                    $column->addLabel('followUp0', __("Follow Up by {name} at {date}", ['name' => Format::name('', $values['preferredNameFirstAider'], $values['surnameFirstAider']), 'date' => Format::dateTimeReadable($values['timestamp'])]));
                    $column->addContent($values['followUp'])->setClass('w-full');
            }

            //Print new-style followup as log
            $firstAidGateway = $container->get(FirstAidGateway::class);
            $logs = $firstAidGateway->queryFollowUpByFirstAidID($tawasulFirstAidID)->fetchAll();

            if (!empty($logs)) {
                $form->addRow()->addContent($page->fetchFromTemplate('ui/discussion.twig.html', [
                    'discussion' => $logs
                ]));
            }

            //Allow entry of fresh followup
            $row = $form->addRow();
                $column = $row->addColumn();
                $column->addLabel('followUp', (empty($logs) ? __('Follow Up') : __('Further Follow Up')) .' / '.__('Notes'))->description(__('If you are the student\'s teacher, please include details such as: the location & lesson, what lead up to the incident, what was the incident, what did you do.'));
                $column->addTextArea('followUp')->setRows(8)->setClass('w-full');


            // CUSTOM FIELDS
            $container->get(CustomFieldHandler::class)->addCustomFieldsToForm($form, 'First Aid', [], $values['fields']);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
