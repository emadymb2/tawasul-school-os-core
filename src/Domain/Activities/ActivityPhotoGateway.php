<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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

namespace TawasulOS\Domain\Activities;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

class ActivityPhotoGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulActivityPhoto';
    private static $primaryKey = 'tawasulActivityPhotoID';
    private static $searchableColumns = [''];

    public function selectPhotosByActivityCategory($tawasulActivityCategoryID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID];
        $sql = "SELECT tawasulActivityPhoto.tawasulActivityID, tawasulActivityPhoto.tawasulActivityPhotoID, tawasulActivityPhoto.filePath, tawasulActivityPhoto.caption
                FROM tawasulActivityPhoto
                JOIN tawasulActivity ON (tawasulActivity.tawasulActivityID=tawasulActivityPhoto.tawasulActivityID)
                WHERE tawasulActivity.tawasulActivityCategoryID=:tawasulActivityCategoryID
                AND tawasulActivityPhoto.sequenceNumber=1
                ORDER BY tawasulActivityPhoto.sequenceNumber";

        return $this->db()->select($sql, $data);
    }

    public function selectPhotosByActivity($tawasulActivityID)
    {
        $data = ['tawasulActivityID' => $tawasulActivityID];
        $sql = "SELECT tawasulActivityPhoto.tawasulActivityPhotoID, tawasulActivityPhoto.filePath, tawasulActivityPhoto.caption, tawasulActivityPhoto.sequenceNumber
                FROM tawasulActivityPhoto
                JOIN tawasulActivity ON (tawasulActivity.tawasulActivityID=tawasulActivityPhoto.tawasulActivityID)
                WHERE tawasulActivityPhoto.tawasulActivityID=:tawasulActivityID
                ORDER BY tawasulActivityPhoto.sequenceNumber";

        return $this->db()->select($sql, $data);
    }

    public function selectPhotosNotInList($tawasulActivityID, $photoIDList)
    {
        $photoIDList = is_array($photoIDList) ? implode(',', $photoIDList) : $photoIDList;

        $data = ['tawasulActivityID' => $tawasulActivityID, 'photoIDList' => $photoIDList];
        $sql = "SELECT * FROM tawasulActivityPhoto WHERE tawasulActivityID=:tawasulActivityID AND NOT FIND_IN_SET(tawasulActivityPhotoID, :photoIDList)";

        return $this->db()->select($sql, $data);
    }
}
