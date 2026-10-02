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

use Tos\Module\TawasulReports\Domain\ReportArchiveGateway;
use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/archive_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/archive_manage_migrate.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $tawasulReportArchiveID = $_POST['tawasulReportArchiveID'] ?? '';

    if (empty($tawasulReportArchiveID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $data = ['tawasulReportArchiveID' => $tawasulReportArchiveID];
    $sql = "INSERT INTO tawasulReportArchiveEntry (`tawasulReportArchiveID`, `tawasulReportID`, `tawasulSchoolYearID`, `tawasulYearGroupID`, `tawasulFormGroupID`, `tawasulPersonID`, `type`, `status`, `reportIdentifier`, `filePath`, `timestampCreated`, `timestampModified`) 
            SELECT :tawasulReportArchiveID, NULL, arrReport.schoolYearID, arrArchive.yearGroupID, (SELECT tawasulFormGroupID FROM tawasulStudentEnrolment WHERE tawasulStudentEnrolment.tawasulSchoolYearID=arrReport.schoolYearID AND tawasulStudentEnrolment.tawasulPersonID=arrArchive.studentID LIMIT 1), arrArchive.studentID, 'Single', 'Final', arrReport.reportName, arrArchive.reportName, arrArchive.created, arrArchive.created
            FROM arrArchive
            JOIN arrReport ON (arrReport.reportID=arrArchive.reportID)
            ON DUPLICATE KEY UPDATE timestampModified=arrArchive.created";

    $inserted = $pdo->affectingStatement($sql, $data);

    $URL .= !$pdo->getQuerySuccess()
        ? "&return=warning1"
        : "&return=success1";

    header("Location: {$URL}&imported={$inserted}");
}
