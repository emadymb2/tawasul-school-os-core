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
use TawasulOS\Tables\DataTable;
use TawasulOS\Services\Format;
use TawasulOS\Domain\Forms\FormSubmissionGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/forms_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Other Forms'));
    
    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
    $tawasulAdmissionsAccountID = $_GET['tawasulAdmissionsAccountID'] ?? '';
    $search = $_GET['search'] ?? '';

    $page->navigator->addSchoolYearNavigation($tawasulSchoolYearID);

    // SEARCH
    $form = Form::create('searchForm', $session->get('absoluteURL').'/index.php','get');
    $form->setTitle(__('Search'));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/forms_manage.php');
    $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

    $row = $form->addRow();
        $row->addLabel('search', __('Search For'))->description();
        $row->addTextField('search')->setValue($search);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Search'), ['tawasulSchoolYearID']);

    echo $form->getOutput();

    // QUERY
    $formSubmissionGateway = $container->get(FormSubmissionGateway::class);
    $criteria = $formSubmissionGateway->newQueryCriteria(true)
        ->sortBy('timestampCreated', 'DESC')
        ->filterBy('admissionsAccount', $tawasulAdmissionsAccountID)
        ->fromPOST();

    $submissions = $formSubmissionGateway->queryFormsBySchoolYear($criteria, $tawasulSchoolYearID);

    // DATA TABLE
    $table = DataTable::createPaginated('admissions', $criteria);
    $table->setTitle(__('Forms'));

    $table->addColumn('student', __('Student'));
    $table->addColumn('formName', __('Form Name'));
    $table->addColumn('timestampCreated', __('Created'))->format(Format::using('relativeTime', 'timestampCreated'));

    if (isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/formBuilder.php')) {
        $table->addHeaderAction('forms', __('Form Builder'))
            ->setURL('/modules/TawasulSystemAdmin/formBuilder.php')
            ->setIcon('markbook')
            ->displayLabel();
    }
    
    $table->modifyRows(function ($values, $row) {
        if ($values['status'] == 'Incomplete') $row->addClass('warning');
        return $row;
    });
    
    $table->addActionColumn()
        ->format(function ($values, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/TawasulAdmissions/applications_manage_edit.php');

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/TawasulAdmissions/applications_manage_delete.php');
        });

    echo $table->render($submissions);
}
