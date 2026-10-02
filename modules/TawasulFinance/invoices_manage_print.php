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

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_manage_print.php') == false) {
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

    //Proceed!
    $urlParams = compact('tawasulSchoolYearID', 'status', 'tawasulFinanceInvoiceeID', 'monthOfIssue', 'tawasulFinanceBillingScheduleID', 'tawasulFinanceFeeCategoryID'); 

    //Proceed!
    $page->breadcrumbs
        ->add(__('Manage Invoices'), 'invoices_manage.php', $urlParams)
        ->add(__('Print Invoices, Receipts & Reminders'));    

    //Check if tawasulFinanceInvoiceID and tawasulSchoolYearID specified
    if ($tawasulFinanceInvoiceID == '' or $tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID);
        $sql = 'SELECT * FROM tawasulFinanceInvoice WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID';
        $result = $connection2->prepare($sql);
        $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $row = $result->fetch();

            if ($status != '' or $tawasulFinanceInvoiceeID != '' or $monthOfIssue != '' or $tawasulFinanceBillingScheduleID != '') {
                $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulFinance', 'invoices_manage.php')->withQueryParams($urlParams));
            }

            if ($row['status'] == 'Pending') {
                echo "<div class='error'>";
                echo __('There is nothing to print, as the invoice has yet to be issued.');
                echo '</div>';
            } else {
                echo "<table cellspacing='0' style='width: 100%'>";
                echo "<tr class='head'>";
                echo '<th>';
                echo __('Item');
                echo '</th>';
                echo "<th style='width: 120px'>";
                echo __('Actions');
                echo '</th>';
                echo '</tr>';

                $count = 0;
                $rowNum = 'even';

                ?>
					<tr class='<?php echo $rowNum ?>'>
						<td>
							<b><?php echo __('Invoice') ?></b><br/>
						</td>
						<td class="left">
							<?php
                            echo "<a title='".__('Print')."' target='_blank' href='".$session->get('absoluteURL').'/report.php?q=/modules/'.$session->get('module').'/invoices_manage_print_print.php&type=invoice&tawasulFinanceInvoiceID='.$row['tawasulFinanceInvoiceID']."&tawasulSchoolYearID=$tawasulSchoolYearID'>".__('Print').icon('solid', 'print', 'size-5 text-gray-600')."</a>"; ?>
						</td>
					</tr>
					<?php
                    ++$count;
                if ($count % 2 == 0) {
                    $rowNum = 'even';
                } else {
                    $rowNum = 'odd';
                }
                ?>
					<?php
                    if ($row['status'] == 'Issued' || $row['status'] == 'Paid - Partial') {
                        if ($row['reminderCount'] >= 0) {
                            ?>
							<tr class='<?php echo $rowNum ?>'>
								<td>
									<b><?php echo __('Reminder 1') ?></b><br/>
								</td>
								<td class="left">
									<?php
                                    echo "<a title='".__('Print')."' target='_blank' href='".$session->get('absoluteURL').'/report.php?q=/modules/'.$session->get('module').'/invoices_manage_print_print.php&type=reminder1&tawasulFinanceInvoiceID='.$row['tawasulFinanceInvoiceID']."&tawasulSchoolYearID=$tawasulSchoolYearID'>".__('Print').icon('solid', 'print', 'size-5 text-gray-600')."</a>";
                            ?>
								</td>
							</tr>
							<?php

                        }
                        ++$count;
                        if ($count % 2 == 0) {
                            $rowNum = 'even';
                        } else {
                            $rowNum = 'odd';
                        }
                        if ($row['reminderCount'] >= 1) {
                            ?>
							<tr class='<?php echo $rowNum ?>'>
								<td>
									<b><?php echo __('Reminder 2') ?></b><br/>
								</td>
								<td class="left">
									<?php
                                    echo "<a title='".__('Print')."' target='_blank' href='".$session->get('absoluteURL').'/report.php?q=/modules/'.$session->get('module').'/invoices_manage_print_print.php&type=reminder2&tawasulFinanceInvoiceID='.$row['tawasulFinanceInvoiceID']."&tawasulSchoolYearID=$tawasulSchoolYearID'>".__('Print').icon('solid', 'print', 'size-5 text-gray-600')."</a>";
                            ?>
								</td>
							</tr>
							<?php

                        }
                        ++$count;
                        if ($count % 2 == 0) {
                            $rowNum = 'even';
                        } else {
                            $rowNum = 'odd';
                        }
                        if ($row['reminderCount'] >= 2) {
                            ?>
							<tr class='<?php echo $rowNum ?>'>
								<td>
									<b><?php echo __('Reminder 3') ?></b><br/>
								</td>
								<td class="left">
									<?php
                                    echo "<a title='".__('Print')."' target='_blank' href='".$session->get('absoluteURL').'/report.php?q=/modules/'.$session->get('module').'/invoices_manage_print_print.php&type=reminder3&tawasulFinanceInvoiceID='.$row['tawasulFinanceInvoiceID']."&tawasulSchoolYearID=$tawasulSchoolYearID'>".__('Print').icon('solid', 'print', 'size-5 text-gray-600')."</a>";
                            ?>
								</td>
							</tr>
							<?php

                        }
                    }
                	if ($row['status'] == 'Paid' OR $row['status'] == 'Paid - Partial' OR $row['status'] == 'Refunded') {
                    //Get individual payments that make up receipt
                        try {
                            $data = array('foreignTable' => 'tawasulFinanceInvoice', 'foreignTableID' => $tawasulFinanceInvoiceID);
                            $sql = 'SELECT tawasulPayment.*, surname, preferredName FROM tawasulPayment JOIN tawasulPerson ON (tawasulPayment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE foreignTable=:foreignTable AND foreignTableID=:foreignTableID ORDER BY timestamp, tawasulPaymentID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                        }

                    if ($result->rowCount() < 1) {
                        ?>
							<tr class='<?php echo $rowNum ?>'>
								<td>
									<b><?php echo __('Receipt') ?></b><br/>
								</td>
								<td class="left">
									<?php
                                    echo "<a title='".__('Print')."' target='_blank' href='".$session->get('absoluteURL').'/report.php?q=/modules/'.$session->get('module').'/invoices_manage_print_print.php&type=receipt&tawasulFinanceInvoiceID='.$row['tawasulFinanceInvoiceID']."&tawasulSchoolYearID=$tawasulSchoolYearID'>".__('Print').icon('solid', 'print', 'size-5 text-gray-600')."</a>";
                        ?>
								</td>
							</tr>
							<?php

                    } else {
                        $count2 = 0;
                        while ($row = $result->fetch()) {
                            if ($count % 2 == 0) {
                                $rowNum = 'even';
                            } else {
                                $rowNum = 'odd';
                            }
                            ?>
							<tr class='<?php echo $rowNum ?>'>
								<td>
									<b><?php echo sprintf(__('Receipt %1$s'), ($count2 + 1)) ?></b><br/>
								</td>
								<td class="left">
									<?php
                                    echo "<a title='".__('Print')."' target='_blank' href='".$session->get('absoluteURL').'/report.php?q=/modules/'.$session->get('module')."/invoices_manage_print_print.php&type=receipt&tawasulFinanceInvoiceID=$tawasulFinanceInvoiceID&tawasulSchoolYearID=$tawasulSchoolYearID&receiptNumber=$count2'>".__('Print').icon('solid', 'print', 'size-5 text-gray-600')."</a>";
                                    ?>
								</td>
							</tr>
							<?php
                            ++$count;
                            ++$count2;
                        }
                    }
                }
                echo '</table>';
            }
        }
    }
}
?>
