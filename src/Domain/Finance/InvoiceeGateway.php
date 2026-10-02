<?php

namespace TawasulOS\Domain\Finance;

use TawasulOS\Domain\DataSet;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByPerson;

class InvoiceeGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $primaryKey = 'tawasulFinanceInvoiceeID';
    private static $tableName = 'tawasulFinanceInvoicee';
    private static $searchableColumns = ['preferredName', 'surname', 'username'];

    private static $scrubbableKey = 'tawasulPersonID';
    private static $scrubbableColumns = ['companyName' => null,'companyContact' => null,'companyAddress' => null,'companyEmail' => null,'companyCCFamily' => null,'companyPhone' => null];

    public function queryInvoicees(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulFinanceInvoicee')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID = tawasulFinanceInvoicee.tawasulPersonID')
            ->where("NOT surname = ''")
            ->cols([
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulPerson.title',
                'tawasulPerson.dateStart',
                'tawasulPerson.dateEnd',
                'tawasulPerson.status',
                'tawasulFinanceInvoicee.invoiceTo',
                'tawasulFinanceInvoicee.tawasulFinanceInvoiceeID',
                'tawasulFinanceInvoicee.companyAll',
                "IF(
            tawasulPerson.dateStart <= CURRENT_TIMESTAMP OR
            tawasulPerson.dateStart IS NULL,'Y','N'
          ) AS started",
                "IF(
            tawasulPerson.dateEnd >= CURRENT_TIMESTAMP OR
            tawasulPerson.dateEnd IS NULL,'N','Y'
          ) AS ended"
            ]);

        if (!$criteria->hasFilter('allUsers')) {
            $query->where("tawasulPerson.status = 'Full'")
                    ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
                    ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
                    ->bindValue('today', date('Y-m-d'));
        }


        return $this->runQuery($query, $criteria);
    }

    public function selectStudentsWithNoInvoicee()
    {
        $sql = "SELECT DISTINCT tawasulPerson.tawasulPersonID, surname, preferredName, tawasulFinanceInvoiceeID 
                FROM tawasulPerson 
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) 
                LEFT JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulFinanceInvoiceeID IS NULL";

        return$this->db()->select($sql);
    }
}
