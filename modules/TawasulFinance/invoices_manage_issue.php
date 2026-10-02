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

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_manage_issue.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $tawasulFinanceInvoiceID = $_GET['tawasulFinanceInvoiceID'] ?? '';
    $status = $_GET['status'] ?? '';
    $tawasulFinanceInvoiceeID = $_GET['tawasulFinanceInvoiceeID'] ?? '';
    $monthOfIssue = $_GET['monthOfIssue'] ?? '';
    $tawasulFinanceBillingScheduleID = $_GET['tawasulFinanceBillingScheduleID'] ?? '';
    $tawasulFinanceFeeCategoryID = $_GET['tawasulFinanceFeeCategoryID'] ?? '';

    $urlParams = compact('tawasulSchoolYearID', 'status', 'tawasulFinanceInvoiceeID', 'monthOfIssue', 'tawasulFinanceBillingScheduleID', 'tawasulFinanceFeeCategoryID');

    $page->breadcrumbs
        ->add(__('Manage Invoices'), 'invoices_manage.php', $urlParams)
        ->add(__('Issue Invoice'));

    echo '<p>';
    echo __('Issuing an invoice confirms it in the system, meaning the financial details within the invoice can no longer be edited. On issue, you also have the choice to email the invoice to the appropriate family and company recipients.');
    echo '</p>';

    $page->return->addReturns(['error4' => 'Some aspects of your request failed, but others were successful. Because of the errors, the system did not attempt to send any requested emails.']);

    //Check if tawasulFinanceInvoiceID and tawasulSchoolYearID specified
    if ($tawasulFinanceInvoiceID == '' or $tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
            $sql = "SELECT tawasulFinanceInvoice.*, companyName, companyContact, companyEmail, companyCCFamily, tawasulSchoolYear.name as schoolYear, tawasulPerson.surname, tawasulPerson.preferredName, tawasulFinanceBillingSchedule.name as billingScheduleName, tawasulFinanceBillingSchedule.invoiceDueDate as billingScheduleInvoiceDueDate
					FROM tawasulFinanceInvoice
					JOIN tawasulSchoolYear ON (tawasulSchoolYear.tawasulSchoolYearID=tawasulFinanceInvoice.tawasulSchoolYearID)
					LEFT JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoice.tawasulFinanceInvoiceeID=tawasulFinanceInvoicee.tawasulFinanceInvoiceeID)
					LEFT JOIN tawasulFinanceBillingSchedule ON (tawasulFinanceBillingSchedule.tawasulFinanceBillingScheduleID=tawasulFinanceInvoice.tawasulFinanceBillingScheduleID)
					LEFT JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulFinanceInvoicee.tawasulPersonID)
					WHERE tawasulFinanceInvoice.tawasulSchoolYearID=:tawasulSchoolYearID
					AND tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID
					AND tawasulFinanceInvoice.status='Pending'";
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

			$form = Form::create('invoice', $session->get('absoluteURL').'/modules/'.$session->get('module').'/invoices_manage_issueProcess.php?'.http_build_query($urlParams));
			$form->setFactory(FinanceFormFactory::create($pdo));

			$form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulFinanceInvoiceID', $tawasulFinanceInvoiceID);

			$form->addRow()->addHeading('Basic Information', __('Basic Information'));

			$row = $form->addRow();
                $row->addLabel('schoolYear', __('School Year'));
				$row->addTextField('schoolYear')->required()->readonly();

			$row = $form->addRow();
                $row->addLabel('personName', __('Invoicee'));
                $row->addTextField('personName')->required()->readonly()->setValue(Format::name('', $values['preferredName'], $values['surname'], 'Student', true));

            $form->addHiddenValue('billingScheduleType', $values['billingScheduleType']);

            $row = $form->addRow();
                $row->addLabel('billingScheduleTypeText', __('Scheduling'));
				$row->addTextField('billingScheduleTypeText')->required()->readonly()->setValue(__($values['billingScheduleType']));

			if ($values['billingScheduleType'] == 'Scheduled') {
				$row = $form->addRow();
					$row->addLabel('billingScheduleName', __('Billing Schedule'));
					$row->addTextField('billingScheduleName')->required()->readonly();
					$form->addHiddenValue('invoiceDueDate', Format::date($values['billingScheduleInvoiceDueDate']));
			} else {
				$row = $form->addRow();
					$row->addLabel('invoiceDueDate', __('Invoice Due Date'));
					$row->addDate('invoiceDueDate')->required()->readonly();
			}

            $form->addHiddenValue('status', $values['status']);

			$row = $form->addRow();
				$row->addLabel('statusText', __('Status'));
				$row->addTextField('statusText')->required()->readonly()->setValue(__($values['status']));

			$row = $form->addRow();
                $row->addLabel('notes', __('Notes'))->description(__('Notes will be displayed on the final invoice and receipt.'));
				$row->addTextArea('notes')->setRows(5);

			$form->addRow()->addHeading('Fees', __('Fees'));

			$totalFee = getInvoiceTotalFee($pdo, $tawasulFinanceInvoiceID, $values['status']);
			$row = $form->addRow();
				$row->addLabel('totalFee', __('Total'))->description('<small><i>('.$session->get('currency').')</i></small>');
				$row->addTextField('totalFee')->required()->readonly()->setValue(number_format($totalFee, 2));

                        $form->addHiddenValue('invoiceTo', $values['invoiceTo']);

			$row = $form->addRow();
				$row->addLabel('invoiceToText', __('Invoice To'));
				$row->addTextField('invoiceToText')->required()->readonly()->setValue(__($values['invoiceTo']));

			$form->addRow()->addHeading('Email Invoice', __('Email Invoice'));

			$email = $container->get(SettingGateway::class)->getSettingByScope('Finance', 'email');
			$form->addHiddenValue('email', $email);
			if (empty($email)) {
				$form->addRow()->addAlert(__('An outgoing email address has not been set up under Invoice & Receipt Settings, and so no emails can be sent.'), 'error');
			} else {
				$form->addRow()->addInvoiceEmailCheckboxes('emails[]', 'names[]', $values, $session);
			}

			$row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            $form->loadAllValuesFrom($values);

            echo $form->getOutput();
        }
    }
}
