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
use TawasulOS\Data\Validator;
use TawasulOS\Contracts\Filesystem\FileHandler;
use PDOException;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulStaffID = $_GET['tawasulStaffID'] ?? '';
$tawasulStaffContractID = $_GET['tawasulStaffContractID'] ?? '';
$search = $_GET['search'] ?? '';
if ($tawasulStaffID == '') {
    echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . getModuleName($_POST['address']) . "/staff_manage_edit_contract_edit.php&tawasulStaffContractID=$tawasulStaffContractID&tawasulStaffID=$tawasulStaffID&search=$search";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_manage_edit_contract_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if person specified
        if ($tawasulStaffContractID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulStaffID' => $tawasulStaffID, 'tawasulStaffContractID' => $tawasulStaffContractID);
                $sql = 'SELECT tawasulStaffContract.*, surname, preferredName, username FROM tawasulStaffContract JOIN tawasulStaff ON (tawasulStaffContract.tawasulStaffID=tawasulStaff.tawasulStaffID) JOIN tawasulPerson ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulStaffContract.tawasulStaffID=:tawasulStaffID AND tawasulStaffContractID=:tawasulStaffContractID';
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
                $row = $result->fetch();
                $username = $row['username'];

                $title = $_POST['title'] ?? '';
                $status = $_POST['status'] ?? '';
                $dateStart = !empty($_POST['dateStart']) ? Format::dateConvert($_POST['dateStart']) : null;
                $dateEnd = !empty($_POST['dateEnd']) ? Format::dateConvert($_POST['dateEnd']) : null;
                $salaryScale = $_POST['salaryScale'] ?? '';
                $salaryAmount = $_POST['salaryAmount'] ?? '';
                $salaryPeriod = $_POST['salaryPeriod'] ?? '';
                $responsibility = $_POST['responsibility'] ?? '';
                $responsibilityAmount = $_POST['responsibilityAmount'] ?? null;
                $responsibilityPeriod = $_POST['responsibilityPeriod'] ?? '';
                $housingAmount = $_POST['housingAmount'] ?? '';
                $housingPeriod = $_POST['housingPeriod'] ?? '';
                $travelAmount = $_POST['travelAmount'] ?? '';
                $travelPeriod = $_POST['travelPeriod'] ?? '';
                $retirementAmount = $_POST['retirementAmount'] ?? '';
                $retirementPeriod = $_POST['retirementPeriod'] ?? '';
                $bonusAmount = $_POST['bonusAmount'] ?? '';
                $bonusPeriod = $_POST['bonusPeriod'] ?? '';
                $education = $_POST['education'] ?? '';
                $notes = $_POST['notes'] ?? '';

                $partialFail = false;
                $fileMetaData = null;
                if (!empty($_FILES['file1']['tmp_name'])) {
                    $fileUploader = new TawasulOS\FileUploader($pdo, $session);
                    $fileUploader->getFileExtensions('Document');

                    $file = (isset($_FILES['file1'])) ? $_FILES['file1'] : null;

                    // Upload the file, return the /uploads relative path
                    $contractUpload = $fileUploader->uploadFromPost($file, $username);

                    if (empty($contractUpload)) {
                        $contractUpload = '';
                        $partialFail = true;
                    } else {
                        $fileMetaData = $fileUploader->getFileMetaData($contractUpload);
                    }
                } else {
                    // Remove the attachment if it has been deleted, otherwise retain the original value
                    $contractUpload = empty($_POST['contractUpload']) ? '' : $row['contractUpload'];
                }

                if ($title == '' or $status == '') {
                    $URL .= '&return=error1&step=1';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('tawasulStaffID' => $tawasulStaffID, 'title' => $title, 'status' => $status, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'salaryScale' => $salaryScale, 'salaryAmount' => $salaryAmount, 'salaryPeriod' => $salaryPeriod, 'responsibility' => $responsibility, 'responsibilityAmount' => $responsibilityAmount, 'responsibilityPeriod' => $responsibilityPeriod, 'housingAmount' => $housingAmount, 'housingPeriod' => $housingPeriod, 'travelAmount' => $travelAmount, 'travelPeriod' => $travelPeriod, 'retirementAmount' => $retirementAmount, 'retirementPeriod' => $retirementPeriod, 'bonusAmount' => $bonusAmount, 'bonusPeriod' => $bonusPeriod, 'education' => $education, 'notes' => $notes, 'contractUpload' => $contractUpload, 'tawasulStaffContractID' => $tawasulStaffContractID);
                        $sql = 'UPDATE tawasulStaffContract SET tawasulStaffID=:tawasulStaffID, title=:title, status=:status, dateStart=:dateStart, dateEnd=:dateEnd, salaryScale=:salaryScale, salaryAmount=:salaryAmount, salaryPeriod=:salaryPeriod, responsibility=:responsibility, responsibilityAmount=:responsibilityAmount, responsibilityPeriod=:responsibilityPeriod, housingAmount=:housingAmount, housingPeriod=:housingPeriod, travelAmount=:travelAmount, travelPeriod=:travelPeriod, retirementAmount=:retirementAmount, retirementPeriod=:retirementPeriod, bonusAmount=:bonusAmount, bonusPeriod=:bonusPeriod, education=:education, notes=:notes, contractUpload=:contractUpload WHERE tawasulStaffContractID=:tawasulStaffContractID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    // Record file tracking (only if new file uploaded)
                    if (!empty($fileMetaData) && !empty($tawasulStaffContractID)) {
                        $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulStaffContract', $tawasulStaffContractID, 'contractUpload');

                        if (empty($tawasulFileID)) {
                            $partialFail = true;
                        }
                    }

                    // Handle file deletion when user removes attachment (must be done before recording new upload)
                    if (empty($contractUpload) && !empty($row['contractUpload'])) {
                        $deleted = $container->get(FileHandler::class)->deleteFile('tawasulStaffContract', $tawasulStaffContractID, 'contractUpload');
                    }

                    if ($partialFail == true) {
                        $URL .= '&return=warning1';
                        header("Location: {$URL}");
                    } else {
                        $URL .= "&return=success0";
                        header("Location: {$URL}");
                    }
                }
            }
        }
    }
}
