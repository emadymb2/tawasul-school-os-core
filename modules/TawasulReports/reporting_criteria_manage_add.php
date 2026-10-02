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

use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Forms\Form;
use Tos\Module\TawasulReports\Domain\ReportingCriteriaTypeGateway;
use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;
use Tos\Module\TawasulReports\Domain\ReportingScopeGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_criteria_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $tawasulReportingCycleID = $_GET['tawasulReportingCycleID'] ?? '';
    $tawasulReportingScopeID = $_GET['tawasulReportingScopeID'] ?? '';

    $page->breadcrumbs
        ->add(__('Manage Criteria'), 'reporting_criteria_manage.php', ['tawasulReportingCycleID' => $tawasulReportingCycleID, 'tawasulReportingScopeID' => $tawasulReportingScopeID])
        ->add(__('Add Criteria'));

    if (empty($tawasulReportingCycleID) || empty($tawasulReportingScopeID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $reportingCycle = $container->get(ReportingCycleGateway::class)->getByID($tawasulReportingCycleID);
    $reportingScope = $container->get(ReportingScopeGateway::class)->getByID($tawasulReportingScopeID);
    if (empty($reportingCycle) || empty($reportingScope)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $editLink = '';
    if (isset($_GET['editID'])) {
        $editLink = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reporting_criteria_manage_edit.php&tawasulReportingCycleID='.$tawasulReportingCycleID.'&tawasulReportingScopeID='.$tawasulReportingScopeID.'&tawasulReportingCriteriaID='.$_GET['editID'];
    }

    $page->return->setEditLink($editLink);

    $form = Form::create('reportCriteriaManage', $session->get('absoluteURL').'/modules/TawasulReports/reporting_criteria_manage_addProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulReportingCycleID', $tawasulReportingCycleID);
    $form->addHiddenValue('tawasulReportingScopeID', $tawasulReportingScopeID);
    $form->addHiddenValue('tawasulYearGroupID', $_GET['tawasulYearGroupID'] ?? '');
    $form->addHiddenValue('tawasulFormGroupID', $_GET['tawasulFormGroupID'] ?? '');
    $form->addHiddenValue('tawasulCourseID', $_GET['tawasulCourseID'] ?? '');
    $form->addHiddenValue('scopeType', $reportingScope['scopeType']);

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
        
    $criteriaTypes = $container->get(ReportingCriteriaTypeGateway::class)->selectActiveCriteriaTypes();
    $row = $form->addRow();
        $row->addLabel('tawasulReportingCriteriaTypeID', __('Type'));
        $row->addSearchSelect('tawasulReportingCriteriaTypeID')->fromResults($criteriaTypes)->required()->placeholder();

    $targets = ['Per Student' => __('Per Student'), 'Per Group' => __('Per Group')];
    $row = $form->addRow();
        $row->addLabel('target', __('Target'));
        $row->addSelect('target')->fromArray($targets)->required()->placeholder();
 
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
