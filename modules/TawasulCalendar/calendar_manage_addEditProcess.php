<?php
/*
TawasulOS, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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
use TawasulOS\Services\Format;
use TawasulOS\Domain\Calendar\CalendarGateway;
use TawasulOS\Domain\Calendar\CalendarEditorGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['summary' => 'HTML']);

$tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
$tawasulCalendarID = $_REQUEST['tawasulCalendarID'] ?? null;

$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulCalendar/calendar_manage_addEdit.php&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCalendarID=$tawasulCalendarID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_manage_addEdit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $calendarGateway = $container->get(CalendarGateway::class);
    $editorGateway = $container->get(CalendarEditorGateway::class);

    $data = [
        'tawasulSchoolYearID'   => $tawasulSchoolYearID,
        'name'                 => $_POST['name'] ?? '',
        'description'          => $_POST['description'] ?? '',
        'color'                => $_POST['color'] ?? '',
        'summary'              => $_POST['summary'] ?? '',
        'public'               => $_POST['public'] ?? 'N',
        'viewableStaff'        => $_POST['viewableStaff'] ?? 'N',
        'viewableStudents'     => $_POST['viewableStudents'] ?? 'N',
        'viewableParents'      => $_POST['viewableParents'] ?? 'N',
        'viewableOther'        => $_POST['viewableOther'] ?? 'N',
        'viewableParticipants' => $_POST['viewableParticipants'] ?? 'N',
        'editableStaff'        => $_POST['editableStaff'] ?? 'N',
    ];

    // Validate the required values are present
    if (empty($data['name'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$calendarGateway->unique($data, ['name', 'tawasulSchoolYearID'], $tawasulCalendarID)) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Create the record
    if (!empty($tawasulCalendarID)) {
        $calendarGateway->update($tawasulCalendarID, $data);
    } else {
        $tawasulCalendarID = $calendarGateway->insert($data);
    }

    if (empty($tawasulCalendarID)) {
        $URL .= "&return=error2";
        header("Location: {$URL}");
        exit;
    }

    // Update the editors
    $editors = $_POST['editors'] ?? [];
    $editorIDs = [];
    foreach ($editors as $person) {
        $editorData = [
            'tawasulCalendarID' => $tawasulCalendarID,
            'tawasulPersonID'   => $person['tawasulPersonID'],
            'editAllEvents'    => $person['editAllEvents'] ?? 'N',
        ];

        $tawasulCalendarEditorID = $person['tawasulCalendarEditorID'] ?? '';

        if (!empty($tawasulCalendarEditorID)) {
            $partialFail &= !$editorGateway->update($tawasulCalendarEditorID, $editorData);
        } else {
            $tawasulCalendarEditorID = $editorGateway->insert($editorData);
            $partialFail &= !$tawasulCalendarEditorID;
        }

        $editorIDs[] = str_pad($tawasulCalendarEditorID, 10, '0', STR_PAD_LEFT);
    }

    // Cleanup editor that have been deleted
    $editorGateway->deleteEditorsNotInList($tawasulCalendarID, $editorIDs);

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0&editID=$tawasulCalendarID";

    header("Location: {$URL}");
}
