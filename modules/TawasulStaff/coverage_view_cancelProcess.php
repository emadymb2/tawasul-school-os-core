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
use TawasulOS\Domain\Staff\StaffCoverageDateGateway;
use TawasulOS\Domain\Staff\StaffCoverageGateway;
use Tos\Module\TawasulStaff\CoverageNotificationProcess;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulStaffCoverageID = $_POST['tawasulStaffCoverageID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStaff/coverage_view_cancel.php&tawasulStaffCoverageID='.$tawasulStaffCoverageID;
$URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStaff/coverage_my.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/coverage_view_cancel.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);
    $staffCoverageDateGateway = $container->get(StaffCoverageDateGateway::class);

    $data = [
        'timestampStatus' => date('Y-m-d H:i:s'),
        'notesStatus'     => $_POST['notesStatus'] ?? '',
        'status'          => 'Cancelled',
    ];

    // Validate the required values are present
    if (empty($tawasulStaffCoverageID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $coverage = $staffCoverageGateway->getByID($tawasulStaffCoverageID);

    if (empty($coverage)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // If the coverage is for a particular absence, ensure this exists
    if (!empty($coverage['tawasulStaffAbsenceID'])) {
        $absence = $container->get(StaffAbsenceGateway::class)->getByID($coverage['tawasulStaffAbsenceID']);
        if (empty($absence)) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }
    }

    // Prevent two people cancelling at the same time (?)
    if ($coverage['status'] == 'Cancelled') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Update the database
    $updated = $staffCoverageGateway->update($tawasulStaffCoverageID, $data);

    if (!$updated) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $partialFail = false;

    $coverage = $staffCoverageGateway->getCoverageDetailsByID($tawasulStaffCoverageID);
    $coverageDates = $staffCoverageDateGateway->selectDatesByCoverage($tawasulStaffCoverageID);

    // Unlink any absence dates from the coverage request so they can be re-requested
    foreach ($coverageDates as $coverageDate) {
        $dateData = ['tawasulStaffAbsenceDateID' => null];
        $partialFail &= !$staffCoverageDateGateway->update($coverageDate['tawasulStaffCoverageDateID'], $dateData);
    }

    // Send messages (Mail, SMS) to relevant users
    if ($coverage['requestType'] == 'Assigned' || $coverage['requestType'] == 'Individual' || $coverage['status'] == 'Accepted') {
        $process = $container->get(CoverageNotificationProcess::class);
        $process->startCoverageCancelled($tawasulStaffCoverageID);
    }

    $URLSuccess .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URLSuccess}");
}
