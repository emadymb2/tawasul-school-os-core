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

$tawasulTTColumnRowID = $_POST['tawasulTTColumnRowID'] ?? '';
$tawasulTTColumnID = $_POST['tawasulTTColumnID'] ?? '';

if ($tawasulTTColumnID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/ttColumn_edit_row_edit.php&tawasulTTColumnID=$tawasulTTColumnID&tawasulTTColumnRowID=$tawasulTTColumnRowID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/ttColumn_edit_row_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tt specified
        if ($tawasulTTColumnRowID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulTTColumnRowID' => $tawasulTTColumnRowID);
                $sql = 'SELECT * FROM tawasulTTColumnRow WHERE tawasulTTColumnRowID=:tawasulTTColumnRowID';
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
                $name = $_POST['name'] ?? '';
                $nameShort = $_POST['nameShort'] ?? '';
                $timeStart = $_POST['timeStart'] ?? '';
                $timeEnd = $_POST['timeEnd'] ?? '';
                $type = $_POST['type'] ?? '';

                if ($name == '' or $nameShort == '' or $timeStart == '' or $timeEnd == '' or $type == '') {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    //Check unique inputs for uniquness
                    try {
                        $data = array('name' => $name, 'nameShort' => $nameShort, 'tawasulTTColumnID' => $tawasulTTColumnID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID);
                        $sql = 'SELECT * FROM tawasulTTColumnRow WHERE (name=:name OR nameShort=:nameShort) AND tawasulTTColumnID=:tawasulTTColumnID AND NOT tawasulTTColumnRowID=:tawasulTTColumnRowID';
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
                            $data = array('name' => $name, 'nameShort' => $nameShort, 'timeStart' => $timeStart, 'timeEnd' => $timeEnd, 'type' => $type, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID);
                            $sql = 'UPDATE tawasulTTColumnRow SET name=:name, nameShort=:nameShort, timeStart=:timeStart, timeEnd=:timeEnd, type=:type WHERE tawasulTTColumnRowID=:tawasulTTColumnRowID';
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
}
