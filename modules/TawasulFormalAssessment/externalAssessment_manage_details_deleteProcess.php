<?php

use TawasulOS\Domain\FormalAssessment\ExternalAssessmentStudentGateway;
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

require_once __DIR__ . '/../../tawasul.php';

$tawasulExternalAssessmentStudentID = $_POST['tawasulExternalAssessmentStudentID'] ?? '';
$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$search = $_GET['search'] ?? '';
$allStudents = $_GET['allStudents'] ?? '';

if ($tawasulPersonID == '' or $tawasulExternalAssessmentStudentID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/externalAssessment_manage_details_delete.php&tawasulPersonID=$tawasulPersonID&tawasulExternalAssessmentStudentID=$tawasulExternalAssessmentStudentID&search=$search&allStudents=$allStudents";
    $URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/externalAssessment_details.php&tawasulPersonID=$tawasulPersonID&search=$search&allStudents=$allStudents";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/externalAssessment_manage_details_delete.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tawasulExternalAssessmentStudentID specified
        if ($tawasulExternalAssessmentStudentID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $result = $container->get(ExternalAssessmentStudentGateway::class)->getByID($tawasulExternalAssessmentStudentID);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            if (empty($result)) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                //Write to database
                //Delete fields
                try {
                    $data = array('tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID);
                    $sql = 'DELETE FROM tawasulExternalAssessmentStudentEntry WHERE tawasulExternalAssessmentStudentID=:tawasulExternalAssessmentStudentID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                //Delete assessment
                try {
                    $data = array('tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID);
                    $sql = 'DELETE FROM tawasulExternalAssessmentStudent WHERE tawasulExternalAssessmentStudentID=:tawasulExternalAssessmentStudentID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                $URLDelete = $URLDelete.'&return=success0';
                header("Location: {$URLDelete}");
            }
        }
    }
}
