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

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulFinanceFeeID = $_POST['tawasulFinanceFeeID'] ?? '';
$search = $_GET['search'] ?? '';

if ($tawasulFinanceFeeID == '' or $tawasulSchoolYearID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/fees_manage_edit.php&tawasulFinanceFeeID=$tawasulFinanceFeeID&tawasulSchoolYearID=$tawasulSchoolYearID&search=$search";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/fees_manage_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if person specified
        if ($tawasulFinanceFeeID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceFeeID' => $tawasulFinanceFeeID);
                $sql = 'SELECT * FROM tawasulFinanceFee WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFinanceFeeID=:tawasulFinanceFeeID';
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
                $name = $_POST['name'] ?? '';
                $nameShort = $_POST['nameShort'] ?? '';
                $active = $_POST['active'] ?? '';
                $description = $_POST['description'] ?? '';
                $tawasulFinanceFeeCategoryID = $_POST['tawasulFinanceFeeCategoryID'] ?? '';
                $fee = $_POST['fee'] ?? '';

                if ($name == '' or $nameShort == '' or $active == '' or $tawasulFinanceFeeCategoryID == '' or $fee == '') {
                    $URL .= '&return=error1';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'name' => $name, 'nameShort' => $nameShort, 'active' => $active, 'description' => $description, 'tawasulFinanceFeeCategoryID' => $tawasulFinanceFeeCategoryID, 'fee' => $fee, 'tawasulPersonIDUpdate' => $session->get('tawasulPersonID'), 'tawasulFinanceFeeID' => $tawasulFinanceFeeID);
                        $sql = "UPDATE tawasulFinanceFee SET tawasulSchoolYearID=:tawasulSchoolYearID, name=:name, nameShort=:nameShort, active=:active, description=:description, tawasulFinanceFeeCategoryID=:tawasulFinanceFeeCategoryID, fee=:fee, tawasulPersonIDUpdate=:tawasulPersonIDUpdate, timestampUpdate='".date('Y-m-d H:i:s')."' WHERE tawasulFinanceFeeID=:tawasulFinanceFeeID";
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
}
