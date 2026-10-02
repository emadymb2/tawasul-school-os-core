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
use Tos\Module\TawasulReports\Domain\ReportGateway;
use TawasulOS\Forms\Prefab\BulkActionForm;
use Tos\Module\TawasulReports\Domain\ReportTemplateGateway;
use Tos\Module\TawasulReports\Contexts\ContextFactory;
use Tos\Module\TawasulReports\Domain\ReportArchiveEntryGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reports_generate.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Generate Reports'), 'reports_generate.php')
        ->add(__('Run'));


    $tawasulReportID = $_GET['tawasulReportID'] ?? '';

    $reportGateway = $container->get(ReportGateway::class);
    $reportArchiveEntryGateway = $container->get(ReportArchiveEntryGateway::class);

    $report = $reportGateway->getByID($tawasulReportID);

    if (empty($tawasulReportID) || empty($report)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $templateData = $container->get(ReportTemplateGateway::class)->getByID($report['tawasulReportTemplateID'] ?? '');
    $context = ContextFactory::create($templateData['context']);

    if (empty($templateData) || empty($context)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // QUERY
    $criteria = $reportGateway->newQueryCriteria(true)
        ->sortBy(['sequenceNumber', 'name'])
        ->fromPOST();

    $reports = $reportGateway->queryYearGroupsByReport($criteria, $tawasulReportID);
    $logs = $reportGateway->getRunningReports();
    $logs = $logs[$tawasulReportID] ?? [];

    // FORM
    $form = BulkActionForm::create('bulkAction', $session->get('absoluteURL').'/modules/TawasulReports/reports_generate_batchProcess.php');
    $form->setTitle(__('Year Groups'));
    $form->addHiddenValue('tawasulReportID', $tawasulReportID);

    $bulkActions = array(
        'Generate' => __('Generate'),
        'Delete' => __('Delete'),
    );

    $col = $form->createBulkActionColumn($bulkActions);
        $col->addSelect('status')
            ->fromArray(['Draft' => __('Draft'), 'Final' => __('Final')])
            ->required()
            ->setClass('status w-32');
        $col->addSelect('twoSided')
            ->fromArray(['N' => __('Single-sided'), 'Y' => __('Two-sided')])
            ->required()
            ->setClass('status w-32 ml-1');
        $col->addSubmit(__('Go'));

    $form->toggleVisibilityByClass('status')->onSelect('action')->when('Generate');

    // Data TABLE
    $table = $form->addRow()->addDataTable('reportsGenerate', $criteria)->withData($reports);

    $table->addMetaData('bulkActions', $col);

    $table->addColumn('name', __('Name'));

    $table->addColumn('count', __('Count'))
        ->width('8%')
        ->notSortable()
        ->format(function ($report) use (&$pdo, &$context) {
            if (empty($report['tawasulYearGroupID']) || empty($report['tawasulSchoolYearID'])) {
                return __('N/A');
            }

            $ids = $context->getIdentifiers($pdo, $report['tawasulReportID'], $report['tawasulYearGroupID']);
            return count($ids);
        });

    $table->addColumn('timestamp', __('Last Created'))
        ->notSortable()
        ->format(function ($report) use ($tawasulReportID, &$reportArchiveEntryGateway, &$logs) {
            $reportLogs = $logs[$report['tawasulYearGroupID']] ?? [];
            if (count($reportLogs) > 0) {
                return '<div class="statusBar" data-id="'.($reportLogs['processID'] ?? '').'" data-context="'.($report['tawasulYearGroupID'] ?? '').'" data-report="'.$tawasulReportID.'">'
                      .'<img class="align-middle w-56 -mt-px" src="./themes/Default/img/loading.gif">'
                      .'<span class="tag ml-2 message">'.__('Running').'</span></div>';
            }

            $archive = $reportArchiveEntryGateway->getRecentArchiveEntryByReport($tawasulReportID, 'Batch', $report['tawasulYearGroupID'], 'Staff', true, true);

            if ($archive) {
                $tag = '<span class="tag ml-2 '.($archive['status'] == 'Final' ? 'success' : 'dull').'">'.__($archive['status']).'</span>';
                $url = './modules/TawasulReports/archive_byReport_download.php?tawasulReportArchiveEntryID='.$archive['tawasulReportArchiveEntryID'];
                $title = Format::dateTimeReadable($archive['timestampModified']);
                return Format::link($url, $title).$tag;
            }

            return '';
        });

    $table->addActionColumn()
        ->addParam('tawasulReportID', $tawasulReportID)
        ->format(function ($report, $actions) use (&$logs) {
            $reportLogs = $logs[$report['tawasulYearGroupID']] ?? [];

            if (count($reportLogs) == 0) {
                $actions->addAction('run', __('Run'))
                        ->setIcon('run')
                        ->isModal(650, 300)
                        ->addParam('contextData', $report['tawasulYearGroupID'])
                        ->setURL('/modules/TawasulReports/reports_generate_batchConfirm.php');
            } else {
                $actions->addAction('cancel', __('Cancel'))
                        ->setIcon('iconCross')
                        ->isModal(650, 135)
                        ->addParam('contextData', $report['tawasulYearGroupID'])
                        ->addParam('processID', $reportLogs['processID'])
                        ->setURL('/modules/TawasulReports/reports_generate_cancelConfirm.php');
            }

            $actions->addAction('go', __('Go'))
                    ->setIcon('page_right')
                    ->addParam('contextData', $report['tawasulYearGroupID'])
                    ->setURL('/modules/TawasulReports/reports_generate_single.php');
        });

    $table->addCheckboxColumn('contextData', 'tawasulYearGroupID');

    echo $form->getOutput();
}
?>
<script>
$('.statusBar').each(function(index, element) {
    var refresh = setInterval(function () {
        var path = "<?php echo $session->get('absoluteURL') ?>/modules/TawasulReports/reports_generate_ajax.php";
        var postData = { tawasulLogID: $(element).data('id'), tawasulReportID: $(element).data('report'), contextID: $(element).data('context') };
        $(element).load(path, postData, function(responseText, textStatus, jqXHR) {
            if (responseText.indexOf('Complete') >= 0) {
                clearInterval(refresh);
                $("[title='Cancel']").remove();
            }
        });
    }, 3000);
});
</script>
