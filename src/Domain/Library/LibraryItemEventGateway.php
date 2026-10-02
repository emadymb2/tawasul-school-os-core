<?php

namespace TawasulOS\Domain\Library;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\DataSet;

class LibraryItemEventGateway extends QueryableGateway
{
    use TableAware;
    private static $tableName = 'tawasulLibraryItemEvent';
    private static $primaryKey = 'tawasulLibraryItemEventID';
    private static $searchableColumns = [];

    public function getActiveEventByBorrower($tawasulLibraryItemID, $tawasulPersonIDStatusResponsible)
    {
        $data = ['tawasulLibraryItemID' => $tawasulLibraryItemID, 'tawasulPersonIDStatusResponsible' => $tawasulPersonIDStatusResponsible];
        $sql = "SELECT * FROM tawasulLibraryItemEvent 
            WHERE tawasulLibraryItemID=:tawasulLibraryItemID 
            AND tawasulPersonIDStatusResponsible=:tawasulPersonIDStatusResponsible
            AND (tawasulLibraryItemEvent.status='On Loan' OR tawasulLibraryItemEvent.status='Reserved')";

        return $this->db()->selectOne($sql, $data);
    }
}
