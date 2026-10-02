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

$tawasulStaffApplicationFormID = $_POST['tawasulStaffApplicationFormID'] ?? '';
$search = $_GET['search'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/applicationForm_manage_reject.php&tawasulStaffApplicationFormID=$tawasulStaffApplicationFormID&search=$search";
$URLReject = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/applicationForm_manage.php&search=$search";

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/applicationForm_manage_reject.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulStaffApplicationFormID specified

    if ($tawasulStaffApplicationFormID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulStaffApplicationFormID' => $tawasulStaffApplicationFormID);
            $sql = 'SELECT * FROM tawasulStaffApplicationForm WHERE tawasulStaffApplicationFormID=:tawasulStaffApplicationFormID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($result->rowCount() != 1) {
            $URL .= '&eeturn=error2';
            header("Location: {$URL}");
        } else {
            //Write to database
            try {
                $data = array('tawasulStaffApplicationFormID' => $tawasulStaffApplicationFormID);
                $sql = "UPDATE tawasulStaffApplicationForm SET status='Rejected' WHERE tawasulStaffApplicationFormID=:tawasulStaffApplicationFormID";
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            $URLReject = $URLReject.'&return=success0';
            header("Location: {$URLReject}");
        }
    }
}
