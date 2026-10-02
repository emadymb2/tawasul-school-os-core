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

use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Finance\InvoiceGateway;
use TawasulOS\Domain\Students\StudentGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        $entryCount = 0;

        $search = $_REQUEST['search'] ?? '';
        $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');

        $page->breadcrumbs->add(__('View Invoices'));
        $page->navigator->addSchoolYearNavigation($tawasulSchoolYearID, ['search' => $search]);

        // Online payment
        $settingGateway = $container->get(SettingGateway::class);
        $studentGateway = $container->get(StudentGateway::class);
        $enablePayments = $settingGateway->getSettingByScope('System', 'enablePayments');
        $paymentGateway = $settingGateway->getSettingByScope('System', 'paymentGateway');     

        if ($highestAction == "View Invoices_myChildren") {
            // Get children for this adult
            $children = $container->get(StudentGateway::class)->selectActiveStudentsByFamilyAdult($tawasulSchoolYearID, $session->get('tawasulPersonID'))->fetchGroupedUnique();
            
            if (empty($children)) {
                echo $page->getBlankSlate();
            } elseif (count($children) == 1) {
                $tawasulPersonID = array_key_first($children);
                $student = $children[$tawasulPersonID] ?? [];
            } else {
                // Get child list
                $count = 0;
                $options = [];

                foreach($children as $child) {
                    $options[$child['tawasulPersonID']] = Format::name('', $child['preferredName'], $child['surname'], 'Student', true);
                    ++$count;
                }

                if (count($options) == 0) {
                    echo $page->getBlankSlate();
                } elseif (count($options) == 1) {
                    $_GET['search'] = key($options);
                } else {
                    echo '<h2>';
                    echo 'Choose Student';
                    echo '</h2>';
                  
                    $form = Form::create('filter', $session->get('absoluteURL') . '/index.php', 'get');
                    $form->setClass('noIntBorder fullWidth');
                    $form->setTitle(__('Choose Student'));
                    
                    $form->addHiddenValue('q', '/modules/TawasulFinance/invoices_view.php');
                    $form->addHiddenValue('address', $session->get('address'));
                    $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

                    $row = $form->addRow();
                    $row->addLabel('search', __('Student'));
                    $row->addSelect('search')
                        ->fromArray(Format::nameListArray($children, 'Student'))
                        ->selected($search)
                        ->placeholder();

                    $row = $form->addRow();
                    $row->addSearchSubmit($session);

                    echo $form->getOutput();

                    $tawasulPersonID = $search;

                    if (empty($children[$tawasulPersonID])) {
                        $page->addError(__('You do not have access to this action.'));
                        return;
                    }
                    
                    $student = $children[$tawasulPersonID] ?? '';
                }
            }

        } else if ($highestAction == 'View Invoices_mine') {
            $tawasulPersonID = $session->get("tawasulPersonID");
            $student = $studentGateway->selectActiveStudentByPerson($tawasulSchoolYearID, $tawasulPersonID)->fetch();
        }

        if (!empty($tawasulPersonID) && !empty($tawasulSchoolYearID)) {

            if (empty($student)) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                return;
            }

            $invoices = $container->get(InvoiceGateway::class)->selectInvoicesByPersonID($tawasulSchoolYearID, $tawasulPersonID)->fetchAll();

            if (empty($invoices)) {
                echo $page->getBlankSlate();
                return;
            } 
            // Data Table
            $table = DataTable::create('invoices');
            $table->setTitle(__('View'));
            $table->setDescription(__(sprintf(__('%1$s invoice(s) in current view'), count($invoices))));

            // Modify rows based on status
            $table->modifyRows(function ($invoice, $row) {
                if ($invoice['status'] == 'Issued' && $invoice['invoiceDueDate'] < date('Y-m-d')) $row->addClass('error');
                else if ($invoice['status'] == 'Paid') $row->addClass('current');
                return $row;
            });

            $table->addMetaData('post', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);

            // COLUMNS
            $table->addExpandableColumn('notes');

            $table->addColumn('student', __('Student'))
                ->description(__('Invoice To'))
                ->format(function ($invoice) {
                    $output = '<b>' . Format::name('', $invoice['preferredName'], $invoice['surname'], 'Student', true) . '</b>';
                    $output .= '<br/><span class="text-xs italic">' . __($invoice['invoiceTo']) . '</span>';
                    return $output;
                });

            $table->addColumn('formGroup', __('Form Group'));

            $table->addColumn('status', __('Status'))
                ->format(function ($invoice) {
                    if ($invoice['status'] == 'Issued' && $invoice['invoiceDueDate'] < date('Y-m-d')) {
                        return __('Issued - Overdue');
                    } else if ($invoice['status'] == 'Paid' && $invoice['invoiceDueDate'] < $invoice['paidDate']) {
                        return __('Paid - Late');
                    }
                    return __($invoice['status']);
                });

            $table->addColumn('billingSchedule', __('Schedule'))
                ->format(function ($invoice) {
                    if (!empty($invoice['billingScheduleExtra'])) {
                        return __($invoice['billingScheduleExtra']);
                    }
                    return __($invoice['billingSchedule']);
                });

            $totalFee = 0;
            $table->addColumn('total', __('Total') . ' <small><i>(' . $session->get('currency') . ')</i></small>')
                ->description(__('Paid') . ' (' . $session->get('currency') . ')')
                ->notSortable()
                ->format(function ($invoice) use ($pdo) {
                    $totalFee = getInvoiceTotalFee($pdo, $invoice['tawasulFinanceInvoiceID'], $invoice['status']);
                    if (is_null($totalFee)) return '';

                    $output = Format::currency($totalFee);
                    if (!empty($invoice['paidAmount'])) {
                        $class = Format::number($invoice['paidAmount']) != Format::number($totalFee) ? 'textOverBudget' : '';
                        $output .= '<br/><span class="text-xs italic ' . $class . '">' . Format::currency($invoice['paidAmount']) . '</span>';
                    }
                    return $output;
                });

            $table->addColumn('invoiceIssueDate', __('Issue Date'))
                ->description(__('Due Date'))
                ->format(function ($invoice) {
                    $output = !is_null($invoice['invoiceIssueDate']) ? Format::date($invoice['invoiceIssueDate']) : __('N/A');
                    $output .= '<br/><span class="text-xs italic">' . Format::date($invoice['invoiceDueDate']) . '</span>';
                    return $output;
                });

            // ACTIONS
            $table->addActionColumn()
                ->format(function ($invoice, $actions) use ($enablePayments, $settingGateway, $totalFee) {
                    
                    if ($enablePayments == 'Y' and $invoice['status'] != 'Paid' && $invoice['status'] != 'Cancelled' && $invoice['status'] != 'Refunded') {
                        $financeOnlinePaymentEnabled = $settingGateway->getSettingByScope('Finance', 'financeOnlinePaymentEnabled');
                        $financeOnlinePaymentThreshold = $settingGateway->getSettingByScope('Finance', 'financeOnlinePaymentThreshold');

                        if ($financeOnlinePaymentEnabled == 'Y' && (empty($financeOnlinePaymentThreshold) || $financeOnlinePaymentThreshold >= $totalFee) && $totalFee > 0 && !empty($invoice['key'])) {
                            $actions->addAction('pay', __('Pay Now'))
                                ->setURL('/modules/TawasulFinance/invoices_payOnline.php')
                                ->setIcon('pay')
                                ->addParam('tawasulFinanceInvoiceID', $invoice['tawasulFinanceInvoiceID'])
                                ->addParam('key', $invoice['key']);
                        }
                    }

                    if (in_array($invoice['status'], ['Issued', 'Paid', 'Paid - Partial'])) {
                        $type = $invoice['status'] == 'Issued' ? 'invoice' : 'receipt';
                        $actions->addAction('print', __('Print'))
                            ->setURL('/report.php')
                            ->addParam('q', '/modules/TawasulFinance/invoices_view_print.php')
                            ->addParam('type', $type)
                            ->addParam('tawasulFinanceInvoiceID', $invoice['tawasulFinanceInvoiceID'])
                            ->addParam('tawasulSchoolYearID', $invoice['tawasulSchoolYearID'])
                            ->addParam('tawasulPersonID', $invoice['tawasulPersonID'])
                            ->setIcon('print')
                            ->directLink()
                            ->setTarget('_blank')
                            ->displayLabel();
                    }
                });

            echo $table->render($invoices);
        }
    }
}
