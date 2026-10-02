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

class ReportingProofGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulReportingProof';
    private static $primaryKey = 'tawasulReportingProofID';
    private static $searchableColumns = [''];

    public function selectProofReadingScopes($tawasulSchoolYearID)
    {
        $query = $this
            ->newSelect()
            ->cols(['tawasulReportingScope.tawasulReportingScopeID AS tawasulReportingScopeID', 'tawasulReportingScope.name as scopeName', 'tawasulReportingScope.scopeType', 'tawasulReportingCycle.name as cycleName', 'tawasulReportingCycle.nameShort as cycleNameShort'])
            ->from('tawasulReportingCycle')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where(':today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd')
            ->bindValue('today', date('Y-m-d'))
            ->where("tawasulReportingCriteriaType.valueType='Comment'")
            ->where("tawasulReportingCriteria.target='Per Student'")
            ->groupBy(['tawasulReportingScopeID'])
            ->orderBy(['tawasulReportingCycle.sequenceNumber', 'tawasulReportingScope.sequenceNumber']);

        return $this->runSelect($query);
    }

    public function queryProofReadingByFormGroup($criteria, $tawasulSchoolYearID, $tawasulFormGroupID)
    {
        $criteria->addFilterRules($this->getSharedFilterRules());

        // COURSES
        $query = $this
            ->newQuery()
            ->from('tawasulReportingCycle')
            ->cols(['tawasulReportingValue.tawasulPersonIDStudent', 'tawasulReportingValue.tawasulReportingValueID', 'tawasulReportingCriteria.target as criteriaTarget', 'tawasulReportingCriteria.name as criteriaName', 'tawasulReportingCriteriaType.characterLimit', 'tawasulCourse.name', "CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as nameShort", 'tawasulReportingValue.comment', 'student.surname', 'student.preferredName', 'student.gender', 'writtenBy.surname as surnameWrittenBy',  'writtenBy.preferredName as preferredNameWrittenBy', 'tawasulReportingScope.name AS "scopeName"' ])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulReportingCycle.tawasulSchoolYearID=tawasulStudentEnrolment.tawasulSchoolYearID')
            ->innerJoin('tawasulPerson as student', 'student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulReportingValue', 'student.tawasulPersonID=tawasulReportingValue.tawasulPersonIDStudent AND tawasulReportingValue.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID')
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID AND tawasulReportingProgress.tawasulCourseClassID=tawasulReportingValue.tawasulCourseClassID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulReportingValue.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->innerJoin('tawasulPerson as writtenBy', 'writtenBy.tawasulPersonID=tawasulReportingValue.tawasulPersonIDCreated')
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStudentEnrolment.tawasulFormGroupID=:tawasulFormGroupID')
            ->bindValue('tawasulFormGroupID', $tawasulFormGroupID)
            ->where("tawasulReportingProgress.status='Complete'")
            ->where("tawasulReportingCriteriaType.valueType='Comment'")
            ->where("tawasulReportingValue.tawasulCourseClassID <> 0")
            ->where("(tawasulReportingValue.comment <> '' AND tawasulReportingValue.comment IS NOT NULL)")
            ->where('(:today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd)')
            ->bindValue('today', date('Y-m-d'));

        // FORM GROUP
        $this->unionAllWithCriteria($query, $criteria)
            ->from('tawasulReportingCycle')
            ->cols(['tawasulReportingValue.tawasulPersonIDStudent', 'tawasulReportingValue.tawasulReportingValueID', 'tawasulReportingCriteria.target as criteriaTarget', 'tawasulReportingCriteria.name as criteriaName', 'tawasulReportingCriteriaType.characterLimit', 'tawasulFormGroup.name', 'tawasulFormGroup.nameShort', 'tawasulReportingValue.comment', 'student.surname', 'student.preferredName', 'student.gender', 'writtenBy.surname as surnameWrittenBy',  'writtenBy.preferredName as preferredNameWrittenBy', 'tawasulReportingScope.name AS "scopeName"'])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulReportingCycle.tawasulSchoolYearID=tawasulStudentEnrolment.tawasulSchoolYearID')
            ->innerJoin('tawasulPerson as student', 'student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulReportingValue', 'student.tawasulPersonID=tawasulReportingValue.tawasulPersonIDStudent AND tawasulReportingValue.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID')
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID')
            ->innerJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID AND tawasulReportingProgress.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
            ->innerJoin('tawasulPerson as writtenBy', 'writtenBy.tawasulPersonID=tawasulReportingValue.tawasulPersonIDCreated')
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStudentEnrolment.tawasulFormGroupID=:tawasulFormGroupID')
            ->bindValue('tawasulFormGroupID', $tawasulFormGroupID)
            ->where("tawasulReportingProgress.status='Complete'")
            ->where("tawasulReportingCriteriaType.valueType='Comment'")
            ->where("tawasulReportingCriteria.tawasulFormGroupID IS NOT NULL")
            ->where("(tawasulReportingValue.comment <> '' AND tawasulReportingValue.comment IS NOT NULL)")
            ->where('(:today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd)')
            ->bindValue('today', date('Y-m-d'));

        // YEAR GROUP
        $this->unionAllWithCriteria($query, $criteria)
            ->from('tawasulReportingCycle')
            ->cols(['tawasulReportingValue.tawasulPersonIDStudent', 'tawasulReportingValue.tawasulReportingValueID', 'tawasulReportingCriteria.target as criteriaTarget', 'tawasulReportingCriteria.name as criteriaName', 'tawasulReportingCriteriaType.characterLimit', 'tawasulYearGroup.name', 'tawasulYearGroup.nameShort', 'tawasulReportingValue.comment', 'student.surname', 'student.preferredName', 'student.gender', 'writtenBy.surname as surnameWrittenBy', 'writtenBy.preferredName as preferredNameWrittenBy', 'tawasulReportingScope.name AS "scopeName"'])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulReportingCycle.tawasulSchoolYearID=tawasulStudentEnrolment.tawasulSchoolYearID')
            ->innerJoin('tawasulPerson as student', 'student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulReportingValue', 'student.tawasulPersonID=tawasulReportingValue.tawasulPersonIDStudent AND tawasulReportingValue.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID')
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID')
            ->innerJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID AND tawasulReportingProgress.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
            ->innerJoin('tawasulPerson as writtenBy', 'writtenBy.tawasulPersonID=tawasulReportingValue.tawasulPersonIDCreated')
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStudentEnrolment.tawasulFormGroupID=:tawasulFormGroupID')
            ->bindValue('tawasulFormGroupID', $tawasulFormGroupID)
            ->where("tawasulReportingProgress.status='Complete'")
            ->where("tawasulReportingCriteriaType.valueType='Comment'")
            ->where("tawasulReportingCriteria.tawasulYearGroupID IS NOT NULL")
            ->where("(tawasulReportingValue.comment <> '' AND tawasulReportingValue.comment IS NOT NULL)")
            ->where('(:today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd)')
            ->bindValue('today', date('Y-m-d'));

        $query->orderBy(['criteriaTarget', 'surname', 'preferredName', 'nameShort']);

        return $this->runQuery($query, $criteria);

    }

    public function queryProofReadingByPerson($criteria, $tawasulSchoolYearID, $tawasulPersonID, $reportingScopeIDs = null)
    {
        $reportingScopeIDs = is_array($reportingScopeIDs)? implode(',', $reportingScopeIDs) : $reportingScopeIDs;

        $criteria->addFilterRules($this->getSharedFilterRules());

        // COURSES
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols(['tawasulReportingValue.tawasulPersonIDStudent', 'tawasulReportingValue.tawasulReportingValueID', 'tawasulReportingCriteria.target as criteriaTarget', 'tawasulReportingCriteria.name as criteriaName', 'tawasulReportingCriteriaType.characterLimit', 'tawasulCourse.name', "CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as nameShort", 'tawasulReportingValue.comment', 'student.surname', 'student.preferredName', 'student.gender', 'tawasulReportingScope.name AS "scopeName"'])
            ->innerJoin('tawasulCourseClassPerson', 'tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingCriteria.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->innerJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID')
            ->innerJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID AND tawasulReportingProgress.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
            ->leftJoin('tawasulPerson as student', 'student.tawasulPersonID=tawasulReportingValue.tawasulPersonIDStudent')
            ->where("tawasulReportingProgress.status='Complete'")
            ->where("tawasulReportingCriteriaType.valueType='Comment'")
            ->where("(tawasulReportingValue.comment <> '' AND tawasulReportingValue.comment IS NOT NULL)")
            ->where("tawasulCourseClassPerson.role='Teacher'")
            ->where("tawasulCourseClassPerson.reportable='Y'")
            ->where("tawasulCourseClass.reportable='Y'")
            ->where('tawasulPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('(:today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd)')
            ->bindValue('today', date('Y-m-d'));

        if (!empty($reportingScopeIDs)) {
            $query->where('FIND_IN_SET(tawasulReportingCriteria.tawasulReportingScopeID, :reportingScopeIDs)', ['reportingScopeIDs' => $reportingScopeIDs]);
        }

        // FORM GROUP
        $this->unionAllWithCriteria($query, $criteria)
            ->from('tawasulPerson')
            ->cols(['tawasulReportingValue.tawasulPersonIDStudent', 'tawasulReportingValue.tawasulReportingValueID', 'tawasulReportingCriteria.target as criteriaTarget', 'tawasulReportingCriteria.name as criteriaName', 'tawasulReportingCriteriaType.characterLimit', 'tawasulFormGroup.name', 'tawasulFormGroup.nameShort', 'tawasulReportingValue.comment', 'student.surname', 'student.preferredName', 'student.gender', 'tawasulReportingScope.name AS "scopeName"'])
            ->innerJoin('tawasulFormGroup', '(tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID)')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingCriteria.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->innerJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID')
            ->innerJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID AND tawasulReportingProgress.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
            ->leftJoin('tawasulPerson as student', 'student.tawasulPersonID=tawasulReportingValue.tawasulPersonIDStudent')
            ->where("tawasulReportingProgress.status='Complete'")
            ->where("tawasulReportingCriteriaType.valueType='Comment'")
            ->where("(tawasulReportingValue.comment <> '' AND tawasulReportingValue.comment IS NOT NULL)")
            ->where('tawasulPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('(:today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd)')
            ->bindValue('today', date('Y-m-d'));

        if (!empty($reportingScopeIDs)) {
            $query->where('FIND_IN_SET(tawasulReportingCriteria.tawasulReportingScopeID, :reportingScopeIDs)', ['reportingScopeIDs' => $reportingScopeIDs]);
        }

        // YEAR GROUP
        $this->unionAllWithCriteria($query, $criteria)
            ->from('tawasulPerson')
            ->cols(['tawasulReportingValue.tawasulPersonIDStudent', 'tawasulReportingValue.tawasulReportingValueID', 'tawasulReportingCriteria.target as criteriaTarget', 'tawasulReportingCriteria.name as criteriaName', 'tawasulReportingCriteriaType.characterLimit', 'tawasulYearGroup.name', 'tawasulYearGroup.nameShort', 'tawasulReportingValue.comment', 'student.surname', 'student.preferredName', 'student.gender', 'tawasulReportingScope.name AS "scopeName"'])
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulPersonIDHOY=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingCriteria.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->innerJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID')
            ->innerJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID AND tawasulReportingProgress.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
            ->leftJoin('tawasulPerson as student', 'student.tawasulPersonID=tawasulReportingValue.tawasulPersonIDStudent')
            ->where("tawasulReportingProgress.status='Complete'")
            ->where("tawasulReportingCriteriaType.valueType='Comment'")
            ->where("(tawasulReportingValue.comment <> '' AND tawasulReportingValue.comment IS NOT NULL)")
            ->where('tawasulPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('(:today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd)')
            ->bindValue('today', date('Y-m-d'));

        if (!empty($reportingScopeIDs)) {
            $query->where('FIND_IN_SET(tawasulReportingCriteria.tawasulReportingScopeID, :reportingScopeIDs)', ['reportingScopeIDs' => $reportingScopeIDs]);
        }

        $query->orderBy(['criteriaTarget', 'nameShort', 'surname', 'preferredName']);

        return $this->runQuery($query, $criteria);
    }

    public function selectPendingProofReadingEdits($tawasulReportingCycleIDList)
    {
        $tawasulReportingCycleIDList = is_array($tawasulReportingCycleIDList)? $tawasulReportingCycleIDList : [$tawasulReportingCycleIDList];

        // COURSES
        $query = $this
            ->newSelect()
            ->from('tawasulReportingProof')
            ->cols(['tawasulPerson.tawasulPersonID AS groupBy', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulReportingCycle.name', 'tawasulReportingProof.comment', 'tawasulReportingScope.scopeType'])
            ->innerJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingValueID=tawasulReportingProof.tawasulReportingValueID')
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingValue.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingCriteriaID=tawasulReportingValue.tawasulReportingCriteriaID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulCourseClassPerson', 'tawasulCourseClassPerson.tawasulCourseClassID=tawasulReportingValue.tawasulCourseClassID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')
            ->where('FIND_IN_SET(tawasulReportingCycle.tawasulReportingCycleID, :tawasulReportingCycleIDList)')
            ->bindValue('tawasulReportingCycleIDList', implode(',', $tawasulReportingCycleIDList))
            ->where("tawasulReportingProof.status='Edited'")
            ->where("tawasulReportingScope.scopeType='Course'")
            ->where("tawasulCourseClassPerson.role='Teacher'")
            ->where("tawasulCourseClassPerson.reportable='Y'");

        // FORM GROUPS
        $query->unionAll()
            ->from('tawasulReportingProof')
            ->cols(['tawasulPerson.tawasulPersonID AS groupBy', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulReportingCycle.name', 'tawasulReportingProof.comment', 'tawasulReportingScope.scopeType'])
            ->innerJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingValueID=tawasulReportingProof.tawasulReportingValueID')
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingValue.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingCriteriaID=tawasulReportingValue.tawasulReportingCriteriaID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID')
            ->innerJoin('tawasulPerson', '(tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID)')
            ->where('FIND_IN_SET(tawasulReportingCycle.tawasulReportingCycleID, :tawasulReportingCycleIDList)')
            ->bindValue('tawasulReportingCycleIDList', implode(',', $tawasulReportingCycleIDList))
            ->where("tawasulReportingProof.status='Edited'")
            ->where("tawasulReportingScope.scopeType='Form Group'");

        // YEAR GROUPS
        $query->unionAll()
            ->from('tawasulReportingProof')
            ->cols(['tawasulPerson.tawasulPersonID AS groupBy', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulReportingCycle.name', 'tawasulReportingProof.comment', 'tawasulReportingScope.scopeType'])
            ->innerJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingValueID=tawasulReportingProof.tawasulReportingValueID')
            ->innerJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingValue.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingCriteriaID=tawasulReportingValue.tawasulReportingCriteriaID')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID')
            ->innerJoin('tawasulPerson', 'tawasulYearGroup.tawasulPersonIDHOY=tawasulPerson.tawasulPersonID')
            ->where('FIND_IN_SET(tawasulReportingCycle.tawasulReportingCycleID, :tawasulReportingCycleIDList)')
            ->bindValue('tawasulReportingCycleIDList', implode(',', $tawasulReportingCycleIDList))
            ->where("tawasulReportingProof.status='Edited'")
            ->where("tawasulReportingScope.scopeType='Year Group'");

        $query->orderBy(['surname', 'preferredName']);

        return $this->runSelect($query);
    }

    public function selectProofsByValueID($tawasulReportingValueID)
    {
        $tawasulReportingValueIDList = is_array($tawasulReportingValueID)? $tawasulReportingValueID : [$tawasulReportingValueID];
        $tawasulReportingValueIDList = array_map(function ($item) {
            return str_pad($item, 12, 0, STR_PAD_LEFT);
        }, $tawasulReportingValueIDList);

        $data = ['tawasulReportingValueIDList' => implode(',', $tawasulReportingValueIDList)];
        $sql = "SELECT CAST(tawasulReportingProof.tawasulReportingValueID as UNSIGNED) as groupBy, tawasulReportingProof.*, tawasulPerson.preferredName, tawasulPerson.surname, tawasulPerson.image_240
                FROM tawasulReportingProof 
                JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulReportingProof.tawasulPersonIDProofed)
                WHERE FIND_IN_SET(tawasulReportingProof.tawasulReportingValueID, :tawasulReportingValueIDList)
                AND tawasulReportingProof.status <> 'Declined'";

        return $this->db()->select($sql, $data);
    }

    protected function getSharedFilterRules()
    {
        return [
            // 'status' => function ($query, $status) {
            //     return $query->where('tawasulStaffCoverage.status = :status')
            //                  ->bindValue('status', $status);
            // },
            'scopeType' => function ($query, $scopeType) {
                return $query->where('tawasulReportingScope.scopeType = :scopeType')
                             ->bindValue('scopeType', $scopeType);
            },
            'target' => function ($query, $target) {
                return $query->where('tawasulReportingCriteria.target = :target')
                             ->bindValue('target', $target);
            },
            'class' => function ($query, $class) {
                return $query->where('tawasulCourseClass.nameShort = :class')
                             ->bindValue('class', $class);
            },
            'scope' => function ($query, $scope) {
                return $query->where('tawasulReportingScope.name = :scope')
                             ->bindValue('scope', $scope);
            },
        ];
    }
}
