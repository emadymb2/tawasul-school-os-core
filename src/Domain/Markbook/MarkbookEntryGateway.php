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
 * Markbook Entry Gateway
 *
 * @version v20
 * @since   v20
 */
class MarkbookEntryGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulMarkbookEntry';
    private static $primaryKey = 'tawasulMarkbookEntryID';
    private static $searchableColumns = [];
    
    public function selectMarkbookEntriesByClassAndStudent($tawasulCourseClassID, $tawasulPersonIDStudent)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonIDStudent' => $tawasulPersonIDStudent];
        $sql = "SELECT tawasulMarkbookColumn.type, tawasulMarkbookEntry.*, tawasulMarkbookColumn.*, tawasulMarkbookEntry.comment as commentValue, tawasulMarkbookWeight.calculate
                FROM tawasulMarkbookEntry 
                JOIN tawasulMarkbookColumn ON (tawasulMarkbookColumn.tawasulMarkbookColumnID=tawasulMarkbookEntry.tawasulMarkbookColumnID) 
                JOIN tawasulScale ON (tawasulMarkbookColumn.tawasulScaleIDAttainment=tawasulScale.tawasulScaleID)
                LEFT JOIN tawasulMarkbookWeight ON (tawasulMarkbookWeight.type=tawasulMarkbookColumn.type AND tawasulMarkbookWeight.tawasulCourseClassID=tawasulMarkbookColumn.tawasulCourseClassID)
                WHERE tawasulMarkbookColumn.tawasulCourseClassID=:tawasulCourseClassID
                AND tawasulMarkbookColumn.attainment='Y'
                AND tawasulMarkbookColumn.attainmentWeighting > 0.0
                AND tawasulMarkbookColumn.attainmentType = 'Summative'
                AND tawasulMarkbookEntry.tawasulPersonIDStudent=:tawasulPersonIDStudent
                AND tawasulMarkbookEntry.attainmentValue IS NOT NULL
                AND tawasulMarkbookEntry.attainmentValue <> ''
                AND tawasulScale.tawasulScaleID = 0001
                ORDER BY tawasulMarkbookWeight.calculate, tawasulMarkbookColumn.type, tawasulMarkbookColumn.date
                ";

        return $this->db()->select($sql, $data);
    }
    public function selectMarkbookConcernsByStudentAndDate($tawasulSchoolYearID, $tawasulPersonID, $days = 60)
    {
        $data = ['tawasulPersonIDStudent' => $tawasulPersonID, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'today' => date('Y-m-d'), 'date' => date('Y-m-d', (time() - (24 * 60 * 60 * $days)))];
        $sql = "SELECT tawasulMarkbookEntry.* FROM tawasulMarkbookEntry 
            JOIN tawasulMarkbookColumn ON (tawasulMarkbookEntry.tawasulMarkbookColumnID=tawasulMarkbookColumn.tawasulMarkbookColumnID) 
            JOIN tawasulCourseClass ON (tawasulMarkbookColumn.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) 
            JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) 
            WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
            AND tawasulMarkbookEntry.tawasulPersonIDStudent=:tawasulPersonIDStudent 
            AND (tawasulMarkbookEntry.attainmentConcern='Y' OR tawasulMarkbookEntry.effortConcern='Y') 
            AND tawasulMarkbookColumn.complete='Y' 
            AND tawasulMarkbookColumn.completeDate<=:today 
            AND tawasulMarkbookColumn.completeDate>:date";
        
        return $this->db()->select($sql, $data);
    }
}
