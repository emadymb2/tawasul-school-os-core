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
use TawasulOS\Contracts\Filesystem\FileHandler;
use TawasulOS\Domain\IndividualNeeds\StudentSupportPlanGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$tawasulStudentSupportPlanID = $_POST['tawasulStudentSupportPlanID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulIndividualNeeds/in_supportPlan_manage.php&tawasulPersonID='.urlencode($tawasulPersonID);

if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/in_supportPlan_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

if (empty($tawasulStudentSupportPlanID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

$planGateway = $container->get(StudentSupportPlanGateway::class);
$plan = $planGateway->getByID($tawasulStudentSupportPlanID);

if (empty($plan) || $plan['tawasulPersonID'] != $tawasulPersonID) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

// Delete physical file if type is File
if ($plan['type'] == 'File' && !empty($plan['filePath'])) {
    $container->get(FileHandler::class)->deleteFile('tawasulStudentSupportPlan', $tawasulStudentSupportPlanID, 'filePath');
}

$deleted = $planGateway->delete($tawasulStudentSupportPlanID);

if (!$deleted) {
    $URL .= '&return=error2';
} else {
    $URL .= '&return=success0';
}

header("Location: {$URL}");
exit;
