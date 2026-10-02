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

$tawasulFinanceInvoiceeID = $_GET['tawasulFinanceInvoiceeID'] ?? '';
$address = $_POST['address'] ?? '';
$search = $_GET['search'] ?? '';
$allUsers = $_GET['allUsers'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/invoicees_manage_edit.php&tawasulFinanceInvoiceeID=$tawasulFinanceInvoiceeID&search=$search&allUsers=$allUsers";

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoicees_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulFinanceInvoiceeID specified
    if ($tawasulFinanceInvoiceeID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID);
            $sql = 'SELECT * FROM tawasulFinanceInvoicee WHERE tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID';
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
            $invoiceTo = $_POST['invoiceTo'] ?? '';
            if ($invoiceTo == 'Company') {
                $companyName = $_POST['companyName'] ?? '';
                $companyContact = $_POST['companyContact'] ?? '';
                $companyAddress = $_POST['companyAddress'] ?? '';
                $companyEmail = $_POST['companyEmail'] ?? '';
                $companyCCFamily = $_POST['companyCCFamily'] ?? '';
                $companyPhone = $_POST['companyPhone'] ?? '';
                $companyAll = $_POST['companyAll'] ?? '';
                $tawasulFinanceFeeCategoryIDList = null;
                if ($companyAll == 'N') {
                    $tawasulFinanceFeeCategoryIDList == '';
                    $tawasulFinanceFeeCategoryIDArray = $_POST['tawasulFinanceFeeCategoryIDList'] ?? [];
                    if (count($tawasulFinanceFeeCategoryIDArray) > 0) {
                        foreach ($tawasulFinanceFeeCategoryIDArray as $tawasulFinanceFeeCategoryID) {
                            $tawasulFinanceFeeCategoryIDList .= $tawasulFinanceFeeCategoryID.',';
                        }
                        $tawasulFinanceFeeCategoryIDList = substr($tawasulFinanceFeeCategoryIDList, 0, -1);
                    }
                }
            } else {
                $companyName = null;
                $companyContact = null;
                $companyAddress = null;
                $companyEmail = null;
                $companyCCFamily = null;
                $companyPhone = null;
                $companyAll = null;
                $tawasulFinanceFeeCategoryIDList = null;
            }
            if ($invoiceTo == '') {
                $URL .= '&return=error1';
                header("Location: {$URL}");
            } else {
                //Write to database
                try {
                    $data = array('invoiceTo' => $invoiceTo, 'companyName' => $companyName, 'companyContact' => $companyContact, 'companyAddress' => $companyAddress, 'companyEmail' => $companyEmail, 'companyCCFamily' => $companyCCFamily, 'companyPhone' => $companyPhone, 'companyAll' => $companyAll, 'tawasulFinanceFeeCategoryIDList' => $tawasulFinanceFeeCategoryIDList, 'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID);
                    $sql = 'UPDATE tawasulFinanceInvoicee SET invoiceTo=:invoiceTo, companyName=:companyName, companyContact=:companyContact, companyAddress=:companyAddress, companyEmail=:companyEmail, companyCCFamily=:companyCCFamily, companyPhone=:companyPhone, companyAll=:companyAll, tawasulFinanceFeeCategoryIDList=:tawasulFinanceFeeCategoryIDList WHERE tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID';
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
