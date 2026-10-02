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
use Tos\Module\TawasulReports\Domain\ReportGateway;
use TawasulOS\Domain\School\SchoolYearGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reports_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Reports'));

    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');

    // School Year Picker
    if (!empty($tawasulSchoolYearID)) {
        $page->navigator->addSchoolYearNavigation($tawasulSchoolYearID);
    }

    $reportGateway = $container->get(ReportGateway::class);

    // QUERY
    $criteria = $reportGateway->newQueryCriteria(true)
        ->sortBy(['tawasulReportingCycle.sequenceNumber', 'tawasulReport.name'])
        ->fromPOST();

    $reports = $reportGateway->queryReportsBySchoolYear($criteria, $tawasulSchoolYearID);

    // GRID TABLE
    $table = DataTable::createPaginated('reportsManage', $criteria);
    $table->setTitle(__('View'));

    $table->modifyRows(function ($report, $row) {
        if ($report['active'] != 'Y') $row->addClass('error');
        return $row;
    });

    $table->addMetaData('filterOptions', [
        'active:Y'        => __('Active').': '.__('Yes'),
        'active:N'        => __('Active').': '.__('No'),
    ]);

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/TawasulReports/reports_manage_add.php')
        ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
        ->displayLabel();

    $table->addColumn('name', __('Name'));
    $table->addColumn('context', __('Context'))
        ->format(function ($report) {
            return $report['yearGroups'];
        });

    $table->addColumn('accessDate', __('Go Live'))
        ->format(Format::using('dateTimeReadable', 'accessDate'));

    $table->addActionColumn()
        ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
        ->addParam('tawasulReportID')
        ->format(function ($report, $actions) {
            $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulReports/reports_manage_edit.php');

            $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/TawasulReports/reports_manage_delete.php');
        });

    echo $table->render($reports);
}
