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
use TawasulOS\Forms\CustomFieldHandler;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulDataUpdater/data_medical_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');

    $urlParams = ['tawasulSchoolYearID' => $tawasulSchoolYearID];

    $page->breadcrumbs
        ->add(__('Medical Data Updates'), 'data_medical_manage.php', $urlParams)
        ->add(__('Edit Request'));

    //Check if tawasulPersonMedicalUpdateID specified
    $tawasulPersonMedicalUpdateID = $_GET['tawasulPersonMedicalUpdateID'] ?? '';
    if ($tawasulPersonMedicalUpdateID == 'Y') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID);
            $sql = "SELECT tawasulPersonMedical.* FROM tawasulPersonMedicalUpdate
                    LEFT JOIN tawasulPersonMedical ON (tawasulPersonMedical.tawasulPersonID=tawasulPersonMedicalUpdate.tawasulPersonID)
                    WHERE tawasulPersonMedicalUpdateID=:tawasulPersonMedicalUpdateID";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record does not exist.'));
        } else {
            $data = array('tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID);
            $sql = "SELECT tawasulPersonMedicalUpdate.* FROM tawasulPersonMedicalUpdate
                    LEFT JOIN tawasulPersonMedical ON (tawasulPersonMedical.tawasulPersonID=tawasulPersonMedicalUpdate.tawasulPersonID)
                    WHERE tawasulPersonMedicalUpdateID=:tawasulPersonMedicalUpdateID";
            $newResult = $pdo->executeQuery($data, $sql);

            //Let's go!
            $oldValues = $result->fetch();
            $newValues = $newResult->fetch();

            // Provide a link back to edit the associated record
            if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/medicalForm_manage_edit.php') == true && !empty($oldValues['tawasulPersonMedicalID'])) {
                $params = [ 
                    'tawasulPersonMedicalID' => $oldValues['tawasulPersonMedicalID']
                ];
                $page->navigator->addHeaderAction('edit', __('Edit Medical Form'))
                    ->setURL('/modules/TawasulStudents/medicalForm_manage_edit.php')
                    ->addParams($params)
                    ->setIcon('config')
                    ->displayLabel();
            }

            $compare = array(
                'longTermMedication'        => __('Long-Term Medication?'),
                'longTermMedicationDetails' => __('Medication Details'),
                'comment'      => __('Comment'),
            );

            $compareCondition = array(
                'name'                 => __('Condition Name'),
                'tawasulAlertLevelID'   => __('Risk'),
                'triggers'             => __('Triggers'),
                'reaction'             => __('Reaction'),
                'response'             => __('Response'),
                'medication'           => __('Medication'),
                'lastEpisode'          => __('Last Episode Date'),
                'lastEpisodeTreatment' => __('Last Episode Treatment'),
                'comment'              => __('Comment'),
                'attachment'           => __('Attachment'),
            );

            $sql = "SELECT tawasulMedicalConditionID AS value, name FROM tawasulMedicalCondition ORDER BY name";
            $result = $pdo->executeQuery(array(), $sql);
            $conditions = ($result->rowCount() > 0)? $result->fetchAll(\PDO::FETCH_KEY_PAIR) : array();

            $sql = "SELECT tawasulAlertLevelID AS value, name FROM tawasulAlertLevel ORDER BY sequenceNumber";
            $result = $pdo->executeQuery(array(), $sql);
            $alerts = ($result->rowCount() > 0)? $result->fetchAll(\PDO::FETCH_KEY_PAIR) : array();

            $form = Form::createTable('updateMedical', $session->get('absoluteURL').'/modules/'.$session->get('module').'/data_medical_manage_editProcess.php?tawasulPersonMedicalUpdateID='.$tawasulPersonMedicalUpdateID);

            $form->setClass('w-full colorOddEven');
            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulPersonID', $newValues['tawasulPersonID']);
            $form->addHiddenValue('formExists', !empty($oldValues['tawasulPersonMedicalID']));

            $row = $form->addRow()->setClass('head bg-gray-200');
                $row->addContent(__('Field'));
                $row->addContent(__('Current Value'));
                $row->addContent(__('New Value'));
                $row->addContent(__('Accept'));

            $changeCount = 0;

            // Create a reusable function for adding comparisons to the form
            $comparisonFields = function ($form, $oldValues, $newValues, $fieldName, $label, $count = '') use ($conditions, $alerts, &$changeCount) {
                $oldValue = isset($oldValues[$fieldName])? $oldValues[$fieldName] : '';
                $newValue = isset($newValues[$fieldName])? $newValues[$fieldName] : '';
                $isNotMatching = ($oldValue != $newValue);

                if ($fieldName == 'name') {
                    $oldValue = isset($conditions[$oldValue])? $conditions[$oldValue] : $oldValue;
                    $newValue = isset($conditions[$newValue])? $conditions[$newValue] : $newValue;
                }

                if ($fieldName == 'tawasulAlertLevelID') {
                    $oldValue = isset($alerts[$oldValue])? $alerts[$oldValue] : $oldValue;
                    $newValue = isset($alerts[$newValue])? $alerts[$newValue] : $newValue;
                }

                if ($fieldName == 'lastEpisode') {
                    $oldValue = Format::date($oldValue);
                    $newValue = Format::date($newValue);
                }

                if ($fieldName == 'attachment') {
                    $oldValue = !empty($oldValue) ? Format::link('./'.$oldValue, $oldValue, ['target' => '_blank']) : '';
                    $newValue = !empty($newValue) ? Format::link('./'.$newValue, $newValue, ['target' => '_blank']) : '';
                }

                $row = $form->addRow();
                $row->addLabel($fieldName.'On'.$count, $label);
                $row->addContent($oldValue);
                $row->addContent($newValue)->addClass($isNotMatching ? 'matchHighlightText' : '');

                if ($isNotMatching) {
                    $row->addCheckbox($fieldName.'On'.$count)->checked(true)->setClass('textCenter');
                    $form->addHiddenValue($fieldName.$count, $newValues[$fieldName]);
                    $changeCount++;
                } else {
                    $row->addContent();
                }
            };

            // Basic Medical Form
            $form->addRow()->addHeading('Basic Information', __('Basic Information'));

            foreach ($compare as $fieldName => $label) {
                $comparisonFields($form, $oldValues, $newValues, $fieldName, $label);
            }

            // CUSTOM FIELDS
            $container->get(CustomFieldHandler::class)->addCustomFieldsToDataUpdate($form, 'Medical Form', ['dataUpdater' => 1], $oldValues, $newValues);

            // Existing Conditions
            $data = array('tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID);
            $sql = "SELECT * FROM tawasulPersonMedicalConditionUpdate
                    WHERE tawasulPersonMedicalUpdateID=:tawasulPersonMedicalUpdateID
                    AND NOT tawasulPersonMedicalConditionID IS NULL
                    ORDER BY tawasulPersonMedicalConditionUpdateID";
            $result = $pdo->executeQuery($data, $sql);

            $count = 0;
            if ($result->rowCount() > 0) {
                while ($newValues = $result->fetch()) {
                    $data = array('tawasulPersonMedicalConditionID' => $newValues['tawasulPersonMedicalConditionID']);
                    $sql = "SELECT * FROM tawasulPersonMedicalCondition
                            WHERE tawasulPersonMedicalConditionID=:tawasulPersonMedicalConditionID";
                    $oldResult = $pdo->executeQuery($data, $sql);
                    $oldValues = $oldResult->fetch();

                    $form->addRow()->addHeading(__('Existing Condition').' '.($count+1));
                    $form->addHiddenValue('tawasulPersonMedicalConditionID'.$count, $newValues['tawasulPersonMedicalConditionID']);

                    foreach ($compareCondition as $fieldName => $label) {
                        $comparisonFields($form, $oldValues, $newValues, $fieldName, $label, $count);
                    }

                    $count++;
                }
            }

            // New Conditions
            $data = array('tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID);
            $sql = "SELECT * FROM tawasulPersonMedicalConditionUpdate
                    WHERE tawasulPersonMedicalUpdateID=:tawasulPersonMedicalUpdateID
                    AND tawasulPersonMedicalConditionID IS NULL ORDER BY name";
            $result = $pdo->executeQuery($data, $sql);

            $count2 = 0;
            if ($result->rowCount() > 0) {
                while ($newValues = $result->fetch()) {
                    $count2++;

                    $form->addRow()->addHeading(__('New Condition').' '.$count2);
                    $form->addHiddenValue('tawasulPersonMedicalConditionUpdateID'.($count+$count2), $newValues['tawasulPersonMedicalConditionUpdateID']);

                    foreach ($compareCondition as $fieldName => $label) {
                        $comparisonFields($form, array(), $newValues, $fieldName, $label, $count+$count2);
                    }
                }
            }

            $form->addHiddenValue('count', $count);
            $form->addHiddenValue('count2', $count2);

            $row = $form->addRow();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
