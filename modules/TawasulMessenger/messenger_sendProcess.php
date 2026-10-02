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
use Tos\Module\TawasulMessenger\MessageProcess;
use TawasulOS\Domain\Messenger\MessengerGateway;
use Tos\Module\TawasulMessenger\MessageTargets;
use TawasulOS\Domain\Messenger\MessengerReceiptGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$address = $_POST['address'] ?? '';
$tawasulMessengerID = $_POST['tawasulMessengerID'] ?? '';

$URL = $session->get('absoluteURL') . "/index.php?q=/modules/TawasulMessenger/messenger_send.php&sidebar=true&tawasulMessengerID={$tawasulMessengerID}";

if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php") == false) {
    $URL .= "&return=error0";
    header("Location: {$URL}");
    exit;
} else {
    $messengerGateway = $container->get(MessengerGateway::class);
    $messengerReceiptGateway = $container->get(MessengerReceiptGateway::class);

    // Check for message data
    $values = $messengerGateway->getByID($tawasulMessengerID);
    if (empty($tawasulMessengerID) || empty($values)) {
        $URL .= "&return=error1";
        header("Location: {$URL}");
        exit;
    }

    // Check if the message has already been sent
    if ($values['status'] == 'Sent') {
        $URL .= "&return=error2";
        header("Location: {$URL}");
        exit;
    }

    // Validate that the required values are present
    if (empty($values['subject']) || empty($values['body']) || ($values['email'] == 'Y' && $values['emailFrom'] == '') || ($values['emailReceipt'] == 'Y' && $values['emailReceiptText'] == '')) {
        $URL .= "&return=error3";
        header("Location: {$URL}");
        exit;
    }

    // Check which recipients who have been manually unchecked
    $recipientList = $_POST['tawasulMessengerReceiptID'] ?? []; 
    for ($i = 0; $i < count($recipientList); $i++) {
        $recipientList[$i] = str_pad($recipientList[$i], 14, "0", STR_PAD_LEFT);
    }
    $recipients = $messengerReceiptGateway->selectMessageRecipientList($tawasulMessengerID)->fetchAll();
    $unselected = array_diff(array_column($recipients, 'tawasulMessengerReceiptID'), $recipientList);
    
    // Check if all users have been unselected
    if (count($unselected) == count($recipients)) {
        $URL .= "&return=error6";
        header("Location: {$URL}");
        exit;
    }

    // Remove recipients who have been manually unchecked
    $messengerReceiptGateway->deleteRecipientsByID($tawasulMessengerID, $unselected);
    
    // Remove the [confirmLink] tag if read receipts are not enabled
    if (!empty($values['body']) && $values['emailReceipt'] == 'N') {
        if (strpos($values['body'], '[confirmLink]') !== false) {
            $values['body'] = str_replace('[confirmLink]', '', $values['body']);
            
            // Update the body text in the database after the [confirmLink] tag has been removed
            $messengerGateway->update($tawasulMessengerID, ['body' => $values['body']]);
        }
    }

    // Set the status of the message
    $messengerGateway->update($tawasulMessengerID, ['status' => 'Sending']);

    $process = $container->get(MessageProcess::class);
    $process->startSendMessage(
        $tawasulMessengerID,
        $session->get('tawasulSchoolYearID'),
        $session->get('tawasulPersonID'),
        $session->get('tawasulRoleIDCurrent'),
        $values
    );

    $session->set('pageLoads', null);
    $notification = $values['email'] == 'Y' || $values['sms'] == 'Y' ? 'Y' : 'N';

    $URL.= "&return=success1&notification={$notification}";
    
    header("Location: {$URL}") ;
}
