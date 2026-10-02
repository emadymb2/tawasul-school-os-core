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

namespace Tos\Module\TawasulReports;

use TawasulOS\Services\BackgroundProcess;
use TawasulOS\Comms\NotificationSender;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Students\StudentGateway;
use Tos\Module\TawasulReports\ArchiveFile;
use Tos\Module\TawasulReports\Domain\ReportGateway;
use Tos\Module\TawasulReports\Domain\ReportArchiveEntryGateway;
use Tos\Module\TawasulReports\Domain\ReportArchiveGateway;
use Tos\Module\TawasulReports\Renderer\ReportRendererInterface;
use Tos\Module\TawasulReports\Renderer\MpdfRenderer;
use Tos\Module\TawasulReports\Renderer\TcpdfRenderer;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;

/**
 * GenerateReportProcess
 *
 * @version v19
 * @since   v19
 */
class GenerateReportProcess extends BackgroundProcess implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected $absolutePath;

    public function __construct(SettingGateway $settingGateway)
    {
        $this->absolutePath = $settingGateway->getSettingByScope('System', 'absolutePath');
    }

    public function runReportBatch($tawasulReportID, $contexts = [], $options = [], $tawasulPersonID = null)
    {
        ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
        
        $timeStart = time();
        $report = $this->container->get(ReportGateway::class)->getByID($tawasulReportID);

        if (empty($tawasulReportID) || empty($report)) {
            return false;
        }

        // Set reports to cache in a separate location
        $session = $this->container->get('session');
        $cachePath = $session->has('cachePath') ? $session->get('cachePath').'/reports' : '/uploads/cache';
        $this->container->get('twig')->setCache($session->get('absolutePath').$cachePath);

        $reportArchiveEntryGateway = $this->container->get(ReportArchiveEntryGateway::class);
        $studentGateway = $this->container->get(StudentGateway::class);

        $reportBuilder = $this->container->get(ReportBuilder::class);
        $archive = $this->container->get(ReportArchiveGateway::class)->getByID($report['tawasulReportArchiveID']);
        $archiveFile = $this->container->get(ArchiveFile::class);

        $template = $reportBuilder->buildTemplate($report['tawasulReportTemplateID'], $options['status'] == 'Draft');

        foreach ($contexts as $contextData) {
            $reports = $reportBuilder->buildReportBatch($template, $report, $contextData);

            $renderer = $this->container->get($template->getData('flags') == 1 ? MpdfRenderer::class : TcpdfRenderer::class);
            $renderer->setMode($options['twoSided'] == 'Y'
                ? ReportRendererInterface::OUTPUT_CONTINUOUS | ReportRendererInterface::OUTPUT_TWO_SIDED
                : ReportRendererInterface::OUTPUT_CONTINUOUS
            );

            // Render the Report: Batch
            $path = $archiveFile->getBatchFilePath($tawasulReportID, $contextData);
            $renderer->render($template, $reports, $this->absolutePath.$archive['path'].'/'.$path);

            // Update the Archive: Batch
            $reportArchiveEntryGateway->insertAndUpdate([
                'reportIdentifier'      => $report['name'],
                'tawasulReportID'        => $tawasulReportID,
                'tawasulReportArchiveID' => $report['tawasulReportArchiveID'],
                'tawasulSchoolYearID'    => $report['tawasulSchoolYearID'],
                'tawasulYearGroupID'     => $contextData,
                'type'                  => 'Batch',
                'status'                => $options['status'],
                'filePath'              => $path,
            ], ['status' => $options['status'], 'timestampModified' => date('Y-m-d H:i:s')]);

            // Create reports for each student
            foreach ($reports as $studentReport) {
                $identifier = $studentReport->getID('tawasulStudentEnrolmentID');

                if ($student = $studentGateway->getByID($identifier)) {
                    // Render the Report: Single
                    $path = $archiveFile->getSingleFilePath($tawasulReportID, $student['tawasulYearGroupID'], $identifier);
                    $renderer->render($template, [$studentReport], $this->absolutePath.$archive['path'].'/'.$path);

                    // Update the Archive: Single
                    $reportArchiveEntryGateway->insertAndUpdate([
                        'reportIdentifier'      => $report['name'],
                        'tawasulReportID'        => $tawasulReportID,
                        'tawasulReportArchiveID' => $report['tawasulReportArchiveID'],
                        'tawasulSchoolYearID'    => $student['tawasulSchoolYearID'],
                        'tawasulYearGroupID'     => $student['tawasulYearGroupID'],
                        'tawasulFormGroupID'     => $student['tawasulFormGroupID'],
                        'tawasulPersonID'        => $student['tawasulPersonID'],
                        'type'                  => 'Single',
                        'status'                => $options['status'],
                        'filePath'              => $path,
                    ], ['status' => $options['status'], 'timestampModified' => date('Y-m-d H:i:s'), 'filePath' => $path]);
                }
            }
        }

        // Notify the person who created this report
        if (!empty($tawasulPersonID)) {
            $timeEnd = time();
            $actionText = __('A Report Card Generation CLI script has run.').'<br/><br/>';
            $actionText .= 'Process time: ' . gmdate("H:i:s", ($timeEnd - $timeStart) ).'<br/>';
            $actionText .= 'Process finished on '.date(DATE_RFC2822);
            $actionLink = '/index.php?q=/modules/TawasulReports/reports_generate_batch.php&tawasulReportID='.$tawasulReportID;

            $notificationSender = $this->container->get(NotificationSender::class);
            $notificationSender->addNotification($tawasulPersonID, $actionText, 'Reports', $actionLink);
            $notificationSender->sendNotifications();
        }

        return $contextData;
    }
}
