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
$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$tawasulTTDayRowClassID = $_GET['tawasulTTDayRowClassID'] ?? '';

if ($tawasulTTDayID == '' or $tawasulTTID == '' or $tawasulSchoolYearID == '' or $tawasulTTColumnRowID == '' or $tawasulCourseClassID == '' or $tawasulTTDayRowClassID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception_add.php&tawasulTTDayID=$tawasulTTDayID&tawasulTTID=$tawasulTTID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulTTColumnRowID=$tawasulTTColumnRowID&tawasulTTDayRowClass=$tawasulTTDayRowClassID&tawasulCourseClassID=$tawasulCourseClassID";
    $URLSuccess = $session->get('absoluteURL')."/index.php?q=/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception.php&tawasulTTDayID=$tawasulTTDayID&tawasulTTID=$tawasulTTID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulTTColumnRowID=$tawasulTTColumnRowID&tawasulTTDayRowClass=$tawasulTTDayRowClassID&tawasulCourseClassID=$tawasulCourseClassID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception_add.php') == false) {
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
                $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulTTDayRowClassID, tawasulSpaceID FROM tawasulTTDayRowClass JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulTTColumnRowID=:tawasulTTColumnRowID AND tawasulTTDayID=:tawasulTTDayID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID';
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
                //Run through each of the selected participants.
                $update = true;
                $choices = $_POST['Members'] ?? [];
                if (!empty($_GET['tawasulPersonID'])) {
                    $choices[] = $_GET['tawasulPersonID'];
                }

                if (count($choices) < 1) {
                    $URL .= '&return=error1';
                    header("Location: {$URL}");
                } else {
                    foreach ($choices as $t) {
                        //Check to see if person is already exempted from this class
                        try {
                            $data = array('tawasulPersonID' => $t, 'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID);
                            $sql = 'SELECT * FROM tawasulTTDayRowClassException WHERE tawasulPersonID=:tawasulPersonID AND tawasulTTDayRowClassID=:tawasulTTDayRowClassID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $update = false;
                        }

                        //If student not in course, add them
                        if ($result->rowCount() == 0) {
                            try {
                                $data = array('tawasulPersonID' => $t, 'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID);
                                $sql = 'INSERT INTO tawasulTTDayRowClassException SET tawasulPersonID=:tawasulPersonID, tawasulTTDayRowClassID=:tawasulTTDayRowClassID';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $update = false;
                            }
                        }
                    }
                    //Write to database
                    if ($update == false) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                    } else {
                        $AI = $pdo->getConnection()->lastInsertID();
                        $URLSuccess .= '&return=success0';
                        header("Location: {$URLSuccess}&editID={$AI}");
                    }
                }
            }
        }
    }
}
