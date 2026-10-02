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

use TawasulOS\Domain\School\GradeScaleGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Forms\DatabaseFormFactory;
use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;
use Tos\Module\TawasulReports\Domain\ReportingCriteriaGateway;
use Tos\Module\TawasulReports\Domain\ReportingCriteriaTypeGateway;
use Tos\Module\TawasulReports\Domain\ReportingValueGateway;
use Tos\Module\TawasulReports\Domain\ReportingScopeGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_criteria_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $tawasulReportingCycleID = $_GET['tawasulReportingCycleID'] ?? '';
    $tawasulReportingScopeID = $_GET['tawasulReportingScopeID'] ?? '';
    $tawasulReportingCriteriaID = $_GET['tawasulReportingCriteriaID'] ?? '';

    $page->breadcrumbs
        ->add(__('Manage Criteria'), 'reporting_criteria_manage.php', ['tawasulReportingCycleID' => $tawasulReportingCycleID, 'tawasulReportingScopeID' => $tawasulReportingScopeID])
        ->add(__('Edit Criteria'));

    if (empty($tawasulReportingCriteriaID) || empty($tawasulReportingScopeID) || empty($tawasulReportingCycleID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $reportingCycle = $container->get(ReportingCycleGateway::class)->getByID($tawasulReportingCycleID);
    $reportingScope = $container->get(ReportingScopeGateway::class)->getByID($tawasulReportingScopeID);
    if (empty($reportingCycle) || empty($reportingScope)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $reportingCriteriaGateway = $container->get(ReportingCriteriaGateway::class);
 
    $values = $reportingCriteriaGateway->getByID($tawasulReportingCriteriaID);
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    if (!empty($values['groupID'])) {
        $groupCount = $reportingCriteriaGateway->selectBy(['tawasulReportingCycleID' => $tawasulReportingCycleID, 'groupID' => $values['groupID']])->rowCount();
        echo Format::alert(__('This is a grouped record created using Add Multiple.').' '.__('Editing this record will update all {count} records in the same group. Check the detach option to remove this record from the group and not update other records.', ['count' => '<b>'.$groupCount.'</b>']), 'error');
    }

    $criteriaInUse = $container->get(ReportingValueGateway::class)->selectBy(['tawasulReportingCriteriaID' => $tawasulReportingCriteriaID])->rowCount();
    if ($criteriaInUse > 0) {
        echo Format::alert(__('This criteria is already in use in {count} locations. It cannot be deleted or changed to a different criteria type.', ['count' => '<b>'.$criteriaInUse.'</b>']), 'warning');
    }

    $form = Form::create('reportCriteriaManage', $session->get('absoluteURL').'/modules/TawasulReports/reporting_criteria_manage_editProcess.php');
    
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulReportingCriteriaID', $tawasulReportingCriteriaID);
    $form->addHiddenValue('tawasulReportingScopeID', $tawasulReportingScopeID);
    $form->addHiddenValue('tawasulReportingCycleID', $tawasulReportingCycleID);
    $form->addHiddenValue('tawasulYearGroupID', $values['tawasulYearGroupID']);
    $form->addHiddenValue('tawasulFormGroupID', $values['tawasulFormGroupID']);
    $form->addHiddenValue('tawasulCourseID', $values['tawasulCourseID']);
    $form->addHiddenValue('groupID', $values['groupID']);

    $row = $form->addRow();
        $row->addLabel('reportingCycle', __('Reporting Cycle'));
        $row->addTextField('reportingCycle')->readonly()->setValue($reportingCycle['name']);

    $row = $form->addRow();
        $row->addLabel('reportingScope', __('Scope'));
        $row->addTextField('reportingScope')->readonly()->setValue($reportingScope['name']);

    $row = $form->addRow();
        $row->addLabel('name', __('Name'));
        $row->addTextField('name')->maxLength(255)->required();

    $row = $form->addRow();
        $row->addLabel('description', __('Description'));
        $row->addTextArea('description')->setRows(2);

    $row = $form->addRow();
        $row->addLabel('category', __('Category'))->description(__('Optionally used to group criteria together.'));
        $row->addTextField('category')->maxLength(255);

    if ($criteriaInUse == 0) {
        $criteriaTypes = $container->get(ReportingCriteriaTypeGateway::class)->selectActiveCriteriaTypes();
        $row = $form->addRow();
            $row->addLabel('tawasulReportingCriteriaTypeID', __('Type'));
            $row->addSearchSelect('tawasulReportingCriteriaTypeID')->fromResults($criteriaTypes)->required()->placeholder();

        $targets = ['Per Student' => __('Per Student'), 'Per Group' => __('Per Group')];
        $row = $form->addRow();
            $row->addLabel('target', __('Target'));
            $row->addSelect('target')->fromArray($targets)->required()->placeholder();
    } else {
        $form->addHiddenValue('tawasulReportingCriteriaTypeID', $values['tawasulReportingCriteriaTypeID']);
        $form->addHiddenValue('target', $values['target']);

        $criteriaType = $container->get(ReportingCriteriaTypeGateway::class)->getByID($values['tawasulReportingCriteriaTypeID']);
        $row = $form->addRow();
            $row->addLabel('criteriaType', __('Type'));
            $row->addTextField('criteriaType')->readonly()->setValue($criteriaType['name'] ?? '');

        $row = $form->addRow();
            $row->addLabel('target', __('Target'));
            $row->addTextField('target')->readonly()->setValue(__($values['target']));
    }

    if (!empty($values['groupID'])) {
        $row = $form->addRow();
            $row->addLabel('detach', __('Detach?'))->description(__('Removes this record from a grouped set.'));
            $row->addCheckbox('detach')->setValue('Y');
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    $form->loadAllValuesFrom($values);

    echo $form->getOutput();
}
