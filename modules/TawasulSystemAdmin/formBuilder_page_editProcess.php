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

use TawasulOS\Domain\Forms\FormPageGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['introduction' => 'HTML', 'postscript' => 'HTML']);

$tawasulFormID = $_POST['tawasulFormID'] ?? '';
$tawasulFormPageID = $_POST['tawasulFormPageID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulSystemAdmin/formBuilder_page_edit.php&tawasulFormID='.$tawasulFormID.'&tawasulFormPageID='.$tawasulFormPageID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/formBuilder_page_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $formPageGateway = $container->get(FormPageGateway::class);

    $data = [
        'name'         => $_POST['name'] ?? '',
        'introduction' => $_POST['introduction'] ?? '',
        'postscript'   => $_POST['postscript'] ?? '',
        'tawasulFormID' => $_POST['tawasulFormID'] ?? '',
    ];

    // Validate the required values are present
    if (empty($data['name']) || empty($tawasulFormID) || empty($tawasulFormPageID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    if (!$formPageGateway->exists($tawasulFormPageID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$formPageGateway->unique($data, ['name', 'tawasulFormID'], $tawasulFormPageID)) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $updated = $formPageGateway->update($tawasulFormPageID, $data);

    $URL .= !$updated
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}
