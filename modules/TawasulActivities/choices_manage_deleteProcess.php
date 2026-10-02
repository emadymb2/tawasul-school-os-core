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
use TawasulOS\Domain\Activities\ActivityChoiceGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulActivityCategoryID = $_POST['tawasulActivityCategoryID'] ?? '';
$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulActivities/choices_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/choices_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} elseif (empty($tawasulActivityCategoryID) || empty($tawasulPersonID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $choiceGateway = $container->get(ActivityChoiceGateway::class);

    $choices = $container->get(ActivityChoiceGateway::class)->selectChoicesByPerson($tawasulActivityCategoryID, $tawasulPersonID);
    if (empty($choices)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $deleted = $choiceGateway->deleteWhere(['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'tawasulPersonID' => $tawasulPersonID]);

    $URL .= !$deleted
        ? '&return=error2'
        : '&return=success0';

    header("Location: {$URL}");
}
