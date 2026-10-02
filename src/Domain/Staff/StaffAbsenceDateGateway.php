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

namespace TawasulOS\Domain\Staff;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\Traits\TableAware;

/**
 * Staff Absence Date Gateway
 *
 * @version v18
 * @since   v18
 */
class StaffAbsenceDateGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulStaffAbsenceDate';
    private static $primaryKey = 'tawasulStaffAbsenceDateID';

    private static $searchableColumns = [];

    public function selectDatesByAbsence($tawasulStaffAbsenceID)
    {
        $tawasulStaffAbsenceIDList = is_array($tawasulStaffAbsenceID)? $tawasulStaffAbsenceID : [$tawasulStaffAbsenceID];
        $data = ['tawasulStaffAbsenceIDList' => implode(',', $tawasulStaffAbsenceIDList) ];
        $sql = "SELECT tawasulStaffAbsenceDate.tawasulStaffAbsenceID as groupBy, 
                tawasulStaffAbsenceDate.*, 
                tawasulStaffAbsenceDate.allDay, 
                tawasulStaffAbsenceDate.timeStart,
                tawasulStaffAbsenceDate.timeEnd, '' as coverage, '' as titleCoverage, '' as preferredNameCoverage, '' as surnameCoverage, '' as tawasulPersonIDCoverage, '' as tawasulStaffCoverageID, '' as notes, '' as tawasulTTDayRowClassID, tawasulStaffAbsence.status
            FROM tawasulStaffAbsenceDate
            LEFT JOIN tawasulStaffAbsence ON (tawasulStaffAbsence.tawasulStaffAbsenceID=tawasulStaffAbsenceDate.tawasulStaffAbsenceID)
            WHERE FIND_IN_SET(tawasulStaffAbsenceDate.tawasulStaffAbsenceID, :tawasulStaffAbsenceIDList)
            ORDER BY tawasulStaffAbsenceDate.date, tawasulStaffAbsenceDate.timeStart";

        return $this->db()->select($sql, $data);
    }

    public function selectDatesByAbsenceWithCoverage($tawasulStaffAbsenceID, $coverageOnly = false)
    {
        $tawasulStaffAbsenceIDList = is_array($tawasulStaffAbsenceID)? $tawasulStaffAbsenceID : [$tawasulStaffAbsenceID];
        $data = ['tawasulStaffAbsenceIDList' => implode(',', $tawasulStaffAbsenceIDList) ];
        $sql = "SELECT tawasulStaffAbsenceDate.tawasulStaffAbsenceID as groupBy, tawasulStaffAbsenceDate.*, 
        (CASE WHEN tawasulStaffCoverageDateID IS NOT NULL THEN tawasulStaffCoverageDate.allDay ELSE tawasulStaffAbsenceDate.allDay END) as allDay, 
        (CASE WHEN tawasulStaffCoverageDateID IS NOT NULL THEN tawasulStaffCoverageDate.timeStart ELSE tawasulStaffAbsenceDate.timeStart END) as timeStart,
        (CASE WHEN tawasulStaffCoverageDateID IS NOT NULL THEN tawasulStaffCoverageDate.timeEnd ELSE tawasulStaffAbsenceDate.timeEnd END) as timeEnd, tawasulStaffCoverage.requestType, tawasulStaffAbsence.status as absenceStatus,
        tawasulStaffCoverage.status as coverage, coverage.title as titleCoverage, coverage.preferredName as preferredNameCoverage, coverage.surname as surnameCoverage, coverage.tawasulPersonID as tawasulPersonIDCoverage, tawasulStaffCoverage.tawasulStaffCoverageID, tawasulStaffCoverageDate.reason as notes, tawasulStaffCoverageDate.tawasulStaffCoverageDateID, tawasulStaffAbsence.status, tawasulStaffCoverageDate.foreignTable, tawasulStaffCoverageDate.foreignTableID
                FROM tawasulStaffAbsenceDate
                LEFT JOIN tawasulStaffAbsence ON (tawasulStaffAbsence.tawasulStaffAbsenceID=tawasulStaffAbsenceDate.tawasulStaffAbsenceID)
                LEFT JOIN tawasulStaffCoverageDate ON (tawasulStaffCoverageDate.tawasulStaffAbsenceDateID=tawasulStaffAbsenceDate.tawasulStaffAbsenceDateID)
                LEFT JOIN tawasulStaffCoverage ON (tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID AND (tawasulStaffAbsence.status = 'Cancelled' OR (tawasulStaffCoverage.status <> 'Cancelled' AND tawasulStaffCoverage.status <> 'Declined') ))
                LEFT JOIN tawasulPerson AS coverage ON (tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID)
                WHERE FIND_IN_SET(tawasulStaffAbsenceDate.tawasulStaffAbsenceID, :tawasulStaffAbsenceIDList) ";
               
        if ($coverageOnly) {
            $sql .= " AND tawasulStaffCoverage.tawasulStaffCoverageID IS NOT NULL";
        }
               
        $sql .= " ORDER BY tawasulStaffAbsenceDate.date, tawasulStaffAbsenceDate.timeStart, tawasulStaffCoverageDate.timeStart";

        return $this->db()->select($sql, $data);
    }

    public function selectApprovedAbsenceDatesByPerson($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulStaffAbsenceDate.date as groupBy, tawasulStaffAbsence.*, tawasulStaffAbsenceDate.*, tawasulStaffAbsenceType.name as type, tawasulStaffAbsenceType.sequenceNumber
                FROM tawasulStaffAbsence 
                JOIN tawasulStaffAbsenceDate ON (tawasulStaffAbsenceDate.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID) 
                JOIN tawasulStaffAbsenceType ON (tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID=tawasulStaffAbsence.tawasulStaffAbsenceTypeID)
                WHERE tawasulStaffAbsence.tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulStaffAbsence.tawasulPersonID=:tawasulPersonID
                AND tawasulStaffAbsence.status='Approved'
                ORDER BY tawasulStaffAbsenceDate.date";

        return $this->db()->select($sql, $data);
    }

    public function getByAbsenceAndDate($tawasulStaffAbsenceID, $date)
    {
        $data = ['tawasulStaffAbsenceID' => $tawasulStaffAbsenceID, 'date' => $date ];
        $sql = "SELECT tawasulStaffAbsenceDate.tawasulStaffAbsenceID as groupBy, tawasulStaffAbsenceDate.*, tawasulStaffCoverage.status as coverage, coverage.title as titleCoverage, coverage.preferredName as preferredNameCoverage, coverage.surname as surnameCoverage, coverage.tawasulPersonID as tawasulPersonIDCoverage, tawasulStaffCoverage.tawasulStaffCoverageID
                FROM tawasulStaffAbsenceDate
                LEFT JOIN tawasulStaffCoverageDate ON (tawasulStaffCoverageDate.tawasulStaffAbsenceDateID=tawasulStaffAbsenceDate.tawasulStaffAbsenceDateID)
                LEFT JOIN tawasulStaffCoverage ON (tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID)
                LEFT JOIN tawasulPerson AS coverage ON (tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID)
                WHERE tawasulStaffAbsenceDate.tawasulStaffAbsenceID=:tawasulStaffAbsenceID
                AND tawasulStaffAbsenceDate.date=:date";

        return $this->db()->selectOne($sql, $data);
    }
}
