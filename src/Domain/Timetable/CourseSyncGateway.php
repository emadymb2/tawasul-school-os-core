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

namespace TawasulOS\Domain\Timetable;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v17
 * @since   v17
 */
class CourseSyncGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulCourseClassMap';
    private static $primaryKey = 'tawasulCourseClassMapID';
    private static $searchableColumns = [];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryCourseClassMaps(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulCourseClassMap.tawasulCourseClassID',
                'tawasulCourseClassMap.tawasulFormGroupID',
                'tawasulCourseClassMap.tawasulYearGroupID',
                'tawasulYearGroup.tawasulYearGroupID',
                'tawasulFormGroup.name as formGroupName',
                'tawasulYearGroup.name as yearGroupName',
                'COUNT(DISTINCT tawasulCourseClassMap.tawasulCourseClassID) as classCount',
                "GROUP_CONCAT(DISTINCT tawasulFormGroup.nameShort ORDER BY tawasulFormGroup.nameShort SEPARATOR ', ') as formGroupList",
                "GROUP_CONCAT(DISTINCT tawasulFormGroup.tawasulFormGroupID ORDER BY tawasulFormGroup.tawasulFormGroupID SEPARATOR ',') as tawasulFormGroupIDList",
            ])
            ->innerJoin('tawasulFormGroup', 'tawasulCourseClassMap.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulCourseClassMap.tawasulYearGroupID')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassMap.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->where('FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulCourse.tawasulYearGroupIDList)')
            ->where('tawasulCourse.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulYearGroup.tawasulYearGroupID']);

        return $this->runQuery($query, $criteria);
    }
}
