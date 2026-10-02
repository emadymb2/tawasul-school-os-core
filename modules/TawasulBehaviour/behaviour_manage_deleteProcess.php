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

use TawasulOS\Domain\Behaviour\BehaviourGateway;
use TawasulOS\UI\Components\Alert;

require_once __DIR__ . '/../../tawasul.php';

$tawasulBehaviourID = $_POST['tawasulBehaviourID'] ?? '';
$address = $_POST['address'] ?? '';
$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$tawasulFormGroupID = $_POST['tawasulFormGroupID'] ?? '';
$tawasulYearGroupID = $_POST['tawasulYearGroupID'] ?? '';
$type = $_GET['type'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/behaviour_manage_delete.php&tawasulBehaviourID=$tawasulBehaviourID&tawasulPersonID=$tawasulPersonID&tawasulFormGroupID=$tawasulFormGroupID&tawasulYearGroupID=$tawasulYearGroupID&type=$type";
$URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/behaviour_manage.php&tawasulPersonID=$tawasulPersonID&tawasulFormGroupID=$tawasulFormGroupID&tawasulYearGroupID=$tawasulYearGroupID&type=$type";

if (isActionAccessible($guid, $connection2, '/modules/TawasulBehaviour/behaviour_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $address, $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        // Proceed!
        if ($tawasulBehaviourID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            $result = $container->get(BehaviourGateway::class)->getByID($tawasulBehaviourID);

            if (empty($result)) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                $row = $result;

                // Write to database
                $deleted = $container->get(BehaviourGateway::class)->delete($tawasulBehaviourID);

                if (!$deleted) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                // ALERTS: possible change to Behaviour alert status, recalculate alerts
                $container->get(Alert::class)->recalculateAlerts($row['tawasulPersonID']);

                $URLDelete = $URLDelete.'&return=success0';
                header("Location: {$URLDelete}");
            }
        }
    }
}
