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
use TawasulOS\Domain\Staff\StaffAbsenceGateway;
use TawasulOS\Domain\Staff\StaffCoverageGateway;
use Tos\Module\TawasulStaff\AbsenceNotificationProcess;
use Tos\Module\TawasulStaff\CoverageNotificationProcess;
use TawasulOS\Domain\System\SettingGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulStaffAbsenceID = $_POST['tawasulStaffAbsenceID'] ?? '';
$status = $_POST['status'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStaff/absences_approval_action.php&tawasulStaffAbsenceID='.$tawasulStaffAbsenceID;
$URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStaff/absences_approval.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/absences_approval_action.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} elseif (empty($tawasulStaffAbsenceID) || empty($status)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);
    $absence = $staffAbsenceGateway->getByID($tawasulStaffAbsenceID);

    $coverageMode =  $container->get(SettingGateway::class)->getSettingByScope('Staff', 'coverageMode');

    if (empty($absence)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    if ($absence['status'] != 'Pending Approval') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    if ($absence['tawasulPersonIDApproval'] != $session->get('tawasulPersonID')) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    $data = [
        'status'            => $status,
        'timestampApproval' => date('Y-m-d H:i:s'),
        'notesApproval'     => $_POST['notesApproval'],
    ];

    $updated = $staffAbsenceGateway->update($tawasulStaffAbsenceID, $data);

    if ($updated == false) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Start a background process for notifications
    $process = $container->get(AbsenceNotificationProcess::class);
    $process->startAbsenceApproval($tawasulStaffAbsenceID);

    if ($status == 'Approved') {
        if ($absence['coverageRequired'] == 'N') {
            $process->startNewAbsence($tawasulStaffAbsenceID);
        } else {
            $process = $container->get(CoverageNotificationProcess::class);
            
            $coverageList = $staffCoverageGateway->selectCoverageByAbsenceID($tawasulStaffAbsenceID)->fetchAll(\PDO::FETCH_COLUMN);
            $process->startNewAbsenceWithCoverageRequest($coverageList);

            // Notify individuals or broadcast this request
            if ($coverageMode == 'Requested') {
                $process->startApprovedRequest($coverageList);
            }
        }
    }

    $URLSuccess .= '&return=success0';

    header("Location: {$URLSuccess}");
    exit;
}
