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

include './moduleFunctions.php';

$tawasulFinanceBudgetID = $_GET['tawasulFinanceBudgetID'] ?? '';
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/budgets_manage_edit.php&tawasulFinanceBudgetID=$tawasulFinanceBudgetID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/budgets_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulFinanceBudgetID specified
    if ($tawasulFinanceBudgetID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulFinanceBudgetID' => $tawasulFinanceBudgetID);
            $sql = 'SELECT * FROM tawasulFinanceBudget WHERE tawasulFinanceBudgetID=:tawasulFinanceBudgetID';
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
            //Proceed!
            $name = $_POST['name'] ?? '';
            $nameShort = $_POST['nameShort'] ?? '';
            $active = $_POST['active'] ?? '';
            $category = $_POST['category'] ?? '';

            if ($name == '' or $nameShort == '' or $active == '' or $category == '') {
                $URL .= '&return=error1';
                header("Location: {$URL}");
            } else {
                //Check unique inputs for uniquness
                try {
                    $data = array('name' => $name, 'nameShort' => $nameShort, 'tawasulFinanceBudgetID' => $tawasulFinanceBudgetID);
                    $sql = 'SELECT * FROM tawasulFinanceBudget WHERE (name=:name OR nameShort=:nameShort) AND NOT tawasulFinanceBudgetID=:tawasulFinanceBudgetID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() > 0) {
                    $URL .= '&return=error7';
                    header("Location: {$URL}");
                } else {
                    //Scan through staff
                    $partialFail = false;
                    $staff = array();
                    if (isset($_POST['staff'])) {
                        $staff = $_POST['staff'] ?? [];
                    }
                    $access = $_POST['access'] ?? '';
                    if ($access != 'Full' and $access != 'Write' and $access != 'Read') {
                        $role = 'Read';
                    }
                    if (count($staff) > 0) {
                        foreach ($staff as $t) {
                            //Check to see if person is already registered in this budget
                            try {
                                $dataGuest = array('tawasulPersonID' => $t, 'tawasulFinanceBudgetID' => $tawasulFinanceBudgetID);
                                $sqlGuest = 'SELECT * FROM tawasulFinanceBudgetPerson WHERE tawasulPersonID=:tawasulPersonID AND tawasulFinanceBudgetID=:tawasulFinanceBudgetID';
                                $resultGuest = $connection2->prepare($sqlGuest);
                                $resultGuest->execute($dataGuest);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                            if ($resultGuest->rowCount() == 0) {
                                try {
                                    $data = array('tawasulPersonID' => $t, 'tawasulFinanceBudgetID' => $tawasulFinanceBudgetID, 'access' => $access);
                                    $sql = 'INSERT INTO tawasulFinanceBudgetPerson SET tawasulPersonID=:tawasulPersonID, tawasulFinanceBudgetID=:tawasulFinanceBudgetID, access=:access';
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }
                            }
                        }
                    }

                    //Write to database
                    try {
                        $data = array('name' => $name, 'nameShort' => $nameShort, 'active' => $active, 'category' => $category, 'tawasulPersonIDUpdate' => $session->get('tawasulPersonID'), 'tawasulFinanceBudgetID' => $tawasulFinanceBudgetID);
                        $sql = "UPDATE tawasulFinanceBudget SET name=:name, nameShort=:nameShort, active=:active, category=:category, tawasulPersonIDUpdate=:tawasulPersonIDUpdate, timestampUpdate='".date('Y-m-d H:i:s')."' WHERE tawasulFinanceBudgetID=:tawasulFinanceBudgetID";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    if ($partialFail == true) {
                        $URL .= '&return=error4';
                        header("Location: {$URL}");
                    } else {
                        $URL .= '&return=success0';
                        header("Location: {$URL}");
                    }
                }
            }
        }
    }
}
