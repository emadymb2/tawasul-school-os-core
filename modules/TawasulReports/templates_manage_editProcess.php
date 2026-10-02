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

use Tos\Module\TawasulReports\Domain\ReportTemplateGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulReportTemplateID = $_POST['tawasulReportTemplateID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/templates_manage_edit.php&tawasulReportTemplateID='.$tawasulReportTemplateID.'&sidebar=false';

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/templates_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $templateGateway = $container->get(ReportTemplateGateway::class);

    $data = [
        'name'        => $_POST['name'] ?? '',
        'orientation' => $_POST['orientation'] ?? '',
        'pageSize'    => $_POST['pageSize'] ?? '',
        'marginX'     => $_POST['marginX'] ?? '',
        'marginY'     => $_POST['marginY'] ?? '',
        'stylesheet'  => $_POST['stylesheet'] ?? '',
        'flags'       => $_POST['flags'] ?? '',
        'active'     => $_POST['active'] ?? 'Y',
    ];

    $config = [
        'fonts' => $_POST['fonts'] ?? [],
    ];

    $data['config'] = json_encode($config);

    // Validate the required values are present
    if (empty($data['name'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    if (!$templateGateway->exists($tawasulReportTemplateID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$templateGateway->unique($data, ['name'], $tawasulReportTemplateID)) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $updated = $templateGateway->update($tawasulReportTemplateID, $data);

    $URL .= !$updated
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}
