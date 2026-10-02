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
use TawasulOS\Domain\FormGroups\FormGroupGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulSchoolYearIDNext = $_GET['tawasulSchoolYearIDNext'] ?? '';
$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulSchoolAdmin/formGroup_manage.php&tawasulSchoolYearID=$tawasulSchoolYearIDNext";

if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/formGroup_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    $formGroupGateway = $container->get(FormGroupGateway::class);

    // Check if school years specified (current and next)
    if (empty($tawasulSchoolYearID) || empty($tawasulSchoolYearIDNext)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Get the existing form groups for this school year
    $formGroups = $formGroupGateway->selectFormGroupsBySchoolYear($tawasulSchoolYearID)->fetchAll();
    if (empty($formGroups)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $partialFail = false;
    $partialFailUnique = false;

    foreach ($formGroups as $formGroup) {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearIDNext, 'name' => $formGroup['name'], 'nameShort' => $formGroup['nameShort'], 'tawasulPersonIDTutor' => $formGroup['tawasulPersonIDTutor'], 'tawasulPersonIDTutor2' => $formGroup['tawasulPersonIDTutor2'], 'tawasulPersonIDTutor3' => $formGroup['tawasulPersonIDTutor3'], 'tawasulSpaceID' => $formGroup['tawasulSpaceID'], 'website' => $formGroup['website']];

        // Check for uniqueness in the next school year
        if (!$formGroupGateway->unique($data, ['tawasulSchoolYearID', 'nameShort'])) {
            $partialFailUnique = true;
            continue;
        }

        // Insert the new form group
        $tawasulFormGroupID = $formGroupGateway->insert($data);
        $partialFail &= !$tawasulFormGroupID;
    }

    if ($partialFailUnique == true) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
    } elseif ($partialFail == true) {
        $URL .= '&return=error5';
        header("Location: {$URL}");
    } else {
        $URL .= '&return=success0';
        header("Location: {$URL}");
    }
}
