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

use TawasulOS\Domain\Students\StudentGateway;
use Tos\Module\TawasulReports\ArchiveFile;
use Tos\Module\TawasulReports\ReportBuilder;
use Tos\Module\TawasulReports\Domain\ReportGateway;
use Tos\Module\TawasulReports\Domain\ReportArchiveGateway;
use Tos\Module\TawasulReports\Domain\ReportArchiveEntryGateway;
use Tos\Module\TawasulReports\Renderer\MpdfRenderer;
use Tos\Module\TawasulReports\Renderer\TcpdfRenderer;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulReportID = $_POST['tawasulReportID'] ?? '';
$contextData = $_POST['contextData'] ?? '';
$identifiers = $_POST['identifier'] ?? [];
$status = $_POST['status'] ?? 'Draft';
$action = $_POST['action'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reports_generate_single.php&tawasulReportID='.$tawasulReportID.'&contextData='.$contextData;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reports_generate_batch.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    
    $reportGateway = $container->get(ReportGateway::class);
    $reportArchiveEntryGateway = $container->get(ReportArchiveEntryGateway::class);
    $studentGateway = $container->get(StudentGateway::class);

    $report = $reportGateway->getByID($tawasulReportID);

    // Validate the database relationships exist
    if (empty($tawasulReportID) || empty($report) || empty($identifiers)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    if ($action == 'Generate') {
        // Set reports to cache in a separate location
        $cachePath = $session->has('cachePath') ? $session->get('cachePath').'/reports' : '/uploads/cache';
        $container->get('twig')->setCache($session->get('absolutePath').$cachePath);

        $reportBuilder = $container->get(ReportBuilder::class);
        $archive = $container->get(ReportArchiveGateway::class)->getByID($report['tawasulReportArchiveID']);
        $archiveFile = $container->get(ArchiveFile::class);
        
        $template = $reportBuilder->buildTemplate($report['tawasulReportTemplateID'], $status == 'Draft');
        $renderer = $container->get($template->getData('flags') == 1 ? MpdfRenderer::class : TcpdfRenderer::class);

        foreach ($identifiers as $identifier) {

            $ids = ['tawasulStudentEnrolmentID' => $identifier, 'tawasulReportingCycleID' => $report['tawasulReportingCycleID']];
            $reports = $reportBuilder->buildReportSingle($template, $report, $ids);

            // Archive
            if ($student = $studentGateway->getByID($identifier)) {
                $path = $archiveFile->getSingleFilePath($tawasulReportID, $student['tawasulYearGroupID'], $identifier);
                $renderer->render($template, $reports, $session->get('absolutePath').$archive['path'].'/'.$path);

                $reportArchiveEntryGateway->insertAndUpdate([
                    'reportIdentifier'      => $report['name'],
                    'tawasulReportID'        => $tawasulReportID,
                    'tawasulReportArchiveID' => $report['tawasulReportArchiveID'],
                    'tawasulSchoolYearID'    => $student['tawasulSchoolYearID'],
                    'tawasulYearGroupID'     => $student['tawasulYearGroupID'],
                    'tawasulFormGroupID'     => $student['tawasulFormGroupID'],
                    'tawasulPersonID'        => $student['tawasulPersonID'],
                    'type'                  => 'Single',
                    'status'                => $status,
                    'filePath'              => $path,
                ], ['status' => $status, 'timestampModified' => date('Y-m-d H:i:s'), 'filePath' => $path]);
            } else {
                $partialFail = true;
            }
        }
    } else if ($action == 'Delete') {
        $archive = $container->get(ReportArchiveGateway::class)->getByID($report['tawasulReportArchiveID']);

        foreach ($identifiers as $identifier) {
            if ($student = $studentGateway->getByID($identifier)) {
                $entry = $reportArchiveEntryGateway->selectBy([
                    // 'reportIdentifier'      => $report['name'],
                    'tawasulReportID'        => $tawasulReportID,
                    'tawasulReportArchiveID' => $report['tawasulReportArchiveID'],
                    'tawasulSchoolYearID'    => $student['tawasulSchoolYearID'],
                    'tawasulYearGroupID'     => $student['tawasulYearGroupID'],
                    'tawasulFormGroupID'     => $student['tawasulFormGroupID'],
                    'tawasulPersonID'        => $student['tawasulPersonID'],
                    'type'                  => 'Single',
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
            } else {
                $partialFail = true;
            }
        }
    } else {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $URL .= $partialFail
        ? "&return=error3"
        : "&return=success0";

    header("Location: {$URL}");
}
