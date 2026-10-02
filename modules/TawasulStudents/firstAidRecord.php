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
use TawasulOS\Tables\DataTable;
use TawasulOS\Services\Format;
use TawasulOS\Domain\Students\FirstAidGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/firstAidRecord.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get action with highest precedence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    }

    $page->breadcrumbs->add(__('First Aid Records'));

    $tawasulPersonID = $_GET['tawasulPersonID'] ?? null;
    $tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? null;
    $tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? null;
    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');

    $page->navigator->addSchoolYearNavigation($tawasulSchoolYearID);

    $form = Form::create('action', $session->get('absoluteURL').'/index.php', 'get');
    $form->setTitle(__('Filter'));

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', "/modules/".$session->get('module')."/firstAidRecord.php");
    $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

    $row = $form->addRow();
        $row->addLabel('tawasulPersonID', __('Student'));
        $row->addSelectStudent('tawasulPersonID', $tawasulSchoolYearID)->placeholder()->selected($tawasulPersonID);

    $row = $form->addRow();
        $row->addLabel('tawasulFormGroupID', __('Form Group'));
        $row->addSelectFormGroup('tawasulFormGroupID', $tawasulSchoolYearID)->selected($tawasulFormGroupID);

    $row = $form->addRow();
        $row->addLabel('tawasulYearGroupID', __('Year Group'));
        $row->addSelectYearGroup('tawasulYearGroupID')->selected($tawasulYearGroupID);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session, __('Clear Filters'), ['tawasulSchoolYearID']);

    echo $form->getOutput();

    $firstAidGateway = $container->get(FirstAidGateway::class);

    $criteria = $firstAidGateway->newQueryCriteria(true)
        ->sortBy(['date', 'timeIn'], 'DESC')
        ->filterBy('student', $tawasulPersonID)
        ->filterBy('formGroup', $tawasulFormGroupID)
        ->filterBy('yearGroup', $tawasulYearGroupID)
        ->fromPOST();

    $firstAidRecords = $firstAidGateway->queryFirstAidBySchoolYear($criteria, $tawasulSchoolYearID);

    // DATA TABLE
    $table = DataTable::createPaginated('firstAidRecords', $criteria);
    $table->setTitle(__('First Aid Records'));

    if ($highestAction == 'First Aid Record_editAll') {
        $table->addHeaderAction('add', __('Add'))
            ->setURL('/modules/TawasulStudents/firstAidRecord_add.php')
            ->addParam('tawasulFormGroupID', $tawasulFormGroupID)
            ->addParam('tawasulYearGroupID', $tawasulYearGroupID)
            ->displayLabel();
    }

    // COLUMNS
    $table->addExpandableColumn('details')->format(function($person) use ($firstAidGateway) {
        $output = '';
        if ($person['description'] != '') $output .= '<b>'.__('Description').'</b><br/>'.nl2br($person['description']).'<br/><br/>';
        if ($person['actionTaken'] != '') $output .= '<b>'.__('Action Taken').'</b><br/>'.nl2br($person['actionTaken']).'<br/><br/>';
        if ($person['followUp'] != '') $output .= '<b>'.__("Follow Up by {name} at {date}", ['name' => Format::name('', $person['preferredNameFirstAider'], $person['surnameFirstAider']), 'date' => Format::dateTimeReadable($person['timestamp'])]).'</b><br/>'.nl2br($person['followUp']).'<br/><br/>';
        $resultLog = $firstAidGateway->queryFollowUpByFirstAidID($person['tawasulFirstAidID']);
        foreach ($resultLog AS $rowLog) {
            $output .= '<b>'.__("Follow Up by {name} at {date}", ['name' => Format::name('', $rowLog['preferredName'], $rowLog['surname']), 'date' => Format::dateTimeReadable($rowLog['timestamp'])]).'</b><br/>'.nl2br($rowLog['followUp']).'<br/><br/>';
        }

        return $output;
    });

    $table->addColumn('patient', __('Student'))
        ->description(__('Form Group'))
        ->sortable(['surnamePatient', 'preferredNamePatient'])
        ->format(function($person) use ($session) {
            $url = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID='.$person['tawasulPersonIDPatient'].'&subpage=First Aid&search=&allStudents=&sort=surname,preferredName';
            return Format::link($url, Format::name('', $person['preferredNamePatient'], $person['surnamePatient'], 'Student', true))
                    .'<br/><small><i>'.$person['formGroup'].'</i></small>';
        });

    $table->addColumn('firstAider', __('First Aider'))
        ->sortable(['surnameFirstAider', 'preferredNameFirstAider'])
        ->format(Format::using('name', ['', 'preferredNameFirstAider', 'surnameFirstAider', 'Staff', false, true]));

    $table->addColumn('date', __('Date'))
        ->format(Format::using('date', ['date']));

    $table->addColumn('time', __('Time'))
        ->sortable(['timeIn', 'timeOut'])
        ->format(Format::using('timeRange', ['timeIn', 'timeOut']));

    $table->addActionColumn()
        ->addParam('tawasulPersonID', $tawasulPersonID)
        ->addParam('tawasulFormGroupID', $tawasulFormGroupID)
        ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
        ->addParam('tawasulYearGroupID', $tawasulYearGroupID)
        ->addParam('tawasulFirstAidID')
        ->format(function ($person, $actions) use ($highestAction) {
            if ($highestAction == 'First Aid Record_editAll') {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulStudents/firstAidRecord_edit.php');

                $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/TawasulStudents/firstAidRecord_delete.php');
            } elseif ($highestAction == 'First Aid Record_viewOnlyAddNotes') {
                $actions->addAction('view', __('View'))
                    ->setURL('/modules/TawasulStudents/firstAidRecord_edit.php');
            }
        });

    echo $table->render($firstAidRecords);

}
