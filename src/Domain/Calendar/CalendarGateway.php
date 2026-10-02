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

namespace TawasulOS\Domain\Calendar;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v29
 * @since   v29
 */
class CalendarGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulCalendar';
    private static $primaryKey = 'tawasulCalendarID';

    private static $searchableColumns = ['name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryCalendars(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulCalendarID', 'name', 'description', 'color', 'public', 'viewableStaff', 'viewableStudents', 'viewableParents', 'viewableOther', 'viewableParticipants', 'editableStaff',
                '(SELECT COUNT(*) FROM tawasulCalendarEditor WHERE tawasulCalendarID=tawasulCalendar.tawasulCalendarID) as editors'
            ])
            ->where('tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    public function selectActiveCalendarsBySchoolYear($tawasulSchoolYearID)
    {
        $select = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'tawasulCalendar.tawasulCalendarID', 'tawasulCalendar.name', 'tawasulCalendar.description', 'tawasulCalendar.color', 'tawasulCalendar.sequenceNumber', 'tawasulCalendar.public', 'tawasulCalendar.viewableStaff', 'tawasulCalendar.viewableStudents', 'tawasulCalendar.viewableParents', 'tawasulCalendar.viewableOther', 'tawasulCalendar.viewableParticipants', 'tawasulCalendar.editableStaff'
            ])
            ->where('tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->orderBy(['tawasulCalendar.sequenceNumber', 'tawasulCalendar.name']);

        return $this->runSelect($select);
    }

    public function selectEditableCalendarsByPerson($tawasulSchoolYearID, $tawasulPersonID)
    {
        $select = $this
            ->newSelect()
            ->cols([
                'tawasulCalendar.tawasulCalendarID as value', 'tawasulCalendar.name'
            ])
            ->from($this->getTableName())
            
            ->where('tawasulCalendar.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->orderBy(['tawasulCalendar.sequenceNumber', 'tawasulCalendar.name']);

        if (!empty($tawasulPersonID)) {
            $select
                ->leftJoin('tawasulCalendarEditor', 'tawasulCalendarEditor.tawasulCalendarID=tawasulCalendar.tawasulCalendarID AND tawasulCalendarEditor.tawasulPersonID=:tawasulPersonID')
                ->leftJoin('tawasulStaff', 'tawasulStaff.tawasulPersonID=:tawasulPersonID')
                ->where('( (tawasulCalendar.editableStaff="Y" AND tawasulStaff.tawasulStaffID IS NOT NULL) OR (tawasulCalendarEditor.tawasulCalendarEditorID IS NOT NULL) )')
                ->bindValue('tawasulPersonID', $tawasulPersonID);
        }

        return $this->runSelect($select);
    }
}
