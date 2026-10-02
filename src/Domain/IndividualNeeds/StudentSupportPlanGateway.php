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

namespace TawasulOS\Domain\IndividualNeeds;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\ScrubByPerson;

/**
 * Student Support Plan Gateway
 *
 * @version v31
 * @since   v31
 */
class StudentSupportPlanGateway extends QueryableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulStudentSupportPlan';
    private static $primaryKey = 'tawasulStudentSupportPlanID';
    private static $searchableColumns = ['name', 'description'];

    private static $scrubbableKey = 'tawasulPersonID';
    private static $scrubbableColumns = ['name' => '', 'description' => null, 'filePath' => ''];

    /**
     * @param QueryCriteria $criteria
     * @param string        $tawasulPersonID
     * @return \TawasulOS\Domain\DataSet
     */
    public function queryPlansByStudent(QueryCriteria $criteria, string $tawasulPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulStudentSupportPlan.tawasulStudentSupportPlanID',
                'tawasulStudentSupportPlan.tawasulPersonID',
                'tawasulStudentSupportPlan.tawasulSchoolYearID',
                'tawasulStudentSupportPlan.active',
                'tawasulStudentSupportPlan.type',
                'tawasulStudentSupportPlan.filePath',
                'tawasulStudentSupportPlan.name',
                'tawasulStudentSupportPlan.description',
                'tawasulStudentSupportPlan.viewableStaff',
                'tawasulStudentSupportPlan.viewableParents',
                'tawasulStudentSupportPlan.timestampCreated',
                'tawasulStudentSupportPlan.timestampModified',
                'tawasulStudentSupportPlan.tawasulPersonIDCreated',
                'tawasulStudentSupportPlan.tawasulPersonIDModified',
                'tawasulSchoolYear.name AS schoolYear',
                'tawasulSchoolYear.sequenceNumber',
            ])
            ->innerJoin('tawasulSchoolYear', 'tawasulSchoolYear.tawasulSchoolYearID=tawasulStudentSupportPlan.tawasulSchoolYearID')
            ->where('tawasulStudentSupportPlan.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->orderBy(['tawasulSchoolYear.sequenceNumber DESC', 'tawasulStudentSupportPlan.timestampCreated ASC']);

        $criteria->addFilterRules([
            'viewableStaff' => function ($query, $value) {
                return $query->where("tawasulStudentSupportPlan.viewableStaff='Y'");
            },
            'viewableParents' => function ($query, $value) {
                return $query->where("tawasulStudentSupportPlan.viewableParents='Y'");
            },
            'active' => function ($query, $value) {
                return $query->where("tawasulStudentSupportPlan.active='Y'");
            },
        ]);

        return $this->runQuery($query, $criteria);
    }
}
