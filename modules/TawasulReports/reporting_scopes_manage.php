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

use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use Tos\Module\TawasulReports\Domain\ReportingScopeGateway;
use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_scopes_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage Reporting Cycles'), 'reporting_cycles_manage.php')
        ->add(__('Reporting Scopes'));

    $tawasulReportingCycleID = $_GET['tawasulReportingCycleID'] ?? '';
    if (empty($tawasulReportingCycleID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $reportingCycle = $container->get(ReportingCycleGateway::class)->getByID($tawasulReportingCycleID);
    if (empty($reportingCycle)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $reportingScopeGateway = $container->get(ReportingScopeGateway::class);

    // QUERY
    $criteria = $reportingScopeGateway->newQueryCriteria(true)
        ->sortBy('sequenceNumber')
        ->fromPOST();

    $scopes = $reportingScopeGateway->queryReportingScopesByCycle($criteria, $tawasulReportingCycleID);

    // GRID TABLE
    $table = DataTable::create('reportScopesManage');
    $table->setTitle($reportingCycle['name']);

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/TawasulReports/reporting_scopes_manage_add.php')
        ->addParam('tawasulReportingCycleID', $tawasulReportingCycleID)
        ->displayLabel();

    $table->addDraggableColumn('tawasulReportingScopeID', $session->get('absoluteURL').'/modules/TawasulReports/reporting_scopes_manage_editOrderAjax.php', ['tawasulReportingCycleID' => $tawasulReportingCycleID]);

    $table->addColumn('name', __('Name'));
    $table->addColumn('scopeType', __('Type'));
    $table->addColumn('accessRoles', __('Access'));

    $table->addActionColumn()
        ->addParam('tawasulReportingCycleID', $tawasulReportingCycleID)
        ->addParam('tawasulReportingScopeID')
        ->format(function ($reportingScope, $actions) {
            $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulReports/reporting_scopes_manage_edit.php');

            $actions->addAction('access', __('Manage Access'))
                    ->setURL('/modules/TawasulReports/reporting_access_manage.php');

            $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/TawasulReports/reporting_scopes_manage_delete.php');
        });

    echo $table->render($scopes);
}
