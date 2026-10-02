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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

include '../../config.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulFinanceInvoiceIDs = $session->get('financeInvoiceExportIDs');
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';

    if ($tawasulFinanceInvoiceIDs == '' or $tawasulSchoolYearID == '') {
        echo "<div class='error'>";
        echo __('List of invoices or school year have not been specified, and so this export cannot be completed.');
        echo '</div>';
    } else {

		$whereCount = 0;
		$whereSched = '(';
		$whereAdHoc = '(';
		$whereNotPending = '(';
		$data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
		foreach ($tawasulFinanceInvoiceIDs as $tawasulFinanceInvoiceID) {
			$data['tawasulFinanceInvoiceID'.$whereCount] = $tawasulFinanceInvoiceID;
			$whereSched .= 'tawasulFinanceInvoice.tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID'.$whereCount.' OR ';
			++$whereCount;
		}
		$whereSched = substr($whereSched, 0, -4).')';

		//SQL for billing schedule AND pending
		$sql = "(SELECT tawasulFinanceInvoice.tawasulFinanceInvoiceID, surname, preferredName, tawasulPerson.tawasulPersonID, dob, gender,
				studentID, tawasulFinanceInvoice.invoiceTo, tawasulFinanceInvoice.status, tawasulFinanceInvoice.invoiceIssueDate,
				tawasulFinanceBillingSchedule.invoiceDueDate, paidDate, paidAmount, tawasulFinanceBillingSchedule.name AS billingSchedule,
				NULL AS billingScheduleExtra, notes, tawasulFormGroup.name AS formGroup
			FROM tawasulFinanceInvoice
				JOIN tawasulFinanceBillingSchedule ON (tawasulFinanceInvoice.tawasulFinanceBillingScheduleID=tawasulFinanceBillingSchedule.tawasulFinanceBillingScheduleID)
				JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoice.tawasulFinanceInvoiceeID=tawasulFinanceInvoicee.tawasulFinanceInvoiceeID)
				JOIN tawasulPerson ON (tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID)
				LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID
					AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulFinanceInvoice.tawasulSchoolYearID)
				LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
			WHERE tawasulFinanceInvoice.tawasulSchoolYearID=:tawasulSchoolYearID
				AND billingScheduleType='Scheduled'
				AND tawasulFinanceInvoice.status='Pending'
				AND $whereSched)";
		$sql .= ' UNION ';
		//SQL for Ad Hoc AND pending
		$sql .= "(SELECT tawasulFinanceInvoice.tawasulFinanceInvoiceID, surname, preferredName, tawasulPerson.tawasulPersonID, dob, gender, studentID, tawasulFinanceInvoice.invoiceTo, tawasulFinanceInvoice.status, invoiceIssueDate, invoiceDueDate, paidDate, paidAmount, 'Ad Hoc' AS billingSchedule, NULL AS billingScheduleExtra, notes, tawasulFormGroup.name AS formGroup FROM tawasulFinanceInvoice JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoice.tawasulFinanceInvoiceeID=tawasulFinanceInvoicee.tawasulFinanceInvoiceeID) JOIN tawasulPerson ON (tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID) LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulFinanceInvoice.tawasulSchoolYearID) LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulFinanceInvoice.tawasulSchoolYearID=:tawasulSchoolYearID AND billingScheduleType='Ad Hoc' AND tawasulFinanceInvoice.status='Pending' AND $whereSched)";
		$sql .= ' UNION ';
		//SQL for NOT Pending
		$sql .= "(SELECT tawasulFinanceInvoice.tawasulFinanceInvoiceID, surname, preferredName, tawasulPerson.tawasulPersonID, dob, gender, studentID, tawasulFinanceInvoice.invoiceTo, tawasulFinanceInvoice.status, tawasulFinanceInvoice.invoiceIssueDate, tawasulFinanceInvoice.invoiceDueDate, paidDate, paidAmount, billingScheduleType AS billingSchedule, tawasulFinanceBillingSchedule.name AS billingScheduleExtra, notes, tawasulFormGroup.name AS formGroup FROM tawasulFinanceInvoice LEFT JOIN tawasulFinanceBillingSchedule ON (tawasulFinanceInvoice.tawasulFinanceBillingScheduleID=tawasulFinanceBillingSchedule.tawasulFinanceBillingScheduleID) JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoice.tawasulFinanceInvoiceeID=tawasulFinanceInvoicee.tawasulFinanceInvoiceeID) JOIN tawasulPerson ON (tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID) LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulFinanceInvoice.tawasulSchoolYearID) LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulFinanceInvoice.tawasulSchoolYearID=:tawasulSchoolYearID AND NOT tawasulFinanceInvoice.status='Pending' AND $whereSched)";
		$sql .= " ORDER BY FIND_IN_SET(status, 'Pending,Issued,Paid,Refunded,Cancelled'), invoiceIssueDate, surname, preferredName";
		if (is_null($result = $pdo->executeQuery($data, $sql))) {
			echo "<div class='error'>".$pdo->getError().'</div>';
		}

		$excel = new TawasulOS\Excel('invoices.xlsx');
		if ($excel->estimateCellCount($result) > 8000)    //  If too big, then render csv instead.
			return TawasulOS\csv::generate($pdo, 'Invoices');
		$excel->setActiveSheetIndex(0);
		$excel->getProperties()->setTitle('Invoices');
		$excel->getProperties()->setSubject('Invoice Export');
		$excel->getProperties()->setDescription('Invoice Export');

        //Create border and fill style
        $style_border = array('borders' => array('right' => array('borderStyle' => Border::BORDER_THIN, 'color' => array('argb' => '766f6e')), 'left' => array('borderStyle' => Border::BORDER_THIN, 'color' => array('argb' => '766f6e')), 'top' => array('borderStyle' => Border::BORDER_THIN, 'color' => array('argb' => '766f6e')), 'bottom' => array('borderStyle' => Border::BORDER_THIN, 'color' => array('argb' => '766f6e'))));
        $style_head_fill = array('fill' => array('fillType' => Fill::FILL_SOLID, 'color' => array('rgb' => 'B89FE2')));

        //Auto set column widths
        for($col = 'A'; $col <= 'M'; $col++)
            $excel->getActiveSheet()->getColumnDimension($col)->setAutoSize(true);

		$excel->getActiveSheet()->setCellValueByColumnAndRow(1, 1, __("Invoice Number"));
        $excel->getActiveSheet()->getStyleByColumnAndRow(1, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(1, 1)->applyFromArray($style_head_fill);
		$excel->getActiveSheet()->setCellValueByColumnAndRow(2, 1, __("Student"));
        $excel->getActiveSheet()->getStyleByColumnAndRow(2, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(2, 1)->applyFromArray($style_head_fill);
		$excel->getActiveSheet()->setCellValueByColumnAndRow(3, 1, __("Form Group"));
        $excel->getActiveSheet()->getStyleByColumnAndRow(3, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(3, 1)->applyFromArray($style_head_fill);
		$excel->getActiveSheet()->setCellValueByColumnAndRow(4, 1, __("Invoice To"));
        $excel->getActiveSheet()->getStyleByColumnAndRow(4, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(4, 1)->applyFromArray($style_head_fill);
		$excel->getActiveSheet()->setCellValueByColumnAndRow(5, 1, __("Status"));
        $excel->getActiveSheet()->getStyleByColumnAndRow(5, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(5, 1)->applyFromArray($style_head_fill);
		$excel->getActiveSheet()->setCellValueByColumnAndRow(6, 1, __("Schedule"));
        $excel->getActiveSheet()->getStyleByColumnAndRow(6, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(6, 1)->applyFromArray($style_head_fill);
		$excel->getActiveSheet()->setCellValueByColumnAndRow(7, 1, __("Total Value") . ' (' . $session->get("currency") .')');
        $excel->getActiveSheet()->getStyleByColumnAndRow(7, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(7, 1)->applyFromArray($style_head_fill);
		$excel->getActiveSheet()->setCellValueByColumnAndRow(8, 1, __('Fees') . ' (' . $session->get("currency") .')');
        $excel->getActiveSheet()->getStyleByColumnAndRow(8, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(8, 1)->applyFromArray($style_head_fill);
		$excel->getActiveSheet()->setCellValueByColumnAndRow(9, 1, __("Issue Date"));
        $excel->getActiveSheet()->getStyleByColumnAndRow(9, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(9, 1)->applyFromArray($style_head_fill);
		$excel->getActiveSheet()->setCellValueByColumnAndRow(10, 1, __("Due Date"));
        $excel->getActiveSheet()->getStyleByColumnAndRow(10, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(10, 1)->applyFromArray($style_head_fill);
		$excel->getActiveSheet()->setCellValueByColumnAndRow(11, 1, __("Date Paid"));
        $excel->getActiveSheet()->getStyleByColumnAndRow(11, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(11, 1)->applyFromArray($style_head_fill);
		$excel->getActiveSheet()->setCellValueByColumnAndRow(12, 1, __("Amount Paid") . " (" . $session->get("currency") . ")" );
        $excel->getActiveSheet()->getStyleByColumnAndRow(12, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(12, 1)->applyFromArray($style_head_fill);
        $excel->getActiveSheet()->setCellValueByColumnAndRow(13, 1, __('Notes')  );
        $excel->getActiveSheet()->getStyleByColumnAndRow(13, 1)->applyFromArray($style_border);
        $excel->getActiveSheet()->getStyleByColumnAndRow(13, 1)->applyFromArray($style_head_fill);
		$excel->getActiveSheet()->getStyle("1:1")->getFont()->setBold(true);

		$r = 2;
		$count = 0;

		while ($row=$result->fetch()) {
			$count++ ;
			//Column A
			$invoiceNumberSetting = $container->get(SettingGateway::class)->getSettingByScope("Finance", "invoiceNumber" ) ;
			if ($invoiceNumberSetting=="Person ID + Invoice ID") {
                $invoiceNumber = ltrim($row["tawasulPersonID"],"0") . "-" . ltrim($row["tawasulFinanceInvoiceID"], "0");
				
			}
			else if ($invoiceNumberSetting=="Student ID + Invoice ID") {
                $invoiceNumber = ltrim($row["studentID"],"0") . "-" . ltrim($row["tawasulFinanceInvoiceID"], "0");
			}
			else {
                $invoiceNumber = ltrim($row["tawasulFinanceInvoiceID"], "0");
			}
            $excel->getActiveSheet()->setCellValueByColumnAndRow(1, $r, $invoiceNumber);
            $excel->getActiveSheet()->getStyleByColumnAndRow(1, $r)->applyFromArray($style_border);
            
            $excel->getActiveSheet()->getCell("A".$r)->getHyperlink()->setUrl($session->get('absoluteURL').'/report.php?q=/modules/TawasulFinance/invoices_manage_print_print.php&type=invoice&tawasulFinanceInvoiceID='.$row["tawasulFinanceInvoiceID"].'&tawasulSchoolYearID='.$tawasulSchoolYearID.'&preview='.($row["status"]=="Pending" ? 'true' : ''));
            $excel->getActiveSheet()->getStyle("A".$r)->getFont()->setUnderline(true);
			//Column B
			$excel->getActiveSheet()->setCellValueByColumnAndRow(2, $r, Format::name("", htmlPrep($row["preferredName"]), htmlPrep($row["surname"]), "Student", true));
            $excel->getActiveSheet()->getStyleByColumnAndRow(2, $r)->applyFromArray($style_border);
			//Column C
			$excel->getActiveSheet()->setCellValueByColumnAndRow(3, $r, $row["formGroup"]);
            $excel->getActiveSheet()->getStyleByColumnAndRow(3, $r)->applyFromArray($style_border);
			//Column D
			$excel->getActiveSheet()->setCellValueByColumnAndRow(4, $r, $row["invoiceTo"]);
            $excel->getActiveSheet()->getStyleByColumnAndRow(4, $r)->applyFromArray($style_border);
			//Column E
			$excel->getActiveSheet()->setCellValueByColumnAndRow(5, $r, $row["status"]);
            $excel->getActiveSheet()->getStyleByColumnAndRow(5, $r)->applyFromArray($style_border);
			//Column F
			if ($row["billingScheduleExtra"]!="")  {
				$excel->getActiveSheet()->setCellValueByColumnAndRow(6, $r, $row["billingScheduleExtra"]);
			}
			else {
				$excel->getActiveSheet()->setCellValueByColumnAndRow(6, $r, $row["billingSchedule"]);
			}
            $excel->getActiveSheet()->getStyleByColumnAndRow(6, $r)->applyFromArray($style_border);
			//Column G
			//Calculate total value
			$totalFee=0 ;
			$feeError = false ;
            $fees = [];
			$dataTotal=array("tawasulFinanceInvoiceID"=>$row["tawasulFinanceInvoiceID"]);
			if ($row["status"]=="Pending") {
				$sqlTotal="SELECT tawasulFinanceInvoiceFee.fee AS fee, tawasulFinanceFee.fee AS fee2, (CASE WHEN feeType = 'Ad Hoc' THEN tawasulFinanceInvoiceFee.name else tawasulFinanceFee.name END) as name, (CASE WHEN feeType = 'Ad Hoc' THEN category2.name else category1.name END) as category
					FROM tawasulFinanceInvoiceFee
						LEFT JOIN tawasulFinanceFee ON (tawasulFinanceInvoiceFee.tawasulFinanceFeeID=tawasulFinanceFee.tawasulFinanceFeeID)
                        LEFT JOIN tawasulFinanceFeeCategory AS category1 ON (category1.tawasulFinanceFeeCategoryID=tawasulFinanceFee.tawasulFinanceFeeCategoryID)
                        LEFT JOIN tawasulFinanceFeeCategory as category2 ON (category2.tawasulFinanceFeeCategoryID=tawasulFinanceInvoiceFee.tawasulFinanceFeeCategoryID)
					WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID" ;
			}
			else {
				$sqlTotal="SELECT tawasulFinanceInvoiceFee.fee AS fee, NULL AS fee2, tawasulFinanceInvoiceFee.name, tawasulFinanceFeeCategory.name as category
					FROM tawasulFinanceInvoiceFee
                    LEFT JOIN tawasulFinanceFeeCategory ON (tawasulFinanceFeeCategory.tawasulFinanceFeeCategoryID=tawasulFinanceInvoiceFee.tawasulFinanceFeeCategoryID)
					WHERE tawasulFinanceInvoiceID=:tawasulFinanceInvoiceID" ;
			}
			if (is_null($resultTotal=$pdo->executeQuery($dataTotal, $sqlTotal)))
			{
				$excel->getActiveSheet()->setCellValueByColumnAndRow(7, $r, 'Error calculating total');
				$feeError = true;
			}
			while ($rowTotal = $resultTotal->fetch()) {
				$actualFee = is_numeric($rowTotal["fee2"]) ? $rowTotal["fee2"] : $rowTotal["fee"];
                $totalFee+=$actualFee;

                $fees[] = $rowTotal['category'].': '.$rowTotal['name'].' = '.$actualFee;
			}
			$x = '';
			if (! $feeError) {
				$x .= number_format($totalFee ?? 0, 2, ".", "") ;
				$excel->getActiveSheet()->setCellValueByColumnAndRow(7, $r, $x);
			}
            $excel->getActiveSheet()->getStyleByColumnAndRow(7, $r)->applyFromArray($style_border);
            //Column H
		    $excel->getActiveSheet()->setCellValueByColumnAndRow(8, $r, implode("\n", $fees));
            $excel->getActiveSheet()->getStyleByColumnAndRow(8, $r)->applyFromArray($style_border);
            $excel->getActiveSheet()->getStyleByColumnAndRow(8, $r)->getAlignment()->setWrapText(true);

			//Column I
		    $excel->getActiveSheet()->setCellValueByColumnAndRow(9, $r, Format::date($row["invoiceIssueDate"]));
            $excel->getActiveSheet()->getStyleByColumnAndRow(9, $r)->applyFromArray($style_border);
			//Column K
			$excel->getActiveSheet()->setCellValueByColumnAndRow(10, $r, Format::date($row["invoiceDueDate"]));
            $excel->getActiveSheet()->getStyleByColumnAndRow(10, $r)->applyFromArray($style_border);
			//Column K
			$excel->getActiveSheet()->setCellValueByColumnAndRow(11, $r, !empty($row["paidDate"]) ? Format::date($row["paidDate"]) : '');
            $excel->getActiveSheet()->getStyleByColumnAndRow(11, $r)->applyFromArray($style_border);
			//Column L
			$excel->getActiveSheet()->setCellValueByColumnAndRow(12, $r, number_format($row["paidAmount"] ?? 0, 2, ".", ""));
            $excel->getActiveSheet()->getStyleByColumnAndRow(12, $r)->applyFromArray($style_border);
            //Column M
			$excel->getActiveSheet()->setCellValueByColumnAndRow(13, $r, strip_tags($row["notes"]));
            $excel->getActiveSheet()->getStyleByColumnAndRow(13, $r)->applyFromArray($style_border);
			$r++;
		}

		$session->set('financeInvoiceExportIDs', null);
		$excel->exportWorksheet();
	}
}
