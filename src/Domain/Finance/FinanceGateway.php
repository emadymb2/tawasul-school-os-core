<?php

namespace TawasulOS\Domain\Finance;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\DataSet;

class FinanceGateway extends QueryableGateway
{
    use TableAware;
    private static $primaryKey = 'tawasulFinanceBudgetID';
    private static $tableName = 'tawasulFinanceBudget';
    private static $searchableColumns = [];

  public function queryFees(QueryCriteria $criteria)
    {

        $query = $this
            ->newQuery()
            ->from('tawasulFinanceFee')
            ->join('left', 'tawasulFinanceFeeCategory', 'tawasulFinanceFee.tawasulFinanceFeeCategoryID = tawasulFinanceFeeCategory.tawasulFinanceFeeCategoryID')
            ->cols(
                [
                'tawasulFinanceFee.name',
                'tawasulFinanceFee.nameShort',
                'tawasulFinanceFeeCategory.name as category',
                'tawasulFinanceFee.fee',
                'tawasulFinanceFee.tawasulFinanceFeeID',
                'tawasulFinanceFee.tawasulSchoolYearID',
                'tawasulFinanceFee.active',
                'tawasulFinanceFee.description'
                ]
          );
    
          $criteria->addFilterRules(
            [
               'status' => function ($query, $status) {
                return $query
                ->where('tawasulFinanceFee.active = :status')
                ->bindValue('status', $status);
            },
            'search' => function ($query, $search) {
                return $query
                ->where('(tawasulFinanceFee.name LIKE :search OR tawasulFinanceFeeCategory.name LIKE :search)')
                ->bindValue('search', '%'.$search.'%');
            },
            'tawasulSchoolYearID' => function ($query, $tawasulSchoolYearID) {
                return $query
                ->where('tawasulFinanceFee.tawasulSchoolYearID = :tawasulSchoolYearID')
                ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);
            }
            ]
        );
    
      return $this->runQuery($query, $criteria);
    }  
    
                  
   public function queryFinanceBudget(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols(
                [
                'tawasulFinanceBudget.tawasulFinanceBudgetID',
                'tawasulFinanceBudget.name',
                'tawasulFinanceBudget.nameShort',
                'tawasulFinanceBudget.category',
                'tawasulFinanceBudget.active'
                  ]
            );

        $criteria->addFilterRules(
            [
                'active' => function ($query,$active) {
                return $query
                    ->where('tawasulFinanceBudget.active = :active')
                    ->bindValue('active', $active);
                }
            ]
        );

        return $this->runQuery($query, $criteria);
    }  

    public function queryFinanceCycles(QueryCriteria $criteria)
    {

        $query = $this
            ->newQuery()
            ->from('tawasulFinanceBudgetCycle')
            ->cols(
                [
                'tawasulFinanceBudgetCycle.tawasulFinanceBudgetCycleID',
                'tawasulFinanceBudgetCycle.name',
                'tawasulFinanceBudgetCycle.status',
                'tawasulFinanceBudgetCycle.dateStart',
                'tawasulFinanceBudgetCycle.dateEnd',
                'tawasulFinanceBudgetCycle.sequenceNumber',
                "IF(tawasulFinanceBudgetCycle.dateStart > CURRENT_TIMESTAMP(),'Y','N') as inFuture",
                "IF(tawasulFinanceBudgetCycle.dateEnd < CURRENT_TIMESTAMP(),'Y','N') as inPast"
                ]
            );

        $criteria->addFilterRules(
          [
            'status' => function ($query, $status) {
                return $query
                ->where('tawasulFinanceBudgetCycle.status = :status')
                ->bindValue('status', $status);
            },
            'inPast' => function ($query, $inPast) {
                return $query
                ->where('inPast = :inPast')
                ->bindValue('inPast', $inPast);
            },
            'inFuture' => function ($query, $inFuture) {
                return $query
                ->where('inFuture = :inFuture')
                ->bindValue('inFuture', $inFuture);
            }
            ]
        );
      
        return $this->runQuery($query, $criteria);
    }

    public function queryExpenseApprovers(QueryCriteria $criteria)
    {
        $query = $this
        ->newQuery()
        ->cols([
          'tawasulPerson.title',
          'tawasulPerson.preferredName',
          'tawasulPerson.surname',
          'tawasulFinanceExpenseApprover.sequenceNumber',
          'tawasulFinanceExpenseApprover.tawasulFinanceExpenseApproverID'
        ])
        ->from('tawasulFinanceExpenseApprover')
        ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID = tawasulFinanceExpenseApprover.tawasulPersonID')
        ->where("tawasulPerson.status = 'Full'");
        
        return $this->runQuery($query, $criteria);
    }
}
