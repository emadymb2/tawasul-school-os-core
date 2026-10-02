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

use TawasulOS\Domain\System\NotificationGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$validator = $container->get(Validator::class);
$_POST = $validator->sanitize($_POST);

$tawasulNotificationEventID = $_POST['tawasulNotificationEventID'] ?? null;
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/notificationSettings_manage_edit.php&tawasulNotificationEventID=".$tawasulNotificationEventID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/notificationSettings_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    if ($tawasulNotificationEventID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $gateway = new NotificationGateway($pdo);

        $result = $gateway->selectNotificationEventByID($tawasulNotificationEventID);
        if ($result->rowCount() != 1) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        $tawasulPersonID = $validator->sanitizeNumeric($_POST['tawasulPersonID'] ?? '');
        $scopeType = $validator->sanitizeAlphaNumeric($_POST['scopeType'] ?? '');
        $scopeID = $validator->sanitizeNumeric($_POST[$scopeType] ?? 0);
        $scopeContext = $validator->sanitizeName($_POST['scopeContext'] ?? '');

        if (empty($tawasulPersonID) || empty($scopeType)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        } else {
            $listener = array(
                'tawasulNotificationEventID' => $tawasulNotificationEventID,
                'tawasulPersonID'            => $tawasulPersonID,
                'scopeType'                 => $scopeType,
                'scopeID'                   => $scopeID,
                'scopeContext'              => $scopeContext,
            );

            $result = $gateway->insertNotificationListener($listener);

            $URL .= '&return=success0';
            header("Location: {$URL}");
            exit;
        }
    }
}
