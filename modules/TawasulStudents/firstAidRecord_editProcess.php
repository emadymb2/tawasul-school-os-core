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

use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\Students\FirstAidGateway;
use TawasulOS\Domain\Students\FirstAidFollowupGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulFirstAidID = $_GET['tawasulFirstAidID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/firstAidRecord_edit.php&tawasulFirstAidID=$tawasulFirstAidID&tawasulFormGroupID=".$_GET['tawasulFormGroupID'].'&tawasulYearGroupID='.$_GET['tawasulYearGroupID'];

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/firstAidRecord_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    }

    if (empty($tawasulFirstAidID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $firstAidGateway = $container->get(FirstAidGateway::class);
    $values = $firstAidGateway->getByID($tawasulFirstAidID);

    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $tawasulPersonID = $values['tawasulPersonIDFirstAider'];
    $timeOut = !empty($_POST['timeOut']) ? $_POST['timeOut'] : null;
    $followUp = $_POST['followUp'] ?? '';

    // Only users with edit access can change this record
    if ($highestAction == 'First Aid Record_editAll') {
        $customRequireFail = false;
        $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('First Aid', [], $customRequireFail);

        if ($customRequireFail) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        // Update the record
        $data = ['timeOut' => $timeOut, 'fields' => $fields];
        $firstAidGateway->update($tawasulFirstAidID, $data);

        // Manage custom field file uploads
        if (!empty($fields)) {
            $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('First Aid', [], $fields, 'tawasulFirstAid', $tawasulFirstAidID, $values['fields'] ?? null);
        }
    }

    // Add a new follow up log, if needed
    if (!empty($followUp)) {
        $firstAidFollowUpGateway = $container->get(FirstAidFollowupGateway::class);

        $data = [
            'tawasulFirstAidID' => $tawasulFirstAidID,
            'tawasulPersonID' => $session->get('tawasulPersonID'),
            'followUp' => $followUp,
        ];

        $inserted = $firstAidFollowUpGateway->insert($data);

        if (!$inserted) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }
    }

    $URL .= '&return=success0';
    header("Location: {$URL}");

}
