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

use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;
use Tos\Module\TawasulReports\Domain\ReportingScopeGateway;
use Tos\Module\TawasulReports\Domain\ReportingValueGateway;
use Tos\Module\TawasulReports\Domain\ReportingAccessGateway;
use Tos\Module\TawasulReports\Domain\ReportingCriteriaGateway;
use Tos\Module\TawasulReports\Domain\ReportingProgressGateway;
use Tos\Module\TawasulReports\Domain\ReportingProofGateway;

require_once __DIR__ . '/../../tawasul.php';

$tawasulReportingCycleID = $_POST['tawasulReportingCycleID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reporting_cycles_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_cycles_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} elseif (empty($tawasulReportingCycleID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $reportingCycleGateway = $container->get(ReportingCycleGateway::class);
    $reportingScopeGateway = $container->get(ReportingScopeGateway::class);
    $reportingValueGateway = $container->get(ReportingValueGateway::class);

    $values = $reportingCycleGateway->getByID($tawasulReportingCycleID);

    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $deleted = $reportingCycleGateway->delete($tawasulReportingCycleID);
    $partialFail &= !$deleted;

    // Delete access
    $partialFail &= !$container->get(ReportingAccessGateway::class)->deleteWhere(['tawasulReportingCycleID' => $tawasulReportingCycleID]);

    // Delete criteria
    $partialFail &= !$container->get(ReportingCriteriaGateway::class)->deleteWhere(['tawasulReportingCycleID' => $tawasulReportingCycleID]);

    // Delete progress
    $scopes = $reportingScopeGateway->selectBy(['tawasulReportingCycleID' => $tawasulReportingCycleID])->fetchAll();
    foreach ($scopes as $scopeData) {
        $partialFail &= !$container->get(ReportingProgressGateway::class)->deleteWhere(['tawasulReportingScopeID' => $scopeData['tawasulReportingScopeID']]);
    }

    // Delete Scopes
    $partialFail &= !$reportingScopeGateway->deleteWhere(['tawasulReportingCycleID' => $tawasulReportingCycleID]);

    // Delete proofs
    $values = $reportingValueGateway->selectBy(['tawasulReportingCycleID' => $tawasulReportingCycleID])->fetchAll();
    foreach ($values as $valueData) {
        $partialFail &= !$container->get(ReportingProofGateway::class)->deleteWhere(['tawasulReportingValueID' => $valueData['tawasulReportingValueID']]);
    }

    // Delete values
    $partialFail &= !$reportingValueGateway->deleteWhere(['tawasulReportingCycleID' => $tawasulReportingCycleID]);

    $URL .= $partialFail
        ? '&return=warning1'
        : '&return=success0';

    header("Location: {$URL}");
}
