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

use Tos\Module\TawasulReports\Domain\ReportArchiveGateway;
use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$validator = $container->get(Validator::class);
$_POST = $validator->sanitize($_POST);

$tawasulReportArchiveID = $_POST['tawasulReportArchiveID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/archive_manage_edit.php&tawasulReportArchiveID='.$tawasulReportArchiveID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/archive_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $reportArchiveGateway = $container->get(ReportArchiveGateway::class);
    
    $path = $_POST['path'] ?? '';
    if (substr($path, 0, 8) != '/uploads') {
        $path = '/uploads/' . trim($path, ' /');
    }

    $securePath = $validator->sanitizeFilePath($path, $session->get('absolutePath'));
    if (empty($securePath)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $data = [
        'name'             => $_POST['name'] ?? '',
        'path'             => $securePath ?? '',
        'readonly'         => $_POST['readonly'] ?? 'Y',
        'viewableStaff'    => $_POST['viewableStaff'] ?? 'N',
        'viewableStudents' => $_POST['viewableStudents'] ?? 'N',
        'viewableParents'  => $_POST['viewableParents'] ?? 'N',
        'viewableOther'    => $_POST['viewableOther'] ?? 'N',
    ];

    // Validate the required values are present
    if (empty($tawasulReportArchiveID) || empty($data['name'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    if (!$reportArchiveGateway->exists($tawasulReportArchiveID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$reportArchiveGateway->unique($data, ['name'], $tawasulReportArchiveID)) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $updated = $reportArchiveGateway->update($tawasulReportArchiveID, $data);

    $URL .= !$updated
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}
