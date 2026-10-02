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
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulUsernameFormatID = $_POST['tawasulUsernameFormatID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/userSettings_usernameFormat_edit.php&tawasulUsernameFormatID='.$tawasulUsernameFormatID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/userSettings.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    $format = $_POST['format'] ?? '';
    $tawasulRoleIDList = $_POST['tawasulRoleIDList'] ?? [];
    $isDefault = $_POST['isDefault'] ?? '';
    $isNumeric = $_POST['isNumeric'] ?? '';
    $numericValue = $_POST['numericValue'] ?? 1;
    $numericSize = $_POST['numericSize'] ?? 4;
    $numericIncrement = $_POST['numericIncrement'] ?? 1;

    if (empty($format) || empty($tawasulRoleIDList)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $tawasulRoleIDList = implode(',', $tawasulRoleIDList);

        try {
            $data = array('tawasulUsernameFormatID' => $tawasulUsernameFormatID, 'format' => $format, 'tawasulRoleIDList' => $tawasulRoleIDList, 'isDefault' => $isDefault, 'isNumeric' => $isNumeric, 'numericValue' => $numericValue, 'numericSize' => $numericSize, 'numericIncrement' => $numericIncrement);
            $sql = "UPDATE tawasulUsernameFormat SET format=:format, tawasulRoleIDList=:tawasulRoleIDList, isDefault=:isDefault, isNumeric=:isNumeric, numericValue=:numericValue, numericSize=:numericSize, numericIncrement=:numericIncrement WHERE tawasulUsernameFormatID=:tawasulUsernameFormatID";
            $result = $pdo->executeQuery($data, $sql);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Update default
        if ($isDefault == 'Y') {
            $data = array('tawasulUsernameFormatID' => $tawasulUsernameFormatID);
            $sql = "UPDATE tawasulUsernameFormat SET isDefault='N' WHERE tawasulUsernameFormatID <> :tawasulUsernameFormatID";
            $result = $pdo->executeQuery($data, $sql);
        }

        //Success 0
        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit;
    }
}
