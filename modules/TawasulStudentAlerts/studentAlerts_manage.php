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

use TawasulOS\Forms\Form;
use TawasulOS\View\Component;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\UI\Components\Alert;
use TawasulOS\Support\Facades\Access;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\School\YearGroupGateway;
use TawasulOS\Domain\StudentAlerts\AlertGateway;

if (!isActionAccessible($guid, $connection2, '/modules/TawasulStudentAlerts/studentAlerts_manage.php')) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else {
    $action = Access::get('Student Alerts', 'studentAlerts_manage');
    if (empty($action)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    } 

    $page->breadcrumbs->add(__('Manage Student Alerts'));

    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    $tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
    $tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';
    $tawasulYearGroupIDHOY = '';

    $alertGateway = $container->get(AlertGateway::class);
    

    $yearGroup = $container->get(YearGroupGateway::class)->getYearGroupByPerson($session->get('tawasulPersonID'));
    if ($action->allows('Manage Student Alerts_headOfYear') && !empty($yearGroup) && empty($tawasulPersonID) && empty($tawasulFormGroupID) && empty($tawasulYearGroupID)) {
        $tawasulYearGroupIDHOY = $yearGroup['tawasulYearGroupID'];
        $tawasulYearGroupID = $yearGroup['tawasulYearGroupID'];
    }

    // SEARCH
    $form = Form::createSearch();
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $row = $form->addRow();
        $row->addLabel('tawasulPersonID',__('Student'));
        $row->addSelectStudent('tawasulPersonID', $session->get('tawasulSchoolYearID'))->selected($tawasulPersonID)->placeholder();

    $row = $form->addRow();
        $row->addLabel('tawasulFormGroupID',__('Form Group'));
        $row->addSelectFormGroup('tawasulFormGroupID', $session->get('tawasulSchoolYearID'))->selected($tawasulFormGroupID)->placeholder();

    $row = $form->addRow();
        $row->addLabel('tawasulYearGroupID',__('Year Group'));
        $row->addSelectYearGroup('tawasulYearGroupID')->placeholder()->selected($tawasulYearGroupID);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();
    

    // CRITERIA
    $criteria = $alertGateway->newQueryCriteria(true)
        ->sortBy('status')
        ->sortBy('timestampCreated', 'DESC')
        ->filterBy('student', $tawasulPersonID)
        ->filterBy('formGroup', $tawasulFormGroupID)
        ->filterBy('yearGroup', $tawasulYearGroupID)
        ->fromPOST();

    $canManageAlerts = $action->allowsAny('Manage Student Alerts_all', 'Manage Student Alerts_headOfYear');
    if (!$canManageAlerts && empty($tawasulPersonID) && empty($tawasulFormGroupID) && empty($tawasulYearGroupID)) {
        $alerts = $alertGateway->queryAlertsBySchoolYear($criteria, $session->get('tawasulSchoolYearID'), $session->get('tawasulPersonID'));
    } else {
        $alerts = $alertGateway->queryAlertsBySchoolYear($criteria, $session->get('tawasulSchoolYearID'));
    }

    // DATA TABLE
    $table = DataTable::createPaginated('manageAlerts', $criteria);
    $table->setTitle(__('Alerts'));

    $table->addHeaderAction('add', __('Add Global Alert'))
        ->setURL('/modules/TawasulStudentAlerts/studentAlerts_add.php')
        ->addParam('tawasulPersonID', $tawasulPersonID)
        ->addParam('tawasulFormGroupID', $tawasulFormGroupID)
        ->addParam('tawasulYearGroupID', $tawasulYearGroupID)
        ->displayLabel();

    if (Access::allows('Student Alerts', 'report_alertsByClass')) {
        $table->addHeaderAction('addClass', __('Add Class Alert'))
            ->setURL('/modules/TawasulStudentAlerts/studentAlerts_add.php')
            ->addParam('tawasulPersonID', $tawasulPersonID)
            ->addParam('tawasulFormGroupID', $tawasulFormGroupID)
            ->addParam('tawasulYearGroupID', $tawasulYearGroupID)
            ->addParam('source', 'class')
            ->setIcon('add')
            ->displayLabel();
    }

    $table->modifyRows(function($alert, $row) {
        if ($alert['status'] == 'Pending') $row->addClass('warning');
        elseif ($alert['status'] == 'Declined') $row->addClass('dull');
        elseif ($alert['status'] == 'Cancelled') $row->addClass('dull bg-stripe');
        return $row;
    });

    $table->addMetaData('filterOptions', [
        'scope:global'      => __('Scope').': '.__('Global'),
        'scope:class'       => __('Scope').': '.__('Class'),
        'status:approved'   => __('Status').': '.__('Approved'),
        'status:pending'    => __('Status').': '.__('Pending'),
        'status:declined'   => __('Status').': '.__('Declined'),
        'status:cancelled'  => __('Status').': '.__('Cancelled'),
        'context:automatic' => __('Automatic'),
        'context:manual'    => __('Manual'),
    ]);

    $table->addColumn('tag', __('Tag'))
        ->context('primary')
        ->width('8%')
        ->format(function($values) {
            return Component::render(Alert::class, [
                'color'   => $values['levelColor'] ?? $values['color'],
                'colorBG' => $values['levelColorBG'] ?? $values['colorBG'],
                'title' => $values['type'] ?? '',
                'large' => true,
            ] + $values);
        });

    $table->addColumn('student', __('Student'))
        ->description(__('Form Group'))
        ->sortable(['student.surname', 'student.preferredName'])
        ->context('primary')
        ->format(function($values) {
            return Format::nameLinked($values['tawasulPersonID'], '', $values['preferredName'], $values['surname'], 'Student', true, true, ['subpage' => 'Personal']);
        })
        ->formatDetails(function ($values) {
            return Format::small($values['formGroup']);
        });
    
    $table->addColumn('class', __('Class'))
        ->sortable(['courseName', 'className'])
        ->format(function ($values) {
            return !empty($values['tawasulCourseClassID']) 
                ? Format::courseClassName($values['courseName'], $values['className'])
                : '';
        });

    $table->addColumn('type', __('Type'))
        ->description(__('Level'))
        ->formatDetails(function ($values) {
            return Format::small($values['level']);
        });

    $table->addColumn('teacher', __('Created By'))
        ->description(__('Status'))
        ->context('secondary')
        ->sortable(['preferredNameCreator', 'surnameCreator'])
        ->format(function($values) {
            if ($values['context'] == 'Automatic') return Format::tag(__('Automatic'), 'empty');
            return Format::name($values['titleCreator'], $values['preferredNameCreator'], $values['surnameCreator'], 'Staff');
        })
        ->formatDetails(function ($values) {
            return $values['context'] != 'Automatic'
                ? Format::small($values['status'])
                : '';
        });

    $table->addColumn('comment', __('Comment'))
        ->format(function($values) {
            if (empty($values['comment'])) return '';
            return Format::tooltip(
                icon('solid', 'chat-bubble-text', 'text-gray-500 size-5'),
                '<div class="p-4 w-72">'.$values['comment'].'</div>',
                'p-2', 'white');
        });

    $table->addColumn('timestampCreated', __('Date Recorded'))
        ->context('primary')
        ->format(function($alert) {
            return Format::date($alert['timestampCreated']);
        });

    $table->addActionColumn()
        ->addParam('tawasulPersonID', $tawasulPersonID)
        ->addParam('tawasulFormGroupID', $tawasulFormGroupID)
        ->addParam('tawasulYearGroupID', $tawasulYearGroupID)
        ->addParam('tawasulAlertID')
        ->format(function ($alert, $actions) use ($action, $session, $tawasulYearGroupIDHOY) {
            $accessAll = $action->allows('Manage Student Alerts_all');
            $accessHOY = $action->allows('Manage Student Alerts_headOfYear') && $alert['tawasulYearGroupID'] == $tawasulYearGroupIDHOY;
            $accessCreator = $action->allows('Manage Student Alerts_my') && $alert['tawasulPersonIDCreated'] == $session->get('tawasulPersonID');

            if (($accessAll || $accessHOY) && $alert['status'] == 'Pending') {
                $actions->addAction('approve', __('Approve'))
                    ->setURL('/modules/TawasulStudentAlerts/studentAlerts_manage_status.php')
                    ->addParam('status', 'Approved')
                    ->setIcon('accept');

                $actions->addAction('decline', __('Decline'))
                    ->setURL('/modules/TawasulStudentAlerts/studentAlerts_manage_status.php')
                    ->addParam('status', 'Declined')
                    ->setIcon('reject');
            }
            
            if ($alert['status'] != 'Pending') {
                $actions->addAction('view', __('View'))
                    ->setURL('/modules/TawasulStudentAlerts/studentAlerts_manage_view.php');
            }

            if ($alert['context'] == 'Automatic') return;
            
            if ($accessAll || $accessHOY || ($accessCreator && $alert['status'] == 'Pending')) {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulStudentAlerts/studentAlerts_edit.php');
            }

            if ((($accessAll || $accessHOY) && $alert['status'] == 'Approved') || ($accessCreator && $alert['status'] == 'Pending') || ($accessCreator && $alert['status'] == 'Approved' && !empty($alert['tawasulCourseClassID']))) {
                $actions->addAction('cancel', __('Cancel'))
                    ->setURL('/modules/TawasulStudentAlerts/studentAlerts_manage_status.php')
                    ->addParam('status', 'Cancelled')
                    ->setIcon('reject');
            }

            if (($accessAll || $accessHOY) && ($alert['status'] == 'Declined' || $alert['status'] == 'Cancelled')) {
                $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/TawasulStudentAlerts/studentAlerts_delete.php');
            }
        });

    echo $table->render($alerts);

}
