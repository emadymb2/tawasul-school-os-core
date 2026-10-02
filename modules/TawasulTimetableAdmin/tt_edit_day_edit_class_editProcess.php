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
use TawasulOS\Support\Facades\Access;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulTTDayID = $_GET['tawasulTTDayID'] ?? '';
$tawasulTTID = $_GET['tawasulTTID'] ?? '';
$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulTTColumnRowID = $_GET['tawasulTTColumnRowID'] ?? '';
$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$tawasulTTDayRowClassID = $_GET['tawasulTTDayRowClassID'] ?? '';

if (!Access::allows('Timetable Admin', 'tt_edit_day_edit_class_edit') && !Access::allows('Timetable', 'tt_space_edit')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/tt_edit_day_edit_class_edit.php&tawasulTTDayID=$tawasulTTDayID&tawasulTTID=$tawasulTTID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulTTColumnRowID=$tawasulTTColumnRowID&tawasulTTDayRowClassID=$tawasulTTDayRowClassID&tawasulCourseClassID=$tawasulCourseClassID";

    if ($tawasulTTDayID == '' or $tawasulTTID == '' or $tawasulSchoolYearID == '' or $tawasulTTColumnRowID == '' or $tawasulCourseClassID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        //Proceed!
        $tawasulSpaceID = !empty($_POST['tawasulSpaceID']) ? $_POST['tawasulSpaceID'] : null;

        //Check if tawasulTTDayID specified
        if ($tawasulTTDayID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulTTColumnRowID' => $tawasulTTColumnRowID, 'tawasulTTDayID' => $tawasulTTDayID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID, 'tawasulCourseClassID' => $tawasulCourseClassID);
                $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulTTDayRowClassID FROM tawasulTTDayRowClass JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulTTColumnRowID=:tawasulTTColumnRowID AND tawasulTTDayID=:tawasulTTDayID AND tawasulTTColumnRowID=:tawasulTTColumnRowID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            if ($result->rowCount() < 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                //Write to database
                try {
                    $data = array('tawasulSpaceID' => $tawasulSpaceID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID, 'tawasulTTDayID' => $tawasulTTDayID, 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = 'UPDATE tawasulTTDayRowClass SET tawasulSpaceID=:tawasulSpaceID WHERE tawasulTTColumnRowID=:tawasulTTColumnRowID AND tawasulTTDayID=:tawasulTTDayID AND tawasulCourseClassID=:tawasulCourseClassID';
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
