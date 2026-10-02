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

namespace TawasulOS\Domain\Traits;

/**
 * Implements the ScrubbableGateway interface.
 */
trait ScrubByPerson
{
    public function getScrubbableRecords(string $cutoffDate, array $context = []) : array
    {
        $query = $this
            ->newSelect()
            ->cols([$this->getTableName().'.'.$this->getPrimaryKey(), 'tawasulPerson.tawasulPersonID'])
            ->from($this->getTableName())
            ->where("tawasulPerson.status='Left'");

        // Handle tables that need to be joined with another table to get the scrubbable record
        $scrubbableKey = $this->getScrubbableKey();
        if (is_array($scrubbableKey)) {
            list($keyID, $tableJoin, $tableJoinID) = $scrubbableKey;
            $query->innerJoin($tableJoin, $tableJoin.'.'.$tableJoinID.'='.$this->getTableName().'.'.$tableJoinID)
                  ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID='.$tableJoin.'.'.$keyID);
        } else if (is_string($scrubbableKey)) {
            $query->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID='.$this->getTableName().'.'.$this->getScrubbableKey());
        }

        // Apply the context based on user role
        if (!empty($context)) {
            $query->innerJoin('tawasulRole', 'tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary')
                ->where('FIND_IN_SET(tawasulRole.category, :roleCategories)')
                ->bindValue('roleCategories', implode(',', $context));
        }

        // Only get users whose dateEnd is before the cutoff, falling back to using the lastTimestamp
        $query->where('((tawasulPerson.dateEnd IS NOT NULL AND tawasulPerson.dateEnd < :cutoffDate) 
            OR (tawasulPerson.dateEnd IS NULL AND tawasulPerson.lastTimestamp IS NOT NULL AND tawasulPerson.lastTimestamp < :cutoffDate))')
            ->bindValue('cutoffDate', $cutoffDate);

        return $this->runSelect($query)->fetchGroupedUnique();
    }
}
