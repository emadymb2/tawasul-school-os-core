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
use TawasulOS\Domain\Staff\StaffDutyPersonGateway;

require_once __DIR__ . '/../../tawasul.php';

$_GET = $container->get(Validator::class)->sanitize($_GET);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStaff/staff_duty_edit.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_duty_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!

    $tawasulStaffDutyPersonID = $_GET['tawasulStaffDutyPersonID'] ?? null;
    $data = [
        'tawasulStaffDutyID' => $_GET['tawasulStaffDutyID'] ?? null,
        'tawasulDaysOfWeekID' => $_GET['tawasulDaysOfWeekID'] ?? null,
    ];

    if (empty($tawasulStaffDutyPersonID) || empty($data['tawasulDaysOfWeekID']) || empty($data['tawasulStaffDutyID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    }

    $staffDutyPersonGateway = $container->get(StaffDutyPersonGateway::class);
    $updated = $staffDutyPersonGateway->update($tawasulStaffDutyPersonID, $data);

    echo $updated;
}
