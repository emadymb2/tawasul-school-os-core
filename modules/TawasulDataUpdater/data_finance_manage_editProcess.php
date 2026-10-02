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

$tawasulFinanceInvoiceeUpdateID = $_GET['tawasulFinanceInvoiceeUpdateID'] ?? '';
$tawasulFinanceInvoiceeID = $_POST['tawasulFinanceInvoiceeID'] ?? '';
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/data_finance_manage_edit.php&tawasulFinanceInvoiceeUpdateID=$tawasulFinanceInvoiceeUpdateID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulDataUpdater/data_finance_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulFinanceInvoiceeUpdateID and tawasulFinanceInvoiceeID specified
    if ($tawasulFinanceInvoiceeUpdateID == '' or $tawasulFinanceInvoiceeID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulFinanceInvoiceeUpdateID' => $tawasulFinanceInvoiceeUpdateID);
            $sql = 'SELECT * FROM tawasulFinanceInvoiceeUpdate WHERE tawasulFinanceInvoiceeUpdateID=:tawasulFinanceInvoiceeUpdateID';
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
            if (isset($_POST['newinvoiceToOn'])) {
                if ($_POST['newinvoiceToOn'] == 'on') {
                    $data['invoiceTo'] = $_POST['newinvoiceTo'] ?? '';
                    $set .= 'tawasulFinanceInvoicee.invoiceTo=:invoiceTo, ';
                }
            }
            if (isset($_POST['newcompanyNameOn'])) {
                if ($_POST['newcompanyNameOn'] == 'on') {
                    $data['companyName'] = $_POST['newcompanyName'] ?? '';
                    $set .= 'tawasulFinanceInvoicee.companyName=:companyName, ';
                }
            }
            if (isset($_POST['newcompanyContactOn'])) {
                if ($_POST['newcompanyContactOn'] == 'on') {
                    $data['companyContact'] = $_POST['newcompanyContact'] ?? '';
                    $set .= 'tawasulFinanceInvoicee.companyContact=:companyContact, ';
                }
            }
            if (isset($_POST['newcompanyAddressOn'])) {
                if ($_POST['newcompanyAddressOn'] == 'on') {
                    $data['companyAddress'] = $_POST['newcompanyAddress'] ?? '';
                    $set .= 'tawasulFinanceInvoicee.companyAddress=:companyAddress, ';
                }
            }
            if (isset($_POST['newcompanyEmailOn'])) {
                if ($_POST['newcompanyEmailOn'] == 'on') {
                    $data['companyEmail'] = $_POST['newcompanyEmail'] ?? '';
                    $set .= 'tawasulFinanceInvoicee.companyEmail=:companyEmail, ';
                }
            }
            if (isset($_POST['newcompanyCCFamilyOn'])) {
                if ($_POST['newcompanyCCFamilyOn'] == 'on') {
                    $data['companyCCFamily'] = $_POST['newcompanyCCFamily'] ?? '';
                    $set .= 'tawasulFinanceInvoicee.companyCCFamily=:companyCCFamily, ';
                }
            }
            if (isset($_POST['newcompanyPhoneOn'])) {
                if ($_POST['newcompanyPhoneOn'] == 'on') {
                    $data['companyPhone'] = $_POST['newcompanyPhone'] ?? '';
                    $set .= 'tawasulFinanceInvoicee.companyPhone=:companyPhone, ';
                }
            }
            if (isset($_POST['newcompanyAllOn'])) {
                if ($_POST['newcompanyAllOn'] == 'on') {
                    $data['companyAll'] = $_POST['newcompanyAll'] ?? '';
                    $set .= 'tawasulFinanceInvoicee.companyAll=:companyAll, ';
                }
            }
            if (isset($_POST['newtawasulFinanceFeeCategoryIDListOn'])) {
                if ($_POST['newtawasulFinanceFeeCategoryIDListOn'] == 'on') {
                    $data['tawasulFinanceFeeCategoryIDList'] = $_POST['newtawasulFinanceFeeCategoryIDList'] ?? '';
                    $set .= 'tawasulFinanceInvoicee.tawasulFinanceFeeCategoryIDList=:tawasulFinanceFeeCategoryIDList, ';
                }
            }

            if (strlen($set) > 1) {
                //Write to database
                try {
                    $data['tawasulFinanceInvoiceeID'] = $tawasulFinanceInvoiceeID;
                    $sql = 'UPDATE tawasulFinanceInvoicee SET '.substr($set, 0, (strlen($set) - 2)).' WHERE tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                //Write to database
                try {
                    $data = array('tawasulFinanceInvoiceeUpdateID' => $tawasulFinanceInvoiceeUpdateID);
                    $sql = "UPDATE tawasulFinanceInvoiceeUpdate SET status='Complete' WHERE tawasulFinanceInvoiceeUpdateID=:tawasulFinanceInvoiceeUpdateID";
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
                    $data = array('tawasulFinanceInvoiceeUpdateID' => $tawasulFinanceInvoiceeUpdateID);
                    $sql = "UPDATE tawasulFinanceInvoiceeUpdate SET status='Complete' WHERE tawasulFinanceInvoiceeUpdateID=:tawasulFinanceInvoiceeUpdateID";
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
