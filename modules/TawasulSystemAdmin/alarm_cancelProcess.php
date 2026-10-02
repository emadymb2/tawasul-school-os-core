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
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulSystemAdmin/alarm.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/alarm.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    $tawasulAlarmID = $_GET['tawasulAlarmID'] ?? '';

    //Validate Inputs
    if (empty($tawasulAlarmID)) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
    } else {
        $alarmGateway = $container->get(AlarmGateway::class);
        $settingGateway = $container->get(SettingGateway::class);
        //DEAL WITH ALARM SETTING
        //Write setting to database
        $dataWhere = ['scope' => 'System', 'name' => 'alarm'];
        $settingGateway->updateWhere($dataWhere, ['value' => 'None']);
        //Write alarm to database
        $alarmGateway->update($tawasulAlarmID, ['status' => 'Past', 'timestampEnd' => date('Y-m-d H:i:s')]);

        getSystemSettings($guid, $connection2);
        $URL .= '&return=success0';
        header("Location: {$URL}");
    }
}
