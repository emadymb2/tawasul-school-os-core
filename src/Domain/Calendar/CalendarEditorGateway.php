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
class CalendarEditorGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulCalendarEditor';
    private static $primaryKey = 'tawasulCalendarEditorID';

    private static $searchableColumns = [];

    public function selectEditorsByCalendar($tawasulCalendarID)
    {
        $data = ['tawasulCalendarID' => $tawasulCalendarID];
        $sql = "SELECT tawasulPerson.tawasulPersonID as groupBy,
                    tawasulCalendarEditor.tawasulCalendarEditorID,
                    tawasulCalendarEditor.editAllEvents,
                    tawasulPerson.tawasulPersonID,
                    tawasulPerson.surname,
                    tawasulPerson.preferredName,
                    tawasulPerson.image_240
                FROM tawasulCalendarEditor
                JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulCalendarEditor.tawasulPersonID) 
                WHERE tawasulCalendarEditor.tawasulCalendarID=:tawasulCalendarID
                ORDER BY tawasulCalendarEditor.tawasulCalendarEditorID, tawasulPerson.surname, tawasulPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    public function deleteEditorsNotInList($tawasulCalendarID, $editorIDList)
    {
        $editorIDList = is_array($editorIDList) ? implode(',', $editorIDList) : $editorIDList;

        $data = ['tawasulCalendarID' => $tawasulCalendarID, 'editorIDList' => $editorIDList];
        $sql = "DELETE FROM tawasulCalendarEditor WHERE tawasulCalendarID=:tawasulCalendarID AND NOT FIND_IN_SET(tawasulCalendarEditorID, :editorIDList)";

        return $this->db()->delete($sql, $data);
    }
}
