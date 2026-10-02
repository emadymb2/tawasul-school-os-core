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
use TawasulOS\Data\Validator;
use TawasulOS\Domain\IndividualNeeds\INGateway;
use TawasulOS\Domain\IndividualNeeds\INPersonDescriptorGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address).'/in_archive.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/in_archive.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $deleteCurrentPlans = $_POST['deleteCurrentPlans'] ?? '';
    $title = $_POST['title'] ?? '';
    $tawasulPersonIDs = $_POST['tawasulPersonID'] ?? array();
    if (!is_array($tawasulPersonIDs)) {
        $tawasulPersonIDs = array($tawasulPersonIDs);
    }

    if ($deleteCurrentPlans == '' or $title == '' or count($tawasulPersonIDs) < 1) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        $partialFail = false;

        //SCAN THROUGH EACH USER
        foreach ($tawasulPersonIDs as $tawasulPersonID) {
            $userFail = false;
            //Get each user's record
            try {

                $result = $container->get(INGateway::class)->getINStudentByPersonID($tawasulPersonID);

            } catch (PDOException $e) {
                $userFail = true;
                $partialFail = true;
            }
            if ($result->rowCount() != 1) {
                $userFail = true;
                $partialFail = true;
            }

            if ($userFail == false) {
                $userUpdateFail = false;
                $row = $result->fetch();

                //Check for descriptors, and write to array
                $descriptors = array();
                $descriptorsCount = 0;
                try {
                    $resultDesciptors = $container->get(INPersonDescriptorGateway::class)->selectBy(['tawasulPersonID' => $tawasulPersonID]);
                } catch (PDOException $e) {
                    $partialFail = true;
                }
                while ($rowDesciptors = $resultDesciptors->fetch()) {
                    $descriptors[$descriptorsCount]['tawasulINDescriptorID'] = $rowDesciptors['tawasulINDescriptorID'];
                    $descriptors[$descriptorsCount]['tawasulAlertLevelID'] = $rowDesciptors['tawasulAlertLevelID'];
                    ++$descriptorsCount;
                }
                $descriptors = serialize($descriptors);

                //Make archive of record
                try {
                    $dataUpdate = array('strategies' => $row['strategies'], 'targets' => $row['targets'], 'notes' => $row['notes'], 'fields' => $row['fields'], 'tawasulPersonID' => $tawasulPersonID, 'title' => $title, 'descriptors' => $descriptors);
                    $sqlUpdate = 'INSERT INTO tawasulINArchive SET tawasulPersonID=:tawasulPersonID, strategies=:strategies, targets=:targets, notes=:notes, fields=:fields, archiveTitle=:title, descriptors=:descriptors, archiveTimestamp=now()';
                    $resultUpdate = $connection2->prepare($sqlUpdate);
                    $resultUpdate->execute($dataUpdate);
                } catch (PDOException $e) {
                    $userUpdateFail = true;
                    $partialFail = true;
                }

                //If copy was successful and deleteCurrentPlans=Y, update current record to blank IEP fields
                if ($deleteCurrentPlans == 'Y' and $userUpdateFail == false) {
                    try {
                        $dataUpdate = array('tawasulPersonID' => $tawasulPersonID);
                        $sqlUpdate = "UPDATE tawasulIN SET strategies='', targets='', notes='', fields='' WHERE tawasulPersonID=:tawasulPersonID";
                        $resultUpdate = $connection2->prepare($sqlUpdate);
                        $resultUpdate->execute($dataUpdate);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                }
            }
        }

        //DEAL WITH OUTCOME
        if ($partialFail) {
            $URL .= '&return=warning1';
            header("Location: {$URL}");
        } else {
            $URL .= '&return=success0';
            header("Location: {$URL}");
        }
    }
}