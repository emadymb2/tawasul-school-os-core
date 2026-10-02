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

class FormUploadGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulFormUpload';
    private static $primaryKey = 'tawasulFormUploadID';
    private static $searchableColumns = ['tawasulFormUpload.name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAllDocumentsByContext(QueryCriteria $criteria, $tawasulFormID, $foreignTable, $foreignTableID)
    {
        $status = $this->db()->selectOne("SELECT status FROM {$foreignTable} WHERE {$foreignTable}ID=:foreignTableID", ['foreignTableID' => $foreignTableID]);

        $select = $this
            ->newSelect()
            ->cols(['tawasulFormField.fieldGroup', "GROUP_CONCAT(tawasulFormField.options SEPARATOR ',') as options"])
            ->from('tawasulFormField')
            ->innerJoin('tawasulFormPage', 'tawasulFormPage.tawasulFormPageID=tawasulFormField.tawasulFormPageID')
            ->where('tawasulFormField.fieldGroup="RequiredDocuments"')
            ->where('tawasulFormPage.tawasulFormID=:tawasulFormID', ['tawasulFormID' => $tawasulFormID])
            ->groupBy(['tawasulFormField.fieldGroup']);

        $documents = $this->runSelect($select)->fetchKeyPair();

        foreach ($documents as $fieldGroup => $options) {
            $options = array_map('trim', explode(',', $options));

            if ($fieldGroup == 'RequiredDocuments' && !empty($options)) {
                foreach ($options as $i => $option) {
                    $query = empty($query)? $this->newQuery() : $this->unionAllWithCriteria($query, $criteria);
                    
                    $query->cols(["'Required Documents' AS type", "'Student' as target", ":option{$i} as name", 'tawasulFormUpload.tawasulFormUploadID AS id', 'tawasulFormUpload.path', 'tawasulFormField.required', 'tawasulFormUpload.timestamp'])
                        ->from('tawasulFormField')
                        ->innerJoin('tawasulFormPage', 'tawasulFormPage.tawasulFormPageID=tawasulFormField.tawasulFormPageID')
                        ->leftJoin('tawasulFormUpload', "tawasulFormUpload.tawasulFormFieldID=tawasulFormField.tawasulFormFieldID AND tawasulFormUpload.foreignTable=:foreignTable AND tawasulFormUpload.foreignTableID=:foreignTableID AND (tawasulFormUpload.name=:option{$i} OR tawasulFormUpload.name=SUBSTRING(:option{$i},1,90))")
                        ->where('tawasulFormField.fieldGroup="RequiredDocuments"')
                        ->where('tawasulFormPage.tawasulFormID=:tawasulFormID', ['tawasulFormID' => $tawasulFormID])
                        ->bindValue('foreignTable', $foreignTable)
                        ->bindValue('foreignTableID', $foreignTableID)
                        ->bindValue("option{$i}", $option);
                }
            }
        }

        // Check for orphaned documents if a document type is deleted
        $query = empty($query)? $this->newQuery() : $this->unionAllWithCriteria($query, $criteria);
        $query->distinct()
            ->from('tawasulFormUpload')
            ->cols(["'Unknown' AS type", "'Student' as target", "tawasulFormUpload.name", 'tawasulFormUpload.tawasulFormUploadID AS id', 'tawasulFormUpload.path', '"N" as required', 'tawasulFormUpload.timestamp'])
            ->leftJoin('tawasulFormField', "tawasulFormUpload.tawasulFormFieldID=tawasulFormField.tawasulFormFieldID AND  tawasulFormUpload.foreignTable=:foreignTable AND tawasulFormUpload.foreignTableID=:foreignTableID")
            ->where('(tawasulFormField.tawasulFormFieldID IS NULL OR tawasulFormField.options NOT LIKE CONCAT("%",tawasulFormUpload.name,"%"))')
            ->where('tawasulFormUpload.foreignTable=:foreignTable')
            ->where('tawasulFormUpload.foreignTableID=:foreignTableID')
            ->bindValue('foreignTable', $foreignTable)
            ->bindValue('foreignTableID', $foreignTableID);

        if ($status == 'Accepted') {

            $query = empty($query)? $this->newQuery() : $this->unionAllWithCriteria($query, $criteria);
            $query->distinct()
                ->from('tawasulPersonalDocument')
                ->cols(["'Personal Documents' AS type", "'Student' as target", 'tawasulPersonalDocumentType.name', 'tawasulPersonalDocument.tawasulPersonalDocumentID AS id',  'tawasulPersonalDocument.filePath AS path', '"N" as required', 'tawasulPersonalDocument.timestamp'])
                ->innerJoin('tawasulPersonalDocumentType', 'tawasulPersonalDocumentType.tawasulPersonalDocumentTypeID=tawasulPersonalDocument.tawasulPersonalDocumentTypeID')
                ->innerJoin('tawasulAdmissionsApplication', "JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.result, '$.tawasulPersonIDStudent'))=tawasulPersonalDocument.foreignTableID")
                ->where("tawasulPersonalDocument.foreignTable='tawasulPerson' AND tawasulPersonalDocumentType.activePersonStudent=1")
                ->where('tawasulAdmissionsApplication.tawasulAdmissionsApplicationID=:foreignTableID', ['foreignTableID' => $foreignTableID])
                ->where(":foreignTable='tawasulAdmissionsApplication'")
                ->where("tawasulPersonalDocumentType.fields LIKE '%filePath%'");

            $this->unionAllWithCriteria($query, $criteria)
                ->distinct()
                ->from('tawasulPersonalDocument')
                ->cols(["'Personal Documents' AS type", "'Parent' as target", 'tawasulPersonalDocumentType.name', 'tawasulPersonalDocument.tawasulPersonalDocumentID AS id',  'tawasulPersonalDocument.filePath AS path', '"N" as required', 'tawasulPersonalDocument.timestamp'])
                ->innerJoin('tawasulPersonalDocumentType', 'tawasulPersonalDocumentType.tawasulPersonalDocumentTypeID=tawasulPersonalDocument.tawasulPersonalDocumentTypeID')
                ->innerJoin('tawasulAdmissionsApplication', "JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.result, '$.tawasulPersonIDStudent'))=tawasulPersonalDocument.foreignTableID")
                ->where("tawasulPersonalDocument.foreignTable='tawasulPerson' AND tawasulPersonalDocumentType.activePersonParent=1")
                ->where('tawasulAdmissionsApplication.tawasulAdmissionsApplicationID=:foreignTableID', ['foreignTableID' => $foreignTableID])
                ->where(":foreignTable='tawasulAdmissionsApplication'")
                ->where("tawasulPersonalDocumentType.fields LIKE '%filePath%'");

        } else {
            $query = empty($query)? $this->newQuery() : $this->unionAllWithCriteria($query, $criteria);
            $query->cols(["'Personal Documents' AS type", "'Student' as target", 'tawasulPersonalDocumentType.name', 'tawasulPersonalDocument.tawasulPersonalDocumentID AS id',  'tawasulPersonalDocument.filePath AS path', 'tawasulFormField.required', 'tawasulPersonalDocument.timestamp'])
                ->from('tawasulFormField')
                ->innerJoin('tawasulFormPage', 'tawasulFormPage.tawasulFormPageID=tawasulFormField.tawasulFormPageID')
                ->innerJoin('tawasulPersonalDocumentType', 'tawasulFormField.fieldName="studentDocuments" AND tawasulPersonalDocumentType.activePersonStudent=1')
                ->leftJoin('tawasulPersonalDocument', 'tawasulPersonalDocumentType.tawasulPersonalDocumentTypeID=tawasulPersonalDocument.tawasulPersonalDocumentTypeID AND tawasulPersonalDocument.foreignTable=:foreignTable AND tawasulPersonalDocument.foreignTableID=:foreignTableID')
                ->where('tawasulFormPage.tawasulFormID=:tawasulFormID', ['tawasulFormID' => $tawasulFormID])
                ->where("tawasulPersonalDocumentType.fields LIKE '%filePath%' and tawasulPersonalDocumentType.activeApplicationForm=1")
                ->where('tawasulFormField.fieldGroup="PersonalDocuments"')
                ->bindValue('foreignTable', $foreignTable)
                ->bindValue('foreignTableID', $foreignTableID);

            $this->unionAllWithCriteria($query, $criteria)
                ->cols(["'Personal Documents' AS type", "'Parent 1' as target", 'tawasulPersonalDocumentType.name', 'tawasulPersonalDocument.tawasulPersonalDocumentID AS id', 'tawasulPersonalDocument.filePath AS path', 'tawasulFormField.required', 'tawasulPersonalDocument.timestamp'])
                ->from('tawasulFormField')
                ->innerJoin('tawasulFormPage', 'tawasulFormPage.tawasulFormPageID=tawasulFormField.tawasulFormPageID')
                ->innerJoin('tawasulPersonalDocumentType', 'tawasulFormField.fieldName="parent1Documents" AND tawasulPersonalDocumentType.activePersonParent=1')
                ->leftJoin('tawasulPersonalDocument', 'tawasulPersonalDocumentType.tawasulPersonalDocumentTypeID=tawasulPersonalDocument.tawasulPersonalDocumentTypeID AND tawasulPersonalDocument.foreignTable=:foreignTableP1 AND tawasulPersonalDocument.foreignTableID=:foreignTableID')
                ->where('tawasulFormPage.tawasulFormID=:tawasulFormID', ['tawasulFormID' => $tawasulFormID])
                ->where("tawasulPersonalDocumentType.fields LIKE '%filePath%' and tawasulPersonalDocumentType.activeApplicationForm=1")
                ->where('tawasulFormField.fieldGroup="PersonalDocuments"')
                ->bindValue('foreignTableP1', $foreignTable.'Parent1')
                ->bindValue('foreignTableID', $foreignTableID);

            $this->unionAllWithCriteria($query, $criteria)
                ->cols(["'Personal Documents' AS type", "'Parent 2' as target", 'tawasulPersonalDocumentType.name', 'tawasulPersonalDocument.tawasulPersonalDocumentID AS id',  'tawasulPersonalDocument.filePath AS path', 'tawasulFormField.required', 'tawasulPersonalDocument.timestamp'])
                ->from('tawasulFormField')
                ->innerJoin('tawasulFormPage', 'tawasulFormPage.tawasulFormPageID=tawasulFormField.tawasulFormPageID')
                ->innerJoin('tawasulPersonalDocumentType', 'tawasulFormField.fieldName="parent2Documents" AND tawasulPersonalDocumentType.activePersonParent=1')
                ->leftJoin('tawasulPersonalDocument', 'tawasulPersonalDocumentType.tawasulPersonalDocumentTypeID=tawasulPersonalDocument.tawasulPersonalDocumentTypeID AND tawasulPersonalDocument.foreignTable=:foreignTableP2 AND tawasulPersonalDocument.foreignTableID=:foreignTableID')
                ->where('tawasulFormPage.tawasulFormID=:tawasulFormID', ['tawasulFormID' => $tawasulFormID])
                ->where("tawasulPersonalDocumentType.fields LIKE '%filePath%' and tawasulPersonalDocumentType.activeApplicationForm=1")
                ->where('tawasulFormField.fieldGroup="PersonalDocuments"')
                ->bindValue('foreignTableP2', $foreignTable.'Parent2')
                ->bindValue('foreignTableID', $foreignTableID);
        }

        return $this->runQuery($query, $criteria);
    }

    public function getUploadByContext($tawasulFormID, $foreignTable, $foreignTableID, $name)
    {
        $select = $this
            ->newSelect()
            ->cols(['tawasulFormUpload.tawasulFormUploadID', 'tawasulFormUpload.path'])
            ->from($this->getTableName())
            ->where('tawasulFormUpload.tawasulFormID=:tawasulFormID', ['tawasulFormID' => $tawasulFormID])
            ->where('tawasulFormUpload.foreignTable=:foreignTable', ['foreignTable' => $foreignTable])
            ->where('tawasulFormUpload.foreignTableID=:foreignTableID', ['foreignTableID' => $foreignTableID])
            ->where('tawasulFormUpload.name=:name', ['name' => $name]);

        return $this->runSelect($select)->fetch();
    }

    public function selectAllUploadsByContext($tawasulFormID, $foreignTable, $foreignTableID)
    {
        $select = $this
            ->newSelect()
            ->cols(['tawasulFormUpload.name', 'tawasulFormUpload.path'])
            ->from($this->getTableName())
            ->where('tawasulFormUpload.tawasulFormID=:tawasulFormID', ['tawasulFormID' => $tawasulFormID])
            ->where('tawasulFormUpload.foreignTable=:foreignTable', ['foreignTable' => $foreignTable])
            ->where('tawasulFormUpload.foreignTableID=:foreignTableID', ['foreignTableID' => $foreignTableID]);

        return $this->runSelect($select);
    }
}
