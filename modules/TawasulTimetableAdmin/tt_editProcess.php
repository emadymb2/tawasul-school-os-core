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

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$name = $_POST['name'] ?? '';
$nameShort = $_POST['nameShort'] ?? '';
$nameShortDisplay = $_POST['nameShortDisplay'] ?? '';
$active = $_POST['active'] ?? '';
$count = $_POST['count'] ?? '';
$tawasulYearGroupIDList = (isset($_POST["tawasulYearGroupID"]) ? implode(',', $_POST["tawasulYearGroupID"]) : '');
$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
$tawasulTTID = $_POST['tawasulTTID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'] ?? '').'/tt_edit.php&tawasulTTID='.$tawasulTTID.'&tawasulSchoolYearID='.($_POST['tawasulSchoolYearID'] ?? '');

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if special day specified
    if ($tawasulTTID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulTTID' => $tawasulTTID);
            $sql = 'SELECT * FROM tawasulTT WHERE tawasulTTID=:tawasulTTID';
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
            if ($name == '' or $nameShort == '' or $nameShortDisplay == '' or $tawasulSchoolYearID == '') {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                //Check unique inputs for uniquness
                try {
                    $data = array('name' => $name, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulTTID' => $tawasulTTID);
                    $sql = 'SELECT * FROM tawasulTT WHERE (name=:name AND tawasulSchoolYearID=:tawasulSchoolYearID) AND NOT tawasulTTID=:tawasulTTID';
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
                        $data = array('name' => $name, 'nameShort' => $nameShort, 'nameShortDisplay' => $nameShortDisplay, 'active' => $active, 'tawasulYearGroupIDList' => $tawasulYearGroupIDList, 'tawasulTTID' => $tawasulTTID);
                        $sql = 'UPDATE tawasulTT SET name=:name, nameShort=:nameShort, nameShortDisplay=:nameShortDisplay, active=:active, tawasulYearGroupIDList=:tawasulYearGroupIDList WHERE tawasulTTID=:tawasulTTID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
