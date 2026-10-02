<?php

namespace TawasulOS\Domain\Finance;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\DataSet;

class ExpenseGateway extends QueryableGateway
{
    use TableAware;
    private static $primaryKey = 'tawasulFinanceExpenseID';
    private static $tableName = 'tawasulFinanceExpense';
    private static $searchableColumns = [];

    public function queryExpenseLogByID(QueryCriteria $criteria, $tawasulFinanceExpenseID)
    {
        $query = $this
            ->newQuery()
            ->cols([
                'tawasulPerson.preferredName',
                'tawasulPerson.title',
                'tawasulPerson.surname',
                'tawasulFinanceExpenseLog.timestamp',
                'tawasulFinanceExpenseLog.comment',
                'tawasulFinanceExpenseLog.action'
            ])
            ->from('tawasulFinanceExpenseLog')
            ->innerJoin('tawasulPerson', 'tawasulFinanceExpenseLog.tawasulPersonID = tawasulPerson.tawasulPersonID')
            ->where('tawasulFinanceExpenseLog.tawasulFinanceExpenseID = :tawasulFinanceExpenseID')
            ->bindValue('tawasulFinanceExpenseID', $tawasulFinanceExpenseID);

        return $this->runQuery($query, $criteria);
    }

    public function queryExpensesByBudgetCycleID(QueryCriteria $criteria, $tawasulFinanceBudgetCycleID, $tawasulPersonID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulFinanceExpense.tawasulFinanceExpenseID',
                'tawasulFinanceExpense.tawasulPersonIDCreator',
                'tawasulFinanceExpense.status',
                'tawasulFinanceExpense.title',
                'tawasulFinanceExpense.paymentReimbursementStatus',
                'tawasulFinanceExpense.timestampCreator',
                'tawasulFinanceExpense.cost',
                'tawasulFinanceExpense.purchaseBy',
                'tawasulFinanceBudget.name AS budget',
                'tawasulPerson.preferredName',
                'tawasulPerson.surname',
                "FIND_IN_SET(tawasulFinanceExpense.status, 'Pending,Issued,Paid,Refunded,Cancelled') AS defaultSortOrder"
            ])
            ->innerJoin('tawasulFinanceBudget', 'tawasulFinanceExpense.tawasulFinanceBudgetID = tawasulFinanceBudget.tawasulFinanceBudgetID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulFinanceExpense.tawasulPersonIDCreator')
            ->where('tawasulFinanceExpense.tawasulFinanceBudgetCycleID = :tawasulFinanceBudgetCycleID')
            ->bindValue('tawasulFinanceBudgetCycleID', $tawasulFinanceBudgetCycleID);

        if (!empty($tawasulPersonID)) {
            $query->where('tawasulFinanceExpense.tawasulPersonIDCreator = :tawasulPersonID')
                ->bindValue('tawasulPersonID', $tawasulPersonID);
        }

        $criteria->addFilterRules([
            'budget' => function ($query, $tawasulFinanceBudgetID) {
                return $query
                    ->where('tawasulFinanceExpense.tawasulFinanceBudgetID = :tawasulFinanceBudgetID')
                    ->bindValue('tawasulFinanceBudgetID', $tawasulFinanceBudgetID);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('tawasulFinanceExpense.status = :status')
                    ->bindValue('status', $status);
            },
            'creator' => function ($query, $tawasulPersonIDCreator) {
                return $query
                    ->where('tawasulFinanceExpense.tawasulPersonIDCreator = :tawasulPersonIDCreator')
                    ->bindValue('tawasulPersonIDCreator', $tawasulPersonIDCreator);
            }
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function getExpenseByBudgetID($tawasulFinanceBudgetCycleID, $tawasulFinanceExpenseID)
    {
        $data = ['tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID, 'tawasulFinanceExpenseID' => $tawasulFinanceExpenseID];
        $sql = "SELECT tawasulFinanceExpense.*, tawasulFinanceBudget.name AS budget, surname, preferredName, 'Full' AS access
				FROM tawasulFinanceExpense
				JOIN tawasulFinanceBudget ON (tawasulFinanceExpense.tawasulFinanceBudgetID=tawasulFinanceBudget.tawasulFinanceBudgetID)
				JOIN tawasulPerson ON (tawasulFinanceExpense.tawasulPersonIDCreator=tawasulPerson.tawasulPersonID)
				WHERE tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID AND tawasulFinanceExpenseID=:tawasulFinanceExpenseID AND tawasulFinanceExpense.status='Approved'";

        return $this->db()->selectOne($sql, $data);
    }
}
