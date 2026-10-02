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

$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/markbook_edit_targets.php&tawasulCourseClassID=$tawasulCourseClassID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_edit_targets.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulCourseClassID specified
    if ($tawasulCourseClassID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        $count = $_POST['count'] ?? '';
        $tawasulScaleIDTarget = $_POST['tawasulScaleIDTarget'] ?? '';
        if ($tawasulScaleIDTarget == '')
            $tawasulScaleIDTarget = null;
        $partialFail = false;

        //Update target scale
        try {
            $data = array('tawasulScaleIDTarget' => $tawasulScaleIDTarget, 'tawasulCourseClassID' => $tawasulCourseClassID);
            $sql = 'UPDATE tawasulCourseClass SET tawasulScaleIDTarget=:tawasulScaleIDTarget WHERE tawasulCourseClassID=:tawasulCourseClassID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $partialFail = true;
        }

        for ($i = 1;$i <= $count;++$i) {
            $tawasulPersonIDStudent = $_POST["$i-tawasulPersonID"] ?? '';
            $tawasulScaleGradeID = null;
            if (!empty($_POST["$i-tawasulScaleGradeID"])) {
                $tawasulScaleGradeID = $_POST["$i-tawasulScaleGradeID"] ?? '';
            }

            $selectFail = false;
            try {
                $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonIDStudent' => $tawasulPersonIDStudent);
                $sql = 'SELECT * FROM tawasulMarkbookTarget WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulPersonIDStudent=:tawasulPersonIDStudent';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $partialFail = true;
                $selectFail = true;
            }
            if (!($selectFail)) {
                if ($result->rowCount() < 1) {
                    try {
                        $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonIDStudent' => $tawasulPersonIDStudent, 'tawasulScaleGradeID' => $tawasulScaleGradeID);
                        $sql = 'INSERT INTO tawasulMarkbookTarget SET tawasulCourseClassID=:tawasulCourseClassID, tawasulPersonIDStudent=:tawasulPersonIDStudent, tawasulScaleGradeID=:tawasulScaleGradeID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                } else {
                    $row = $result->fetch();
                    //Update
                    try {
                        $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonIDStudent' => $tawasulPersonIDStudent, 'tawasulScaleGradeID' => $tawasulScaleGradeID);
                        $sql = 'UPDATE tawasulMarkbookTarget SET tawasulScaleGradeID=:tawasulScaleGradeID WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulPersonIDStudent=:tawasulPersonIDStudent';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                }
            }
        }

        //Return!
        if ($partialFail == true) {
            $URL .= '&return=error3';
            header("Location: {$URL}");
        } else {
            $URL .= '&return=success0';
            header("Location: {$URL}");
        }
    }
}
