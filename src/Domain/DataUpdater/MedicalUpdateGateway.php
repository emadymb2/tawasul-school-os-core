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

namespace TawasulOS\Domain\DataUpdater;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByPerson;

/**
 * @version v16
 * @since   v16
 */
class MedicalUpdateGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulPersonMedicalUpdate';
    private static $primaryKey = 'tawasulPersonMedicalUpdateID';

    private static $searchableColumns = ['target.surname', 'target.preferredName', 'target.username'];

    private static $scrubbableKey = 'tawasulPersonID';
    private static $scrubbableColumns = ['longTermMedication' => '','longTermMedicationDetails' => '','comment' => '', 'fields' => ''];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryDataUpdates(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulPersonMedicalUpdateID', 'tawasulPersonMedicalUpdate.status', 'tawasulPersonMedicalUpdate.timestamp', 'target.preferredName', 'target.surname', 'target.tawasulPersonID as tawasulPersonIDTarget', 'updater.tawasulPersonID as tawasulPersonIDUpdater', 'updater.title as updaterTitle', 'updater.preferredName as updaterPreferredName', 'updater.surname as updaterSurname'
            ])
            ->leftJoin('tawasulPerson AS target', 'target.tawasulPersonID=tawasulPersonMedicalUpdate.tawasulPersonID')
            ->leftJoin('tawasulPerson AS updater', 'updater.tawasulPersonID=tawasulPersonMedicalUpdate.tawasulPersonIDUpdater')
            ->where('tawasulPersonMedicalUpdate.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    public function selectMedicalConditionUpdatesByID($tawasulPersonMedicalUpdateID)
    {
        $data = array('tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID);
        $sql = "SELECT tawasulPersonMedicalConditionUpdate.*, tawasulAlertLevel.name AS risk, (CASE WHEN tawasulMedicalCondition.tawasulMedicalConditionID IS NOT NULL THEN tawasulMedicalCondition.name ELSE tawasulPersonMedicalConditionUpdate.name END) as name 
                FROM tawasulPersonMedicalConditionUpdate
                JOIN tawasulAlertLevel ON (tawasulPersonMedicalConditionUpdate.tawasulAlertLevelID=tawasulAlertLevel.tawasulAlertLevelID)
                LEFT JOIN tawasulMedicalCondition ON (tawasulMedicalCondition.tawasulMedicalConditionID=tawasulPersonMedicalConditionUpdate.name)
                WHERE tawasulPersonMedicalConditionUpdate.tawasulPersonMedicalUpdateID=:tawasulPersonMedicalUpdateID 
                ORDER BY tawasulPersonMedicalConditionUpdate.name";

        return $this->db()->select($sql, $data);
    }
}
