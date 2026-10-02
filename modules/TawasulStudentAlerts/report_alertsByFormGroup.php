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

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\View\Component;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\UI\Components\Alert;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\StudentAlerts\AlertGateway;
use TawasulOS\Domain\FormGroups\FormGroupGateway;

if (!isActionAccessible($guid, $connection2, '/modules/TawasulStudentAlerts/report_alertsByFormGroup.php')) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Student Alerts by Form Group'));

    $tawasulFormGroupID = $_REQUEST['tawasulFormGroupID'] ?? '';
    $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');

    $alertGateway = $container->get(AlertGateway::class);
    $alertManager = $container->get(Alert::class);

    $formGroup = $container->get(FormGroupGateway::class)->selectFormGroupsByTutor($session->get('tawasulPersonID'))->fetch();
    if (!empty($formGroup) && empty($tawasulFormGroupID)) {
        $tawasulFormGroupID = $formGroup['tawasulFormGroupID'];
    }

    // SEARCH
    $form = Form::createSearch();
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $row = $form->addRow();
        $row->addLabel('tawasulFormGroupID',__('Form Group'));
        $row->addSelectFormGroup('tawasulFormGroupID', $tawasulSchoolYearID)->selected($tawasulFormGroupID)->placeholder();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    if (empty($tawasulFormGroupID)) return;

        // CRITERIA
    $criteria = $alertGateway->newQueryCriteria(true)
        ->sortBy(['surname', 'preferredName'])
        ->filterBy('formGroup', $tawasulFormGroupID)
        ->fromPOST();

    $students = $alertGateway->queryStudentsWithAlertsByFormGroup($criteria, $tawasulFormGroupID);
    $students->transform(function (&$student) use ($alertGateway, $alertManager, $tawasulSchoolYearID) {
        $student['alerts'] = $alertManager->getAlertsByStudent($student['tawasulPersonID']);
        $student['classAlerts'] = $alertGateway->selectClassAlertsByStudent($tawasulSchoolYearID, $student['tawasulPersonID'])->fetchGrouped();
    });

    // DATA TABLE
    $table = DataTable::createPaginated('manageAlerts', $criteria);
    $table->setTitle(__('Alerts'));

    $table->addHeaderAction('add', __('Add Global Alert'))
        ->setURL('/modules/TawasulStudentAlerts/studentAlerts_add.php')
        ->addParam('tawasulFormGroupID', $tawasulFormGroupID)
        ->addParam('source', 'formGroup')
        ->displayLabel();

    $table->modifyRows(function($alert, $row) {
        if ($alert['status'] == 'Pending') $row->addClass('warning');
        elseif ($alert['status'] == 'Declined') $row->addClass('dull');
        return $row;
    });

    $table->addColumn('image_240', __('Photo'))
        ->context('primary')
        ->width('7%')
        ->notSortable()
        ->format(Format::using('userPhoto', ['image_240', 'xs']));

    $table->addColumn('student', __('Student'))
        ->description(__('Form Group'))
        ->sortable(['surname', 'preferredName'])
        ->context('primary')
        ->format(function($student) {
            return Format::nameLinked($student['tawasulPersonID'], '', $student['preferredName'], $student['surname'], 'Student', true, true, ['subpage' => 'Personal']);
        })
        ->formatDetails(function ($student) {
            return Format::small($student['formGroup']);
        });

    $alertTypes = $alertManager->getActiveAlertTypes();

    foreach ($alertTypes as $alertType) {
        $table->addColumn($alertType['tawasulAlertTypeID'], __($alertType['name']))
            ->notSortable()
            ->width('10%')
            ->format(function($student) use ($alertType) {
                $alert = $student['alerts'][$alertType['name']] ?? [];
                $classAlerts = $student['classAlerts'][$alertType['name']] ?? [];
                $output = '';

                if (!empty($alert)) {
                    $output .= Component::render(Alert::class, [
                        'color'   => $alert['levelColor'] ?? $alert['color'],
                        'colorBG' => $alert['levelColorBG'] ?? $alert['colorBG'],
                        'title' => $alert['type'] ?? '',
                        'large' => true,
                    ] + $alert);
                }

                if (!empty($classAlerts)) {
                    $topAlert = current($classAlerts);
                    $output .= Component::render(Alert::class, [
                        'link'    => Url::fromModuleRoute('TawasulStudentAlerts', 'studentAlerts_manage')->withQueryParams(['tawasulPersonID' => $student['tawasulPersonID']]),
                        'color'   => $topAlert['levelColor'] ?? $topAlert['color'],
                        'colorBG' => $topAlert['levelColorBG'] ?? $topAlert['colorBG'],
                        'title'   => __('Class').' '.$topAlert['name'],
                        'tag'     => count($classAlerts),
                        'medium'  => true,
                    ]);
                }

                return $output;
            });
    }

    $table->addActionColumn()
        ->addParam('tawasulFormGroupID', $tawasulFormGroupID)
        ->addParam('tawasulPersonID')
        ->addParam('source', 'formGroup')
        ->format(function ($alert, $actions)  {
            $actions->addAction('add', __('Add'))
                ->setURL('/modules/TawasulStudentAlerts/studentAlerts_add.php');

            $actions->addAction('view', __('View'))
                ->setURL('/modules/TawasulStudentAlerts/studentAlerts_manage.php');
        });

    echo $table->render($students);

}
