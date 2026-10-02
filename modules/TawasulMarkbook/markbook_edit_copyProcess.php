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

$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulMarkbook/markbook_edit.php&tawasulCourseClassID=$tawasulCourseClassID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_edit_copy.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $tawasulMarkbookCopyClassID = $_GET['tawasulMarkbookCopyClassID'] ?? null;
    $copyColumnID = $_POST['copyColumnID'] ?? null;

    if (empty($_POST)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else if (empty($tawasulCourseClassID) || empty($tawasulMarkbookCopyClassID) || empty($copyColumnID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {

        try {
            $data2 = array('tawasulCourseClassID' => $tawasulMarkbookCopyClassID);
            $sql2 = 'SELECT * FROM tawasulMarkbookColumn WHERE tawasulCourseClassID=:tawasulCourseClassID';
            $result2 = $connection2->prepare($sql2);
            $result2->execute($data2);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($result2->rowCount() <= 0) {
            $URL .= '&return=warning1';
            header("Location: {$URL}");
            exit();
        } else {

            $partialFail = false;
            while ($column = $result2->fetch() ) {

                // Only include the selected columns
                if ( isset($copyColumnID[ $column['tawasulMarkbookColumnID'] ]) && $column['tawasulMarkbookColumnID'] == true ) {

                    //Write to database
                    try {
                        $date = (!empty($_POST['date']))? Format::dateConvert($_POST['date']) : date('Y-m-d');
                        $data = array('tawasulUnitID' => $column['tawasulUnitID'], 'tawasulPlannerEntryID' => $column['tawasulPlannerEntryID'], 'tawasulCourseClassID' => $tawasulCourseClassID, 'name' => $column['name'], 'description' => $column['description'], 'type' => $column['type'], 'date' => $date, 'sequenceNumber' => $column['sequenceNumber'], 'attainment' => $column['attainment'], 'tawasulScaleIDAttainment' => $column['tawasulScaleIDAttainment'], 'attainmentWeighting' => $column['attainmentWeighting'], 'attainmentRaw' => $column['attainmentRaw'], 'attainmentRawMax' => $column['attainmentRawMax'], 'effort' => $column['effort'], 'tawasulScaleIDEffort' => $column['tawasulScaleIDEffort'], 'tawasulRubricIDAttainment' => $column['tawasulRubricIDAttainment'], 'tawasulRubricIDEffort' => $column['tawasulRubricIDEffort'], 'comment' => $column['comment'], 'uploadedResponse' => $column['uploadedResponse'], 'viewableStudents' => $column['viewableStudents'], 'viewableParents' => $column['viewableParents'], 'attachment' => $column['attachment'], 'tawasulPersonIDCreator' => $column['tawasulPersonIDCreator'], 'tawasulPersonIDLastEdit' => $column['tawasulPersonIDLastEdit'], 'tawasulSchoolYearTermID' => $column['tawasulSchoolYearTermID']);
                    $sql = 'INSERT INTO tawasulMarkbookColumn SET tawasulUnitID=:tawasulUnitID, tawasulPlannerEntryID=:tawasulPlannerEntryID, tawasulCourseClassID=:tawasulCourseClassID, name=:name, description=:description, type=:type, date=:date, sequenceNumber=:sequenceNumber, attainment=:attainment, tawasulScaleIDAttainment=:tawasulScaleIDAttainment, attainmentWeighting=:attainmentWeighting, attainmentRaw=:attainmentRaw, attainmentRawMax=:attainmentRawMax, effort=:effort, tawasulScaleIDEffort=:tawasulScaleIDEffort, tawasulRubricIDAttainment=:tawasulRubricIDAttainment, tawasulRubricIDEffort=:tawasulRubricIDEffort, comment=:comment, uploadedResponse=:uploadedResponse, viewableStudents=:viewableStudents, viewableParents=:viewableParents, attachment=:attachment, tawasulPersonIDCreator=:tawasulPersonIDCreator, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit, tawasulSchoolYearTermID=:tawasulSchoolYearTermID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }

                }

                if ($partialFail) {
                    $URL .= '&return=warning1';
                    header("Location: {$URL}");
                    exit();
                } else {
                    $URL .= "&return=success0";
                    header("Location: {$URL}");
                }
            }

        }
    }
}

?>
