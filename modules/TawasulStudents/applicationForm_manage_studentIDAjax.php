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

//TawasulOS system-wide include
require_once __DIR__ . '/../../tawasul.php';

if (!$session->has('tawasulPersonID') || !$session->has('tawasulRoleIDPrimary')) {
    die(__('Your request failed because you do not have access to this action.'));
} else {
    $tawasulApplicationFormID = $_POST['tawasulApplicationFormID'] ?? '';
    $studentID = $_POST['studentID'] ?? '';
    if (empty($tawasulApplicationFormID) || empty($studentID)) {
        die(0);
    }

    $count = 0;

    $data = ['tawasulApplicationFormID' => $tawasulApplicationFormID, 'studentID' => $studentID];
    $sql = "SELECT COUNT(*) FROM tawasulApplicationForm WHERE studentID=:studentID AND tawasulApplicationFormID<>:tawasulApplicationFormID";
    $count += $pdo->selectOne($sql, $data);

    $data = ['studentID' => $studentID];
    $sql = "SELECT COUNT(*) FROM tawasulPerson WHERE studentID=:studentID";
    $count += $pdo->selectOne($sql, $data);

    echo $count;
}
