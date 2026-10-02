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

require_once __DIR__ . '/../../tawasul.php';

$settingGateway = $container->get(SettingGateway::class);
$from = $settingGateway->getSettingByScope('Finance', 'email');

//Module includes
include './moduleFunctions.php';

$logGateway = $container->get(LogGateway::class);
$action = $_POST['action'] ?? '';
$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$status = $_GET['status'] ?? '';
$tawasulFinanceInvoiceeID = $_GET['tawasulFinanceInvoiceeID'] ?? '';
$monthOfIssue = $_GET['monthOfIssue'] ?? '';
$tawasulFinanceBillingScheduleID = $_GET['tawasulFinanceBillingScheduleID'] ?? '';
$tawasulFinanceFeeCategoryID = $_GET['tawasulFinanceFeeCategoryID'] ?? '';

if ($tawasulSchoolYearID == '' or $action == '') { echo 'Fatal error loading this page!';
} else {
    if ($action == 'issue' or $action == 'issueNoEmail') {
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/invoices_manage.php&tawasulSchoolYearID=$tawasulSchoolYearID&status=Issued&tawasulFinanceInvoiceeID=$tawasulFinanceInvoiceeID&monthOfIssue=$monthOfIssue&tawasulFinanceBillingScheduleID=$tawasulFinanceBillingScheduleID";
    } else {
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/invoices_manage.php&tawasulSchoolYearID=$tawasulSchoolYearID&status=$status&tawasulFinanceInvoiceeID=$tawasulFinanceInvoiceeID&monthOfIssue=$monthOfIssue&tawasulFinanceBillingScheduleID=$tawasulFinanceBillingScheduleID&tawasulFinanceFeeCategoryID=$tawasulFinanceFeeCategoryID";
    }

    if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_manage.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        $tawasulFinanceInvoiceIDs = $_POST['tawasulFinanceInvoiceIDs'] ?? '';
        if (count($tawasulFinanceInvoiceIDs) < 1) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            $partialFail = false;
            //DELETE
            if ($action == 'delete') {
                foreach ($tawasulFinanceInvoiceIDs as $tawasulFinanceInvoiceID) {
                    try {
                        $data = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                        $sql = 'DELETE FROM tawasulFinanceInvoice WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }

                    try {
                        $data = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                        $sql = 'DELETE FROM tawasulFinanceInvoiceFee WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                }
                if ($partialFail == true) {
                    $URL .= '&return=warning1';
                    header("Location: {$URL}");
                } else {
                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
            //ISSUE
            elseif ($action == 'issue' or $action == 'issueNoEmail') {
                $thisLockFail = false;
                //LOCK INVOICE TABLES
                try {
                    $data = array();
                    $sql = 'LOCK TABLES tawasulFinanceInvoice WRITE, tawasulFinanceInvoiceFee WRITE, tawasulFinanceInvoicee WRITE, tawasulFinanceFee WRITE, tawasulFinanceFeeCategory WRITE, tawasulFinanceBillingSchedule WRITE';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $partialFail = true;
                    $thisLockFail = true;
                }

                if ($thisLockFail == false) {
                    $emailFail = false;
                    foreach ($tawasulFinanceInvoiceIDs as $tawasulFinanceInvoiceID) {

                            $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                            $sql = "SELECT tawasulFinanceInvoice.*, tawasulFinanceBillingSchedule.invoiceDueDate AS invoiceDueDateScheduled FROM tawasulFinanceInvoice LEFT JOIN tawasulFinanceBillingSchedule ON (tawasulFinanceInvoice.tawasulFinanceBillingScheduleID=tawasulFinanceBillingSchedule.tawasulFinanceBillingScheduleID) WHERE tawasulFinanceInvoice.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID AND status='Pending'";
                            $result = $connection2->prepare($sql);
                            $result->execute($data);

                        if ($result->rowCount() != 1) {
                            $partialFail = true;
                        } else {
                            $row = $result->fetch();
                            $status = 'Issued';
                            if ($row['billingScheduleType'] == 'Scheduled') {
                                $separated = 'Y';
                                $invoiceDueDate = $row['invoiceDueDateScheduled'];
                            } else {
                                $separated = null;
                                $invoiceDueDate = $row['invoiceDueDate'];
                            }
                            $invoiceIssueDate = date('Y-m-d');

                            if ($invoiceDueDate == '') {
                                $partialFail = true;
                            } else {
                                //Write to database
                                try {
                                    $data = array('status' => $status, 'separated' => $separated, 'invoiceDueDate' => $invoiceDueDate, 'invoiceIssueDate' => $invoiceIssueDate, 'tawasulPersonIDUpdate' => $session->get('tawasulPersonID'), 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                                    $sql = "UPDATE tawasulFinanceInvoice SET status=:status, separated=:separated, invoiceDueDate=:invoiceDueDate, invoiceIssueDate=:invoiceIssueDate, tawasulPersonIDUpdate=:tawasulPersonIDUpdate, timestampUpdate='".date('Y-m-d H:i:s')."' WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID";
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $URL .= '&return=error2';
                                    header("Location: {$URL}");
                                    exit();
                                }

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
                            }
                        }
                    }
                }

                //Unlock invoice table

                    $sql = 'UNLOCK TABLES';
                    $result = $connection2->query($sql);

                if ($action == 'issue') {
                    //Loop through invoices again, this time to send invoices....they can not be sent in first loop due to table locking issues.
                    foreach ($tawasulFinanceInvoiceIDs as $tawasulFinanceInvoiceID) {

                            $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                            $sql = 'SELECT tawasulFinanceInvoice.*, tawasulFinanceBillingSchedule.invoiceDueDate AS invoiceDueDateScheduled FROM tawasulFinanceInvoice LEFT JOIN tawasulFinanceBillingSchedule ON (tawasulFinanceInvoice.tawasulFinanceBillingScheduleID=tawasulFinanceBillingSchedule.tawasulFinanceBillingScheduleID) WHERE tawasulFinanceInvoice.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);

                        $emails = [];
                        $emailsCount = 0;

                        if ($result->rowCount() != 1) {
                            $emailFail = true;
                        } else {
                            $row = $result->fetch();

                            //DEAL WITH EMAILS
                            if ($row['invoiceTo'] == 'Company') {
                                try {
                                    $dataCompany = array('tawasulFinanceInvoiceeID' => $row['tawasulFinanceInvoiceeID']);
                                    $sqlCompany = 'SELECT * FROM tawasulFinanceInvoicee WHERE tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID';
                                    $resultCompany = $connection2->prepare($sqlCompany);
                                    $resultCompany->execute($dataCompany);
                                } catch (PDOException $e) {
                                    $emailFail = true;
                                }
                                if ($resultCompany->rowCount() != 1) {
                                    $emailFail = true;
                                } else {
                                    $rowCompany = $resultCompany->fetch();
                                    if ($rowCompany['companyEmail'] != '' and $rowCompany['companyContact'] != '' and $rowCompany['companyName'] != '') {
                                        $emails = array_map('trim', explode(',', $rowCompany['companyEmail']));
                                        $emailsCount += count($emails);

                                        if ($rowCompany['companyCCFamily'] == 'Y') {
                                            try {
                                                $dataParents = array('tawasulFinanceInvoiceeID' => $row['tawasulFinanceInvoiceeID']);
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
                                    $dataParents = array('tawasulFinanceInvoiceeID' => $row['tawasulFinanceInvoiceeID']);
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

                            if ($from == '' or count($emails) < 1) {
                                $emailFail = true;
                            } else {
                                //Prep message
                                $body = invoiceContents($guid, $connection2, $tawasulFinanceInvoiceID, $tawasulSchoolYearID, $session->get('currency'), true)."<p style='font-style: italic;'>Email sent via ".$session->get('systemName').' at '.$session->get('organisationName').'.</p>';

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
                                    //Set log
                                    $logArray=array() ;
                                    $logArray['recipients'] = is_array($emails) ? implode(',', $emails) : '' ;
                                    $logGateway->addLog($session->get("tawasulSchoolYearID"), 'Finance', $session->get("tawasulPersonID"), 'Finance - Bulk Invoice Issue Email Failure', $logArray) ;
                                }
                            }
                        }
                    }
                }

                if ($partialFail == true) {
                    $URL .= '&return=warning1';
                    header("Location: {$URL}");
                } elseif ($emailFail == true) {
                    $URL .= '&return=success1';
                    header("Location: {$URL}");
                } else {
                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
            //REMINDERS
            elseif ($action == 'reminders') {
                foreach ($tawasulFinanceInvoiceIDs as $tawasulFinanceInvoiceID) {

                        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                        $sql = "SELECT tawasulFinanceInvoice.*, tawasulFinanceBillingSchedule.invoiceDueDate AS invoiceDueDateScheduled FROM tawasulFinanceInvoice LEFT JOIN tawasulFinanceBillingSchedule ON (tawasulFinanceInvoice.tawasulFinanceBillingScheduleID=tawasulFinanceBillingSchedule.tawasulFinanceBillingScheduleID) WHERE tawasulFinanceInvoice.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID AND (status='Issued' OR status='Paid - Partial')";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);

                    $emailFail = false;
                    $emails = [];
                    $emailsCount = 0;

                    if ($result->rowCount() != 1) {
                        $partialFail = true;
                    } else {
                        $row = $result->fetch();

                        //DEAL WITH EMAILS
                        if ($row['invoiceTo'] == 'Company') {
                            try {
                                $dataCompany = array('tawasulFinanceInvoiceeID' => $row['tawasulFinanceInvoiceeID']);
                                $sqlCompany = 'SELECT * FROM tawasulFinanceInvoicee WHERE tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID';
                                $resultCompany = $connection2->prepare($sqlCompany);
                                $resultCompany->execute($dataCompany);
                            } catch (PDOException $e) {
                                $emailFail = true;
                            }
                            if ($resultCompany->rowCount() != 1) {
                                $emailFail = true;
                            } else {
                                $rowCompany = $resultCompany->fetch();
                                if ($rowCompany['companyEmail'] != '' and $rowCompany['companyContact'] != '' and $rowCompany['companyName'] != '') {
                                    $emails = array_map('trim', explode(',', $rowCompany['companyEmail']));
                                    $emailsCount += count($emails);

                                    if ($rowCompany['companyCCFamily'] == 'Y') {
                                        try {
                                            $dataParents = array('tawasulFinanceInvoiceeID' => $row['tawasulFinanceInvoiceeID']);
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
                                $dataParents = array('tawasulFinanceInvoiceeID' => $row['tawasulFinanceInvoiceeID']);
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
                    }

                    if ($from == '' or count($emails) < 1) {
                        $emailFail = true;
                    } else {
                        //Prep message
                        $body = '';
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
                            //Set log
                            $logArray=array() ;
                            $logArray['recipients'] = is_array($emails) ? implode(',', $emails) : '' ;
                            $logGateway->addLog($session->get("tawasulSchoolYearID"), 'Finance', $session->get("tawasulPersonID"), 'Finance - Bulk Invoice Reminder Email Failure', $logArray) ;
                        }
                    }
                }

                if ($partialFail == true) {
                    $URL .= '&return=warning1';
                    header("Location: {$URL}");
                } elseif ($emailFail == true) {
                    $URL .= '&return=success1';
                    header("Location: {$URL}");
                } else {
                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
            //Export
            elseif ($action == 'export') {
                $session->set('financeInvoiceExportIDs', $tawasulFinanceInvoiceIDs);

				include ('./invoices_manage_processBulkExportContents.php');
            }
            // Mark as Paid
            elseif ($action == 'paid') {
                $paymentType = $_POST['paymentType'] ?? '';
                $paidDate = !empty($_POST['paidDate']) ? Format::dateConvert($_POST['paidDate']) : null;

                if (empty($paymentType) || empty($paidDate)) {
                    $URL .= '&return=error1';
                    header("Location: {$URL}");
                    exit;
                }

                $partialFail = false;
                foreach ($tawasulFinanceInvoiceIDs as $tawasulFinanceInvoiceID) {
                    $totalFee = getInvoiceTotalFee($pdo, $tawasulFinanceInvoiceID, 'Issued');
                    $alreadyPaid = getAmountPaid($connection2, $guid, 'tawasulFinanceInvoice', $tawasulFinanceInvoiceID);

                    $paidAmount = $totalFee - $alreadyPaid;

                    if (empty($paidAmount) || $paidAmount <= 0) {
                        $partialFail = true;
                    } else {
                        $logFail = setPaymentLog($connection2, $guid, 'tawasulFinanceInvoice', $tawasulFinanceInvoiceID, $paymentType, 'Complete', $paidAmount, null, null, null, null, null, null, $paidDate);
                        if ($logFail == false) {
                            $partialFail = true;
                        } else {
                            try {
                                $data = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID, 'paidDate' => $paidDate, 'paidAmount' => $paidAmount, 'timestampUpdate' => date('Y-m-d H:i:s'), 'tawasulPersonIDUpdate' => $session->get('tawasulPersonID'));
                                $sql = "UPDATE tawasulFinanceInvoice SET status='Paid', paidDate=:paidDate, paidAmount=:paidAmount, tawasulPersonIDUpdate=:tawasulPersonIDUpdate, timestampUpdate=:timestampUpdate WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID";
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        }
                    }
                }

                if ($partialFail == true) {
                    $URL .= '&return=warning1';
                    header("Location: {$URL}");
                } else {
                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }

            } else {
                $URL .= '&return=error1';
                header("Location: {$URL}");
            }
        }
    }
}
