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

use TawasulOS\Data\PasswordPolicy;
use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['notes' => 'HTML']);

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$status = $_GET['status'] ?? '';
$tawasulFinanceInvoiceeID = $_GET['tawasulFinanceInvoiceeID'] ?? '';
$monthOfIssue = $_GET['monthOfIssue'] ?? '';
$tawasulFinanceBillingScheduleID = $_GET['tawasulFinanceBillingScheduleID'] ?? '';
$tawasulFinanceFeeCategoryID = $_GET['tawasulFinanceFeeCategoryID'] ?? '';

if ($tawasulSchoolYearID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/invoices_manage_add.php&tawasulSchoolYearID=$tawasulSchoolYearID&status=$status&tawasulFinanceInvoiceeID=$tawasulFinanceInvoiceeID&monthOfIssue=$monthOfIssue&tawasulFinanceBillingScheduleID=$tawasulFinanceBillingScheduleID&tawasulFinanceFeeCategoryID=$tawasulFinanceFeeCategoryID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_manage_add.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        $tawasulFinanceInvoiceeIDs = $_POST['tawasulFinanceInvoiceeIDs'] ?? '';
        $scheduling = $_POST['scheduling'] ?? '';
        if ($scheduling == 'Scheduled') {
            $tawasulFinanceBillingScheduleID = $_POST['tawasulFinanceBillingScheduleID'] ?? '';
            $invoiceDueDate = null;
        } elseif ($scheduling == 'Ad Hoc') {
            $tawasulFinanceBillingScheduleID = null;
            $invoiceDueDate = !empty($_POST['invoiceDueDate']) ? Format::dateConvert($_POST['invoiceDueDate']) : null;
        }
        $notes = $_POST['notes'] ?? '';
        $order = $_POST['order'] ?? array();

        if (count($tawasulFinanceInvoiceeIDs) == 0 or $scheduling == '' or ($scheduling == 'Scheduled' and $tawasulFinanceBillingScheduleID == '') or ($scheduling == 'Ad Hoc' and empty($invoiceDueDate)) or count($order) == 0) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            $studentFailCount = 0;
            $invoiceFailCount = 0;
            $invoiceFeeFailCount = 0;
            $feeFail = false;

            // Use password policy to generate random strings
            $randStrGenerator = new PasswordPolicy(true, true, false, 40);

            //PROCESS FEES
            $fees = [];
            foreach ($order as $fee) {
                $fees[$fee]['name'] = $_POST['name'.$fee] ?? '';
                $fees[$fee]['tawasulFinanceFeeCategoryID'] = $_POST['tawasulFinanceFeeCategoryID'.$fee] ?? '';
                $fees[$fee]['fee'] = $_POST['fee'.$fee] ?? '';
                $fees[$fee]['feeType'] = $_POST['feeType'.$fee] ?? '';
                $fees[$fee]['tawasulFinanceFeeID'] = $_POST['tawasulFinanceFeeID'.$fee] ?? '';
                $fees[$fee]['description'] = $_POST['description'.$fee] ?? '';

                if ($fees[$fee]['name'] == '' or $fees[$fee]['tawasulFinanceFeeCategoryID'] == '' or $fees[$fee]['fee'] == '' or is_numeric($fees[$fee]['fee']) == false or $fees[$fee]['feeType'] == '' or ($fees[$fee]['feeType'] == 'Standard' and $fees[$fee]['tawasulFinanceFeeID'] == '')) {
                    $feeFail = true;
                }
            }

            if ($feeFail == true) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit();
            } else {
                //CYCLE THROUGH STUDENTS
                foreach ($tawasulFinanceInvoiceeIDs as $tawasulFinanceInvoiceeID) {
                    // Check for a dash, for cases where the ID has been joined with a tawasulCourseClassID
                    $tawasulFinanceInvoiceeID = (strpos($tawasulFinanceInvoiceeID, "-") == 8) ? substr($tawasulFinanceInvoiceeID, 9) : $tawasulFinanceInvoiceeID;

                    $thisStudentFailed = false;
                    $invoiceTo = '';
                    $companyAll = '';
                    $tawasulFinanceFeeCategoryIDList2 = '';

                    //GET INVOICE RECORD, set $invoiceTo and $companyCategories if required
                    try {
                        $data = array('tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID);
                        $sql = 'SELECT * FROM tawasulFinanceInvoicee WHERE tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        ++$studentFailCount;
                        $thisStudentFailed = true;
                    }
                    if ($result->rowCount() != 1) {
                        if ($thisStudentFailed != true) {
                            ++$studentFailCount;
                            $thisStudentFailed = true;
                        }
                    } else {
                        $row = $result->fetch();
                        $invoiceTo = $row['invoiceTo'];
                        if ($invoiceTo != 'Family' and $invoiceTo != 'Company') {
                            ++$studentFailCount;
                            $thisStudentFailed = true;
                        } else {
                            if ($invoiceTo == 'Company') {
                                $companyAll = $row['companyAll'];
                                if ($companyAll == 'N') {
                                    $tawasulFinanceFeeCategoryIDList2 = $row['tawasulFinanceFeeCategoryIDList'];
                                    if ($tawasulFinanceFeeCategoryIDList2 != '') {
                                        $tawasulFinanceFeeCategoryIDs = explode(',', $tawasulFinanceFeeCategoryIDList2);
                                    } else {
                                        $tawasulFinanceFeeCategoryIDs = null;
                                    }
                                }

                                $companyFamily = false; //This holds true when company is set, companyAll=N and there are some fees for the family to pay...
                                foreach ($fees as $fee) {
                                    if ($invoiceTo == 'Company' and $companyAll == 'N' and strpos($tawasulFinanceFeeCategoryIDList2, $fee['tawasulFinanceFeeCategoryID']) === false) {
                                        $companyFamily = true;
                                    }
                                }
                                $companyFamilyCompanyHasCharges = false; //This holds true when company is set, companyAll=N and there are some fees for the company to pay...e.g.  they are not all held by the family
                                if ($invoiceTo == 'Company' and $companyAll == 'N') {
                                    foreach ($fees as $fee) {
                                        if ($invoiceTo == 'Company' and $companyAll == 'N' and is_numeric(strpos($tawasulFinanceFeeCategoryIDList2, $fee['tawasulFinanceFeeCategoryID']))) {
                                            $companyFamilyCompanyHasCharges = true;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if ($thisStudentFailed == false) {
                        //CHECK FOR INVOICE AND UPDATE/ADD FOR FAMILY (INC WHEN COMPANY IS PAYING ONLY SOME FEES)
                        if ($invoiceTo == 'Family' or $companyFamily == true) {
                            $thisInvoiceFailed = false;
                            try {
                                if ($scheduling == 'Scheduled') {
                                    $dataInvoice = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID, 'tawasulFinanceBillingScheduleID' => $tawasulFinanceBillingScheduleID);
                                    $sqlInvoice = "SELECT * FROM tawasulFinanceInvoice WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID AND invoiceTo='Family' AND billingScheduleType='Scheduled' AND tawasulFinanceBillingScheduleID=:tawasulFinanceBillingScheduleID AND status='Pending'";
                                } else {
                                    $dataInvoice = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID);
                                    $sqlInvoice = "SELECT * FROM tawasulFinanceInvoice WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID AND invoiceTo='Family' AND billingScheduleType='Ad Hoc' AND status='Pending'";
                                }
                                $resultInvoice = $connection2->prepare($sqlInvoice);
                                $resultInvoice->execute($dataInvoice);
                            } catch (PDOException $e) {
                                ++$invoiceFailCount;
                                $thisInvoiceFailed = true;
                            }
                            if ($resultInvoice->rowCount() == 0 and $thisInvoiceFailed == false) {
                                //Add invoice
                                //Make and store unique code for confirmation. add it to email text.
                                $key = '';

                                //Let's go! Create key, send the invite
                                $continue = false;
                                $count = 0;

                                while ($continue == false and $count < 100) {
                                    $key = $randStrGenerator->generate();
                                    $dataUnique = array('key' => $key);
                                    $sqlUnique = 'SELECT * FROM tawasulFinanceInvoice WHERE tawasulFinanceInvoice.`key`=:key';
                                    $resultUnique = $connection2->prepare($sqlUnique);
                                    $resultUnique->execute($dataUnique);

                                    if ($resultUnique->rowCount() == 0) {
                                        $continue = true;
                                    }
                                    ++$count;
                                }

                                if ($continue == false) {
                                    $URL .= '&return=error2';
                                    header("Location: {$URL}");
                                    exit();
                                } else {
                                    try {
                                        if ($scheduling == 'Scheduled') {
                                            $dataInvoiceAdd = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID, 'tawasulFinanceBillingScheduleID' => $tawasulFinanceBillingScheduleID, 'notes' => $notes, 'key' => $key, 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'));
                                            $sqlInvoiceAdd = "INSERT INTO tawasulFinanceInvoice SET tawasulSchoolYearID=:tawasulSchoolYearID, tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID, invoiceTo='Family', billingScheduleType='Scheduled', tawasulFinanceBillingScheduleID=:tawasulFinanceBillingScheduleID, notes=:notes, `key`=:key, status='Pending', separated='N', tawasulPersonIDCreator=:tawasulPersonIDCreator, timeStampCreator='".date('Y-m-d H:i:s')."'";
                                        } else {
                                            $dataInvoiceAdd = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID, 'invoiceDueDate' => $invoiceDueDate, 'notes' => $notes, 'key' => $key, 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'));
                                            $sqlInvoiceAdd = "INSERT INTO tawasulFinanceInvoice SET tawasulSchoolYearID=:tawasulSchoolYearID, tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID, invoiceTo='Family', billingScheduleType='Ad Hoc', status='Pending', invoiceDueDate=:invoiceDueDate, notes=:notes, `key`=:key, tawasulPersonIDCreator=:tawasulPersonIDCreator, timeStampCreator='".date('Y-m-d H:i:s')."'";
                                        }
                                        $resultInvoiceAdd = $connection2->prepare($sqlInvoiceAdd);
                                        $resultInvoiceAdd->execute($dataInvoiceAdd);
                                    } catch (PDOException $e) {
                                        ++$invoiceFailCount;
                                        $thisInvoiceFailed = true;
                                    }

                                    $AI = $connection2->lastInsertID();

                                    if ($thisInvoiceFailed == false) {
                                        //Add fees to invoice
                                        $count = 0;
                                        foreach ($fees as $fee) {
                                            ++$count;
                                            if ($invoiceTo == 'Family' or ($invoiceTo == 'Company' and $companyAll == 'N' and strpos($tawasulFinanceFeeCategoryIDList2, $fee['tawasulFinanceFeeCategoryID']) === false)) {
                                                try {
                                                    if ($fee['feeType'] == 'Standard') {
                                                        $dataInvoiceFee = array('tawasulFinanceInvoiceID' => $AI, 'feeType' => $fee['feeType'], 'tawasulFinanceFeeID' => $fee['tawasulFinanceFeeID'], 'count' => $count);
                                                        $sqlInvoiceFee = "INSERT INTO tawasulFinanceInvoiceFee SET tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID, feeType=:feeType, tawasulFinanceFeeID=:tawasulFinanceFeeID, separated='N', sequenceNumber=:count";
                                                    } else {
                                                        $dataInvoiceFee = array('tawasulFinanceInvoiceID' => $AI, 'feeType' => $fee['feeType'], 'name' => $fee['name'], 'description' => $fee['description'], 'tawasulFinanceFeeCategoryID' => $fee['tawasulFinanceFeeCategoryID'], 'fee' => $fee['fee'], 'count' => $count);
                                                        $sqlInvoiceFee = "INSERT INTO tawasulFinanceInvoiceFee SET tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID, feeType=:feeType, name=:name, description=:description, tawasulFinanceFeeCategoryID=:tawasulFinanceFeeCategoryID, fee=:fee, sequenceNumber=:count";
                                                    }
                                                    $resultInvoiceFee = $connection2->prepare($sqlInvoiceFee);
                                                    $resultInvoiceFee->execute($dataInvoiceFee);
                                                } catch (PDOException $e) {
                                                    ++$invoiceFeeFailCount;
                                                }
                                            }
                                        }
                                    }
                                }
                            } elseif ($resultInvoice->rowCount() == 1 and $thisInvoiceFailed == false) {
                                $rowInvoice = $resultInvoice->fetch();

                                //Add fees to invoice
                                $count = 0;
                                foreach ($fees as $fee) {
                                    ++$count;
                                    if ($invoiceTo == 'Family' or ($invoiceTo == 'Company' and $companyAll == 'N' and strpos($tawasulFinanceFeeCategoryIDList2, $fee['tawasulFinanceFeeCategoryID']) === false)) {
                                        try {
                                            if ($fee['feeType'] == 'Standard') {
                                                $dataInvoiceFee = array('tawasulFinanceInvoiceID' => $rowInvoice['tawasulFinanceInvoiceID'], 'feeType' => $fee['feeType'], 'tawasulFinanceFeeID' => $fee['tawasulFinanceFeeID'], 'count' => $count);
                                                $sqlInvoiceFee = "INSERT INTO tawasulFinanceInvoiceFee SET tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID, feeType=:feeType, tawasulFinanceFeeID=:tawasulFinanceFeeID, separated='N', sequenceNumber=:count";
                                            } else {
                                                $dataInvoiceFee = array('tawasulFinanceInvoiceID' => $rowInvoice['tawasulFinanceInvoiceID'], 'feeType' => $fee['feeType'], 'name' => $fee['name'], 'description' => $fee['description'], 'tawasulFinanceFeeCategoryID' => $fee['tawasulFinanceFeeCategoryID'], 'fee' => $fee['fee'], 'count' => $count);
                                                $sqlInvoiceFee = "INSERT INTO tawasulFinanceInvoiceFee SET tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID, feeType=:feeType, name=:name, description=:description, tawasulFinanceFeeCategoryID=:tawasulFinanceFeeCategoryID, fee=:fee, sequenceNumber=:count";
                                            }
                                            $resultInvoiceFee = $connection2->prepare($sqlInvoiceFee);
                                            $resultInvoiceFee->execute($dataInvoiceFee);
                                        } catch (PDOException $e) {
                                            ++$invoiceFeeFailCount;
                                        }
                                    }
                                }

                                //Update invoice
                                try {
                                    if ($scheduling == 'Scheduled') {
                                        $dataInvoiceAdd = array('tawasulPersonIDUpdate' => $session->get('tawasulPersonID'), 'notes' => $rowInvoice['notes'].' '.$notes, 'tawasulFinanceInvoiceID' => $rowInvoice['tawasulFinanceInvoiceID']);
                                        $sqlInvoiceAdd = "UPDATE tawasulFinanceInvoice SET tawasulPersonIDUpdate=:tawasulPersonIDUpdate, notes=:notes, timeStampUpdate='".date('Y-m-d H:i:s')."' WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID";
                                    } else {
                                        $dataInvoiceAdd = array('invoiceDueDate' => $invoiceDueDate, 'tawasulPersonIDUpdate' => $session->get('tawasulPersonID'), 'notes' => $rowInvoice['notes'].' '.$notes, 'tawasulFinanceInvoiceID' => $rowInvoice['tawasulFinanceInvoiceID']);
                                        $sqlInvoiceAdd = "UPDATE tawasulFinanceInvoice SET invoiceDueDate=:invoiceDueDate, tawasulPersonIDUpdate=:tawasulPersonIDUpdate, notes=:notes, timeStampUpdate='".date('Y-m-d H:i:s')."' WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID";
                                    }
                                    $resultInvoiceAdd = $connection2->prepare($sqlInvoiceAdd);
                                    $resultInvoiceAdd->execute($dataInvoiceAdd);
                                } catch (PDOException $e) {
                                    ++$invoiceFailCount;
                                    $thisInvoiceFailed = true;
                                }
                            } else {
                                if ($thisInvoiceFailed == false) {
                                    ++$invoiceFailCount;
                                    $thisInvoiceFailed = true;
                                }
                            }
                        }

                        //CHECK FOR INVOICE AND UPDATE/ADD FOR COMPANY
                        if (($invoiceTo == 'Company' and $companyAll == 'Y') or ($invoiceTo == 'Company' and $companyAll == 'N' and $companyFamilyCompanyHasCharges == true)) {
                            $thisInvoiceFailed = false;
                            try {
                                if ($scheduling == 'Scheduled') {
                                    $dataInvoice = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID, 'tawasulFinanceBillingScheduleID' => $tawasulFinanceBillingScheduleID);
                                    $sqlInvoice = "SELECT * FROM tawasulFinanceInvoice WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID AND invoiceTo='Company' AND billingScheduleType='Scheduled' AND tawasulFinanceBillingScheduleID=:tawasulFinanceBillingScheduleID AND status='Pending'";
                                } else {
                                    $dataInvoice = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID);
                                    $sqlInvoice = "SELECT * FROM tawasulFinanceInvoice WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID AND invoiceTo='Company' AND billingScheduleType='Ad Hoc' AND status='Pending'";
                                }
                                $resultInvoice = $connection2->prepare($sqlInvoice);
                                $resultInvoice->execute($dataInvoice);
                            } catch (PDOException $e) {
                                ++$invoiceFailCount;
                                $thisInvoiceFailed = true;
                            }
                            if ($resultInvoice->rowCount() == 0 and $thisInvoiceFailed == false) {
                                //ADD INVOICE
                                //Make and store unique code for confirmation. add it to email text.
                                $key = '';

                                //Let's go! Create key, send the invite
                                $continue = false;
                                $count = 0;
                                while ($continue == false and $count < 100) {
                                    $key = $randStrGenerator->generate();
                                    $dataUnique = array('key' => $key);
                                    $sqlUnique = 'SELECT * FROM tawasulFinanceInvoice WHERE tawasulFinanceInvoice.`key`=:key';
                                    $resultUnique = $connection2->prepare($sqlUnique);
                                    $resultUnique->execute($dataUnique);

                                    if ($resultUnique->rowCount() == 0) {
                                        $continue = true;
                                    }
                                    ++$count;
                                }

                                if ($continue == false) {
                                    $URL .= '&return=error2';
                                    header("Location: {$URL}");
                                    exit();
                                } else {
                                    try {
                                        if ($scheduling == 'Scheduled') {
                                            $dataInvoiceAdd = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID, 'tawasulFinanceBillingScheduleID' => $tawasulFinanceBillingScheduleID, 'notes' => $notes, 'key' => $key, 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'));
                                            $sqlInvoiceAdd = "INSERT INTO tawasulFinanceInvoice SET tawasulSchoolYearID=:tawasulSchoolYearID, tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID, invoiceTo='Company', billingScheduleType='Scheduled', tawasulFinanceBillingScheduleID=:tawasulFinanceBillingScheduleID, notes=:notes, `key`=:key, status='Pending', separated='N', tawasulPersonIDCreator=:tawasulPersonIDCreator, timeStampCreator='".date('Y-m-d H:i:s')."'";
                                        } else {
                                            $dataInvoiceAdd = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID, 'invoiceDueDate' => $invoiceDueDate, 'notes' => $notes, 'key' => $key, 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'));
                                            $sqlInvoiceAdd = "INSERT INTO tawasulFinanceInvoice SET tawasulSchoolYearID=:tawasulSchoolYearID, tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID, invoiceTo='Company', billingScheduleType='Ad Hoc', status='Pending', invoiceDueDate=:invoiceDueDate, notes=:notes, `key`=:key, tawasulPersonIDCreator=:tawasulPersonIDCreator, timeStampCreator='".date('Y-m-d H:i:s')."'";
                                        }
                                        $resultInvoiceAdd = $connection2->prepare($sqlInvoiceAdd);
                                        $resultInvoiceAdd->execute($dataInvoiceAdd);
                                    } catch (PDOException $e) {
                                        ++$invoiceFailCount;
                                        $thisInvoiceFailed = true;
                                    }

                                    $AI = $connection2->lastInsertID();

                                    if ($thisInvoiceFailed == false) {
                                        //Add fees to invoice
                                        $count = 0;
                                        foreach ($fees as $fee) {
                                            ++$count;
                                            if (($invoiceTo == 'Company' and $companyAll == 'Y') or ($invoiceTo == 'Company' and $companyAll == 'N' and is_numeric(strpos($tawasulFinanceFeeCategoryIDList2, $fee['tawasulFinanceFeeCategoryID'])))) {
                                                try {
                                                    if ($fee['feeType'] == 'Standard') {
                                                        $dataInvoiceFee = array('tawasulFinanceInvoiceID' => $AI, 'feeType' => $fee['feeType'], 'tawasulFinanceFeeID' => $fee['tawasulFinanceFeeID'], 'count' => $count);
                                                        $sqlInvoiceFee = "INSERT INTO tawasulFinanceInvoiceFee SET tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID, feeType=:feeType, tawasulFinanceFeeID=:tawasulFinanceFeeID, separated='N', sequenceNumber=:count";
                                                    } else {
                                                        $dataInvoiceFee = array('tawasulFinanceInvoiceID' => $AI, 'feeType' => $fee['feeType'], 'name' => $fee['name'], 'description' => $fee['description'], 'tawasulFinanceFeeCategoryID' => $fee['tawasulFinanceFeeCategoryID'], 'fee' => $fee['fee'], 'count' => $count);
                                                        $sqlInvoiceFee = "INSERT INTO tawasulFinanceInvoiceFee SET tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID, feeType=:feeType, name=:name, description=:description, tawasulFinanceFeeCategoryID=:tawasulFinanceFeeCategoryID, fee=:fee, sequenceNumber=:count";
                                                    }
                                                    $resultInvoiceFee = $connection2->prepare($sqlInvoiceFee);
                                                    $resultInvoiceFee->execute($dataInvoiceFee);
                                                } catch (PDOException $e) {
                                                    ++$invoiceFeeFailCount;
                                                }
                                            }
                                        }
                                    }
                                }
                            } elseif ($resultInvoice->rowCount() == 1 and $thisInvoiceFailed == false) {
                                $rowInvoice = $resultInvoice->fetch();

                                //Add fees to invoice
                                $count = 0;
                                foreach ($fees as $fee) {
                                    ++$count;
                                    if (($invoiceTo == 'Company' and $companyAll == 'Y') or ($invoiceTo == 'Company' and $companyAll == 'N' and is_numeric(strpos($tawasulFinanceFeeCategoryIDList2, $fee['tawasulFinanceFeeCategoryID'])))) {
                                        try {
                                            if ($fee['feeType'] == 'Standard') {
                                                $dataInvoiceFee = array('tawasulFinanceInvoiceID' => $rowInvoice['tawasulFinanceInvoiceID'], 'feeType' => $fee['feeType'], 'tawasulFinanceFeeID' => $fee['tawasulFinanceFeeID'], 'count' => $count);
                                                $sqlInvoiceFee = "INSERT INTO tawasulFinanceInvoiceFee SET tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID, feeType=:feeType, tawasulFinanceFeeID=:tawasulFinanceFeeID, separated='N', sequenceNumber=:count";
                                            } else {
                                                $dataInvoiceFee = array('tawasulFinanceInvoiceID' => $rowInvoice['tawasulFinanceInvoiceID'], 'feeType' => $fee['feeType'], 'name' => $fee['name'], 'description' => $fee['description'], 'tawasulFinanceFeeCategoryID' => $fee['tawasulFinanceFeeCategoryID'], 'fee' => $fee['fee'], 'count' => $count);
                                                $sqlInvoiceFee = "INSERT INTO tawasulFinanceInvoiceFee SET tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID, feeType=:feeType, name=:name, description=:description, tawasulFinanceFeeCategoryID=:tawasulFinanceFeeCategoryID, fee=:fee, sequenceNumber=:count";
                                            }
                                            $resultInvoiceFee = $connection2->prepare($sqlInvoiceFee);
                                            $resultInvoiceFee->execute($dataInvoiceFee);
                                        } catch (PDOException $e) {
                                            ++$invoiceFeeFailCount;
                                        }
                                    }
                                }

                                //Update invoice
                                try {
                                    if ($scheduling == 'Scheduled') {
                                        $dataInvoiceAdd = array('tawasulPersonIDUpdate' => $session->get('tawasulPersonID'), 'notes' => $rowInvoice['notes'].' '.$notes, 'tawasulFinanceInvoiceID' => $rowInvoice['tawasulFinanceInvoiceID']);
                                        $sqlInvoiceAdd = "UPDATE tawasulFinanceInvoice SET tawasulPersonIDUpdate=:tawasulPersonIDUpdate, notes=:notes, timeStampUpdate='".date('Y-m-d H:i:s')."' WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID";
                                    } else {
                                        $dataInvoiceAdd = array('invoiceDueDate' => $invoiceDueDate, 'tawasulPersonIDUpdate' => $session->get('tawasulPersonID'), 'notes' => $rowInvoice['notes'].' '.$notes, 'tawasulFinanceInvoiceID' => $rowInvoice['tawasulFinanceInvoiceID']);
                                        $sqlInvoiceAdd = "UPDATE tawasulFinanceInvoice SET invoiceDueDate=:invoiceDueDate, tawasulPersonIDUpdate=:tawasulPersonIDUpdate, notes=:notes, timeStampUpdate='".date('Y-m-d H:i:s')."' WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID";
                                    }
                                    $resultInvoiceAdd = $connection2->prepare($sqlInvoiceAdd);
                                    $resultInvoiceAdd->execute($dataInvoiceAdd);
                                } catch (PDOException $e) {
                                    ++$invoiceFailCount;
                                    $thisInvoiceFailed = true;
                                }
                                $AI = $rowInvoice['tawasulFinanceInvoiceID'];
                            } else {
                                if ($thisInvoiceFailed == false) {
                                    ++$invoiceFailCount;
                                    $thisInvoiceFailed = true;
                                }
                            }
                        }
                    }

                    $tawasulFinanceInvoiceID = NULL;
                    if (isset($rowInvoice['tawasulFinanceInvoiceID'])) {
                        $tawasulFinanceInvoiceID = $rowInvoice['tawasulFinanceInvoiceID'];
                    } else if (isset($AI)) {
                        $tawasulFinanceInvoiceID = $AI;
                    }

                    //SET tawasulFinanceFeeCategoryIDList WITH ALL FEES (doing this now due to the complex nature of adding fees above)
                    $dataTemp = array('tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                    $sqlTemp = 'SELECT tawasulFinanceFeeCategoryID FROM tawasulFinanceInvoiceFee WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
                    $resultTemp = $connection2->prepare($sqlTemp);
                    $resultTemp->execute($dataTemp);

                    $tawasulFinanceFeeCategoryIDList = '';
                    while ($rowTemp = $resultTemp->fetch()) {
                        $tawasulFinanceFeeCategoryIDList .= $rowTemp['tawasulFinanceFeeCategoryID'].",";
                    }

                    $tawasulFinanceFeeCategoryIDList = substr($tawasulFinanceFeeCategoryIDList, 0, -1);
                    if ($tawasulFinanceFeeCategoryIDList != '') {
                        $dataTemp2 = array('tawasulFinanceFeeCategoryIDList' => $tawasulFinanceFeeCategoryIDList, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
                        $sqlTemp2 = 'UPDATE tawasulFinanceInvoice SET tawasulFinanceFeeCategoryIDList=:tawasulFinanceFeeCategoryIDList WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
                        $resultTemp2 = $connection2->prepare($sqlTemp2);
                        $resultTemp2->execute($dataTemp2);
                    }
                }

                //Return results, include three types of fail and counts
                if ($studentFailCount != 0 or $invoiceFailCount != 0 or $invoiceFeeFailCount != 0) {
                    $URL .= "&return=error3&studentFailCount=$studentFailCount&invoiceFailCount=$invoiceFailCount&invoiceFeeFailCount=$invoiceFeeFailCount";
                    header("Location: {$URL}");
                } else {
                    $URL .= '&return=success0';
                    header("Location: {$URL}&editID={$tawasulFinanceInvoiceID}");
                }
            }
        }
    }
}
