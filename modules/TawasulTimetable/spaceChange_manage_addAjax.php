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
use TawasulOS\Domain\School\FacilityGateway;
use TawasulOS\Domain\Timetable\TimetableDayDateGateway;

require_once __DIR__ . '/../../tawasul.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulTimetable/spaceBooking_manage_add.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/spaceBooking_manage_add.php') == false) {
    echo Format::alert(__('You do not have access to this action.'));
} else {
    
    $tawasulTTDayRowClassID = substr($_POST['tawasulTTDayRowClassID'] ?? '', 0, 12);
    $date = substr($_POST['tawasulTTDayRowClassID'] ?? '', 13);
    $tawasulSpaceID = $_POST['tawasulSpaceID'] ?? '';

    if (empty($tawasulTTDayRowClassID) || empty($date) || empty($tawasulSpaceID)) {
        echo Format::alert(__('You have not specified one or more required parameters.'));
        return;
    }

    $facilityGateway = $container->get(FacilityGateway::class);
    $ttDayDateGateway = $container->get(TimetableDayDateGateway::class);

    $facility = $facilityGateway->getByID($tawasulSpaceID);
    $period = $ttDayDateGateway->getTimetablePeriodByDayRowClass($tawasulTTDayRowClassID);

    if (empty($period) || empty($facility)) {
        echo Format::alert(__('You have not specified one or more required parameters.'));
        return;
    }

    $inUse = $facilityGateway->selectFacilityInUseByDateAndTime($tawasulSpaceID, $date, $period['timeStart'], $period['timeEnd'])->fetchAll(\PDO::FETCH_COLUMN, 0);

    if (!empty($inUse)) {
        echo Format::alert(__('In Use by {name} (Capacity: {capacity})', ['name' => implode(', ', $inUse), 'capacity' => $facility['capacity']]), 'error');
    } else {
        echo Format::alert(__('Available (Capacity: {capacity})', ['capacity' => $facility['capacity']]), 'success');
    }
    
}
