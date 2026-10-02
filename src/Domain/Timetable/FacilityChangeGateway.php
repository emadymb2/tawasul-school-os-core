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
 * @version v16
 * @since   v16
 */
class FacilityChangeGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulTTSpaceChange';
    private static $primaryKey = 'tawasulTTSpaceChangeID';

    private static $searchableColumns = ['spaceOld.name', 'spaceNew.name', 'tawasulCourse.nameShort', 'tawasulCourseClass.nameShort', 'tawasulPerson.surname', 'tawasulPerson.preferredName'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryFacilityChanges(QueryCriteria $criteria, $tawasulPersonID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulTTSpaceChangeID', 'date', 'tawasulCourseClass.tawasulCourseClassID', 'tawasulCourse.nameShort as courseName', 'tawasulCourseClass.nameShort as className', 'spaceOld.name as spaceOld', 'spaceNew.name as spaceNew', 'tawasulPerson.preferredName', 'tawasulPerson.surname'
            ])
            ->innerJoin('tawasulTTDayRowClass', 'tawasulTTSpaceChange.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID')
            ->innerJoin('tawasulCourseClass', 'tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->leftJoin('tawasulSpace AS spaceOld', 'tawasulTTDayRowClass.tawasulSpaceID=spaceOld.tawasulSpaceID')
            ->leftJoin('tawasulSpace AS spaceNew', 'tawasulTTSpaceChange.tawasulSpaceID=spaceNew.tawasulSpaceID')
            ->leftJoin('tawasulPerson', 'tawasulTTSpaceChange.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where('date >= :today')
            ->bindValue('today', date('Y-m-d'));

        if (!empty($tawasulPersonID)) {
            $query->leftJoin('tawasulCourseClassPerson', 
                             'tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
                  ->where('tawasulCourseClassPerson.tawasulPersonID = :tawasulPersonID')
                  ->bindValue('tawasulPersonID', $tawasulPersonID);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryFacilityChangesByDepartment(QueryCriteria $criteria, $tawasulPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulTTSpaceChangeID', 'date', 'tawasulCourseClass.tawasulCourseClassID', 'tawasulCourse.nameShort as courseName', 'tawasulCourseClass.nameShort as className', 'spaceOld.name as spaceOld', 'spaceNew.name as spaceNew', 'tawasulPerson.preferredName', 'tawasulPerson.surname'
            ])
            ->innerJoin('tawasulTTDayRowClass', 'tawasulTTSpaceChange.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID')
            ->innerJoin('tawasulCourseClass', 'tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->innerJoin('tawasulCourseClassPerson', 'tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->leftJoin('tawasulSpace AS spaceOld', 'tawasulTTDayRowClass.tawasulSpaceID=spaceOld.tawasulSpaceID')
            ->leftJoin('tawasulSpace AS spaceNew', 'tawasulTTSpaceChange.tawasulSpaceID=spaceNew.tawasulSpaceID')
            ->leftJoin('tawasulPerson', 'tawasulTTSpaceChange.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where('tawasulCourseClassPerson.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('date >= :today')
            ->bindValue('today', date('Y-m-d'));

        $query->union()
            ->from($this->getTableName())
            ->cols([
                'tawasulTTSpaceChangeID', 'date', 'tawasulCourseClass.tawasulCourseClassID', 'tawasulCourse.nameShort as courseName', 'tawasulCourseClass.nameShort as className', 'spaceOld.name as spaceOld', 'spaceNew.name as spaceNew', 'tawasulPerson.preferredName', 'tawasulPerson.surname'
            ])
            ->innerJoin('tawasulTTDayRowClass', 'tawasulTTSpaceChange.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID')
            ->innerJoin('tawasulCourseClass', 'tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->innerJoin('tawasulDepartment', 'tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID')
            ->innerJoin('tawasulDepartmentStaff', 'tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID')
            ->leftJoin('tawasulSpace AS spaceOld', 'tawasulTTDayRowClass.tawasulSpaceID=spaceOld.tawasulSpaceID')
            ->leftJoin('tawasulSpace AS spaceNew', 'tawasulTTSpaceChange.tawasulSpaceID=spaceNew.tawasulSpaceID')
            ->leftJoin('tawasulPerson', 'tawasulTTSpaceChange.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where("tawasulDepartmentStaff.role = 'Coordinator'")
            ->where("tawasulDepartmentStaff.tawasulPersonID = :tawasulPersonID")
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('date >= :today')
            ->bindValue('today', date('Y-m-d'));

        return $this->runQuery($query, $criteria);
    }
}
