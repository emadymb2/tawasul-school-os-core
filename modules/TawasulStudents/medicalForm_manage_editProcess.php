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

use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulPersonMedicalID = $_GET['tawasulPersonMedicalID'] ?? '';
$search = $_GET['search'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/medicalForm_manage_edit.php&tawasulPersonMedicalID=$tawasulPersonMedicalID&search=$search";

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/medicalForm_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if medical form specified
    if ($tawasulPersonMedicalID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulPersonMedicalID' => $tawasulPersonMedicalID);
            $sql = 'SELECT * FROM tawasulPersonMedical WHERE tawasulPersonMedicalID=:tawasulPersonMedicalID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($result->rowCount() != 1) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            // Fetch old record for comparison
            $oldMedicalRecord = $result->fetch();

            $longTermMedication = $_POST['longTermMedication'] ?? 'N';
            $longTermMedicationDetails = (isset($_POST['longTermMedicationDetails']) ? $_POST['longTermMedicationDetails'] : '');
            $comment = $_POST['comment'] ?? '';

            $customRequireFail = false;
            $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('Medical Form', [], $customRequireFail);

            if ($customRequireFail) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit;
            }

            //Write to database
            try {
                $data = array('longTermMedication' => $longTermMedication, 'longTermMedicationDetails' => $longTermMedicationDetails, 'fields' => $fields, 'comment' => $comment, 'tawasulPersonMedicalID' => $tawasulPersonMedicalID);
                $sql = 'UPDATE tawasulPersonMedical SET longTermMedication=:longTermMedication, longTermMedicationDetails=:longTermMedicationDetails, fields=:fields, comment=:comment WHERE tawasulPersonMedicalID=:tawasulPersonMedicalID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            // Manage custom field file uploads
            if (!empty($fields) && !empty($tawasulPersonMedicalID)) {
                $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('Medical Form', [], $fields, 'tawasulPersonMedical', $tawasulPersonMedicalID, $oldMedicalRecord['fields'] ?? null);
            }
            
            $URL .= '&return=success0';
            header("Location: {$URL}");
        }
    }
}
