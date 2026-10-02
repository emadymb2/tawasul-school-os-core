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

use TawasulOS\Domain\Timetable\TimetableDayGateway;

require_once __DIR__ . '/../../tawasul.php';

$tawasulCourseClassID = $_REQUEST['tawasulCourseClassID'] ?? '';
$tawasulTTID = $_REQUEST['tawasulTTID'] ?? '';

$URL = $session->get('absoluteURL') . "/index.php?q=/modules/TawasulTimetableAdmin/tt_edit_byClass.php&tawasulTTID={$tawasulTTID}&tawasulCourseClassID={$tawasulCourseClassID}";

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_byClass.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    if (empty($tawasulCourseClassID) || empty($tawasulTTID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $timetableDayGateway = $container->get(TimetableDayGateway::class);

    $entryOrders = $_POST['order'] ?? [];
    $entries = [];

    foreach ($entryOrders as $order) {
        $entry = $_POST['ttBlocks'][$order];

        if (empty($entry['tawasulTTColumnRowID']) || empty($entry['tawasulTTDayID'])) {
            continue;
        }

        $data = [
            'tawasulTTColumnRowID' => strstr($entry['tawasulTTColumnRowID'], '-', true),
            'tawasulTTDayID'       => $entry['tawasulTTDayID'],
            'tawasulCourseClassID' => $tawasulCourseClassID,
            'tawasulSpaceID'       => $entry['tawasulTTSpaceID'] ?? ''
        ]; 
        
        if (!empty($entry['tawasulTTDayRowClassID'])) {
            // Already exists, update
            $tawasulTTDayRowClassID = $entry['tawasulTTDayRowClassID'];
            $timetableDayGateway->updateDayRowClass($tawasulTTDayRowClassID, $data);
        } else {
            // Doesn't exist, create new
            $tawasulTTDayRowClassID = $timetableDayGateway->insertDayRowClass($data);
            $tawasulTTDayRowClassID = str_pad($tawasulTTDayRowClassID, 12, '0', STR_PAD_LEFT);
        }

        $entries[] = $tawasulTTDayRowClassID;
    }

    $timetableDayGateway->deleteTTDayRowClassesNotInSet($tawasulTTID, $tawasulCourseClassID, $entries);

    $URL .= count($entries) != count($entryOrders)
        ? '&return=warning1'
        : '&return=success0';
    header("Location: {$URL}");
    exit;
}
