<?php

namespace TawasulOS\Domain\Library;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\DataSet;

class LibraryShelfGateway extends QueryableGateway
{
    use TableAware;
    private static $tableName = 'tawasulLibraryShelf';
    private static $primaryKey = 'tawasulLibraryShelfID';
    private static $searchableColumns = [];

    public function queryLibraryShelves(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulLibraryShelf.tawasulLibraryShelfID',
                'tawasulLibraryShelf.name',
                'tawasulLibraryShelf.active',
                'tawasulLibraryShelf.shuffle',
                'tawasulLibraryShelf.field',
                'tawasulLibraryShelf.fieldValue',
                'tawasulLibraryShelf.type',
                 'tawasulLibraryShelf.tawasulLibraryTypeID',
                'tawasulLibraryShelf.sequenceNumber',
            ]);

        $criteria->addFilterRules([
            'name' => function ($query, $name) {
                return $query
                    ->where('tawasulLibraryShelf.name LIKE :name')
                    ->bindValue('name', '%' . $name . '%');
            },
            'active' => function ($query, $active) {
                return $query
                    ->where('tawasulLibraryShelf.active = :active')
                    ->bindValue('active', $active);
            }
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function getShelfByID($id) {
        $data = ['id' => $id];
        $sql = "SELECT * FROM tawasulLibraryShelf WHERE tawasulLibraryShelfID=:id";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectDisplayableCategories() {
        // Build the type/collection arrays
        $sql = "SELECT tawasulLibraryTypeID as value, name, fields FROM tawasulLibraryType WHERE active='Y' ORDER BY name";
        $result = $this->db()->select($sql);
        
        $typeList = ($result->rowCount() > 0) ? $result->fetchAll() : array();
        $category = $categoryChained = $subCategory = $subCategoryChained = array();
        $types = array_reduce($typeList, function ($group, $item) use (&$category, &$categoryChained, &$subCategory, &$subCategoryChained) {
            $group[$item['value']] = __($item['name']);

            foreach (json_decode($item['fields'], true) as $field) {
                if ($field['type'] == 'Select') {
                    $category[$field['name']] = __($field['name']);
                    $categoryChained[$field['name']] = $item['value'];
                    if($item['name'] == 'Print Publication'){
                        $category['Search Terms'] = __('Search Terms');
                        $categoryChained['Search Terms'] = $item['value'];
                    }
                    $categoryChained[$field['name']] = $item['value'];
                    foreach (explode(',', $field['options']) as $fieldItem) {
                        $fieldItem = trim($fieldItem);
                        $subCategory[$fieldItem] = __($fieldItem);
                        $subCategoryChained[$fieldItem] = $field['name'];
                    }
                }
            }
            return $group;
        }, []);

        return ['category' => $category, 
                'categoryChained' => $categoryChained, 
                'subCategory' => $subCategory, 
                'subCategoryChained' => $subCategoryChained, 
                'types' => $types];
    }
    
    public function selectItemsByTypeAndFields($tawasulLibraryTypeID, $field, $fieldValue)
    {
        if ($field == 'Search Terms') {
            $fieldValue = '"%'.$fieldValue.'%"';
        }

        $field = '$."'.$field.'"';
        $data = ['tawasulLibraryTypeID' => $tawasulLibraryTypeID, 'field' => $field, 'fieldValue' => $fieldValue];
        $sql = "SELECT tawasulLibraryItem.tawasulLibraryItemID, tawasulLibraryItem.name, tawasulLibraryItem.producer, tawasulLibraryItem.imageLocation, tawasulLibraryItem.status, tawasulLibraryItem.locationDetail, JSON_EXTRACT(tawasulLibraryItem.fields, '$.Description') as description, tawasulSpace.name as spaceName FROM tawasulLibraryItem JOIN tawasulLibraryType ON (tawasulLibraryType.tawasulLibraryTypeID = tawasulLibraryItem.tawasulLibraryTypeID) JOIN tawasulSpace ON (tawasulSpace.tawasulSpaceID = tawasulLibraryItem.tawasulSpaceID) WHERE tawasulLibraryItem.tawasulLibraryTypeID = :tawasulLibraryTypeID AND tawasulLibraryItem.tawasulLibraryItemIDParent IS NULL";

        if($field == '$."Search Terms"') {
            $sql .= " AND JSON_EXTRACT(tawasulLibraryItem.fields , :field) LIKE :fieldValue;";
        } else {
            $sql .= " AND JSON_EXTRACT(tawasulLibraryItem.fields , :field) = :fieldValue;";
        }
        
        return $this->db()->select($sql, $data);
    }
}
