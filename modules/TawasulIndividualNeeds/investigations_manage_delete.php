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

use TawasulOS\Forms\Prefab\DeleteForm;
use TawasulOS\Domain\IndividualNeeds\INInvestigationGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
$tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
$tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';

if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/investigations_manage_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get action with highest precedence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    }

    $tawasulINInvestigationID = $_GET['tawasulINInvestigationID'] ?? '';
    if (empty($tawasulINInvestigationID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $investigationGateway = $container->get(INInvestigationGateway::class);
    $investigation = $investigationGateway->getByID($tawasulINInvestigationID);

    if (empty($investigation)) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }

    $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/investigations_manage_deleteProcess.php");
    $form->addHiddenValue('tawasulINInvestigationID', $tawasulINInvestigationID);
    $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
    $form->addHiddenValue('tawasulFormGroupID', $tawasulFormGroupID);
    $form->addHiddenValue('tawasulYearGroupID', $tawasulYearGroupID);
    echo $form->getOutput();
}
