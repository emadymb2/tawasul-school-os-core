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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Services\Format;
use TawasulOS\Contracts\Comms\Mailer;
use TawasulOS\Domain\System\LogGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['notes' => 'HTML']);

//Module includes
include './moduleFunctions.php';

$logGateway = $container->get(LogGateway::class);
$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulFinanceInvoiceID = $_POST['tawasulFinanceInvoiceID'] ?? '';
$status = $_GET['status'] ?? '';
$tawasulFinanceInvoiceeID = $_GET['tawasulFinanceInvoiceeID'] ?? '';
$monthOfIssue = $_GET['monthOfIssue'] ?? '';
$tawasulFinanceBillingScheduleID = $_GET['tawasulFinanceBillingScheduleID'] ?? '';
$tawasulFinanceFeeCategoryID = $_GET['tawasulFinanceFeeCategoryID'] ?? '';

if ($tawasulFinanceInvoiceID == '' or $tawasulSchoolYearID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/invoices_manage_edit.php&tawasulFinanceInvoiceID=$tawasulFinanceInvoiceID&tawasulSchoolYearID=$tawasulSchoolYearID&status=$status&tawasulFinanceInvoiceeID=$tawasulFinanceInvoiceeID&monthOfIssue=$monthOfIssue&tawasulFinanceBillingScheduleID=$tawasulFinanceBillingScheduleID&tawasulFinanceFeeCategoryID=$tawasulFinanceFeeCategoryID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_manage_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if person specified
        if ($tawasulFinanceInvoiceID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            //LOCK INVOICE TABLES
            try {
                $data = array();
                $sql = 'LOCK TABLES tawasulFinanceInvoice WRITE, tawasulFinanceInvoiceFee WRITE, tawasulFinanceInvoicee WRITE, tawasulPayment READ';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            try {
                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                $sql = 'SELECT * FROM tawasulFinanceInvoice WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
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
                $row = $result->fetch();
                $notes = $_POST['notes'] ?? '';
                $status = $row['status'];
                if ($status != 'Pending') {
                    $status = $_POST['status'] ?? '';
                    if ($status == 'Paid - Complete') {
                        $status = 'Paid';
                    }
                }
                $order = $_POST['order'] ?? [];

                if ($_POST['status'] == 'Paid' or $_POST['status'] == 'Paid - Partial' or $_POST['status'] == 'Paid - Complete') {
                    $paidDate = !empty($_POST['paidDate']) ? Format::dateConvert($_POST['paidDate']) : null;
                } else if ($_POST['status'] == 'Refunded') {
                    $paidDate = $row['paidDate'];
                } else {
                    $paidDate = null;
                }
                if ($_POST['status'] == 'Paid' or $_POST['status'] == 'Paid - Partial' or $_POST['status'] == 'Paid - Complete') {
                    $paidAmountLog = $_POST['paidAmount'] ?? '';
                    $paidAmount = $_POST['paidAmount'] ?? '';
                    //If some paid already, work out amount, and add it to total
                    $alreadyPaid = getAmountPaid($connection2, $guid, 'tawasulFinanceInvoice', $tawasulFinanceInvoiceID);
                    $paidAmount += $alreadyPaid;
                } else if ($_POST['status'] == 'Refunded') {
                    $paidAmount = $row['paidAmount'];
                } else {
                    $paidAmount = null;
                }
                $paymentType = null;
                if ($_POST['status'] == 'Paid' or $_POST['status'] == 'Paid - Partial' or $_POST['status'] == 'Paid - Complete') {
                    $paymentType = $_POST['paymentType'] ?? '';
                }
                $paymentTransactionID = null;
                if ($_POST['status'] == 'Paid' or $_POST['status'] == 'Paid - Partial' or $_POST['status'] == 'Paid - Complete') {
                    $paymentTransactionID = $_POST['paymentTransactionID'] ?? '';
                }
                if ($row['billingScheduleType'] == 'Ad Hoc' and ($row['status'] == 'Pending' or $row['status'] == 'Issued')) {
                    $invoiceDueDate = !empty($_POST['invoiceDueDate']) ? Format::dateConvert($_POST['invoiceDueDate']) : null;
                } else {
                    $invoiceDueDate = $row['invoiceDueDate'];
                }

                //Write to database
                try {
                    $data = array('status' => $status, 'notes' => $notes, 'paidDate' => $paidDate, 'paidAmount' => $paidAmount, 'invoiceDueDate' => $invoiceDueDate, 'tawasulPersonIDUpdate' => $session->get('tawasulPersonID'), 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                    $sql = "UPDATE tawasulFinanceInvoice SET status=:status, notes=:notes, paidDate=:paidDate, paidAmount=:paidAmount, invoiceDueDate=:invoiceDueDate, tawasulPersonIDUpdate=:tawasulPersonIDUpdate, timestampUpdate='".date('Y-m-d H:i:s')."' WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                $partialFail = false;

                if ($status == 'Pending') {
                    if (is_null($order)) {
                        $partialFail = true;
                    } else {
                        //Remove fees
                        try {
                            $data = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                            $sql = 'DELETE FROM tawasulFinanceInvoiceFee WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }

                        //Organise Fees
                        $fees = array();
                        $tawasulFinanceFeeCategoryIDList = '';
                        foreach ($order as $fee) {
                            $fees[$fee]['name'] = $_POST['name'.$fee] ?? '';
                            $fees[$fee]['tawasulFinanceFeeCategoryID'] = $_POST['tawasulFinanceFeeCategoryID'.$fee] ?? '';
                            $fees[$fee]['fee'] = $_POST['fee'.$fee] ?? '';
                            $fees[$fee]['feeType'] = $_POST['feeType'.$fee] ?? '';
                            $fees[$fee]['tawasulFinanceFeeID'] = $_POST['tawasulFinanceFeeID'.$fee] ?? '';
                            $fees[$fee]['description'] = $_POST['description'.$fee] ?? '';

                            $tawasulFinanceFeeCategoryIDList .= $_POST['tawasulFinanceFeeCategoryID'.$fee].",";
                        }
                        $tawasulFinanceFeeCategoryIDList = substr($tawasulFinanceFeeCategoryIDList, 0, -1);

                        //Write to fee categories
                        try {
                            $dataTemp = array('tawasulFinanceFeeCategoryIDList' => $tawasulFinanceFeeCategoryIDList, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                            $sqlTemp = "UPDATE tawasulFinanceInvoice SET tawasulFinanceFeeCategoryIDList=:tawasulFinanceFeeCategoryIDList WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID";
                            $resultTemp = $connection2->prepare($sqlTemp);
                            $resultTemp->execute($dataTemp);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }

                        //Add fees to invoice
                        $count = 0;
                        foreach ($fees as $fee) {
                            ++$count;
                            try {
                                if ($fee['feeType'] == 'Standard') {
                                    $dataInvoiceFee = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID, 'feeType' => $fee['feeType'], 'tawasulFinanceFeeID' => $fee['tawasulFinanceFeeID'], 'count' => $count);
                                    $sqlInvoiceFee = "INSERT INTO tawasulFinanceInvoiceFee SET tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID, feeType=:feeType, tawasulFinanceFeeID=:tawasulFinanceFeeID, separated='N', sequenceNumber=:count";
                                } else {
                                    $dataInvoiceFee = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID, 'feeType' => $fee['feeType'], 'name' => $fee['name'], 'description' => $fee['description'], 'tawasulFinanceFeeCategoryID' => $fee['tawasulFinanceFeeCategoryID'], 'fee' => $fee['fee'], 'count' => $count);
                                    $sqlInvoiceFee = "INSERT INTO tawasulFinanceInvoiceFee SET tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID, feeType=:feeType, name=:name, description=:description, tawasulFinanceFeeCategoryID=:tawasulFinanceFeeCategoryID, fee=:fee, sequenceNumber=:count";
                                }
                                $resultInvoiceFee = $connection2->prepare($sqlInvoiceFee);
                                $resultInvoiceFee->execute($dataInvoiceFee);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        }
                    }
                }

                //Unlock tables

                    $sql = 'UNLOCK TABLES';
                    $result = $connection2->query($sql);

                // Log the payment
                if ($status == 'Paid' or $status == 'Paid - Partial') {
                    if ($_POST['status'] == 'Paid') {
                        $statusLog = 'Complete';
                    } elseif ($_POST['status'] == 'Paid - Partial') {
                        $statusLog = 'Partial';
                    } elseif ($_POST['status'] == 'Paid - Complete') {
                        $statusLog = 'Final';
                    }
                    $logFail = setPaymentLog($connection2, $guid, 'tawasulFinanceInvoice', $tawasulFinanceInvoiceID, $paymentType, $statusLog, $paidAmountLog, null, null, null, null, $paymentTransactionID, null, $paidDate);
                    if ($logFail == false) {
                        $partialFail = true;
                    }
                }

                $emailFail = false;
                //Email Receipt
                if (isset($_POST['emailReceipt'])) {
                    if ($_POST['emailReceipt'] == 'Y' && stripos($status, 'Paid') !== false) {
                        $from = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
                        if ($partialFail == false and $from != '') {
                            //Send emails
                            $emails = array() ;
                            if (isset($_POST['emails'])) {
                                $emails = $_POST['emails'] ?? '';
                                for ($i = 0; $i < count($emails); ++$i) {
                                    $emailsInner = explode(',', $emails[$i]);
                                    for ($n = 0; $n < count($emailsInner); ++$n) {
                                        if ($n == 0) {
                                            $emails[$i] = trim($emailsInner[$n]);
                                        } else {
                                            array_push($emails, trim($emailsInner[$n]));
                                        }
                                    }
                                }
                            }
                            if (count($emails) > 0) {
                                //Get receipt number

                                    $dataPayments = array('foreignTable' => 'tawasulFinanceInvoice', 'foreignTableID' => $tawasulFinanceInvoiceID);
                                    $sqlPayments = 'SELECT tawasulPayment.*, surname, preferredName FROM tawasulPayment JOIN tawasulPerson ON (tawasulPayment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE foreignTable=:foreignTable AND foreignTableID=:foreignTableID ORDER BY timestamp, tawasulPaymentID';
                                    $resultPayments = $connection2->prepare($sqlPayments);
                                    $resultPayments->execute($dataPayments);
                                $receiptCount = $resultPayments->rowCount();

                                //Prep message
                                $body = receiptContents($guid, $connection2, $tawasulFinanceInvoiceID, $tawasulSchoolYearID, $session->get('currency'), true, $receiptCount-1);

                                $mail = $container->get(Mailer::class);
                                $mail->SetFrom($from, sprintf(__('%1$s Finance'), $session->get('organisationName')));
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

                                if (!$mail->Send()) {
                                    $emailFail = true;
                                }
                            } else {
                                $emailFail = true;
                            }
                        }
                    }
                }
                //Email reminder
                if (isset($_POST['emailReminder'])) {
                    if ($_POST['emailReminder'] == 'Y') {
                        $from = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
                        if ($partialFail == false and $from != '') {
                            //Send emails
                            $emails = array() ;
                            if (isset($_POST['emails'])) {
                                $emails = $_POST['emails'] ?? '';
                                for ($i = 0; $i < count($emails); ++$i) {
                                    $emailsInner = explode(',', $emails[$i]);
                                    for ($n = 0; $n < count($emailsInner); ++$n) {
                                        if ($n == 0) {
                                            $emails[$i] = trim($emailsInner[$n]);
                                        } else {
                                            array_push($emails, trim($emailsInner[$n]));
                                        }
                                    }
                                }
                            }


                            if (count($emails) > 0) {
                                $body = '';
                                $settingGateway = $container->get(SettingGateway::class);
                                //Prep message
                                if ($row['reminderCount'] == '0') {
                                    $reminderText = $settingGateway->getSettingByScope('Finance', 'reminder1Text');
                                } elseif ($row['reminderCount'] == '1') {
                                    $reminderText = $settingGateway->getSettingByScope('Finance', 'reminder2Text');
                                } elseif ($row['reminderCount'] >= '2') {
                                    $reminderText = $settingGateway->getSettingByScope('Finance', 'reminder3Text');
                                }
                                if ($reminderText != '') {
                                    $reminderOutput = $row['reminderCount'] + 1;
                                    if ($reminderOutput > 3) {
                                        $reminderOutput = '3+';
                                    }
                                    $body .= '<p>Reminder '.$reminderOutput.': '.$reminderText.'</p><br/>';
                                }
                                $body .= invoiceContents($guid, $connection2, $tawasulFinanceInvoiceID, $tawasulSchoolYearID, $session->get('currency'), true)."<p style='font-style: italic;'>Email sent via ".$session->get('systemName').' at '.$session->get('organisationName').'.</p>';

                                //Update reminder count
                                if ($row['reminderCount'] < 3) {

                                        $data = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID, 'reminderCount' => $row['reminderCount'] + 1);
                                        $sql = 'UPDATE tawasulFinanceInvoice SET reminderCount=:reminderCount WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                }

                                $mail = $container->get(Mailer::class);
                                $mail->SetFrom($from, sprintf(__('%1$s Finance'), $session->get('organisationName')));
                                foreach ($emails as $address) {
                                    $mail->AddBCC($address);
                                }

                                $mail->Subject = __('Reminder from {organisation} via {system}', [
                                    'organisation' => $session->get('organisationNameShort'),
                                    'system' => $session->get('systemName'),
                                ]);

                                $mail->renderBody('mail/email.twig.html', [
                                    'title'  => $mail->Subject,
                                    'body'   => $body,
                                    'maxWidth' => '900px',
                                ]);

                                if (!$mail->Send()) {
                                    $emailFail = true;
                                }
                            } else {
                                $emailFail = true;
                            }

                            if ($emailFail) {
                                $logArray = [];
                                $logArray['recipients'] = is_array($emails) ? implode(',', $emails) : $emails;
                                $logGateway->addLog($session->get("tawasulSchoolYearID"), 'Finance', $session->get("tawasulPersonID"), 'Finance - Reminder Email Failure', $logArray);
                            }
                        }
                    }
                }

                if ($partialFail == true) {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } elseif ($emailFail == true) {
                    $URL .= '&return=success1';
                    header("Location: {$URL}");
                } else {
                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
