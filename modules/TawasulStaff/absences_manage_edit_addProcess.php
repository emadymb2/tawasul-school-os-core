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

use TawasulOS\Domain\Staff\StaffAbsenceGateway;
use TawasulOS\Domain\Staff\StaffAbsenceDateGateway;
use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulStaffAbsenceID = $_POST['tawasulStaffAbsenceID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStaff/absences_manage_edit.php&tawasulStaffAbsenceID='.$tawasulStaffAbsenceID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/absences_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} elseif (empty($tawasulStaffAbsenceID) || empty($_POST['date'])) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

    $values = $staffAbsenceGateway->getByID($tawasulStaffAbsenceID);

    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $date = Format::dateConvert($_POST['date']);

    $data = [
        'tawasulStaffAbsenceID' => $tawasulStaffAbsenceID,
        'date'                 => $date,
        'allDay'               => $_POST['allDay'] ?? 'N',
        'timeStart'            => $_POST['timeStart'] ?? null,
        'timeEnd'              => $_POST['timeEnd'] ?? null,
    ];

    if ($staffAbsenceDateGateway->unique($data, ['tawasulStaffAbsenceID', 'date'])) {
        $inserted = $staffAbsenceDateGateway->insert($data);
    } else {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $URL .= !$inserted
        ? '&return=error2'
        : '&return=success0';

    header("Location: {$URL}");
}
