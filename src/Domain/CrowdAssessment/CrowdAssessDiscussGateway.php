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

namespace TawasulOS\Domain\CrowdAssessment;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * CrowdAssessDiscuss
 *
 * @version v22
 * @since   v22
 */
class CrowdAssessDiscussGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulCrowdAssessDiscuss';
    private static $primaryKey = 'tawasulCrowdAssessDiscussID';
    private static $searchableColumns = [];

    public function selectDiscussionByHomeworkID($tawasulPlannerEntryHomeworkID, $parent = null)
    {
        $query = $this
            ->newSelect()
            ->cols([
                'tawasulCrowdAssessDiscuss.*', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.image_240', 'tawasulRole.category',
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulPerson', 'tawasulCrowdAssessDiscuss.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulRole', 'tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID')
            ->where('tawasulPlannerEntryHomeworkID=:tawasulPlannerEntryHomeworkID')
            ->bindValue('tawasulPlannerEntryHomeworkID', $tawasulPlannerEntryHomeworkID)
            ->orderBy(['tawasulCrowdAssessDiscuss.timestamp']);

        if ($parent === 0) {
            $query->where('tawasulCrowdAssessDiscussIDReplyTo IS NULL');
        } elseif ($parent != null) {
            $query->where('tawasulCrowdAssessDiscussIDReplyTo=:parent', ['parent' => $parent]);
        }

        return $this->runSelect($query);
    }
}
