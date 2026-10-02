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

use Tos\Module\TawasulReports\Domain\ReportingProofGateway;
use Tos\Module\TawasulReports\Domain\ReportingValueGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$mode = $_POST['mode'] ?? '';
$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$tawasulFormGroupID = $_POST['tawasulFormGroupID'] ?? '';
$page = $_POST['page'] ?? '';
$filter = $_POST['filter'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reporting_proofread.php&mode='.$mode.'&tawasulPersonID='.$tawasulPersonID.'&tawasulFormGroupID='.$tawasulFormGroupID.'&page='.$page.'&filter='.$filter;

if (!empty($_POST['override'])) {
    $URL .= '&override='.($_POST['override'] ?? '');
}

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_proofread.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;
    $updated = false;

    $comments = $_POST['comment'] ?? [];
    $statuses = $_POST['status'] ?? [];
    $reasons = $_POST['reason'] ?? [];
    $proofs = $_POST['proof'] ?? [];

    $reportingProofGateway = $container->get(ReportingProofGateway::class);
    $reportingValueGateway = $container->get(ReportingValueGateway::class);

    foreach ($statuses as $tawasulReportingValueID => $status) {
        if ($status == 'Accepted' || $status == 'Declined' || $status == 'Revised') {
            // Action and update the proof record
            $tawasulReportingProofID = $proofs[$tawasulReportingValueID];
            if (!empty($tawasulReportingProofID)) {
                $data = [
                    'status'                 => $status == 'Revised' ? 'Accepted' : $status,
                    'tawasulPersonIDActioned' => $session->get('tawasulPersonID'),
                    'timestampActioned'      => date('Y-m-d H:i:s'),
                ];
                $updated = $reportingProofGateway->update($tawasulReportingProofID, $data);
            }

            // Update the comment text of the report using the proof text
            if ($status == 'Accepted' && !empty($tawasulReportingProofID)) {
                $proof = $reportingProofGateway->getByID($tawasulReportingProofID);
                $updated &= $reportingValueGateway->update($tawasulReportingValueID, [
                    'comment' => $proof['comment'],
                ]);
            }

            // Update the comment text of the report using the revised text
            if ($status == 'Revised' && !empty($comments[$tawasulReportingValueID])) {
                $updated &= $reportingValueGateway->update($tawasulReportingValueID, [
                    'comment' => $comments[$tawasulReportingValueID],
                ]);
            }
        } elseif ($status == 'Done' || $status == 'Edited') {
            // Submit the proof read status and comment
            $data = [
                'tawasulReportingValueID' => $tawasulReportingValueID,
                'status'                 => $status,
                'comment'                => $status == 'Edited' ? ($comments[$tawasulReportingValueID] ?? '') : '',
                'reason'                 => $reasons[$tawasulReportingValueID] ?? '',
                'tawasulPersonIDProofed'  => $session->get('tawasulPersonID'),
                'timestampProofed'       => date('Y-m-d H:i:s'),
            ];

            $updated = $reportingProofGateway->insertAndUpdate($data, [
                'status' => $data['status'],
                'comment' => $data['comment'],
                'reason' => $data['reason'],
                'tawasulPersonIDProofed' => $data['tawasulPersonIDProofed'],
                'timestampProofed' => $data['timestampProofed'],
            ]);
        }
        
        $partialFail &= !$updated;
    }

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
}
