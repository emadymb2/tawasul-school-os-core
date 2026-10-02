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
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\User\UserStatusLogGateway;

require getcwd().'/../tawasul.php';

//Check for CLI, so this cannot be run through browser
$remoteCLIKey = $container->get(SettingGateway::class)->getSettingByScope('System Admin', 'remoteCLIKey');
$remoteCLIKeyInput = $_GET['remoteCLIKey'] ?? null;
if (!(isCommandLineInterface() || ($remoteCLIKey != '' && $remoteCLIKey == $remoteCLIKeyInput))) {
    echo __('This script cannot be run from a browser, only via CLI.');
} else {
    $count = 0;

    //Scan through every user to correct own status
    $userGateway = $container->get(UserGateway::class);
    $userStatusLogGateway = $container->get(UserStatusLogGateway::class);

    $sql = 'SELECT tawasulPersonID, status, dateEnd, dateStart, tawasulRoleIDAll FROM tawasulPerson ORDER BY tawasulPersonID';
    $result = $pdo->select($sql);

    while ($row = $result->fetch()) {
        //Check for status=='Expected' when met or exceeded start date and set to 'Full'
        if ($row['dateStart'] != '' && date('Y-m-d') >= $row['dateStart'] && $row['status'] == 'Expected') {
            $userGateway->update($row['tawasulPersonID'], ['status' => 'Full']);
            $userStatusLogGateway->insert(['tawasulPersonID' => $row['tawasulPersonID'], 'statusOld' => $row['status'], 'statusNew' => 'Full', 'reason' => 'CLI Status Check and Fix']);
        }

        //Check for status=='Full' when end date exceeded, and set to 'Left'
        if ($row['dateEnd'] != '' && date('Y-m-d') > $row['dateEnd'] && $row['status'] == 'Full') {
            $userGateway->update($row['tawasulPersonID'], ['status' => 'Left']);
            $userStatusLogGateway->insert(['tawasulPersonID' => $row['tawasulPersonID'], 'statusOld' => $row['status'], 'statusNew' => 'Left', 'reason' => 'CLI Status Check and Fix']);
        }
    }

    // Look for parents who are set to Full and counts the active children (also catches parents with no children)
    $sql = "SELECT adult.tawasulPersonID,
            COUNT(DISTINCT CASE WHEN NOT child.status='Left' THEN child.tawasulPersonID END) as activeChildren
            FROM tawasulPerson as adult
            JOIN tawasulFamilyAdult ON (adult.tawasulPersonID=tawasulFamilyAdult.tawasulPersonID)
            LEFT JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID)
            LEFT JOIN tawasulPerson as child ON (child.tawasulPersonID=tawasulFamilyChild.tawasulPersonID)
            WHERE adult.status='Full'
            GROUP BY adult.tawasulPersonID";
    $result = $pdo->select($sql);

    while ($row = $result->fetch()) {
        // Skip parents who have any active children
        if ($row['activeChildren'] > 0) continue;

        // Mark parents as Left only if they don't have other non-parent roles

            $data = array('tawasulPersonID' => $row['tawasulPersonID']);
            $sql = "UPDATE tawasulPerson SET tawasulPerson.status='Left'
                    WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID
                    AND (SELECT COUNT(*) FROM tawasulRole WHERE FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll) AND category<>'Parent') = 0";
            $resultUpdate = $connection2->prepare($sql);
            $resultUpdate->execute($data);

        // Add the number of updated rows to the count
        $count += $resultUpdate->rowCount();
    }

    // Raise a new notification event
    $event = new NotificationEvent('User Admin', 'User Status Check and Fix');

    $event->setNotificationText(sprintf(__('A User Admin CLI script has run, updating %1$s users.'), $count));
    $event->setActionLink('/index.php?q=/modules/TawasulUserAdmin/user_manage.php');

    //Notify admin
    $event->addRecipient($session->get('organisationAdministrator'));

    // Send all notifications
    $sendReport = $event->sendNotifications($pdo, $session);

    // Output the result to terminal
    echo sprintf('Sent %1$s notifications: %2$s inserts, %3$s updates, %4$s emails sent, %5$s emails failed.', $sendReport['count'], $sendReport['inserts'], $sendReport['updates'], $sendReport['emailSent'], $sendReport['emailFailed'])."\n";
}
