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

namespace TawasulOS\Domain\StudentAlerts;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * AlertGateway
 *
 * @version v30
 * @since   v30
 */

class AlertGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulAlert';
    private static $primaryKey = 'tawasulAlertID';
    private static $searchableColumns = [];

    public function queryAlertsBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonIDCreated = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulAlert.tawasulAlertID',
                'tawasulAlert.tawasulAlertLevelID',
                'tawasulAlert.tawasulCourseClassID',
                'tawasulAlert.context',
                'tawasulAlert.type',
                'tawasulAlert.status',
                'tawasulAlert.level',
                'tawasulAlert.dateStart',
                'tawasulAlert.dateEnd',
                'tawasulAlert.comment',
                'tawasulAlert.tawasulPersonIDCreated',
                'tawasulAlert.timestampCreated',
                'tawasulAlertLevel.color as levelColor',
                'tawasulAlertLevel.colorBG as levelColorBG',
                'tawasulAlertType.tag',
                'tawasulAlertType.color',
                'tawasulAlertType.colorBG',
                'tawasulStudentEnrolment.tawasulFormGroupID',
                'tawasulStudentEnrolment.tawasulYearGroupID',
                'student.tawasulPersonID',
                'student.surname',
                'student.preferredName',
                'tawasulFormGroup.nameShort AS formGroup',
                'creator.title AS titleCreator',
                'creator.surname AS surnameCreator',
                'creator.preferredName AS preferredNameCreator',
                'tawasulCourse.nameShort as courseName',
                'tawasulCourseClass.nameShort as className',
            ])
            ->innerJoin('tawasulAlertType', 'tawasulAlert.tawasulAlertTypeID=tawasulAlertType.tawasulAlertTypeID')
            ->leftJoin('tawasulAlertLevel', 'tawasulAlert.tawasulAlertLevelID=tawasulAlertLevel.tawasulAlertLevelID')
            ->innerJoin('tawasulPerson AS student', 'tawasulAlert.tawasulPersonID=student.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulPerson AS creator', 'tawasulAlert.tawasulPersonIDCreated=creator.tawasulPersonID')
            ->leftJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulAlert.tawasulCourseClassID')
            ->leftJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->where('tawasulAlert.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID=tawasulAlert.tawasulSchoolYearID')
            ->where('tawasulAlertType.active="Y"');

        if (!empty($tawasulPersonIDCreated)) {
            $query->where('tawasulAlert.tawasulPersonIDCreated = :tawasulPersonIDCreated')
                ->bindValue('tawasulPersonIDCreated', $tawasulPersonIDCreated);
        }

        $criteria->addFilterRules([
            'student' => function ($query, $tawasulPersonID) {
                return $query
                    ->where('tawasulAlert.tawasulPersonID = :tawasulPersonID')
                    ->bindValue('tawasulPersonID', $tawasulPersonID);
            },
            'formGroup' => function ($query, $tawasulFormGroupID) {
                return $query
                    ->where('tawasulStudentEnrolment.tawasulFormGroupID = :tawasulFormGroupID')
                    ->bindValue('tawasulFormGroupID', $tawasulFormGroupID);
            },
            'yearGroup' => function ($query, $tawasulYearGroupID) {
                return $query
                    ->where('tawasulStudentEnrolment.tawasulYearGroupID = :tawasulYearGroupID')
                    ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('tawasulAlert.status = :status')
                    ->bindValue('status', $status);
            },
            'context' => function ($query, $context) {
                return $query
                    ->where('tawasulAlert.context = :context')
                    ->bindValue('context', $context);
            },
            'scope' => function ($query, $value) {
                return $value == 'class' 
                    ? $query->where('tawasulAlert.tawasulCourseClassID IS NOT NULL')
                    : $query->where('tawasulAlert.tawasulCourseClassID IS NULL');
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentsWithAlertsByFormGroup(QueryCriteria $criteria, $tawasulFormGroupID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulStudentEnrolmentID', 'tawasulStudentEnrolment.tawasulSchoolYearID', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.image_240', 'tawasulYearGroup.nameShort AS yearGroup', 'tawasulFormGroup.nameShort AS formGroup', 'tawasulStudentEnrolment.rollOrder', 'tawasulPerson.dateStart', 'tawasulPerson.dateEnd', 'tawasulPerson.status', "'Student' as roleCategory"
            ])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->innerJoin('tawasulAlert', 'tawasulAlert.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulAlert.tawasulSchoolYearID=tawasulStudentEnrolment.tawasulSchoolYearID')
            ->where("tawasulPerson.status = 'Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
            ->bindValue('today', date('Y-m-d'))
            ->where('tawasulStudentEnrolment.tawasulFormGroupID = :tawasulFormGroupID')
            ->bindValue('tawasulFormGroupID', $tawasulFormGroupID)
            ->groupBy(['tawasulPerson.tawasulPersonID']);

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentsWithAlertsByClass(QueryCriteria $criteria, $tawasulCourseClassID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulCourse.tawasulSchoolYearID', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.image_240', 'tawasulYearGroup.nameShort AS yearGroup', 'tawasulFormGroup.nameShort AS formGroup', 'tawasulStudentEnrolment.rollOrder', 'tawasulPerson.dateStart', 'tawasulPerson.dateEnd', 'tawasulPerson.status', "'Student' as roleCategory"
            ])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->innerJoin('tawasulCourseClassPerson', 'tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID AND tawasulCourse.tawasulSchoolYearID=tawasulStudentEnrolment.tawasulSchoolYearID')
            ->innerJoin('tawasulAlert', 'tawasulAlert.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID AND tawasulAlert.tawasulSchoolYearID=tawasulCourse.tawasulSchoolYearID')
            ->where("tawasulPerson.status = 'Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
            ->bindValue('today', date('Y-m-d'))
            ->where('tawasulCourseClassPerson.role="Student"')
            ->where('tawasulCourseClassPerson.tawasulCourseClassID = :tawasulCourseClassID')
            ->bindValue('tawasulCourseClassID', $tawasulCourseClassID)
            ->groupBy(['tawasulPerson.tawasulPersonID']);

        return $this->runQuery($query, $criteria);
    }

    public function getAlertEditAccess($tawasulAlertID, $tawasulPersonID)
    {
        $data = ['tawasulAlertID' => $tawasulAlertID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT (CASE WHEN tawasulPersonIDCreated = :tawasulPersonID THEN TRUE ELSE FALSE END) AS canEdit FROM tawasulAlert WHERE tawasulAlertID = :tawasulAlertID";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectAlertTypes()
    {
        $sql = "SELECT name as groupBy, tawasulAlertType.* FROM tawasulAlertType ORDER BY sequenceNumber, name";

        return $this->db()->select($sql);
    }

    public function selectActiveAlertsByStudent($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulAlertType.name, tawasulAlertType.tag, tawasulAlertType.description, tawasulAlertType.color, tawasulAlertType.colorBG, tawasulAlertType.name as `type`, tawasulAlertLevel.name as `level`, tawasulAlertLevel.sequenceNumber as `alertLevel`, tawasulAlertLevel.color as `levelColor`, tawasulAlertLevel.colorBG as `levelColorBG`, tawasulPerson.privacy, tawasulAlert.timestampCreated, tawasulAlert.context
            FROM tawasulAlert 
            JOIN tawasulAlertType ON (tawasulAlertType.tawasulAlertTypeID=tawasulAlert.tawasulAlertTypeID)
            LEFT JOIN tawasulAlertLevel ON (tawasulAlert.tawasulAlertLevelID=tawasulAlertLevel.tawasulAlertLevelID) 
            JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulAlert.tawasulPersonID)
            WHERE tawasulAlert.tawasulSchoolYearID=:tawasulSchoolYearID 
            AND tawasulAlert.tawasulPersonID=:tawasulPersonID 
            AND tawasulAlert.status='Approved'
            AND tawasulAlert.tawasulCourseClassID IS NULL
            AND tawasulAlertType.active='Y'
            AND (tawasulAlert.dateStart IS NULL OR tawasulAlert.dateStart<=CURRENT_DATE)
            AND (tawasulAlert.dateEnd IS NULL OR tawasulAlert.dateEnd>=CURRENT_DATE)
            ORDER BY tawasulAlertType.sequenceNumber, tawasulAlertLevel.sequenceNumber DESC, FIND_IN_SET(tawasulAlert.context,'Automatic,Manual'), tawasulAlert.timestampCreated DESC";

        return $this->db()->select($sql, $data);
    }

    public function selectClassAlertsByStudent($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulAlertType.name as groupBy, tawasulAlertType.name, tawasulAlertType.tag, tawasulAlertType.description, tawasulAlertType.color, tawasulAlertType.colorBG, tawasulAlertType.name as `type`, tawasulAlertLevel.name as `level`, tawasulAlertLevel.sequenceNumber as `alertLevel`, tawasulAlertLevel.color as `levelColor`, tawasulAlertLevel.colorBG as `levelColorBG`, tawasulPerson.privacy, tawasulAlert.timestampCreated, tawasulAlert.context, tawasulAlert.tawasulCourseClassID
            FROM tawasulAlert 
            JOIN tawasulAlertType ON (tawasulAlertType.tawasulAlertTypeID=tawasulAlert.tawasulAlertTypeID)
            LEFT JOIN tawasulAlertLevel ON (tawasulAlert.tawasulAlertLevelID=tawasulAlertLevel.tawasulAlertLevelID) 
            JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulAlert.tawasulPersonID)
            WHERE tawasulAlert.tawasulSchoolYearID=:tawasulSchoolYearID 
            AND tawasulAlert.tawasulPersonID=:tawasulPersonID 
            AND tawasulAlert.status='Approved'
            AND tawasulAlert.context='Manual'
            AND tawasulAlert.tawasulCourseClassID IS NOT NULL
            AND tawasulAlertType.active='Y'
            AND (tawasulAlert.dateStart IS NULL OR tawasulAlert.dateStart<=CURRENT_DATE)
            AND (tawasulAlert.dateEnd IS NULL OR tawasulAlert.dateEnd>=CURRENT_DATE)
            ORDER BY tawasulAlertType.sequenceNumber, tawasulAlertLevel.sequenceNumber DESC, FIND_IN_SET(tawasulAlert.context,'Automatic,Manual'), tawasulAlert.timestampCreated DESC";

        return $this->db()->select($sql, $data);
    }

    public function selectAutomaticAlertsByStudent($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulAlertType.name as groupBy, tawasulAlert.tawasulAlertID, tawasulAlert.type
            FROM tawasulAlert 
            JOIN tawasulAlertType ON (tawasulAlertType.tawasulAlertTypeID=tawasulAlert.tawasulAlertTypeID)
            WHERE tawasulAlert.tawasulSchoolYearID=:tawasulSchoolYearID 
            AND tawasulAlert.tawasulPersonID=:tawasulPersonID 
            AND tawasulAlert.context='Automatic'";

        return $this->db()->select($sql, $data);
    }

    public function getAutomaticAlertCount($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT COUNT(*)
            FROM tawasulAlert 
            JOIN tawasulAlertType ON (tawasulAlertType.tawasulAlertTypeID=tawasulAlert.tawasulAlertTypeID)
            WHERE tawasulAlert.tawasulSchoolYearID=:tawasulSchoolYearID 
            AND tawasulAlert.context='Automatic'";

        return $this->db()->selectOne($sql, $data);
    }

    public function getHighestAlertByType($tawasulSchoolYearID, $tawasulPersonID, $alertType)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'status' => 'Approved', 'name' => $alertType];
        $sql = "SELECT tawasulAlertType.tawasulAlertTypeID, tawasulAlertLevel.tawasulAlertLevelID, tawasulAlertType.name, tawasulAlertType.tag, tawasulAlertType.description, tawasulAlertType.color, tawasulAlertType.colorBG, tawasulAlertType.name as `type`, tawasulAlertLevel.name as `level`, tawasulAlertLevel.color as `levelColor`, tawasulAlertLevel.colorBG as `levelColorBG`
            FROM tawasulAlert 
            JOIN tawasulAlertType ON (tawasulAlertType.tawasulAlertTypeID=tawasulAlert.tawasulAlertTypeID)
            LEFT JOIN tawasulAlertLevel ON (tawasulAlert.tawasulAlertLevelID=tawasulAlertLevel.tawasulAlertLevelID) 
            WHERE tawasulPersonID=:tawasulPersonID 
            AND tawasulAlert.tawasulSchoolYearID=:tawasulSchoolYearID 
            AND tawasulAlert.status=:status 
            AND tawasulAlertType.name=:name
            AND tawasulAlertType.active='Y'
            ORDER BY tawasulAlertLevel.sequenceNumber DESC";

        return $this->db()->selectOne($sql, $data);
    }
}
