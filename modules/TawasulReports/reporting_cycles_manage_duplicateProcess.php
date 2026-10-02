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
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\FormGroups\FormGroupGateway;
use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;
use Tos\Module\TawasulReports\Domain\ReportingScopeGateway;
use Tos\Module\TawasulReports\Domain\ReportingCriteriaGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulReportingCycleID = $_POST['tawasulReportingCycleID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reporting_cycles_manage_duplicate.php&tawasulReportingCycleID='.$tawasulReportingCycleID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_cycles_manage_duplicate.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $reportingCycleGateway = $container->get(ReportingCycleGateway::class);
    $reportingScopeGateway = $container->get(ReportingScopeGateway::class);
    $reportingCriteriaGateway = $container->get(ReportingCriteriaGateway::class);
    $formGroupGateway = $container->get(FormGroupGateway::class);
    $courseGateway = $container->get(CourseGateway::class);

    $data = [
        'tawasulSchoolYearID'    => $_POST['tawasulSchoolYearID'] ?? '',
        'name'                  => $_POST['name'] ?? '',
        'nameShort'             => $_POST['nameShort'] ?? '',
        'dateStart'             => $_POST['dateStart'] ?? '',
        'dateEnd'               => $_POST['dateEnd'] ?? '',
        'cycleNumber'           => $_POST['cycleNumber'] ?? '1',
        'cycleTotal'            => $_POST['cycleTotal'] ?? '1',
        'sequenceNumber'        => $_POST['sequenceNumber'] ?? '1',
    ];

    $data['dateStart'] = Format::dateConvert($data['dateStart']);
    $data['dateEnd'] = Format::dateConvert($data['dateEnd']);
    
    // Validate the required values are present
    if (empty($tawasulReportingCycleID) || empty($data['tawasulSchoolYearID']) || empty($data['name']) || empty($data['nameShort'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
    
    // Validate the database relationships exist
    $values = $reportingCycleGateway->getByID($tawasulReportingCycleID);
    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$reportingCycleGateway->unique($data, ['name', 'tawasulSchoolYearID'], $tawasulReportingCycleID)) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Update milestone dates
    $milestones = json_decode($values['milestones'], true);
    foreach ($milestones ?? [] as $index => $milestone) {
        $milestones[$index]['milestoneDate'] = $data['dateStart'];
    }

    // Update duplicated values
    $data['milestones'] = json_encode($milestones);
    $data['notes'] = $values['notes'];
    $data['sequenceNumber'] = $values['sequenceNumber'];
    $data['tawasulYearGroupIDList'] = $values['tawasulYearGroupIDList'];

    // Create the record
    $tawasulReportingCycleIDNew = $reportingCycleGateway->insert($data);
    $failedCriteria = 0;

    // Duplicate the reporting scopes and criteria
    if (!empty($tawasulReportingCycleIDNew)) {
        $scopes = $reportingScopeGateway->selectBy(['tawasulReportingCycleID' => $tawasulReportingCycleID])->fetchAll();
        foreach ($scopes as $scopeData) {
            $scopeData['tawasulReportingCycleID'] = $tawasulReportingCycleIDNew;
            $tawasulReportingScopeIDNew = $reportingScopeGateway->insert($scopeData);

            if (!empty($tawasulReportingScopeIDNew)) {
                $criteria = $reportingCriteriaGateway->selectBy([
                    'tawasulReportingCycleID' => $tawasulReportingCycleID,
                    'tawasulReportingScopeID' => $scopeData['tawasulReportingScopeID']]
                )->fetchAll();

                foreach ($criteria as $criteriaData) {
                    // Grab the form group ID by name if it's in a different school year
                    if (!empty($criteriaData['tawasulFormGroupID']) && $data['tawasulSchoolYearID'] != $values['tawasulSchoolYearID']) {
                        $formGroupSource = $formGroupGateway->getByID($criteriaData['tawasulFormGroupID']);
                        $formGroupDestination = $formGroupGateway->selectBy([
                            'tawasulSchoolYearID' => $data['tawasulSchoolYearID'], 
                            'nameShort' => $formGroupSource['nameShort'],
                        ])->fetch();

                        if (!empty($formGroupDestination['tawasulFormGroupID'])) {
                            $criteriaData['tawasulFormGroupID'] = $formGroupDestination['tawasulFormGroupID'];
                        } else {
                            $failedCriteria++;
                            continue;
                        }
                    }
                    // Grab the course ID by name if it's in a different school year
                    if (!empty($criteriaData['tawasulCourseID']) && $data['tawasulSchoolYearID'] != $values['tawasulSchoolYearID']) {
                        $courseSource = $courseGateway->getByID($criteriaData['tawasulCourseID']);
                        $courseDestination = $courseGateway->selectBy([
                            'tawasulSchoolYearID' => $data['tawasulSchoolYearID'], 
                            'nameShort' => $courseSource['nameShort'],
                        ])->fetch();
                        
                        if (!empty($courseDestination['tawasulCourseID'])) {
                            $criteriaData['tawasulCourseID'] = $courseDestination['tawasulCourseID'];
                        } else {
                            $failedCriteria++;
                            continue;
                        }
                    }

                    $criteriaData['tawasulReportingCycleID'] = $tawasulReportingCycleIDNew;
                    $criteriaData['tawasulReportingScopeID'] = $tawasulReportingScopeIDNew;
                    $tawasulReportingCriteriaIDNew = $reportingCriteriaGateway->insert($criteriaData);
                }
            }
        }
    }

    if (!$tawasulReportingCycleIDNew) {
        $URL .= "&return=error2";
        header("Location: {$URL}");
    }

    $URL .= !empty($failedCriteria)
        ? "&return=warning3&failedCriteria=$failedCriteria"
        : "&return=success0";

    header("Location: {$URL}&editID=$tawasulReportingCycleIDNew");
}
