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
 * AlarmLevel Gateway.
 *
 * @version v25
 * @since   v25
 */
class AlertLevelGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulAlertLevel';
    private static $primaryKey = 'tawasulAlertLevelID';

    const LEVEL_HIGH = 1;
    const LEVEL_MEDIUM = 2;
    const LEVEL_LOW = 3;

    /**
     * Get the specified alert level.
     *
     * @version v25
     * @since   v25
     *
     * @param integer $tawasulAlertLevelID  The ID of the alert level.
     * @param bool    $translated          To translate name and description field of the
     *                                     alert. Default: true.
     *
     * @return array|false  An assoc array of the selected alert,
     *                      or false if not exists.
     *                      The field 'name' and 'description' are
     *                      translated unless $translated is false.
     */
    public function getByID(int $tawasulAlertLevelID, bool $translated = true)
    {
        $sql = 'SELECT * FROM tawasulAlertLevel WHERE tawasulAlertLevelID=:tawasulAlertLevelID';
        $row = $this->db()
            ->selectOne($sql, [
                'tawasulAlertLevelID' => $tawasulAlertLevelID,
            ]);
        return (!empty($row) && $translated)
            ? [
                'tawasulAlertLevelID' => $row['tawasulAlertLevelID'],
                'name' => __($row['name']),
                'nameShort' => $row['nameShort'],
                'color' => $row['color'],
                'colorBG' => $row['colorBG'],
                'description' => __($row['description']),
                'sequenceNumber' => $row['sequenceNumber'],
            ]
            : $row;
    }

    public function selectAlertLevels()
    {
        $data= [];
        $sql = "SELECT tawasulAlertLevelID as value, name FROM tawasulAlertLevel ORDER BY sequenceNumber";
        
        return $this->db()->select($sql, $data);
    }
}
