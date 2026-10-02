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

use Tos\Module\TawasulReports\Domain\ReportGateway;
use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reports_manage_add.php&tawasulSchoolYearID='.$tawasulSchoolYearID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reports_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $reportGateway = $container->get(ReportGateway::class);

    $data = [
        'tawasulSchoolYearID'     => $_POST['tawasulSchoolYearID'] ?? '',
        'tawasulReportArchiveID'  => $_POST['tawasulReportArchiveID'] ?? '',
        'tawasulReportingCycleID' => $_POST['tawasulReportingCycleID'] ?? null,
        'tawasulReportTemplateID' => $_POST['tawasulReportTemplateID'] ?? '',
        'name'                   => $_POST['name'] ?? '',
        'active'                 => $_POST['active'] ?? 'Y',
        'tawasulYearGroupIDList'  => isset($_POST['tawasulYearGroupIDList'])? implode(',', $_POST['tawasulYearGroupIDList']) : null,
    ];

    if (!empty($_POST['accessDate'])) {
        $data['accessDate'] = Format::dateConvert($_POST['accessDate']).' '.($_POST['accessTime'] ?? '00:00');
    }

    // Validate the required values are present
    if (empty($data['tawasulSchoolYearID']) || empty($data['tawasulReportTemplateID']) || empty($data['name'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$reportGateway->unique($data, ['tawasulSchoolYearID', 'name'])) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Create the record
    $tawasulReportID = $reportGateway->insert($data);

    $URL .= !$tawasulReportID
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}&editID=$tawasulReportID");
}
