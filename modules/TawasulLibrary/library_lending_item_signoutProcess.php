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

use TawasulOS\Data\Validator;
use TawasulOS\Services\Format;
use TawasulOS\Comms\EmailTemplate;
use TawasulOS\Contracts\Comms\Mailer;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\User\FamilyGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulLibraryItemID = $_POST['tawasulLibraryItemID'] ?? '';

$address = $_POST['address'] ?? '';
$name = $_GET['name'] ?? '';
$tawasulLibraryTypeID = $_GET['tawasulLibraryTypeID'] ?? '';
$tawasulSpaceID = $_GET['tawasulSpaceID'] ?? '';
$status = $_GET['status'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/library_lending_item_signOut.php&tawasulLibraryItemID=$tawasulLibraryItemID&name=$name&tawasulLibraryTypeID=$tawasulLibraryTypeID&tawasulSpaceID=$tawasulSpaceID&status=$status";
$URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/library_lending_item.php&tawasulLibraryItemID=$tawasulLibraryItemID&name=$name&tawasulLibraryTypeID=$tawasulLibraryTypeID&tawasulSpaceID=$tawasulSpaceID&status=$status";

if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_lending_item_signOut.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    // Proceed!
    $statusCurrent = $_POST['statusCurrent'] ?? '';
    $status = $_POST['status'] ?? '';
    $typeActions = [
        'Decommissioned' => 'Decommission',
        'Lost'           => 'Loss',
        'On Loan'        => 'Loan',
        'Repair'         => 'Repair',
        'Reserved'       => 'Reserve',
    ];
    $type = $typeActions[$status] ?? 'Other';
    $tawasulPersonIDStatusResponsible = $_POST['tawasulPersonIDStatusResponsible'] ?? null;
    $returnExpected = !empty($_POST['returnExpected']) ? Format::dateConvert($_POST['returnExpected']) : null;
    $notifyParents = $_POST['notifyParents'] ?? 'N';
    $returnAction = $_POST['returnAction'] ?? '';
    $tawasulPersonIDReturnAction = $_POST['tawasulPersonIDReturnAction'] ?? null;

    // Validate Inputs
    if ($tawasulLibraryItemID == '' or $status == '' or empty($tawasulPersonIDStatusResponsible) or $statusCurrent != 'Available') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulLibraryItemID' => $tawasulLibraryItemID);
            $sql = 'SELECT * FROM tawasulLibraryItem WHERE tawasulLibraryItemID=:tawasulLibraryItemID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($result->rowCount() != 1) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            //Write to database
            try {
                $data = array('tawasulLibraryItemID' => $tawasulLibraryItemID, 'type' => $type, 'status' => $status, 'tawasulPersonIDStatusResponsible' => $tawasulPersonIDStatusResponsible, 'tawasulPersonIDOut' => $session->get('tawasulPersonID'), 'timestampOut' => date('Y-m-d H:i:s', time()), 'returnExpected' => $returnExpected, 'returnAction' => $returnAction, 'tawasulPersonIDReturnAction' => $tawasulPersonIDReturnAction);
                $sql = 'INSERT INTO tawasulLibraryItemEvent SET tawasulLibraryItemID=:tawasulLibraryItemID, type=:type, status=:status, tawasulPersonIDStatusResponsible=:tawasulPersonIDStatusResponsible, tawasulPersonIDOut=:tawasulPersonIDOut, timestampOut=:timestampOut, returnExpected=:returnExpected, returnAction=:returnAction, tawasulPersonIDReturnAction=:tawasulPersonIDReturnAction';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            try {
                $data = array('tawasulLibraryItemID' => $tawasulLibraryItemID, 'status' => $status, 'tawasulPersonIDStatusResponsible' => $tawasulPersonIDStatusResponsible, 'tawasulPersonIDStatusRecorder' => $session->get('tawasulPersonID'), 'timestampStatus' => date('Y-m-d H:i:s', time()), 'returnExpected' => $returnExpected, 'returnAction' => $returnAction, 'tawasulPersonIDReturnAction' => $tawasulPersonIDReturnAction);
                $sql = 'UPDATE tawasulLibraryItem SET status=:status, tawasulPersonIDStatusResponsible=:tawasulPersonIDStatusResponsible, tawasulPersonIDStatusRecorder=:tawasulPersonIDStatusRecorder, timestampStatus=:timestampStatus, returnExpected=:returnExpected, returnAction=:returnAction, tawasulPersonIDReturnAction=:tawasulPersonIDReturnAction WHERE tawasulLibraryItemID=:tawasulLibraryItemID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            // Send notifications to parents if Notify Parents is selected for a Student
            $userDetails = $container->get(UserGateway::class)->getUserDetails($tawasulPersonIDStatusResponsible, $session->get('tawasulSchoolYearID'));

            if ($notifyParents == 'Y' && $userDetails['roleCategory'] == 'Student') {
                $template = $container->get(EmailTemplate::class)->setTemplate('Parent Notification for Student Lending Item');

                $mail = $container->get(Mailer::class);
                $mail->SMTPKeepAlive = true;

                // Get Parent Contact Details
                $familyGateway = $container->get(FamilyGateway::class);
                $familyAdults = $familyGateway->selectContactPriority1AdultsByStudent($tawasulPersonIDStatusResponsible)->fetchAll();

                foreach ($familyAdults as $adult) {
                    // Format an email to send to the parent
                    $templateData = [
                        'parentTitle'         => $adult['title'],
                        'parentPreferredName' => $adult['preferredName'],
                        'parentSurname'       => $adult['surname'],
                        'parentEmail'         => $adult['email'],
                        'date'                =>  Format::date(date('Y-m-d')),
                        'studentPreferredName' => $userDetails['preferredName'],
                        'studentSurname' => $userDetails['surname'],
                    ];

                    // Setup the email recipients
                    $mail->ClearAddresses();
                    $mail->AddAddress($adult['email']);

                    $mail->SetFrom($session->get('organisationEmail'), $session->get('organisationName'));
                    $mail->AddReplyTo($session->get('organisationEmail'));
                    $mail->setDefaultSender($template->renderSubject($templateData));

                    $mail->renderBody('mail/message.twig.html', [
                        'title'  => $template->renderSubject($templateData),
                        'body'   => $template->renderBody($templateData),
                    ]);

                    // Send email and record the result
                    $sent = $mail->Send();

                    $emails[$emailIndex] = Format::name($adult['title'], $adult['preferredName'], $adult['surname'], 'Parent').' ('.$adult['email'].') - '.__('Student').': '.$userDetails['preferredName'].' '.$userDetails['surname']. ($sent ? __('Sent') : __('Failed') );
                    $emailIndex++;
                }
            }
            
            $URL = $URLSuccess.'&return=success0';
            header("Location: {$URL}");
        }
    }
}