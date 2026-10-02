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

namespace TawasulOS\Domain\Staff;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * StaffFacilityGateway Gateway
 *
 * @version v20
 * @since   v20
 */
class StaffFacilityGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulSpacePerson';
    private static $primaryKey = 'tawasulSpacePersonID';

    private static $searchableColumns = [];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryFacilitiesByPerson(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->cols([
                'tawasulSpace.*', 'tawasulSpacePerson.tawasulSpacePersonID', 'usageType', "NULL AS exception"
            ])
            ->from('tawasulSpacePerson')
            ->innerJoin('tawasulSpace', 'tawasulSpacePerson.tawasulSpaceID=tawasulSpace.tawasulSpaceID')
            ->where('tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);

        $this->unionWithCriteria($query, $criteria)
            ->distinct()
            ->cols([
                'tawasulSpace.*', 'NULL AS tawasulSpacePersonID', "'Form Group' as usageType", "NULL AS exception"
            ])
            ->from('tawasulFormGroup')
            ->innerJoin('tawasulSpace', 'tawasulFormGroup.tawasulSpaceID=tawasulSpace.tawasulSpaceID')
            ->where('(tawasulPersonIDTutor=:tawasulPersonID OR tawasulPersonIDTutor2=:tawasulPersonID OR tawasulPersonIDTutor3=:tawasulPersonID)')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        $this->unionWithCriteria($query, $criteria)
            ->distinct()
            ->cols([
                'tawasulSpace.*', 'NULL AS tawasulSpacePersonID', "'Timetable' as usageType", 'tawasulTTDayRowClassException.tawasulPersonID AS exception'
            ])
            ->from('tawasulSpace')
            ->innerJoin('tawasulTTDayRowClass', 'tawasulTTDayRowClass.tawasulSpaceID=tawasulSpace.tawasulSpaceID')
            ->innerJoin('tawasulCourseClass', 'tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->innerJoin('tawasulCourseClassPerson', 'tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->leftJoin('tawasulTTDayRowClassException', 'tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND (tawasulTTDayRowClassException.tawasulPersonID=:tawasulPersonID OR tawasulTTDayRowClassException.tawasulPersonID IS NULL)')
            ->where('tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        return $this->runQuery($query, $criteria);
    }
}
