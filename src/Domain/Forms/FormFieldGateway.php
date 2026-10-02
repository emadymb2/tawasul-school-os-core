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

class FormFieldGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulFormField';
    private static $primaryKey = 'tawasulFormFieldID';
    private static $searchableColumns = ['tawasulFormField.fieldName'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryFieldsByPage(QueryCriteria $criteria, $tawasulFormPageID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulFormField.tawasulFormFieldID', 'tawasulFormField.*'])
            ->where('tawasulFormField.tawasulFormPageID=:tawasulFormPageID')
            ->bindValue('tawasulFormPageID', $tawasulFormPageID);

        return $this->runQuery($query, $criteria);
    }

    public function selectFieldOrderByPage($tawasulFormPageID)
    {
        $select = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['sequenceNumber', 'fieldType', 'label'])
            ->where('tawasulFormPageID=:tawasulFormPageID')
            ->bindValue('tawasulFormPageID', $tawasulFormPageID)
            ->orderBy(['sequenceNumber']);

        return $this->runSelect($select);
    }

    public function getNextSequenceNumberByPage($tawasulFormPageID)
    {
        $select = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['MAX(sequenceNumber) + 1 as sequenceNumber'])
            ->where('tawasulFormPageID=:tawasulFormPageID')
            ->bindValue('tawasulFormPageID', $tawasulFormPageID);

        return $this->runSelect($select)->fetchColumn(0);
    }

    public function bumpSequenceNumbersByAmount($tawasulFormPageID, $sequenceNumber, $amount) {
        $data = ['tawasulFormPageID' => $tawasulFormPageID, 'sequenceNumber' => $sequenceNumber, 'amount' => $amount];
        $sql = "UPDATE tawasulFormField 
                SET sequenceNumber=sequenceNumber+:amount 
                WHERE sequenceNumber>:sequenceNumber 
                AND tawasulFormPageID=:tawasulFormPageID";

        return $this->db()->update($sql, $data);
    }

    public function getFieldInForm($tawasulFormID, $fieldName)
    {
        $data = ['tawasulFormID' => $tawasulFormID, 'fieldName' => $fieldName];
        $sql = "SELECT tawasulFormFieldID 
                FROM tawasulFormField 
                JOIN tawasulFormPage ON (tawasulFormPage.tawasulFormPageID=tawasulFormField.tawasulFormPageID)
                WHERE tawasulFormPage.tawasulFormID=:tawasulFormID 
                AND tawasulFormField.fieldName=:fieldName";

        return $this->db()->selectOne($sql, $data);
    }

}
