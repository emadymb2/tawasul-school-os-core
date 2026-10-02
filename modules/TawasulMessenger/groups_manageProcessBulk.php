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

require_once __DIR__ . '/../../tawasul.php';

$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/groups_manage.php";

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/groups_manage.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $action = $_POST['action'] ?? '';
    $tawasulGroupIDList = $_POST['tawasulGroupIDList'] ?? '';
    $tawasulSchoolYearIDCopyTo = $_POST['tawasulSchoolYearIDCopyTo'] ?? array();

    if (empty($action) || empty($tawasulGroupIDList)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $groupGateway = $container->get(GroupGateway::class);
        $partialFail = false;

        if ($action == 'Duplicate' || $action == 'DuplicateMembers') {
            foreach ($tawasulGroupIDList as $tawasulGroupID) {
                $data = $groupGateway->getByID($tawasulGroupID);
                $data['tawasulSchoolYearID'] = $tawasulSchoolYearIDCopyTo;

                // Copy groups to selected year
                $inserted = $groupGateway->insert($data);
                $partialFail &= !$inserted;

                // Optionally add members to new group
                if ($inserted && $action == 'DuplicateMembers') {
                    $members = $groupGateway->selectPersonIDsByGroup($tawasulGroupID)->fetchAll();
                    if (empty($members)) continue;

                    foreach ($members as $member) {
                        $insertedMember = $groupGateway->insertGroupPerson([
                            'tawasulGroupID' => $inserted,
                            'tawasulPersonID' => $member['tawasulPersonID'],
                        ]);
                        $partialFail &= !$insertedMember;
                    }
                }
            }
        } elseif ($action == 'Delete') {
            foreach ($tawasulGroupIDList as $tawasulGroupID) {
                $deleted = $groupGateway->delete($tawasulGroupID);
                $partialFail &= !$deleted;

                $deleted = $groupGateway->deletePeopleByGroupID($tawasulGroupID);
                $partialFail &= !$deleted;
            }
        } else {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        $URL .= $partialFail
            ? "&return=warning1"
            : "&return=success0";

        header("Location: {$URL}");
    }
}
