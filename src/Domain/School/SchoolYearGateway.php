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

namespace TawasulOS\Domain\School;

use TawasulOS\Contracts\Services\Session;
use TawasulOS\Domain\DataSet;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;

/**
 * School Year Gateway
 *
 * @version v17
 * @since   v17
 */
class SchoolYearGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulSchoolYear';
    private static $primaryKey = 'tawasulSchoolYearID';

    /**
     * Query for school years.
     *
     * @version v17
     * @since   v17
     *
     * @param QueryCriteria $criteria
     *
     * @return DataSet
     */
    public function querySchoolYears(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulSchoolYearID', 'name', 'sequenceNumber', 'status', 'firstDay', 'lastDay'
            ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get a key value array with tawasulSchoolYearID as keys
     * and the school year name as the values.
     *
     * @version v17
     * @since   v17
     *
     * @param QueryCriteria $criteria
     *
     * @return array
     */
    public function getSchoolYearList($activeOnly = false, $desc = false)
    {
        $sql = "SELECT tawasulSchoolYearID AS value, name FROM tawasulSchoolYear ";
        if ($activeOnly) $sql .= "WHERE (status='Current' OR status='Upcoming') ";
        $sql .= "ORDER BY sequenceNumber";
        if ($desc) $sql .= " DESC";

        return $this->db()->select($sql)->fetchKeyPair();
    }

    /**
     * Get a key value array with tawasulSchoolYearID as keys
     * and the school year name as the values of the specified
     * school years.
     *
     * @version v17
     * @since   v17
     *
     * @param array $schoolYearList  An array of school year IDs
     *
     * @return array
     */
    public function getSchoolYearsFromList($schoolYearList = [])
    {
        $data = ['tawasulSchoolYearIDList' => is_array($schoolYearList) ? implode(',', $schoolYearList) : $schoolYearList];
        $sql = "SELECT tawasulSchoolYearID as value, name FROM tawasulSchoolYear WHERE FIND_IN_SET(tawasulSchoolYearID, :tawasulSchoolYearIDList) ORDER BY sequenceNumber";

        return $this->db()->select($sql, $data)->fetchKeyPair();
    }

    /**
     * Get a single school year by its ID.
     *
     * @version v17
     * @since   v17
     *
     * @param int $tawasulSchoolYearID
     *
     * @return array|false  The information of the spcified school year, or false if not found.
     */
    public function getSchoolYearByID($tawasulSchoolYearID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT * FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get the school year next to the specified school year.
     *
     * @version v17
     * @since   v17
     *
     * @param int $tawasulSchoolYearID  The ID of the specified school year.
     *
     * @return array|false  The information of the next school year, or false if not found.
     */
    public function getNextSchoolYearByID($tawasulSchoolYearID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT * FROM tawasulSchoolYear WHERE sequenceNumber=(SELECT MIN(sequenceNumber) FROM tawasulSchoolYear WHERE sequenceNumber > (SELECT sequenceNumber FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID))";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get the school year previous of the specified school year.
     *
     * @version v17
     * @since   v17
     *
     * @param int $tawasulSchoolYearID  The ID of the specified school year.
     *
     * @return array|false  The information of the previous school year, or false if not found.
     */
    public function getPreviousSchoolYearByID($tawasulSchoolYearID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT * FROM tawasulSchoolYear WHERE sequenceNumber=(SELECT MAX(sequenceNumber) FROM tawasulSchoolYear WHERE sequenceNumber < (SELECT sequenceNumber FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID))";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get the current school year information.
     *
     * @version v25
     * @since   v25

     * @return array
     */
    public function getCurrentSchoolYear()
    {
        return $this->db()->selectOne("SELECT * FROM tawasulSchoolYear WHERE status='Current'");
    }
}
