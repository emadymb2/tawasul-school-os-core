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

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_criteria_manage.php') == false) {
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

    $page->breadcrumbs->add(__('Manage Criteria'));

    $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');
    $reportingScopeGateway = $container->get(ReportingScopeGateway::class);
    $reportingCycleGateway = $container->get(ReportingCycleGateway::class);
    $reportingCriteriaGateway = $container->get(ReportingCriteriaGateway::class);

    $reportingCycles = $reportingCycleGateway->selectReportingCyclesBySchoolYear($tawasulSchoolYearID)->fetchKeyPair();

    if (empty($reportingCycles)) {
        $page->addMessage(__('There are no active reporting cycles.'));
        return;
    }

    // FORM
    $form = Form::create('archiveByReport', $session->get('absoluteURL').'/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder w-full');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('q', '/modules/TawasulReports/reporting_criteria_manage.php');
    $form->addHiddenValue('tawasulReportingScopeID', $urlParams['tawasulReportingScopeID']);
    $form->addHiddenValue('tawasulReportingCycleID', $urlParams['tawasulReportingCycleID']);

    $row = $form->addRow();
        $row->addLabel('tawasulReportingCycleID', __('Reporting Cycle'));
        $row->addSelect('tawasulReportingCycleID')
            ->fromArray($reportingCycles)
            ->selected($urlParams['tawasulReportingCycleID'])
            ->placeholder()
            ->required();

    $reportingScopes = $reportingScopeGateway->selectReportingScopesBySchoolYear($tawasulSchoolYearID)->fetchAll();
    $scopesChained = array_combine(array_column($reportingScopes, 'value'), array_column($reportingScopes, 'chained'));
    $scopesOptions = array_combine(array_column($reportingScopes, 'value'), array_column($reportingScopes, 'name'));
    
    $row = $form->addRow();
        $row->addLabel('tawasulReportingScopeID', __('Scope'));
        $row->addSelect('tawasulReportingScopeID')
            ->fromArray($scopesOptions)
            ->selected($urlParams['tawasulReportingScopeID'])
            ->chainedTo('tawasulReportingCycleID', $scopesChained)
            ->placeholder();

    if (!empty($urlParams['tawasulReportingScopeID']) && !empty($urlParams['tawasulReportingCycleID'])) {
        $reportingScope = $reportingScopeGateway->getByID($urlParams['tawasulReportingScopeID']);
        $reportingCycle = $reportingCycleGateway->getByID($reportingScope['tawasulReportingCycleID']);
        if (empty($reportingScope) || empty($reportingCycle)) {
            $page->addError(__('The specified record cannot be found.'));
            return;
        }

        if ($reportingScope['scopeType'] == 'Year Group') {
            $scopeTypeID = $urlParams['tawasulYearGroupID'];
            $row = $form->addRow();
                $row->addLabel('tawasulYearGroupID', __('Year Group'));
                $row->addSelectYearGroup('tawasulYearGroupID')
                    ->selected($urlParams['tawasulYearGroupID']);
        }

        if ($reportingScope['scopeType'] == 'Form Group') {
            $scopeTypeID = $urlParams['tawasulFormGroupID'];
            $row = $form->addRow();
                $row->addLabel('tawasulFormGroupID', __('Form Group'));
                $row->addSelectFormGroup('tawasulFormGroupID', $reportingCycle['tawasulSchoolYearID'])
                    ->selected($urlParams['tawasulFormGroupID']);
        }

        if ($reportingScope['scopeType'] == 'Course') {
            $scopeTypeID = $urlParams['tawasulCourseID'];
            $row = $form->addRow();
                $row->addLabel('tawasulCourseID', __('Course'));
                $row->addSelectCourseByYearGroup('tawasulCourseID', $reportingCycle['tawasulSchoolYearID'], $reportingCycle['tawasulYearGroupIDList'])
                    ->selected($urlParams['tawasulCourseID']);
        }
    }

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    if (empty($urlParams['tawasulReportingCycleID'])) {
        return;
    }

    if (empty($urlParams['tawasulReportingScopeID'])) {
        $table = DataTable::create('reportScopes')->setTitle(__('Criteria'));
        $table->addHeaderAction('scopes', __('Manage Scopes & Criteria'))
            ->setURL('/modules/TawasulReports/reporting_scopes_manage.php')
            ->addParam('tawasulReportingCycleID', $urlParams['tawasulReportingCycleID'])
            ->setIcon('markbook')
            ->displayLabel();

        echo $table->render([]);
        return;
    }

    // QUERY
    $criteria = $reportingCriteriaGateway->newQueryCriteria(true)
        ->sortBy(['scopeSequence', 'tawasulReportingCriteria.sequenceNumber'])
        ->fromPOST();

    $reportingCriteria = $reportingCriteriaGateway->queryReportingCriteriaByScope($criteria, $urlParams['tawasulReportingScopeID'], $reportingScope['scopeType'], $scopeTypeID);

    // DATA TABLE
    $table = empty($scopeTypeID) ? DataTable::createPaginated('reportCriteriaManage', $criteria) : DataTable::create('reportCriteriaManage');
    $table->setTitle(__('Criteria'));

    if (empty($urlParams['tawasulYearGroupID']) && empty($urlParams['tawasulFormGroupID']) && empty($urlParams['tawasulCourseID'])) {
        $table->addHeaderAction('addMulti', __('Add Multiple'))
            ->setIcon('page_new_multi')
            ->setURL('/modules/TawasulReports/reporting_criteria_manage_addMultiple.php')
            ->addParam('tawasulReportingCycleID', $urlParams['tawasulReportingCycleID'])
            ->addParam('tawasulReportingScopeID', $urlParams['tawasulReportingScopeID'])
            ->displayLabel();
    } else {
        $table->addHeaderAction('add', __('Add'))
            ->setURL('/modules/TawasulReports/reporting_criteria_manage_add.php')
            ->addParam('tawasulReportingCycleID', $urlParams['tawasulReportingCycleID'])
            ->addParam('tawasulReportingScopeID', $urlParams['tawasulReportingScopeID'])
            ->addParam('tawasulYearGroupID', $urlParams['tawasulYearGroupID'])
            ->addParam('tawasulFormGroupID', $urlParams['tawasulFormGroupID'])
            ->addParam('tawasulCourseID', $urlParams['tawasulCourseID'])
            ->displayLabel();
    }

    $table->addHeaderAction('criteria', __('Manage Criteria Types'))
            ->setIcon('markbook')
            ->setURL('/modules/TawasulReports/criteriaTypes_manage.php')
            ->displayLabel();

    if (empty($scopeTypeID)) {
        $table->addColumn('scopeTypeName', $reportingScope['scopeType'])
            ->format(function ($reportingCriteria) use (&$urlParams) {
                $url = './index.php?q=/modules/TawasulReports/reporting_criteria_manage.php&'.http_build_query([
                    'tawasulYearGroupID' => $reportingCriteria['tawasulYearGroupID'],
                    'tawasulFormGroupID' => $reportingCriteria['tawasulFormGroupID'],
                    'tawasulCourseID' => $reportingCriteria['tawasulCourseID']
                    ] + $urlParams);
                return Format::link($url, $reportingCriteria['scopeTypeName']);
            });
    } else {
        $table->addDraggableColumn('tawasulReportingCriteriaID', $session->get('absoluteURL').'/modules/TawasulReports/reporting_criteria_manage_editOrderAjax.php', ['tawasulReportingCycleID' => $urlParams['tawasulReportingCycleID'], 'tawasulReportingScopeID' => $urlParams['tawasulReportingScopeID']]);
    }

    $table->addColumn('name', __('Criteria'));
    $table->addColumn('criteriaType', __('Type'))
        ->description(__('Category'))
        ->formatDetails(function ($row) {
            return Format::small($row['category'] ?? '');
        });
    $table->addColumn('target', __('Target'));
    $table->addColumn('values', __('Status'))
        ->format(function ($reportingCriteria) {
            return ($reportingCriteria['values'] > 0)
                ? '<span class="tag warning" title="'.__('This criteria is already in use in {count} locations. It cannot be deleted or changed to a different criteria type.', ['count' => $reportingCriteria['values']]).'">'.__('Locked').'</span>'
                : '<span class="tag dull" title="'.__('This criteria has not been used yet. It can safely be edited or deleted.').'">'.__('Unlocked').'</span>';
        });

    $table->addActionColumn()
        ->addParam('tawasulReportingCycleID', $urlParams['tawasulReportingCycleID'])
        ->addParam('tawasulReportingScopeID', $urlParams['tawasulReportingScopeID'])
        ->addParam('tawasulYearGroupID', $urlParams['tawasulYearGroupID'])
        ->addParam('tawasulFormGroupID', $urlParams['tawasulFormGroupID'])
        ->addParam('tawasulCourseID', $urlParams['tawasulCourseID'])
        ->addParam('tawasulReportingCriteriaID')
        ->format(function ($reportingCriteria, $actions) {
            $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulReports/reporting_criteria_manage_edit.php');

            if ($reportingCriteria['values'] <= 0) {
                $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/TawasulReports/reporting_criteria_manage_delete.php');
            }
        });
    

    echo $table->render($reportingCriteria);
}
