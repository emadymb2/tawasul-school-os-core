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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\StudentAlerts\AlertGateway;

if (!isActionAccessible($guid, $connection2, '/modules/TawasulStudentAlerts/studentAlerts_manage_view.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    
    $page->breadcrumbs
        ->add(__('Manage Alerts'), 'studentAlerts_manage.php')
        ->add(__('View Alert'));

    $tawasulAlertID = $_GET['tawasulAlertID'] ?? '';
    $params = [
        'tawasulPersonID'      => $_REQUEST['tawasulPersonID'] ?? '',
        'tawasulFormGroupID'   => $_REQUEST['tawasulFormGroupID'] ?? '',
        'tawasulYearGroupID'   => $_REQUEST['tawasulYearGroupID'] ?? '',
        'tawasulCourseClassID' => $_REQUEST['tawasulCourseClassID'] ?? '',
        'source'              => $_REQUEST['source'] ?? '',
    ];

    $alertGateway = $container->get(AlertGateway::class);

    if (empty($tawasulAlertID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $alert = $alertGateway->getByID($tawasulAlertID);
    if (empty($alert)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    if (!empty($params['tawasulPersonID']) || !empty($params['tawasulFormGroupID']) || !empty($params['tawasulYearGroupID'])) {
        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulStudentAlerts', 'studentAlerts_manage')->withQueryParams($params));
    }

    $form = Form::create('viewAlert', '');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addRow()->addHeading('Alert', __('Alert'));

    $row = $form->addRow();
        $row->addLabel('tawasulPersonID', __('Student'));
        $row->addSelectStudent('tawasulPersonID', $session->get('tawasulSchoolYearID'))->placeholder()->selected($alert['tawasulPersonID'])->readonly();

    $row = $form->addRow();
        $row->addLabel('type', __('Type'));
        $row->addTextField('type')
            ->setValue($alert['type'])
            ->readonly();
    
    if (!empty($alert['level'])) {
        $row = $form->addRow();
            $row->addLabel('level', __('Level'));
            $row->addTextField('level')
                ->setValue($alert['level'])
                ->readonly();
    }

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addTextField('status')
            ->setValue($alert['status'])
            ->readonly();

    $row = $form->addRow();
            $col = $row->addColumn();
            $col->addLabel('comment', __('Comment'));
            $col->addTextArea('comment')
                ->setValue($alert['comment'])
                ->readonly()
                ->setRows(3);

    if ($alert['status'] != 'Pending') {
        $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('notes', __('Notes'));
        $col->addTextArea('notes')
            ->setValue($alert['notesStatus'])
            ->readonly()
            ->setRows(3);
    }
                
    echo $form->getOutput();
}

