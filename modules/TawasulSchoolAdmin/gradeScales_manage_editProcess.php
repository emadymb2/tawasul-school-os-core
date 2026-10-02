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
$tawasulScaleID = $_POST['tawasulScaleID'] ?? '';
$usage = $_POST['usage'] ?? '';
$active = $_POST['active'] ?? '';
$numeric = $_POST['numeric'] ?? '';
$lowestAcceptable = $_POST['lowestAcceptable'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/gradeScales_manage_edit.php&tawasulScaleID='.$tawasulScaleID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/gradeScales_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if special day specified
    if ($tawasulScaleID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulScaleID' => $tawasulScaleID);
            $sql = 'SELECT * FROM tawasulScale WHERE tawasulScaleID=:tawasulScaleID';
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
            if ($name == '' or $nameShort == '' or $usage == '' or $active == '' or $numeric == '') {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                //Check unique inputs for uniquness
                try {
                    $data = array('name' => $name, 'nameShort' => $nameShort, 'tawasulScaleID' => $tawasulScaleID);
                    $sql = 'SELECT * FROM tawasulScale WHERE (name=:name OR nameShort=:nameShort) AND NOT tawasulScaleID=:tawasulScaleID';
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
                        $data = array('name' => $name, 'nameShort' => $nameShort, 'usage' => $usage, 'active' => $active, 'numeric' => $numeric, 'lowestAcceptable' => $lowestAcceptable, 'tawasulScaleID' => $tawasulScaleID);
                        $sql = 'UPDATE tawasulScale SET name=:name, nameShort=:nameShort, `usage`=:usage, active=:active, `numeric`=:numeric, lowestAcceptable=:lowestAcceptable WHERE tawasulScaleID=:tawasulScaleID';
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
