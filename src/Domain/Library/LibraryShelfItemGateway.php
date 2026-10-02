<?php

namespace TawasulOS\Domain\Library;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\DataSet;

class LibraryShelfItemGateway extends QueryableGateway
{
    use TableAware;
    private static $tableName = 'tawasulLibraryShelfItem';
    private static $primaryKey = 'tawasulLibraryShelfItemID';
    private static $searchableColumns = [];

    public function insertShelfItem($tawasulLibraryItemID, $tawasulLibraryShelfID) {
        return $this->insert([
            'tawasulLibraryItemID' 	    => $tawasulLibraryItemID,
            'tawasulLibraryShelfID'  	=> $tawasulLibraryShelfID
        ]);
    }


    public function queryItemsByShelfID($tawasulLibraryShelfID, QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulLibraryItem')
            ->cols([
                'tawasulLibraryShelfItem.tawasulLibraryShelfItemID', 
                'tawasulLibraryShelfItem.tawasulLibraryItemID', 
                'tawasulLibraryItem.name', 
                'tawasulLibraryItem.producer',
                'tawasulLibraryItem.imageLocation',
                'tawasulLibraryItem.status',
                'tawasulLibraryItem.locationDetail',
                'JSON_EXTRACT(tawasulLibraryItem.fields , "$.Description") as description',
                'tawasulSpace.name as spaceName',
            ])
            ->innerJoin('tawasulLibraryShelfItem', 'tawasulLibraryItem.tawasulLibraryItemID=tawasulLibraryShelfItem.tawasulLibraryItemID')
            ->leftJoin('tawasulSpace', 'tawasulLibraryItem.tawasulSpaceID = tawasulSpace.tawasulSpaceID')
            ->where('tawasulLibraryShelfItem.tawasulLibraryShelfID=:tawasulLibraryShelfID')
            ->bindValue('tawasulLibraryShelfID', $tawasulLibraryShelfID);

        return $this->runQuery($query, $criteria);
    }

    public function selectDefaultShelfTopBorrowed()
    {
        $data = ['timestampOut' => date('Y-m-d H:i:s', (time() - (60 * 60 * 24 * 30)))];
        $sql = "SELECT tawasulLibraryItem.name, tawasulLibraryItem.producer, tawasulLibraryItem.imageLocation, tawasulLibraryItem.status, tawasulLibraryItem.locationDetail, JSON_EXTRACT(tawasulLibraryItem.fields , \"$.Description\") as description, tawasulSpace.name as spaceName, COUNT( * ) AS count 
        FROM tawasulLibraryItem 
        JOIN tawasulLibraryItemEvent ON (tawasulLibraryItemEvent.tawasulLibraryItemID=tawasulLibraryItem.tawasulLibraryItemID) 
        JOIN tawasulLibraryType ON (tawasulLibraryItem.tawasulLibraryTypeID=tawasulLibraryType.tawasulLibraryTypeID)
        JOIN tawasulSpace ON (tawasulLibraryItem.tawasulSpaceID=tawasulSpace.tawasulSpaceID) 
        WHERE timestampOut>=:timestampOut 
        AND tawasulLibraryItem.borrowable='Y' 
        AND tawasulLibraryItemEvent.type='Loan' 
        AND tawasulLibraryType.name='Print Publication' 
        AND tawasulSpace.type='Library'
        AND tawasulLibraryItem.imageLocation IS NOT NULL
        AND tawasulLibraryItem.imageLocation <> ''
        GROUP BY producer, name 
        ORDER BY count DESC LIMIT 0, 20";

        return $this->db()->select($sql, $data);
    }

    public function selectDefaultShelfNewItems()
    {
        $sql = "SELECT tawasulLibraryItem.name, tawasulLibraryItem.producer, tawasulLibraryItem.imageLocation, tawasulLibraryItem.status, tawasulLibraryItem.locationDetail, JSON_EXTRACT(tawasulLibraryItem.fields , \"$.Description\") as description, tawasulSpace.name as spaceName
            FROM tawasulLibraryItem 
            JOIN tawasulLibraryType ON (tawasulLibraryItem.tawasulLibraryTypeID=tawasulLibraryType.tawasulLibraryTypeID) 
            JOIN tawasulSpace ON (tawasulLibraryItem.tawasulSpaceID=tawasulSpace.tawasulSpaceID) 
            WHERE tawasulLibraryItem.borrowable='Y' 
            AND tawasulLibraryType.name='Print Publication' 
            AND tawasulSpace.type='Library'
            AND tawasulLibraryItem.imageLocation IS NOT NULL
            AND tawasulLibraryItem.imageLocation <> ''
            ORDER BY timestampCreator DESC LIMIT 0, 20";

        return $this->db()->select($sql);
    }
}