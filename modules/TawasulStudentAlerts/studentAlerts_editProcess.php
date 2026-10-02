<?php
/*
TawasulOS, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use TawasulOS\Http\Url;
use TawasulOS\Data\Validator;
use TawasulOS\Services\Format;
use TawasulOS\Support\Facades\Access;
use TawasulOS\Domain\System\AlertLevelGateway;
use TawasulOS\Domain\StudentAlerts\AlertGateway;
use TawasulOS\Domain\StudentAlerts\AlertTypeGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['comment' => 'HTML']);

$tawasulAlertID = $_POST['tawasulAlertID'] ?? '';

$URL = Url::fromModuleRoute('TawasulStudentAlerts', 'studentAlerts_edit')->withQueryParams(['tawasulAlertID' => $tawasulAlertID]);

if (!isActionAccessible($guid, $connection2, '/modules/TawasulStudentAlerts/studentAlerts_edit.php')) {
    // Access denied
    $URL = $URL.'&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $alertGateway = $container->get(AlertGateway::class);
    $alertLevelGateway = $container->get(AlertLevelGateway::class);
    $alertTypeGateway = $container->get(AlertTypeGateway::class);

    $action = Access::get('Student Alerts', 'studentAlerts_edit');
    $canEditAlert = $action->allowsAny('Manage Student Alerts_all', 'Manage Student Alerts_headOfYear') || $alertGateway->getAlertEditAccess($tawasulAlertID, $session->get('tawasulPersonID'));
    
    if (!$canEditAlert) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    $data = [
        'status'    => $_POST['status'] ?? 'Pending',
        'level'     => $_POST['level'] ?? '',
        'dateStart' => !empty($_POST['dateStart']) ? Format::dateConvert($_POST['dateStart']) : null,
        'dateEnd'   => !empty($_POST['dateEnd']) ? Format::dateConvert($_POST['dateEnd']) : null,
        'comment'   => $_POST['comment'] ?? '',
    ];

    // Validate the required values are present
    $values = $alertGateway->getByID($tawasulAlertID);
    if (empty($tawasulAlertID) || empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $alertType = $alertTypeGateway->getByID($values['tawasulAlertTypeID']);
    if (empty($alertType)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Ensure levels are turned off if not in use
    $data['tawasulAlertTypeID'] = $alertType['tawasulAlertTypeID'];
    if ($alertType['useLevels'] == 'N') {
        $data['tawasulAlertLevelID'] = null;
        $data['level'] = null;
    }

    // Set level ID based on level selected
    if (!empty($data['level'])) {
        if ($alertLevel = $alertLevelGateway->selectBy(['name' => $data['level']])->fetch()) {
            $data['tawasulAlertLevelID'] = $alertLevel['tawasulAlertLevelID'];
        }
    }

    $updated = $alertGateway->update($tawasulAlertID, $data);
    $partialFail = !$updated;

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
}
