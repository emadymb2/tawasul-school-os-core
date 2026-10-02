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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace TawasulOS\Domain\Finance;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\DataSet;

class BillingScheduleGateway extends QueryableGateway
{
    use TableAware;
    private static $primaryKey = 'tawasulFinanceBillingScheduleID';
    private static $tableName = 'tawasulFinanceBillingSchedule';
    private static $searchableColumns = [];

    public function queryBillingSchedules(QueryCriteria $criteria, $tawasulSchoolYearID = null, $search = null)
    {
        $query = $this
        ->newQuery()
        ->cols([
            '*'
          ])
        ->from('tawasulFinanceBillingSchedule');

        if (!empty($tawasulSchoolYearID)) {
            $query->where('tawasulFinanceBillingSchedule.tawasulSchoolYearID=:tawasulSchoolYearID')
                ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);
        }

        if (!empty($search)) {
            $query->where("tawasulFinanceBillingSchedule.name LIKE concat('%',:name,'%')")
                ->bindValue('name', $search);
        }

        return $this->runQuery($query, $criteria);
    }
}
