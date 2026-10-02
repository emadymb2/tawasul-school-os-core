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
use Tos\Module\TawasulReports\Domain\ReportArchiveGateway;
use Tos\Module\TawasulReports\Domain\ReportArchiveEntryGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reports_send.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Send Reports'));

    $step = $_GET['step'] ?? 1;
    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
    $tawasulReportID = $_GET['tawasulReportID'] ?? '';
    $tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';

    $reportGateway = $container->get(ReportGateway::class);
    $reportArchiveGateway = $container->get(ReportArchiveGateway::class);
    $reportArchiveEntryGateway = $container->get(ReportArchiveEntryGateway::class);
    $roleCategory = $session->get('tawasulRoleIDCurrentCategory');

    if (empty($tawasulReportID)) {
        // QUERY
        $criteria = $reportArchiveEntryGateway->newQueryCriteria(true)
            ->sortBy(['sequenceNumber', 'reportIdentifier', 'name'])
            ->filterBy('reportID', true)
            ->filterBy('active', 'Y')
            ->fromPOST();

        $archives = $reportArchiveEntryGateway->queryArchiveReportsBySchoolYear($criteria, $tawasulSchoolYearID, $roleCategory, false, true);

        // DATA TABLE
        $table = DataTable::createPaginated('reportsSend', $criteria);
        $table->setTitle(__('Send Reports'));

        $table->addColumn('reportIdentifier', __('Report'));

        $table->addColumn('timestampModified', __('Last Created'))
            ->format(function ($report) {
                $tag = '<span class="tag ml-2 success">'.__('Final').'</span>';
                return Format::dateTimeReadable($report['timestampModified']).$tag;
            });

        $table->addActionColumn()
            ->addParam('step', '2')
            ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->addParam('tawasulReportID')
            ->addParam('reportIdentifier')
            ->format(function ($report, $actions) {
                $actions->addAction('go', __('Go'))
                        ->setIcon('page_right')
                        ->setURL('/modules/TawasulReports/reports_send.php');
            });

        echo $table->render($archives);

    } else if ($step == 2) {
        // QUERY
        $criteria = $reportGateway->newQueryCriteria(true)
            ->sortBy(['sequenceNumber', 'name'])
            ->fromPOST();

        $reports = $reportGateway->queryYearGroupsByReport($criteria, $tawasulReportID, $roleCategory, false, true);
        $report = $reportGateway->getByID($tawasulReportID);
        $archive = $reportArchiveGateway->getByID($report['tawasulReportArchiveID'] ?? '');

        if (empty($archive) || $archive['viewableParents'] != 'Y') {
            echo Format::alert(__('This report is in an archive that is not viewable by {roleCategory}.', ['roleCategory' => __('Parents')]));
            return;
        }

        // Data TABLE
        $table = DataTable::createPaginated('reportsSend', $criteria);
        $table->setTitle($report['name']);

        $table->addColumn('name', __('Name'));

        $table->addColumn('count', __('Count'));

        $table->addColumn('timestamp', __('Last Created'))
            ->notSortable()
            ->format(function ($report) use ($tawasulReportID, &$reportArchiveEntryGateway) {
                $archive = $reportArchiveEntryGateway->getRecentArchiveEntryByReport($tawasulReportID, 'Batch', $report['tawasulYearGroupID'], 'Staff', true, true);

                if ($archive) {
                    $tag = '<span class="tag ml-2 '.($archive['status'] == 'Final' ? 'success' : 'dull').'">'.__($archive['status']).'</span>';
                    return Format::dateTimeReadable($archive['timestampModified']).$tag;
                }

                return '';
            });

        $table->addActionColumn()
            ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->addParam('tawasulReportID', $tawasulReportID)
            ->format(function ($report, $actions) {
                $actions->addAction('go', __('Go'))
                    ->setIcon('page_right')
                    ->addParam('tawasulYearGroupID', $report['tawasulYearGroupID'])
                    ->addParam('contextData', $report['tawasulYearGroupID'])
                    ->setURL('/modules/TawasulReports/reports_send_batch.php');
            });

        echo $table->render($reports);
    }
}
