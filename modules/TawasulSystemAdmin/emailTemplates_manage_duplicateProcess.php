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

use TawasulOS\Domain\System\EmailTemplateGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulEmailTemplateID = $_POST['tawasulEmailTemplateID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulSystemAdmin/emailTemplates_manage_duplicate.php&tawasulEmailTemplateID='.$tawasulEmailTemplateID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/emailTemplates_manage_duplicate.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $emailTemplateGateway = $container->get(EmailTemplateGateway::class);

    $data = [
        'templateName' => $_POST['templateName'] ?? '',
        'type'         => 'Custom',
    ];

    // Validate the required values are present
    if (empty($tawasulEmailTemplateID) || empty($data['templateName'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate that the database record exists
    $values = $emailTemplateGateway->getByID($tawasulEmailTemplateID);
    if (empty($values)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$emailTemplateGateway->unique($data, ['templateName'], $tawasulEmailTemplateID)) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $editID = $emailTemplateGateway->insert(array_merge($values, $data));

    $URL .= !$editID
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}&editID={$editID}");
}
