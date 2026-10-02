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

$urlParams = [
    'tawasulReportingScopeID' => $_POST['tawasulReportingScopeID'] ?? '',
    'tawasulReportingCycleID' => $_POST['tawasulReportingCycleID'] ?? '',
    'tawasulYearGroupID' => $_POST['tawasulYearGroupID'] ?? null,
    'tawasulFormGroupID' => $_POST['tawasulFormGroupID'] ?? null,
    'tawasulCourseID' => $_POST['tawasulCourseID'] ?? null,
    'scopeType' => $_POST['scopeType'] ?? null,
];
$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reporting_criteria_manage_add.php&'.http_build_query($urlParams);

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_criteria_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $reportingCriteriaGateway = $container->get(ReportingCriteriaGateway::class);

    $data = [
        'tawasulReportingCycleID'        => $urlParams['tawasulReportingCycleID'],
        'tawasulReportingScopeID'        => $urlParams['tawasulReportingScopeID'],
        'tawasulReportingCriteriaTypeID' => $_POST['tawasulReportingCriteriaTypeID'] ?? '',
        'tawasulYearGroupID'             => $urlParams['tawasulYearGroupID'],
        'tawasulFormGroupID'             => $urlParams['tawasulFormGroupID'],
        'tawasulCourseID'                => $urlParams['tawasulCourseID'],
        'target'                        => $_POST['target'] ?? '',
        'name'                          => $_POST['name'] ?? '',
        'description'                   => $_POST['description'] ?? '',
        'category'                      => $_POST['category'] ?? '',
    ];

    switch ($urlParams['scopeType']) {
        case 'Year Group': $scopeTypeID = $data['tawasulYearGroupID']; break;
        case 'Form Group': $scopeTypeID = $data['tawasulFormGroupID']; break;
        case 'Course':     $scopeTypeID = $data['tawasulCourseID']; break;
    }
    $data['sequenceNumber'] = $reportingCriteriaGateway->getHighestSequenceNumberByScope($urlParams['tawasulReportingScopeID'], $urlParams['scopeType'], $scopeTypeID) + 1;

    // Validate the required values are present
    if (empty($data['name']) || empty($data['tawasulReportingCycleID']) || empty($data['tawasulReportingScopeID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Create the record
    $tawasulReportingCriteriaID = $reportingCriteriaGateway->insert($data);

    $URL .= !$tawasulReportingCriteriaID
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}&editID=$tawasulReportingCriteriaID");
}
