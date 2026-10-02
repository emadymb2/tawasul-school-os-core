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
use Tos\Module\TawasulMessenger\MessageProcess;
use Tos\Module\TawasulMessenger\MessageTargets;
use TawasulOS\Domain\Messenger\MessengerGateway;

require_once __DIR__ . '/../../tawasul.php';

// Module includes
include './moduleFunctions.php';

$validator = $container->get(Validator::class);
$_POST = $validator->sanitize($_POST, ['body' => 'HTML']);

$tawasulMessengerID = $_POST['tawasulMessengerID'] ?? '';
$search = $_GET['search'] ?? '';
$resendEmail = $_POST['resendEmail'] ?? '';

$URL = $session->get('absoluteURL') . "/index.php?q=/modules/TawasulMessenger/messenger_manage_report.php&sidebar=true&search=$search&tawasulMessengerID=$tawasulMessengerID";

if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_manage_report.php")==FALSE) {
    $URL.="&return=error0";
    header("Location: {$URL}");
} else {
    // Proceed!
    $highestAction=getHighestGroupedAction($guid, '/modules/TawasulMessenger/messenger_manage_report.php', $connection2);
    if ($highestAction == FALSE) {
        $URL.="&return=error0";
        header("Location: {$URL}");
        exit;
    }

    // Check for empty POST. This can happen if no recipients are selected
    if (empty($tawasulMessengerID) || empty($_POST['individualList'])) {
        $URL.="&return=error1";
        header("Location: {$URL}");
        exit;
    }

    $messengerGateway = $container->get(MessengerGateway::class);
    $messageTargets = $container->get(MessageTargets::class);

    $message = $messengerGateway->getByID($tawasulMessengerID);
    if (empty($message)) {
        $URL.="&return=error2";
        header("Location: {$URL}");
        exit;
    }

    $data = [
        'sms'           => $message['sms'] ?? 'N',
        'email'         => $message['email'] ?? 'N',
        'emailReceipt'  => $message['emailReceipt'] ?? 'N',
    ];

    $partialFail = false;
    $tawasulMessengerReceiptIDs = $messageTargets->createMessageRecipientsFromTargets($tawasulMessengerID, $data, $partialFail);

    if (empty($tawasulMessengerReceiptIDs)) {
        $URL.="&return=error6";
        header("Location: {$URL}");
        exit;
    }

    if ($resendEmail == 'Y') {
        $process = $container->get(MessageProcess::class);
        $process->startSendEmailToRecipients($tawasulMessengerID, $tawasulMessengerReceiptIDs);

        $URL .= $partialFail 
            ? '&return=error4'
            : "&return=success1";
    } else {
        $URL .= $partialFail 
            ? '&return=error4'
            : "&return=success0";
    }
    
    header("Location: {$URL}");
}
