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

use TawasulOS\Domain\Messenger\GroupGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulGroupID = $_GET['tawasulGroupID'] ?? '';
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/groups_manage_edit.php&tawasulGroupID=$tawasulGroupID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/groups_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    if (empty($tawasulGroupID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $name = $_POST['name'] ?? '';
        $choices = $_POST['members'] ?? array();

        if (empty($name)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        } else {
            $groupGateway = $container->get(GroupGateway::class);

            $highestAction = getHighestGroupedAction($guid, '/modules/TawasulMessenger/groups_manage.php', $connection2);
            if ($highestAction == 'Manage Groups_all') {
                $values = $groupGateway->selectGroupByID($tawasulGroupID);
            } else {
                $values = $groupGateway->selectGroupByIDAndOwner($tawasulGroupID, $session->get('tawasulPersonID'));
            }

            if (empty($values)) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit;
            } else {
                $data = array('tawasulGroupID' => $tawasulGroupID, 'name' => $name);
                $updated = $groupGateway->updateGroup($data);
                $partialFail = false;

                if (count($choices) > 0) {
                    foreach ($choices as $tawasulPersonID) {
                        $data = array('tawasulGroupID' => $tawasulGroupID, 'tawasulPersonID' => $tawasulPersonID);
                        $inserted = $groupGateway->insertGroupPerson($data);
                        $partialFail &= !$inserted;
                    }
                }

                if ($partialFail) {
                    $URL .= '&return=warning1';
                    header("Location: {$URL}");
                    exit;
                } else {
                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                    exit;
                }
            }
        }
    }
}
