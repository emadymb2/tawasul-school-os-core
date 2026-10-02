<?php

namespace TawasulOS\Domain\Library;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\DataSet;

class LibraryGateway extends QueryableGateway
{
    use TableAware;
    private static $tableName = 'tawasulLibraryItem';
    private static $primaryKey = 'tawasulLibraryItemID';
    private static $searchableColumns = ['tawasulLibraryItem.name', 'tawasulLibraryItem.producer', 'tawasulLibraryItem.id'];

    public function queryLendingDetail(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulLibraryItemEvent')
            ->cols([
                'tawasulPersonResponsible.tawasulPersonID as responsiblePersonID',
                'tawasulPersonResponsible.title as responsiblePersonTitle',
                'tawasulPersonResponsible.preferredName as responsiblePersonPreferredName',
                'tawasulPersonResponsible.surname as responsiblePersonSurname',
                'tawasulPersonResponsible.image_240 as responsiblePersonImage',
                'tawasulPersonOut.tawasulPersonId as outPersonID',
                'tawasulPersonOut.title as outPersonTitle',
                'tawasulPersonOut.preferredName as outPersonPreferredName',
                'tawasulPersonOut.surname as outPersonSurname',
                'tawasulPersonOut.image_240 as outPersonImage',
                'tawasulPersonIn.tawasulPersonID as inPersonID',
                'tawasulPersonIn.title as inPersonTitle',
                'tawasulPersonIn.preferredName as inPersonPreferredName',
                'tawasulPersonIn.surname as inPersonSurname',
                'tawasulPersonIn.image_240 as inPersonImage',
                'tawasulLibraryItemEvent.tawasulPersonIDStatusResponsible',
                'tawasulLibraryItemEvent.tawasulLibraryItemID',
                'tawasulLibraryItemEvent.tawasulLibraryItemEventID',
                'CONVERT(tawasulLibraryItemEvent.timestampOut,DATE) AS timestampOut',
                'CONVERT(tawasulLibraryItemEvent.timestampReturn,DATE) AS timestampReturn',
                'tawasulLibraryItemEvent.status',
                'tawasulLibraryItemEvent.returnExpected',
                'tawasulLibraryItemEvent.returnAction',
                'tawasulLibraryItemEvent.tawasulPersonIDOut',
                "IF(tawasulLibraryItemEvent.returnExpected < CURRENT_DATE,'Y','N') as pastDue"
            ])
            ->leftJoin('tawasulPerson as tawasulPersonResponsible', 'tawasulLibraryItemEvent.tawasulPersonIDStatusResponsible = tawasulPersonResponsible.tawasulPersonID')
            ->leftJoin('tawasulPerson as tawasulPersonOut', 'tawasulLibraryItemEvent.tawasulPersonIDOut = tawasulPersonOut.tawasulPersonID')
            ->leftJoin('tawasulPerson as tawasulPersonIn', 'tawasulLibraryItemEvent.tawasulPersonIDIn = tawasulPersonIn.tawasulPersonID');


        $criteria->addFilterRules([
            'tawasulLibraryItemID' => function ($query, $itemid) {
                return $query
                    ->where('tawasulLibraryItemEvent.tawasulLibraryItemID = :itemid')
                    ->bindValue('itemid', $itemid);
            }
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryLending(QueryCriteria $criteria)
    {
      
        $query = $this
        ->newQuery()
        ->from($this->getTableName())
        ->join('left', 'tawasulLibraryType', 'tawasulLibraryType.tawasulLibraryTypeID = tawasulLibraryItem.tawasulLibraryTypeID')
        ->join('left', 'tawasulSpace', 'tawasulSpace.tawasulSpaceId = tawasulLibraryItem.tawasulSpaceID')
        ->join('left', 'tawasulPerson', 'tawasulLibraryItem.tawasulPersonIDStatusResponsible = tawasulPerson.tawasulPersonID')
        ->cols([
          "tawasulLibraryItem.id",
          "tawasulLibraryItem.name",
          "tawasulLibraryItem.producer",
          "tawasulLibraryType.name as 'typeName'",
          "tawasulLibraryItem.tawasulLibraryItemID",
          "tawasulLibraryItem.tawasulLibraryTypeID",
          "tawasulLibraryItem.tawasulSpaceID",
          "tawasulLibraryItem.status",
          "tawasulLibraryItem.returnExpected",
          "tawasulLibraryItem.tawasulPersonIDStatusResponsible",
          "tawasulLibraryItem.timestampStatus",
          "tawasulPerson.title",
          "tawasulPerson.preferredName",
          "tawasulPerson.surname",
          "tawasulPerson.firstName",
          "tawasulSpace.name as 'spaceName'",
          "tawasulLibraryItem.locationDetail",
          "IF(tawasulLibraryItem.status = 'On Loan' AND tawasulLibraryItem.returnExpected < CURRENT_DATE,'Y','N') as 'pastDue'",
          "(SELECT tawasulLibraryItemEventID FROM tawasulLibraryItemEvent WHERE tawasulLibraryItemEvent.tawasulLibraryItemID=tawasulLibraryItem.tawasulLibraryItemID ORDER BY timestampOut DESC, timestampReturn DESC LIMIT 1) as tawasulLibraryItemEventID"
        ])
        ->where("tawasulLibraryItem.status IN ('Available','Repair','Reserved','On Loan')")
        ->where("ownershipType != 'Individual'")
        ->where("tawasulLibraryItem.borrowable = 'Y'");

        $criteria->addFilterRules([
            'name' => function ($query, $name) {
                return $query
                ->where('(tawasulLibraryItem.name like :name OR tawasulLibraryItem.producer like :name OR tawasulLibraryItem.id like :name)')
                ->bindValue('name', '%'.$name.'%');
            },
            'tawasulLibraryTypeID' => function ($query, $typeid) {
                return $query
                ->where('tawasulLibraryItem.tawasulLibraryTypeID = :typeid')
                ->bindValue('typeid', $typeid);
            },
            'tawasulSpaceID' => function ($query, $spaceid) {
                return $query
                ->where('tawasulLibraryItem.tawasulSpaceID = :spaceid')
                ->bindValue('spaceid', $spaceid);
            },
            'status' => function ($query, $status) {
                return $query
                ->where('tawasulLibraryItem.status = :status')
                ->bindValue('status', $status);
            }
        ]);
        return $this->runQuery($query, $criteria);
    }

    public function queryBrowseItems(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulLibraryItem.tawasulLibraryItemID',
                'tawasulLibraryItem.tawasulLibraryTypeID',
                'tawasulLibraryItem.id',
                'tawasulLibraryItem.name',
                'tawasulLibraryItem.producer',
                'tawasulLibraryItem.fields',
                'tawasulLibraryItem.vendor',
                'tawasulLibraryItem.purchaseDate',
                'tawasulLibraryItem.invoiceNumber',
                'tawasulLibraryItem.imageType',
                'tawasulLibraryItem.imageLocation',
                'tawasulLibraryItem.comment',
                'tawasulLibraryItem.tawasulSpaceID',
                'tawasulSpace.name as spaceName',
                'tawasulLibraryItem.locationDetail',
                'tawasulLibraryItem.ownershipType',
                'tawasulLibraryItem.tawasulPersonIDOwnership',
                'tawasulLibraryItem.physicalCondition',
                'tawasulLibraryItem.bookable',
                'tawasulLibraryItem.borrowable',
                'tawasulLibraryItem.status',
                'tawasulLibraryItem.tawasulPersonIDStatusResponsible',
                'tawasulLibraryItem.tawasulPersonIDStatusRecorder',
                'tawasulLibraryItem.timestampStatus',
                'tawasulLibraryItem.returnExpected',
                'tawasulLibraryItem.returnAction',
                'tawasulLibraryItem.tawasulPersonIDReturnAction',
                'tawasulLibraryItem.tawasulPersonIDCreator',
                'tawasulLibraryItem.timestampCreator',
                'tawasulLibraryItem.tawasulPersonIDUpdate',
                'JSON_EXTRACT(tawasulLibraryItem.fields , "$.Description") as description',
                'JSON_EXTRACT(tawasulLibraryItem.fields , "$.Subjects") as subjects',
                'JSON_EXTRACT(tawasulLibraryItem.fields , \'$."Search Terms"\') as searchTerms',
                'tawasulLibraryItem.timestampUpdate'
            ])
            ->innerJoin('tawasulLibraryType', 'tawasulLibraryItem.tawasulLibraryTypeID = tawasulLibraryType.tawasulLibraryTypeID')
            ->join('left', 'tawasulSpace', 'tawasulLibraryItem.tawasulSpaceID = tawasulSpace.tawasulSpaceID')
            ->where("tawasulLibraryItem.status IN ('Available','On Loan','Repair')")
            ->where("tawasulLibraryItem.ownershipType <> 'Individual'")
            ->where("tawasulLibraryItem.borrowable = 'Y'");

        $criteria->addFilterRules([
            'name' => function ($query, $name) {
                return $query
                    ->where('tawasulLibraryItem.name LIKE :name')
                    ->bindValue('name', '%' . $name . '%');
            },
            'producer' => function ($query, $producer) {
                return $query
                    ->where('tawasulLibraryItem.producer LIKE :producer')
                    ->bindValue('producer', '%' . $producer . '%');
            },
            'type' => function ($query, $type) {
                return $query
                    ->where('tawasulLibraryItem.tawasulLibraryTypeID = :type')
                    ->bindValue('type', $type);
            },
            'collection' => function ($query, $collection) {
                return $query
                    ->where("tawasulLibraryItem.fields LIKE CONCAT('%\"Collection\":\"', :collection, '\"%')")
                    ->bindValue('collection', $collection);
            },
            'location' => function ($query, $location) {
                return $query
                    ->where('tawasulSpace.name LIKE :location')
                    ->bindValue('location', $location);
            },
            'agecheck' => function ($query, $readerAge) {
                return $query
                    ->where('tawasulLibraryItem.fields->\'$."Reader Age (Youngest)"\' != "" 
                            AND tawasulLibraryItem.fields->\'$."Reader Age (Oldest)"\' != "" 
                            AND tawasulLibraryItem.fields->\'$."Reader Age (Youngest)"\'+0 <= :readerAge 
                            AND tawasulLibraryItem.fields->\'$."Reader Age (Oldest)"\'+0 >= :readerAge')
                    ->bindValue('readerAge', $readerAge);
            },
            'everything' => function ($query, $needle) {
                $globalSearch = "(";
                foreach ($query->getCols() as $col) {
                    if(preg_match('/.* as .*/', $col)) {
                        $col = preg_replace('/ as .*/', '', $col);
                    }
                    $globalSearch .= $col . " LIKE :needle OR ";
                }
                $globalSearch = preg_replace("/ OR $/", ")", $globalSearch);
                return $query
                    ->where($globalSearch)
                    ->bindValue('needle', '%' . $needle . '%');
            }
        ]);
        return $this->runQuery($query, $criteria);
    }

    public function queryCatalog(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulLibraryItem.tawasulLibraryItemID',
                'tawasulLibraryItem.tawasulLibraryItemIDParent',
                'tawasulLibraryItem.tawasulLibraryTypeID',
                'tawasulLibraryItem.id',
                'tawasulLibraryItem.name',
                'tawasulLibraryItem.producer',
                'tawasulLibraryItem.fields',
                'tawasulLibraryItem.vendor',
                'tawasulLibraryItem.purchaseDate',
                'tawasulLibraryItem.invoiceNumber',
                'tawasulLibraryItem.imageType',
                'tawasulLibraryItem.imageLocation',
                'tawasulLibraryItem.comment',
                'tawasulLibraryItem.tawasulSpaceID',
                'tawasulSpace.name as spaceName',
                'tawasulLibraryItem.locationDetail',
                'tawasulLibraryItem.ownershipType',
                'tawasulLibraryItem.tawasulPersonIDOwnership',
                'tawasulLibraryItem.physicalCondition',
                'tawasulLibraryItem.bookable',
                'tawasulLibraryItem.borrowable',
                'tawasulLibraryItem.status',
                'tawasulLibraryItem.tawasulPersonIDStatusResponsible',
                'tawasulLibraryItem.tawasulPersonIDStatusRecorder',
                'tawasulLibraryItem.timestampStatus',
                'tawasulLibraryItem.returnExpected',
                'tawasulLibraryItem.returnAction',
                'tawasulLibraryItem.tawasulPersonIDReturnAction',
                'tawasulLibraryItem.tawasulPersonIDCreator',
                'tawasulLibraryItem.timestampCreator',
                'tawasulLibraryItem.tawasulPersonIDUpdate',
                'tawasulLibraryItem.timestampUpdate',
                'tawasulPerson.title as title',
                'tawasulPerson.preferredName',
                'tawasulPerson.surname',
                'tawasulLibraryType.name as itemType',
                'responsible.title as titleResponsible',
                'responsible.surname as surnameResponsible',
                'responsible.preferredName as preferredNameResponsible',
                'tawasulFormGroup.nameShort as formGroup',
              ])
            ->innerJoin('tawasulLibraryType', 'tawasulLibraryItem.tawasulLibraryTypeID = tawasulLibraryType.tawasulLibraryTypeID')
            ->leftJoin('tawasulSpace', 'tawasulLibraryItem.tawasulSpaceID = tawasulSpace.tawasulSpaceID')
            ->leftJoin('tawasulPerson', 'tawasulLibraryItem.tawasulPersonIDOwnership = tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulPerson as responsible', 'responsible.tawasulPersonID=tawasulLibraryItem.tawasulPersonIDStatusResponsible')
            ->leftJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=responsible.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        $criteria->addFilterRules([
            'name' => function ($query, $name) {
                return $query
                    ->where('(tawasulLibraryItem.name LIKE :name OR tawasulLibraryItem.producer LIKE :name OR tawasulLibraryItem.id LIKE :name)')
                    ->bindValue('name', '%' . $name . '%');
            },
            'parent' => function ($query, $parentID) {
                if($parentID == 'NULL') {
                    $query = $query
                    ->leftJoin('tawasulLibraryItem AS parent', '(parent.tawasulLibraryItemID=tawasulLibraryItem.tawasulLibraryItemIDParent)')
                    ->where('(tawasulLibraryItem.id IS NULL OR parent.id IS NULL)');
                } else {
                    $query = $query
                    ->leftJoin('tawasulLibraryItem AS parent', '(parent.tawasulLibraryItemID=tawasulLibraryItem.tawasulLibraryItemIDParent)')
                    ->where('(tawasulLibraryItem.id = :parentID OR parent.id = :parentID)')
                    ->bindValue('parentID', $parentID);
                }
                return $query;
            },
            'type' => function ($query, $type) {
                return $query
                    ->where('tawasulLibraryItem.tawasulLibraryTypeID = :type')
                    ->bindValue('type', $type);
            },
            'location' => function ($query, $location) {
                return $query
                    ->where('tawasulLibraryItem.tawasulSpaceID = :location')
                    ->bindValue('location', $location);
            },
            'locationDetail' => function ($query, $locationDetail) {
                return $query
                    ->where('tawasulLibraryItem.locationDetail LIKE :locationDetail')
                    ->bindValue('locationDetail', '%'.$locationDetail.'%');
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('tawasulLibraryItem.status = :status')
                    ->bindValue('status', $status);
            },
            'owner' => function ($query, $owner) {
                return $query
                    ->where('tawasulLibraryItem.tawasulPersonIDOwnership = :owner')
                    ->bindValue('owner', $owner);
            },
            'typeSpecificFields' => function ($query, $typeSpecificFields) {
                return $query
                    ->where('tawasulLibraryItem.fields LIKE :typeSpecificFields')
                    ->bindValue('typeSpecificFields', '%'.$typeSpecificFields.'%');
            },
            'everything' => function ($query, $needle) {
                $globalSearch = "(";
                foreach ($query->getCols() as $col) {
                    $globalSearch .= $col . " LIKE :needle OR ";
                }
                $globalSearch = preg_replace("/ OR $/", ")", $globalSearch);
                return $query
                    ->where($globalSearch)
                    ->bindValue('needle', '%' . $needle . '%');
            }
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function getLibraryItemDetails($tawasulLibraryItemID)
    {
        $data = ['tawasulLibraryItemID' => $tawasulLibraryItemID];
        $sql = "SELECT tawasulLibraryItem.*, tawasulLibraryType.name AS type, parent.id as parentID
            FROM tawasulLibraryItem 
            JOIN tawasulLibraryType ON (tawasulLibraryItem.tawasulLibraryTypeID=tawasulLibraryType.tawasulLibraryTypeID) 
            LEFT JOIN tawasulLibraryItem AS parent ON (parent.tawasulLibraryItemID=tawasulLibraryItem.tawasulLibraryItemIDParent)
            WHERE tawasulLibraryItem.tawasulLibraryItemID=:tawasulLibraryItemID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getByRecordID($id)
    {
        $data = ['id' => $id];
        $sql = "SELECT * FROM tawasulLibraryItem WHERE id=:id OR (tawasulLibraryItem.fields IS NOT NULL AND tawasulLibraryItem.fields != '' AND JSON_VALID(tawasulLibraryItem.fields) AND JSON_EXTRACT(tawasulLibraryItem.fields , '$.ISBN13')=:id)";

        return $this->db()->selectOne($sql, $data);
    }

    public function getChildRecordCount($tawasulLibraryItemID)
    {
        $data = ['tawasulLibraryItemID' => $tawasulLibraryItemID];
        $sql = "SELECT COUNT(*) FROM tawasulLibraryItem WHERE tawasulLibraryItemIDParent=:tawasulLibraryItemID";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectChildRecordIDs($tawasulLibraryItemID)
    {
        $data = ['tawasulLibraryItemID' => $tawasulLibraryItemID];
        $sql = "SELECT tawasulLibraryItemID FROM tawasulLibraryItem WHERE tawasulLibraryItemIDParent=:tawasulLibraryItemID";

        return $this->db()->select($sql, $data);
    }

    public function updateChildRecords($tawasulLibraryItemIDParent)
    {
        $data = ['tawasulLibraryItemIDParent' => $tawasulLibraryItemIDParent];

        $sql = "UPDATE tawasulLibraryItem 
            JOIN tawasulLibraryItem AS parent ON (parent.tawasulLibraryItemID=tawasulLibraryItem.tawasulLibraryItemIDParent)
            SET tawasulLibraryItem.fields=parent.fields, 
                tawasulLibraryItem.name=parent.name,
                tawasulLibraryItem.producer=parent.producer,
                tawasulLibraryItem.vendor=parent.vendor,
                tawasulLibraryItem.imageType=parent.imageType,
                tawasulLibraryItem.imageLocation=parent.imageLocation,
                tawasulLibraryItem.tawasulSpaceID=parent.tawasulSpaceID,
                tawasulLibraryItem.locationDetail=parent.locationDetail,
                tawasulLibraryItem.tawasulDepartmentID=parent.tawasulDepartmentID,
                tawasulLibraryItem.tawasulPersonIDUpdate=parent.tawasulPersonIDUpdate,
                tawasulLibraryItem.timestampUpdate=parent.timestampUpdate
            WHERE tawasulLibraryItem.tawasulLibraryItemIDParent=:tawasulLibraryItemIDParent";

        return $this->db()->update($sql, $data);
    }

    public function updateFromParentRecord($tawasulLibraryItemID)
    {
        $data = ['tawasulLibraryItemID' => $tawasulLibraryItemID];

        $sql = "UPDATE tawasulLibraryItem 
            JOIN tawasulLibraryItem AS parent ON (parent.tawasulLibraryItemID=tawasulLibraryItem.tawasulLibraryItemIDParent)
            SET tawasulLibraryItem.fields=parent.fields, 
                tawasulLibraryItem.name=parent.name,
                tawasulLibraryItem.producer=parent.producer,                
                tawasulLibraryItem.vendor=parent.vendor,                
                tawasulLibraryItem.imageType=parent.imageType,
                tawasulLibraryItem.imageLocation=parent.imageLocation,
                tawasulLibraryItem.tawasulSpaceID=parent.tawasulSpaceID,
                tawasulLibraryItem.locationDetail=parent.locationDetail,
                tawasulLibraryItem.tawasulDepartmentID=parent.tawasulDepartmentID
            WHERE tawasulLibraryItem.tawasulLibraryItemID=:tawasulLibraryItemID";

        return $this->db()->update($sql, $data);
    }
    
    public function queryItemsForShelves(QueryCriteria $criteria)
    {
      
        $query = $this
        ->newQuery()
        ->from($this->getTableName())
        ->cols([
          "tawasulLibraryItem.id",
          "tawasulLibraryItem.name",
          "tawasulLibraryItem.producer",
          "tawasulLibraryItem.tawasulLibraryItemID",
          "tawasulLibraryItem.tawasulLibraryTypeID",
          "tawasulLibraryItem.imageLocation",
          "tawasulLibraryItem.status",
        ])
        ->where("tawasulLibraryItem.imageLocation IS NOT NULL AND tawasulLibraryItem.imageLocation != ''")
        ->where("tawasulLibraryItem.status IN ('Available','On Loan','Repair')");

        $criteria->addFilterRules([
        'name' => function ($query, $name) {
            return $query
            ->where('(tawasulLibraryItem.name like :name OR tawasulLibraryItem.producer like :name OR tawasulLibraryItem.id like :name)')
            ->bindValue('name', '%'.$name.'%');
        },
        'parent' => function ($query, $parentID) {
            if($parentID == 'NULL') {
                $query = $query
                ->leftJoin('tawasulLibraryItem AS parent', '(parent.tawasulLibraryItemID=tawasulLibraryItem.tawasulLibraryItemIDParent)')
                ->where('(tawasulLibraryItem.id IS NULL OR parent.id IS NULL)');
            } else {
                $query = $query
                ->leftJoin('tawasulLibraryItem AS parent', '(parent.tawasulLibraryItemID=tawasulLibraryItem.tawasulLibraryItemIDParent)')
                ->where('(tawasulLibraryItem.id = :parentID OR parent.id = :parentID)')
                ->bindValue('parentID', $parentID);
            }
            return $query;
        },
        ]);
        return $this->runQuery($query, $criteria);
    }

    public function selectDistinctVendorList()
    {
        $data = [];
        $sql = "SELECT DISTINCT vendor FROM tawasulLibraryItem ORDER BY vendor";

        return $this->db()->select($sql, $data);
    }

    public function selectDistinctLocationDetails()
    {
        $data = [];
        $sql = "SELECT DISTINCT locationDetail FROM tawasulLibraryItem ORDER BY locationDetail";

        return $this->db()->select($sql, $data);
    }
}
