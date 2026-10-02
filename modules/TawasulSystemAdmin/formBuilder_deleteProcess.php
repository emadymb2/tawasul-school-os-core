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

use TawasulOS\Domain\Forms\FormGateway;
use TawasulOS\Domain\Forms\FormPageGateway;
use TawasulOS\Domain\Forms\FormFieldGateway;

require_once __DIR__ . '/../../tawasul.php';

$tawasulFormID = $_POST['tawasulFormID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulSystemAdmin/formBuilder.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/formBuilder_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} elseif (empty($tawasulFormID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $formGateway = $container->get(FormGateway::class);
    $formPageGateway = $container->get(FormPageGateway::class);
    $formFieldGateway = $container->get(FormFieldGateway::class);

    $values = $formGateway->getByID($tawasulFormID);

    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Delete the form
    $deleted = $formGateway->delete($tawasulFormID);
    $partialFail &= !$deleted;

    $pages = $formPageGateway->selectBy(['tawasulFormID' => $tawasulFormID], ['tawasulFormPageID'])->fetchAll();

    // Delete all the fields for each page
    foreach ($pages as $page) {
        $deleted = $formFieldGateway->deleteWhere(['tawasulFormPageID' => $page['tawasulFormPageID']]);
        $partialFail &= !$deleted;
    }

    // Delete the pages
    $deleted = $formPageGateway->deleteWhere(['tawasulFormID' => $tawasulFormID]);
    $partialFail &= !$deleted;

    $URL .= $partialFail
        ? '&return=warning1'
        : '&return=success0';

    header("Location: {$URL}");
}
