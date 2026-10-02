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
use Tos\Module\TawasulFinance\Forms\FinanceFormFactory;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $status = $_GET['status'] ?? '';
    $tawasulFinanceInvoiceeID = $_GET['tawasulFinanceInvoiceeID'] ?? '';
    $monthOfIssue = $_GET['monthOfIssue'] ?? '';
    $tawasulFinanceBillingScheduleID = $_GET['tawasulFinanceBillingScheduleID'] ?? '';
    $tawasulFinanceFeeCategoryID = $_GET['tawasulFinanceFeeCategoryID'] ?? '';

    $urlParams = compact('tawasulSchoolYearID', 'status', 'tawasulFinanceInvoiceeID', 'monthOfIssue', 'tawasulFinanceBillingScheduleID', 'tawasulFinanceFeeCategoryID');

    //Proceed!
    $page->breadcrumbs
        ->add(__('Manage Invoices'), 'invoices_manage.php', $urlParams)
        ->add(__('Add Fees & Invoices'));

    $error3 = __('Some aspects of your update failed, effecting the following areas:').'<ul>';
    if (!empty($_GET['studentFailCount'])) {
        $error3 .= '<li>'.$_GET['studentFailCount'].' '.__('students encountered problems.').'</li>';
    }
    if (!empty($_GET['invoiceFailCount'])) {
        $error3 .= '<li>'.$_GET['invoiceFailCount'].' '.__('invoices encountered problems.').'</li>';
    }
    if (!empty($_GET['invoiceFeeFailCount'])) {
        $error3 .= '<li>'.$_GET['invoiceFeeFailCount'].' '.__('fee entries encountered problems.').'</li>';
    }
    $error3 .= '</ul>'.__('It is recommended that you remove all pending invoices and try to recreate them.');

    $page->return->addReturns(['error3' => $error3]);

    echo '<p>';
    echo __('Here you can add fees to one or more students. These fees will be added to an existing invoice or used to form a new invoice, depending on the specified billing schedule and other details.');
    echo '</p>';

    if ($tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $data= array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT name AS schoolYear FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID";
        $result = $pdo->executeQuery($data, $sql);
        $schoolYearName = $result->rowCount() > 0? $result->fetchColumn(0) : '';

        if ($status != '' or $tawasulFinanceInvoiceeID != '' or $monthOfIssue != '' or $tawasulFinanceBillingScheduleID != '') {
            $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulFinance', 'invoices_manage.php')->withQueryParams($urlParams));
        }

        $form = Form::create('invoice', $session->get('absoluteURL').'/modules/'.$session->get('module').'/invoices_manage_addProcess.php?'.http_build_query($urlParams));
        $form->setFactory(FinanceFormFactory::create($pdo));

        $form->addHiddenValue('address', $session->get('address'));

        $form->addRow()->addHeading('Basic Information', __('Basic Information'));

        $row = $form->addRow();
            $row->addLabel('schoolYear', __('School Year'));
            $row->addTextField('schoolYear')->required()->readonly()->setValue($schoolYearName);

        $row = $form->addRow();
            $row->addLabel('tawasulFinanceInvoiceeIDs', __('Invoicees'))->append(sprintf(__('Visit %1$sManage Invoicees%2$s to automatically generate missing students.'), "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/TawasulFinance/invoicees_manage.php'>", '</a>'));
            $row->addSelectInvoicee('tawasulFinanceInvoiceeIDs', $tawasulSchoolYearID, ["byClass" => true])->required()->selectMultiple();

        $scheduling = array('Scheduled' => __('Scheduled'), 'Ad Hoc' => __('Ad Hoc'));
        $row = $form->addRow();
            $row->addLabel('scheduling', __('Scheduling'))->description(__('When using scheduled, invoice due date is linked to and determined by the schedule.'));
            $row->addRadio('scheduling')->fromArray($scheduling)->required()->inline()->checked('Scheduled');

        $form->toggleVisibilityByClass('schedulingScheduled')->onRadio('scheduling')->when('Scheduled');
        $form->toggleVisibilityByClass('schedulingAdHoc')->onRadio('scheduling')->when('Ad Hoc');

        $row = $form->addRow()->addClass('schedulingScheduled');
            $row->addLabel('tawasulFinanceBillingScheduleID', __('Billing Schedule'));
            $row->addSelectBillingSchedule('tawasulFinanceBillingScheduleID', $tawasulSchoolYearID)->required()->selected($tawasulFinanceBillingScheduleID);

        $row = $form->addRow()->addClass('schedulingAdHoc');
            $row->addLabel('invoiceDueDate', __('Invoice Due Date'))->description(__('For fees added to existing invoice, specified date will override existing due date.'));
            $row->addDate('invoiceDueDate')->required();

        $row = $form->addRow();
            $row->addLabel('notes', __('Notes'))->description(__('Notes will be displayed on the final invoice and receipt.'));
            $row->addTextArea('notes')->setRows(5);

        $form->addRow()->addHeading('Fees', __('Fees'));

        // CUSTOM BLOCKS

        // Fee selector
        $feeSelector = $form->getFactory()->createSelectFee('addNewFee', $tawasulSchoolYearID)->addClass('addBlock');

        // Block template
        $blockTemplate = $form->getFactory()->createTable()->setClass('blank');
            $row = $blockTemplate->addRow();
                $row->addTextField('name')->setClass('w-full pr-10 title')->required()->placeholder(__('Fee Name'));

            $col = $blockTemplate->addRow()->addColumn()->addClass('flex mt-1');
                $col->addSelectFeeCategory('tawasulFinanceFeeCategoryID')
                    ->setClass('w-48 m-0');

                $col->addCurrency('fee')
                    ->setClass('w-48 ml-1')
                    ->required()
                    ->placeholder(__('Value').(!empty($session->get('currency'))? ' ('.$session->get('currency').')' : ''));

            $col = $blockTemplate->addRow()->addClass('showHide w-full')->addColumn();
                $col->addLabel('description', __('Description'));
                $col->addTextArea('description')->setRows('auto')->setClass('w-full float-none m-0');

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

        // Add predefined block data (for templating new blocks, triggered with the feeSelector)
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT tawasulFinanceFeeID as groupBy, tawasulFinanceFeeID, name, description, fee, tawasulFinanceFeeCategoryID FROM tawasulFinanceFee WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY name";
        $result = $pdo->executeQuery($data, $sql);
        $feeData = $result->rowCount() > 0? $result->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE) : array();

        $customBlocks->addPredefinedBlock('Ad Hoc Fee', array('feeType' => 'Ad Hoc', 'tawasulFinanceFeeID' => 0));
        foreach ($feeData as $tawasulFinanceFeeID => $data) {
            $customBlocks->addPredefinedBlock($tawasulFinanceFeeID, $data + array('feeType' => 'Standard', 'readonly' => ['name', 'fee', 'description', 'tawasulFinanceFeeCategoryID']) );
        }

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        echo $form->getOutput();
    }
}
