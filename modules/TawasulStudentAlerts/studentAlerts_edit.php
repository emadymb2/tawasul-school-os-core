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

use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Forms\Form;
use TawasulOS\Http\Url;
use TawasulOS\Services\Format;
use TawasulOS\Support\Facades\Access;
use TawasulOS\Domain\StudentAlerts\AlertGateway;
use TawasulOS\Domain\StudentAlerts\AlertTypeGateway;
use TawasulOS\Domain\Timetable\CourseClassGateway;

if (!isActionAccessible($guid, $connection2, '/modules/TawasulStudentAlerts/studentAlerts_edit.php')) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $action = Access::get('Student Alerts', 'studentAlerts_edit');
    if (empty($action)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    }

    $page->breadcrumbs
        ->add(__('Manage Alerts'), 'studentAlerts_manage.php')
        ->add(__('Edit'));

    $alertGateway = $container->get(AlertGateway::class);
    $alertTypeGateway = $container->get(AlertTypeGateway::class);

    
    $tawasulAlertID = $_GET['tawasulAlertID'] ?? '';
    $params = [
        'tawasulPersonID'    => $_REQUEST['tawasulPersonID'] ?? '',
        'tawasulFormGroupID' => $_REQUEST['tawasulFormGroupID'] ?? '',
        'tawasulYearGroupID' => $_REQUEST['tawasulYearGroupID'] ?? '',
    ];

    if (empty($tawasulAlertID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $values = $alertGateway->getByID($tawasulAlertID);
    
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $canEditAlert = $action->allowsAny('Manage Student Alerts_all', 'Manage Student Alerts_headOfYear') || $alertGateway->getAlertEditAccess($tawasulAlertID, $session->get('tawasulPersonID'));
    
    if (!$canEditAlert) {
        $page->addError(__('You do not have edit access to this record.'));
        return;
    }

    if (!empty($params['tawasulPersonID']) || !empty($params['tawasulFormGroupID']) || !empty($params['tawasulYearGroupID'])) {
        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulStudentAlerts', 'studentAlerts_manage')->withQueryParams($params));
    }

    $form = Form::create('editAlert', $session->get('absoluteURL').'/modules/TawasulStudentAlerts/studentAlerts_editProcess.php?tawasulAlertID='.$tawasulAlertID.'&tawasulPersonID='.$params['tawasulPersonID'].'&tawasulFormGroupID='.$params['tawasulFormGroupID'].'&tawasulYearGroupID='.$params['tawasulYearGroupID']);
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulAlertID', $tawasulAlertID);

    $form->addRow()->addHeading('Edit Alert', __('Edit Alert'));

    $row = $form->addRow();
        $row->addLabel('tawasulPersonID', __('Student'));
        $row->addSelectStudent('tawasulPersonID', $session->get('tawasulSchoolYearID'))->placeholder()->selected($values['tawasulPersonID'])->readonly();
        $form->addHiddenValue('tawasulPersonID', $values['tawasulPersonID']);

    if (!empty($values['tawasulCourseClassID'])) {
        $class = $container->get(CourseClassGateway::class)->getCourseClassByID($values['tawasulCourseClassID']);
        $row = $form->addRow();
            $row->addLabel('tawasulCourseClassID', __('Class'));
            $row->addTextField('tawasulCourseClassID')->readOnly()->setValue(Format::courseClassName($class['courseNameShort'] ?? '', $class['nameShort'] ?? ''));
    }

    $alertType = $alertTypeGateway->getByID($values['tawasulAlertTypeID']);
    $row = $form->addRow();
        $row->addLabel('typeLabel', __('Type'));
        $row->addTextField('typeLabel')->readonly()->setValue($alertType['name']);

    if ($action->allowsAny('Manage Student Alerts_all', 'Manage Student Alerts_headOfYear') && $values['status'] != 'Pending') {
        $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')->fromArray(['Pending' => __('Pending'), 'Approved' => __('Approved'), 'Declined' => __('Declined')])->required();
    } else {
        $row = $form->addRow();
        $row->addLabel('statusLabel', __('Status'));
        $row->addTextField('statusLabel')->readOnly()->setValue($values['status']);
        $form->addHiddenValue('status', $values['status']);
    }

    if ($alertType['useLevels'] == 'Y') {
        $row = $form->addRow();
            $row->addLabel('level', __('Level'));
            $row->addSelect('level')
                ->fromArray(['High' => __('High'), 'Medium' => __('Medium'), 'Low' => __('Low')])->required();
    }

    // $row = $form->addRow();
    //     $row->addLabel('dateStart', __('Start Date'))->description(__('If the alert is for a specified period'));
    //     $row->addDate('dateStart');

    // $row = $form->addRow();
    //     $row->addLabel('dateEnd', __('End Date'))->description(__('If the alert is for a specified period')); 
    //     $row->addDate('dateEnd');

    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('comment', __('Comment'));
        $col->addTextArea('comment')->setRows(5);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    $form->loadAllValuesFrom($values);

    echo $form->getOutput();
}
