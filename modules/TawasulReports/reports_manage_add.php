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
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\School\SchoolYearGateway;
use Tos\Module\TawasulReports\Domain\ReportArchiveGateway;
use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;
use Tos\Module\TawasulReports\Domain\ReportTemplateGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reports_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');

    $page->breadcrumbs
        ->add(__('Manage Reports'), 'reports_manage.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
        ->add(__('Add Report'));

    $editLink = '';
    if (isset($_GET['editID'])) {
        $editLink = $session->get('absoluteURL').'/index.php?q=/modules/TawasulReports/reports_manage_edit.php&tawasulSchoolYearID='.$tawasulSchoolYearID.'&tawasulReportID='.$_GET['editID'];
    }

    $page->return->setEditLink($editLink);

    $reportingCycleGateway = $container->get(ReportingCycleGateway::class);

    $form = Form::create('reportsManage', $session->get('absoluteURL').'/modules/TawasulReports/reports_manage_addProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

    $form->addRow()->addHeading('Report Details', __('Report Details'));

    $schoolYear = $container->get(SchoolYearGateway::class)->getSchoolYearByID($tawasulSchoolYearID);
    $row = $form->addRow();
        $row->addLabel('schoolYear', __('School Year'));
        $row->addTextField('schoolYear')->readonly()->setValue($schoolYear['name']);

    $row = $form->addRow();
        $row->addLabel('name', __('Name'))->description(__('Must be unique'));
        $row->addTextField('name')->maxLength(90)->required();

    $row = $form->addRow();
        $row->addLabel('active', __('Active'));
        $row->addYesNo('active')->required();

    $templatesCycles = $container->get(ReportTemplateGateway::class)->selectActiveTemplates('Reporting Cycle')->fetchKeyPair();
    $templatesEnrolment = $container->get(ReportTemplateGateway::class)->selectActiveTemplates('Student Enrolment')->fetchKeyPair();
    $row = $form->addRow();
        $row->addLabel('tawasulReportTemplateID', __('Template'));
        $row->addSelect('tawasulReportTemplateID')->fromArray($templatesCycles + $templatesEnrolment)->required()->placeholder();

    // Reporting Cycle Context
    if (!empty($templatesCycles)) {
        $form->toggleVisibilityByClass('reportingCycleContext')->onSelect('tawasulReportTemplateID')->when(array_keys($templatesCycles));

        $reportingCycles = $reportingCycleGateway->selectReportingCyclesBySchoolYear($tawasulSchoolYearID)->fetchKeyPair();
        $row = $form->addRow()->addClass('reportingCycleContext');
            $row->addLabel('tawasulReportingCycleID', __('Reporting Cycle'));
            $row->addSelect('tawasulReportingCycleID')->fromArray($reportingCycles)->required()->placeholder();
    }

    // Student Enrolment Context
    $row = $form->addRow();
        $row->addLabel('tawasulYearGroupIDList', __('Year Groups'));
        $row->addCheckboxYearGroup('tawasulYearGroupIDList')->addCheckAllNone()->loadFromCSV($values);
    
    $form->addRow()->addHeading('Access', __('Access'));

    $archives = $container->get(ReportArchiveGateway::class)->selectWriteableArchives()->fetchKeyPair();
    $row = $form->addRow();
        $row->addLabel('tawasulReportArchiveID', __('Archive'))->description(__('The selected archive determines where files are saved and who can access them.'));
        $row->addSelect('tawasulReportArchiveID')->fromArray($archives)->required()->placeholder();

    $row = $form->addRow();
        $row->addLabel('accessDate', __('Go Live Date'))->prepend('1. ')->append('<br/>'.__('2. Reports are hidden until date is reached.'));
        $col = $row->addColumn('accessDate')->setClass('inline');
        $col->addDate('accessDate')->addClass('mr-2');
        $col->addTime('accessTime');

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
