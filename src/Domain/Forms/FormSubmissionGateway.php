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

namespace TawasulOS\Domain\Forms;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

class FormSubmissionGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulFormSubmission';
    private static $primaryKey = 'tawasulFormSubmissionID';
    private static $searchableColumns = ['tawasulFormSubmission.name'];

    public function queryFormsBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $type = null)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->cols([
                'tawasulFormSubmission.tawasulFormSubmissionID',
                'tawasulFormSubmission.tawasulFormID',
                'tawasulFormSubmission.identifier',
                'tawasulFormSubmission.status',
                'tawasulFormSubmission.timestampCreated',
                'tawasulForm.tawasulFormID',
                'tawasulForm.name as formName',
                'tawasulAdmissionsAccount.tawasulAdmissionsAccountID',
                'tawasulAdmissionsAccount.email',
             ])
            ->from($this->getTableName())
            ->innerJoin('tawasulForm', 'tawasulFormSubmission.tawasulFormID=tawasulForm.tawasulFormID')
            ->innerJoin('tawasulSchoolYear', 'tawasulFormSubmission.timestampCreated BETWEEN tawasulSchoolYear.firstDay AND tawasulSchoolYear.lastDay')
            ->leftJoin('tawasulAdmissionsAccount', "tawasulFormSubmission.foreignTable='tawasulAdmissionsAccount' AND tawasulFormSubmission.foreignTableID=tawasulAdmissionsAccount.tawasulAdmissionsAccountID")
            ->where('tawasulSchoolYear.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulForm.type<>'Application'");
        
            if (!empty($type)) {
                $query->where('tawasulForm.type=:type')
                      ->bindValue('type', $type);
            }

        return $this->runQuery($query, $criteria);
    }

    public function querySubmissionsByForm(QueryCriteria $criteria, $tawasulFormID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulFormSubmission.tawasulFormSubmissionID', 'tawasulFormSubmission.tawasulFormID', 'tawasulFormSubmission.identifier'])
            ->where('tawasulFormSubmission.tawasulFormID=:tawasulFormID')
            ->bindValue('tawasulFormID', $tawasulFormID);

        return $this->runQuery($query, $criteria);
    }

    public function queryFormSubmissionsByContext(QueryCriteria $criteria, $foreignTable, $foreignTableID) 
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->cols([
                'tawasulFormSubmission.tawasulFormSubmissionID',
                'tawasulFormSubmission.tawasulFormID',
                'tawasulFormSubmission.identifier',
                'tawasulFormSubmission.status',
                'tawasulFormSubmission.timestampCreated',
                'tawasulForm.tawasulFormID',
                'tawasulForm.name as formName',
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulForm', 'tawasulFormSubmission.tawasulFormID=tawasulForm.tawasulFormID')
            ->where('tawasulFormSubmission.foreignTable=:foreignTable')
            ->bindValue('foreignTable', $foreignTable)
            ->where('tawasulFormSubmission.foreignTableID=:foreignTableID')
            ->bindValue('foreignTableID', $foreignTableID);

        return $this->runQuery($query, $criteria);
    }

    public function getFormSubmissionByIdentifier($tawasulFormID, $identifier, $fields = null)
    {
        return $this->selectBy(['tawasulFormID' => $tawasulFormID, 'identifier' => $identifier], $fields)->fetch();
    }

    public function getNewUniqueIdentifier(string $tawasulFormID)
    {
        $data = ['tawasulFormID' => $tawasulFormID];

        do {
            $data['identifier'] = bin2hex(random_bytes(20));
        } while (!$this->unique($data, ['tawasulFormID', 'identifier']));

        return $data['identifier'];
    }
}
