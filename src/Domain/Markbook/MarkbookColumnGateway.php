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

namespace TawasulOS\Domain\Markbook;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * Markbook Column Gateway
 *
 * @version v17
 * @since   v17
 */
class MarkbookColumnGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulMarkbookColumn';
    private static $primaryKey = 'tawasulMarkbookColumnID';
    private static $searchableColumns = ['name', 'description', 'type'];
    
    /**
     * 
     */
    public function queryMarkbookColumnsByClass(QueryCriteria $criteria, $tawasulCourseClassID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulMarkbookColumn')
            ->cols(['*','tawasulMarkbookColumn.name as name','tawasulMarkbookColumn.sequenceNumber as sequenceNumber'])
            ->where('tawasulMarkbookColumn.tawasulCourseClassID = :tawasulCourseClassID')
            ->bindValue('tawasulCourseClassID', $tawasulCourseClassID)
            ->groupBy(['tawasulMarkbookColumn.tawasulMarkbookColumnID']);

        $criteria->addFilterRules([
            'term' => function ($query, $tawasulSchoolYearTermID) {
                if (intval($tawasulSchoolYearTermID) <= 0) return $query;

                return $query
                    ->innerJoin('tawasulSchoolYearTerm', 'tawasulSchoolYearTerm.tawasulSchoolYearTermID=tawasulMarkbookColumn.tawasulSchoolYearTermID 
                        OR tawasulMarkbookColumn.date BETWEEN tawasulSchoolYearTerm.firstDay AND tawasulSchoolYearTerm.lastDay')
                    ->where('tawasulSchoolYearTerm.tawasulSchoolYearTermID = :tawasulSchoolYearTermID')
                    ->bindValue('tawasulSchoolYearTermID', $tawasulSchoolYearTermID);
            },
            'show' => function ($query, $show) {
                switch ($show) {
                    case 'marked'  : $query->where("complete = 'Y'"); break;
                    case 'unmarked': $query->where("complete = 'N'"); break;
                }
                return $query;
            },
        ]);

        return $this->runQuery($query, $criteria);
    }
}
