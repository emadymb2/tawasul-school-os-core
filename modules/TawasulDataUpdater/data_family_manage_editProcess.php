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

$tawasulFamilyUpdateID = $_GET['tawasulFamilyUpdateID'] ?? '';
$tawasulFamilyID = $_POST['tawasulFamilyID'] ?? '';
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/data_family_manage_edit.php&tawasulFamilyUpdateID=$tawasulFamilyUpdateID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulDataUpdater/data_family_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if stawasulFamilyUpdateID and tawasulFamilyID specified
    if ($tawasulFamilyUpdateID == '' or $tawasulFamilyID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulFamilyUpdateID' => $tawasulFamilyUpdateID);
            $sql = 'SELECT * FROM tawasulFamilyUpdate WHERE tawasulFamilyUpdateID=:tawasulFamilyUpdateID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($result->rowCount() != 1) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            //Set values
            $data = array();
            $set = '';
            if (isset($_POST['newnameAddressOn'])) {
                if ($_POST['newnameAddressOn'] == 'on') {
                    $data['nameAddress'] = $_POST['newnameAddress'] ?? '';
                    $set .= 'tawasulFamily.nameAddress=:nameAddress, ';
                }
            }
            if (isset($_POST['newhomeAddressOn'])) {
                if ($_POST['newhomeAddressOn'] == 'on') {
                    $data['homeAddress'] = $_POST['newhomeAddress'] ?? '';
                    $set .= 'tawasulFamily.homeAddress=:homeAddress, ';
                }
            }
            if (isset($_POST['newhomeAddressDistrictOn'])) {
                if ($_POST['newhomeAddressDistrictOn'] == 'on') {
                    $data['homeAddressDistrict'] = $_POST['newhomeAddressDistrict'] ?? '';
                    $set .= 'tawasulFamily.homeAddressDistrict=:homeAddressDistrict, ';
                }
            }
            if (isset($_POST['newhomeAddressCountryOn'])) {
                if ($_POST['newhomeAddressCountryOn'] == 'on') {
                    $data['homeAddressCountry'] = $_POST['newhomeAddressCountry'] ?? '';
                    $set .= 'tawasulFamily.homeAddressCountry=:homeAddressCountry, ';
                }
            }
            if (isset($_POST['newlanguageHomePrimaryOn'])) {
                if ($_POST['newlanguageHomePrimaryOn'] == 'on') {
                    $data['languageHomePrimary'] = $_POST['newlanguageHomePrimary'] ?? '';
                    $set .= 'tawasulFamily.languageHomePrimary=:languageHomePrimary, ';
                }
            }
            if (isset($_POST['newlanguageHomeSecondaryOn'])) {
                if ($_POST['newlanguageHomeSecondaryOn'] == 'on') {
                    $data['languageHomeSecondary'] = $_POST['newlanguageHomeSecondary'] ?? '';
                    $set .= 'tawasulFamily.languageHomeSecondary=:languageHomeSecondary, ';
                }
            }

            if (strlen($set) > 1) {
                //Write to database
                try {
                    $data['tawasulFamilyID'] = $tawasulFamilyID;
                    $sql = 'UPDATE tawasulFamily SET '.substr($set, 0, (strlen($set) - 2)).' WHERE tawasulFamilyID=:tawasulFamilyID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                //Write to database
                try {
                    $data = array('tawasulFamilyUpdateID' => $tawasulFamilyUpdateID);
                    $sql = "UPDATE tawasulFamilyUpdate SET status='Complete' WHERE tawasulFamilyUpdateID=:tawasulFamilyUpdateID";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=warning1';
                    header("Location: {$URL}");
                    exit();
                }

                $URL .= '&return=success0';
                header("Location: {$URL}");
            } else {
                //Write to database
                try {
                    $data = array('tawasulFamilyUpdateID' => $tawasulFamilyUpdateID);
                    $sql = "UPDATE tawasulFamilyUpdate SET status='Complete' WHERE tawasulFamilyUpdateID=:tawasulFamilyUpdateID";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&updateReturn=success1';
                    header("Location: {$URL}");
                    exit();
                }

                $URL .= '&return=success0';
                header("Location: {$URL}");
            }
        }
    }
}
