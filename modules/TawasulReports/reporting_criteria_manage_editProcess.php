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

use Tos\Module\TawasulReports\Domain\ReportingCriteriaGateway;
use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulReportingCriteriaID = $_POST['tawasulReportingCriteriaID'] ?? '';
$urlParams = [
    'tawasulReportingScopeID' => $_POST['tawasulReportingScopeID'] ?? '',
    'tawasulReportingCycleID' => $_POST['tawasulReportingCycleID'] ?? '',
    'tawasulYearGroupID' => $_POST['tawasulYearGroupID'] ?? null,
    'tawasulFormGroupID' => $_POST['tawasulFormGroupID'] ?? null,
    'tawasulCourseID' => $_POST['tawasulCourseID'] ?? null,
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reporting_criteria_manage.php&'.http_build_query($urlParams);

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_criteria_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $reportingCriteriaGateway = $container->get(ReportingCriteriaGateway::class);
    $groupID = $_POST['groupID'] ?? null;
    $detach = $_POST['detach'] ?? null;

    $data = [
        'tawasulReportingCriteriaTypeID' => $_POST['tawasulReportingCriteriaTypeID'] ?? '',
        'name'                          => $_POST['name'] ?? '',
        'description'                   => $_POST['description'] ?? '',
        'category'                      => $_POST['category'] ?? '',
        'target'                        => $_POST['target'] ?? '',
    ];
    
    // Allow users to detach a record from it's group
    if ($detach) {
        $data['groupID'] = null;
        $groupID = null;
    }

    // Validate the required values are present
    if (empty($tawasulReportingCriteriaID) || empty($urlParams['tawasulReportingScopeID']) || empty($data['name'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    if (!$reportingCriteriaGateway->exists($tawasulReportingCriteriaID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Update the record (or grouped records)
    if (!empty($groupID)) {
        $updated = $reportingCriteriaGateway->updateWhere(['tawasulReportingCycleID' => $urlParams['tawasulReportingCycleID'], 'groupID' => $groupID], $data);
    } else {
        $updated = $reportingCriteriaGateway->update($tawasulReportingCriteriaID, $data);
    }

    $URL .= !$updated
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}
