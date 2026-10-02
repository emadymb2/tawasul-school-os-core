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
    $contextData = is_array($_REQUEST['contextData']) ? implode(',', $_REQUEST['contextData']) : $_REQUEST['contextData'];

    $values = $container->get(ReportGateway::class)->getByID($tawasulReportID);

    if (empty($tawasulReportID) || empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $form = Form::create('reportsGenerate', $session->get('absoluteURL').'/modules/TawasulReports/reports_generate_batchProcess.php');
    
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulReportID', $tawasulReportID);
    $form->addHiddenValue('contextData', $contextData);
    $form->addHiddenValue('action', 'Generate');

    $options = ['Draft' => __('Draft'), 'Final' => __('Final')];
    $row = $form->addRow();
        $row->addLabel('status', __('Status'))->description(__('Draft reports are not accessible by parents or students.'));
        $row->addSelect('status')->fromArray($options)->required();

    $row = $form->addRow();
        $row->addLabel('twoSided', __('Two-sided'))->description(__('Adds blank pages as required between individual reports.'));
        $row->addYesNo('twoSided')->required()->selected('Y');

    $row = $form->addRow();
        $row->addSubmit(__('Run'));

    echo $form->getOutput();
}
