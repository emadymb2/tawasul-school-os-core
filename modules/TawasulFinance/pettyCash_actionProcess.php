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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\Finance\PettyCashGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$params = [
    'action'                   => $_POST['action'] ?? '',
    'tawasulFinancePettyCashID' => $_POST['tawasulFinancePettyCashID'] ?? '',
    'tawasulSchoolYearID'       => $_POST['tawasulSchoolYearID'] ?? '',
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/pettyCash.php&'.http_build_query($params);

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/pettyCash_action.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $pettyCashGateway = $container->get(PettyCashGateway::class);

    $data = [
        'tawasulPersonIDStatus' => $session->get('tawasulPersonID'),
        'notes'                => $_POST['notes'],
    ];

    if (!empty($_POST['statusDate'])) {
        $data['timestampStatus'] = Format::dateConvert($_POST['statusDate']).' '.($_POST['statusTime'] ?? '00:00');
    }

    // Validate the required values are present
    if (empty($params['tawasulFinancePettyCashID']) || empty($data['timestampStatus'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record exists
    $values = $pettyCashGateway->getByID($params['tawasulFinancePettyCashID']);
    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Update the status
    if ($values['actionRequired'] == 'Repay') {
        $data['status'] = 'Repaid';
    } elseif ($values['actionRequired'] == 'Refund') {
        $data['status'] = 'Refunded';
    } else {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $tawasulFinancePettyCashID = $params['tawasulFinancePettyCashID'];
    $pettyCashGateway->update($tawasulFinancePettyCashID, $data);
    

    $URL .= !$tawasulFinancePettyCashID
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}
