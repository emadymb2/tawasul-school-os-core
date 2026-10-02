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

use TawasulOS\Forms\Form;
use Tos\Module\TawasulReports\Domain\ReportGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reports_generate_batch.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!

    $tawasulReportID = $_GET['tawasulReportID'] ?? '';
    $processID = $_GET['processID'] ?? '';

    $values = $container->get(ReportGateway::class)->getByID($tawasulReportID);

    if (empty($tawasulReportID) || empty($processID)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $form = Form::create('reportsGenerate', $session->get('absoluteURL').'/modules/TawasulReports/reports_generate_cancelProcess.php');
    
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulReportID', $tawasulReportID);
    $form->addHiddenValue('processID', $processID);

    $row = $form->addRow();
        $row->addSubmit(__('Cancel'));

    echo $form->getOutput();
}
