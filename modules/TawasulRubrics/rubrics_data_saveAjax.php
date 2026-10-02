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

require_once __DIR__ . '/../../tawasul.php';

include './moduleFunctions.php';

if (!$session->has('tawasulPersonID') || $session->get('tawasulRoleIDCurrentCategory') != 'Staff') {
    return;
}

$mode = $_GET['mode'] ?? '';
if ($mode == 'Add') {
    
        $data = array('tawasulRubricID' => $_GET['tawasulRubricID'], 'tawasulPersonID' => $_GET['tawasulPersonID'], 'tawasulRubricCellID' => $_GET['tawasulRubricCellID'], 'contextDBTable' => $_GET['contextDBTable'], 'contextDBTableID' => $_GET['contextDBTableID']);
        $sql = 'INSERT INTO tawasulRubricEntry SET tawasulRubricID=:tawasulRubricID, tawasulPersonID=:tawasulPersonID, tawasulRubricCellID=:tawasulRubricCellID, contextDBTable=:contextDBTable, contextDBTableID=:contextDBTableID';
        $result = $connection2->prepare($sql);
        $result->execute($data);
}
if ($mode == 'Remove') {
    
        $data = array('tawasulRubricID' => $_GET['tawasulRubricID'], 'tawasulPersonID' => $_GET['tawasulPersonID'], 'tawasulRubricCellID' => $_GET['tawasulRubricCellID'], 'contextDBTable' => $_GET['contextDBTable'], 'contextDBTableID' => $_GET['contextDBTableID']);
        $sql = 'DELETE FROM tawasulRubricEntry WHERE tawasulRubricID=:tawasulRubricID AND tawasulPersonID=:tawasulPersonID AND tawasulRubricCellID=:tawasulRubricCellID AND contextDBTable=:contextDBTable AND contextDBTableID=:contextDBTableID';
        $result = $connection2->prepare($sql);
        $result->execute($data);
}
