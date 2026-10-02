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
use TawasulOS\Domain\Activities\ActivityCategoryGateway;
use TawasulOS\Domain\Activities\ActivityChoiceGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Activities\ActivityGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$params = [
    'mode'                => $_POST['mode'] ?? '',
    'tawasulActivityCategoryID' => $_POST['tawasulActivityCategoryID'] ?? '',
    'tawasulPersonID'      => $_POST['tawasulPersonID'] ?? '',
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulActivities/choices_manage_addEdit.php&'.http_build_query($params);

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/choices_manage_addEdit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $categoryGateway = $container->get(ActivityCategoryGateway::class);
    $activityGateway = $container->get(ActivityGateway::class);
    $choiceGateway = $container->get(ActivityChoiceGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    $choices = $_POST['choices'] ?? [];

    // Validate the required values are present
    if (empty($choices) || empty($params['tawasulPersonID']) || empty($params['tawasulActivityCategoryID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $category = $categoryGateway->getCategoryDetailsByID($params['tawasulActivityCategoryID']);
    if (empty($category)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $existingChoices = $choiceGateway->selectChoicesByPerson($params['tawasulActivityCategoryID'], $params['tawasulPersonID'])->fetchGroupedUnique();

    $category = $categoryGateway->getByID($params['tawasulActivityCategoryID']);
    $signUpChoices = $category['signUpChoices'] ?? 3;

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

        // Validate the choice number selected
        if ($choice <= 0 || $choice > $signUpChoices) {
            $URL .= '&return=error5';
            header("Location: {$URL}");
            exit;
        }

        // Prepare data to insert or update
        $choicesData = [
            'tawasulActivityID'         => $tawasulActivityID,
            'tawasulActivityCategoryID' => $params['tawasulActivityCategoryID'],
            'tawasulPersonID'           => $params['tawasulPersonID'],
            'choice'                   => $choice,
            'timestampModified'        => date('Y-m-d H:i:s'),
            'tawasulPersonIDModified'   => $session->get('tawasulPersonID'),
        ];

        $tawasulActivityChoiceID = $existingChoices[$choice]['tawasulActivityChoiceID'] ?? '';

        if (!empty($tawasulActivityChoiceID)) {
            $partialFail &= !$choiceGateway->update($tawasulActivityChoiceID, $choicesData);
        } else {
            $choicesData['timestampCreated'] = date('Y-m-d H:i:s');
            $choicesData['tawasulPersonIDCreated'] = $session->get('tawasulPersonID');

            $tawasulActivityChoiceID = $choiceGateway->insert($choicesData);
            $partialFail &= !$tawasulActivityChoiceID;
        }

        $choiceIDs[] = str_pad($tawasulActivityChoiceID, 12, '0', STR_PAD_LEFT);
    }

    // Cleanup sign ups that have been deleted
    $choiceGateway->deleteChoicesNotInList($params['tawasulActivityCategoryID'], $params['tawasulPersonID'], $choiceIDs);

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header($params['mode'] == 'add'
        ? "Location: {$URL}&editID={$params['tawasulActivityCategoryID']}&editID2={$params['tawasulPersonID']}"
        : "Location: {$URL}"
    );
}
