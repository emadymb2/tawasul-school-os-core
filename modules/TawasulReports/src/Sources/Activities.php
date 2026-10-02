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

class Activities extends DataSource
{
    public function getSchema()
    {
        return [
            0 => [
                'name'         => ['numerify', 'Activity ##'],
                'type'         => ['randomElement', ['Creativity', 'Action', 'Service']],
                'programStart' => ['date', 'Y-m-d', '+1 month'],
                'programEnd'   => ['date', 'Y-m-d', '+6 months'],
            ],
        ];
    }

    public function getData($ids = [])
    {
        $data = array('tawasulStudentEnrolmentID' => $ids['tawasulStudentEnrolmentID'], 'tawasulReportID' => $ids['tawasulReportID']);
        $sql = "SELECT tawasulActivity.name, tawasulActivity.type, programStart, programEnd
                FROM tawasulReport
                JOIN tawasulActivity ON (tawasulActivity.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                JOIN tawasulActivityStudent ON (tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID)
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulActivityStudent.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                WHERE tawasulReport.tawasulReportID=:tawasulReportID
                AND tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID 
                AND tawasulActivity.active='Y'
                AND tawasulActivityStudent.status='Accepted' 
                ORDER BY tawasulActivity.name";

        $result = $this->db()->executeQuery($data, $sql);

        return ($result->rowCount() > 0)? $result->fetchAll() : array();
    }
}
