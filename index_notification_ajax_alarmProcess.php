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

use TawasulOS\Domain\System\AlarmGateway;
use TawasulOS\Http\Url;

//TawasulOS system-wide includes
require_once __DIR__ . '/tawasul.php';

$tawasulAlarmID = $_GET['tawasulAlarmID'] ?? '';
$URL = Url::fromRoute();

//Proceed!
if (!$session->has('tawasulPersonID') || !$session->has('tawasulRoleIDCurrent')) {
    header("Location: {$URL->withReturn('error0')}");
    exit;
} elseif (empty($tawasulAlarmID)) {
    header("Location: {$URL}");
} else {
    //Check alarm
    $alarmGateway = $container->get(AlarmGateway::class);

    $alarm = $alarmGateway->getByID($tawasulAlarmID);

    if (!empty($alarm)) {
        //Check confirmation of alarm
        $alarmConfirm =  $alarmGateway->getAlarmConfirmationByPerson($alarm['tawasulAlarmID'], $session->get('tawasulPersonID'));

        if (empty($alarmConfirm)) {
            //Insert confirmation
            $dataConfirm =['tawasulAlarmID' => $alarm['tawasulAlarmID'], 'tawasulPersonID' => $session->get('tawasulPersonID'), 'timestamp' => date('Y-m-d H:i:s')];
            $alarmGateway->insertAlarmConfirm($dataConfirm);
        }
    }

    //Success 0
    header("Location: {$URL}");
}
