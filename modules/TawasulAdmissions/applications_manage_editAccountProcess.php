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
use TawasulOS\Domain\Admissions\AdmissionsAccountGateway;
use TawasulOS\Domain\Admissions\AdmissionsApplicationGateway;

require_once __DIR__ . '/../../tawasul.php';

$tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
$tawasulAdmissionsApplicationID = $_POST['tawasulAdmissionsApplicationID'] ?? '';
$tawasulAdmissionsAccountID = $_POST['tawasulAdmissionsAccountID'] ?? '';
$search = $_POST['search'] ?? '';
$tab = $_POST['tab'] ?? 0;

$URL = Url::fromModuleRoute('TawasulAdmissions', 'applications_manage_edit')->withQueryParams(['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulAdmissionsApplicationID' => $tawasulAdmissionsApplicationID, 'search' => $search, 'tab' => $tab]);

if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/applications_manage_edit.php') == false) {
    header("Location: {$URL->withReturn('error0')}");
    exit;
} else {
    // Proceed!

    // Get the application form data
    $admissionsApplicationGateway = $container->get(AdmissionsApplicationGateway::class);
    $application = $admissionsApplicationGateway->getByID($tawasulAdmissionsApplicationID);
    if (empty($tawasulAdmissionsApplicationID) || empty($application)) {
        header("Location: {$URL->withReturn('error1')}");
        exit;
    }

    // Get the new admissions account
    $account = $container->get(AdmissionsAccountGateway::class)->getByID($tawasulAdmissionsAccountID);
    if (empty($account)) {
        header("Location: {$URL->withReturn('error1')}");
        exit;
    }

    // Check that this application is associated with an account
    if ($application['foreignTable'] != 'tawasulAdmissionsAccount') {
        header("Location: {$URL->withReturn('error2')}");
        exit;
    }

    $updated = $admissionsApplicationGateway->update($tawasulAdmissionsApplicationID, [
        'foreignTableID' => $tawasulAdmissionsAccountID,
    ]);

    header("Location: {$URL->withReturn(!$updated ? 'error2' : 'success0')}");
}
