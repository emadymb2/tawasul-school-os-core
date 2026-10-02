<?php

namespace TawasulOS\Domain\Finance;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;

class PettyCashGateway extends QueryableGateway
{
    use TableAware;
    private static $primaryKey = 'tawasulFinancePettyCashID';
    private static $tableName = 'tawasulFinancePettyCash';
    private static $searchableColumns = ['tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.username'];

    public function queryPettyCashBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
        ->newQuery()
        ->cols([
            'tawasulFinancePettyCash.tawasulFinancePettyCashID',
            'tawasulFinancePettyCash.amount',
            'tawasulFinancePettyCash.reason',
            'tawasulFinancePettyCash.notes',
            'tawasulFinancePettyCash.actionRequired',
            'tawasulFinancePettyCash.status',
            'tawasulFinancePettyCash.timestampCreated',
            'tawasulFinancePettyCash.timestampStatus',
            'tawasulPerson.tawasulPersonID',
            'tawasulPerson.preferredName',
            'tawasulPerson.title',
            'tawasulPerson.surname',
            'tawasulRole.category as roleCategory',
            'tawasulFormGroup.nameShort as formGroup',
            '(CASE WHEN tawasulFinancePettyCash.status = "Pending" THEN 0 ELSE 1 END) as statusSort',
          ])
        ->from('tawasulFinancePettyCash')
        ->innerJoin('tawasulPerson', 'tawasulFinancePettyCash.tawasulPersonID = tawasulPerson.tawasulPersonID')
        ->innerJoin('tawasulRole', 'tawasulRole.tawasulRoleID = tawasulPerson.tawasulRoleIDPrimary')
        ->leftJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID = tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID = tawasulFinancePettyCash.tawasulSchoolYearID')
        ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
        ->where('tawasulFinancePettyCash.tawasulSchoolYearID = :tawasulSchoolYearID')
        ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    public function queryPettyCashBalance(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
        ->newQuery()
        ->cols([
            'SUM(tawasulFinancePettyCash.amount) as total',
          ])
        ->from('tawasulFinancePettyCash')
        ->innerJoin('tawasulPerson', 'tawasulFinancePettyCash.tawasulPersonID = tawasulPerson.tawasulPersonID')
        ->where('tawasulFinancePettyCash.status = "Pending"')
        ->where('tawasulFinancePettyCash.actionRequired = "Repay"')
        ->where('tawasulFinancePettyCash.tawasulSchoolYearID = :tawasulSchoolYearID')
        ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    public function selectPettyCashBalanceByStudent($tawasulSchoolYearID)
    {
        $select = $this
        ->newSelect()
        ->cols([ 
            'DATE(tawasulFinancePettyCash.timestampCreated) as date', 
            'SUM(tawasulFinancePettyCash.amount) as amount', 
            'tawasulPerson.tawasulPersonID',
            'tawasulPerson.preferredName as studentPreferredName',
            'tawasulPerson.surname as studentSurname',
            'tawasulPerson.officialName as studentOfficialName',
          ])
        ->from('tawasulFinancePettyCash')
        ->innerJoin('tawasulPerson', 'tawasulFinancePettyCash.tawasulPersonID = tawasulPerson.tawasulPersonID')
        ->innerJoin('tawasulRole', 'tawasulRole.tawasulRoleID = tawasulPerson.tawasulRoleIDPrimary')
        ->where('tawasulFinancePettyCash.status = "Pending"')
        ->where('tawasulFinancePettyCash.actionRequired = "Repay"')
        ->where('tawasulRole.category = "Student"')
        ->where("tawasulPerson.status = 'Full'")
        ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
        ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
        ->where('tawasulFinancePettyCash.tawasulSchoolYearID = :tawasulSchoolYearID')
        ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
        ->where('DATE(tawasulFinancePettyCash.timestampCreated) < :today')
        ->bindValue('today', date('Y-m-d'))
        ->groupBy(['tawasulPerson.tawasulPersonID'])
        ->having('amount > 0');

        return $this->runSelect($select);
    }

    public function selectPettyCashBalanceByStaff($tawasulSchoolYearID)
    {
        $select = $this
        ->newSelect()
        ->cols([ 
            'DATE(tawasulFinancePettyCash.timestampCreated) as date', 
            'SUM(tawasulFinancePettyCash.amount) as amount', 
            'tawasulPerson.tawasulPersonID',
            'tawasulPerson.title',
            'tawasulPerson.preferredName',
            'tawasulPerson.surname',
            'tawasulPerson.email',
          ])
        ->from('tawasulFinancePettyCash')
        ->innerJoin('tawasulPerson', 'tawasulFinancePettyCash.tawasulPersonID = tawasulPerson.tawasulPersonID')
        ->innerJoin('tawasulRole', 'tawasulRole.tawasulRoleID = tawasulPerson.tawasulRoleIDPrimary')
        ->where('tawasulFinancePettyCash.status = "Pending"')
        ->where('tawasulFinancePettyCash.actionRequired = "Repay"')
        ->where('tawasulRole.category = "Staff"')
        ->where("tawasulPerson.status = 'Full'")
        ->where('tawasulFinancePettyCash.tawasulSchoolYearID = :tawasulSchoolYearID')
        ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
        ->where('DATE(tawasulFinancePettyCash.timestampCreated) < :today')
        ->bindValue('today', date('Y-m-d'))
        ->groupBy(['tawasulPerson.tawasulPersonID'])
        ->having('amount > 0');

        return $this->runSelect($select);
    }
}
