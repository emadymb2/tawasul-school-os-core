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

namespace TawasulOS\Domain\User;

use TawasulOS\Services\Format;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByTimestamp;

/**
 * @version v22
 * @since   v22
 */
class PersonalDocumentGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByTimestamp;

    private static $tableName = 'tawasulPersonalDocument';
    private static $primaryKey = 'tawasulPersonalDocumentID';

    private static $searchableColumns = [];

    private static $scrubbableKey = 'timestamp';
    private static $scrubbableColumns = ['documentNumber' => null,'documentName' => null,'documentType' => null,'dateIssue' => null,'dateExpiry' => null,'filePath' => 'deleteFile','country' => null];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryDocumentsByPerson(QueryCriteria $criteria, $tawasulPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulPersonalDocumentID', 'tawasulPersonID',
            ]);

        $criteria->addFilterRules([
            'type' => function ($query, $type) {
                return $query
                    ->where('tawasulPersonalDocument.type = :type')
                    ->bindValue('type', $type);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentDocuments(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonIDList)
    {
        if (is_string($tawasulPersonIDList)) {
            $tawasulPersonIDList = explode(',', $tawasulPersonIDList);
        }

        if (is_array($tawasulPersonIDList)) {
            $tawasulPersonIDList = array_map(function($item) {
                return str_pad($item, 12, 0, STR_PAD_LEFT);
            }, $tawasulPersonIDList);
            $tawasulPersonIDList = implode(',', $tawasulPersonIDList);
        }

        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols(['tawasulPersonalDocument.tawasulPersonalDocumentID', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulFormGroup.name as formGroup', 'tawasulPersonalDocumentType.name as documentTypeName', 'tawasulPersonalDocumentType.document', 'tawasulPersonalDocument.documentNumber', 'tawasulPersonalDocument.documentName', 'tawasulPersonalDocument.documentType', 'tawasulPersonalDocument.dateIssue', 'tawasulPersonalDocument.dateExpiry', 'tawasulPersonalDocument.country', 'tawasulPersonalDocument.filePath'])
            ->innerJoin('tawasulPersonalDocumentType', 'tawasulPersonalDocument.tawasulPersonalDocumentTypeID=tawasulPersonalDocumentType.tawasulPersonalDocumentTypeID')
            ->innerJoin('tawasulPerson', 'LPAD(tawasulPerson.tawasulPersonID, 12, "0")=tawasulPersonalDocument.foreignTableID')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulPersonalDocument.foreignTable="tawasulPerson"')
            ->where('FIND_IN_SET(tawasulPersonalDocument.foreignTableID, :tawasulPersonIDList)')
            ->bindValue('tawasulPersonIDList', $tawasulPersonIDList);

        $criteria->addFilterRules([
            'documents' => function ($query, $documents) {
                if (empty($documents)) return $query;
                return $query
                    ->where('FIND_IN_SET(tawasulPersonalDocumentType.tawasulPersonalDocumentTypeID, :documents)')
                    ->bindValue('documents', $documents);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectPersonalDocuments($foreignTable = null, $foreignTableID = null, $params = [])
    {
        $query = $this
            ->newSelect()
            ->cols(['tawasulPersonalDocumentType.*'])
            ->from('tawasulPersonalDocumentType')
            ->where("tawasulPersonalDocumentType.active='Y'");

        if (!empty($foreignTable) && !empty($foreignTableID)) {
            $query
                ->cols(['tawasulPersonalDocument.tawasulPersonalDocumentID', 
                'tawasulPersonalDocument.foreignTable', 'tawasulPersonalDocument.foreignTableID', 'tawasulPersonalDocument.documentName', 'tawasulPersonalDocument.documentNumber', 'tawasulPersonalDocument.documentType', 'tawasulPersonalDocument.country', 'tawasulPersonalDocument.dateIssue', 'tawasulPersonalDocument.dateExpiry', 'tawasulPersonalDocument.filePath'])
                ->leftJoin('tawasulPersonalDocument', 'tawasulPersonalDocument.tawasulPersonalDocumentTypeID=tawasulPersonalDocumentType.tawasulPersonalDocumentTypeID AND tawasulPersonalDocument.foreignTable=:foreignTable AND tawasulPersonalDocument.foreignTableID=:foreignTableID')
                ->bindValue('foreignTable', $foreignTable)
                ->bindValue('foreignTableID', $foreignTableID);
        }

        // Handle role category flags as ORs
        $query->where(function ($query) use (&$params) {
            if ($params['student'] ?? false) {
                $query->orWhere('activePersonStudent=:student', ['student' => $params['student']]);
            }
            if ($params['staff'] ?? false) {
                $query->orWhere('activePersonStaff=:staff', ['staff' => $params['staff']]);
            }
            if ($params['parent'] ?? false) {
                $query->orWhere('activePersonParent=:parent', ['parent' => $params['parent']]);
            }
            if ($params['other'] ?? false) {
                $query->orWhere('activePersonOther=:other', ['other' => $params['other']]);
            }
        });

        // Handle additional flags as ANDs
        if ($params['applicationForm'] ?? false) {
            $query->where('activeApplicationForm=:applicationForm', ['applicationForm' => $params['applicationForm']]);
        }
        if ($params['dataUpdater'] ?? false) {
            $query->where('activeDataUpdater=:dataUpdater', ['dataUpdater' => $params['dataUpdater']]);
        }
        if ($params['notEmpty'] ?? false) {
            $query->where('tawasulPersonalDocument.tawasulPersonalDocumentID IS NOT NULL');
        }

        $query->orderBy(['sequenceNumber', 'name']);

        return $this->runSelect($query);
    }

    public function updatePersonalDocumentOwnership($foreignTableOld, $foreignTableOldID, $foreignTableNew, $foreignTableNewID)
    {
        $data = ['foreignTableOld' => $foreignTableOld, 'foreignTableOldID' => $foreignTableOldID, 'foreignTableNew' => $foreignTableNew, 'foreignTableNewID' => $foreignTableNewID];
        $sql = "UPDATE tawasulPersonalDocument 
                SET foreignTable=:foreignTableNew, foreignTableID=:foreignTableNewID 
                WHERE foreignTable=:foreignTableOld AND foreignTableID=:foreignTableOldID";

        return $this->db()->update($sql, $data);
    }

    public function deletePersonalDocuments($foreignTable, $foreignTableID)
    {
        $data = ['foreignTable' => $foreignTable, 'foreignTableID' => $foreignTableID];
        $sql = "DELETE FROM tawasulPersonalDocument 
                WHERE foreignTable=:foreignTable AND foreignTableID=:foreignTableID";

        return $this->db()->delete($sql, $data);
    }

    public function getPersonalDocumentDataByID($tawasulPersonalDocumentTypeID, $foreignTable, $foreignTableID)
    {
        $data = ['tawasulPersonalDocumentTypeID' => $tawasulPersonalDocumentTypeID, 'foreignTable' => $foreignTable, 'foreignTableID' => $foreignTableID];
        $sql = "SELECT tawasulPersonalDocumentID, filePath FROM tawasulPersonalDocument WHERE tawasulPersonalDocumentTypeID=:tawasulPersonalDocumentTypeID AND  foreignTable=:foreignTable AND foreignTableID=:foreignTableID";

        return $this->db()->select($sql, $data)->fetch();
    }
}
