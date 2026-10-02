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

use TawasulOS\Services\Format;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\Staff\StaffDutyGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStaff/staff_duty_edit.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_duty_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!

    $dutyList = $_POST['dutyList'] ?? null;
    $order = $_POST['order'] ?? null;

    if (is_null($dutyList) || is_null($order)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    }

    $staffDutyGateway = $container->get(StaffDutyGateway::class);
    $dutyIDList = [];

    // Add or update new time slots in the duty roster
    foreach ($dutyList as $index => $duty) {
        $sequenceNumber = array_search($index, $order);
        $daysOfWeek = !empty($duty['tawasulDaysOfWeekIDList'])
            ? implode(',', $duty['tawasulDaysOfWeekIDList']) 
            : '';

        $data = [
            'tawasulDaysOfWeekIDList' => $daysOfWeek,
            'name'                   => $duty['name'],
            'nameShort'              => $duty['nameShort'],
            'type'                   => $duty['type'],
            'timeStart'              => $duty['timeStart'],
            'timeEnd'                => $duty['timeEnd'],
            'sequenceNumber'         => $sequenceNumber,
        ];

        if (!empty($duty['tawasulStaffDutyID'])) {
            // $values = $staffDutyGateway->getByID($duty['tawasulStaffDutyID']);
            $tawasulStaffDutyID = $duty['tawasulStaffDutyID'];
            $staffDutyGateway->update($tawasulStaffDutyID, $data);
        } else {
            $tawasulStaffDutyID = $staffDutyGateway->insert($data);
            $tawasulStaffDutyID = str_pad($tawasulStaffDutyID, 6, '0', STR_PAD_LEFT);
        }

        $dutyIDList[] = $tawasulStaffDutyID;
    }

    // Cleanup any removed time slots
    $staffDutyGateway->deleteDutyNotInList($dutyIDList);

    $URL .= '&return=success0';
    header("Location: {$URL}");

}
