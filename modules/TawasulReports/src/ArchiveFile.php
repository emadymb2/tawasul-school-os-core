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

namespace Tos\Module\TawasulReports;

use Tos\Module\TawasulReports\ReportData;

class ArchiveFile
{
    public function getBatchFilePath($tawasulReportID, $contextData) : string
    {
        $hash = substr(hash('sha1', $tawasulReportID.$contextData), 0, 12);
        $filename = 'reportBatch_'.$contextData.'_'.$hash.'.pdf';

        $path = $tawasulReportID.'/'.$filename;

        return $path;
    }

    public function getSingleFilePath($tawasulReportID, $tawasulYearGroupID, $identifier) : string
    {
        $hash = substr(hash('sha1', $tawasulReportID.$tawasulYearGroupID.$identifier), 0, 12);
        $filename = 'report_'.$identifier.'_'.$hash.'.pdf';

        $path = $tawasulReportID.'/'.$tawasulYearGroupID.'/'.$filename;

        return $path;
    }
}
