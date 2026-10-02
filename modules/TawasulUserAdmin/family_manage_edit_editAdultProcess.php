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

$tawasulFamilyID = $_GET['tawasulFamilyID'] ?? '';
$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
$search = $_GET['search'] ?? '';

if ($tawasulFamilyID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/family_manage_edit_editAdult.php&tawasulFamilyID=$tawasulFamilyID&tawasulPersonID=$tawasulPersonID&search=$search";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/family_manage_edit_editAdult.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if person specified
        if ($tawasulPersonID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulFamilyID' => $tawasulFamilyID, 'tawasulPersonID' => $tawasulPersonID);
                $sql = "SELECT * FROM tawasulPerson, tawasulFamily, tawasulFamilyAdult WHERE tawasulFamily.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID AND tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulFamily.tawasulFamilyID=:tawasulFamilyID AND tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')";
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
                //Validate Inputs
                $comment = $_POST['comment'] ?? '';
                $childDataAccess = $_POST['childDataAccess'] ?? '';
                $contactPriority = $_POST['contactPriority'] ?? '';
                if ($contactPriority == 1) {
                    $contactCall = 'Y';
                    $contactSMS = 'Y';
                    $contactEmail = 'Y';
                    $contactMail = 'Y';
                } else {
                    $contactCall = $_POST['contactCall'] ?? 'N';
                    $contactSMS = $_POST['contactSMS'] ?? 'N';
                    $contactEmail = $_POST['contactEmail'] ?? 'N';
                    $contactMail = $_POST['contactMail'] ?? 'N';
                }

                //Enforce one and only one contactPriority=1 parent
                if ($contactPriority == 1) {
                    //Set all other parents in family who are set to 1, to 2

                        $dataCP = array('tawasulPersonID' => $tawasulPersonID, 'tawasulFamilyID' => $tawasulFamilyID);
                        $sqlCP = 'UPDATE tawasulFamilyAdult SET contactPriority=contactPriority+1 WHERE contactPriority < 3 AND tawasulFamilyID=:tawasulFamilyID AND NOT tawasulPersonID=:tawasulPersonID';
                        $resultCP = $connection2->prepare($sqlCP);
                        $resultCP->execute($dataCP);
                } else {
                    //Check to see if there is a parent set to 1 already, and if not, change this one to 1

                        $dataCP = array('tawasulPersonID' => $tawasulPersonID, 'tawasulFamilyID' => $tawasulFamilyID);
                        $sqlCP = 'SELECT * FROM tawasulFamilyAdult WHERE contactPriority=1 AND tawasulFamilyID=:tawasulFamilyID AND NOT tawasulPersonID=:tawasulPersonID';
                        $resultCP = $connection2->prepare($sqlCP);
                        $resultCP->execute($dataCP);
                    if ($resultCP->rowCount() < 1) {
                        $contactPriority = 1;
                        $contactCall = 'Y';
                        $contactSMS = 'Y';
                        $contactEmail = 'Y';
                        $contactMail = 'Y';
                    }

                    // Set any other contact priority 2 to 3
                    if ($contactPriority == 2) {

                        $dataCP = array('tawasulPersonID' => $tawasulPersonID, 'tawasulFamilyID' => $tawasulFamilyID);
                            $sqlCP = 'UPDATE tawasulFamilyAdult SET contactPriority=3 WHERE contactPriority=2 AND tawasulFamilyID=:tawasulFamilyID AND NOT tawasulPersonID=:tawasulPersonID';
                            $resultCP = $connection2->prepare($sqlCP);
                            $resultCP->execute($dataCP);
                    }
                }

                //Write to database
                try {
                    $data = array('comment' => $comment, 'childDataAccess' => $childDataAccess, 'contactPriority' => $contactPriority, 'contactCall' => $contactCall, 'contactSMS' => $contactSMS, 'contactEmail' => $contactEmail, 'contactMail' => $contactMail, 'tawasulFamilyID' => $tawasulFamilyID, 'tawasulPersonID' => $tawasulPersonID);
                    $sql = 'UPDATE tawasulFamilyAdult SET comment=:comment, childDataAccess=:childDataAccess, contactPriority=:contactPriority, contactCall=:contactCall, contactSMS=:contactSMS, contactEmail=:contactEmail, contactMail=:contactMail WHERE tawasulFamilyID=:tawasulFamilyID AND tawasulPersonID=:tawasulPersonID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                $URL .= '&return=success0';
                header("Location: {$URL}");
            }
        }
    }
}
