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

namespace TawasulOS\Domain\User;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByPerson;

/**
 * @version v21
 * @since   v21
 */
class FamilyAdultGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulFamilyAdult';
    private static $primaryKey = 'tawasulFamilyAdultID';

    private static $searchableColumns = [''];

    private static $scrubbableKey = 'tawasulPersonID';
    private static $scrubbableColumns = ['comment' => ''];

    public function deleteFamilyAdult($tawasulFamilyID, $tawasulPersonIDAdult)
    {
        $query = $this
            ->newDelete()
            ->from('tawasulFamilyAdult')
            ->where('tawasulFamilyID=:tawasulFamilyID', ['tawasulFamilyID' => $tawasulFamilyID])
            ->where('tawasulPersonID=:tawasulPersonIDAdult', ['tawasulPersonIDAdult' => $tawasulPersonIDAdult]);

        return $this->runDelete($query);
    }
    
    public function insertFamilyRelationship($tawasulFamilyID, $tawasulPersonIDAdult, $tawasulPersonIDStudent, $relationship)
    {
        $data = ['tawasulFamilyID' => $tawasulFamilyID, 'tawasulPersonID1' => $tawasulPersonIDAdult, 'tawasulPersonID2' => $tawasulPersonIDStudent];
        $sql = "SELECT tawasulFamilyRelationshipID FROM tawasulFamilyRelationship WHERE tawasulFamilyID=:tawasulFamilyID AND tawasulPersonID1=:tawasulPersonID1 AND tawasulPersonID2=:tawasulPersonID2";
        
        $existing = $this->db()->selectOne($sql, $data);

        if ($existing) {
            $query = $this
                ->newUpdate()
                ->table('tawasulFamilyRelationship')
                ->cols(['relationship' => $relationship])
                ->where('tawasulFamilyID=:tawasulFamilyID', ['tawasulFamilyID' => $tawasulFamilyID])
                ->where('tawasulPersonID1=:tawasulPersonIDAdult', ['tawasulPersonIDAdult' => $tawasulPersonIDAdult])
                ->where('tawasulPersonID2=:tawasulPersonIDStudent', ['tawasulPersonIDStudent' => $tawasulPersonIDStudent]);

            return $this->runUpdate($query);
        } else {
            $query = $this
                ->newInsert()
                ->into('tawasulFamilyRelationship')
                ->cols([
                    'tawasulFamilyID'  => $tawasulFamilyID,
                    'tawasulPersonID1' => $tawasulPersonIDAdult,
                    'tawasulPersonID2' => $tawasulPersonIDStudent,
                    'relationship'    => $relationship,
                ]);

            return $this->runInsert($query);
        }
    }

    public function deleteFamilyRelationship($tawasulFamilyID, $tawasulPersonIDAdult, $tawasulPersonIDStudent)
    {
        $query = $this
            ->newDelete()
            ->from('tawasulFamilyRelationship')
            ->where('tawasulFamilyID=:tawasulFamilyID', ['tawasulFamilyID' => $tawasulFamilyID])
            ->where('tawasulPersonID1=:tawasulPersonIDAdult', ['tawasulPersonIDAdult' => $tawasulPersonIDAdult])
            ->where('tawasulPersonID2=:tawasulPersonIDStudent', ['tawasulPersonIDStudent' => $tawasulPersonIDStudent]);

        return $this->runDelete($query);
    }
}
