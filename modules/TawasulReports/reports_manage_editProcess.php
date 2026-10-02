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
$tawasulReportID = $_POST['tawasulReportID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reports_manage_edit.php&tawasulReportID='.$tawasulReportID.'&tawasulSchoolYearID='.$tawasulSchoolYearID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reports_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $reportGateway = $container->get(ReportGateway::class);

    $data = [
        'tawasulSchoolYearID'     => $_POST['tawasulSchoolYearID'] ?? '',
        'name'                   => $_POST['name'] ?? '',
        'active'                 => $_POST['active'] ?? 'Y',
        'tawasulReportArchiveID'  => $_POST['tawasulReportArchiveID'] ?? null,
        'tawasulReportingCycleID' => $_POST['tawasulReportingCycleID'] ?? null,
        'queryBuilderQueryID'    => $_POST['queryBuilderQueryID'] ?? null,
        'tawasulYearGroupIDList'  => isset($_POST['tawasulYearGroupIDList'])? implode(',', $_POST['tawasulYearGroupIDList']) : null,
    ];

    if (!empty($_POST['accessDate'])) {
        $data['accessDate'] = Format::dateConvert($_POST['accessDate']).' '.($_POST['accessTime'] ?? '00:00');
    }

    // Validate the required values are present
    if (empty($tawasulSchoolYearID) || empty($data['name'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    if (!$reportGateway->exists($tawasulReportID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$reportGateway->unique($data, ['tawasulSchoolYearID', 'name'], $tawasulReportID)) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $updated = $reportGateway->update($tawasulReportID, $data);

    $URL .= !$updated
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}
