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

use TawasulOS\Services\Format;
use TawasulOS\Comms\EmailTemplate;
use TawasulOS\Contracts\Comms\Mailer;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\User\FamilyGateway;
use TawasulOS\Services\BackgroundProcess;
use League\Container\ContainerAwareTrait;
use TawasulOS\Domain\Students\StudentGateway;
use League\Container\ContainerAwareInterface;
use Tos\Module\TawasulReports\Domain\ReportGateway;
use Tos\Module\TawasulReports\Domain\ReportArchiveEntryGateway;

/**
 * SendNotificationsProcess
 *
 * @version v21
 * @since   v21
 */
class SendReportsProcess extends BackgroundProcess implements ContainerAwareInterface
{
    use ContainerAwareTrait;


    public function __construct()
    {
        
    }

    public function runSendReportsToParents($tawasulReportID, $templateName, $identifiers)
    {
        $familyGateway = $this->container->get(FamilyGateway::class);
        $reportGateway = $this->container->get(ReportGateway::class);
        $userGateway = $this->container->get(UserGateway::class);
        $reportArchiveEntryGateway = $this->container->get(ReportArchiveEntryGateway::class);
    
        $report = $reportGateway->getByID($tawasulReportID);
        $template = $this->container->get(EmailTemplate::class)->setTemplate($templateName);
        $mail = $this->container->get(Mailer::class);
        $mail->SMTPKeepAlive = true;

        $sendReport = ['emailSent' => 0, 'emailFailed' => 0];

        foreach ($identifiers as $tawasulReportArchiveEntryID) {
            $archive = $reportArchiveEntryGateway->getByID($tawasulReportArchiveEntryID);
            $student = $userGateway->getByID($archive['tawasulPersonID'] ?? '');

            if (empty($archive) || empty($student)) {
                $sendReport['emailFailed']++;
                continue;
            }

            // Generate and save an access token so this report can be accessed securely, or use existing unexpired token
            if (!empty($archive['accessToken']) && (empty($archive['timestampAccessExpiry']) || date('Y-m-d H:i:s') < $archive['timestampAccessExpiry'])) {
                $accessToken = $archive['accessToken'];
            } else {
                $accessToken = bin2hex(random_bytes(20));
            }
            
            $data = [
                'timestampSent' => date('Y-m-d H:i:s'),
                'accessToken' => $accessToken,
                'timestampAccessExpiry' => date('Y-m-d H:i:s', strtotime('+1 week')),
            ];
            $reportArchiveEntryGateway->update($tawasulReportArchiveEntryID, $data);
    
            // Get the adults in this family and filter by email settings
            $familyAdults = $familyGateway->selectFamilyAdultsByStudent($archive['tawasulPersonID'], true)->fetchAll();
            $familyAdults = array_filter($familyAdults, function ($adult) {
                return $adult['contactEmail'] == 'Y' && !empty($adult['email']);
            });

            foreach ($familyAdults as $parent) {
                // Setup the data for this template
                $data = [
                    'reportName'           => $report['name'],
                    'studentPreferredName' => $student['preferredName'],
                    'studentSurname'       => $student['surname'],
                    'parentPreferredName'  => $parent['preferredName'],
                    'parentSurname'        => $parent['surname'],
                    'date'                 => Format::date($archive['timestampModified']),
                ];
        
                // Render the templates for this email
                $subject = $template->renderSubject($data);
                $body = $template->renderBody($data);

                $mail->AddAddress($parent['email'], Format::name('', $parent['preferredName'], $parent['surname'], 'Parent', false, true));
                
                $mail->setDefaultSender($subject);
                $mail->renderBody('mail/email.twig.html', [
                    'title'  => $subject,
                    'body'   => $body,
                    'button' => [
                        'url'  => '/modules/TawasulReports/archive_byStudent_download.php?action=view&tawasulReportArchiveEntryID='.$tawasulReportArchiveEntryID.'&token='.$accessToken.'&tawasulPersonIDAccessed='.$parent['tawasulPersonID'],
                        'text' => __('Download'),
                    ],

                    'button2' => $parent['status'] == 'Full' && $parent['canLogin'] == 'Y' ? [
                        'url'  => '/index.php?q=/modules/TawasulReports/archive_byStudent_view.php&tawasulSchoolYearID='.$report['tawasulSchoolYearID'].'&tawasulPersonID='.$student['tawasulPersonID'],
                        'text' => __('View Online'),
                    ] : [],
                ]);

                if ($mail->Send()) {
                    $sendReport['emailSent']++;
                } else {
                    $sendReport['emailFailed']++;
                }

                // Clear addresses
                $mail->ClearAllRecipients();
            }
        }

        // Close SMTP connection
        $mail->smtpClose();

        return $sendReport;
    }

