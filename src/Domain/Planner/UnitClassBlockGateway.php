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

namespace TawasulOS\Domain\Planner;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * UnitClassBlockGateway
 *
 * @version v21
 * @since   v21
 */
class UnitClassBlockGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulUnitClassBlock';
    private static $primaryKey = 'tawasulUnitClassBlockID';
    private static $searchableColumns = [];
    
    public function selectBlocksByLessonAndClass($tawasulPlannerEntryID, $tawasulCourseClassID)
    {
        $data = ['tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = 'SELECT * FROM tawasulUnitClassBlock 
                JOIN tawasulUnitClass ON (tawasulUnitClassBlock.tawasulUnitClassID=tawasulUnitClass.tawasulUnitClassID) 
                WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID 
                AND tawasulCourseClassID=:tawasulCourseClassID 
                ORDER BY sequenceNumber';

        return $this->db()->select($sql, $data);
    }
    
    public function selectBlocksByUnitAndClass($tawasulUnitID, $tawasulCourseClassID)
    {
        $data = ['tawasulUnitID' => $tawasulUnitID, 'tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = 'SELECT tawasulUnitClassBlock.tawasulPlannerEntryID as groupBy, tawasulUnitClassBlock.*  
                FROM tawasulUnitClassBlock 
                JOIN tawasulUnitClass ON (tawasulUnitClassBlock.tawasulUnitClassID=tawasulUnitClass.tawasulUnitClassID) 
                WHERE tawasulUnitClass.tawasulUnitID=:tawasulUnitID 
                AND tawasulUnitClass.tawasulCourseClassID=:tawasulCourseClassID 
                ORDER BY sequenceNumber';

        return $this->db()->select($sql, $data);
    }

    public function deleteBlocksNotInList($tawasulUnitClassID, $tawasulUnitClassBlockIDList)
    {
        $tawasulUnitClassBlockIDList = is_array($tawasulUnitClassBlockIDList) ? implode(',', $tawasulUnitClassBlockIDList) : $tawasulUnitClassBlockIDList;

        $data = ['tawasulUnitClassID' => $tawasulUnitClassID, 'tawasulUnitClassBlockIDList' => $tawasulUnitClassBlockIDList];
        $sql = "DELETE FROM tawasulUnitClassBlock WHERE tawasulUnitClassID=:tawasulUnitClassID AND NOT FIND_IN_SET(tawasulUnitClassBlockID, :tawasulUnitClassBlockIDList)";

        return $this->db()->delete($sql, $data);
    }

    public function deletePlannerBlocksNotInList($tawasulPlannerEntryID, $tawasulUnitClassBlockIDList)
    {
        $tawasulUnitClassBlockIDList = is_array($tawasulUnitClassBlockIDList) ? implode(',', $tawasulUnitClassBlockIDList) : $tawasulUnitClassBlockIDList;

        $data = ['tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulUnitClassBlockIDList' => $tawasulUnitClassBlockIDList];
        $sql = "DELETE FROM tawasulUnitClassBlock WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID AND NOT FIND_IN_SET(tawasulUnitClassBlockID, :tawasulUnitClassBlockIDList)";

        return $this->db()->delete($sql, $data);
    }
    

}
