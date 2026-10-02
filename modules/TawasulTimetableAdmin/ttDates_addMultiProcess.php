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

use TawasulOS\Domain\School\SchoolYearGateway;
use TawasulOS\Domain\Timetable\TimetableDayGateway;
use TawasulOS\Domain\Timetable\TimetableDayDateGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$dates = $_POST['dates'] ?? [];
$tawasulTTDayID = $_POST['tawasulTTDayID'] ?? '';
$overwrite = $_POST['overwrite'] ?? 'N';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['q'])."/ttDates.php&tawasulSchoolYearID=$tawasulSchoolYearID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/ttDates_edit_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    // Proceed!
    $partialFail = false;

    // Validate Inputs
    if (empty($tawasulSchoolYearID) or empty($dates) or empty($tawasulTTDayID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $timetableDayGateway = $container->get(TimetableDayGateway::class);
    $timetableDayDateGateway = $container->get(TimetableDayDateGateway::class);

    // Validate records exist
    $schoolYear = $container->get(SchoolYearGateway::class)->getByID($tawasulSchoolYearID);
    $tawasulTTDay = $timetableDayGateway->getTTDayByID($tawasulTTDayID);
    if (empty($schoolYear) || empty($tawasulTTDay)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit();
    }
    
    foreach ($dates as $date) {
        if (!isSchoolOpen($guid, date('Y-m-d', $date), $connection2, true)) {
            $partialFail = true;
            continue;
        } 

        // Remove existing TT Day Dates if overwriting
        if ($overwrite == 'Y') {
            $timetableDayDateGateway->deleteWhere(['date' => date('Y-m-d', $date)]);
        }

        // Check if a day from the TT is already set
        $days = $timetableDayGateway->selectDaysByDate(date('Y-m-d', $date), $tawasulTTDay['tawasulTTID']);

        if ($days->rowCount() > 0) {
            $partialFail = true;
        } else {
            $data = ['tawasulTTDayID' => $tawasulTTDayID, 'date' => date('Y-m-d', $date)];
            $inserted = $timetableDayDateGateway->insert($data);
            $partialFail &= !$inserted;
        }
        
    }

    $URL .= $partialFail
        ? '&return=warning1'
        : '&return=success0';
    header("Location: {$URL}");

}
