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
use TawasulOS\Forms\Prefab\BulkActionForm;
use Tos\Module\TawasulReports\Contexts\ContextFactory;
use Tos\Module\TawasulReports\Domain\ReportTemplateGateway;
use TawasulOS\Domain\DataSet;
use TawasulOS\Domain\System\SettingGateway;
use Tos\Module\TawasulReports\Domain\ReportArchiveEntryGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reports_generate_single.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $tawasulReportID = $_GET['tawasulReportID'] ?? '';
    $tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';
    $contextData = $_GET['contextData'] ?? '';
    $search = $_GET['search'] ?? '';

    $page->breadcrumbs
        ->add(__('Generate Reports'), 'reports_generate.php')
        ->add(__('Run'), 'reports_generate_batch.php', ['tawasulReportID' => $tawasulReportID])
        ->add(__('Single'));


    $reportGateway = $container->get(ReportGateway::class);
    $reportArchiveEntryGateway = $container->get(ReportArchiveEntryGateway::class);

    $report = $reportGateway->getByID($tawasulReportID);
    $templateData = $container->get(ReportTemplateGateway::class)->getByID($report['tawasulReportTemplateID'] ?? '');

    if (empty($tawasulReportID) || empty($report)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $context = ContextFactory::create($templateData['context']);

$ids = $context->getIdentifiers($pdo, $report['tawasulReportID'], $contextData, true);
$ids = array_map(function ($entry) use ($tawasulReportID, $report, &$reportArchiveEntryGateway) {
    $entry['archive'] = $reportArchiveEntryGateway->getRecentArchiveEntryByReport($tawasulReportID, 'Single', $entry['tawasulPersonID'], 'Staff', true, true);

    // Backward-compatibility fallback: some archived entries are linked by identifier only.
    if (empty($entry['archive']) && !empty($report['name'])) {
        $entry['archive'] = $reportArchiveEntryGateway->getRecentArchiveEntryByReportIdentifier(
            $report['tawasulSchoolYearID'],
            $report['name'],
            'Single',
            $entry['tawasulPersonID'],
            'Staff',
            true,
            true
        );
    }

    return $entry;
    }, $ids);

    // FORM
    $form = BulkActionForm::create('bulkAction', $session->get('absoluteURL').'/modules/TawasulReports/reports_generate_singleProcess.php');

    $form->addHiddenValue('tawasulReportID', $tawasulReportID);
    $form->addHiddenValue('contextData', $contextData);
    $form->addHiddenValue('search', $search);

    $bulkActions = array(
        'Generate' => __('Generate'),
        'Delete' => __('Delete'),
    );

    $col = $form->createBulkActionColumn($bulkActions);
        $col->addSelect('status')
            ->fromArray(['Draft' => __('Draft'), 'Final' => __('Final')])
            ->required()
            ->setClass('status w-32');
        $col->addSubmit(__('Go'));

    $form->toggleVisibilityByClass('status')->onSelect('action')->when('Generate');

    // Data TABLE
    $table = $form->addRow()->addDataTable('reportsGenerate', $reportGateway->newQueryCriteria(true))->withData(new DataSet($ids));

    $table->addMetaData('bulkActions', $col);

    if (!empty(array_filter(array_column($ids, 'formGroup')))) {
        $table->addColumn('formGroup', __('Form Group'))
            ->notSortable()
            ->width('10%')
            ->format(function($values) {
                return $values['formGroup'] ?? __('Unknown');
            });
    }

    $table->addColumn('name', __('Name'))->notSortable()->format($context->getFormatter());

    $table->addColumn('timestamp', __('Last Created'))
        ->notSortable()
        ->format(function ($report) use ($tawasulReportID, &$reportArchiveEntryGateway) {
            if ($report['archive']) {
                $tag = !empty($report['archive']['status'])
                    ? '<span class="tag ml-2 '.($report['archive']['status'] == 'Final' ? 'success' : 'dull').'">'.__($report['archive']['status']).'</span>'
                    : '';
                $title = Format::dateTimeReadable($report['archive']['timestampModified']);
                $url = './modules/TawasulReports/archive_byStudent_download.php?tawasulReportArchiveEntryID='.$report['archive']['tawasulReportArchiveEntryID'].'&tawasulPersonID='.$report['tawasulPersonID'];
                return Format::link($url, $title).$tag;
            }

            return '';
        });

    $debugMode = $container->get(SettingGateway::class)->getSettingByScope('Reports', 'debugMode');

    $table->addActionColumn()
        ->addParam('tawasulReportID', $tawasulReportID)
        ->format(function ($report, $actions) use ($debugMode) {
            if ($debugMode == 'Y') {
                $actions->addAction('inspect', __('Inspect'))
                        ->setIcon('search')
                        ->modalWindow('900', '550')
                        ->addParam('tawasulStudentEnrolmentID', $report['tawasulStudentEnrolmentID'] ?? '')
                        ->setURL('/modules/TawasulReports/reports_generate_singleDebug.php');
            }

            if ($report['archive']) {
                $actions->addAction('view', __('View'))
                        ->directLink()
                        ->addParam('action', 'view')
                        ->addParam('tawasulPersonID', $report['tawasulPersonID'] ?? '')
                        ->addParam('tawasulReportArchiveEntryID', $report['archive']['tawasulReportArchiveEntryID'] ?? '')
                        ->setURL('/modules/TawasulReports/archive_byStudent_download.php');

                $actions->addAction('download', __('Download'))
                        ->directLink()
                        ->setIcon('download')
                        ->addParam('tawasulPersonID', $report['tawasulPersonID'] ?? '')
                        ->addParam('tawasulReportArchiveEntryID', $report['archive']['tawasulReportArchiveEntryID'] ?? '')
                        ->setURL('/modules/TawasulReports/archive_byStudent_download.php');
            }
        });

    $table->addCheckboxColumn('identifier', 'tawasulStudentEnrolmentID');


    echo $form->getOutput();
}
