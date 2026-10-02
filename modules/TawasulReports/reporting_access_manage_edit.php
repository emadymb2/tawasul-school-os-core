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

use TawasulOS\Domain\School\GradeScaleGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulReports\Domain\ReportingAccessGateway;
use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;
use Tos\Module\TawasulReports\Domain\ReportingScopeGateway;
use TawasulOS\Forms\DatabaseFormFactory;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_access_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage Access'), 'reporting_access_manage.php')
        ->add(__('Edit Access'));

    $tawasulReportingAccessID = $_GET['tawasulReportingAccessID'] ?? '';
    $reportingAccessGateway = $container->get(ReportingAccessGateway::class);

    if (empty($tawasulReportingAccessID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $values = $reportingAccessGateway->getByID($tawasulReportingAccessID);

    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
    $reportingScopeGateway = $container->get(ReportingScopeGateway::class);
    $reportingCycleGateway = $container->get(ReportingCycleGateway::class);

    $form = Form::create('accessManage', $session->get('absoluteURL').'/modules/TawasulReports/reporting_access_manage_editProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulReportingAccessID', $tawasulReportingAccessID);

    $values['tawasulRoleIDList'] = explode(',', $values['tawasulRoleIDList']);
    $row = $form->addRow();
        $row->addLabel('tawasulRoleIDList', __('Roles'));
        $row->addSelectRole('tawasulRoleIDList')->required()->selectMultiple();

    $reportingCycle = $reportingCycleGateway->getByID($values['tawasulReportingCycleID']);
    $row = $form->addRow();
        $row->addLabel('reportingCycle', __('Reporting Cycle'));
        $row->addTextField('reportingCycle')->readonly()->setValue($reportingCycle['name']);

    $reportingScopes = $reportingScopeGateway->selectReportingScopesBySchoolYear($tawasulSchoolYearID)->fetchGrouped();
    $scopesOptions = $reportingScopes[$values['tawasulReportingCycleID']] ?? [];
    $scopesOptions = array_combine(array_column($scopesOptions, 'value'), array_column($scopesOptions, 'name'));

    $row = $form->addRow();
        $row->addLabel('tawasulReportingScopeID', __('Scope'));
        $row->addSelect('tawasulReportingScopeID')
            ->setSize(4)
            ->fromArray($scopesOptions)
            ->selectMultiple()
            ->required()
            ->selected(explode(',', $values['tawasulReportingScopeIDList']));

    $row = $form->addRow();
        $row->addLabel('dateStart', __('Start Date'));
        $row->addDate('dateStart')->chainedTo('dateEnd')->required();

    $row = $form->addRow();
        $row->addLabel('dateEnd', __('End Date'));
        $row->addDate('dateEnd')->chainedFrom('dateStart')->required();

    $row = $form->addRow();
        $row->addLabel('canWrite', __('Can Write'));
        $row->addYesNo('canWrite')->required();

    $row = $form->addRow();
        $row->addLabel('canProofRead', __('Can Proof Read'));
        $row->addYesNo('canProofRead')->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    $form->loadAllValuesFrom($values);

    echo $form->getOutput();
}
