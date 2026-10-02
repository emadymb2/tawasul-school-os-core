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

$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
$tawasulFinanceInvoiceID = $_POST['tawasulFinanceInvoiceID'] ?? '';
$status = $_GET['status'] ?? null;
$tawasulFinanceInvoiceeID = $_POST['tawasulFinanceInvoiceeID'] ?? null;
$monthOfIssue = $_GET['monthOfIssue'] ?? null;
$tawasulFinanceBillingScheduleID = $_POST['tawasulFinanceBillingScheduleID'] ?? null;
$tawasulFinanceFeeCategoryID = $_POST['tawasulFinanceFeeCategoryID'] ?? null;

if ($tawasulFinanceInvoiceID == '' or $tawasulSchoolYearID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/invoices_manage_delete.php&tawasulFinanceInvoiceID=$tawasulFinanceInvoiceID&tawasulSchoolYearID=$tawasulSchoolYearID&status=$status&tawasulFinanceInvoiceeID=$tawasulFinanceInvoiceeID&monthOfIssue=$monthOfIssue&tawasulFinanceBillingScheduleID=$tawasulFinanceBillingScheduleID&tawasulFinanceFeeCategoryID=$tawasulFinanceFeeCategoryID";
    $URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/invoices_manage.php&tawasulSchoolYearID=$tawasulSchoolYearID&status=$status&tawasulFinanceInvoiceeID=$tawasulFinanceInvoiceeID&monthOfIssue=$monthOfIssue&tawasulFinanceBillingScheduleID=$tawasulFinanceBillingScheduleID&tawasulFinanceFeeCategoryID=$tawasulFinanceFeeCategoryID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_manage_delete.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        if ($tawasulFinanceInvoiceID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                $sql = "SELECT * FROM tawasulFinanceInvoice WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID AND status='Pending'";
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
                //Write to database
                try {
                    $data = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                    $sql = 'DELETE FROM tawasulFinanceInvoice WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                try {
                    $data = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                    $sql = 'DELETE FROM tawasulFinanceInvoiceFee WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                $URLDelete = $URLDelete.'&return=success0';
                header("Location: {$URLDelete}");
            }
        }
    }
}
