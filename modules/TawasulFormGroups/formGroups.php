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

use TawasulOS\Tables\DataTable;
use TawasulOS\Services\Format;
use TawasulOS\Domain\FormGroups\FormGroupGateway;
use TawasulOS\Domain\School\YearGroupGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulFormGroups/formGroups.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Proceed!
        $page->breadcrumbs->add(__('View Form Groups'));

        $formGroupGateway = $container->get(FormGroupGateway::class);
        if ($highestAction == "View Form Groups_all") {
            $formGroups = $formGroupGateway->selectFormGroupsBySchoolYear($session->get('tawasulSchoolYearID'));
        }
        else {
            $formGroups = $formGroupGateway->selectFormGroupsBySchoolYearMyChildren($session->get('tawasulSchoolYearID'), $session->get('tawasulPersonID'));
        }

        $formatTutorsList = function($row) use ($formGroupGateway) {
            $tutors = $formGroupGateway->selectTutorsByFormGroup($row['tawasulFormGroupID'])->fetchAll();
            if (count($tutors) > 1) $tutors[0]['surname'] .= ' ('.__('Main Tutor').')';

            return Format::nameList($tutors, 'Staff', false, true);
        };

        $table = DataTable::create('formGroups');
        $table->setTitle(__('Form Groups'));

        $table->addColumn('name', __('Name'));
        $table->addColumn('tutors', __('Form Tutors'))->format($formatTutorsList);
        $table->addColumn('space', __('Room'));
        if ($session->get('tawasulRoleIDCurrentCategory') == "Staff") {
            $table->addColumn('students', __('Students'));
        }

        $actions = $table->addActionColumn()->addParam('tawasulFormGroupID');
        $actions->addAction('view', __('View'))
                ->setURL('/modules/TawasulFormGroups/formGroups_details.php');

        echo $table->render($formGroups->toDataSet());

        //Display year group table for staff
        $roleCategory = $session->get('tawasulRoleIDCurrentCategory');
        if ($roleCategory == 'Staff') {
            $yearGroupGateway = $container->get(YearGroupGateway::class);

            $criteria = $yearGroupGateway->newQueryCriteria(true)
                ->sortBy(['tawasulYearGroup.sequenceNumber'])
                ->fromPOST('clinics');

            $yearGroups = $yearGroupGateway->queryYearGroups($criteria);

            $table = DataTable::create('yearGroups');
            $table->setTitle(__('Year Group Summary'));

            $table->addColumn('name', __('Name'));
            $table->addColumn('tawasulPersonIDHOY', __('Head of Year'))
                ->format(function ($values) {
                    if (!empty($values['preferredName']) && !empty($values['surname'])) {
                        return Format::name('', $values['preferredName'], $values['surname'], 'Staff', false, true);
                    }
                });
            $table->addColumn('students', __('Students'))
                ->format(function ($values) use ($yearGroupGateway, $session) {
                    return $yearGroupGateway->studentCountByYearGroup($values['tawasulYearGroupID'], $session->get('tawasulSchoolYearID'));
                });

            echo $table->render($yearGroups);
        }
    }
}
