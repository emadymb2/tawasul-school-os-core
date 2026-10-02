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

namespace TawasulOS\Domain\Planner;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * PlannerParentWeeklyEmailSummary Gateway
 *
 * @version v21
 * @since   v21
 */
class PlannerParentWeeklyEmailSummaryGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulPlannerParentWeeklyEmailSummary';
    private static $primaryKey = 'tawasulPlannerParentWeeklyEmailSummaryID';
    private static $searchableColumns = [];


    public function getAnySummaryDetailsByKey($key)
    {
        $data = ['key' => $key];
        $sql = 'SELECT * FROM tawasulPlannerParentWeeklyEmailSummary WHERE tawasulPlannerParentWeeklyEmailSummary.key=:key';

        return $this->db()->selectOne($sql, $data);
    }

    public function getWeeklySummaryDetailsByParent($tawasulSchoolYearID, $tawasulPersonIDParent, $tawasulPersonIDStudent)
    {
        $data = [
            'tawasulSchoolYearID' => $tawasulSchoolYearID,
            'tawasulPersonIDStudent' => $tawasulPersonIDStudent,
            'tawasulPersonIDParent' => $tawasulPersonIDParent,
            'weekOfYear' => date('W')
        ];
        $sql = 'SELECT * FROM tawasulPlannerParentWeeklyEmailSummary WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonIDStudent=:tawasulPersonIDStudent AND tawasulPersonIDParent=:tawasulPersonIDParent AND weekOfYear=:weekOfYear';

        return $this->db()->selectOne($sql, $data);
    }
}
