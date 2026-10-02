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

namespace TawasulOS\Domain\IndividualNeeds;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByPerson;

/**
 * Investigations Gateway
 *
 * @version v19
 * @since   v19
 */
class INInvestigationGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulINInvestigation';
    private static $primaryKey = 'tawasulINInvestigationID';

    private static $searchableColumns = [];

    private static $scrubbableKey = 'tawasulPersonIDStudent';
    private static $scrubbableColumns = ['date' => '','reason' => '','strategiesTried' => '','parentsInformed' => '','parentsResponse'=> null,'resolutionDetails'=> null];

    /**
     * @param QueryCriteria $criteria
     * @param int $tawasulSchoolYearID
     * @param int $tawasulPersonIDCreator
     * @return DataSet
     */
    public function queryInvestigations(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonIDCreator = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulINInvestigation.*',
                'student.tawasulPersonID',
                'student.surname',
                'student.preferredName',
                'tawasulFormGroup.nameShort AS formGroup',
                'creator.title AS titleCreator',
                'creator.surname AS surnameCreator',
                'creator.preferredName AS preferredNameCreator'
            ])
            ->innerJoin('tawasulPerson AS student', 'tawasulINInvestigation.tawasulPersonIDStudent=student.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulPerson AS creator', 'tawasulINInvestigation.tawasulPersonIDCreator=creator.tawasulPersonID')
            ->where('tawasulINInvestigation.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID=tawasulINInvestigation.tawasulSchoolYearID');

        if (!empty($tawasulPersonIDCreator)) {
            $query->where('(tawasulINInvestigation.tawasulPersonIDCreator=:tawasulPersonIDCreator OR tawasulFormGroup.tawasulPersonIDTutor=:tawasulPersonIDCreator OR tawasulFormGroup.tawasulPersonIDTutor2=:tawasulPersonIDCreator OR tawasulFormGroup.tawasulPersonIDTutor3=:tawasulPersonIDCreator)')
                ->bindValue('tawasulPersonIDCreator', $tawasulPersonIDCreator);
        }

        $criteria->addFilterRules([
            'student' => function ($query, $tawasulPersonID) {
                return $query
                    ->where('tawasulINInvestigation.tawasulPersonIDStudent=:tawasulPersonID')
                    ->bindValue('tawasulPersonID', $tawasulPersonID);
            },
            'formGroup' => function ($query, $tawasulFormGroupID) {
                return $query
                    ->where('tawasulStudentEnrolment.tawasulFormGroupID=:tawasulFormGroupID')
                    ->bindValue('tawasulFormGroupID', $tawasulFormGroupID);
            },
            'yearGroup' => function ($query, $tawasulYearGroupID) {
                return $query
                    ->where('tawasulStudentEnrolment.tawasulYearGroupID=:tawasulYearGroupID')
                    ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param int $tawasulINInvestigationID
     * @return array
     */
    public function getInvestigationByID($tawasulINInvestigationID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'tawasulINInvestigation.*',
                'student.tawasulPersonID',
                'student.surname',
                'student.preferredName',
                'tawasulFormGroup.nameShort AS formGroup',
                'creator.title AS titleCreator',
                'creator.surname AS surnameCreator',
                'creator.preferredName AS preferredNameCreator',
                'tawasulFormGroup.tawasulPersonIDTutor',
                'tawasulFormGroup.tawasulPersonIDTutor2',
                'tawasulFormGroup.tawasulPersonIDTutor3',
                'tawasulYearGroup.tawasulPersonIDHOY',
                'tawasulYearGroup.tawasulYearGroupID'
            ])
            ->innerJoin('tawasulPerson AS student', 'tawasulINInvestigation.tawasulPersonIDStudent=student.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->innerJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->leftJoin('tawasulPerson AS creator', 'tawasulINInvestigation.tawasulPersonIDCreator=creator.tawasulPersonID')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID=tawasulINInvestigation.tawasulSchoolYearID')
            ->bindValue('tawasulINInvestigationID', $tawasulINInvestigationID)
            ->where('tawasulINInvestigation.tawasulINInvestigationID=:tawasulINInvestigationID');

        return $this->runSelect($query)->fetch();
    }

    /**
     * @param int $tawasulSchoolYearID
     * @param int $tawasulPersonID
     * @return result
     */
    public function queryTeachersByInvestigation($tawasulSchoolYearID, $tawasulPersonID)
    {
        $result = null;

        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID);
        $sql = "SELECT tawasulCourseClassTeacher.tawasulCourseClassPersonID, tawasulCourseClassTeacher.tawasulPersonID, tawasulCourseClass.tawasulCourseClassID, tawasulCourseClass.nameShort AS class, tawasulCourse.nameShort AS course, surname, preferredName
            FROM tawasulCourse
                JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                JOIN tawasulCourseClassPerson AS tawasulCourseClassStudent ON (tawasulCourseClassStudent.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourseClassStudent.role='Student')
                JOIN tawasulCourseClassPerson AS tawasulCourseClassTeacher ON (tawasulCourseClassTeacher.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourseClassTeacher.role='Teacher')
                JOIN tawasulPerson ON (tawasulCourseClassTeacher.tawasulPersonID=tawasulPerson.tawasulPersonID)
            WHERE tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulCourseClassStudent.tawasulPersonID=:tawasulPersonID
                AND tawasulCourseClass.reportable='Y'
                AND tawasulCourseClassStudent.reportable='Y'
            ORDER BY course, class";
        $result = $this->db()->select($sql, $data);

        return $result;
    }

    /**
     * @param int $tawasulSchoolYearID
     * @param int $tawasulPersonID
     * @return result
     */
    public function queryHOYByInvestigation($tawasulSchoolYearID, $tawasulPersonID)
    {
        $result = null;

        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID);
        $sql = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName
            FROM tawasulStudentEnrolment
                JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                JOIN tawasulPerson ON (tawasulYearGroup.tawasulPersonIDHOY=tawasulPerson.tawasulPersonID)
            WHERE tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID";
        $result = $this->db()->select($sql, $data);

        return $result;
    }
}
