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

class Report extends DataSource
{
    public function getSchema()
    {
        return [
            'name'       => "Sample Report",
            'status'     => "Final",
            'date'       => ['date', 'Y-m-d'],
            'schoolYear' => '2019-2020',
            'currentYear' => '2019-2020',
        ];
    }

    public function getData($ids = [])
    {
        $data = ['tawasulReportID' => $ids['tawasulReportID']];
        $sql = "SELECT tawasulReport.name, tawasulReport.status, tawasulReport.accessDate as date, tawasulSchoolYear.name as schoolYear, (SELECT name FROM tawasulSchoolYear WHERE status='Current' LIMIT 1) as currentYear
                FROM tawasulReport 
                JOIN tawasulSchoolYear ON (tawasulSchoolYear.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                WHERE tawasulReport.tawasulReportID=:tawasulReportID";

        return $this->db()->selectOne($sql, $data);
    }
}
