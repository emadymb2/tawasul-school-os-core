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

namespace Tos\Module\TawasulReports\Sources;

use Tos\Module\TawasulReports\DataSource;

class ReportingCycle extends DataSource
{
    public function getSchema()
    {
        return [
            'name'           => ['numerify', 'Sample Report #'],
            'nameShort'      => ['numerify', 'Sample #'],
            'sequenceNumber' => ['randomDigit'],
            'cycleNumber'    => ['numberBetween', 1, 3],
            'cycleTotal'    => '3',
            'dateStart'      => ['date', 'Y-m-d', '+1 month'],
            'dateEnd'        => ['date', 'Y-m-d', '+6 months'],
            'notes'          => ['paragraph'],
            'cycles'         => [
                1 => 'Cycle 1',
                2 => 'Cycle 2',
                3 => 'Cycle 3',
            ],
        ];
    }

    public function getData($ids = [])
    {
        $data = ['tawasulReportID' => $ids['tawasulReportID']];
        $sql = "SELECT tawasulReportingCycle.name, tawasulReportingCycle.nameShort, tawasulReportingCycle.sequenceNumber, tawasulReportingCycle.cycleNumber, tawasulReportingCycle.cycleTotal, tawasulReportingCycle.dateStart, tawasulReportingCycle.dateEnd, tawasulReportingCycle.notes
                FROM tawasulReport 
                JOIN tawasulReportingCycle ON (tawasulReportingCycle.tawasulReportingCycleID=tawasulReport.tawasulReportingCycleID)
                WHERE tawasulReport.tawasulReportID=:tawasulReportID";

        $values = $this->db()->selectOne($sql, $data);


        $data = ['tawasulStudentEnrolmentID' => $ids['tawasulStudentEnrolmentID'], 'tawasulReportID' => $ids['tawasulReportID']];
        $sql = "SELECT allCycles.cycleNumber, allCycles.nameShort
                FROM tawasulReport 
                JOIN tawasulYearGroup ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulReport.tawasulYearGroupIDList))
                JOIN tawasulReportingCycle AS allCycles ON (allCycles.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                WHERE tawasulReport.tawasulReportID=:tawasulReportID
                AND tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID
                AND FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, allCycles.tawasulYearGroupIDList)
                AND FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, allCycles.tawasulYearGroupIDList)
                GROUP BY allCycles.tawasulReportingCycleID
                ORDER BY allCycles.sequenceNumber, allCycles.cycleNumber";

        $values['cycles'] = $this->db()->select($sql, $data)->fetchKeyPair();

        return $values;
    }
}
