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
use TawasulOS\Domain\User\UserGateway;

require_once __DIR__ . '/../../tawasul.php';

$tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
$search = $_POST['search'] ?? '';
$tawasulFormID = $_POST['tawasulFormID'] ?? '';
$applicationType = $_POST['applicationType'] ?? '';
$tawasulAdmissionsAccountID = $_POST['tawasulAdmissionsAccountID'] ?? '';
$tawasulPersonID = $_POST['tawasulPersonID'] ?? null;
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

$URL = Url::fromModuleRoute('TawasulAdmissions', 'applications_manage_addSelect')->withQueryParams(['tawasulSchoolYearID' => $tawasulSchoolYearID, 'search' => $search]);

if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/applications_manage_add.php') == false) {
    header("Location: {$URL->withReturn('error0')}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    if (empty($tawasulFormID)) {
        header("Location: {$URL->withReturn('error1')}");
        exit;
    }

    $admissionsAccountGateway = $container->get(AdmissionsAccountGateway::class);
    switch ($applicationType) {
        case 'blank':
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $account = !empty($email) ? $admissionsAccountGateway->selectBy(['email' => $email])->fetch() : [];
            break;

        case 'account':
            $account = $admissionsAccountGateway->getByID($tawasulAdmissionsAccountID);
            break;
            
        case 'person':
            $account = $admissionsAccountGateway->getAccountByPerson($tawasulPersonID);
            if (empty($account)) {
                $person = $container->get(UserGateway::class)->getByID($tawasulPersonID, ['email']);
                $email = $person['email'] ?? '';
            }
            break;
    }

    if ($applicationType != 'blank' && empty($account) && empty($email)) {
        header("Location: {$URL->withReturn('error3')}");
        exit;
    }

    if (empty($account)) {
        // New account
        $accessID = $admissionsAccountGateway->getUniqueAccessID($guid.$email);
        $accessToken = $admissionsAccountGateway->getUniqueAccessToken($guid.$accessID);
        $accessExpiry = date('Y-m-d H:i:s', strtotime("+2 days"));

        $tawasulAdmissionsAccountID = $admissionsAccountGateway->insert([
            'email'                => $email ?? null,
            'accessID'             => $accessID,
            'accessToken'          => $accessToken,
            'timestampTokenExpire' => $accessExpiry,
            'tawasulPersonID'       => $tawasulPersonID ?? null,
            'ipAddress'            => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        $accountType = 'new';
    } else {   
        // Existing account
        $accessID = $account['accessID'];
        $tawasulAdmissionsAccountID = $account['tawasulAdmissionsAccountID'];
        $accountType = 'existing';
    }

    if (empty($tawasulAdmissionsAccountID) || empty($accessID)) {
        header("Location: {$URL->withReturn('error2')}");
        exit;
    }


    $URL = Url::fromModuleRoute('TawasulAdmissions', 'applications_manage_add')->withQueryParams([
        'tawasulSchoolYearID' => $tawasulSchoolYearID,
        'search'             => $search,
        'tawasulFormID'       => $tawasulFormID,
        'accessID'           => $accessID,
        'accountType'        => $accountType,
    ]);
    header("Location: {$URL}");
    exit;
}
