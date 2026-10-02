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
use TawasulOS\Domain\School\GradeScaleGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/gradeScales_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('Manage Grade Scales'), 'gradeScales_manage.php')
        ->add(__('Edit Grade Scale'));

    //Check if tawasulScaleID specified
    $tawasulScaleID = (isset($_GET['tawasulScaleID']))? $_GET['tawasulScaleID'] : null;
    if (empty($tawasulScaleID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        
            $data = array('tawasulScaleID' => $tawasulScaleID);
            $sql = 'SELECT * FROM tawasulScale WHERE tawasulScaleID=:tawasulScaleID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $form = Form::create('gradeScaleEdit', $session->get('absoluteURL').'/modules/'.$session->get('module').'/gradeScales_manage_editProcess.php?tawasulScaleID='.$tawasulScaleID);

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulScaleID', $tawasulScaleID);

            $row = $form->addRow();
                $row->addLabel('name', __('Name'))->description(__('Must be unique.'));
                $row->addTextField('name')->required()->maxLength(40);

            $row = $form->addRow();
                $row->addLabel('nameShort', __('Short Name'))->description(__('Must be unique.'));
                $row->addTextField('nameShort')->required()->maxLength(5);

            $row = $form->addRow();
                $row->addLabel('usage', __('Usage'))->description(__('Brief description of how scale is used.'));
                $row->addTextField('usage')->required()->maxLength(50);

            $row = $form->addRow();
                $row->addLabel('active', __('Active'));
                $row->addYesNo('active')->required();

            $row = $form->addRow();
                $row->addLabel('numeric', __('Numeric'))->description(__('Does this scale use only numeric grades? Note, grade "Incomplete" is exempt.'));
                $row->addYesNo('numeric')->required();

            $data = array('tawasulScaleID' => $tawasulScaleID);
            $sql = "SELECT sequenceNumber as value, tawasulScaleGrade.value as name FROM tawasulScaleGrade WHERE tawasulScaleID=:tawasulScaleID ORDER BY sequenceNumber";

            $row = $form->addRow();
                $row->addLabel('lowestAcceptable', __('Lowest Acceptable'))->description(__('This is the lowest grade a student can get without being unsatisfactory.'));
                $row->addSelect('lowestAcceptable')->fromQuery($pdo, $sql, $data)->placeholder();

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            $form->loadAllValuesFrom($values);

            echo $form->getOutput();

            echo '<h2>';
            echo __('Edit Grades');
            echo '</h2>';

            $gradeScaleGateway = $container->get(GradeScaleGateway::class);

            // QUERY
            $criteria = $gradeScaleGateway->newQueryCriteria(true)
                ->sortBy('sequenceNumber')
                ->fromPOST();

            $grades = $gradeScaleGateway->queryGradeScaleGrades($criteria, $tawasulScaleID);

            // DATA TABLE
            $table = DataTable::createPaginated('gradeScaleManage', $criteria);

            $table->addHeaderAction('add', __('Add'))
                ->setURL('/modules/TawasulSchoolAdmin/gradeScales_manage_edit_grade_add.php')
                ->addParam('tawasulScaleID', $tawasulScaleID)
                ->displayLabel();

            $table->addColumn('value', __('Value'));
            $table->addColumn('descriptor', __('Descriptor'));
            $table->addColumn('sequenceNumber', __('Sequence Number'));
            $table->addColumn('isDefault', __('Is Default?'))->format(Format::using('yesNo', ['isDefault']));
                
            // ACTIONS
            $table->addActionColumn()
                ->addParam('tawasulScaleID')
                ->addParam('tawasulScaleGradeID')
                ->format(function ($grade, $actions) {
                    $actions->addAction('edit', __('Edit'))
                            ->setURL('/modules/TawasulSchoolAdmin/gradeScales_manage_edit_grade_edit.php');

                    $actions->addAction('delete', __('Delete'))
                            ->setURL('/modules/TawasulSchoolAdmin/gradeScales_manage_edit_grade_delete.php');
                });

            echo $table->render($grades);
        }
    }
}
