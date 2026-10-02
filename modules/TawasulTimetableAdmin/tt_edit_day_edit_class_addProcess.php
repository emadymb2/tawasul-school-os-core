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

$tawasulTTDayID = $_GET['tawasulTTDayID'] ?? '';
$tawasulTTID = $_GET['tawasulTTID'] ?? '';
$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulTTColumnRowID = $_GET['tawasulTTColumnRowID'] ?? '';
$tawasulCourseClassID = $_POST['tawasulCourseClassID'] ?? '';

$tawasulSpaceID = !empty($_POST['tawasulSpaceID']) ? $_POST['tawasulSpaceID'] : null;

if ($tawasulTTDayID == '' or $tawasulTTID == '' or $tawasulSchoolYearID == '' or $tawasulTTColumnRowID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/tt_edit_day_edit_class_add.php&tawasulTTDayID=$tawasulTTDayID&tawasulTTID=$tawasulTTID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulTTColumnRowID=$tawasulTTColumnRowID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_add.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tawasulTTDayID specified
        if ($tawasulTTDayID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {

                $data = array('tawasulTTDayID' => $tawasulTTDayID, 'tawasulTTID' => $tawasulTTID, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID);
                $sql = 'SELECT tawasulTT.name AS ttName, tawasulTTDay.name AS dayName, tawasulTTColumnRow.name AS rowName FROM tawasulTT JOIN tawasulTTDay ON (tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID) JOIN tawasulTTColumn ON (tawasulTTDay.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID) JOIN tawasulTTColumnRow ON (tawasulTTColumn.tawasulTTColumnID=tawasulTTColumnRow.tawasulTTColumnID) WHERE tawasulTTDay.tawasulTTDayID=:tawasulTTDayID AND tawasulTT.tawasulTTID=:tawasulTTID AND tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulTTColumnRowID=:tawasulTTColumnRowID';
                $result = $connection2->prepare($sql);
                $result->execute($data);

            if ($result->rowCount() != 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                //Write to database
                try {
                    $data = array('tawasulTTColumnRowID' => $tawasulTTColumnRowID, 'tawasulTTDayID' => $tawasulTTDayID, 'tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulSpaceID' => $tawasulSpaceID);
                    $sql = 'INSERT INTO tawasulTTDayRowClass SET tawasulTTColumnRowID=:tawasulTTColumnRowID, tawasulTTDayID=:tawasulTTDayID, tawasulCourseClassID=:tawasulCourseClassID, tawasulSpaceID=:tawasulSpaceID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                //Last insert ID
                $AI = str_pad($connection2->lastInsertID(), 8, '0', STR_PAD_LEFT);

                $URL .= "&return=success0&editID=$AI&tawasulCourseClassID=$tawasulCourseClassID";
                header("Location: {$URL}");
            }
        }
    }
}
