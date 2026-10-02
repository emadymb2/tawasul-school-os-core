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

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryableGateway;

/**
 * File Pointer Gateway
 *
 * @version v31
 * @since   v31
 */
class FilePointerGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulFilePointer';
    private static $primaryKey = 'tawasulFilePointerID';

    public function getFileAndPointerID(string $foreignTable, int|string $foreignTableID, string $foreignColumn)
    {
        $data = ['foreignTable' => $foreignTable, 'foreignTableID' => $foreignTableID, 'foreignColumn' => $foreignColumn];

        $sql = "SELECT tawasulFilePointer.tawasulFilePointerID, tawasulFile.tawasulFileID, tawasulFile.filePath
                FROM tawasulFilePointer
                JOIN tawasulFile ON tawasulFilePointer.tawasulFileID = tawasulFile.tawasulFileID
                WHERE tawasulFilePointer.foreignTable = :foreignTable
                AND tawasulFilePointer.foreignTableID = :foreignTableID
                AND tawasulFilePointer.foreignColumn = :foreignColumn";

        return $this->db()->selectOne($sql, $data);
    }

    public function countPointersByFileID(int $tawasulFileID)
    {
        $data = ['tawasulFileID' => $tawasulFileID];
        $sql = "SELECT COUNT(*) as count FROM tawasulFilePointer WHERE tawasulFileID = :tawasulFileID";
        
       return $this->db()->select($sql, $data);
    }
}