<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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

namespace TawasulOS\Domain\Activities;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

class ActivityCategoryGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulActivityCategory';
    private static $primaryKey = 'tawasulActivityCategoryID';
    private static $searchableColumns = ['tawasulActivityCategory.name','tawasulActivityCategory.nameShort'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryCategories(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols([
                'tawasulActivityCategory.tawasulActivityCategoryID',
                'tawasulActivityCategory.name',
                'tawasulActivityCategory.nameShort',
                'tawasulActivityCategory.description',
                'tawasulActivityCategory.backgroundImage',
                'tawasulActivityCategory.active',
                'tawasulActivityCategory.viewableDate',
                'tawasulActivityCategory.accessOpenDate',
                'tawasulActivityCategory.accessCloseDate',
                'tawasulActivityCategory.accessEnrolmentDate',
                
                "(CASE WHEN CURRENT_TIMESTAMP >= tawasulActivityCategory.viewableDate THEN 'Y' ELSE 'N' END) as viewable",
                "COUNT(DISTINCT tawasulActivity.tawasulActivityID) as activityCount",
            ])
            ->leftJoin('tawasulActivity', 'tawasulActivityCategory.tawasulActivityCategoryID=tawasulActivity.tawasulActivityCategoryID')
            ->where('tawasulActivityCategory.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulActivityCategoryID','name']);

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('tawasulActivityCategory.active = :active')
                    ->bindValue('active', $active);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryCategoriesByPerson(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols([
                ':tawasulPersonID as tawasulPersonID',
                'tawasulActivityCategory.tawasulActivityCategoryID',
                'tawasulActivityCategory.name',
                'tawasulActivityCategory.nameShort',
                'tawasulActivityCategory.description',
                'tawasulActivityCategory.backgroundImage',
                'tawasulActivityCategory.active',
                'tawasulActivityCategory.viewableDate',
                'tawasulActivityCategory.accessOpenDate',
                'tawasulActivityCategory.accessCloseDate',
                'tawasulActivityCategory.accessEnrolmentDate',
                "(CASE WHEN CURRENT_TIMESTAMP >= tawasulActivityCategory.viewableDate THEN 'Y' ELSE 'N' END) as viewable",
                "GROUP_CONCAT(DISTINCT tawasulActivity.name ORDER BY deepLearningChoice.choice SEPARATOR ',') as choices",

            ])
            ->leftJoin('deepLearningChoice', 'deepLearningChoice.tawasulActivityCategoryID=tawasulActivityCategory.tawasulActivityCategoryID AND deepLearningChoice.tawasulPersonID=:tawasulPersonID')
            ->leftJoin('tawasulActivity', 'tawasulActivity.tawasulActivityID=deepLearningChoice.tawasulActivityID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulActivityCategory.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulActivityCategory.viewableDate <= CURRENT_TIMESTAMP')
            ->where('tawasulActivityCategory.active ="Y" ')
            ->groupBy(['tawasulActivityCategory.tawasulActivityCategoryID']);

        return $this->runQuery($query, $criteria);
    }

    public function selectAllCategories()
    {
        $sql = "SELECT tawasulSchoolYear.name as groupBy, tawasulActivityCategory.tawasulActivityCategoryID as value, tawasulActivityCategory.name 
                FROM tawasulActivityCategory
                JOIN tawasulSchoolYear ON (tawasulActivityCategory.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) 
                WHERE tawasulActivityCategory.active='Y'
                ORDER BY tawasulSchoolYear.sequenceNumber DESC, tawasulActivityCategory.sequenceNumber, tawasulActivityCategory.name";

        return $this->db()->select($sql);
    }

    public function selectCategoriesBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulActivityCategory.tawasulActivityCategoryID as value, tawasulActivityCategory.name 
                FROM tawasulActivityCategory
                WHERE tawasulActivityCategory.active='Y'
                AND tawasulActivityCategory.tawasulSchoolYearID=:tawasulSchoolYearID
                ORDER BY tawasulActivityCategory.sequenceNumber, tawasulActivityCategory.name";

        return $this->db()->select($sql, $data);
    }

    public function getCategoryDetailsByID($tawasulActivityCategoryID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID];
        $sql = "SELECT tawasulActivityCategory.*, tawasulSchoolYear.name as schoolYear, (CASE WHEN CURRENT_TIMESTAMP >= tawasulActivityCategory.viewableDate THEN 'Y' ELSE 'N' END) as viewable
                FROM tawasulActivityCategory
                JOIN tawasulSchoolYear ON (tawasulActivityCategory.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) 
                WHERE tawasulActivityCategory.tawasulActivityCategoryID=:tawasulActivityCategoryID
                GROUP BY tawasulActivityCategory.tawasulActivityCategoryID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getCategorySignUpAccess($tawasulActivityCategoryID, $tawasulPersonID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulStudentEnrolment.tawasulStudentEnrolmentID
                FROM tawasulActivityCategory
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivityCategory.tawasulSchoolYearID)
                WHERE tawasulActivityCategory.tawasulActivityCategoryID=:tawasulActivityCategoryID
                AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID
                GROUP BY tawasulActivityCategory.tawasulActivityCategoryID";

        return $this->db()->selectOne($sql, $data);
    }
}
