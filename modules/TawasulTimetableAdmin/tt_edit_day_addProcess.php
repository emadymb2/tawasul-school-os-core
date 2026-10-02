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
$color = $_POST['color'] ?? '';
$fontColor = $_POST['fontColor'] ?? '';
$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
$tawasulTTID = $_POST['tawasulTTID'] ?? '';
$tawasulTTColumnID = $_POST['tawasulTTColumnID'] ?? '';

// Filter valid colour values
$color = preg_replace('/[^a-fA-F0-9\#]/', '', mb_substr($color, 0, 7));
$fontColor = preg_replace('/[^a-fA-F0-9\#]/', '', mb_substr($fontColor, 0, 7));

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/tt_edit_day_add.php&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulTTID=$tawasulTTID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_day_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Validate Inputs
    if ($tawasulSchoolYearID == '' or $tawasulTTID == '' or $name == '' or $nameShort == '' or $tawasulTTColumnID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        //Check unique inputs for uniquness
        try {
            $data = array('name' => $name, 'nameShort' => $nameShort, 'tawasulTTID' => $tawasulTTID);
            $sql = 'SELECT * FROM tawasulTTDay WHERE ((name=:name) OR (nameShort=:nameShort)) AND tawasulTTID=:tawasulTTID';
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
                $data = array('tawasulTTID' => $tawasulTTID, 'name' => $name, 'nameShort' => $nameShort, 'color' => $color, 'fontColor' => $fontColor, 'tawasulTTColumnID' => $tawasulTTColumnID);
                $sql = 'INSERT INTO tawasulTTDay SET tawasulTTID=:tawasulTTID, name=:name, nameShort=:nameShort, color=:color, fontColor=:fontColor, tawasulTTColumnID=:tawasulTTColumnID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            //Last insert ID
            $AI = str_pad($connection2->lastInsertID(), 6, '0', STR_PAD_LEFT);

            $URL .= "&return=success0&editID=$AI";
            header("Location: {$URL}");
        }
    }
}
