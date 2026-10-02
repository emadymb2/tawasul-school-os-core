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

use TawasulOS\Http\Url;

require_once __DIR__ . '/tawasul.php';

$URL = Url::fromRoute('notifications')->withQueryParam('sidebar', 'false');

if (!$session->has('tawasulPersonID') || !$session->has('tawasulRoleIDCurrent')) {
    header("Location: {$URL->withReturn('error0')}");
    exit;
} elseif (!isset($_GET['tawasulNotificationID'])) {
    header("Location: {$URL->withReturn('error1')}");
    exit;
} else {
    $tawasulNotificationID = $_GET['tawasulNotificationID'] ?? '';

    //Check for existence of notification, beloning to this user
    try {
        $data = array('tawasulNotificationID' => $tawasulNotificationID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
        $sql = 'SELECT * FROM tawasulNotification WHERE tawasulPersonID=:tawasulPersonID AND tawasulNotificationID=:tawasulNotificationID';
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        header("Location: {$URL->withReturn('error2')}");
        exit;
    }

    if ($result->rowCount() != 1) {
        header("Location: {$URL->withReturn('error2')}");
        exit;
    } else {
        //Delete notification
        try {
            $data = array('tawasulNotificationID' => $tawasulNotificationID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
            $sql = 'DELETE FROM tawasulNotification WHERE tawasulPersonID=:tawasulPersonID AND tawasulNotificationID=:tawasulNotificationID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            header("Location: {$URL->withReturn('error2')}");
            exit;
        }

        //Success 0
        header("Location: {$URL->withReturn('success0')}");
        exit;
    }
}
