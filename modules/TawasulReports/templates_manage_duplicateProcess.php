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

use TawasulOS\Services\Format;
use Tos\Module\TawasulReports\Domain\ReportTemplateGateway;
use Tos\Module\TawasulReports\Domain\ReportTemplateSectionGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulReportTemplateID = $_POST['tawasulReportTemplateID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/templates_manage_duplicate.php&tawasulReportTemplateID='.$tawasulReportTemplateID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/templates_manage_duplicate.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $reportTemplateGateway = $container->get(ReportTemplateGateway::class);
    $reportTemplateSectionGateway = $container->get(ReportTemplateSectionGateway::class);

    $data = [
        'name'    => $_POST['name'] ?? '',
        'context' => $_POST['context'] ?? '',
    ];

    // Validate the required values are present
    if (empty($tawasulReportTemplateID) || empty($data['name']) || empty($data['context'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
    
    // Validate the database relationships exist
    $values = $reportTemplateGateway->getByID($tawasulReportTemplateID);
    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$reportTemplateGateway->unique($data, ['name'], $tawasulReportTemplateID)) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Update duplicated values
    $data['orientation'] = $values['orientation'];
    $data['pageSize'] = $values['pageSize'];
    $data['marginX'] = $values['marginX'];
    $data['marginY'] = $values['marginY'];
    $data['stylesheet'] = $values['stylesheet'];
    $data['flags'] = $values['flags'];
    $data['config'] = $values['config'];

    // Create the record
    $tawasulReportTemplateIDNew = $reportTemplateGateway->insert($data);
    $failedSections = 0;

    if (empty($tawasulReportTemplateIDNew)) {
        $URL .= "&return=error2";
        header("Location: {$URL}");
    }

    // Duplicate the template sections
    $sections = $reportTemplateSectionGateway->selectBy(['tawasulReportTemplateID' => $tawasulReportTemplateID])->fetchAll();
    foreach ($sections as $sectionData) {
        $sectionData['tawasulReportTemplateID'] = $tawasulReportTemplateIDNew;
        $tawasulReportTemplateSectionIDNew = $reportTemplateSectionGateway->insert($sectionData);
        $failedSections += empty($tawasulReportTemplateSectionIDNew);
    }

    $URL .= !empty($failedSections)
        ? "&return=warning1&failedSections=$failedSections"
        : "&return=success0";

    header("Location: {$URL}&editID=$tawasulReportTemplateIDNew");
}
