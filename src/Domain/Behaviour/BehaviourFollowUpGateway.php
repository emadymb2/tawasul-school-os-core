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

namespace TawasulOS\Domain\Behaviour;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByPerson;

/**
 * @version v27
 * @since   v27
 */
class BehaviourFollowUpGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulBehaviourFollowUp';
    private static $primaryKey = 'tawasulBehaviourFollowUpID';

    private static $searchableColumns = [''];
    
    private static $scrubbableKey = ['tawasulPersonID', 'tawasulBehaviourFollowUpID', 'tawasulBehaviourID'];
    private static $scrubbableColumns = ['followUp' => ''];

    public function selectFollowUpByBehaviourID($tawasulBehaviourID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'tawasulBehaviourFollowUp.*',
                'tawasulBehaviourFollowUp.followUp as comment',
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulPerson.image_240'
            ])
            ->innerJoin('tawasulBehaviour', 'tawasulBehaviourFollowUp.tawasulBehaviourID=tawasulBehaviour.tawasulBehaviourID')
            ->innerJoin('tawasulPerson', 'tawasulBehaviourFollowUp.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where('tawasulBehaviourFollowUp.tawasulBehaviourID=:tawasulBehaviourID')
            ->bindValue('tawasulBehaviourID', $tawasulBehaviourID);

        return $this->runSelect($query);
    }

    public function selectFollowUpsByBehaviorID($tawasulBehaviourIDList)
    {
        $idList = is_array($tawasulBehaviourIDList) ? implode(',', $tawasulBehaviourIDList) : $tawasulBehaviourIDList;
        $data = array('idList' => $idList);
        $sql = "SELECT tawasulBehaviourFollowUp.tawasulBehaviourID, tawasulBehaviourFollowUp.tawasulPersonID, tawasulBehaviourFollowUp.followUp, tawasulPerson.firstName, tawasulPerson.surname
        FROM tawasulBehaviourFollowUp
        JOIN tawasulPerson ON (tawasulBehaviourFollowUp.tawasulPersonID=tawasulPerson.tawasulPersonID)
        WHERE FIND_IN_SET(tawasulBehaviourFollowUp.tawasulBehaviourID, :idList)";

        return $this->db()->select($sql, $data);
    }
}
