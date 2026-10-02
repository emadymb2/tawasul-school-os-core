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

namespace TawasulOS\Domain\Library;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Tables\DataTable;

/**
 * LibraryReportGateway
 *
 * @version v20
 * @since   v20
 */
class LibraryReportGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulLibraryItem';
    private static $primaryKey = 'tawasulLibraryItemID';

    public function queryStudentReportData(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulLibraryItem')
            ->cols([
                'tawasulLibraryItem.tawasulLibraryItemID',
                'tawasulLibraryItem.name',
                'tawasulLibraryItem.producer',
                'tawasulLibraryItem.id',
                'tawasulLibraryItem.imageType',
                'tawasulLibraryItem.imageLocation',
                'tawasulLibraryItem.fields',
                'tawasulLibraryType.fields as typeFields',
                'tawasulLibraryItem.locationDetail',
                'tawasulLibraryItem.ownershipType',
                'tawasulLibraryItem.borrowable',
                'tawasulSpace.name as spaceName',
                'tawasulLibraryItemEvent.tawasulLibraryItemEventID',
                'tawasulLibraryItemEvent.timestampOut',
                'tawasulLibraryItemEvent.returnExpected',
                'tawasulLibraryItemEvent.status',
                'tawasulLibraryItemEvent.timestampReturn',
                'tawasulLibraryItemEvent.tawasulPersonIDStatusResponsible',
                "IF(tawasulLibraryItemEvent.returnExpected < CURRENT_DATE,'Y','N') as pastDue"
            ])
            ->innerJoin('tawasulLibraryType', 'tawasulLibraryType.tawasulLibraryTypeID = tawasulLibraryItem.tawasulLibraryTypeID')
            ->leftJoin('tawasulLibraryItemEvent', 'tawasulLibraryItemEvent.tawasulLibraryItemID = tawasulLibraryItem.tawasulLibraryItemID')
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID = tawasulLibraryItem.tawasulSpaceID');

        $criteria->addFilterRules([
            'tawasulPersonID' => function ($query, $tawasulPersonID) {
                return $query
                    ->where('tawasulLibraryItemEvent.tawasulPersonIDStatusResponsible = :tawasulPersonID')
                    ->bindValue('tawasulPersonID', $tawasulPersonID);
            },
            'ownershipType' => function ($query, $ownershipType) {
                return $query
                    ->where('tawasulLibraryItem.ownershipType = :ownershipType')
                    ->bindValue('ownershipType', $ownershipType);
            },
            'tawasulPersonIDOwnership' => function ($query, $tawasulPersonID) {
                return $query
                    ->where('tawasulLibraryItem.tawasulPersonIDOwnership = :tawasulPersonID')
                    ->bindValue('tawasulPersonID', $tawasulPersonID);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('tawasulLibraryItemEvent.status = :status')
                    ->bindValue('status', $status);
            },
            'type' => function ($query, $type) {
                return $query
                    ->where('tawasulLibraryType.name = :type')
                    ->bindValue('type', $type);
            },
            'notType' => function ($query, $type) {
                return $query
                    ->where('tawasulLibraryType.name <> :type')
                    ->bindValue('type', $type);
            }
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryCatalogSummary(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulLibraryItem')
            ->cols(['tawasulLibraryItem.*', 'tawasulLibraryType.name as type', 'tawasulSpace.name as space', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName'])
            ->leftJoin('tawasulLibraryType', 'tawasulLibraryType.tawasulLibraryTypeID=tawasulLibraryItem.tawasulLibraryTypeID')
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID=tawasulLibraryItem.tawasulSpaceID')
            ->leftJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulLibraryItem.tawasulPersonIDOwnership');

        $criteria->addFilterRules([
            'id' => function ($query, $tawasulLibraryTypeID) {
                return $query
                    ->where('tawasulLibraryItem.tawasulLibraryTypeID = :tawasulLibraryTypeID')
                    ->bindValue('tawasulLibraryTypeID', $tawasulLibraryTypeID);
            },
            'ownershipType' => function ($query, $ownershipType) {
                return $query
                    ->where('tawasulLibraryItem.ownershipType = :ownershipType')
                    ->bindValue('ownershipType', $ownershipType);
            },
            'space' => function ($query, $tawasulSpaceID) {
                return $query
                    ->where('tawasulLibraryItem.tawasulSpaceID = :tawasulSpaceID')
                    ->bindValue('tawasulSpaceID', $tawasulSpaceID);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('tawasulLibraryItem.status = :status')
                    ->bindValue('status', $status);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }
    
    public function queryOverdueItems($criteria, $tawasulSchoolYearID, $ignoreStatus = null)
    {
        $query = $this
            ->newQuery()
            ->cols(['tawasulLibraryItem.*', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.email', 'tawasulFormGroup.nameShort AS formGroup'])
            ->from('tawasulLibraryItem')
            ->innerJoin('tawasulPerson', 'tawasulLibraryItem.tawasulPersonIDStatusResponsible=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->where("tawasulLibraryItem.status='On Loan'")
            ->where("borrowable='Y'")
            ->where('returnExpected<:today')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->bindValue('today', date('Y-m-d'));

        if ($ignoreStatus != 'on') {
            $query->where("tawasulPerson.status='Full'");
        }

        $criteria->addFilterRules([
            'type' => function ($query, $tawasulLibraryTypeID) {
                return $query
                    ->where('tawasulLibraryItem.tawasulLibraryTypeID = :tawasulLibraryTypeID')
                    ->bindValue('tawasulLibraryTypeID', $tawasulLibraryTypeID);
            },
            'department' => function ($query, $tawasulDepartmentID) {
                return $query
                    ->where('tawasulLibraryItem.tawasulDepartmentID = :tawasulDepartmentID')
                    ->bindValue('tawasulDepartmentID', $tawasulDepartmentID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }
}
