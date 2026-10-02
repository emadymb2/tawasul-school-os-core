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

namespace TawasulOS\Domain\System;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\Traits\TableAware;

/**
 * File Gateway
 *
 * @version v31
 * @since   v31
 */
class FileGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulFile';
    private static $primaryKey = 'tawasulFileID';
    
    public function selectFilesByForeignRecord($foreignTable, $foreignTableID)
    {
        $data = ['foreignTable' => $foreignTable, 'foreignTableID' => $foreignTableID];
        $sql = "SELECT tawasulFile.* FROM tawasulFile JOIN tawasulFilePointer ON (tawasulFile.tawasulFileID = tawasulFilePointer.tawasulFileID) WHERE tawasulFilePointer.foreignTable = :foreignTable AND tawasulFilePointer.foreignTableID = :foreignTableID ORDER BY tawasulFile.uploadedAt DESC";

        return $this->db()->select($sql, $data);
    }

    public function selectAllFileRecords()
    {
        $sql = "SELECT tawasulFileID, filePath, fileName, uploadedAt FROM tawasulFile";
        return $this->db()->select($sql);
    }
}
