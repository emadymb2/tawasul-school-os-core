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

class FormGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulForm';
    private static $primaryKey = 'tawasulFormID';
    private static $searchableColumns = ['tawasulForm.name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryForms(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->cols(['tawasulForm.tawasulFormID', 'tawasulForm.name', 'tawasulForm.description', 'tawasulForm.active', 'tawasulForm.public', 'tawasulForm.type', 'COUNT(tawasulFormPageID) as pages'])
            ->from($this->getTableName())
            ->leftJoin('tawasulFormPage', 'tawasulFormPage.tawasulFormID=tawasulForm.tawasulFormID')
            ->groupBy(['tawasulForm.tawasulFormID']);

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('tawasulForm.active = :active')
                    ->bindValue('active', $active);
            },
            'public' => function ($query, $public) {
                return $query
                    ->where('tawasulForm.public = :public')
                    ->bindValue('public', $public);
            },
            'type' => function ($query, $type) {
                return $query
                    ->where('tawasulForm.type = :type')
                    ->bindValue('type', $type);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectFieldsByForm($tawasulFormID)
    {
        $select = $this
            ->newSelect()
            ->cols(["(CASE WHEN tawasulFormField.fieldName LIKE '%heading%' THEN CONCAT(tawasulFormField.fieldName, tawasulFormField.tawasulFormFieldID) ELSE tawasulFormField.fieldName END) as groupBy", 'tawasulFormField.*', 'tawasulFormPage.sequenceNumber as pageNumber'])
            ->from('tawasulFormField')
            ->innerJoin('tawasulFormPage', 'tawasulFormPage.tawasulFormPageID=tawasulFormField.tawasulFormPageID')
            ->where('tawasulFormPage.tawasulFormID=:tawasulFormID')
            ->bindValue('tawasulFormID', $tawasulFormID)
            ->orderBy(['tawasulFormPage.sequenceNumber', 'tawasulFormField.sequenceNumber']);

        return $this->runSelect($select);
    }

    public function getSubmissionCountByForm($tawasulFormID) 
    {
        $query = $this
            ->newSelect()
            ->cols([
                'COUNT(tawasulFormSubmission.tawasulFormSubmissionID) + COUNT(tawasulAdmissionsApplication.tawasulAdmissionsApplicationID) as count',
            ])
            ->from('tawasulForm')
            ->leftJoin('tawasulFormSubmission', 'tawasulFormSubmission.tawasulFormID=tawasulForm.tawasulFormID')
            ->leftJoin('tawasulAdmissionsApplication', 'tawasulAdmissionsApplication.tawasulFormID=tawasulForm.tawasulFormID')
            ->where('tawasulForm.tawasulFormID=:tawasulFormID')
            ->bindValue('tawasulFormID', $tawasulFormID);

        return $this->runSelect($query)->fetchColumn(0);
    }

    public function getNewUniqueIdentifier(string $tawasulFormID)
    {
        $data = ['tawasulFormID' => $tawasulFormID];
        $sql = "SELECT tawasulFormSubmissionID FROM tawasulFormSubmission WHERE tawasulFormID=:tawasulFormID AND identifier=:identifier";

        do {
            $data['identifier'] =  bin2hex(random_bytes(20));
            $result = $this->db()->selectOne($sql, $data);
        } while (!empty($result));

        return $data['identifier'];
    }
}
