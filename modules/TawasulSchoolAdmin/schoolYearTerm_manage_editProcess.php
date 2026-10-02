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

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulSchoolYearTermID = $_GET['tawasulSchoolYearTermID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/schoolYearTerm_manage_edit.php&tawasulSchoolYearTermID='.$tawasulSchoolYearTermID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/schoolYearTerm_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulSchoolYearTermID specified
    if ($tawasulSchoolYearTermID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulSchoolYearTermID' => $tawasulSchoolYearTermID);
            $sql = 'SELECT * FROM tawasulSchoolYearTerm WHERE tawasulSchoolYearTermID=:tawasulSchoolYearTermID';
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
            //Validate Inputs
            $tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
            $sequenceNumber = $_POST['sequenceNumber'] ?? '';
            $name = $_POST['name'] ?? '';
            $nameShort = $_POST['nameShort'] ?? '';
            $firstDay = !empty($_POST['firstDay']) ? Format::dateConvert($_POST['firstDay']) : null;
            $lastDay = !empty($_POST['lastDay']) ? Format::dateConvert($_POST['lastDay']) : null;

            if ($tawasulSchoolYearID == '' or $name == '' or $nameShort == '' or $sequenceNumber == '' or is_numeric($sequenceNumber) == false or $firstDay == '' or $lastDay == '') {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                //Check unique inputs for uniquness
                try {
                    $data = array('sequenceNumber' => $sequenceNumber, 'tawasulSchoolYearTermID' => $tawasulSchoolYearTermID);
                    $sql = 'SELECT * FROM tawasulSchoolYearTerm WHERE sequenceNumber=:sequenceNumber AND NOT tawasulSchoolYearTermID=:tawasulSchoolYearTermID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() > 0) {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'name' => $name, 'nameShort' => $nameShort, 'sequenceNumber' => $sequenceNumber, 'firstDay' => $firstDay, 'lastDay' => $lastDay, 'tawasulSchoolYearTermID' => $tawasulSchoolYearTermID);
                        $sql = 'UPDATE tawasulSchoolYearTerm SET tawasulSchoolYearID=:tawasulSchoolYearID, name=:name, nameShort=:nameShort, sequenceNumber=:sequenceNumber, firstDay=:firstDay, lastDay=:lastDay WHERE tawasulSchoolYearTermID=:tawasulSchoolYearTermID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                    }

                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
