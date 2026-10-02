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
use TawasulOS\Domain\Staff\StaffCoverageGateway;
use TawasulOS\Domain\Staff\StaffCoverageDateGateway;

$_POST['address'] = '/modules/TawasulStaff/absences_manage.php';
$tawasulStaffAbsenceID = $_POST['tawasulStaffAbsenceID'] ?? '';
$search = $_POST['search'] ?? '';

require_once __DIR__ . '/../../tawasul.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStaff/absences_manage.php&search='.$search;

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/absences_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} elseif (empty($tawasulStaffAbsenceID)) {
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

    $absenceDates = $staffAbsenceDateGateway->selectDatesByAbsenceWithCoverage($tawasulStaffAbsenceID)->fetchAll();
    $partialFail = false;

    // First delete any coverage attached to this absence
    $partialFail &= $container->get(StaffCoverageDateGateway::class)->deleteCoverageDatesByAbsenceID($tawasulStaffAbsenceID);
    $partialFail &= $container->get(StaffCoverageGateway::class)->deleteCoverageByAbsenceID($tawasulStaffAbsenceID);

    // Delete each absence date
    foreach ($absenceDates as $log) {
        $partialFail &= $staffAbsenceDateGateway->delete($log['tawasulStaffAbsenceDateID']);
    }

    // Then delete the absence itself
    $partialFail &= $staffAbsenceGateway->delete($tawasulStaffAbsenceID);

    $URL .= $partialFail
        ? '&return=warning1'
        : '&return=success0';

    header("Location: {$URL}");
}
