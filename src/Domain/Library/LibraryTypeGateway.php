<?php

namespace TawasulOS\Domain\Library;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\DataSet;

class LibraryTypeGateway extends QueryableGateway
{
    use TableAware;
    private static $tableName = 'tawasulLibraryType';
    private static $primaryKey = 'tawasulLibraryTypeID';
    private static $searchableColumns = [];
}
