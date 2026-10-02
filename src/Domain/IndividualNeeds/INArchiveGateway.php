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

namespace TawasulOS\Domain\IndividualNeeds;

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
class INArchiveGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulINArchive';
    private static $primaryKey = 'tawasulINArchiveID';

    private static $searchableColumns = [];

    private static $scrubbableKey = 'tawasulPersonID';
    private static $scrubbableColumns = ['strategies' => '','targets' => '','notes' => '','descriptors' => ''];

    public function selectINArchivesByPersonID($tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulINArchiveID as groupBy, tawasulINArchive.* FROM tawasulINArchive WHERE tawasulPersonID=:tawasulPersonID ORDER BY archiveTimestamp DESC";

        return $this->db()->select($sql, $data);
    }
    
}
