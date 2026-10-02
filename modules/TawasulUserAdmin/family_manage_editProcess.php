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
use TawasulOS\Forms\CustomFieldHandler;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulFamilyID = $_GET['tawasulFamilyID'] ?? '';
$search = $_GET['search'] ?? '';

if ($tawasulFamilyID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/family_manage_edit.php&tawasulFamilyID=$tawasulFamilyID&search=$search";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/family_manage_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        // Fetch old record for comparison
        try {
            $data = ['tawasulFamilyID' => $tawasulFamilyID];
            $sql = 'SELECT * FROM tawasulFamily WHERE tawasulFamilyID=:tawasulFamilyID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if (empty($result)) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        $oldFamilyRecord = $result->fetch();

        //Validate Inputs
        $name = $_POST['name'] ?? '';
        $status = $_POST['status'] ?? '';
        $languageHomePrimary = $_POST['languageHomePrimary'] ?? '';
        $languageHomeSecondary = $_POST['languageHomeSecondary'] ?? '';
        $nameAddress = $_POST['nameAddress'] ?? '';
        $homeAddress = $_POST['homeAddress'] ?? '';
        $homeAddressDistrict = $_POST['homeAddressDistrict'] ?? '';
        $homeAddressCountry = $_POST['homeAddressCountry'] ?? '';

        $customRequireFail = false;
        $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('Family', [], $customRequireFail);

        if ($customRequireFail) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        //Write to database
        try {
            $data = array('name' => $name, 'status' => $status, 'languageHomePrimary' => $languageHomePrimary, 'languageHomeSecondary' => $languageHomeSecondary, 'nameAddress' => $nameAddress, 'homeAddress' => $homeAddress, 'homeAddressDistrict' => $homeAddressDistrict, 'homeAddressCountry' => $homeAddressCountry, 'fields' => $fields, 'tawasulFamilyID' => $tawasulFamilyID);
            $sql = 'UPDATE tawasulFamily SET name=:name, status=:status, languageHomePrimary=:languageHomePrimary, languageHomeSecondary=:languageHomeSecondary, nameAddress=:nameAddress, homeAddress=:homeAddress, homeAddressDistrict=:homeAddressDistrict, homeAddressCountry=:homeAddressCountry, fields=:fields WHERE tawasulFamilyID=:tawasulFamilyID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        // Manage custom field file uploads
        if(!empty($fields) && !empty($tawasulFamilyID)) {
            $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('Family', [], $fields, 'tawasulFamily', $tawasulFamilyID, $oldFamilyRecord['fields'] ?? null);
        }

        //Success 0
        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit();
    }
}
