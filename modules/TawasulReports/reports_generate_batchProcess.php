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
use Tos\Module\TawasulReports\GenerateReportProcess;
use Tos\Module\TawasulReports\Domain\ReportArchiveGateway;
use Tos\Module\TawasulReports\Domain\ReportArchiveEntryGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulReportID = $_POST['tawasulReportID'] ?? '';
$contextData = $_POST['contextData'] ?? '';
$status = $_POST['status'] ?? 'Draft';
$twoSided = $_POST['twoSided'] ?? 'N';
$action = $_POST['action'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reports_generate_batch.php&tawasulReportID='.$tawasulReportID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reports_generate_batch.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;
    $reportGateway = $container->get(ReportGateway::class);

    $report = $reportGateway->getByID($tawasulReportID);

    // Validate the database relationships exist
    if (empty($report)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $contexts = is_array($contextData)? $contextData : [$contextData];

    if ($action == 'Generate') {
        $options = compact('status', 'twoSided');
        $process = $container->get(GenerateReportProcess::class);

        $success = $process->startReportBatch($tawasulReportID, $contexts, $options, $session->get('tawasulPersonID'));
        $partialFail &= !$success;
        
        sleep(1.0);
    } else if ($action == 'Delete') {
        $reportArchiveEntryGateway = $container->get(ReportArchiveEntryGateway::class);
        $archive = $container->get(ReportArchiveGateway::class)->getByID($report['tawasulReportArchiveID']);

        foreach ($contexts as $tawasulYearGroupID) {
            $entry = $reportArchiveEntryGateway->selectBy([
                // 'reportIdentifier'      => $report['name'],
                'tawasulReportID'        => $tawasulReportID,
                'tawasulReportArchiveID' => $report['tawasulReportArchiveID'],
                'tawasulSchoolYearID'    => $report['tawasulSchoolYearID'],
                'tawasulYearGroupID'     => $tawasulYearGroupID,
                'type'                  => 'Batch',
            ])->fetch();

            if (!empty($entry)) {
                // Remove the file itself
                $path = $session->get('absolutePath').$archive['path'].'/'.$entry['filePath'];
                if (!empty($archive) && file_exists($path)) {
                    unlink($path);
                }
                
                // Then remove the archive entry
                $deleted = $reportArchiveEntryGateway->delete($entry['tawasulReportArchiveEntryID']);
                $partialFail &= !$deleted;
            }
        }

    } else {
        $partialFail = true;
    }

    $URL .= $partialFail
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}
