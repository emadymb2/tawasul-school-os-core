<?php

namespace TawasulOS\Domain\Finance;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\DataSet;

class FinanceFeeCategoryGateway extends QueryableGateway
{
    use TableAware;
    private static $primaryKey = 'tawasulFinanceFeeCategoryID';
    private static $tableName = 'tawasulFinanceFeeCategory';
    private static $searchableColumns = [];

    public function selectActiveFeeCategories()
    {
        $sql = "SELECT tawasulFinanceFeeCategoryID as value, name FROM tawasulFinanceFeeCategory WHERE active='Y' AND NOT tawasulFinanceFeeCategoryID=1 ORDER BY name";

        return $this->db()->select($sql);
    }
}
