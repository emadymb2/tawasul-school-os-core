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

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Domain\System\SettingGateway;
use Tos\Module\TawasulFinance\Forms\FinanceFormFactory;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $tawasulFinanceInvoiceID = $_GET['tawasulFinanceInvoiceID'] ?? '';
    $status = $_GET['status'] ?? '';
    $tawasulFinanceInvoiceeID = $_GET['tawasulFinanceInvoiceeID'] ?? '';
    $monthOfIssue = $_GET['monthOfIssue'] ?? '';
    $tawasulFinanceBillingScheduleID = $_GET['tawasulFinanceBillingScheduleID'] ?? '';
    $tawasulFinanceFeeCategoryID = $_GET['tawasulFinanceFeeCategoryID'] ?? '';

    $urlParams = compact('tawasulSchoolYearID', 'status', 'tawasulFinanceInvoiceeID', 'monthOfIssue', 'tawasulFinanceBillingScheduleID', 'tawasulFinanceFeeCategoryID');

    //Proceed!
    $page->breadcrumbs
        ->add(__('Manage Invoices'), 'invoices_manage.php', $urlParams)
        ->add(__('Edit Invoice'));

    $page->return->addReturns(['success1' => __('Your request was completed successfully, but one or more requested emails could not be sent.'), 'error3' => __('Some elements of your request failed, but others were successful.')]);

    if ($tawasulFinanceInvoiceID == '' or $tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
            $sql = "SELECT tawasulFinanceInvoice.*, companyName, companyContact, companyEmail, companyCCFamily, tawasulSchoolYear.name as schoolYear, tawasulPerson.surname, tawasulPerson.preferredName, tawasulFinanceBillingSchedule.name as billingScheduleName
                    FROM tawasulFinanceInvoice
                    JOIN tawasulSchoolYear ON (tawasulSchoolYear.tawasulSchoolYearID=tawasulFinanceInvoice.tawasulSchoolYearID)
                    LEFT JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoice.tawasulFinanceInvoiceeID=tawasulFinanceInvoicee.tawasulFinanceInvoiceeID)
                    LEFT JOIN tawasulFinanceBillingSchedule ON (tawasulFinanceBillingSchedule.tawasulFinanceBillingScheduleID=tawasulFinanceInvoice.tawasulFinanceBillingScheduleID)
                    LEFT JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulFinanceInvoicee.tawasulPersonID)
                    WHERE tawasulFinanceInvoice.tawasulSchoolYearID=:tawasulSchoolYearID
                    AND tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            if ($status != '' or $tawasulFinanceInvoiceeID != '' or $monthOfIssue != '' or $tawasulFinanceBillingScheduleID != '') {
                $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulFinance', 'invoices_manage.php')->withQueryParams($urlParams));
            }

            $form = Form::create('invoice', $session->get('absoluteURL').'/modules/'.$session->get('module').'/invoices_manage_editProcess.php?'.http_build_query($urlParams));
            $form->setFactory(FinanceFormFactory::create($pdo));

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulFinanceInvoiceID', $tawasulFinanceInvoiceID);
            $form->addHiddenValue('billingScheduleType', $values['billingScheduleType']);

            $form->addRow()->addHeading('Basic Information', __('Basic Information'));

            $row = $form->addRow();
                $row->addLabel('schoolYear', __('School Year'));
                $row->addTextField('schoolYear')->required()->readonly();

            $row = $form->addRow();
                $row->addLabel('personName', __('Invoicee'));
                $row->addTextField('personName')->required()->readonly()->setValue(Format::name('', $values['preferredName'], $values['surname'], 'Student', true));

            $row = $form->addRow();
                $row->addLabel('billingScheduleTypeText', __('Scheduling'));
                $row->addTextField('billingScheduleTypeText')->required()->readonly()->setValue(__($values['billingScheduleType']));

            if ($values['billingScheduleType'] == 'Scheduled') {
                $row = $form->addRow();
                    $row->addLabel('billingScheduleName', __('Billing Schedule'));
                    $row->addTextField('billingScheduleName')->required()->readonly();
            } else {
                if ($values['status'] == 'Pending' || $values['status'] == 'Issued') {
                    $row = $form->addRow();
                        $row->addLabel('invoiceDueDate', __('Invoice Due Date'));
                        $row->addDate('invoiceDueDate')->required();
                } else {
                    $row = $form->addRow();
                        $row->addLabel('invoiceDueDate', __('Invoice Due Date'));
                        $row->addDate('invoiceDueDate')->required()->readonly();
                }
            }

            if ($values['status'] == 'Pending') {
                $form->addHiddenValue('status', $values['status']);

                $row = $form->addRow();
                    $row->addLabel('statusText', __('Status'))
                        ->description(__('This value cannot be changed. Use the Issue function to change the status from "Pending" to "Issued".'));
                    $row->addTextField('statusText')->required()->readonly()->setValue(__($values['status']));
            } else {
                $row = $form->addRow();
                    $row->addLabel('status', __('Status'))
                        ->description(__('Available options are limited according to current status.'));
                    $row->addSelectInvoiceStatus('status', $values['status'])->required();
            }

            // PAYMENT INFO
            if ($values['status'] == 'Issued' or $values['status'] == 'Paid - Partial') {
                $form->toggleVisibilityByClass('paymentInfo')->onSelect('status')->when(array('Paid', 'Paid - Partial', 'Paid - Complete'));

                $row = $form->addRow()->addClass('paymentInfo');
                    $row->addLabel('paymentType', __('Payment Type'));
                    $row->addSelectPaymentMethod('paymentType')->required();

                $row = $form->addRow()->addClass('paymentInfo');
                    $row->addLabel('paymentTransactionID', __('Transaction ID'))->description(__('Transaction ID to identify this payment.'));
                    $row->addTextField('paymentTransactionID')->maxLength(50);

                $row = $form->addRow()->addClass('paymentInfo');
                    $row->addLabel('paidDate', __('Date Paid'))->description(__('Date of payment, not entry to system.'));
                    $row->addDate('paidDate')->required();

                $remainingFee = getInvoiceTotalFee($pdo, $tawasulFinanceInvoiceID, $values['status']);
                if ($values['status'] == 'Paid - Partial') {
                    $alreadyPaid = getAmountPaid($connection2, $guid, 'tawasulFinanceInvoice', $tawasulFinanceInvoiceID);
                    $remainingFee -= $alreadyPaid;
                }

                $row = $form->addRow()->addClass('paymentInfo');
                    $row->addLabel('paidAmount', __('Amount Paid'))->description(__('Amount in current payment.'));
                    $row->addCurrency('paidAmount')->maxLength(14)->required()->setValue(number_format($remainingFee, 2, '.', ''));

                unset($values['paidDate']);
                unset($values['paidAmount']);
            }

            $row = $form->addRow();
                $row->addLabel('notes', __('Notes'))->description(__('Notes will be displayed on the final invoice and receipt.'));
                $row->addTextArea('notes')->setRows(5);

            // FEES
            $form->addRow()->addHeading('Fees', __('Fees'));

            // Ad Hoc OR Issued (Fixed Fees)
            $dataFees = array('tawasulFinanceInvoiceID' => $values['tawasulFinanceInvoiceID']);
            $sqlFees = "SELECT tawasulFinanceInvoiceFee.tawasulFinanceInvoiceFeeID, tawasulFinanceInvoiceFee.feeType, tawasulFinanceFeeCategory.name AS category, tawasulFinanceInvoiceFee.name AS name, tawasulFinanceInvoiceFee.fee, tawasulFinanceInvoiceFee.description AS description, NULL AS tawasulFinanceFeeID, tawasulFinanceInvoiceFee.tawasulFinanceFeeCategoryID AS tawasulFinanceFeeCategoryID, sequenceNumber FROM tawasulFinanceInvoiceFee JOIN tawasulFinanceFeeCategory ON (tawasulFinanceInvoiceFee.tawasulFinanceFeeCategoryID=tawasulFinanceFeeCategory.tawasulFinanceFeeCategoryID) WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID";

            // Union with Standard (Flexible Fees)
            if ($values['status'] == 'Pending') {
                $sqlFees = "(".$sqlFees." AND feeType='Ad Hoc')";
                $sqlFees .= " UNION ";
                $sqlFees .= "(SELECT tawasulFinanceInvoiceFee.tawasulFinanceInvoiceFeeID, tawasulFinanceInvoiceFee.feeType, tawasulFinanceFeeCategory.name AS category, tawasulFinanceFee.name AS name, tawasulFinanceFee.fee AS fee, tawasulFinanceFee.description AS description, tawasulFinanceInvoiceFee.tawasulFinanceFeeID AS tawasulFinanceFeeID, tawasulFinanceFeeCategory.tawasulFinanceFeeCategoryID AS tawasulFinanceFeeCategoryID, sequenceNumber FROM tawasulFinanceInvoiceFee JOIN tawasulFinanceFee ON (tawasulFinanceInvoiceFee.tawasulFinanceFeeID=tawasulFinanceFee.tawasulFinanceFeeID) JOIN tawasulFinanceFeeCategory ON (tawasulFinanceFee.tawasulFinanceFeeCategoryID=tawasulFinanceFeeCategory.tawasulFinanceFeeCategoryID) WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID AND feeType='Standard')";
            }

            $sqlFees .= " ORDER BY sequenceNumber";
            $resultFees = $pdo->executeQuery($dataFees, $sqlFees);

            // CUSTOM BLOCKS
            if ($values['status'] == 'Pending') {
                // Fee selector
                $feeSelector = $form->getFactory()->createSelectFee('addNewFee', $tawasulSchoolYearID)->addClass('addBlock');

                // Block template
                $blockTemplate = $form->getFactory()->createTable()->setClass('blank');
                $row = $blockTemplate->addRow();
                    $row->addTextField('name')->setClass('standardWidth floatLeft noMargin title')->required()->placeholder(__('Fee Name'));

                $col = $blockTemplate->addRow()->addColumn()->addClass('inline');
                    $col->addSelectFeeCategory('tawasulFinanceFeeCategoryID')
                        ->setClass('shortWidth floatLeft noMargin');

                    $col->addCurrency('fee')
                        ->setClass('shortWidth floatLeft')
                        ->required()
                        ->placeholder(__('Value').(!empty($session->get('currency'))? ' ('.$session->get('currency').')' : ''));

                $col = $blockTemplate->addRow()->addClass('showHide w-full')->addColumn();
                    $col->addLabel('description', __('Description'));
                    $col->addTextArea('description')->setRows('auto')->setClass('w-full floatNone noMargin');

                // Custom Blocks for Fees
                $row = $form->addRow();
                    $customBlocks = $row->addCustomBlocks('feesBlock', $session)
                        ->fromTemplate($blockTemplate)
                        ->settings([
                            'inputNameStrategy' => 'string',
                            'addOnEvent'        => 'change',
                            'sortable'          => true,
                            'uniqueID'          => 'tawasulFinanceInvoiceFeeID',
                            'hiddenInputs'      => 'tawasulFinanceFeeID,feeType',
                            ])
                        ->placeholder(__('Fees will be listed here...'))
                        ->addToolInput($feeSelector)
                        ->addBlockButton('showHide', __('Show/Hide'), 'plus.png');

                // Add existing blocks
                while ($fee = $resultFees->fetch()) {
                    $fee['readonly'] = ($fee['feeType'] == 'Standard')? array('name', 'fee', 'description', 'tawasulFinanceFeeCategoryID') : array('name', 'fee', 'tawasulFinanceFeeCategoryID');
                    $fee['tawasulFinanceInvoiceFeeID'] = str_pad($fee['tawasulFinanceInvoiceFeeID'], 15, '0', STR_PAD_LEFT);
                    $fee['tawasulFinanceFeeCategoryID'] = str_pad($fee['tawasulFinanceFeeCategoryID'], 4, '0', STR_PAD_LEFT);

                    $customBlocks->addBlock($fee['tawasulFinanceInvoiceFeeID'], $fee);
                }

                // Add predefined block data (for templating new blocks, triggered with the feeSelector)
                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
                $sql = "SELECT tawasulFinanceFeeID as groupBy, tawasulFinanceFeeID, name, description, fee, tawasulFinanceFeeCategoryID FROM tawasulFinanceFee WHERE tawasulSchoolYearID=:tawasulSchoolYearID  ORDER BY name";
                $result = $pdo->executeQuery($data, $sql);
                $feeData = $result->rowCount() > 0? $result->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE) : array();

                $customBlocks->addPredefinedBlock('Ad Hoc Fee', array('feeType' => 'Ad Hoc', 'tawasulFinanceFeeID' => 0));
                foreach ($feeData as $tawasulFinanceFeeID => $data) {
                    $customBlocks->addPredefinedBlock($tawasulFinanceFeeID, $data + array('feeType' => 'Standard', 'readonly' => ['name', 'fee', 'description', 'tawasulFinanceFeeCategoryID']) );
                }
            } else {
                // Display fees already issued (readonly)
                if ($resultFees->rowCount() == 0) {
                    $form->addRow()->addAlert(__('There are no records to display.'), 'error');
                } else {
                    $table = $form->addRow()->addTable()->addClass('colorOddEven');

                    $header = $table->addHeaderRow();
                        $header->addContent(__('Name'));
                        $header->addContent(__('Category'));
                        $header->addContent(__('Description'));
                        $header->addContent(__('Fee'))->append(' <small><i>('.$session->get('currency').')</i></small>');

                    $feeTotal = 0;
                    while ($fee = $resultFees->fetch()) {
                        $feeTotal += $fee['fee'];
                        $row = $table->addRow();
                            $row->addContent($fee['name']);
                            $row->addContent($fee['category']);
                            $row->addContent($fee['description']);
                            $row->addContent(number_format($fee['fee'], 2, '.', ','))->prepend(substr($session->get('currency'), 4).' ');
                    }

                    $row = $table->addRow()->addClass('current');
                        $row->addTableCell(__('Invoice Total:'))->colspan(3)->wrap('<b class="floatRight">', '</b>');
                        $row->addTableCell(number_format($feeTotal, 2, '.', ','))->prepend(substr($session->get('currency'), 4).' ')->wrap('<b>', '</b>');
                }
            }

            $form->addRow()->addHeading('Payment Log', __('Payment Log'));

            $form->addRow()->addContent(getPaymentLog($connection2, $guid, 'tawasulFinanceInvoice', $tawasulFinanceInvoiceID, null, $feeTotal ?? ''));

            $settingGateway = $container->get(SettingGateway::class);

            // EMAIL RECEIPTS
            if ($values['status'] == 'Issued' || $values['status'] == 'Paid - Partial') {
                $form->toggleVisibilityByClass('emailReceipts')->onSelect('status')->when(array('Paid', 'Paid - Partial', 'Paid - Complete'));
                $form->addRow()->addHeading('Email Receipt', __('Email Receipt'))->addClass('emailReceipts');

                $row = $form->addRow()->addClass('emailReceipts');
                    $row->addYesNoRadio('emailReceipt')->checked('Y');

                $form->toggleVisibilityByClass('emailReceiptsTable')->onRadio('emailReceipt')->when(array('Y'));

                $email = $settingGateway->getSettingByScope('Finance', 'email');
                $form->addHiddenValue('email', $email);
                if (empty($email)) {
                    $row = $form->addRow()->addClass('emailReceipts emailReceiptsTable');
                    $row->addAlert(__('An outgoing email address has not been set up under Invoice & Receipt Settings, and so no emails can be sent.'), 'error');
                } else {
                    $row = $form->addRow()->addClass('emailReceipts emailReceiptsTable');
                    $row->addInvoiceEmailCheckboxes('emails[]', 'names[]', $values, $session);
                }
            }

            // EMAIL REMINDERS
            if ($values['status'] == 'Issued' && $values['invoiceDueDate'] < date('Y-m-d')) {

                $form->toggleVisibilityByClass('emailReminders')->onSelect('status')->when(array('Issued'));
                $form->addRow()->addHeading(sprintf(__('Email Reminder %1$s'), ($values['reminderCount'])+1))->addClass('emailReminders');

                $row = $form->addRow()->addClass('emailReminders');
                    $row->addYesNoRadio('emailReminder')->checked('Y');

                $form->toggleVisibilityByClass('emailRemindersTable')->onRadio('emailReminder')->when(array('Y'));

                $email = $settingGateway->getSettingByScope('Finance', 'email');
                $form->addHiddenValue('email', $email);
                if (empty($email)) {
                    $row = $form->addRow()->addClass('emailReminders emailRemindersTable');
                    $row->addAlert(__('An outgoing email address has not been set up under Invoice & Receipt Settings, and so no emails can be sent.'), 'error');
                } else {
                    $row = $form->addRow()->addClass('emailReminders emailRemindersTable');
                    $row->addInvoiceEmailCheckboxes('emails[]', 'names[]', $values, $session);
                }
            }

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            $form->loadAllValuesFrom($values);

            echo $form->getOutput();
        }
    }
}
