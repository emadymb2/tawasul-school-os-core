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

use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\Staff\SubstituteGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$search = $_GET['search'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStaff/substitutes_manage_add.php&search='.$search;

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/substitutes_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $subGateway = $container->get(SubstituteGateway::class);

    $data = [
        'tawasulPersonID' => $_POST['tawasulPersonID'] ?? '',
        'active'         => $_POST['active'] ?? '',
        'type'           => $_POST['type'] ?? '',
        'details'        => $_POST['details'] ?? '',
        'priority'       => $_POST['priority'] ?? '',
    ];

    // Validate the required values are present
    if (empty($data['tawasulPersonID']) || empty($data['active'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $person = $container->get(UserGateway::class)->getByID($data['tawasulPersonID']);

    if (empty($person)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this person doesn't already have a record
    if (!$subGateway->unique($data, ['tawasulPersonID'])) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Create the substitute
    $tawasulSubstituteID = $subGateway->insert($data);

    $URL .= !$tawasulSubstituteID
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}&editID=$tawasulSubstituteID");
}
