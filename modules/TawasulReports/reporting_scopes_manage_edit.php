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
use Tos\Module\TawasulReports\Domain\ReportingScopeGateway;
use TawasulOS\Tables\DataTable;
use TawasulOS\Forms\DatabaseFormFactory;
use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;
use Tos\Module\TawasulReports\Domain\ReportingCriteriaGateway;
use TawasulOS\Forms\Prefab\BulkActionForm;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_scopes_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $urlParams = [
        'tawasulReportingCycleID' => $_GET['tawasulReportingCycleID'] ?? '',
        'tawasulReportingScopeID' => $_GET['tawasulReportingScopeID'] ?? '',
        'tawasulYearGroupID'      => $_GET['tawasulYearGroupID'] ?? '',
        'tawasulFormGroupID'      => $_GET['tawasulFormGroupID'] ?? '',
        'tawasulCourseID'         => $_GET['tawasulCourseID'] ?? '',
    ];

    $page->breadcrumbs
        ->add(__('Manage Reporting Cycles'), 'reporting_cycles_manage.php')
        ->add(__('Reporting Scopes'), 'reporting_scopes_manage.php', ['tawasulReportingCycleID' => $urlParams['tawasulReportingCycleID']])
        ->add(__('Edit Scope'));

    if (empty($urlParams['tawasulReportingScopeID']) || empty($urlParams['tawasulReportingCycleID'])) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $reportingScopeGateway = $container->get(ReportingScopeGateway::class);
    $reportingCycleGateway = $container->get(ReportingCycleGateway::class);
    $reportingCriteriaGateway = $container->get(ReportingCriteriaGateway::class);

    $reportingScope = $reportingScopeGateway->getByID($urlParams['tawasulReportingScopeID']);
    if (empty($reportingScope)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $reportingCycle = $reportingCycleGateway->getByID($reportingScope['tawasulReportingCycleID']);
    if (empty($reportingCycle)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $form = Form::create('scopesManage', $session->get('absoluteURL').'/modules/TawasulReports/reporting_scopes_manage_editProcess.php');
    $form->setTitle($reportingCycle['name']);

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulReportingScopeID', $urlParams['tawasulReportingScopeID']);
    $form->addHiddenValue('tawasulReportingCycleID', $reportingScope['tawasulReportingCycleID']);

    $row = $form->addRow();
        $row->addLabel('name', __('Name'))->description(__('Must be unique'));
        $row->addTextField('name')->maxLength(90)->required();

    $row = $form->addRow();
        $row->addLabel('scopeType', __('Type'));
        $row->addTextField('scopeType')->readonly();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    $form->loadAllValuesFrom($reportingScope);

    echo $form->getOutput();

    // QUERY
    $criteria = $reportingCriteriaGateway->newQueryCriteria(true)
        ->sortBy(['nameShort', 'name'])
        ->fromPOST();

    $reportingCriteria = $reportingCriteriaGateway->queryReportingCriteriaGroupsByScope($criteria, $urlParams['tawasulReportingScopeID'], $reportingScope['scopeType']);

    // BULK ACTIONS
    $form = BulkActionForm::create('bulkAction', $session->get('absoluteURL').'/modules/TawasulReports/reporting_scopes_manage_editProcessBulk.php');
    $form->setTitle(__('Criteria'));
    $form->addHiddenValue('tawasulReportingScopeID', $urlParams['tawasulReportingScopeID']);
    $form->addHiddenValue('tawasulReportingCycleID', $reportingScope['tawasulReportingCycleID']);

    $bulkActions = array(
        'Add Multiple' => __('Add Multiple'),
        'Delete' => __('Delete'),
    );

    $col = $form->createBulkActionColumn($bulkActions);
    $col->addSubmit(__('Go'));

    // DATA TABLE
    $table = $form->addRow()->addDataTable('reportCriteriaManage', $criteria)->withData($reportingCriteria);
    $table->addMetaData('bulkActions', $col);

    $table->addColumn('nameShort', __('Short Name'));
    $table->addColumn('name', __('Name'));
    $table->addColumn('count', __('Criteria'));

    $table->addHeaderAction('addMulti', __('Add Multiple'))
        ->setIcon('page_new_multi')
        ->setURL('/modules/TawasulReports/reporting_criteria_manage_addMultiple.php')
        ->addParam('tawasulReportingCycleID', $urlParams['tawasulReportingCycleID'])
        ->addParam('tawasulReportingScopeID', $urlParams['tawasulReportingScopeID'])
        ->addParam('referer', 'scopes')
        ->displayLabel();

    $table->addHeaderAction('criteria', __('Manage Criteria Types'))
        ->setIcon('markbook')
        ->setURL('/modules/TawasulReports/criteriaTypes_manage.php')
        ->addParam('referer', 'scopes')
        ->displayLabel();

    $table->addActionColumn()
        ->addParam('tawasulReportingCycleID', $urlParams['tawasulReportingCycleID'])
        ->addParam('tawasulReportingScopeID', $urlParams['tawasulReportingScopeID'])
        ->addParam('tawasulYearGroupID')
        ->addParam('tawasulFormGroupID')
        ->addParam('tawasulCourseID')
        ->addParam('tawasulReportingCriteriaID')
        ->addParam('referer', 'scopes')
        ->format(function ($reportingCriteria, $actions) {
            $actions->addAction('view', __('View'))
                    ->setURL('/modules/TawasulReports/reporting_criteria_manage.php');
        });

    $table->addCheckboxColumn('scopeTypeID');

    echo $form->getOutput();
}
