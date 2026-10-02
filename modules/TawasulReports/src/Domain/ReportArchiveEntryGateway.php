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

namespace Tos\Module\TawasulReports\Domain;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

class ReportArchiveEntryGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulReportArchiveEntry';
    private static $primaryKey = 'tawasulReportArchiveEntryID';
    private static $searchableColumns = ['tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.username'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryArchiveByReport(QueryCriteria $criteria, $tawasulReportID, $tawasulYearGroupID = '', $tawasulFormGroupID = '', $roleCategory = 'Other', $viewDraft = false, $viewPast = false)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReportArchiveEntry.tawasulReportArchiveEntryID', 'tawasulReportArchiveEntry.tawasulReportID', 'tawasulReportArchiveEntry.tawasulYearGroupID', 'tawasulReportArchiveEntry.tawasulFormGroupID', 'tawasulReportArchiveEntry.filePath', 'tawasulReportArchiveEntry.status', 'tawasulReportArchiveEntry.timestampModified', 'tawasulReportArchiveEntry.timestampSent', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.email', 'tawasulReportArchiveEntry.timestampAccessed', 'parent.title as parentTitle', 'parent.preferredName as parentPreferredName', 'parent.surname as parentSurname'])
            ->innerJoin('tawasulReportArchive', 'tawasulReportArchive.tawasulReportArchiveID=tawasulReportArchiveEntry.tawasulReportArchiveID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulReportArchiveEntry.tawasulPersonID')
            ->leftJoin('tawasulPerson as parent', 'tawasulReportArchiveEntry.tawasulPersonIDAccessed=parent.tawasulPersonID')
            ->leftJoin('tawasulReport', 'tawasulReport.tawasulReportID=tawasulReportArchiveEntry.tawasulReportID')
            ->where("tawasulReportArchiveEntry.type='Single'")
            ->where('(tawasulReportArchiveEntry.tawasulReportID=:tawasulReportID OR tawasulReportArchiveEntry.reportIdentifier=:tawasulReportID)')
            ->bindValue('tawasulReportID', $tawasulReportID);

        $query = $this->applyArchiveAccessLogic($query, $roleCategory, $viewDraft, $viewPast);

        if (!empty($tawasulYearGroupID)) {
            $query->where('tawasulReportArchiveEntry.tawasulYearGroupID=:tawasulYearGroupID')
                  ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
        }
        if (!empty($tawasulFormGroupID) && $tawasulFormGroupID != 'All') {
            $query->where('tawasulReportArchiveEntry.tawasulFormGroupID=:tawasulFormGroupID')
                  ->bindValue('tawasulFormGroupID', $tawasulFormGroupID);
        }

        return $this->runQuery($query, $criteria);
    }

    public function queryArchiveByReportIdentifier(QueryCriteria $criteria, $tawasulSchoolYearID, $reportIdentifier, $tawasulYearGroupID = '', $tawasulFormGroupID = '', $roleCategory = 'Other', $viewDraft = false, $viewPast = false)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReportArchiveEntry.tawasulReportArchiveEntryID', 'tawasulReportArchiveEntry.tawasulReportID', 'tawasulReportArchiveEntry.reportIdentifier', 'tawasulReportArchiveEntry.tawasulYearGroupID', 'tawasulReportArchiveEntry.tawasulFormGroupID', 'tawasulReportArchiveEntry.filePath', 'tawasulYearGroup.sequenceNumber'])
            ->innerJoin('tawasulReportArchive', 'tawasulReportArchive.tawasulReportArchiveID=tawasulReportArchiveEntry.tawasulReportArchiveID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportArchiveEntry.tawasulFormGroupID')
            ->leftJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportArchiveEntry.tawasulYearGroupID')
            ->where('tawasulReportArchiveEntry.reportIdentifier=:reportIdentifier')
            ->bindValue('reportIdentifier', $reportIdentifier)
            ->where('tawasulReportArchiveEntry.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        $query = $this->applyArchiveAccessLogic($query, $roleCategory, $viewDraft, $viewPast);

        if (!empty($tawasulFormGroupID)) {
            $query->cols(['tawasulPerson.tawasulPersonID', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulReportArchiveEntry.timestampAccessed', 'parent.title as parentTitle', 'parent.preferredName as parentPreferredName', 'parent.surname as parentSurname'])
                  ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulReportArchiveEntry.tawasulPersonID')
                  ->leftJoin('tawasulPerson as parent', 'tawasulReportArchiveEntry.tawasulPersonIDAccessed=parent.tawasulPersonID')
                  ->where("tawasulReportArchiveEntry.type='Single'");

            if ($tawasulFormGroupID != 'All') {
                $query->where('tawasulReportArchiveEntry.tawasulFormGroupID=:tawasulFormGroupID')
                  ->bindValue('tawasulFormGroupID', $tawasulFormGroupID);
            }
        } elseif (!empty($tawasulYearGroupID)) {
            $query->cols(['tawasulFormGroup.name AS name', 'COUNT(DISTINCT tawasulReportArchiveEntry.tawasulPersonID) as count', "COUNT(DISTINCT CASE WHEN tawasulReportArchiveEntry.tawasulPersonIDAccessed IS NOT NULL THEN tawasulReportArchiveEntry.tawasulReportArchiveEntryID END) as readCount"])
                  ->where("tawasulReportArchiveEntry.type='Single'")
                  ->where('tawasulReportArchiveEntry.tawasulYearGroupID=:tawasulYearGroupID')
                  ->bindValue('tawasulYearGroupID', $tawasulYearGroupID)
                  ->groupBy(['tawasulReportArchiveEntry.tawasulFormGroupID']);
        } else {
            $query->cols(['tawasulFormGroup.name AS name', 'COUNT(DISTINCT tawasulReportArchiveEntry.tawasulReportArchiveEntryID) AS count'])
                  ->groupBy(['tawasulReportArchiveEntry.tawasulYearGroupID', 'tawasulReportArchiveEntry.tawasulFormGroupID']);
        }

        return $this->runQuery($query, $criteria);
    }

    public function queryArchiveReportsBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $roleCategory = 'Other', $viewDraft = false, $viewPast = false)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReportArchiveEntry.tawasulReportArchiveEntryID', 'tawasulReportArchiveEntry.reportIdentifier', 'MAX(tawasulReportArchiveEntry.timestampModified) as timestampModified', 'tawasulReport.tawasulReportID', 'tawasulReport.name', 'tawasulReportingCycle.sequenceNumber as sequenceNumber', "COUNT(DISTINCT tawasulReportArchiveEntry.tawasulReportArchiveEntryID) AS totalCount", "COUNT(DISTINCT CASE WHEN tawasulReportArchiveEntry.tawasulPersonIDAccessed IS NOT NULL THEN tawasulReportArchiveEntry.tawasulReportArchiveEntryID END) as readCount"])
            ->innerJoin('tawasulReportArchive', 'tawasulReportArchive.tawasulReportArchiveID=tawasulReportArchiveEntry.tawasulReportArchiveID')
            ->leftJoin('tawasulReport', 'tawasulReport.tawasulReportID=tawasulReportArchiveEntry.tawasulReportID')
            ->leftJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReport.tawasulReportingCycleID')
            ->where('tawasulReportArchiveEntry.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulReportArchiveEntry.reportIdentifier']);

        $query = $this->applyArchiveAccessLogic($query, $roleCategory, $viewDraft, $viewPast);

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('tawasulReport.active = :active')
                    ->bindValue('active', $active);
            },
            'reportID' => function ($query, $reportID) {
                return $query
                    ->where('tawasulReport.tawasulReportID > 0');
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryArchiveBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulYearGroupID = '', $tawasulFormGroupID = '', $roleCategory = 'Other', $viewDraft = false, $viewPast = false)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from('tawasulStudentEnrolment')
            ->cols(['tawasulReportArchiveEntry.tawasulReportArchiveEntryID', 'tawasulReportArchiveEntry.tawasulReportID', 'tawasulReportArchiveEntry.tawasulYearGroupID', 'tawasulReportArchiveEntry.tawasulFormGroupID', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.image_240', 'tawasulPerson.status', 'tawasulPerson.dateStart', 'tawasulPerson.dateEnd', "'Student' as roleCategory", "MAX(tawasulStudentEnrolment.tawasulStudentEnrolmentID) as tawasulStudentEnrolmentID", 'MAX(tawasulYearGroup.nameShort) as yearGroup', 'MAX(tawasulFormGroup.nameShort) as formGroup', "COUNT(DISTINCT tawasulReportArchiveEntry.tawasulReportArchiveEntryID) as count"])
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulReportArchiveEntry', 'tawasulReportArchiveEntry.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulReportArchive', 'tawasulReportArchive.tawasulReportArchiveID=tawasulReportArchiveEntry.tawasulReportArchiveID')
            ->leftJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
            ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->leftJoin('tawasulReport', 'tawasulReport.tawasulReportID=tawasulReportArchiveEntry.tawasulReportID')
            ->where("tawasulReportArchiveEntry.type='Single'")
            ->groupBy(['tawasulPerson.tawasulPersonID', 'tawasulReportArchiveEntry.tawasulPersonID']);

        if ($criteria->hasFilter('all')) {
            $query->innerJoin('tawasulRole', 'FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)')
                    ->where("tawasulRole.category='Student'");
        } else {
            $query->where('tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID')
                  ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
                  ->where("tawasulPerson.status <> 'Left'");
        }

        $query = $this->applyArchiveAccessLogic($query, $roleCategory, $viewDraft, $viewPast);

        if (!empty($tawasulYearGroupID)) {
            $query->where('tawasulStudentEnrolment.tawasulYearGroupID=:tawasulYearGroupID')
                  ->where('tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
                  ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
        }
        if (!empty($tawasulFormGroupID)) {
            $query->where('tawasulStudentEnrolment.tawasulFormGroupID=:tawasulFormGroupID')
                  ->where('tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
                  ->bindValue('tawasulFormGroupID', $tawasulFormGroupID);
        }

        $criteria->addFilterRules([
            // 'active' => function ($query, $active) {
            //     return $query
            //         ->where('tawasulReport.active = :active')
            //         ->bindValue('active', $active);
            // },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryArchiveByStudent(QueryCriteria $criteria, $tawasulPersonID, $roleCategory = 'Other', $viewDraft = false, $viewPast = false)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulSchoolYear.name as schoolYear', 'tawasulSchoolYear.sequenceNumber', 'tawasulReportArchiveEntry.tawasulReportArchiveEntryID', 'tawasulReportArchiveEntry.status', 'tawasulReportArchiveEntry.timestampModified', 'tawasulReportArchiveEntry.reportIdentifier', 'tawasulReportArchiveEntry.tawasulReportID', 'tawasulReportArchiveEntry.tawasulYearGroupID', 'tawasulReportArchiveEntry.tawasulFormGroupID', 'tawasulPerson.tawasulPersonID', 'tawasulYearGroup.nameShort as yearGroup', 'tawasulFormGroup.nameShort as formGroup', 'tawasulReport.name as reportName', 'tawasulReportArchiveEntry.timestampAccessed', 'parent.title as parentTitle', 'parent.preferredName as parentPreferredName', 'parent.surname as parentSurname'])
            ->innerJoin('tawasulReportArchive', 'tawasulReportArchive.tawasulReportArchiveID=tawasulReportArchiveEntry.tawasulReportArchiveID')
            ->innerJoin('tawasulSchoolYear', 'tawasulSchoolYear.tawasulSchoolYearID=tawasulReportArchiveEntry.tawasulSchoolYearID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulReportArchiveEntry.tawasulPersonID')
            ->leftJoin('tawasulReport', 'tawasulReport.tawasulReportID=tawasulReportArchiveEntry.tawasulReportID')
            ->leftJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportArchiveEntry.tawasulYearGroupID')
            ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportArchiveEntry.tawasulFormGroupID')
            ->leftJoin('tawasulPerson as parent', 'tawasulReportArchiveEntry.tawasulPersonIDAccessed=parent.tawasulPersonID')
            ->where("tawasulReportArchiveEntry.type='Single'")
            ->where('tawasulReportArchiveEntry.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);

        $query = $this->applyArchiveAccessLogic($query, $roleCategory, $viewDraft, $viewPast);

        return $this->runQuery($query, $criteria);
    }

    public function selectParentArchiveAccessByReportingCycle($tawasulReportingCycleID)
    {
        $tawasulReportingCycleIDList = is_array($tawasulReportingCycleID)? implode(',', $tawasulReportingCycleID) : $tawasulReportingCycleID;

        $query = $this
            ->newSelect()
            ->cols(['parent.tawasulPersonID', 'parent.surname', 'parent.preferredName', 'parent.email'])
            ->from($this->getTableName())
            ->innerJoin('tawasulReportArchive', 'tawasulReportArchive.tawasulReportArchiveID=tawasulReportArchiveEntry.tawasulReportArchiveID')
            ->innerJoin('tawasulReport', 'tawasulReport.tawasulReportID=tawasulReportArchiveEntry.tawasulReportID')
            ->innerJoin('tawasulPerson as student', 'student.tawasulPersonID=tawasulReportArchiveEntry.tawasulPersonID')
            ->innerJoin('tawasulFamilyChild', 'tawasulFamilyChild.tawasulPersonID=student.tawasulPersonID')
            ->innerJoin('tawasulFamilyAdult', 'tawasulFamilyAdult.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID AND tawasulFamilyAdult.contactPriority=1')
            ->innerJoin('tawasulPerson as parent', 'parent.tawasulPersonID=tawasulFamilyAdult.tawasulPersonID')
            ->where('FIND_IN_SET(tawasulReport.tawasulReportingCycleID, :tawasulReportingCycleIDList)')
            ->bindValue('tawasulReportingCycleIDList', $tawasulReportingCycleIDList)
            ->where("tawasulReportArchiveEntry.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID")
            ->where("tawasulReportArchiveEntry.type='Single'")
            ->where("tawasulReportArchiveEntry.status='Final'")
            ->where("tawasulReportArchive.viewableParents='Y'")
            ->where("tawasulFamilyAdult.childDataAccess='Y'")
            ->where("student.status='Full'")
            ->where("parent.status='Full'")
            ->where("parent.email<>''")
            ->where("parent.receiveNotificationEmails='Y'")
            ->where("(tawasulReport.accessDate IS NULL OR tawasulReport.accessDate <= :currentDateTime)")
            ->bindValue('currentDateTime', date('Y-m-d H:i:s'));

        return $this->runSelect($query);
    }

    public function getRecentArchiveEntryByReport($tawasulReportID, $type, $contextID, $roleCategory = 'Other', $viewDraft = false, $viewPast = false)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['tawasulReportArchiveEntry.*'])
            ->innerJoin('tawasulReportArchive', 'tawasulReportArchive.tawasulReportArchiveID=tawasulReportArchiveEntry.tawasulReportArchiveID')
            ->leftJoin('tawasulReport', 'tawasulReport.tawasulReportID=tawasulReportArchiveEntry.tawasulReportID')
            ->where('tawasulReportArchiveEntry.tawasulReportID=:tawasulReportID')
            ->bindValue('tawasulReportID', $tawasulReportID)
            ->where('tawasulReportArchiveEntry.type=:type')
            ->bindValue('type', $type)
            ->orderBy(['tawasulReportArchiveEntry.timestampModified DESC'])
            ->limit(1);

        $query = $this->applyArchiveAccessLogic($query, $roleCategory, $viewDraft, $viewPast);

        if ($type == 'Batch') {
            $query->where('tawasulReportArchiveEntry.tawasulYearGroupID=:contextID')
                  ->bindValue('contextID', $contextID);
        } else {
            $query->where('tawasulReportArchiveEntry.tawasulPersonID=:contextID')
                  ->bindValue('contextID', $contextID);
        }

        return $this->runSelect($query)->fetch();
    }

    public function getRecentArchiveEntryByReportIdentifier($tawasulSchoolYearID, $reportIdentifier, $type, $contextID, $roleCategory = 'Other', $viewDraft = false, $viewPast = false)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['tawasulReportArchiveEntry.*'])
            ->innerJoin('tawasulReportArchive', 'tawasulReportArchive.tawasulReportArchiveID=tawasulReportArchiveEntry.tawasulReportArchiveID')
            ->leftJoin('tawasulReport', 'tawasulReport.tawasulReportID=tawasulReportArchiveEntry.tawasulReportID')
            ->where('tawasulReportArchiveEntry.reportIdentifier=:reportIdentifier')
            ->bindValue('reportIdentifier', $reportIdentifier)
            ->where('tawasulReportArchiveEntry.type=:type')
            ->bindValue('type', $type)
            ->where('tawasulReportArchiveEntry.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->orderBy(['tawasulReportArchiveEntry.timestampModified DESC'])
            ->limit(1);

        $query = $this->applyArchiveAccessLogic($query, $roleCategory, $viewDraft, $viewPast);

        if ($type == 'Batch') {
            $query->where('tawasulReportArchiveEntry.tawasulYearGroupID=:contextID')
                  ->bindValue('contextID', $contextID);
        } else {
            $query->where('tawasulReportArchiveEntry.tawasulPersonID=:contextID')
                  ->bindValue('contextID', $contextID);
        }

        return $this->runSelect($query)->fetch();
    }

    public function selectArchiveEntriesByReportIdentifier($tawasulSchoolYearID, $reportIdentifier)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['tawasulReportArchiveEntry.tawasulPersonID as groupBy', 'tawasulReportArchiveEntry.*'])
            ->where('tawasulReportArchiveEntry.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulReportArchiveEntry.reportIdentifier=:reportIdentifier')
            ->bindValue('reportIdentifier', $reportIdentifier)
            ->where("tawasulReportArchiveEntry.type='Single'");

        return $this->runSelect($query);
    }

    public function selectArchiveEntryByAccessToken($tawasulReportArchiveEntryID, $accessToken)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['tawasulReportArchiveEntry.*'])
            ->where('CURRENT_TIMESTAMP <= tawasulReportArchiveEntry.timestampAccessExpiry')
            ->where('tawasulReportArchiveEntry.tawasulReportArchiveEntryID=:tawasulReportArchiveEntryID')
            ->bindValue('tawasulReportArchiveEntryID', $tawasulReportArchiveEntryID)
            ->where('tawasulReportArchiveEntry.accessToken=:accessToken')
            ->bindValue('accessToken', $accessToken)
            ->where("tawasulReportArchiveEntry.type='Single'")
            ->where("tawasulReportArchiveEntry.status='Final'");

        return $this->runSelect($query);
    }

    protected function applyArchiveAccessLogic(&$query, $roleCategory = 'Other', $viewDraft = false, $viewPast = false)
    {
        if (!$viewDraft) {
            $query->where("tawasulReportArchiveEntry.status='Final'")
                  ->where("(tawasulReport.tawasulReportID IS NULL OR tawasulReport.accessDate <= :currentDateTime)")
                  ->bindValue('currentDateTime', date('Y-m-d H:i:s'));
        }

        if (!$viewPast) {
            $query->where("tawasulReportArchiveEntry.tawasulSchoolYearID=(SELECT tawasulSchoolYearID FROM tawasulSchoolYear WHERE status='Current')");
        }

        if ($roleCategory == 'Staff') {
            $query->where("tawasulReportArchive.viewableStaff='Y'");
        } elseif ($roleCategory == 'Student') {
            $query->where("tawasulReportArchive.viewableStudents='Y'");
        } elseif ($roleCategory == 'Parent') {
            $query->where("tawasulReportArchive.viewableParents='Y'");
        } else {
            $query->where("tawasulReportArchive.viewableOther='Y'");
        }

        return $query;
    }
}
