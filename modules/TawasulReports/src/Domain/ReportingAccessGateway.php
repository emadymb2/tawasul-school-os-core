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

class ReportingAccessGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulReportingAccess';
    private static $primaryKey = 'tawasulReportingAccessID';
    private static $searchableColumns = [''];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryReportingAccessBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols(['tawasulReportingAccess.tawasulReportingAccessID', 'tawasulReportingCycle.name as reportingCycle', "GROUP_CONCAT(DISTINCT tawasulRole.name ORDER BY tawasulRole.type, tawasulRole.name SEPARATOR '<br/>') as roleName", "GROUP_CONCAT(DISTINCT tawasulReportingScope.name ORDER BY tawasulReportingScope.sequenceNumber SEPARATOR '<br/>') as scopeName", 'tawasulReportingAccess.dateStart', 'tawasulReportingAccess.dateEnd', 'tawasulReportingCycle.dateStart as cycleDateStart', 'tawasulReportingCycle.dateEnd as cycleDateEnd'])
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingAccess.tawasulReportingCycleID')
            ->leftJoin('tawasulReportingScope', 'FIND_IN_SET(tawasulReportingScope.tawasulReportingScopeID, tawasulReportingAccess.tawasulReportingScopeIDList)')
            ->leftJoin('tawasulRole', 'FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulReportingAccess.tawasulRoleIDList)')
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulReportingAccess.tawasulReportingAccessID']);

        $criteria->addFilterRules([
            'reportingCycle' => function ($query, $tawasulReportingCycleID) {
                return $query
                    ->where('tawasulReportingCycle.tawasulReportingCycleID = :tawasulReportingCycleID')
                    ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryActiveReportingCyclesByPerson(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols(['tawasulReportingCycle.tawasulReportingCycleID', 'tawasulReportingCycle.name', 'tawasulReportingCycle.dateStart', 'tawasulReportingCycle.dateEnd', 'tawasulReportingCycle.milestones', 'tawasulReportingAccess.canWrite', 'tawasulReportingAccess.canProofRead'])
            ->innerJoin('tawasulReportingAccess', "(
                (tawasulReportingAccess.accessType='Person' AND FIND_IN_SET(tawasulPerson.tawasulPersonID, tawasulReportingAccess.tawasulPersonIDList)) OR (tawasulReportingAccess.accessType='Role' AND FIND_IN_SET(tawasulPerson.tawasulRoleIDPrimary, tawasulReportingAccess.tawasulRoleIDList))
            )")
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingAccess.tawasulReportingCycleID')
            ->where('tawasulPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('(:today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd)')
            ->where('(:today BETWEEN tawasulReportingAccess.dateStart AND tawasulReportingAccess.dateEnd)')
            ->bindValue('today', date('Y-m-d'))
            ->groupBy(['tawasulReportingCycle.tawasulReportingCycleID']);

        return $this->runQuery($query, $criteria);
    }

    public function queryActiveReportingScopesByPerson(QueryCriteria $criteria, $tawasulReportingCycleID, $tawasulPersonID)
    {
        $tawasulReportingCycleIDList = is_array($tawasulReportingCycleID)? $tawasulReportingCycleID : [$tawasulReportingCycleID];
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols(['tawasulReportingScope.tawasulReportingScopeID', 'tawasulReportingScope.name', 'MIN(tawasulReportingAccess.dateStart) as dateStart', 'MAX(tawasulReportingAccess.dateEnd) as dateEnd', 'tawasulReportingAccess.canWrite', 'tawasulReportingAccess.canProofRead'])
            ->innerJoin('tawasulReportingAccess', "(
                (tawasulReportingAccess.accessType='Person' AND FIND_IN_SET(tawasulPerson.tawasulPersonID, tawasulReportingAccess.tawasulPersonIDList)) OR (tawasulReportingAccess.accessType='Role' AND FIND_IN_SET(tawasulPerson.tawasulRoleIDPrimary, tawasulReportingAccess.tawasulRoleIDList))
            )")
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingAccess.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID AND FIND_IN_SET(tawasulReportingScope.tawasulReportingScopeID, tawasulReportingAccess.tawasulReportingScopeIDList)')
            ->where('tawasulPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('FIND_IN_SET(tawasulReportingCycle.tawasulReportingCycleID, :tawasulReportingCycleIDList)')
            ->bindValue('tawasulReportingCycleIDList', implode(',', $tawasulReportingCycleIDList))
            ->where('(:today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd)')
            ->where('(:today BETWEEN tawasulReportingAccess.dateStart AND tawasulReportingAccess.dateEnd)')
            ->bindValue('today', date('Y-m-d'))
            ->groupBy(['tawasulReportingScope.tawasulReportingScopeID']);

        return $this->runQuery($query, $criteria);
    }

    public function queryActiveCriteriaGroupsByPerson(QueryCriteria $criteria, $tawasulReportingScopeID, $tawasulPersonID, $allStudents = false)
    {
        $onlyFullStudents = !$allStudents
            ? "AND student.status='Full' AND (student.dateStart IS NULL OR student.dateStart<=:today) AND (student.dateEnd IS NULL OR student.dateEnd>=:today)"
            : "";

        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols(["LPAD(tawasulCourseClass.tawasulCourseClassID, 8, '0')  as scopeTypeID", 'tawasulCourse.name as criteriaName', "CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as criteriaNameShort",
            "(SELECT COUNT(*) FROM tawasulReportingCriteria as criteria WHERE criteria.tawasulReportingScopeID=:tawasulReportingScopeID AND criteria.target='Per Student') as targetCount",
            "(SELECT COUNT(*) FROM tawasulCourseClassPerson as students JOIN tawasulPerson as student ON (student.tawasulPersonID=students.tawasulPersonID) JOIN tawasulStudentEnrolment as enrolment ON (student.tawasulPersonID=enrolment.tawasulPersonID) WHERE enrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID AND FIND_IN_SET(enrolment.tawasulYearGroupID, tawasulReportingCycle.tawasulYearGroupIDList) AND students.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND students.role='Student' AND students.reportable='Y' $onlyFullStudents) as totalCount",
            "(SELECT COUNT(*) FROM tawasulReportingProgress as progress JOIN tawasulPerson AS student ON (student.tawasulPersonID=progress.tawasulPersonIDStudent) JOIN tawasulCourseClassPerson AS students ON (students.tawasulCourseClassID=progress.tawasulCourseClassID AND student.tawasulPersonID=students.tawasulPersonID) WHERE progress.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND progress.tawasulReportingScopeID=:tawasulReportingScopeID AND progress.status='Complete' AND students.role='Student' $onlyFullStudents) as progressCount",
            "(SELECT COUNT(*) FROM tawasulReportingProgress as progress JOIN tawasulPerson AS student ON (student.tawasulPersonID=progress.tawasulPersonIDStudent) JOIN tawasulCourseClassPerson AS students ON (students.tawasulCourseClassID=progress.tawasulCourseClassID AND student.tawasulPersonID=students.tawasulPersonID) WHERE progress.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND progress.tawasulReportingScopeID=:tawasulReportingScopeID AND progress.status='Complete' AND (student.status='Left' OR student.dateStart>:today OR student.dateEnd<:today OR students.role='Student - Left')) as leftCount"])
            ->innerJoin('tawasulCourseClassPerson', 'tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingCriteria.tawasulReportingCycleID')
            ->where("(tawasulCourseClassPerson.role='Teacher' OR tawasulCourseClassPerson.role='Assistant')")
            ->where("tawasulCourseClassPerson.reportable='Y'")
            ->where("tawasulCourseClass.reportable='Y'")
            ->where('tawasulPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulReportingCriteria.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->having('totalCount > 0')
            ->groupBy(['tawasulCourse.tawasulCourseID', 'tawasulCourseClass.tawasulCourseClassID']);

        if (!$allStudents) $query->bindValue('today', date('Y-m-d'));

        $query->unionAll()
            ->from('tawasulPerson')
            ->cols(["LPAD(tawasulYearGroup.tawasulYearGroupID, 3, '0') as scopeTypeID", 'tawasulYearGroup.name as criteriaName', 'tawasulYearGroup.nameShort as criteriaNameShort',
            "(SELECT COUNT(*) FROM tawasulReportingCriteria as criteria WHERE criteria.tawasulReportingScopeID=:tawasulReportingScopeID AND criteria.target='Per Student') as targetCount",
            "(SELECT COUNT(*) FROM tawasulStudentEnrolment as enrolment JOIN tawasulPerson as student ON (student.tawasulPersonID=enrolment.tawasulPersonID) WHERE enrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID AND FIND_IN_SET(enrolment.tawasulYearGroupID, tawasulReportingCycle.tawasulYearGroupIDList) AND enrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID $onlyFullStudents) as totalCount",
            "(SELECT COUNT(*) FROM tawasulReportingProgress as progress JOIN tawasulPerson AS student ON (student.tawasulPersonID=progress.tawasulPersonIDStudent) WHERE progress.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID AND progress.tawasulReportingScopeID=:tawasulReportingScopeID AND progress.status='Complete' $onlyFullStudents) as progressCount",
            "(SELECT COUNT(*) FROM tawasulReportingProgress as progress JOIN tawasulPerson AS student ON (student.tawasulPersonID=progress.tawasulPersonIDStudent) WHERE progress.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID AND progress.tawasulReportingScopeID=:tawasulReportingScopeID AND progress.status='Complete' AND (student.status='Left' OR student.dateStart>:today OR student.dateEnd<:today)) as leftCount"
            ])
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulPersonIDHOY=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingCriteria.tawasulReportingCycleID')
            ->where('tawasulPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulReportingCriteria.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->having('totalCount > 0')
            ->groupBy(['tawasulYearGroup.tawasulYearGroupID']);

        if (!$allStudents) $query->bindValue('today', date('Y-m-d'));

        $query->unionAll()
            ->from('tawasulPerson')
            ->cols(["LPAD(tawasulFormGroup.tawasulFormGroupID, 5, '0') as scopeTypeID", 'tawasulFormGroup.name as criteriaName', 'tawasulFormGroup.nameShort as criteriaNameShort',
            "(SELECT COUNT(*) FROM tawasulReportingCriteria as criteria WHERE criteria.tawasulReportingScopeID=:tawasulReportingScopeID AND criteria.target='Per Student') as targetCount",
            "(SELECT COUNT(*) FROM tawasulStudentEnrolment as enrolment JOIN tawasulPerson as student ON (student.tawasulPersonID=enrolment.tawasulPersonID) WHERE enrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID AND FIND_IN_SET(enrolment.tawasulYearGroupID, tawasulReportingCycle.tawasulYearGroupIDList)AND enrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID $onlyFullStudents) as totalCount",
            "(SELECT COUNT(*) FROM tawasulReportingProgress as progress JOIN tawasulPerson AS student ON (student.tawasulPersonID=progress.tawasulPersonIDStudent) WHERE progress.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID AND progress.tawasulReportingScopeID=:tawasulReportingScopeID AND progress.status='Complete' $onlyFullStudents) as progressCount",
            "(SELECT COUNT(*) FROM tawasulReportingProgress as progress JOIN tawasulPerson AS student ON (student.tawasulPersonID=progress.tawasulPersonIDStudent) WHERE progress.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID AND progress.tawasulReportingScopeID=:tawasulReportingScopeID AND progress.status='Complete' AND (student.status='Left' OR student.dateStart>:today OR student.dateEnd<:today)) as leftCount"])
            ->innerJoin('tawasulFormGroup', '(tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID)')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingCriteria.tawasulReportingCycleID')
            ->where('tawasulPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulReportingCriteria.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->having('totalCount > 0')
            ->groupBy(['tawasulFormGroup.tawasulFormGroupID']);

        if (!$allStudents) $query->bindValue('today', date('Y-m-d'));

        return $this->runQuery($query, $criteria);
    }


    public function selectAccessibleFormGroupsByReportingScope($tawasulReportingScopeID)
    {
        $query = $this
            ->newSelect()
            ->from('tawasulReportingScope')
            ->cols(['tawasulFormGroup.tawasulFormGroupID', 'tawasulFormGroup.name'])
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingScope.tawasulReportingCycleID')
            ->innerJoin('tawasulStudentEnrolment', 'FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, tawasulReportingCycle.tawasulYearGroupIDList) AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->groupBy(['tawasulFormGroup.tawasulFormGroupID'])
            ->orderBy(['LENGTH(tawasulFormGroup.name)', 'tawasulFormGroup.name']);

        return $this->runSelect($query);
    }

    public function selectAccessibleStaffByReportingScope($tawasulReportingScopeID)
    {
        // COURSE
        $query = $this
            ->newSelect()
            ->distinct()
            ->from('tawasulReportingScope')
            ->cols(['tawasulPerson.tawasulPersonID', 'tawasulPerson.surname', 'tawasulPerson.preferredName'])
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->innerJoin('tawasulCourseClassPerson', 'tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')
            ->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->where("(tawasulCourseClassPerson.role='Teacher' OR tawasulCourseClassPerson.role='Assistant')")
            ->where("tawasulCourseClassPerson.reportable='Y'")
            ->where("tawasulCourseClass.reportable='Y'")
            ->where("tawasulPerson.status='Full'")
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID);

        // FORM GROUP
        $query->unionAll()
            ->distinct()
            ->from('tawasulReportingScope')
            ->cols(['tawasulPerson.tawasulPersonID', 'tawasulPerson.surname', 'tawasulPerson.preferredName'])
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID')
            ->innerJoin('tawasulPerson', '(tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID)')
            ->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->where("tawasulPerson.status='Full'")
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID);

        // YEAR GROUP
        $query->unionAll()
            ->distinct()
            ->from('tawasulReportingScope')
            ->cols(['tawasulPerson.tawasulPersonID', 'tawasulPerson.surname', 'tawasulPerson.preferredName'])
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID')
            ->innerJoin('tawasulPerson', 'tawasulYearGroup.tawasulPersonIDHOY=tawasulPerson.tawasulPersonID')
            ->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->where("tawasulPerson.status='Full'")
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID);

        $query->orderBy(['surname', 'preferredName']);

        return $this->runSelect($query);
    }

    public function selectReportingDetailsByScope($tawasulReportingScopeID, $scopeType, $scopeTypeID)
    {
        $query = $this
            ->newSelect()
            ->from('tawasulReportingScope')
            ->cols(['tawasulReportingScope.name as scopeName'])
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->bindValue('scopeTypeID', $scopeTypeID)
            ->groupBy(['tawasulReportingScope.tawasulReportingScopeID']);

        if ($scopeType == 'Year Group') {
            $query->cols(['tawasulYearGroup.name as name', 'tawasulYearGroup.nameShort as nameShort'])
                ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID')
                ->where('tawasulYearGroup.tawasulYearGroupID=:scopeTypeID');
        } elseif ($scopeType == 'Form Group') {
            $query->cols(['tawasulFormGroup.name as name', 'tawasulFormGroup.nameShort as nameShort'])
                ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID')
                ->where('tawasulFormGroup.tawasulFormGroupID=:scopeTypeID');
        } elseif ($scopeType == 'Course') {
            $query->cols(['tawasulCourse.name', "CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as nameShort"])
                ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
                ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
                ->where('tawasulCourseClass.tawasulCourseClassID=:scopeTypeID');
        }

        return $this->runSelect($query);
    }

    public function selectReportingProgressByScope($tawasulReportingScopeID, $scopeType, $scopeTypeID, $allStudents = false)
    {
        $query = $this
            ->newSelect()
            ->from('tawasulReportingCriteria')
            ->cols(['tawasulPerson.tawasulPersonID', 'tawasulReportingProgress.tawasulReportingProgressID', 'tawasulReportingProgress.status as progress', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.image_240', 'tawasulPerson.status'])
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingCriteria.tawasulReportingCycleID')
            ->bindValue('scopeTypeID', $scopeTypeID)
            ->where('tawasulReportingCriteria.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->where("tawasulReportingCriteria.target='Per Student'")
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->orderBy(['tawasulPerson.surname', 'tawasulPerson.preferredName']);

        if (!$allStudents) {
            $query->where("tawasulPerson.status='Full'")
                ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)')
                ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)')
                ->bindValue('today', date('Y-m-d'));
        }

        if ($scopeType == 'Year Group') {
            $query->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID')
                ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
                ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID AND tawasulReportingProgress.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID')
                ->where('tawasulStudentEnrolment.tawasulYearGroupID=:scopeTypeID')
                ->groupBy(['tawasulStudentEnrolment.tawasulPersonID']);
        } elseif ($scopeType == 'Form Group') {
            $query->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID')
                ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
                ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID AND tawasulReportingProgress.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID')
                ->where('tawasulStudentEnrolment.tawasulFormGroupID=:scopeTypeID')
                ->groupBy(['tawasulStudentEnrolment.tawasulPersonID']);
        } elseif ($scopeType == 'Course') {
            $query->cols(['tawasulCourseClassPerson.role'])
                ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
                ->innerJoin('tawasulCourseClassPerson', "tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND ".($allStudents ? "(tawasulCourseClassPerson.role='Student' OR tawasulCourseClassPerson.role='Student - Left')" : "tawasulCourseClassPerson.role='Student'"))
                ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')
                ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID AND tawasulReportingProgress.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulPerson.tawasulPersonID')
                ->where('tawasulCourseClass.tawasulCourseClassID=:scopeTypeID')
                ->where("tawasulCourseClassPerson.reportable='Y'")
                ->where("tawasulCourseClass.reportable='Y'")
                ->groupBy(['tawasulCourseClassPerson.tawasulCourseClassPersonID']);

                if (!$allStudents) {
                    $query->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID AND tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID')
                    ->where('FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, tawasulReportingCycle.tawasulYearGroupIDList)');
                }
        }

        return $this->runSelect($query);
    }

    public function selectReportingCriteriaByStudent($tawasulReportingCycleID, $tawasulPersonIDStudent)
    {
        // YEAR GROUP
        $query = $this
            ->newSelect()
            ->from('tawasulReportingCycle')
            ->cols(['tawasulReportingScope.tawasulReportingScopeID  as groupBy', 'tawasulReportingScope.name as scopeName', '0 as orderBy',
            'tawasulReportingCriteria.tawasulReportingCriteriaID', 'tawasulReportingCriteria.name', 'tawasulReportingCriteria.description', 'tawasulReportingCriteria.category', 'tawasulReportingCriteriaType.name as criteriaName', 'tawasulReportingCriteriaType.valueType', 'tawasulReportingCriteriaType.characterLimit', 'tawasulReportingCriteriaType.tawasulScaleID', 'tawasulReportingValue.tawasulScaleGradeID', "(CASE WHEN tawasulReportingCriteriaType.valueType='Grade Scale' THEN tawasulScaleGrade.descriptor ELSE tawasulReportingValue.value END) as value", 'tawasulReportingValue.comment', 'tawasulReportingProgress.status as progress',
            'created.title', 'created.preferredName', 'created.surname', 'tawasulReportingScope.sequenceNumber as scopeSequence', 'tawasulReportingCriteria.sequenceNumber as criteriaSequence', 'tawasulReportingCriteria.target as criteriaTarget', 'NULL as teachers'])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID
                AND tawasulReportingCriteria.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->leftJoin('tawasulReportingValue', "tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID
                AND (tawasulReportingCriteria.target='Per Group' OR (tawasulReportingValue.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID) AND tawasulReportingCriteria.target='Per Student')
                ")
            ->leftJoin('tawasulScaleGrade', 'tawasulScaleGrade.tawasulScaleID=tawasulReportingCriteriaType.tawasulScaleID AND tawasulReportingValue.tawasulScaleGradeID=tawasulScaleGrade.tawasulScaleGradeID')
            ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID
                AND tawasulReportingProgress.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID
                AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
            ->leftJoin('tawasulPerson as created', 'tawasulReportingValue.tawasulPersonIDCreated=created.tawasulPersonID')
            ->where('tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonIDStudent')
            ->bindValue('tawasulPersonIDStudent', $tawasulPersonIDStudent)
            ->where('tawasulReportingCriteria.tawasulReportingCycleID=:tawasulReportingCycleID')
            ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID)
            ->where("tawasulReportingScope.scopeType = 'Year Group'")
            ->where("tawasulReportingCriteriaType.valueType <> 'Remark'");

        // FORM GROUP
        $query->unionAll()
            ->from('tawasulReportingCycle')
            ->cols(['tawasulReportingScope.tawasulReportingScopeID  as groupBy', 'tawasulReportingScope.name as scopeName', '0 as orderBy',
            'tawasulReportingCriteria.tawasulReportingCriteriaID', 'tawasulReportingCriteria.name', 'tawasulReportingCriteria.description', 'tawasulReportingCriteria.category', 'tawasulReportingCriteriaType.name as criteriaName', 'tawasulReportingCriteriaType.valueType', 'tawasulReportingCriteriaType.characterLimit', 'tawasulReportingCriteriaType.tawasulScaleID', 'tawasulReportingValue.tawasulScaleGradeID', "(CASE WHEN tawasulReportingCriteriaType.valueType='Grade Scale' THEN tawasulScaleGrade.descriptor ELSE tawasulReportingValue.value END) as value", 'tawasulReportingValue.comment', 'tawasulReportingProgress.status as progress',
            'created.title', 'created.preferredName', 'created.surname', 'tawasulReportingScope.sequenceNumber as scopeSequence', 'tawasulReportingCriteria.sequenceNumber as criteriaSequence', 'tawasulReportingCriteria.target as criteriaTarget', 'NULL as teachers'])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID
                AND tawasulReportingCriteria.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->leftJoin('tawasulReportingValue', "tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID
                AND (tawasulReportingCriteria.target='Per Group' OR (tawasulReportingValue.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID) AND tawasulReportingCriteria.target='Per Student')
                ")
            ->leftJoin('tawasulScaleGrade', 'tawasulScaleGrade.tawasulScaleID=tawasulReportingCriteriaType.tawasulScaleID AND tawasulReportingValue.tawasulScaleGradeID=tawasulScaleGrade.tawasulScaleGradeID')
            ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID
                AND tawasulReportingProgress.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID
                AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
            ->leftJoin('tawasulPerson as created', 'tawasulReportingValue.tawasulPersonIDCreated=created.tawasulPersonID')
            ->where('tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonIDStudent')
            ->bindValue('tawasulPersonIDStudent', $tawasulPersonIDStudent)
            ->where('tawasulReportingCriteria.tawasulReportingCycleID=:tawasulReportingCycleID')
            ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID)
            ->where("tawasulReportingScope.scopeType = 'Form Group'")
            ->where("tawasulReportingCriteriaType.valueType <> 'Remark'");

        // COURSE
        $query->unionAll()
            ->from('tawasulReportingCycle')
            ->cols(['tawasulCourse.tawasulCourseID as groupBy', 'tawasulCourse.name as scopeName', 'tawasulCourse.orderBy as orderBy',
            'tawasulReportingCriteria.tawasulReportingCriteriaID', 'tawasulReportingCriteria.name', 'tawasulReportingCriteria.description', 'tawasulReportingCriteria.category', 'tawasulReportingCriteriaType.name as criteriaName', 'tawasulReportingCriteriaType.valueType', 'tawasulReportingCriteriaType.characterLimit', 'tawasulReportingCriteriaType.tawasulScaleID', 'tawasulReportingValue.tawasulScaleGradeID', "(CASE WHEN tawasulReportingCriteriaType.valueType='Grade Scale' THEN tawasulScaleGrade.descriptor ELSE tawasulReportingValue.value END) as value", 'tawasulReportingValue.comment', 'tawasulReportingProgress.status as progress', 'editor.title', 'editor.preferredName', 'editor.surname', 'tawasulReportingScope.sequenceNumber as scopeSequence', 'tawasulReportingCriteria.sequenceNumber as criteriaSequence', 'tawasulReportingCriteria.target as criteriaTarget', "GROUP_CONCAT(CONCAT(teacher.preferredName, ' ', teacher.surname) ORDER BY teacher.surname SEPARATOR ', ' ) AS teachers"])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID
                AND tawasulReportingCriteria.tawasulCourseID IS NOT NULL')
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
            ->innerJoin('tawasulCourseClass', "tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID")
            ->innerJoin('tawasulCourseClassPerson', 'tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->leftJoin('tawasulReportingValue', "tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID
                AND tawasulCourseClass.tawasulCourseClassID=tawasulReportingValue.tawasulCourseClassID
                AND (tawasulReportingCriteria.target='Per Group' OR (tawasulReportingValue.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID) AND tawasulReportingCriteria.target='Per Student')
                ")
            ->leftJoin('tawasulScaleGrade', 'tawasulScaleGrade.tawasulScaleID=tawasulReportingCriteriaType.tawasulScaleID AND tawasulReportingValue.tawasulScaleGradeID=tawasulScaleGrade.tawasulScaleGradeID')
            ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID
                AND tawasulReportingProgress.tawasulCourseClassID=tawasulReportingValue.tawasulCourseClassID
                AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
            ->leftJoin('tawasulPerson as editor', 'tawasulReportingValue.tawasulPersonIDModified=editor.tawasulPersonID')
            ->leftJoin('tawasulCourseClassPerson AS teachers', "teachers.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND teachers.role='Teacher'")
            ->leftJoin('tawasulPerson as teacher', 'teachers.tawasulPersonID=teacher.tawasulPersonID')
            ->where('tawasulCourseClassPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->where("(tawasulCourseClassPerson.role='Student' OR (tawasulCourseClassPerson.role='Student - Left' AND tawasulReportingValue.tawasulReportingValueID IS NOT NULL AND (tawasulReportingValue.value IS NOT NULL OR tawasulReportingValue.comment <> '') ))")
            ->where("tawasulCourseClassPerson.reportable='Y'")
            ->where("tawasulCourseClass.reportable='Y'")
            ->where('tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonIDStudent')
            ->bindValue('tawasulPersonIDStudent', $tawasulPersonIDStudent)
            ->where('tawasulReportingCriteria.tawasulReportingCycleID=:tawasulReportingCycleID')
            ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID)
            ->where("tawasulReportingCriteriaType.valueType <> 'Remark'")
            ->where("tawasulReportingScope.scopeType = 'Course'")
            ->groupBy(['tawasulReportingCriteria.tawasulReportingCriteriaID', 'tawasulCourseClass.tawasulCourseClassID']);

        $query->orderBy([
            'scopeSequence',
            'orderBy',
            'criteriaTarget DESC',
            'criteriaSequence',
            'tawasulReportingCriteriaID']);

        return $this->runSelect($query);
    }

    public function selectReportingCriteriaByStudentAndScope($tawasulReportingScopeID, $scopeType, $scopeTypeID, $tawasulPersonIDStudent)
    {
        $query = $this
            ->newSelect()
            ->from('tawasulReportingCriteria')
            ->cols(['tawasulReportingCriteria.tawasulReportingCriteriaID', 'tawasulReportingCriteria.name', 'tawasulReportingCriteria.description', 'tawasulReportingCriteria.category', 'tawasulReportingCriteriaType.name as criteriaName', 'tawasulReportingCriteriaType.valueType', 'tawasulReportingCriteriaType.defaultValue', 'tawasulReportingCriteriaType.characterLimit', 'tawasulReportingCriteriaType.tawasulScaleID', 'tawasulReportingValue.tawasulReportingValueID', 'tawasulReportingValue.tawasulScaleGradeID', 'tawasulReportingValue.value', 'tawasulReportingValue.comment', 
            'tawasulReportingValue.tawasulReportingValueID', 'tawasulReportingValue.tawasulPersonIDCreated', 'tawasulReportingValue.tawasulPersonIDModified'])
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->leftJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID AND tawasulReportingValue.tawasulPersonIDStudent=:tawasulPersonIDStudent')
            ->where('tawasulReportingCriteria.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->where("tawasulReportingCriteria.target='Per Student'")
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->bindValue('tawasulPersonIDStudent', $tawasulPersonIDStudent)
            ->bindValue('scopeTypeID', $scopeTypeID)
            ->orderBy(['tawasulReportingCriteria.sequenceNumber', 'tawasulReportingCriteria.tawasulReportingCriteriaID']);

        if ($scopeType == 'Year Group') {
            $query->where('tawasulReportingCriteria.tawasulYearGroupID=:scopeTypeID');
        } elseif ($scopeType == 'Form Group') {
            $query->where('tawasulReportingCriteria.tawasulFormGroupID=:scopeTypeID');
        } elseif ($scopeType == 'Course') {
            $query->leftJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
                ->leftJoin('tawasulCourseClassPerson', 'tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
                ->where('tawasulCourseClass.tawasulCourseClassID=:scopeTypeID')
                ->where('tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonIDStudent')
                ->where("(tawasulCourseClassPerson.role='Student' OR tawasulCourseClassPerson.role='Student - Left')");
        }

        return $this->runSelect($query);
    }

    public function selectAllRemarksByStudent($tawasulReportingCycleID, $tawasulPersonIDStudent)
    {
        $query = $this
            ->newSelect()
            ->from('tawasulReportingCycle')
            ->cols(['tawasulReportingCriteria.tawasulReportingCriteriaID', 'tawasulReportingCriteria.name', 'tawasulReportingCriteria.description', 'tawasulReportingCriteria.category', 'tawasulReportingCriteriaType.name as criteriaName', 'tawasulReportingValue.comment', 'tawasulReportingValue.timestampModified', 'created.title', 'created.preferredName', 'created.surname', 'created.image_240', 'tawasulReportingProgress.status as progress', 'tawasulReportingScope.tawasulReportingScopeID', 'tawasulReportingScope.scopeType', "(CASE WHEN tawasulReportingCriteria.tawasulYearGroupID IS NOT NULL THEN tawasulReportingCriteria.tawasulYearGroupID WHEN tawasulReportingCriteria.tawasulFormGroupID IS NOT NULL THEN tawasulReportingCriteria.tawasulFormGroupID ELSE tawasulReportingValue.tawasulCourseClassID END) AS scopeTypeID"])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID
                AND (tawasulReportingCriteria.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID
                OR tawasulReportingCriteria.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID
                OR tawasulReportingCriteria.tawasulCourseID IS NOT NULL)')
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->leftJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID AND tawasulReportingValue.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID')
            ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID
                AND (tawasulReportingProgress.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID
                    OR tawasulReportingProgress.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID
                    OR tawasulReportingProgress.tawasulCourseClassID=tawasulReportingValue.tawasulCourseClassID)
                AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
            ->leftJoin('tawasulPerson as created', 'tawasulReportingValue.tawasulPersonIDCreated=created.tawasulPersonID')
            ->where('tawasulReportingCriteria.tawasulReportingCycleID=:tawasulReportingCycleID')
            ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID)
            ->where("tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonIDStudent")
            ->bindValue('tawasulPersonIDStudent', $tawasulPersonIDStudent)
            ->where("tawasulReportingCriteria.target='Per Student'")
            ->where("tawasulReportingCriteriaType.valueType='Remark'")
            ->where("tawasulReportingProgress.status='Complete'")
            ->orderBy(['tawasulReportingCriteria.sequenceNumber', 'tawasulReportingCriteria.tawasulReportingCriteriaID']);

        return $this->runSelect($query);
    }

    public function selectReportingCriteriaByGroup($tawasulReportingScopeID, $scopeType, $scopeTypeID)
    {
        $query = $this
            ->newSelect()
            ->distinct()
            ->from('tawasulReportingCriteria')
            ->cols(['tawasulReportingCriteria.tawasulReportingCriteriaID', 'tawasulReportingCriteria.name', 'tawasulReportingCriteria.description', 'tawasulReportingCriteria.category', 'tawasulReportingCriteriaType.name as criteriaName', 'tawasulReportingCriteriaType.valueType', 'tawasulReportingCriteriaType.characterLimit', 'tawasulReportingCriteriaType.tawasulScaleID', 'tawasulReportingValue.tawasulScaleGradeID', 'tawasulReportingValue.value', 'tawasulReportingValue.comment'])
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->where('tawasulReportingCriteria.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->where("tawasulReportingCriteria.target='Per Group'")
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->bindValue('scopeTypeID', $scopeTypeID)
            ->groupBy(['tawasulReportingCriteria.tawasulReportingCriteriaID'])
            ->orderBy(['tawasulReportingCriteria.sequenceNumber', 'tawasulReportingCriteria.tawasulReportingCriteriaID']);

        if ($scopeType == 'Year Group') {
            $query->leftJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID')
                ->where('tawasulReportingCriteria.tawasulYearGroupID=:scopeTypeID');
        } elseif ($scopeType == 'Form Group') {
            $query->leftJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID')
                ->where('tawasulReportingCriteria.tawasulFormGroupID=:scopeTypeID');
        } elseif ($scopeType == 'Course') {
            $query
                ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=:scopeTypeID')
                ->leftJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID AND tawasulReportingValue.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
                ->where('(tawasulReportingValue.tawasulReportingValueID IS NULL OR (tawasulReportingValue.tawasulReportingValueID IS NOT NULL AND tawasulReportingValue.tawasulCourseClassID=:scopeTypeID))');
        }

        return $this->runSelect($query);
    }

    public function getAccessToScopeByPerson($tawasulReportingScopeID, $tawasulPersonID)
    {
        $query = $this
            ->newSelect()
            ->from('tawasulPerson')
            ->cols(['tawasulReportingScope.tawasulReportingScopeID', 'tawasulReportingScope.name', 'MIN(tawasulReportingAccess.dateStart) as dateStart', 'MAX(tawasulReportingAccess.dateEnd) as dateEnd', "(CASE WHEN :today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd AND :today BETWEEN MIN(tawasulReportingAccess.dateStart) AND MAX(tawasulReportingAccess.dateEnd) THEN 'Y' ELSE 'N' END) as reportingOpen", "(CASE WHEN tawasulReportingAccess.tawasulReportingAccessID IS NOT NULL THEN 'Y' ELSE 'N' END) AS canAccess", 'tawasulReportingAccess.canWrite', 'tawasulReportingAccess.canProofRead'])
            ->innerJoin('tawasulReportingAccess', "(
                (tawasulReportingAccess.accessType='Person' AND FIND_IN_SET(tawasulPerson.tawasulPersonID, tawasulReportingAccess.tawasulPersonIDList)) OR (tawasulReportingAccess.accessType='Role' AND FIND_IN_SET(tawasulPerson.tawasulRoleIDPrimary, tawasulReportingAccess.tawasulRoleIDList))
            )")
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingAccess.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID AND FIND_IN_SET(tawasulReportingScope.tawasulReportingScopeID, tawasulReportingAccess.tawasulReportingScopeIDList)')
            ->bindValue('today', date('Y-m-d'))
            ->where('tawasulPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->having('tawasulReportingScope.tawasulReportingScopeID IS NOT NULL');

        return $this->runSelect($query)->fetch();
    }

    public function getAccessToScopeAndCriteriaGroupByPerson($tawasulReportingScopeID, $scopeType, $scopeTypeID, $tawasulPersonID)
    {
        $query = $this
            ->newSelect()
            ->from('tawasulPerson')
            ->cols(['tawasulReportingScope.tawasulReportingScopeID', 'tawasulReportingScope.name', 'MIN(tawasulReportingAccess.dateStart) as dateStart', 'MAX(tawasulReportingAccess.dateEnd) as dateEnd', "(CASE WHEN :today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd AND :today BETWEEN MIN(tawasulReportingAccess.dateStart) AND MAX(tawasulReportingAccess.dateEnd) THEN 'Y' ELSE 'N' END) as reportingOpen", "(CASE WHEN MAX(tawasulReportingAccess.tawasulReportingAccessID) IS NOT NULL THEN 'Y' ELSE 'N' END) AS canAccess", 'MAX(tawasulReportingAccess.canWrite) as canWrite', 'MAX(tawasulReportingAccess.canProofRead) as canProofRead'])
            ->innerJoin('tawasulReportingAccess', "(
                (tawasulReportingAccess.accessType='Person' AND FIND_IN_SET(tawasulPerson.tawasulPersonID, tawasulReportingAccess.tawasulPersonIDList)) OR (tawasulReportingAccess.accessType='Role' AND FIND_IN_SET(tawasulPerson.tawasulRoleIDPrimary, tawasulReportingAccess.tawasulRoleIDList))
            )")
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingAccess.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID AND FIND_IN_SET(tawasulReportingScope.tawasulReportingScopeID, tawasulReportingAccess.tawasulReportingScopeIDList)')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->bindValue('today', date('Y-m-d'))
            ->where('tawasulPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->bindValue('scopeTypeID', $scopeTypeID)
            ->having('identifier IS NOT NULL')
            ->groupBy(['tawasulReportingScope.tawasulReportingScopeID']);

        if ($scopeType == 'Year Group') {
            $query->cols(['tawasulYearGroup.tawasulYearGroupID as identifier'])
                ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID')
                ->where('tawasulYearGroup.tawasulYearGroupID=:scopeTypeID')
                ->where('tawasulYearGroup.tawasulPersonIDHOY=tawasulPerson.tawasulPersonID');
        } elseif ($scopeType == 'Form Group') {
            $query->cols(['tawasulFormGroup.tawasulFormGroupID as identifier'])
                ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID')
                ->where('tawasulFormGroup.tawasulFormGroupID=:scopeTypeID')
                ->where('(tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID)');
        } elseif ($scopeType == 'Course') {
            $query->cols(['tawasulCourseClassPerson.tawasulCourseClassID as identifier'])
                ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
                ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
                ->innerJoin('tawasulCourseClassPerson', 'tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
                ->where('tawasulCourseClassPerson.tawasulCourseClassID=:scopeTypeID')
                ->where('tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID')
                ->where("(tawasulCourseClassPerson.role='Teacher' OR tawasulCourseClassPerson.role='Assistant')")
                ->where("tawasulCourseClass.reportable='Y'")
                ->where("tawasulCourseClassPerson.reportable='Y'");
        }

        return $this->runSelect($query)->fetch();
    }
}
