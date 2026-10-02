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

use TawasulOS\Services\Format;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Data\Validator;
use TawasulOS\Comms\NotificationSender;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\Students\FirstAidGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/firstAidRecord_add.php&tawasulFormGroupID='.$_GET['tawasulFormGroupID'].'&tawasulYearGroupID='.$_GET['tawasulYearGroupID'];

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/firstAidRecord_add.php') == false) {
    $URL .= '&return=error0&step=1';
    header("Location: {$URL}");
} else {
    //Proceed!
    $data = [
        'tawasulPersonIDPatient'    => $_POST['tawasulPersonID'] ?? '',
        'tawasulPersonIDFirstAider' => $session->get('tawasulPersonID'),
        'tawasulPersonIDFollowUp'   => $_POST['tawasulPersonIDFollowUp'] ?? null,
        'date'                     => !empty($_POST['date']) ? Format::dateConvert($_POST['date']) : '',
        'timeIn'                   => $_POST['timeIn'] ?? '',
        'description'              => $_POST['description'] ?? '',
        'actionTaken'              => $_POST['actionTaken'] ?? '',
        'followUp'                 => $_POST['followUp'] ?? '',
        'tawasulSchoolYearID'       => $session->get('tawasulSchoolYearID'),
    ];

    $firstAidGateway = $container->get(FirstAidGateway::class);
    $student = $container->get(UserGateway::class)->getByID($data['tawasulPersonIDPatient'], ['preferredName', 'surname']);

    if ($data['tawasulPersonIDPatient'] == '' || $data['tawasulPersonIDFirstAider'] == '' || $data['date'] == '' || $data['timeIn'] == '' || empty($student)) {
        $URL .= '&return=error1&step=1';
        header("Location: {$URL}");
        exit;
    }

    $customRequireFail = false;
    $data['fields'] = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('First Aid', [], $customRequireFail);

    if ($customRequireFail) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $tawasulFirstAidID = $firstAidGateway->insert($data);
    $tawasulFirstAidID = str_pad($tawasulFirstAidID, 12, '0', STR_PAD_LEFT);

    // Manage custom field file uploads
    if (!empty($data['fields'])) {
        $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('First Aid', [], $data['fields'], 'tawasulFirstAid', $tawasulFirstAidID);
    }

    // Send a notification to the requested user
    if (!empty($data['tawasulPersonIDFollowUp'])) {
        $notificationSender = $container->get(NotificationSender::class);

        $text = __('A first aid record has been created for {name} at {time}. Your follow-up has been requested. Please click below to view and enter details.', ['name' => Format::name('', $student['preferredName'], $student['surname'], 'Student'), 'time' => $data['timeIn']]);
        $actionLink = '/index.php?q=/modules/TawasulStudents/firstAidRecord_edit.php&tawasulFirstAidID='.$tawasulFirstAidID;

        $notificationSender->addNotification($data['tawasulPersonIDFollowUp'], $text, 'First Aid', $actionLink);
        $notificationSender->sendNotifications();
    }

    $URL .= "&return=success0&editID=$tawasulFirstAidID";
    header("Location: {$URL}");
}
