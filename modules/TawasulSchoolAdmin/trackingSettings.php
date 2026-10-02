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
use TawasulOS\Forms\Form;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/trackingSettings.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Tracking Settings'));

    $form = Form::create('trackingSettings', $session->get('absoluteURL').'/modules/'.$session->get('module').'/trackingSettingsProcess.php');

    $form->removeClass('standardForm');
    $form->addHiddenValue('address', $session->get('address'));

    // Get the yearGroups in a $key => $value array
    $sql = "SELECT tawasulYearGroupID as `value`, name FROM tawasulYearGroup ORDER BY sequenceNumber";
    $result = $pdo->executeQuery(array(), $sql);
    $yearGroups = $result->fetchAll(\PDO::FETCH_KEY_PAIR);

    if (empty($yearGroups)) {
        $form->addRow()->addAlert(__('There are no records to display.'), 'error');
    } else {
        // EXTERNAL ASSESSMENT DATA POINTS
        $row = $form->addRow();
            $row->addHeading(__('Data Points').' - '.__('External Assessment'))
                ->append(__('Use the options below to select the external assessments that you wish to include in your Data Points export.'))
                ->append(' ')
                ->append(__('If duplicates of any assessment exist, only the most recent entry will be shown.'));

        // Get the existing External Assesment IDs and categories
        $sql = "SELECT DISTINCT tawasulExternalAssessment.tawasulExternalAssessmentID, tawasulExternalAssessment.nameShort, tawasulExternalAssessmentField.category FROM tawasulExternalAssessment JOIN tawasulExternalAssessmentField ON (tawasulExternalAssessmentField.tawasulExternalAssessmentID=tawasulExternalAssessment.tawasulExternalAssessmentID) WHERE active='Y' ORDER BY nameShort, category";
        $result = $pdo->executeQuery(array(), $sql);

        $settingGateway = $container->get(SettingGateway::class);

        if ($result->rowCount() < 1) {
            $form->addRow()->addAlert(__('There are no records to display.'), 'error');
        } else {
            // Get the external data points from Settings, if any exist
            $externalAssessmentDataPoints = unserialize($settingGateway->getSettingByScope('Tracking', 'externalAssessmentDataPoints'));
            $externalAssessmentDataPoints = is_array($externalAssessmentDataPoints) ? $externalAssessmentDataPoints : array() ;

            // Create a lookup table for data points as tawasulExternalAssessmentID-category pair
            $externalDP = array();
            foreach ($externalAssessmentDataPoints as $dp) {
                $key = $dp['tawasulExternalAssessmentID'].'-'.$dp['category'];
                $externalDP[$key] = (isset($dp['tawasulYearGroupIDList']))? $dp['tawasulYearGroupIDList'] : '';
            }

            $count = 0;
            while ($assessment = $result->fetch()) {
                $name = 'externalDP['.$count.'][tawasulYearGroupIDList][]';
                $categoryLabel = substr($assessment['category'], (strpos($assessment['category'], '_') + 1));
                $key = $assessment['tawasulExternalAssessmentID'].'-'.$assessment['category'];

                $checked = array();
                if (isset($externalDP[$key])) {
                    // Explode the saved CSV data into an array
                    $checked = explode(',', $externalDP[$key]) ;
                }

                // Add the checkbox group for this tawasulExternalAssessmentID-category pair
                $row = $form->addRow();
                    $row->addLabel($name, __($assessment['nameShort']).' - '.__($categoryLabel));
                    $row->addCheckbox($name)->fromArray($yearGroups)->checked($checked);

                $form->addHiddenValue('externalDP['.$count.'][tawasulExternalAssessmentID]', $assessment['tawasulExternalAssessmentID']);
                $form->addHiddenValue('externalDP['.$count.'][category]', $assessment['category']);

                $count++;
            }
        }

        // INTERNAL ASSESSMENT DATA POINTS
        $row = $form->addRow();
            $row->addHeading(__('Data Points').' - '.__('Internal Assessment'))
                ->append(__('Use the options below to select the internal assessments that you wish to include in your Data Points export.'))
                ->append(' ')
                ->append(__('If duplicates of any assessment exist, only the most recent entry will be shown.'));

        $internalAssessmentTypes = explode(',', $settingGateway->getSettingByScope('Formal Assessment', 'internalAssessmentTypes'));

        if (empty($internalAssessmentTypes)) {
            $form->addRow()->addAlert(__('There are no records to display.'), 'error');
        } else {
            // Get the internal data points from Settings, if any exist
            $internalAssessmentDataPoints = unserialize($settingGateway->getSettingByScope('Tracking', 'internalAssessmentDataPoints'));
            $internalAssessmentDataPoints = is_array($internalAssessmentDataPoints) ? $internalAssessmentDataPoints : array() ;

            // Create a lookup table for data points (CSV index order can change)
            $internalDP = array();
            foreach ($internalAssessmentDataPoints as $dp) {
                $internalDP[$dp['type']] = (isset($dp['tawasulYearGroupIDList']))? $dp['tawasulYearGroupIDList'] : '';
            }

            $count = 0;
            foreach ($internalAssessmentTypes as $internalAssessmentType) {
                $name = 'internalDP['.$count.'][tawasulYearGroupIDList][]';
                $checked = array();
                if (isset($internalDP[$internalAssessmentType])) {
                    // Explode the saved CSV data into an array
                    $checked = explode(',', $internalDP[$internalAssessmentType]);
                }

                // Add the checkbox group for this type
                $row = $form->addRow();
                    $row->addLabel($name, __($internalAssessmentType));
                    $row->addCheckbox($name)->fromArray($yearGroups)->checked($checked);

                $form->addHiddenValue('internalDP['.$count.'][type]', $internalAssessmentType);

                $count++;
            }
        }
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
