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
use TawasulOS\Domain\FormalAssessment\ExternalAssessmentStudentGateway;
use TawasulOS\Domain\FormalAssessment\ExternalAssessmentStudentEntryGateway;

require_once __DIR__ . '/../../tawasul.php';

$action = $_POST['action'] ?? '';
$search = $_POST['search'] ?? '';
$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFormalAssessment/externalAssessment.php&search='.$search;

if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/externalAssessment_manage_details_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} elseif (empty($action) || empty($tawasulPersonID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
} else {
    // Proceed!
    $tawasulPersonIDList = is_array($tawasulPersonID)? $tawasulPersonID : [$tawasulPersonID];
    $eaStudentGateway = $container->get(ExternalAssessmentStudentGateway::class);
    $eaStudentEntryGateway = $container->get(ExternalAssessmentStudentEntryGateway::class);
    $partialFail = false;

    if ($action == 'Add') {
        $tawasulExternalAssessmentID = $_POST['tawasulExternalAssessmentID'] ?? '';
        $copyToGCSECheck = $_POST['copyToGCSECheck'] ?? 'N';
        $date = $_POST['date'] ?? '';

        if (empty($tawasulExternalAssessmentID) || empty($copyToGCSECheck) || empty($date)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit();
        }

        foreach ($tawasulPersonIDList as $tawasulPersonID) {
            $data = [
                'tawasulExternalAssessmentID' => $tawasulExternalAssessmentID,
                'tawasulPersonID' => $tawasulPersonID,
                'date' => Format::dateConvert($date),
            ];

            // Do not create a record if it already exists
            $isUnique = $eaStudentGateway->unique($data, ['tawasulExternalAssessmentID', 'tawasulPersonID', 'date']);
            if (!$isUnique) continue;

            // Insert the student, then add the entries
            if ($tawasulExternalAssessmentStudentID = $eaStudentGateway->insert($data)) {

                // Optionally copy CAT data to GCSE
                if ($tawasulExternalAssessmentID == 2 && $copyToGCSECheck == 'Y') {
                    $data = [
                        'tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID,
                        'tawasulExternalAssessmentID' => $tawasulExternalAssessmentID,
                        'tawasulPersonID' => $tawasulPersonID,
                    ];

                    $sql = "INSERT INTO tawasulExternalAssessmentStudentEntry
                            (`tawasulExternalAssessmentStudentID`, `tawasulExternalAssessmentFieldID`, `tawasulScaleGradeID`)
                            SELECT :tawasulExternalAssessmentStudentID, field.tawasulExternalAssessmentFieldID,
                            (
                                SELECT tawasulExternalAssessmentStudentEntry.tawasulScaleGradeID FROM tawasulExternalAssessment
                                JOIN tawasulExternalAssessmentField ON (tawasulExternalAssessmentField.tawasulExternalAssessmentID=tawasulExternalAssessment.tawasulExternalAssessmentID)
                                JOIN tawasulExternalAssessmentStudent ON (tawasulExternalAssessmentStudent.tawasulExternalAssessmentID=tawasulExternalAssessment.tawasulExternalAssessmentID)
                                JOIN tawasulExternalAssessmentStudentEntry ON (tawasulExternalAssessmentStudentEntry.tawasulExternalAssessmentFieldID=tawasulExternalAssessmentField.tawasulExternalAssessmentFieldID AND tawasulExternalAssessmentStudentEntry.tawasulExternalAssessmentStudentID=tawasulExternalAssessmentStudent.tawasulExternalAssessmentStudentID)
                                WHERE tawasulExternalAssessment.name='Cognitive Abilities Test'
                                    AND tawasulExternalAssessmentStudent.tawasulPersonID=:tawasulPersonID
                                    AND tawasulExternalAssessmentField.name=field.name
                                    AND tawasulExternalAssessmentField.category LIKE '%GCSE Target Grades'
                                    AND NOT (tawasulScaleGradeID IS NULL)
                                LIMIT 1
                            )
                            FROM tawasulExternalAssessmentField as field
                            WHERE field.tawasulExternalAssessmentID=:tawasulExternalAssessmentID
                            AND field.category='0_Target Grade'";
                } else {
                    $data = [
                        'tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID,
                        'tawasulExternalAssessmentID' => $tawasulExternalAssessmentID,
                    ];

                    $sql = "INSERT INTO tawasulExternalAssessmentStudentEntry
                            (`tawasulExternalAssessmentStudentID`, `tawasulExternalAssessmentFieldID`, `tawasulScaleGradeID`)
                            SELECT :tawasulExternalAssessmentStudentID, tawasulExternalAssessmentFieldID, NULL
                            FROM tawasulExternalAssessmentField
                            WHERE tawasulExternalAssessmentField.tawasulExternalAssessmentID=:tawasulExternalAssessmentID";
                }

                $inserted = $pdo->insert($sql, $data);
                $partialFail &= !$inserted;
            }
        }

        $URL .= $partialFail
            ? '&return=warning1'
            : '&return=success0';
        header("Location: {$URL}");
    } else {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    }
}
