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

use TawasulOS\Domain\Students\StudentGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_view_print.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
        $tawasulFinanceInvoiceID = $_GET['tawasulFinanceInvoiceID'] ?? '';
        $type = $_GET['type'] ?? '';
        $tawasulPersonID = null;
        if (isset($_GET['tawasulPersonID'])) {
            $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
        }

        if ($tawasulFinanceInvoiceID == '' or $tawasulSchoolYearID == '' or $type == '' or $tawasulPersonID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            //Confirm access to this student

            if ($highestAction=="View Invoices_myChildren") {
                $student = $container->get(StudentGateway::class)->getStudentByFamilyAdult($tawasulPersonID, $session->get('tawasulPersonID'));
            } else if ($highestAction=="View Invoices_mine") {
                $student = $container->get(StudentGateway::class)->selectActiveStudentByPerson($tawasulSchoolYearID, $session->get('tawasulPersonID'))->fetch();
            }


            if (empty($student)) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {

                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID, 'tawasulPersonID' => $tawasulPersonID);
                $sql = "SELECT surname, preferredName, tawasulFinanceInvoice.* FROM tawasulFinanceInvoice JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoice.tawasulFinanceInvoiceeID=tawasulFinanceInvoicee.tawasulFinanceInvoiceeID) JOIN tawasulPerson ON (tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID AND tawasulFinanceInvoicee.tawasulPersonID=:tawasulPersonID AND (tawasulFinanceInvoice.status='Issued' OR tawasulFinanceInvoice.status='Paid' OR tawasulFinanceInvoice.status='Paid - Partial')";
                $result = $connection2->prepare($sql);
                $result->execute($data);

                if ($result->rowCount() != 1) {
                    $page->addError(__('The specified record cannot be found.'));
                } else {
                    //Let's go!
                    $row = $result->fetch();

                    $statusExtra = '';
                    if ($row['status'] == 'Issued' and $row['invoiceDueDate'] < date('Y-m-d')) {
                        $statusExtra = 'Overdue';
                    }
                    if ($row['status'] == 'Paid' and $row['invoiceDueDate'] < $row['paidDate']) {
                        $statusExtra = 'Late';
                    }

                    if ($type == 'invoice') {
                        echo '<h2>';
                        echo 'Invoice';
                        echo '</h2>';
                        $invoiceContents = invoiceContents($guid, $connection2, $tawasulFinanceInvoiceID, $tawasulSchoolYearID, $session->get('currency'), false, false);
                        if ($invoiceContents == false) {
                            $page->addError(__('An error occurred.'));
                        } else {
                            echo $invoiceContents;
                        }
                    } elseif ($type = 'receipt') {
                        echo '<h2>';
                        echo __('Receipt');
                        echo '</h2>';
                        //Get receipt number
                        $receiptNumber = null;

                            $dataReceiptNumber = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                            $sqlReceiptNumber = "SELECT *
                                FROM tawasulPayment
                                JOIN tawasulFinanceInvoice ON (tawasulPayment.foreignTableID=tawasulFinanceInvoice.tawasulFinanceInvoiceID AND tawasulPayment.foreignTable='tawasulFinanceInvoice')
                                WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID
                                ORDER BY timestamp DESC, tawasulPayment.tawasulPaymentID DESC
                            ";
                            $resultReceiptNumber = $connection2->prepare($sqlReceiptNumber);
                            $resultReceiptNumber->execute($dataReceiptNumber);
                        $receiptNumber = ($resultReceiptNumber->rowCount()-1) ;
                        $receiptContents = receiptContents($guid, $connection2, $tawasulFinanceInvoiceID, $tawasulSchoolYearID, $session->get('currency'), false, $receiptNumber);
                        if ($receiptContents == false) {
                            $page->addError(__('An error occurred.'));
                            echo '</div>';
                        } else {
                            echo $receiptContents;
                        }
                    }
                }
            }
        }
    }
}
