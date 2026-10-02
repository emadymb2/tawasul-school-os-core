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

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reporting_access_manage_add.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_access_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $reportArchiveGateway = $container->get(ReportingAccessGateway::class);

    $data = [
        'tawasulReportingCycleID' => $_POST['tawasulReportingCycleID'] ?? '',
        'tawasulRoleIDList'       => isset($_POST['tawasulRoleIDList']) ? implode(',', $_POST['tawasulRoleIDList']) : '',
        'dateStart'              => isset($_POST['dateStart'])? Format::dateConvert($_POST['dateStart']) : null,
        'dateEnd'                => isset($_POST['dateEnd'])? Format::dateConvert($_POST['dateEnd']) : null,
        'canWrite'               => $_POST['canWrite'] ?? '',
        'canProofRead'           => $_POST['canProofRead'] ?? '',
        'accessType'             => 'Role',
    ];

    $data['tawasulReportingScopeIDList'] = is_array($_POST['tawasulReportingScopeID'])? implode(',', $_POST['tawasulReportingScopeID']) : '';

    // Validate the required values are present
    if (empty($data['tawasulReportingCycleID']) || empty($data['tawasulReportingScopeIDList']) || empty($data['tawasulRoleIDList'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$reportArchiveGateway->unique($data, ['tawasulReportingCycleID', 'tawasulRoleIDList'])) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Create the record
    $tawasulReportingAccessID = $reportArchiveGateway->insert($data);

    $URL .= !$tawasulReportingAccessID
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}&editID=$tawasulReportingAccessID");
}
