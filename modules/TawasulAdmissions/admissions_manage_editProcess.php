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

use TawasulOS\Http\Url;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\Admissions\AdmissionsAccountGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulAdmissionsAccountID = $_POST['tawasulAdmissionsAccountID'] ?? '';
$search = $_POST['search'] ?? '';

$URL = Url::fromModuleRoute('TawasulAdmissions', 'admissions_manage_edit')->withQueryParams(['tawasulAdmissionsAccountID' => $tawasulAdmissionsAccountID, 'search' => $search]);

if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/admissions_manage_edit.php') == false) {
    header("Location: {$URL->withReturn('error0')}");
    exit;
} else {
    // Proceed!
    $admissionsAccountGateway = $container->get(AdmissionsAccountGateway::class);

    $data = [
        'email' => filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL),
    ];

    // Validate the required values are present
    if (empty($data['email']) || empty($tawasulAdmissionsAccountID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    if (!$admissionsAccountGateway->exists($tawasulAdmissionsAccountID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$admissionsAccountGateway->unique($data, ['email'], $tawasulAdmissionsAccountID)) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $updated = $admissionsAccountGateway->update($tawasulAdmissionsAccountID, $data);

    $URL .= !$updated
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}
