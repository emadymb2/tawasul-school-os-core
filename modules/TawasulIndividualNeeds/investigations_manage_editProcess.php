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
use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
$tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
$tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';
$tawasulINInvestigationID = $_POST['tawasulINInvestigationID'] ?? '';

$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulIndividualNeeds/investigations_manage_edit.php&tawasulINInvestigationID=$tawasulINInvestigationID&tawasulPersonID=$tawasulPersonID&tawasulFormGroupID=$tawasulFormGroupID&tawasulYearGroupID=$tawasulYearGroupID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/investigations_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    $highestAction = getHighestGroupedAction($guid, '/modules/TawasulIndividualNeeds/investigations_manage.php', $connection2);
    if ($highestAction == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    // Proceed!
    $investigationGateway = $container->get(INInvestigationGateway::class);

    $data = [
        'reason'                => $_POST['reason'] ?? '',
        'strategiesTried'       => $_POST['strategiesTried'] ?? '',
        'parentsInformed'       => $_POST['parentsInformed'] ?? '',
        'parentsResponse'       => $_POST['parentsResponse'] ?? null
    ];

    // Validate the required values are present
    if (empty($data['reason']) || empty($data['parentsInformed'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database record exist
    $investigation = $investigationGateway->getInvestigationByID($tawasulINInvestigationID);
    if (empty($investigation)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $canEdit = false ;
    if ($highestAction == 'Manage Investigations_all' || ($highestAction == 'Manage Investigations_my' && ($investigation['tawasulPersonIDCreator'] == $session->get('tawasulPersonID')))) {
        $canEdit = true ;
    }

    $isTutor = false ;
    if ($investigation['tawasulPersonIDTutor'] == $session->get('tawasulPersonID') || $investigation['tawasulPersonIDTutor2'] == $session->get('tawasulPersonID') || $investigation['tawasulPersonIDTutor3'] == $session->get('tawasulPersonID')) {
        $isTutor = true ;
    }

    if (!$canEdit && !$isTutor) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $updated = $investigationGateway->update($tawasulINInvestigationID, $data);

    //Deal with resolution
    if ($isTutor && $investigation['status'] == 'Referral') {
        $notificationGateway = $container->get(NotificationGateway::class);
        $notificationSender = $container->get(NotificationSender::class);

        $studentName = Format::name('', $investigation['preferredName'], $investigation['surname'], 'Student', false, true);
        $status = ($_POST['resolvable'] == 'Y') ? 'Resolved' : 'Investigation';

        if ($status == 'Resolved') { //Notify the requesting teacher
            $data = [
                'resolutionDetails'      => $_POST['resolutionDetails'] ?? '',
                'status'                => $status,
            ];

            $updated = $investigationGateway->update($tawasulINInvestigationID, $data);

            $notificationString = __('An Individual Needs investigation for {student} has been resolved.', ['student' => $studentName]);
            $notificationSender->addNotification($investigation['tawasulPersonIDCreator'], $notificationString, "Individual Needs", "/index.php?q=/modules/TawasulIndividualNeeds/investigations_manage_edit.php&tawasulINInvestigationID=$tawasulINInvestigationID");
            $notificationSender->sendNotifications();
        } else if ($status == 'Investigation') { //Notify requesting teacher, and start further investigation
            $contributionGateway = $container->get(INInvestigationContributionGateway::class);

            //Get list of checked contributors
            $contributorsCount = 0;
            $contributors = array();
            if (!empty($_POST['tawasulPersonIDHOY'])) {
                $contributors[$contributorsCount]['type'] = 'Head of Year' ;
                $contributors[$contributorsCount]['tawasulPersonID'] = $_POST['tawasulPersonIDHOY'] ;
                $contributors[$contributorsCount]['tawasulCourseClassPersonID'] = null ;
                $contributorsCount++;
            }
            $tawasulCourseClassPersonIDs = $_POST['tawasulCourseClassPersonID'] ?? array();
            foreach ($tawasulCourseClassPersonIDs AS $tawasulCourseClassPersonID) {
                $contributors[$contributorsCount]['type'] = 'Teacher' ;
                $contributors[$contributorsCount]['tawasulPersonID'] = substr($tawasulCourseClassPersonID, 0, 10) ;
                $contributors[$contributorsCount]['tawasulCourseClassPersonID'] = substr($tawasulCourseClassPersonID, 11, 10) ;
                $contributorsCount++;
            }

            if (count($contributors) <1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit;
            } else {
                //Update investigation status
                $data = [
                    'status'                => $status,
                ];
                $updated = $investigationGateway->update($tawasulINInvestigationID, $data);

                foreach ($contributors AS $contributor) {
                    $contributor['tawasulINInvestigationID'] = $tawasulINInvestigationID;

                    //Insert contributor
                    $insert = $contributionGateway->insert($contributor);

                    //Notify contributor
                    $notificationString = __('Your input into an Individual Needs investigation for {student} has been requested.', ['student' => $studentName]);
                    $notificationSender->addNotification($contributor['tawasulPersonID'], $notificationString, "Individual Needs", "/index.php?q=/modules/TawasulIndividualNeeds/investigations_submit_detail.php&tawasulINInvestigationID=$tawasulINInvestigationID&tawasulINInvestigationContributionID=$insert");
                }
            }

            //Notify requesting teacher
            $notificationString = __('Further inquiry into the Individual Needs investigation for {student} has been initiated.', ['student' => $studentName]);
            $notificationSender->addNotification($investigation['tawasulPersonIDCreator'], $notificationString, "Individual Needs", "/index.php?q=/modules/TawasulIndividualNeeds/investigations_manage_edit.php&tawasulINInvestigationID=$tawasulINInvestigationID");
            $notificationSender->sendNotifications();
        }
    }

    $URL .= !$updated
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}
