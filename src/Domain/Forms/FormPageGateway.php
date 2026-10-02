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

class FormPageGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulFormPage';
    private static $primaryKey = 'tawasulFormPageID';
    private static $searchableColumns = ['tawasulFormPage.name'];
    
    /**
     * @return DataSet
     */
    public function selectPagesByForm($tawasulFormID)
    {
        $query = $this
            ->newSelect()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulFormPage.sequenceNumber AS groupBy', 'tawasulFormPage.tawasulFormPageID', 'tawasulFormPage.name', 'tawasulFormPage.sequenceNumber', 'tawasulFormPage.introduction', 'tawasulFormPage.postScript', '(SELECT COUNT(*) FROM tawasulFormField WHERE tawasulFormField.tawasulFormPageID=tawasulFormPage.tawasulFormPageID) as count'])
            ->where('tawasulFormPage.tawasulFormID=:tawasulFormID')
            ->bindValue('tawasulFormID', $tawasulFormID)
            ->orderBy(['tawasulFormPage.sequenceNumber']);

        return $this->runSelect($query);
    }

    public function getPageIDByNumber($tawasulFormID, $page)
    {
        return $this->selectBy(['tawasulFormID' => $tawasulFormID, 'sequenceNumber' => $page], ['tawasulFormPageID'])->fetchColumn(0);
    }

    public function getNextPageByNumber($tawasulFormID, $page)
    {
        $data = ['tawasulFormID' => $tawasulFormID, 'page' => $page];
        $sql = "SELECT * FROM tawasulFormPage WHERE sequenceNumber=(SELECT MIN(sequenceNumber) FROM tawasulFormPage WHERE sequenceNumber > :page AND tawasulFormID=:tawasulFormID) AND tawasulFormID=:tawasulFormID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getFinalPageNumber($tawasulFormID)
    {
        $data = ['tawasulFormID' => $tawasulFormID];
        $sql = "SELECT MAX(sequenceNumber) FROM tawasulFormPage WHERE tawasulFormID=:tawasulFormID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getNextSequenceNumberByForm($tawasulFormID)
    {
        $select = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['MAX(sequenceNumber) + 1 as sequenceNumber'])
            ->where('tawasulFormID=:tawasulFormID')
            ->bindValue('tawasulFormID', $tawasulFormID);

        return $this->runSelect($select)->fetchColumn(0);
    }
}
