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

use Tos\Module\TawasulReports\Domain\ReportingAccessGateway;
use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulReportingAccessID = $_POST['tawasulReportingAccessID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reporting_access_manage_edit.php&tawasulReportingAccessID='.$tawasulReportingAccessID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_access_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $reportingAccessGateway = $container->get(ReportingAccessGateway::class);

    $data = [
        'tawasulRoleIDList' => isset($_POST['tawasulRoleIDList']) ? implode(',', $_POST['tawasulRoleIDList']) : '',
        'dateStart'        => isset($_POST['dateStart'])? Format::dateConvert($_POST['dateStart']) : null,
        'dateEnd'          => isset($_POST['dateEnd'])? Format::dateConvert($_POST['dateEnd']) : null,
        'canWrite'         => $_POST['canWrite'] ?? '',
        'canProofRead'     => $_POST['canProofRead'] ?? '',
    ];

    $data['tawasulReportingScopeIDList'] = is_array($_POST['tawasulReportingScopeID'])? implode(',', $_POST['tawasulReportingScopeID']) : '';

    // Validate the required values are present
    if (empty($tawasulReportingAccessID) || empty($data['tawasulReportingScopeIDList'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    if (!$reportingAccessGateway->exists($tawasulReportingAccessID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $updated = $reportingAccessGateway->update($tawasulReportingAccessID, $data);

    $URL .= !$updated
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}