    public function runSendReportsToStudents($tawasulReportID, $templateName, $identifiers)
    {
        $reportGateway = $this->container->get(ReportGateway::class);
        $userGateway = $this->container->get(UserGateway::class);
        $reportArchiveEntryGateway = $this->container->get(ReportArchiveEntryGateway::class);
    
        $report = $reportGateway->getByID($tawasulReportID);
        $template = $this->container->get(EmailTemplate::class)->setTemplate($templateName);
        $mail = $this->container->get(Mailer::class);
        $mail->SMTPKeepAlive = true;

        $sendReport = ['emailSent' => 0, 'emailFailed' => 0];

        foreach ($identifiers as $tawasulReportArchiveEntryID) {
            $archive = $reportArchiveEntryGateway->getByID($tawasulReportArchiveEntryID);
            $student = $userGateway->getByID($archive['tawasulPersonID'] ?? '');

            if (empty($archive) || empty($student)) {
                $sendReport['emailFailed']++;
                continue;
            }

            // Generate and save an access token so this report can be accessed securely, or use existing unexpired token
            if (!empty($archive['accessToken']) && (empty($archive['timestampAccessExpiry']) || date('Y-m-d H:i:s') < $archive['timestampAccessExpiry'])) {
                $accessToken = $archive['accessToken'];
            } else {
                $accessToken = bin2hex(random_bytes(20));
            }
            
            $data = [
                'timestampSent' => date('Y-m-d H:i:s'),
                'accessToken' => $accessToken,
                'timestampAccessExpiry' => date('Y-m-d H:i:s', strtotime('+1 week')),
            ];
            $reportArchiveEntryGateway->update($tawasulReportArchiveEntryID, $data);
    
            // Setup the data for this template
            $data = [
                'reportName'           => $report['name'],
                'studentPreferredName' => $student['preferredName'],
                'studentSurname'       => $student['surname'],
                'date'                 => Format::date($archive['timestampModified']),
            ];
    
            // Render the templates for this email
            $subject = $template->renderSubject($data);
            $body = $template->renderBody($data);

            $mail->AddAddress($student['email'], Format::name('', $student['preferredName'], $student['surname'], 'Student', false, true));
            
            $mail->setDefaultSender($subject);
            $mail->renderBody('mail/email.twig.html', [
                'title'  => $subject,
                'body'   => $body,
                'button' => [
                    'url'  => '/modules/TawasulReports/archive_byStudent_download.php?action=view&tawasulReportArchiveEntryID='.$tawasulReportArchiveEntryID.'&token='.$accessToken,
                    'text' => __('Download'),
                ],

                'button2' => $student['status'] == 'Full' && $student['canLogin'] == 'Y' ? [
                    'url'  => '/index.php?q=/modules/TawasulReports/archive_byStudent_view.php&tawasulSchoolYearID='.$report['tawasulSchoolYearID'].'&tawasulPersonID='.$student['tawasulPersonID'],
                    'text' => __('View Online'),
                ] : [],
            ]);

            if ($mail->Send()) {
                $sendReport['emailSent']++;
            } else {
                $sendReport['emailFailed']++;
            }

            // Clear addresses
            $mail->ClearAllRecipients();
            
        }

        // Close SMTP connection
        $mail->smtpClose();

        return $sendReport;
    }
}
