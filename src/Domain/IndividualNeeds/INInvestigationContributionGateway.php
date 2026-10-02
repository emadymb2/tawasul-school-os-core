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
class INInvestigationContributionGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulINInvestigationContribution';
    private static $primaryKey = 'tawasulINInvestigationContributionID';

    private static $searchableColumns = [];

    private static $scrubbableKey = ['tawasulPersonIDStudent', 'tawasulINInvestigation', 'tawasulINInvestigationID'];
    private static $scrubbableColumns = ['cognition'=> null,'memory'=> null,'selfManagement'=> null,'attention'=> null,'socialInteraction'=> null,'communication'=> null,'comment'=> null];

    /**
     * @param QueryCriteria $criteria
     * @param int $tawasulINInvestigationID
     * @return DataSet
     */
    public function queryContributionsByInvestigation(QueryCriteria $criteria, $tawasulINInvestigationID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulINInvestigationContribution.*',
                'surname',
                'preferredName',
                'tawasulCourse.nameShort AS course',
                'tawasulCourseClass.nameShort AS class'
            ])
            ->innerJoin('tawasulPerson','tawasulINInvestigationContribution.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulCourseClassPerson', 'tawasulINInvestigationContribution.tawasulCourseClassPersonID=tawasulCourseClassPerson.tawasulCourseClassPersonID')
            ->leftJoin('tawasulCourseClass', 'tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->leftJoin('tawasulCourse', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->where('tawasulINInvestigationID=:tawasulINInvestigationID')
            ->bindValue('tawasulINInvestigationID', $tawasulINInvestigationID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @param int $tawasulPersonID
     * @param string $status
     * @return DataSet
     */
    public function queryContributionsByPerson(QueryCriteria $criteria, $tawasulPersonID, $status = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulINInvestigationContribution.*',
                'tawasulINInvestigation.tawasulPersonIDStudent',
                'student.surname',
                'student.preferredName',
                'tawasulFormGroup.nameShort AS formGroup',
                'creator.title AS titleCreator',
                'creator.surname AS surnameCreator',
                'creator.preferredName AS preferredNameCreator',
                'date',
                'type',
                'tawasulCourse.nameShort AS course',
                'tawasulCourseClass.nameShort AS class'
            ])
            ->innerJoin('tawasulINInvestigation', 'tawasulINInvestigationContribution.tawasulINInvestigationID=tawasulINInvestigation.tawasulINInvestigationID')
            ->innerJoin('tawasulPerson AS student', 'tawasulINInvestigation.tawasulPersonIDStudent=student.tawasulPersonID')
            ->innerJoin('tawasulPerson AS creator', 'tawasulINInvestigation.tawasulPersonIDCreator=creator.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulINInvestigation.tawasulSchoolYearID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulCourseClassPerson', 'tawasulINInvestigationContribution.tawasulCourseClassPersonID=tawasulCourseClassPerson.tawasulCourseClassPersonID')
            ->leftJoin('tawasulCourseClass', 'tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->leftJoin('tawasulCourse', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->where('tawasulINInvestigationContribution.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);

        if (!empty($status)) {
            $query->where('tawasulINInvestigationContribution.status=:status')
            ->bindValue('status', $status);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param int $tawasulINInvestigationContributionID
     * @return array
     */
    public function getContributionByID($tawasulINInvestigationContributionID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'tawasulINInvestigationContribution.*',
                'student.surname',
                'student.preferredName',
                'tawasulFormGroup.nameShort AS formGroup',
                'creator.title AS titleCreator',
                'creator.surname AS surnameCreator',
                'creator.preferredName AS preferredNameCreator',
                'date',
                'type',
                'tawasulCourse.nameShort AS course',
                'tawasulCourseClass.nameShort AS class'
            ])
            ->innerJoin('tawasulINInvestigation', 'tawasulINInvestigationContribution.tawasulINInvestigationID=tawasulINInvestigation.tawasulINInvestigationID')
            ->innerJoin('tawasulPerson AS student', 'tawasulINInvestigation.tawasulPersonIDStudent=student.tawasulPersonID')
            ->innerJoin('tawasulPerson AS creator', 'tawasulINInvestigation.tawasulPersonIDCreator=creator.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulINInvestigation.tawasulSchoolYearID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulCourseClassPerson', 'tawasulINInvestigationContribution.tawasulCourseClassPersonID=tawasulCourseClassPerson.tawasulCourseClassPersonID')
            ->leftJoin('tawasulCourseClass', 'tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->leftJoin('tawasulCourse', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->where('tawasulINInvestigationContribution.tawasulINInvestigationContributionID=:tawasulINInvestigationContributionID')
            ->bindValue('tawasulINInvestigationContributionID', $tawasulINInvestigationContributionID);

        return $this->runSelect($query)->fetch();
    }

    /**
     * @param string $tawasulINInvestigationID
     * @return array
     */
    public function getInvestigationCompletion($tawasulINInvestigationID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                "COUNT(DISTINCT CASE WHEN status = 'Complete' THEN tawasulINInvestigationContributionID END) AS complete",
                "COUNT(DISTINCT tawasulINInvestigationContributionID) AS total"
            ])
            ->where('tawasulINInvestigationID=:tawasulINInvestigationID')
            ->bindValue('tawasulINInvestigationID', $tawasulINInvestigationID);

        return $this->runSelect($query)->fetch();
    }

    /**
     * @param QueryCriteria $criteria
     * @return array
     */
    public function queryInvestigationStatistics(QueryCriteria $criteria, $tawasulINInvestigationID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                '*'
            ])
            ->where('tawasulINInvestigationID=:tawasulINInvestigationID')
            ->bindValue('tawasulINInvestigationID', $tawasulINInvestigationID);

        $results = $this->runQuery($query, $criteria);

        //Turn data into statistical table
        $strands = getInvestigationCriteriaStrands(true);
        $count = 0 ;
        for ($i = 0; $i < count($strands); $i++) {
            $strands[$i]['data'] = array();
            $criteria = getInvestigationCriteriaArray($strands[$i]['nameHuman']);
            foreach ($criteria as $criterion) {
                $strands[$i]['data'][$criterion] = 0;
                foreach ($results as $result) {
                    $resultData = @unserialize($result[$strands[$i]['name']]);
                    if (is_array($resultData)) {
                        foreach ($resultData AS $resultDatum) {
                            if ($resultDatum == $criterion) {
                                $strands[$i]['data'][$criterion] ++;
                            }
                        }
                    }
                    else {
                        $resultData = $result[$strands[$i]['name']];
                        if ($resultData == $criterion) {
                            $strands[$i]['data'][$criterion] ++;
                        }
                    }
                }
            }
        }

        return $strands;
    }
}
