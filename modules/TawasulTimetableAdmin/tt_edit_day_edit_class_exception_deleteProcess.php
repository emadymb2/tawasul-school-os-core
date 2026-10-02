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

require_once __DIR__ . '/../../tawasul.php';

$tawasulTTDayID = $_REQUEST['tawasulTTDayID'] ?? '';
$tawasulTTID = $_REQUEST['tawasulTTID'] ?? '';
$tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? '';
$tawasulTTColumnRowID = $_REQUEST['tawasulTTColumnRowID'] ?? '';
$tawasulCourseClassID = $_REQUEST['tawasulCourseClassID'] ?? '';
$tawasulTTDayRowClassID = $_REQUEST['tawasulTTDayRowClassID'] ?? '';
$tawasulTTDayRowClassExceptionID = $_REQUEST['tawasulTTDayRowClassExceptionID'] ?? '';

if ($tawasulTTDayID == '' or $tawasulTTID == '' or $tawasulSchoolYearID == '' or $tawasulTTColumnRowID == '' or $tawasulCourseClassID == '' or $tawasulTTDayRowClassID == '' or $tawasulTTDayRowClassExceptionID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception_delete.php&tawasulTTDayID=$tawasulTTDayID&tawasulTTID=$tawasulTTID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulTTColumnRowID=$tawasulTTColumnRowID&tawasulTTDayRowClass=$tawasulTTDayRowClassID&tawasulCourseClassID=$tawasulCourseClassID&tawasulTTDayRowClassExceptionID=$tawasulTTDayRowClassExceptionID";
    $URLDelete = $session->get('absoluteURL')."/index.php?q=/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception.php&tawasulTTDayID=$tawasulTTDayID&tawasulTTID=$tawasulTTID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulTTColumnRowID=$tawasulTTColumnRowID&tawasulTTDayRowClass=$tawasulTTDayRowClassID&tawasulCourseClassID=$tawasulCourseClassID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception_delete.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tawasulTTDayID specified
        if ($tawasulTTDayID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulTTColumnRowID' => $tawasulTTColumnRowID, 'tawasulTTDayID' => $tawasulTTDayID, 'tawasulCourseClassID' => $tawasulCourseClassID);
                $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulTTDayRowClassID FROM tawasulTTDayRowClass JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulTTColumnRowID=:tawasulTTColumnRowID AND tawasulTTDayID=:tawasulTTDayID AND tawasulTTColumnRowID=:tawasulTTColumnRowID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID";
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
                try {
                    $data = array('tawasulTTDayRowClassExceptionID' => $tawasulTTDayRowClassExceptionID);
                    $sql = 'SELECT * FROM tawasulTTDayRowClassException WHERE tawasulTTDayRowClassExceptionID=:tawasulTTDayRowClassExceptionID';
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
                        $data = array('tawasulTTDayRowClassExceptionID' => $tawasulTTDayRowClassExceptionID);
                        $sql = 'DELETE FROM tawasulTTDayRowClassException WHERE tawasulTTDayRowClassExceptionID=:tawasulTTDayRowClassExceptionID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $URLDelete = $URLDelete.'&return=success0';
                    header("Location: {$URLDelete}");
                }
            }
        }
    }
}
