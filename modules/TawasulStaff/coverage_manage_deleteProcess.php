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

use TawasulOS\Domain\Staff\StaffCoverageGateway;
use TawasulOS\Domain\Staff\StaffCoverageDateGateway;
use TawasulOS\Domain\Staff\StaffAbsenceGateway;

require_once __DIR__ . '/../../tawasul.php';

$tawasulStaffCoverageID = $_POST['tawasulStaffCoverageID'] ?? '';
$search = $_POST['search'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStaff/coverage_manage.php&search='.$search;

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/coverage_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} elseif (empty($tawasulStaffCoverageID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffCoverageDateGateway = $container->get(StaffCoverageDateGateway::class);
    $values = $staffCoverageGateway->getByID($tawasulStaffCoverageID);

    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $coverageDates = $staffCoverageDateGateway->selectDatesByCoverage($tawasulStaffCoverageID)->fetchAll();
    $partialFail = false;

    // Delete each date first
    foreach ($coverageDates as $date) {
        $partialFail &= !$staffCoverageDateGateway->delete($date['tawasulStaffCoverageDateID']);
    }

    // Then delete the coverage itself
    $partialFail &= !$staffCoverageGateway->delete($tawasulStaffCoverageID);

    // Check for other coverage linked to this absence
    $otherCoverage = $staffCoverageGateway->selectBy(['tawasulStaffAbsenceID' => $values['tawasulStaffAbsenceID']])->fetchAll();

    // Update the original absence to flag it as not requiring coverage
    if (empty($otherCoverage) && !empty($values['tawasulStaffAbsenceID'])) {
        $staffAbsenceGateway->update($values['tawasulStaffAbsenceID'], ['coverageRequired' => 'N']);
    }

    $URL .= $partialFail
        ? '&return=warning1'
        : '&return=success0';

    header("Location: {$URL}");
}
