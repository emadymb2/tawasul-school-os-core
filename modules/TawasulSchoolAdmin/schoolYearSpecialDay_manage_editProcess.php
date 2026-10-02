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
use TawasulOS\Domain\School\SchoolYearSpecialDayGateway;
use TawasulOS\Services\Format;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
$tawasulSchoolYearSpecialDayID = $_GET['tawasulSchoolYearSpecialDayID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/schoolYearSpecialDay_manage_edit.php&tawasulSchoolYearSpecialDayID='.$tawasulSchoolYearSpecialDayID.'&tawasulSchoolYearID='.$tawasulSchoolYearID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/schoolYearSpecialDay_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    if (empty($tawasulSchoolYearSpecialDayID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    }

    $specialDayGateway = $container->get(SchoolYearSpecialDayGateway::class);
    $values = $specialDayGateway->getByID($tawasulSchoolYearSpecialDayID);

    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit();
    }

    //Validate Inputs
    $data = [
        'type'                   => $_POST['type'] ?? '',
        'name'                   => $_POST['name'] ?? '',
        'description'            => $_POST['description'] ?? '',
        'tawasulSchoolYearTermID' => $_POST['tawasulSchoolYearTermID'] ?? '',
        'schoolOpen'             => null,
        'schoolStart'            => null,
        'schoolEnd'              => null,
        'schoolClose'            => null,
        'tawasulYearGroupIDList'  => $_POST['tawasulYearGroupIDList'] ?? '',
        'tawasulFormGroupIDList'  => $_POST['tawasulFormGroupIDList'] ?? '',
        'cancelActivities'       => $_POST['cancelActivities'] ?? 'N',
        'cancelBookings'         => $_POST['cancelBookings'] ?? 'N',
        'cancelClasses'          => $_POST['cancelClasses'] ?? 'N',
        'cancelDuty'             => $_POST['cancelDuty'] ?? 'N',
    ];

    if (!empty($_POST['schoolOpenH']) && is_numeric($_POST['schoolOpenH']) && is_numeric($_POST['schoolOpenM'])) {
        $data['schoolOpen'] = $_POST['schoolOpenH'].':'.$_POST['schoolOpenM'].':00';
    }

    if (!empty($_POST['schoolStartH']) && is_numeric($_POST['schoolStartH']) && is_numeric($_POST['schoolStartM'])) {
        $data['schoolStart'] = $_POST['schoolStartH'].':'.$_POST['schoolStartM'].':00';
    }

    if (!empty($_POST['schoolEndH']) && is_numeric($_POST['schoolEndH']) && is_numeric($_POST['schoolEndM'])) {
        $data['schoolEnd'] = $_POST['schoolEndH'].':'.$_POST['schoolEndM'].':00';
    }

    if (!empty($_POST['schoolCloseH']) && is_numeric($_POST['schoolCloseH']) && is_numeric($_POST['schoolCloseM'])) {
        $data['schoolClose'] = $_POST['schoolCloseH'].':'.$_POST['schoolCloseM'].':00';
    }

    if (!empty($data['tawasulYearGroupIDList']) && is_array($data['tawasulYearGroupIDList'])) {
        $data['tawasulYearGroupIDList'] = implode(',', $data['tawasulYearGroupIDList']);
    }

    if (!empty($data['tawasulFormGroupIDList']) && is_array($data['tawasulFormGroupIDList'])) {
        $data['tawasulFormGroupIDList'] = implode(',', $data['tawasulFormGroupIDList']);
    }

    // Update the term ID, or fallback to the previous one
    $tawasulSchoolYearTermID = isset($_POST['tawasulSchoolYearTermID'])? $_POST['tawasulSchoolYearTermID'] : $values['tawasulSchoolYearTermID'];

    if ($data['type'] == '' or $data['name'] == '' or $tawasulSchoolYearID == '') {
        $URL .= '&return=error3';
        header("Location: {$URL}");
    }

    $updated = $specialDayGateway->update($tawasulSchoolYearSpecialDayID, $data);

    $URL .= !$updated
        ? '&return=error2'
        : '&return=success0';
    header("Location: {$URL}");
}
