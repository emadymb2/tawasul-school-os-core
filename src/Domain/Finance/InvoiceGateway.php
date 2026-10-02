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

namespace TawasulOS\Domain\Finance;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * Invoice Gateway
 *
 * @version v16
 * @since   v16
 */
class InvoiceGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulFinanceInvoice';
    private static $primaryKey = 'tawasulFinanceInvoiceID';

    private static $searchableColumns = [];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryInvoicesByYear(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulFinanceInvoice.tawasulFinanceInvoiceID',
                'tawasulFinanceInvoice.invoiceTo',
                'tawasulFinanceInvoice.status',
                'tawasulFinanceInvoice.invoiceIssueDate',
                'tawasulFinanceInvoice.paidDate',
                'tawasulFinanceInvoice.paidAmount',
                'tawasulFinanceInvoice.notes',
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulFormGroup.name AS formGroup',
                "(CASE 
                    WHEN tawasulFinanceInvoice.status = 'Pending' AND billingScheduleType='Scheduled' THEN tawasulFinanceBillingSchedule.invoiceDueDate 
                    ELSE tawasulFinanceInvoice.invoiceDueDate END
                ) AS invoiceDueDate",
                "(CASE 
                    WHEN tawasulFinanceInvoice.status = 'Pending' AND billingScheduleType='Scheduled' THEN tawasulFinanceBillingSchedule.name
                    WHEN billingScheduleType='Ad Hoc' THEN 'Ad Hoc'
                    ELSE tawasulFinanceBillingSchedule.name END
                ) AS billingSchedule",
                "FIND_IN_SET(tawasulFinanceInvoice.status, 'Pending,Issued,Paid,Refunded,Cancelled') as defaultSortOrder"
            ])
            ->innerJoin('tawasulFinanceInvoicee', 'tawasulFinanceInvoice.tawasulFinanceInvoiceeID=tawasulFinanceInvoicee.tawasulFinanceInvoiceeID')
            ->innerJoin('tawasulPerson', 'tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulFinanceBillingSchedule', 'tawasulFinanceInvoice.tawasulFinanceBillingScheduleID=tawasulFinanceBillingSchedule.tawasulFinanceBillingScheduleID')
            ->leftJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulFinanceInvoice.tawasulSchoolYearID')
            ->leftJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->where('tawasulFinanceInvoice.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulFinanceInvoice.tawasulFinanceInvoiceID']);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                switch ($status) {
                    case 'Issued':
                        $query->where('tawasulFinanceInvoice.invoiceDueDate >= :today')
                              ->bindValue('today', date('Y-m-d'));
                        break;

                    case 'Issued - Overdue':
                        $status = 'Issued';
                        $query->where('tawasulFinanceInvoice.invoiceDueDate < :today')
                              ->bindValue('today', date('Y-m-d'));
                        break;

                    case 'Paid':
                        $query->where('tawasulFinanceInvoice.invoiceDueDate >= tawasulFinanceInvoice.paidDate');
                        break;

                    case 'Paid - Late':
                        $status = 'Paid';
                        $query->where('tawasulFinanceInvoice.invoiceDueDate < tawasulFinanceInvoice.paidDate');
                        break;
                }

                return $query
                    ->where('tawasulFinanceInvoice.status LIKE :status')
                    ->bindValue('status', $status);
            },

            'invoicee' => function ($query, $tawasulFinanceInvoiceeID) {
                return $query
                    ->where('tawasulFinanceInvoice.tawasulFinanceInvoiceeID = :tawasulFinanceInvoiceeID')
                    ->bindValue('tawasulFinanceInvoiceeID', $tawasulFinanceInvoiceeID);
            },

            'month' => function ($query, $monthOfIssue) {
                return $query
                    ->where('MONTH(tawasulFinanceInvoice.invoiceIssueDate) = :monthOfIssue')
                    ->bindValue('monthOfIssue', $monthOfIssue);
            },

            'billingSchedule' => function ($query, $tawasulFinanceBillingScheduleID) {
                if ($tawasulFinanceBillingScheduleID == 'Ad Hoc') {
                    return $query->where('tawasulFinanceInvoice.billingScheduleType = "Ad Hoc"');
                } else {
                    return $query
                        ->where('tawasulFinanceInvoice.tawasulFinanceBillingScheduleID = :tawasulFinanceBillingScheduleID')
                        ->bindValue('tawasulFinanceBillingScheduleID', $tawasulFinanceBillingScheduleID);
                }
            },

            'feeCategory' => function ($query, $tawasulFinanceFeeCategoryID) {
                return $query
                    ->leftJoin('tawasulFinanceInvoiceFee', 'tawasulFinanceInvoiceFee.tawasulFinanceInvoiceID=tawasulFinanceInvoice.tawasulFinanceInvoiceID')
                    ->leftJoin('tawasulFinanceFee', 'tawasulFinanceInvoiceFee.tawasulFinanceFeeID=tawasulFinanceFee.tawasulFinanceFeeID')
                    ->where(function ($query) {
                        $query->where('tawasulFinanceInvoiceFee.tawasulFinanceFeeCategoryID=:tawasulFinanceFeeCategoryID')
                              ->orWhere("(tawasulFinanceInvoiceFee.separated='N' AND tawasulFinanceFee.tawasulFinanceFeeCategoryID=:tawasulFinanceFeeCategoryID)");
                    })
                    ->bindValue('tawasulFinanceFeeCategoryID', $tawasulFinanceFeeCategoryID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryFeeCategories(QueryCriteria $criteria)
    {
        $query = $this
        ->newQuery()
        ->from('tawasulFinanceFeeCategory')
        ->cols([
          'tawasulFinanceFeeCategoryID',
          'name',
          'nameShort',
          'active',
          'description'
        ]);
        $criteria->addFilterRules([
        'active' => function ($query, $active) {
            return $query
            ->where('tawasulFinanceFeeCategory.active = :active')
            ->bindValue('active', $active);
        }
        ]);
        return $this->runQuery($query, $criteria);
    }

    public function selectInvoicesByPersonID($tawasulSchoolYearID, $tawasulPersonID) 
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT 
            tawasulFinanceInvoice.tawasulFinanceInvoiceID,
            tawasulFinanceInvoice.tawasulSchoolYearID,
            surname,
            preferredName,
            tawasulFinanceInvoice.invoiceTo,
            tawasulFinanceInvoice.status,
            tawasulFinanceInvoice.key,
            tawasulFinanceInvoice.invoiceIssueDate,
            tawasulFinanceInvoice.invoiceDueDate,
            paidDate,
            paidAmount,
            billingScheduleType AS billingSchedule,
            tawasulFinanceBillingSchedule.name AS billingScheduleExtra,
            tawasulFinanceInvoice.notes,
            tawasulFormGroup.name AS formGroup,
            tawasulPerson.tawasulPersonID 
        FROM tawasulFinanceInvoice 
            JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoice.tawasulFinanceInvoiceeID = tawasulFinanceInvoicee.tawasulFinanceInvoiceeID) 
            JOIN tawasulPerson ON (tawasulFinanceInvoicee.tawasulPersonID = tawasulPerson.tawasulPersonID) 
            LEFT JOIN tawasulFinanceBillingSchedule ON (tawasulFinanceInvoice.tawasulFinanceBillingScheduleID = tawasulFinanceBillingSchedule.tawasulFinanceBillingScheduleID) 
            LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID = tawasulPerson.tawasulPersonID) 
            LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID = tawasulFormGroup.tawasulFormGroupID) 
        WHERE tawasulFinanceInvoice.tawasulSchoolYearID = :tawasulSchoolYearID 
            AND tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID 
            AND NOT tawasulFinanceInvoice.status='Pending' 
            AND tawasulFinanceInvoicee.tawasulPersonID = :tawasulPersonID 
        ORDER BY invoiceIssueDate, surname, preferredName";

        return $this->db()->select($sql, $data);
    }
}
