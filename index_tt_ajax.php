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

use TawasulOS\Domain\Timetable\TimetableGateway;
use TawasulOS\Services\Format;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\UI\Timetable\Timetable;
use TawasulOS\UI\Timetable\TimetableContext;

// TawasulOS system-wide includes
require_once __DIR__ . '/tawasul.php';

// Setup variables
$tawasulTTID = $_REQUEST['tawasulTTID'] ?? null;
$tawasulPersonID = $_REQUEST['tawasulPersonID'] ?? $session->get('tawasulPersonID');
$tawasulSpaceID = $_REQUEST['tawasulSpaceID'] ?? null;
$format = $_REQUEST['format'] ?? '';
$edit = $_REQUEST['edit'] ?? false;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/tt.php') == false) {
    // Access denied
    echo Format::alert(__('Your request failed because you do not have access to this action.'), 'error');
} else {
    include './modules/TawasulTimetable/moduleFunctions.php';

    $ttDate = null;

    if (!empty($_REQUEST['ttDateNav'])) {
        $ttDate = $_REQUEST['ttDateNav'];
    } elseif (!empty($_REQUEST['ttDateChooser'])) {
        $ttDate = $_REQUEST['ttDateChooser'];
    } elseif (!empty($_REQUEST['ttDate'])) {
        $ttDate = Format::dateConvert($_REQUEST['ttDate']);
    }

    // Get and update preferences
    $userGateway = $container->get(UserGateway::class);
    if (!empty($tawasulTTID)) {
        $userGateway->setUserPreferenceByScope($session->get('tawasulPersonID'), 'ttOptions', 'tawasulTTID', preg_replace('/[^0-9]/', '', $tawasulTTID));
    }

    // Create timetable context
    $context = $container->get(TimetableContext::class)
        ->set('tawasulSchoolYearID', $session->get('tawasulSchoolYearID'))
        ->set('tawasulPersonID', $tawasulPersonID)
        ->set('tawasulSpaceID', $tawasulSpaceID)
        ->set('tawasulTTID', $tawasulTTID)
        ->set('format', $format)
        ->set('edit', $edit);

    // Build and render timetable
    echo $container->get(Timetable::class)
        ->setDate($ttDate)
        ->setContext($context)
        ->addCoreLayers($container)
        ->getOutput(); 
}
