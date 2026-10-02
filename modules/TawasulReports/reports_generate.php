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

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reports_generate.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Generate Reports'));

    $reportGateway = $container->get(ReportGateway::class);

    // School Year Picker
    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
    $page->navigator->addSchoolYearNavigation($tawasulSchoolYearID);

    // QUERY
    $criteria = $reportGateway->newQueryCriteria(true)
        ->sortBy(['tawasulReportingCycle.sequenceNumber', 'tawasulReport.name'])
        ->filterBy('active', 'Y')
        ->fromPOST();

    $reports = $reportGateway->queryReportsBySchoolYear($criteria, $tawasulSchoolYearID);
    $logs = $reportGateway->getRunningReports();
    $reports->joinColumn('tawasulReportID', 'logs', $logs);

    // Data TABLE
    $table = DataTable::createPaginated('reportsGenerate', $criteria);
    $table->setTitle(__('Generate Reports'));

    $table->addColumn('name', __('Name'));

    $table->addColumn('timestampGenerated', __('Last Created'))
        ->format(function ($report) {
            if (is_array($report['logs']) && count($report['logs']) > 0) {
                $firstLog = current($report['logs']);
                return '<div class="statusBar" data-id="'.($firstLog['processID'] ?? '').'">'
                      .'<img class="align-middle w-56 -mt-px" src="./themes/Default/img/loading.gif">'
                      .'<span class="tag ml-2 message">'.__('Running').'</span></div>';
            }

            return !empty($report['timestampGenerated'])? Format::dateTimeReadable($report['timestampGenerated']) : '';
        });

    $table->addActionColumn()
        ->addParam('tawasulReportID')
        ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
        ->format(function ($report, $actions) {
            $actions->addAction('go', __('Go'))
                    ->setIcon('page_right')
                    ->setURL('/modules/TawasulReports/reports_generate_batch.php');
        });

    echo $table->render($reports);
}
?>
<script>
$('.statusBar').each(function(index, element) {
    var refresh = setInterval(function () {
        var path = "<?php echo $session->get('absoluteURL') ?>/modules/TawasulReports/reports_generate_ajax.php";
        var postData = { tawasulLogID: $(element).data('id') };
        $(element).load(path, postData, function(responseText, textStatus, jqXHR) {
            if (responseText.indexOf('Complete') >= 0) {
                clearInterval(refresh);
            }
        });
    }, 3000);
});
</script>
