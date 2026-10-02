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

use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;
use Tos\Module\TawasulReports\Domain\ReportingValueGateway;
use Tos\Module\TawasulReports\Domain\ReportingProgressGateway;
use Tos\Module\TawasulReports\Domain\ReportingScopeGateway;
use Tos\Module\TawasulReports\Domain\ReportingCriteriaGateway;
use Tos\Module\TawasulReports\Domain\ReportingAccessGateway;
use TawasulOS\Data\Validator;
use TawasulOS\FileUploader;
use TawasulOS\Contracts\Filesystem\FileHandler;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$urlParams = [
    'tawasulReportingCycleID' => $_POST['tawasulReportingCycleID'] ?? '',
    'tawasulReportingScopeID' => $_POST['tawasulReportingScopeID'] ?? '',
    'scopeTypeID' => $_POST['scopeTypeID'] ?? '',
    'tawasulPersonID' => $_POST['tawasulPersonID'] ?? '',
    'allStudents' => $_POST['allStudents'] ?? '',
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reporting_write.php&'.http_build_query($urlParams);

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_write.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;
    $reportingValueGateway = $container->get(ReportingValueGateway::class);
    $reportingProgressGateway = $container->get(ReportingProgressGateway::class);
    $reportingCriteriaGateway = $container->get(ReportingCriteriaGateway::class);
    $reportingAccessGateway = $container->get(ReportingAccessGateway::class);
    $fileUploader = $container->get(FileUploader::class);
    $fileHandler = $container->get(FileHandler::class);
    
    $values = $_POST['value'] ?? [];

    // Validate the required values are present
    if (empty($urlParams['tawasulReportingCycleID']) || empty($urlParams['tawasulReportingScopeID']) || empty($urlParams['scopeTypeID']) || empty($values)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $reportingScope = $container->get(ReportingScopeGateway::class)->getByID($urlParams['tawasulReportingScopeID']);
    $reportingCycle = $container->get(ReportingCycleGateway::class)->getByID($urlParams['tawasulReportingCycleID']);
    if (empty($reportingCycle) || empty($reportingScope)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // ACCESS CHECK: overall check (for high-level access) or per-scope check for general access
    $accessCheck = $reportingAccessGateway->getAccessToScopeByPerson($urlParams['tawasulReportingScopeID'], $session->get('tawasulPersonID'));
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == 'Write Reports_editAll') {
        $reportingOpen = ($accessCheck['reportingOpen'] ?? 'N') == 'Y';
        $canAccessReport = true;
        $canWriteReport = true;
    } elseif ($highestAction == 'Write Reports_mine') {
        $writeCheck = $reportingAccessGateway->getAccessToScopeAndCriteriaGroupByPerson($urlParams['tawasulReportingScopeID'], $reportingScope['scopeType'], $urlParams['scopeTypeID'], $session->get('tawasulPersonID'));
        $reportingOpen = ($writeCheck['reportingOpen'] ?? 'N') == 'Y';
        $canAccessReport = ($accessCheck['canAccess'] ?? 'N') == 'Y';
        $canWriteReport = $reportingOpen && ($writeCheck['canWrite'] ?? 'N') == 'Y';
    }

    if (empty($canAccessReport) || !$canWriteReport) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    $data = [
        'tawasulReportingCycleID'    => $urlParams['tawasulReportingCycleID'],
        'tawasulReportingCriteriaID' => $urlParams['tawasulReportingScopeID'],
        'tawasulSchoolYearID'        => $reportingCycle['tawasulSchoolYearID'],
        'tawasulCourseClassID'       => $reportingScope['scopeType'] == 'Course' ? $urlParams['scopeTypeID'] : '',
        'tawasulPersonIDCreated'     => $session->get('tawasulPersonID'),
    ];
    
    // Insert or update each record
    foreach ($values as $tawasulReportingCriteriaID => $value) {
        $data['tawasulReportingCriteriaID'] = $tawasulReportingCriteriaID;
        $data['value'] = $data['comment'] = $data['tawasulScaleGradeID'] = null;

        $criteriaType = $reportingCriteriaGateway->getCriteriaTypeByID($tawasulReportingCriteriaID);
        $criteriaOptions = !empty($criteriaType['options']) ? json_decode($criteriaType['options'], true) : [];

        $fileMetaData = null;
        if ($criteriaType['valueType'] == 'Comment' || $criteriaType['valueType'] == 'Remark') {
            $data['comment'] = $value;
        } elseif ($criteriaType['valueType'] == 'Grade Scale') {
            $data['value'] = $reportingValueGateway->getGradeScaleValueByID($value);
            $data['tawasulScaleGradeID'] = $value;
        } elseif ($criteriaType['valueType'] == 'Image') {
            // Check if a new file is being uploaded
            if (!empty($_FILES['file'.$tawasulReportingCriteriaID]['tmp_name'])) {
                $data['value'] = $fileUploader->uploadAndResizeImage($_FILES['file'.$tawasulReportingCriteriaID], 'reportFile', $criteriaOptions['imageSize'] ?? 1024, $criteriaOptions['imageQuality'] ?? 80);

                // Get file metadata for tracking
                if (!empty($data['value'])) {
                    $fileMetaData = $fileUploader->getFileMetaData($data['value']);
                }
            } else {
                $data['value'] = $value;
            }
        } else {
            $data['value'] = $value;
        }

        $tawasulReportingValueID = $reportingValueGateway->insertAndUpdate($data, [
            'value' => $data['value'],
            'comment' => $data['comment'],
            'tawasulScaleGradeID' => $data['tawasulScaleGradeID'],
            'tawasulPersonIDModified' => $session->get('tawasulPersonID'),
            'timestampModified' => date('Y-m-d H:i:s'),
        ]);
        
        // Check if insert/update was successful
        if (empty($tawasulReportingValueID)) {
            $partialFail = true;
            continue;
        }

        // Record file tracking after successful insert/update
        if (!empty($fileMetaData) && !empty($tawasulReportingValueID)) {
            $tawasulFileID = $fileHandler->recordFileUpload($fileMetaData, 'tawasulReportingValue', $tawasulReportingValueID, 'value');

            if (empty($tawasulFileID)) {
                $partialFail = true;
            }
        }

        // Handle file deletion when user removes image
        if (empty($data['value']) && !empty($tawasulReportingValueID)) {
            $deleted = $fileHandler->deleteFile('tawasulReportingValue', $tawasulReportingValueID, 'value');
        }
    }

    // Update progress
    // $dataProgress = [
    //     'tawasulReportingScopeID' => $urlParams['tawasulReportingScopeID'],
    //     'tawasulYearGroupID'      => $reportingScope['scopeType'] == 'Year Group' ? $urlParams['scopeTypeID'] : null,
    //     'tawasulFormGroupID'      => $reportingScope['scopeType'] == 'Form Group' ? $urlParams['scopeTypeID'] : null,
    //     'tawasulCourseClassID'    => $reportingScope['scopeType'] == 'Course' ? $urlParams['scopeTypeID'] : '',
    //     'tawasulPersonIDStudent'  => $tawasulPersonIDStudent,
    //     'status'               => !empty($_POST['complete'])? 'Complete' : 'In Progress',
    // ];
    // $updated = $reportingProgressGateway->insertAndUpdate($dataProgress, [
    //     'status' => $dataProgress['status'],
    // ]);

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
}
