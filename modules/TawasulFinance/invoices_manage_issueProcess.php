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

use TawasulOS\Services\Format;
use TawasulOS\Contracts\Comms\Mailer;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['notes' => 'HTML']);

//Module includes
include './moduleFunctions.php';

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulFinanceInvoiceID = $_POST['tawasulFinanceInvoiceID'] ?? '';
$status = $_GET['status'] ?? '';
$tawasulFinanceInvoiceeID = $_GET['tawasulFinanceInvoiceeID'] ?? '';
$monthOfIssue = $_GET['monthOfIssue'] ?? '';
$tawasulFinanceBillingScheduleID = $_GET['tawasulFinanceBillingScheduleID'] ?? '';
$tawasulFinanceFeeCategoryID = $_GET['tawasulFinanceFeeCategoryID'] ?? '';

if ($tawasulFinanceInvoiceID == '' or $tawasulSchoolYearID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/invoices_manage_issue.php&tawasulFinanceInvoiceID=$tawasulFinanceInvoiceID&tawasulSchoolYearID=$tawasulSchoolYearID&status=$status&tawasulFinanceInvoiceeID=$tawasulFinanceInvoiceeID&monthOfIssue=$monthOfIssue&tawasulFinanceBillingScheduleID=$tawasulFinanceBillingScheduleID&tawasulFinanceFeeCategoryID=$tawasulFinanceFeeCategoryID";
    $URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/invoices_manage.php&tawasulFinanceInvoiceID=$tawasulFinanceInvoiceID&tawasulSchoolYearID=$tawasulSchoolYearID&status=$status&tawasulFinanceInvoiceeID=$tawasulFinanceInvoiceeID&monthOfIssue=$monthOfIssue&tawasulFinanceBillingScheduleID=$tawasulFinanceBillingScheduleID&tawasulFinanceFeeCategoryID=$tawasulFinanceFeeCategoryID";

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
                $sql = 'LOCK TABLES tawasulFinanceInvoice WRITE, tawasulFinanceInvoiceFee WRITE, tawasulFinanceInvoicee WRITE, tawasulFinanceFee WRITE, tawasulFinanceFeeCategory WRITE';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            try {
                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                $sql = "SELECT * FROM tawasulFinanceInvoice WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID AND status='Pending'";
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
                $status = 'Issued';
                $invoiceDueDate = $_POST['invoiceDueDate'] ?? '';
                if ($row['billingScheduleType'] == 'Scheduled') {
                    $separated = 'Y';
                } else {
                    $separated = null;
                }
                $invoiceIssueDate = date('Y-m-d');

                if ($invoiceDueDate == '') {
                    $URL .= '&return=error1';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('status' => $status, 'notes' => $notes, 'separated' => $separated, 'invoiceDueDate' => Format::dateConvert($invoiceDueDate), 'invoiceIssueDate' => $invoiceIssueDate, 'tawasulPersonIDUpdate' => $session->get('tawasulPersonID'), 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                        $sql = "UPDATE tawasulFinanceInvoice SET status=:status, notes=:notes, separated=:separated, invoiceDueDate=:invoiceDueDate, invoiceIssueDate=:invoiceIssueDate, tawasulPersonIDUpdate=:tawasulPersonIDUpdate, timestampUpdate='".date('Y-m-d H:i:s')."' WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $partialFail = false;
                    $emailFail = false;

                    //Read & Organise Fees
                    $fees = array();
                    $count = 0;
                    //Standard Fees
                    try {
                        $dataFees['tawasulFinanceInvoiceID'] = $tawasulFinanceInvoiceID;
                        $sqlFees = "SELECT tawasulFinanceInvoiceFee.tawasulFinanceInvoiceFeeID, tawasulFinanceInvoiceFee.feeType, tawasulFinanceFeeCategory.name AS category, tawasulFinanceFee.name AS name, tawasulFinanceFee.fee AS fee, tawasulFinanceFee.description AS description, tawasulFinanceInvoiceFee.tawasulFinanceFeeID AS tawasulFinanceFeeID, tawasulFinanceFee.tawasulFinanceFeeCategoryID AS tawasulFinanceFeeCategoryID, sequenceNumber FROM tawasulFinanceInvoiceFee JOIN tawasulFinanceFee ON (tawasulFinanceInvoiceFee.tawasulFinanceFeeID=tawasulFinanceFee.tawasulFinanceFeeID) JOIN tawasulFinanceFeeCategory ON (tawasulFinanceFee.tawasulFinanceFeeCategoryID=tawasulFinanceFeeCategory.tawasulFinanceFeeCategoryID) WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID AND feeType='Standard' ORDER BY sequenceNumber";
                        $resultFees = $connection2->prepare($sqlFees);
                        $resultFees->execute($dataFees);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                    while ($rowFees = $resultFees->fetch()) {
                        $fees[$count]['name'] = $rowFees['name'];
                        $fees[$count]['tawasulFinanceFeeCategoryID'] = $rowFees['tawasulFinanceFeeCategoryID'];
                        $fees[$count]['fee'] = $rowFees['fee'];
                        $fees[$count]['feeType'] = 'Standard';
                        $fees[$count]['tawasulFinanceFeeID'] = $rowFees['tawasulFinanceFeeID'];
                        $fees[$count]['separated'] = 'Y';
                        $fees[$count]['description'] = $rowFees['description'];
                        $fees[$count]['sequenceNumber'] = $rowFees['sequenceNumber'];
                        ++$count;
                    }

                    //Ad Hoc Fees
                    try {
                        $dataFees['tawasulFinanceInvoiceID'] = $tawasulFinanceInvoiceID;
                        $sqlFees = "SELECT tawasulFinanceInvoiceFee.tawasulFinanceInvoiceFeeID, tawasulFinanceInvoiceFee.feeType, tawasulFinanceFeeCategory.name AS category, tawasulFinanceInvoiceFee.name AS name, tawasulFinanceInvoiceFee.fee, tawasulFinanceInvoiceFee.description AS description, NULL AS tawasulFinanceFeeID, tawasulFinanceInvoiceFee.tawasulFinanceFeeCategoryID AS tawasulFinanceFeeCategoryID, sequenceNumber FROM tawasulFinanceInvoiceFee JOIN tawasulFinanceFeeCategory ON (tawasulFinanceInvoiceFee.tawasulFinanceFeeCategoryID=tawasulFinanceFeeCategory.tawasulFinanceFeeCategoryID) WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID AND feeType='Ad Hoc' ORDER BY sequenceNumber";
                        $resultFees = $connection2->prepare($sqlFees);
                        $resultFees->execute($dataFees);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                    while ($rowFees = $resultFees->fetch()) {
                        $fees[$count]['name'] = $rowFees['name'];
                        $fees[$count]['tawasulFinanceFeeCategoryID'] = $rowFees['tawasulFinanceFeeCategoryID'];
                        $fees[$count]['fee'] = $rowFees['fee'];
                        $fees[$count]['feeType'] = 'Ad Hoc';
                        $fees[$count]['tawasulFinanceFeeID'] = null;
                        $fees[$count]['separated'] = null;
                        $fees[$count]['description'] = $rowFees['description'];
                        $fees[$count]['sequenceNumber'] = $rowFees['sequenceNumber'];
                        ++$count;
                    }

                    //Remove fees
                    try {
                        $data = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                        $sql = 'DELETE FROM tawasulFinanceInvoiceFee WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }

                    //Add fees to invoice
                    foreach ($fees as $fee) {
                        try {
                            $dataInvoiceFee = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID, 'feeType' => $fee['feeType'], 'tawasulFinanceFeeID' => $fee['tawasulFinanceFeeID'], 'name' => $fee['name'], 'description' => $fee['description'], 'tawasulFinanceFeeCategoryID' => $fee['tawasulFinanceFeeCategoryID'], 'fee' => $fee['fee'], 'separated' => $fee['separated'], 'sequenceNumber' => $fee['sequenceNumber']);
                            $sqlInvoiceFee = 'INSERT INTO tawasulFinanceInvoiceFee SET tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID, feeType=:feeType, tawasulFinanceFeeID=:tawasulFinanceFeeID, name=:name, description=:description, tawasulFinanceFeeCategoryID=:tawasulFinanceFeeCategoryID, fee=:fee, separated=:separated, sequenceNumber=:sequenceNumber';
                            $resultInvoiceFee = $connection2->prepare($sqlInvoiceFee);
                            $resultInvoiceFee->execute($dataInvoiceFee);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                    }

                    //Unlock module table

                        $sql = 'UNLOCK TABLES';
                        $result = $connection2->query($sql);

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
                            //Prep message
                            $body = invoiceContents($guid, $connection2, $tawasulFinanceInvoiceID, $tawasulSchoolYearID, $session->get('currency'), true);

                            $mail = $container->get(Mailer::class);
                            $mail->SetFrom($from, sprintf(__('%1$s Finance'), $session->get('organisationName')));
                            foreach ($emails as $address) {
                                $mail->AddBCC($address);
                            }

                            $mail->Subject = __('Invoice from {organisation} via {system}', [
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
                        }
                    }

                    if ($partialFail == true) {
                        $URL .= '&return=error3';
                        header("Location: {$URL}");
                    } elseif ($emailFail == true) {
                        $URLSuccess = $URLSuccess.'&return=success1';
                        header("Location: {$URLSuccess}");
                    } else {
                        $URLSuccess = $URLSuccess.'&return=success0';
                        header("Location: {$URLSuccess}");
                    }
                }
            }
        }
    }
}
