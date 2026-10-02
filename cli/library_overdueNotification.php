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

use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Comms\NotificationSender;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\System\NotificationGateway;

require getcwd().'/../tawasul.php';

//Check for CLI, so this cannot be run through browser
$settingGateway = $container->get(SettingGateway::class);
$remoteCLIKey = $settingGateway->getSettingByScope('System Admin', 'remoteCLIKey');
$remoteCLIKeyInput = $_GET['remoteCLIKey'] ?? null;
if (!(isCommandLineInterface() OR ($remoteCLIKey != '' AND $remoteCLIKey == $remoteCLIKeyInput))) {
	print __("This script cannot be run from a browser, only via CLI.") ;
}
else {
    //SCAN THROUGH ALL OVERDUE LOANS
    $today = date('Y-m-d');
    $data = array('today' => $today);
    $sql = "SELECT tawasulLibraryItem.*, surname, preferredName, email FROM tawasulLibraryItem JOIN tawasulPerson ON (tawasulLibraryItem.tawasulPersonIDStatusResponsible=tawasulPerson.tawasulPersonID) WHERE tawasulLibraryItem.status='On Loan' AND borrowable='Y' AND returnExpected<:today AND tawasulPerson.status='Full' ORDER BY surname, preferredName";
    $result = $connection2->prepare($sql);
    $result->execute($data);

    // Initialize the notification sender & gateway objects
    $notificationGateway = $container->get(NotificationGateway::class);
    $notificationSender = $container->get(NotificationSender::class);

    // Raise a new notification event
    $event = new NotificationEvent('Library', 'Overdue Loan Items');

    if ($event->getEventDetails($notificationGateway, 'active') == 'Y') {
        if ($result->rowCount() > 0) {
            while ($row = $result->fetch()) { //For every student
                $notificationText = sprintf(__('You have an overdue loan item that needs to be returned (%1$s).'), $row['name']);
                $notificationSender->addNotification($row['tawasulPersonIDStatusResponsible'], $notificationText, 'Library', '/index.php?q=/modules/TawasulLibrary/library_browse.php&tawasulLibraryItemID='.$row['tawasulLibraryItemID']);
            }
        }
    }

    $event->setNotificationText(sprintf(__('A Library Overdue Items CLI script has run, notifying %1$s users.'), $notificationSender->getNotificationCount()));
    $event->setActionLink('/index.php?q=/modules/TawasulAttendance/report_formGroupsNotRegistered_byDate.php');

    // Push the event to the notification sender
    $event->pushNotifications($notificationGateway, $notificationSender);

    // Send all notifications
    $sendReport = $notificationSender->sendNotifications();

    // Output the result to terminal
    echo sprintf('Sent %1$s notifications: %2$s inserts, %3$s updates, %4$s emails sent, %5$s emails failed.', $sendReport['count'], $sendReport['inserts'], $sendReport['updates'], $sendReport['emailSent'], $sendReport['emailFailed'])."\n";
}
?>

