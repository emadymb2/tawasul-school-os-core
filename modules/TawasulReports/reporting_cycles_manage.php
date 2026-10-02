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
use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_cycles_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Reporting Cycles'));

    $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');
    $reportingCycleGateway = $container->get(ReportingCycleGateway::class);

    // QUERY
    $criteria = $reportingCycleGateway->newQueryCriteria(true)
        ->sortBy('sequenceNumber')
        ->fromPOST();

    $reports = $reportingCycleGateway->queryReportingCyclesBySchoolYear($criteria, $tawasulSchoolYearID);

    // GRID TABLE
    $table = DataTable::create('reportsManage');
    $table->setTitle(__('View'));

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/TawasulReports/reporting_cycles_manage_add.php')
        ->displayLabel();

    $table->addDraggableColumn('tawasulReportingCycleID', $session->get('absoluteURL').'/modules/TawasulReports/reporting_cycles_manage_editOrderAjax.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);

    $table->addColumn('name', __('Name'))->width('30%');
    $table->addColumn('cycleNumber', __('Cycle'))->width('8%');
    $table->addColumn('yearGroups', __('Year Groups'))->width('15%');
    $table->addColumn('dateStart', __('Start Date'))->format(Format::using('dateReadable', 'dateStart'))->width('15%');
    $table->addColumn('dateEnd', __('End Date'))->format(Format::using('dateReadable', 'dateEnd'))->width('15%');

    $table->addActionColumn()
        ->addParam('tawasulReportingCycleID')
        ->format(function ($reportingCycle, $actions) {
            $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulReports/reporting_cycles_manage_edit.php');

            $actions->addAction('scopes', __('Manage Scopes & Criteria'))
                    ->setIcon('markbook')
                    ->setClass('mx-1')
                    ->setURL('/modules/TawasulReports/reporting_scopes_manage.php');

            $actions->addAction('duplicate', __('Duplicate'))
                    ->setIcon('copy')
                    ->setURL('/modules/TawasulReports/reporting_cycles_manage_duplicate.php');

            $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/TawasulReports/reporting_cycles_manage_delete.php');
        });

    echo $table->render($reports);
}
