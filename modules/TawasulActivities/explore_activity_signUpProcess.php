<?php
/*
TawasulOS, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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
use TawasulOS\Data\Validator;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Activities\ActivityCategoryGateway;
use TawasulOS\Domain\Activities\ActivityChoiceGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Students\StudentGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$params = [
    'tawasulActivityCategoryID' => $_REQUEST['tawasulActivityCategoryID'] ?? '',
    'tawasulActivityID' => $_REQUEST['tawasulActivityID'] ?? (!empty($_POST['choices'])? current($_POST['choices']) : ''),
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulActivities/explore_activity.php&sidebar=false&'.http_build_query($params);
$URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/TawasulActivities/activities_my.php&'.http_build_query($params);

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/explore_activity_signUp.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;
    $highestAction = getHighestGroupedAction($guid, '/modules/TawasulActivities/explore_activity_signUp.php', $connection2);

    $activityGateway = $container->get(ActivityGateway::class);
    $categoryGateway = $container->get(ActivityCategoryGateway::class);
    $choiceGateway = $container->get(ActivityChoiceGateway::class);
    $settingGateway = $container->get(SettingGateway::class);
    
    $tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
    $choices = $_POST['choices'] ?? [];

    // Only users with manage permission can sign up a different user
    $canManageChoice = isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage.php');
    if (!$canManageChoice) {
        $tawasulPersonID = $session->get('tawasulPersonID');
    }

    // Validate the required values are present
    if (empty($choices) || empty($tawasulPersonID) || empty($params['tawasulActivityCategoryID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $category = $categoryGateway->getCategoryDetailsByID($params['tawasulActivityCategoryID'] ?? '');
    if (empty($category)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Check that sign up is open based on the date
    $signUpIsOpen = false;
    if (!empty($category['accessOpenDate']) && !empty($category['accessCloseDate'])) {
        $accessOpenDate = DateTime::createFromFormat('Y-m-d H:i:s', $category['accessOpenDate'])->format('U');
        $accessCloseDate = DateTime::createFromFormat('Y-m-d H:i:s', $category['accessCloseDate'])->format('U');
        $now = (new DateTime('now'))->format('U');

        $signUpIsOpen = $accessOpenDate <= $now && $accessCloseDate >= $now;
    }

    // Can register for family children
    if ($highestAction == 'Explore Activities_registerByParent') {
        $categoryYearGroups = explode(',', $category['tawasulYearGroupIDParentRegister'] ?? ''); 
        $children = $container->get(StudentGateway::class)
            ->selectAnyStudentsByFamilyAdult($session->get('tawasulSchoolYearID'), $session->get('tawasulPersonID'))
            ->fetchGroupedUnique();

        $tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
        $child = $children[$tawasulPersonID] ?? [];
        if (empty($child) || !in_array($child['tawasulYearGroupID'], $categoryYearGroups)) {
            $URL .= '&return=error4';
            header("Location: {$URL}");
            exit;
        }

        $URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/TawasulActivities/activities_view_myChildren.php&'.http_build_query($params);
    }

    // Check the student's sign up access based on their year group
    $signUpCategory = $categoryGateway->getCategorySignUpAccess($params['tawasulActivityCategoryID'], $tawasulPersonID);

    if (!$signUpIsOpen || !$signUpCategory) {
        $URL .= '&return=error4&reason=1';
        header("Location: {$URL}");
        exit;
    }

    // Get experiences and choices
    $activities = $activityGateway->selectActivitiesByCategoryAndPerson($params['tawasulActivityCategoryID'], $tawasulPersonID)->fetchKeyPair();
    $choicesSelected = $choiceGateway->selectChoicesByPerson($params['tawasulActivityCategoryID'], $tawasulPersonID)->fetchGroupedUnique();

    $category = $categoryGateway->getByID($params['tawasulActivityCategoryID']);
    $signUpChoices = $category['signUpChoices'] ?? 3;

    // Lower the choice limit if there are less options
    if (count($activities) < $signUpChoices) {
        $signUpChoices = count($activities);
    }

    // Update the sign up choices
    $choiceIDs = [];
    foreach ($choices as $choice => $tawasulActivityID) {
        $choice = intval($choice);

        // Validate the experience selected
        if (!$activityGateway->exists($tawasulActivityID)) {
            $URL .= '&return=error5';
            header("Location: {$URL}");
            exit;
        }

        $signUpActivity = $activityGateway->getActivitySignUpAccess($tawasulActivityID, $tawasulPersonID);
        if (!$signUpActivity) {
            $URL .= '&return=error5';
            header("Location: {$URL}");
            exit;
        }

        // Validate the choice number selected
        if ($choice <= 0 || $choice > $signUpChoices) {
            $URL .= '&return=error5';
            header("Location: {$URL}");
            exit;
        }

        // Prepare data to insert or update
        $signUpData = [
            'tawasulActivityID'         => $tawasulActivityID,
            'tawasulActivityCategoryID' => $params['tawasulActivityCategoryID'],
            'tawasulPersonID'           => $tawasulPersonID,
            'choice'                   => $choice,
            'timestampModified'        => date('Y-m-d H:i:s'),
            'tawasulPersonIDModified'   => $session->get('tawasulPersonID'),
        ];

        $tawasulActivityChoiceID = $choicesSelected[$choice]['tawasulActivityChoiceID'] ?? '';

        if (!empty($tawasulActivityChoiceID)) {
            $partialFail &= !$choiceGateway->update($tawasulActivityChoiceID, $signUpData);
        } else {
            $signUpData['timestampCreated'] = date('Y-m-d H:i:s');
            $signUpData['tawasulPersonIDCreated'] = $session->get('tawasulPersonID');

            $tawasulActivityChoiceID = $choiceGateway->insert($signUpData);
            $partialFail &= !$tawasulActivityChoiceID;
        }

        $choiceIDs[] = str_pad($tawasulActivityChoiceID, 12, '0', STR_PAD_LEFT);
    }

    // Cleanup sign ups that have been deleted
    $choiceGateway->deleteChoicesNotInList($params['tawasulActivityCategoryID'], $tawasulPersonID, $choiceIDs);

    if ($partialFail) {
        $URL .= '&return=warning1';
        header("Location: {$URL}");
        exit;
    }

    $URLSuccess .= '&return=success1';
    header("Location: {$URLSuccess}");
}
