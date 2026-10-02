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

class ReportingProgressGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulReportingProgress';
    private static $primaryKey = 'tawasulReportingProgressID';
    private static $searchableColumns = [''];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryReportingProgressByCycle(QueryCriteria $criteria, $tawasulReportingCycleID)
    {
        // COURSES
        $query = $this
            ->newQuery()
            ->cols(['tawasulReportingScope.tawasulReportingScopeID AS tawasulReportingScopeID', 'tawasulReportingScope.sequenceNumber AS sequenceNumber', 'tawasulReportingScope.name', "COUNT(DISTINCT tawasulCourseClassPerson.tawasulCourseClassPersonID) as totalCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProgress.status='Complete' THEN tawasulReportingProgress.tawasulReportingProgressID END) as progressCount"])
            ->from('tawasulReportingCycle')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingAccess', 'FIND_IN_SET(tawasulReportingScope.tawasulReportingScopeID, tawasulReportingAccess.tawasulReportingScopeIDList)')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
            ->innerJoin('tawasulCourseClassPerson', "tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID")
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')
            ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulCourseClassPerson.tawasulPersonID AND tawasulReportingProgress.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID')
            ->where('tawasulReportingCycle.tawasulReportingCycleID=:tawasulReportingCycleID')
            ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID)
            ->where("tawasulReportingScope.scopeType='Course'")
            ->where("tawasulReportingCriteria.target='Per Student'")
            ->where("tawasulCourseClass.reportable='Y'")
            ->where("tawasulCourseClassPerson.reportable='Y'")
            ->where("tawasulCourseClassPerson.role='Student'")
            ->where("tawasulPerson.status='Full'")
            ->where("(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)")
            ->where("(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)")
            ->bindValue('today', date('Y-m-d'))
            ->groupBy(['tawasulReportingScopeID']);

        // FORM GROUPS
        $query->unionAll()
            ->cols(['tawasulReportingScope.tawasulReportingScopeID AS tawasulReportingScopeID', 'tawasulReportingScope.sequenceNumber AS sequenceNumber', 'tawasulReportingScope.name', "COUNT(DISTINCT tawasulStudentEnrolment.tawasulStudentEnrolmentID) as totalCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProgress.status='Complete' THEN tawasulReportingProgress.tawasulReportingProgressID END) as progressCount"])
            ->from('tawasulReportingCycle')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingAccess', 'FIND_IN_SET(tawasulReportingScope.tawasulReportingScopeID, tawasulReportingAccess.tawasulReportingScopeIDList)')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID AND tawasulReportingProgress.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->where('tawasulReportingCycle.tawasulReportingCycleID=:tawasulReportingCycleID')
            ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID)
            ->where("tawasulReportingScope.scopeType='Form Group'")
            ->where("tawasulReportingCriteria.target='Per Student'")
            ->where("tawasulPerson.status='Full'")
            ->where("(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)")
            ->where("(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)")
            ->bindValue('today', date('Y-m-d'))
            ->groupBy(['tawasulReportingScopeID']);


        // YEAR GROUPS
        $query->unionAll()
            ->cols(['tawasulReportingScope.tawasulReportingScopeID AS tawasulReportingScopeID', 'tawasulReportingScope.sequenceNumber AS sequenceNumber', 'tawasulReportingScope.name', "COUNT(DISTINCT tawasulStudentEnrolment.tawasulStudentEnrolmentID) as totalCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProgress.status='Complete' THEN tawasulReportingProgress.tawasulReportingProgressID END) as progressCount"])
            ->from('tawasulReportingCycle')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingAccess', 'FIND_IN_SET(tawasulReportingScope.tawasulReportingScopeID, tawasulReportingAccess.tawasulReportingScopeIDList)')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID AND tawasulReportingProgress.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->where('tawasulReportingCycle.tawasulReportingCycleID=:tawasulReportingCycleID')
            ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID)
            ->where("tawasulReportingScope.scopeType='Year Group'")
            ->where("tawasulReportingCriteria.target='Per Student'")
            ->where("tawasulPerson.status='Full'")
            ->where("(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)")
            ->where("(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)")
            ->bindValue('today', date('Y-m-d'))
            ->groupBy(['tawasulReportingScopeID']);

        return $this->runQuery($query, $criteria);
    }

    public function queryReportingProgressByPerson(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulReportingCycleID = null, $tawasulReportingScopeID = null)
    {
        // COURSES
        $query = $this
            ->newQuery()
            ->cols(['teacher.tawasulPersonID AS tawasulPersonID', 'teacher.surname', 'teacher.preferredName', "COUNT(DISTINCT studentClass.tawasulCourseClassPersonID) as totalCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProgress.status='Complete' THEN tawasulReportingProgress.tawasulReportingProgressID END) as progressCount"])
            ->from('tawasulReportingCycle')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingAccess', 'FIND_IN_SET(tawasulReportingScope.tawasulReportingScopeID, tawasulReportingAccess.tawasulReportingScopeIDList)')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
            ->innerJoin('tawasulCourseClassPerson as studentClass', "studentClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND studentClass.role='Student'")
            ->innerJoin('tawasulPerson as student', 'student.tawasulPersonID=studentClass.tawasulPersonID')
            ->innerJoin('tawasulCourseClassPerson as teacherClass', "teacherClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND teacherClass.role='Teacher'")
            ->innerJoin('tawasulPerson as teacher', 'teacher.tawasulPersonID=teacherClass.tawasulPersonID')
            ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID AND tawasulReportingProgress.tawasulPersonIDStudent=studentClass.tawasulPersonID AND tawasulReportingProgress.tawasulCourseClassID=studentClass.tawasulCourseClassID')
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulReportingScope.scopeType='Course'")
            ->where("tawasulReportingCriteria.target='Per Student'")
            ->where("tawasulCourseClass.reportable='Y'")
            ->where("studentClass.reportable='Y'")
            ->where("teacherClass.reportable='Y'")
            ->where("student.status='Full'")
            ->where("(student.dateStart IS NULL OR student.dateStart<=:today)")
            ->where("(student.dateEnd IS NULL OR student.dateEnd>=:today)")
            ->bindValue('today', date('Y-m-d'))
            // ->where('FIND_IN_SET(teacher.tawasulRoleIDPrimary, tawasulReportingAccess.tawasulRoleIDList)')
            ->groupBy(['tawasulPersonID']);

        if ($tawasulReportingCycleID) {
            $query->where('tawasulReportingCycle.tawasulReportingCycleID=:tawasulReportingCycleID', ['tawasulReportingCycleID' => $tawasulReportingCycleID]);
        }
        if ($tawasulReportingScopeID) {
            $query->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID', ['tawasulReportingScopeID' => $tawasulReportingScopeID]);
        }
        if (empty($tawasulReportingCycleID) && empty($tawasulReportingScopeID)) {
            $query->where(':today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd', ['today' => date('Y-m-d')]);
        }

        // FORM GROUPS
        $query->unionAll()
            ->cols(['teacher.tawasulPersonID AS tawasulPersonID', 'teacher.surname', 'teacher.preferredName', "COUNT(DISTINCT student.tawasulPersonID) as totalCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProgress.status='Complete' THEN tawasulReportingProgress.tawasulReportingProgressID END) as progressCount"])
            ->from('tawasulReportingCycle')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingAccess', 'FIND_IN_SET(tawasulReportingScope.tawasulReportingScopeID, tawasulReportingAccess.tawasulReportingScopeIDList)')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->innerJoin('tawasulPerson as student', 'student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulPerson as teacher', '(teacher.tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor OR teacher.tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor2 OR teacher.tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor3)')
            ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID AND tawasulReportingProgress.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulReportingScope.scopeType='Form Group'")
            ->where("tawasulReportingCriteria.target='Per Student'")
            ->where("student.status='Full'")
            ->where("(student.dateStart IS NULL OR student.dateStart<=:today)")
            ->where("(student.dateEnd IS NULL OR student.dateEnd>=:today)")
            ->bindValue('today', date('Y-m-d'))
            // ->where('FIND_IN_SET(teacher.tawasulRoleIDPrimary, tawasulReportingAccess.tawasulRoleIDList)')
            ->groupBy(['tawasulPersonID']);

        if ($tawasulReportingCycleID) {
            $query->where('tawasulReportingCycle.tawasulReportingCycleID=:tawasulReportingCycleID', ['tawasulReportingCycleID' => $tawasulReportingCycleID]);
        }
        if ($tawasulReportingScopeID) {
            $query->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID', ['tawasulReportingScopeID' => $tawasulReportingScopeID]);
        }
        if (empty($tawasulReportingCycleID) && empty($tawasulReportingScopeID)) {
            $query->where(':today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd', ['today' => date('Y-m-d')]);
        }

        // YEAR GROUPS
        $query->unionAll()
            ->cols(['teacher.tawasulPersonID AS tawasulPersonID', 'teacher.surname', 'teacher.preferredName', "COUNT(DISTINCT student.tawasulPersonID) as totalCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProgress.status='Complete' THEN tawasulReportingProgress.tawasulReportingProgressID END) as progressCount"])
            ->from('tawasulReportingCycle')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingAccess', 'FIND_IN_SET(tawasulReportingScope.tawasulReportingScopeID, tawasulReportingAccess.tawasulReportingScopeIDList)')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulPerson as student', 'student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulPerson as teacher', 'teacher.tawasulPersonID=tawasulYearGroup.tawasulPersonIDHOY')
            ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID AND tawasulReportingProgress.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulReportingScope.scopeType='Year Group'")
            ->where("tawasulReportingCriteria.target='Per Student'")
            ->where("student.status='Full'")
            ->where("(student.dateStart IS NULL OR student.dateStart<=:today)")
            ->where("(student.dateEnd IS NULL OR student.dateEnd>=:today)")
            ->bindValue('today', date('Y-m-d'))
            // ->where('FIND_IN_SET(teacher.tawasulRoleIDPrimary, tawasulReportingAccess.tawasulRoleIDList)')
            ->groupBy(['tawasulPersonID']);

        if ($tawasulReportingCycleID) {
            $query->where('tawasulReportingCycle.tawasulReportingCycleID=:tawasulReportingCycleID', ['tawasulReportingCycleID' => $tawasulReportingCycleID]);
        }
        if ($tawasulReportingScopeID) {
            $query->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID', ['tawasulReportingScopeID' => $tawasulReportingScopeID]);
        }
        if (empty($tawasulReportingCycleID) && empty($tawasulReportingScopeID)) {
            $query->where(':today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd', ['today' => date('Y-m-d')]);
        }

        return $this->runQuery($query, $criteria);
    }

    public function queryReportingProgressByDepartment(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulReportingCycleID = null)
    {
        // COURSES
        $query = $this
            ->newQuery()
            ->cols(['tawasulDepartment.name as department', 'tawasulDepartment.tawasulDepartmentID', 'tawasulCourseClass.tawasulCourseClassID', 'tawasulCourse.nameShort as courseName', 'tawasulCourseClass.nameShort as className', 'tawasulReportingCycle.tawasulReportingCycleID', "GROUP_CONCAT(DISTINCT CONCAT(teacher.preferredName, ' ', teacher.surname) SEPARATOR '<br/>') as teachers","CONCAT(tawasulReportingScope.tawasulReportingScopeID, '-', tawasulCourseClass.tawasulCourseClassID) as criteriaSelector", "COUNT(DISTINCT studentClass.tawasulCourseClassPersonID) as totalCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProgress.status='Complete' THEN tawasulReportingProgress.tawasulReportingProgressID END) as progressCount"])
            ->from('tawasulReportingCycle')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingAccess', 'FIND_IN_SET(tawasulReportingScope.tawasulReportingScopeID, tawasulReportingAccess.tawasulReportingScopeIDList)')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->innerJoin('tawasulDepartment', 'tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID')
            ->innerJoin('tawasulCourseClassPerson as studentClass', "studentClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND studentClass.role='Student'")
            ->innerJoin('tawasulPerson as student', 'student.tawasulPersonID=studentClass.tawasulPersonID')
            ->leftJoin('tawasulCourseClassPerson as teacherClass', "teacherClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND teacherClass.role='Teacher'")
            ->leftJoin('tawasulPerson as teacher', 'teacher.tawasulPersonID=teacherClass.tawasulPersonID')
            ->leftJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID AND tawasulReportingProgress.tawasulPersonIDStudent=studentClass.tawasulPersonID AND tawasulReportingProgress.tawasulCourseClassID=studentClass.tawasulCourseClassID')
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulReportingScope.scopeType='Course'")
            ->where("tawasulReportingCriteria.target='Per Student'")
            ->where("tawasulCourseClass.reportable='Y'")
            ->where("studentClass.reportable='Y'")
            ->where("student.status='Full'")
            ->where("(student.dateStart IS NULL OR student.dateStart<=:today)")
            ->where("(student.dateEnd IS NULL OR student.dateEnd>=:today)")
            ->bindValue('today', date('Y-m-d'))
            ->groupBy(['tawasulCourseClass.tawasulCourseClassID']);

        if ($tawasulReportingCycleID) {
            $query->where('tawasulReportingCycle.tawasulReportingCycleID=:tawasulReportingCycleID', ['tawasulReportingCycleID' => $tawasulReportingCycleID]);
        }
        if (empty($tawasulReportingCycleID) && empty($tawasulReportingScopeID)) {
            $query->where(':today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd', ['today' => date('Y-m-d')]);
        }

        return $this->runQuery($query, $criteria);
    }

    public function queryProofReadingProgressByScope(QueryCriteria $criteria, $tawasulReportingScopeID, $scopeType = 'Year Group')
    {
        // COURSES
        if ($scopeType == 'Course') {
            $query = $this
                ->newQuery()
                ->cols(['tawasulCourseClass.tawasulCourseClassID', 'tawasulReportingCriteria.sequenceNumber', "CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as name", "COUNT(DISTINCT tawasulReportingValue.tawasulReportingValueID) as totalCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProof.status='Done' OR tawasulReportingProof.status='Accepted' THEN tawasulReportingProof.tawasulReportingProofID END) as progressCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProof.status='Edited' THEN tawasulReportingProof.tawasulReportingProofID END) as partialCount"])
                ->from('tawasulReportingScope')
                ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
                ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
                ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
                ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
                ->innerJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID AND tawasulReportingValue.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
                ->innerJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID AND tawasulReportingProgress.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
                ->leftJoin('tawasulReportingProof', 'tawasulReportingProof.tawasulReportingValueID=tawasulReportingValue.tawasulReportingValueID')
                ->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID')
                ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
                ->where("tawasulReportingScope.scopeType='Course'")
                ->where("tawasulReportingCriteria.target='Per Student'")
                ->where("tawasulReportingCriteriaType.valueType='Comment'")
                ->where("tawasulReportingProgress.status='Complete'")
                ->where("tawasulCourseClass.reportable='Y'")
                ->groupBy(['tawasulCourseClass.tawasulCourseClassID']);

        } else if ($scopeType == 'Form Group') {
            $query = $this
                ->newQuery()
                ->cols(['tawasulFormGroup.tawasulFormGroupID', 'tawasulReportingCriteria.sequenceNumber', "tawasulFormGroup.name", "COUNT(DISTINCT tawasulReportingValue.tawasulReportingValueID) as totalCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProof.status='Done' OR tawasulReportingProof.status='Accepted' THEN tawasulReportingProof.tawasulReportingProofID END) as progressCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProof.status='Edited' THEN tawasulReportingProof.tawasulReportingProofID END) as partialCount"])
                ->from('tawasulReportingScope')
                ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
                ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
                ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID')
                ->innerJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID')
                ->innerJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID AND tawasulReportingProgress.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
                ->leftJoin('tawasulReportingProof', 'tawasulReportingProof.tawasulReportingValueID=tawasulReportingValue.tawasulReportingValueID')
                ->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID')
                ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
                ->where("tawasulReportingScope.scopeType='Form Group'")
                ->where("tawasulReportingCriteria.target='Per Student'")
                ->where("tawasulReportingCriteriaType.valueType='Comment'")
                ->where("tawasulReportingProgress.status='Complete'")
                ->groupBy(['tawasulFormGroup.tawasulFormGroupID']);

        } else if ($scopeType == 'Year Group') {
            $query = $this
                ->newQuery()
                ->cols(['tawasulYearGroup.tawasulYearGroupID', 'tawasulReportingCriteria.sequenceNumber', "tawasulYearGroup.name", "COUNT(DISTINCT tawasulReportingValue.tawasulReportingValueID) as totalCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProof.status='Done' OR tawasulReportingProof.status='Accepted' THEN tawasulReportingProof.tawasulReportingProofID END) as progressCount", "COUNT(DISTINCT CASE WHEN tawasulReportingProof.status='Edited' THEN tawasulReportingProof.tawasulReportingProofID END) as partialCount"])
                ->from('tawasulReportingScope')
                ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
                ->innerJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
                ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID')
                ->innerJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID')
                ->innerJoin('tawasulReportingProgress', 'tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID AND tawasulReportingProgress.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent')
                ->leftJoin('tawasulReportingProof', 'tawasulReportingProof.tawasulReportingValueID=tawasulReportingValue.tawasulReportingValueID')
                ->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID')
                ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
                ->where("tawasulReportingScope.scopeType='Year Group'")
                ->where("tawasulReportingCriteria.target='Per Student'")
                ->where("tawasulReportingCriteriaType.valueType='Comment'")
                ->where("tawasulReportingProgress.status='Complete'")
                ->groupBy(['tawasulYearGroup.tawasulYearGroupID']);
        }

        $criteria->addFilterRules([
            'reportingCycle' => function ($query, $tawasulReportingCycleID) {
                return $query
                    ->where('tawasulReportingScope.tawasulReportingCycleID = :tawasulReportingCycleID')
                    ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

}
