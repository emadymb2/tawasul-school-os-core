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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Services\Format;
use TawasulOS\Domain\Students\ApplicationFormGateway;
use TawasulOS\Contracts\Comms\Mailer;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulApplicationFormID = $_POST['tawasulApplicationFormID'] ?? '';
$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
$search = $_GET['search'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/applicationForm_manage_edit.php&tawasulApplicationFormID=$tawasulApplicationFormID&tawasulSchoolYearID=$tawasulSchoolYearID&search=$search";

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/applicationForm_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} elseif (empty($tawasulApplicationFormID) or empty($tawasulSchoolYearID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
} else {
    // Proceed!
    $application = $container->get(ApplicationFormGateway::class)->getByID($tawasulApplicationFormID);
    if (empty($application)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
    }

    $settingGateway = $container->get(SettingGateway::class);
    $currency = $settingGateway->getSettingByScope('System', 'currency');
    $applicationProcessFee = $settingGateway->getSettingByScope('Application Form', 'applicationProcessFee');
    $applicationProcessFeeText = $_POST['applicationProcessFeeText'] ?? '';
    if (empty($applicationProcessFee) || empty($applicationProcessFeeText)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    }

    $subject = __('Application Fee');
    $body = __($applicationProcessFeeText);

    $mail = $container->get(Mailer::class);
    $mail->AddAddress($application['parent1email']);
    $mail->setDefaultSender($subject);
    $mail->renderBody('mail/message.twig.html', [
        'title'  => $subject,
        'body'   => $body,
        'details' => [
            __('Application ID')             => $tawasulApplicationFormID,
            __('Application Processing Fee') => $currency.$applicationProcessFee,
        ],
        'button' => [
            'url'  => 'index.php?q=/modules/TawasulStudents/applicationForm_payFee.php&key='.$application['tawasulApplicationFormHash'],
            'text' => __('Pay Online'),
        ],
    ]);

    if ($mail->Send()) {
        $URL = $URL.'&return=success0';
        header("Location: {$URL}");
    } else {
        $URL = $URL.'&return=error3';
        header("Location: {$URL}");
    }
}
