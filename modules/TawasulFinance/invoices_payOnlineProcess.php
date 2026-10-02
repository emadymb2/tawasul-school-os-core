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

use TawasulOS\Contracts\Comms\Mailer;
use TawasulOS\Contracts\Services\Payment;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

include './moduleFunctions.php';

$tawasulFinanceInvoiceID = $_REQUEST['tawasulFinanceInvoiceID'] ?? '';
$key = $_REQUEST['key'] ?? '';

$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulFinance/invoices_payOnline.php&tawasulFinanceInvoiceID=$tawasulFinanceInvoiceID&key=$key";
$URLPayment = $session->get('absoluteURL')."/modules/TawasulFinance/invoices_payOnlineProcess.php?tawasulFinanceInvoiceID=$tawasulFinanceInvoiceID&key=$key";

$payment = $container->get(Payment::class);
$payment->setReturnURL($URLPayment);
$payment->setCancelURL($URLPayment);
$payment->setForeignTable('tawasulFinanceInvoice', $tawasulFinanceInvoiceID);

$settingGateway = $container->get(SettingGateway::class);

if (!$payment->incomingPayment()) {
    // No incoming payment, let's send a request

    // Check variables
    if (empty($tawasulFinanceInvoiceID)|| empty($key)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    //Check for record
    $keyReadFail = false;
    try {
        $dataKeyRead = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID, 'key' => $key);
        $sqlKeyRead = "SELECT * FROM tawasulFinanceInvoice WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID AND `key`=:key AND (status='Issued' OR status='Paid - Partial')";
        $resultKeyRead = $connection2->prepare($sqlKeyRead);
        $resultKeyRead->execute($dataKeyRead);
    } catch (PDOException $e) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit();
    }

    if ($resultKeyRead->rowCount() != 1) { //If not exists, report error
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit();
    } else {    //If exists check confirmed
        $rowKeyRead = $resultKeyRead->fetch();

        //Get value of the invoice.
        $feeOK = true;
        try {
            $dataFees['tawasulFinanceInvoiceID'] = $tawasulFinanceInvoiceID;
            $sqlFees = 'SELECT tawasulFinanceInvoiceFee.tawasulFinanceInvoiceFeeID, tawasulFinanceInvoiceFee.feeType, tawasulFinanceFeeCategory.name AS category, tawasulFinanceInvoiceFee.name AS name, tawasulFinanceInvoiceFee.fee, tawasulFinanceInvoiceFee.description AS description, NULL AS tawasulFinanceFeeID, tawasulFinanceInvoiceFee.tawasulFinanceFeeCategoryID AS tawasulFinanceFeeCategoryID, sequenceNumber FROM tawasulFinanceInvoiceFee JOIN tawasulFinanceFeeCategory ON (tawasulFinanceInvoiceFee.tawasulFinanceFeeCategoryID=tawasulFinanceFeeCategory.tawasulFinanceFeeCategoryID) WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID ORDER BY sequenceNumber';
            $resultFees = $connection2->prepare($sqlFees);
            $resultFees->execute($dataFees);
        } catch (PDOException $e) {
            $feeOK = false;
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($feeOK == true) {
            $feeTotal = 0;
            while ($rowFees = $resultFees->fetch()) {
                $feeTotal += $rowFees['fee'];
            }

            $feeRemaining = $feeTotal;
            if ($feeTotal > 0 && !empty($rowKeyRead['paidAmount']) && $rowKeyRead['paidAmount'] < $feeTotal) {
                $feeRemaining = floatval($feeTotal) - floatval($rowKeyRead['paidAmount']);
            }


            if ($payment->isEnabled() && $feeTotal > 0 && $feeRemaining > 0) {
                $financeOnlinePaymentEnabled = $settingGateway->getSettingByScope('Finance', 'financeOnlinePaymentEnabled');
                $financeOnlinePaymentThreshold = $settingGateway->getSettingByScope('Finance', 'financeOnlinePaymentThreshold');
                if ($financeOnlinePaymentEnabled == 'Y') {
                    if ($financeOnlinePaymentThreshold == '' or $financeOnlinePaymentThreshold >= $feeRemaining) {
                        // Let's make a payment
                        $return = $payment->requestPayment($feeRemaining, __('Invoice Number') .' '.$tawasulFinanceInvoiceID);

                        if (!empty($return)) {
                            $URL .= '&return='.$return;
                            header("Location: " . $URL);
                            exit;
                        }

                    } else {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }
                } else {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }
            } else {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }
        }
        
    }
} else { 
    // Handle incoming payment

    $tawasulFinanceInvoiceID = $_GET['tawasulFinanceInvoiceID'] ?? '';
    $key = $_GET['key'] ?? '';

    $tawasulFinanceInvoiceeID = '';
    $invoiceTo = '';
    $tawasulSchoolYearID = '';

    $dataKeyRead = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID, 'key' => $key);
    $sqlKeyRead = 'SELECT tawasulFinanceInvoice.tawasulFinanceInvoiceeID, tawasulFinanceInvoice.invoiceTo, tawasulFinanceInvoice.tawasulSchoolYearID FROM tawasulFinanceInvoice WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID AND `key`=:key';
    $resultKeyRead = $connection2->prepare($sqlKeyRead);
    $resultKeyRead->execute($dataKeyRead);
    if ($resultKeyRead->rowCount() == 1) {
        $rowKeyRead = $resultKeyRead->fetch();
        $tawasulFinanceInvoiceeID = $rowKeyRead['tawasulFinanceInvoiceeID'];
        $invoiceTo = $rowKeyRead['invoiceTo'];
        $tawasulSchoolYearID = $rowKeyRead['tawasulSchoolYearID'];
    }

    //Check return values to see if we can proceed
    if ($tawasulFinanceInvoiceID == '' or $key == '' or $tawasulFinanceInvoiceeID == '' or $invoiceTo = '' or $tawasulSchoolYearID == '') {
        $URL .= '&return=warning3';
        header("Location: {$URL}");
        exit();
    } else {
        //PROCEED AND FINALISE PAYMENT
        $return = $payment->confirmPayment();
        $result = $payment->getPaymentResult();
        $tawasulPaymentID = $result['tawasulPaymentID'];

        //Payment was successful. Yeah!
        if ($result['success']) {
            $updateFail = false;

            //Link tawasulPayment record to tawasulFinanceInvoice, and make note that payment made
            if (!empty($tawasulPaymentID)) {
                $data = array('paidDate' => date('Y-m-d'), 'paidAmount' => $result['amount'], 'tawasulPaymentID' => $tawasulPaymentID, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                $sql = "UPDATE tawasulFinanceInvoice SET status='Paid', paidDate=:paidDate, paidAmount=:paidAmount, tawasulPaymentID=:tawasulPaymentID WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID";
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } else {
                $updateFail = true;
            }

            if ($updateFail == true) {
                $URL .= "&addReturn=success3&tawasulFinanceInvoiceID=$tawasulFinanceInvoiceID&key=$key";
                header("Location: {$URL}");
                exit;
            }

            //EMAIL RECEIPT (no error reporting)
            //Populate to email.
            $emails = [];
            $emailsCount = 0;
            if ($invoiceTo == 'Company') {

                $dataCompany = array('tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID);
                $sqlCompany = 'SELECT * FROM tawasulFinanceInvoicee WHERE tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID';
                $resultCompany = $connection2->prepare($sqlCompany);
                $resultCompany->execute($dataCompany);
                if ($resultCompany->rowCount() != 1) {
                } else {
                    $rowCompany = $resultCompany->fetch();
                    if ($rowCompany['companyEmail'] != '' and $rowCompany['companyContact'] != '' and $rowCompany['companyName'] != '') {
                        $emails = array_map('trim', explode(',', $rowCompany['companyEmail']));
                        $emailsCount += count($emails);

                        $rowCompany['companyCCFamily'];
                        if ($rowCompany['companyCCFamily'] == 'Y') {
                            try {
                                $dataParents = array('tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID);
                                $sqlParents = "SELECT parent.title, parent.surname, parent.preferredName, parent.email, parent.address1, parent.address1District, parent.address1Country, homeAddress, homeAddressDistrict, homeAddressCountry FROM tawasulFinanceInvoicee JOIN tawasulPerson AS student ON (tawasulFinanceInvoicee.tawasulPersonID=student.tawasulPersonID) JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=student.tawasulPersonID) JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulFamilyAdult ON (tawasulFamily.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID) JOIN tawasulPerson AS parent ON (tawasulFamilyAdult.tawasulPersonID=parent.tawasulPersonID) WHERE tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID AND (contactPriority=1 OR (contactPriority=2 AND contactEmail='Y')) ORDER BY contactPriority, surname, preferredName";
                                $resultParents = $connection2->prepare($sqlParents);
                                $resultParents->execute($dataParents);
                            } catch (PDOException $e) {
                                $emailFail = true;
                            }
                            if ($resultParents->rowCount() < 1) {
                                $emailFail = true;
                            } else {
                                while ($rowParents = $resultParents->fetch()) {
                                    if ($rowParents['preferredName'] != '' and $rowParents['surname'] != '' and $rowParents['email'] != '') {
                                        $emails[$emailsCount] = $rowParents['email'];
                                        ++$emailsCount;
                                    }
                                }
                            }
                        }
                    } else {
                        $emailFail = true;
                    }
                }
            } else {
                try {
                    $dataParents = array('tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID);
                    $sqlParents = "SELECT parent.title, parent.surname, parent.preferredName, parent.email, parent.address1, parent.address1District, parent.address1Country, homeAddress, homeAddressDistrict, homeAddressCountry FROM tawasulFinanceInvoicee JOIN tawasulPerson AS student ON (tawasulFinanceInvoicee.tawasulPersonID=student.tawasulPersonID) JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=student.tawasulPersonID) JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulFamilyAdult ON (tawasulFamily.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID) JOIN tawasulPerson AS parent ON (tawasulFamilyAdult.tawasulPersonID=parent.tawasulPersonID) WHERE tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID AND (contactPriority=1 OR (contactPriority=2 AND contactEmail='Y')) ORDER BY contactPriority, surname, preferredName";
                    $resultParents = $connection2->prepare($sqlParents);
                    $resultParents->execute($dataParents);
                } catch (PDOException $e) {
                    $emailFail = true;
                }
                if ($resultParents->rowCount() < 1) {
                    $emailFail = true;
                } else {
                    while ($rowParents = $resultParents->fetch()) {
                        if ($rowParents['preferredName'] != '' and $rowParents['surname'] != '' and $rowParents['email'] != '') {
                            $emails[$emailsCount] = $rowParents['email'];
                            ++$emailsCount;
                        }
                    }
                }
            }

            //Send emails
            if (count($emails) > 0) {
                //Get receipt number

                $dataPayments = array('foreignTable' => 'tawasulFinanceInvoice', 'foreignTableID' => $tawasulFinanceInvoiceID);
                $sqlPayments = 'SELECT tawasulPayment.*, surname, preferredName FROM tawasulPayment JOIN tawasulPerson ON (tawasulPayment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE foreignTable=:foreignTable AND foreignTableID=:foreignTableID ORDER BY timestamp, tawasulPaymentID';
                $resultPayments = $connection2->prepare($sqlPayments);
                $resultPayments->execute($dataPayments);
                $receiptCount = $resultPayments->rowCount();

                //Prep message
                $body = receiptContents($guid, $connection2, $tawasulFinanceInvoiceID, $tawasulSchoolYearID, $session->get('currency'), true, $receiptCount)."<p style='font-style: italic;'>Email sent via ".$session->get('systemName').' at '.$session->get('organisationName').'.</p>';

                $mail = $container->get(Mailer::class);
                $mail->SetFrom($settingGateway->getSettingByScope('Finance', 'email'), sprintf(__('%1$s Finance'), $session->get('organisationName')));
                foreach ($emails as $address) {
                    $mail->AddBCC($address);
                }

                $mail->Subject = __('Receipt from {organisation} via {system}', [
                    'organisation' => $session->get('organisationNameShort'),
                    'system' => $session->get('systemName'),
                ]);

                $mail->renderBody('mail/email.twig.html', [
                    'title'  => $mail->Subject,
                    'body'   => $body,
                    'maxWidth' => '900px',
                ]);

                $mail->Send();
            }

            $URL .= "&return=success1&tawasulFinanceInvoiceID=$tawasulFinanceInvoiceID&key=$key";
            header("Location: {$URL}");
        } else {
            if ($return == 'warning3') {
                $URL .= "&return=warning3&tawasulFinanceInvoiceID=$tawasulFinanceInvoiceID&key=$key";
                header("Location: {$URL}");
                exit;
            }
            $updateFail = false;

            //Link tawasulPayment record to tawasulApplicationForm, and make note that payment made
            if (!empty($tawasulPaymentID)) {
                $data = array('tawasulPaymentID' => $tawasulPaymentID, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                $sql = 'UPDATE tawasulFinanceInvoice tawasulPaymentID=:tawasulPaymentID WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } else {
                $updateFail = true;
            }

            if ($updateFail == true) {
                //Success 2
                $URL .= "&return=success2&tawasulFinanceInvoiceID=$tawasulFinanceInvoiceID&key=$key";
                header("Location: {$URL}");
                exit;
            }

            //Success 2
            $URL .= "&return=success2&tawasulFinanceInvoiceID=$tawasulFinanceInvoiceID&key=$key";
            header("Location: {$URL}");
        }
    }
}
