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

use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$settingGateway = $container->get(SettingGateway::class);
$enableDescriptors = $settingGateway->getSettingByScope('Behaviour', 'enableDescriptors');
$enableLevels = $settingGateway->getSettingByScope('Behaviour', 'enableLevels');

if (isActionAccessible($guid, $connection2, '/modules/TawasulBehaviour/behaviour_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $page->breadcrumbs
        ->add(__('Manage Behaviour Records'), 'behaviour_manage.php')
        ->add(__('Add Multiple'));

    $tawasulBehaviourID = $_GET['tawasulBehaviourID'] ?? null;
    $tawasulMultiIncidentID = $_GET['tawasulMultiIncidentID'] ?? null;
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    $tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
    $tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';
    $type = $_GET['type'] ?? '';

    $settingGateway = $container->get(SettingGateway::class);

    $form = Form::create('addform', $session->get('absoluteURL').'/modules/TawasulBehaviour/behaviour_manage_addMultiProcess.php?tawasulPersonID='.$_GET['tawasulPersonID'].'&tawasulFormGroupID='.$_GET['tawasulFormGroupID'].'&tawasulYearGroupID='.$_GET['tawasulYearGroupID'].'&type='.$_GET['type']);
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', '/modules/TawasulBehaviour/behaviour_manage_addMulti.php');
    $form->addRow()->addHeading('Step 1', __('Step 1'));

    $policyLink = $settingGateway->getSettingByScope('Behaviour', 'policyLink');
    if (!empty($policyLink)) {
        $form->addHeaderAction('viewPolicy', __('View Behaviour Policy'))
            ->setExternalURL($policyLink)
            ->setIcon('document')
            ->displayLabel();
    }
    if (!empty($tawasulPersonID) or !empty($tawasulFormGroupID) or !empty($tawasulYearGroupID) or !empty($type)) {
        $form->addHeaderAction('back', __('Back to Search Results'))
            ->setURL('/modules/TawasulBehaviour/behaviour_manage.php')
            ->setIcon('search')
            ->displayLabel()
            ->addParam('tawasulPersonID', $_GET['tawasulPersonID'])
            ->addParam('tawasulFormGroupID', $_GET['tawasulFormGroupID'])
            ->addParam('tawasulYearGroupID', $_GET['tawasulYearGroupID'])
            ->addParam('type', $_GET['type']);
    }

    // Student
    $row = $form->addRow();
        $col = $row->addColumn();
            $col->addLabel('tawasulPersonIDMulti', __('Students'));

            $studentGateway = $container->get(StudentGateway::class);
            $studentCriteria = $studentGateway->newQueryCriteria()
                ->sortBy(['surname', 'preferredName']);

            $students = array_reduce($studentGateway->queryStudentsBySchoolYear($studentCriteria, $session->get('tawasulSchoolYearID'))->toArray(), function ($array, $student) {
                $array['students'][$student['tawasulPersonID']] = Format::name($student['title'], $student['preferredName'], $student['surname'], 'Student', true) . ' - ' . $student['formGroup'];
                $array['form'][$student['tawasulPersonID']] = $student['formGroup'];
                return $array;
            });

            $multiSelect = $col->addMultiSelect('tawasulPersonIDMulti')
                ->addSortableAttribute('Form', $students['form'])
                ->required();

            if (!empty($tawasulPersonID)) {
                $multiSelect->destination()->fromArray([$tawasulPersonID => $students['students'][$tawasulPersonID]]);
                unset($students['students'][$tawasulPersonID]);
            }

            $multiSelect->source()->fromArray($students['students']);
            

    // Date
    $row = $form->addRow();
        $row->addLabel('date', __('Date'));
        $row->addDate('date')->setValue(date($session->get('i18n')['dateFormatPHP']))->required();

    // Type
    $row = $form->addRow();
        $row->addLabel('type', __('Type'));
        $row->addSelectBehaviourType('type')->selected($type)->required();

    // Descriptor
    if ($enableDescriptors == 'Y') {
        $negativeDescriptors = $settingGateway->getSettingByScope('Behaviour', 'negativeDescriptors');
        $negativeDescriptors = (!empty($negativeDescriptors)) ? explode(',', $negativeDescriptors) : [];
        $positiveDescriptors = $settingGateway->getSettingByScope('Behaviour', 'positiveDescriptors');
        $positiveDescriptors = (!empty($positiveDescriptors)) ? explode(',', $positiveDescriptors) : [];
        $observationDescriptors = $settingGateway->getSettingByScope('Behaviour', 'observationDescriptors');
        $observationDescriptors = (!empty($observationDescriptors))? explode(',', $observationDescriptors) : [];

        $chainedToNegative = array_combine($negativeDescriptors, array_fill(0, count($negativeDescriptors), 'Negative'));
        $chainedToPositive = array_combine($positiveDescriptors, array_fill(0, count($positiveDescriptors), 'Positive'));
        $chainedToObservation = array_combine($observationDescriptors, array_fill(0, count($observationDescriptors), 'Observation'));
        $chainedTo = array_merge($chainedToNegative, $chainedToPositive, $chainedToObservation);

        $row = $form->addRow();
            $row->addLabel('descriptor', __('Descriptor'));
            $row->addSelect('descriptor')
                ->fromArray($positiveDescriptors)
                ->fromArray($negativeDescriptors)
                ->fromArray($observationDescriptors)
                ->chainedTo('type', $chainedTo)
                ->required()
                ->placeholder();
    }

    // Level
    if ($enableLevels == 'Y') {
        $optionsLevels = $settingGateway->getSettingByScope('Behaviour', 'levels');
        if ($optionsLevels != '') {
            $optionsLevels = explode(',', $optionsLevels);
        }
        $row = $form->addRow();
            $row->addLabel('level', __('Level'));
            $row->addSelect('level')
                ->fromArray($optionsLevels)
                ->placeholder();
    }

    $form->addRow()->addHeading('Details', __('Details'));

    // Incident
    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('comment', __('Incident'));
        $col->addTextArea('comment')
            ->setRows(5)
            ->setClass('w-full');

    // Follow Up
    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('followup', __('Follow Up'));
        $col->addTextArea('followUp')
            ->setRows(5)
            ->setClass('w-full');

    // CUSTOM FIELDS
    $container->get(CustomFieldHandler::class)->addCustomFieldsToForm($form, 'Behaviour', []);

    // Copy to Notes
    $row = $form->addRow();
        $row->addLabel('copyToNotes', __('Copy To Notes'));
        $row->addCheckbox('copyToNotes');

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
?>
