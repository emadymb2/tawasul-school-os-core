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

use TawasulOS\Comms\NotificationSender;
use TawasulOS\Domain\System\NotificationGateway;
use TawasulOS\Domain\IndividualNeeds\INInvestigationGateway;
use TawasulOS\Domain\IndividualNeeds\INInvestigationContributionGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\User\RoleGateway;
use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulINInvestigationID = $_POST['tawasulINInvestigationID'] ?? '';
$tawasulINInvestigationContributionID = $_POST['tawasulINInvestigationContributionID'] ?? '';

$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulIndividualNeeds/investigations_submit_detail.php&tawasulINInvestigationID=$tawasulINInvestigationID&tawasulINInvestigationContributionID=$tawasulINInvestigationContributionID";
$URLSuccess = $session->get('absoluteURL')."/index.php?q=/modules/TawasulIndividualNeeds/investigations_submit.php&tawasulINInvestigationID=$tawasulINInvestigationID&tawasulINInvestigationContributionID=$tawasulINInvestigationContributionID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/investigations_submit_detail.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Validate the database records exist
    $investigationGateway = $container->get(INInvestigationGateway::class);
    $investigation = $investigationGateway->getInvestigationByID($tawasulINInvestigationID);

    $contributionsGateway = $container->get(INInvestigationContributionGateway::class);
    $contribution = $contributionsGateway->getContributionByID($tawasulINInvestigationContributionID);

    if (empty($investigation) || empty($contribution) || $contribution['tawasulPersonID'] != $session->get('tawasulPersonID')) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    } else {
        $data = [
            'status'            => 'Complete',
            'cognition'         => $_POST['cognition'] ?? null,
            'memory'            => (!empty($_POST['memory'])) ? serialize($_POST['memory']) : '',
            'selfManagement'    => (!empty($_POST['selfManagement'])) ? serialize($_POST['selfManagement']) : '',
            'attention'         => (!empty($_POST['attention'])) ? serialize($_POST['attention']) : '',
            'socialInteraction' => (!empty($_POST['socialInteraction'])) ? serialize($_POST['socialInteraction']) : '',
            'communication'     => (!empty($_POST['communication'])) ? serialize($_POST['communication']) : '',
            'comment'           => $_POST['comment'] ?? ''
        ];

        // Update the record
        $updated = $contributionsGateway->update($tawasulINInvestigationContributionID, $data);

        //Check for completion, update status and issue notifications
        $completion = $contributionsGateway->getInvestigationCompletion($tawasulINInvestigationID);
        if ($completion['complete'] == $completion['total']) {
            $data = [
                'status'            => 'Investigation Complete',
            ];
            $investigationGateway->update($tawasulINInvestigationID, $data);

            $notificationGateway = $container->get(NotificationGateway::class);
            $notificationSender = $container->get(NotificationSender::class);;

            $studentName = Format::name('', $investigation['preferredName'], $investigation['surname'], 'Student', false, true);
            $notificationString = __('An Individual Needs investigation for {student} has been completed.', ['student' => $studentName]);

            //Originating teacher
            $notificationSender->addNotification($investigation['tawasulPersonIDCreator'], $notificationString, "Individual Needs", "/index.php?q=/modules/TawasulIndividualNeeds/investigations_manage_edit.php&tawasulINInvestigationID=$tawasulINInvestigationID");

            //Form tutors
            if ($investigation['tawasulPersonIDTutor'] != '') {
                $notificationSender->addNotification($investigation['tawasulPersonIDTutor'], $notificationString, "Individual Needs", "/index.php?q=/modules/TawasulIndividualNeeds/investigations_manage_edit.php&tawasulINInvestigationID=$tawasulINInvestigationID");
            }
            if ($investigation['tawasulPersonIDTutor2'] != '') {
                $notificationSender->addNotification($investigation['tawasulPersonIDTutor2'], $notificationString, "Individual Needs", "/index.php?q=/modules/TawasulIndividualNeeds/investigations_manage_edit.php&tawasulINInvestigationID=$tawasulINInvestigationID");
            }
            if ($investigation['tawasulPersonIDTutor3'] != '') {
                $notificationSender->addNotification($investigation['tawasulPersonIDTutor3'], $notificationString, "Individual Needs", "/index.php?q=/modules/TawasulIndividualNeeds/investigations_manage_edit.php&tawasulINInvestigationID=$tawasulINInvestigationID");
            }

            //HOY
            if ($investigation['tawasulPersonIDHOY'] != '') {
                $notificationSender->addNotification($investigation['tawasulPersonIDHOY'], $notificationString, "Individual Needs", "/index.php?q=/modules/TawasulIndividualNeeds/investigations_manage_edit.php&tawasulINInvestigationID=$tawasulINInvestigationID");
            }

            //LS role
            $notificationRole = $container->get(SettingGateway::class)->getSettingByScope('Individual Needs', 'investigationNotificationRole');
            if (!empty($notificationRole)) {
                $roleGateway = $container->get(RoleGateway::class);
                $criteria = $roleGateway->newQueryCriteria();
                $users = $roleGateway->queryUsersByRole($criteria, $notificationRole);
                foreach ($users AS $user) {
                    $notificationSender->addNotification($user['tawasulPersonID'], $notificationString, "Individual Needs", "/index.php?q=/modules/TawasulIndividualNeeds/investigations_manage_edit.php&tawasulINInvestigationID=$tawasulINInvestigationID");
                }
            }

            $notificationSender->sendNotifications();
        }

        if ($updated) {
            $URLSuccess .= "&return=success0";
            header("Location: {$URLSuccess}");
        }
        else {
            $URL .= "&return=error2";
            header("Location: {$URL}");
        }
    }
}
