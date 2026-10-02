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

$scopeType = $_POST['scopeType'] ?? '';
$urlParams = [
    'tawasulReportingScopeID' => $_POST['tawasulReportingScopeID'] ?? '',
    'tawasulReportingCycleID' => $_POST['tawasulReportingCycleID'] ?? '',
    'referer' => $_REQUEST['referer'] ?? '',
];
$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reporting_criteria_manage_addMultiple.php&'.http_build_query($urlParams);

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_criteria_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;
    $reportingCriteriaGateway = $container->get(ReportingCriteriaGateway::class);

    $data = [
        'tawasulReportingCycleID'        => $urlParams['tawasulReportingCycleID'],
        'tawasulReportingScopeID'        => $urlParams['tawasulReportingScopeID'],
        'tawasulReportingCriteriaTypeID' => $_POST['tawasulReportingCriteriaTypeID'] ?? '',
        'tawasulYearGroupID'             => null,
        'tawasulFormGroupID'             => null,
        'tawasulCourseID'                => null,
        'target'                        => $_POST['target'] ?? '',
        'name'                          => $_POST['name'] ?? '',
        'description'                   => $_POST['description'] ?? '',
        'category'                      => $_POST['category'] ?? '',
        'groupID'                       => bin2hex(random_bytes(22)),
    ];

    // Validate the required values are present
    if (empty($scopeType) || empty($data['name']) || empty($data['tawasulReportingScopeID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    if ($scopeType == 'Year Group') {
        $identifier = 'tawasulYearGroupID';
    } elseif ($scopeType == 'Form Group') {
        $identifier = 'tawasulFormGroupID';
    } elseif ($scopeType == 'Course') {
        $identifier = 'tawasulCourseID';
    }

    // Create one record per selected year group/form group/course
    $group = $_POST[$identifier] ?? [];
    foreach ($group as $scopeTypeID) {
        $data[$identifier] = $scopeTypeID;
        $data['sequenceNumber'] = $reportingCriteriaGateway->getHighestSequenceNumberByScope($data['tawasulReportingScopeID'], $scopeType, $scopeTypeID) + 1;
        $inserted = $reportingCriteriaGateway->insert($data);
        $partialFail &= !$inserted;
    }

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}&scopeTypeID=".implode(',', $group));
}
