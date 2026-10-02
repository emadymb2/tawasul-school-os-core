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
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\System\AlertLevelGateway;
use TawasulOS\Domain\IndividualNeeds\INGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/in_summary.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Individual Needs Summary'));

    $tawasulINDescriptorID = null;
    if (isset($_GET['tawasulINDescriptorID'])) {
        $tawasulINDescriptorID = $_GET['tawasulINDescriptorID'] ?? '';
    }
    $tawasulAlertLevelID = null;
    if (isset($_GET['tawasulAlertLevelID'])) {
        $tawasulAlertLevelID = $_GET['tawasulAlertLevelID'] ?? '';
    }
    $tawasulFormGroupID = null;
    if (isset($_GET['tawasulFormGroupID'])) {
        $tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
    }
    $tawasulYearGroupID = null;
    if (isset($_GET['tawasulYearGroupID'])) {
        $tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';
    }

    echo '<h3>';
    echo __('Filter');
    echo '</h3>';

    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder w-full');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('q', '/modules/TawasulIndividualNeeds/in_summary.php');
    $form->addHiddenValue('address', $session->get('address'));

    //SELECT FROM ARRAY
    $result = $container->get(INGateway::class)->selectINDescriptor();

    $row = $form->addRow();
    	$row->addLabel('tawasulINDescriptorID', __('Descriptor'));
        $row->addSelect('tawasulINDescriptorID')->fromResults($result)->selected($tawasulINDescriptorID)->placeholder();

    $result = $container->get(AlertLevelGateway::class)->selectAlertLevels();

    $row = $form->addRow();
        $row->addLabel('tawasulAlertLevelID', __('Alert Level'));
        $row->addSelect('tawasulAlertLevelID')->fromResults($result)->selected($tawasulAlertLevelID)->placeholder();

    $row = $form->addRow();
        $row->addLabel('tawasulFormGroupID', __('Form Group'));
        $row->addSelectFormGroup('tawasulFormGroupID', $session->get('tawasulSchoolYearID'))->selected($tawasulFormGroupID)->placeholder();

    $row = $form->addRow();
        $row->addLabel('tawasulYearGroupID', __('Year Group'));
        $row->addSelectYearGroup('tawasulYearGroupID')->selected($tawasulYearGroupID)->placeholder();

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    echo '<h3>';
    echo __('Students With Records');
    echo '</h3>';
    echo '<p>';
    echo __('Students only show up in this list if they have an Individual Needs record with descriptors set. If a student does not show up here, check in Individual Needs Records.');
    echo '</p>';

    $individualNeedsGateway = $container->get(INGateway::class);

    $criteria = $individualNeedsGateway->newQueryCriteria(true)
        ->sortBy(['surname', 'preferredName'])
        ->filterBy('descriptor', $tawasulINDescriptorID)
        ->filterBy('alert', $tawasulAlertLevelID)
        ->filterBy('formGroup', $tawasulFormGroupID)
        ->filterBy('yearGroup', $tawasulYearGroupID)
        ->fromPOST();

    $individualNeeds = $individualNeedsGateway->queryINBySchoolYear($criteria, $session->get('tawasulSchoolYearID'));

    // DATA TABLE
    $table = DataTable::createPaginated('inSummary', $criteria);

    $table->modifyRows(function($student, $row) {
        if ($student['status'] != 'Full') $row->addClass('error');
        if (!($student['dateStart'] == '' || $student['dateStart'] <= date('Y-m-d'))) $row->addClass('error');
        if (!($student['dateEnd'] == '' || $student['dateEnd'] >= date('Y-m-d'))) $row->addClass('error');
        return $row;
    });

    $table->addMetaData('filterOptions', [
        'alert:003'    => __('Alert Level').': '.__('Low'),
        'alert:002' => __('Alert Level').': '.__('Medium'),
        'alert:001'   => __('Alert Level').': '.__('High'),
    ]);

    // COLUMNS
    $table->addColumn('student', __('Student'))
        ->sortable(['surname', 'preferredName'])
        ->format(Format::using('nameLinked', ['tawasulPersonID', '', 'preferredName', 'surname', 'Student', true, false, ['subpage' => 'Individual Needs']]));
    $table->addColumn('yearGroup', __('Year Group'));
    $table->addColumn('formGroup', __('Form Group'));

    $table->addActionColumn()
        ->addParam('tawasulPersonID')
        ->addParam('tawasulINDescriptorID', $tawasulINDescriptorID)
        ->addParam('tawasulAlertLevelID', $tawasulAlertLevelID)
        ->addParam('tawasulFormGroupID', $tawasulFormGroupID)
        ->addParam('tawasulYearGroupID', $tawasulYearGroupID)
        ->addParam('source', 'summary')
        ->format(function ($row, $actions) {
            $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulIndividualNeeds/in_edit.php');
        });

    echo $table->render($individualNeeds);
}
