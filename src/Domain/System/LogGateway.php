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

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\Traits\TableAware;

/**
 * Log Gateway
 *
 * @version v17
 * @since   v17
 */
class LogGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulLog';
    private static $primaryKey = 'tawasulLogID';

    private static $searchableColumns = ['title'];

    /**
     * Queries the list of System logs.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryLogs(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulLogID', 'tawasulModule.name AS module', 'surname', 'preferredName', 'username', 'tawasulSchoolYear.name AS schoolYear', 'timestamp', 'tawasulLog.title', 'serialisedArray', 'ip'
            ])
            ->leftJoin('tawasulModule', 'tawasulLog.tawasulModuleID=tawasulModule.tawasulModuleID')
            ->leftJoin('tawasulPerson', 'tawasulLog.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulSchoolYear', 'tawasulLog.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID')
            ->where('tawasulLog.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        $criteria->addFilterRules([
            'ip' => function ($query, $ip) {
                return $query
                    ->where('tawasulLog.ip = :ip')
                    ->bindValue('ip', $ip);
            },
            'title' => function ($query, $title) {
                return $query
                    ->where('tawasulLog.title = :title')
                    ->bindValue('title', $title);
            },
            'tawasulPersonID' => function ($query, $tawasulPersonID) {
                return $query
                    ->where('tawasulLog.tawasulPersonID = :tawasulPersonID')
                    ->bindValue('tawasulPersonID', $tawasulPersonID);
            },
            'module' => function ($query, $module) {
                return $query
                    ->where('tawasulModule.name = :module')
                    ->bindValue('module', $module);
            },
            'startDate' => function ($query, $startDate) {
                return $query
                    ->where('timestamp >= :startDate')
                    ->bindValue('startDate', $startDate);
            },
            'endDate' => function ($query, $endDate) {
                return $query
                    ->where('timestamp <= :endDate')
                    ->bindValue('endDate', $endDate);
            },
            'array' => function ($query, $array) {
                $array = unserialize($array);
                if (is_array($array)) {
                    $count = 0;
                    foreach ($array as $key => $value) {
                        $bindKey = 'key' . $count;
                        $bindValue = 'value' . $count;

                        $query
                            ->where('serialisedArray LIKE CONCAT("%", :'.$bindKey.', "%;%", :'.$bindValue.', "%")')
                            ->bindValue($bindKey, $key)
                            ->bindValue($bindValue, $value);
                        
                        $count++;
                    }
                    return $query;
                }
            }
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectLogsByModuleAndTitle($moduleName, $title)
    {
        $data = array('moduleName' => $moduleName, 'title' => $title);
        $sql = "SELECT tawasulLog.title as groupBy, tawasulLog.*, tawasulPerson.surname, tawasulPerson.preferredName, tawasulPerson.title
                FROM tawasulLog
                LEFT JOIN tawasulModule ON (tawasulModule.tawasulModuleID=tawasulLog.tawasulModuleID)
                LEFT JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulLog.tawasulPersonID)
                WHERE (tawasulModule.name=:moduleName OR (:moduleName IS NULL AND tawasulLog.tawasulModuleID IS NULL))
                AND tawasulLog.title LIKE :title
                ORDER BY tawasulLog.timestamp DESC";

        return $this->db()->select($sql, $data);
    }

    public function getLogByID($tawasulLogID)
    {
        $data = array('tawasulLogID' => $tawasulLogID);
        $sql = "SELECT tawasulLog.*, tawasulPerson.username, tawasulPerson.surname, tawasulPerson.preferredName
                FROM tawasulLog
                LEFT JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulLog.tawasulPersonID)
                WHERE tawasulLog.tawasulLogID=:tawasulLogID";

        return $this->db()->selectOne($sql, $data);
    }

    public function purgeLogs($title, $cutoffDate)
    {
        $titleList = is_array($title) ? implode(',', $title) : $title;

        $data = ['titleList' => $titleList, 'cutoffDate' => $cutoffDate];
        $sql = "DELETE FROM tawasulLog WHERE FIND_IN_SET(title, :titleList) AND timestamp <= :cutoffDate";

        return $this->db()->delete($sql, $data);
    }

    public function addLog($tawasulSchoolYearID, $module, $tawasulPersonID, $title, $array = null, $ip = null)
    {
        $serialisedArray = is_array($array) ? serialize($array) : null;
        $ip = (empty($ip) ? getIPAddress() : $ip);

        $data = [
            'tawasulSchoolYearID' => $tawasulSchoolYearID,
            'module' => $module,
            'tawasulPersonID' => $tawasulPersonID,
            'title' => $title,
            'serialisedArray' => $serialisedArray,
            'ip' => $ip
        ];

        $sql = "INSERT INTO tawasulLog SET
                tawasulSchoolYearID = :tawasulSchoolYearID,
                tawasulModuleID = (SELECT tawasulModuleID FROM tawasulModule WHERE tawasulModule.name = :module),
                tawasulPersonID = :tawasulPersonID,
                title = :title,
                serialisedArray = :serialisedArray,
                ip = :ip";

        return $this->db()->insert($sql, $data);
    }
}
