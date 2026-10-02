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

use TawasulOS\Domain\Activities\ActivityStaffGateway;

require_once __DIR__ . '/../../tawasul.php';

$tawasulActivityID = $_POST['tawasulActivityID'] ?? '';
$tawasulActivityStaffID = $_POST['tawasulActivityStaffID'] ?? '';
$search = $_POST['search'] ?? '';
$tawasulSchoolYearTermID = $_POST['tawasulSchoolYearTermID'] ?? '';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module') . "/activities_manage_edit.php&tawasulActivityID=$tawasulActivityID&search=$search&tawasulSchoolYearTermID=$tawasulSchoolYearTermID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    $activityStaffGateway = $container->get(ActivityStaffGateway::class);

    if (!$activityStaffGateway->exists($tawasulActivityStaffID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        if (!$activityStaffGateway->delete($tawasulActivityStaffID)) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        $URL .= '&return=success0';
        header("Location: {$URL}");
    }
}
