<?php

namespace TawasulOS\Domain\Library;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\DataSet;

class LibraryItemGateway extends QueryableGateway
{
    use TableAware;
    private static $tableName = 'tawasulLibraryItem';
    private static $primaryKey = 'tawasulLibraryItemID';
    private static $searchableColumns = [];
}
