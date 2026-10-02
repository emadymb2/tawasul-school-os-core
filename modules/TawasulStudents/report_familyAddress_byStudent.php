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

use TawasulOS\View\View;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Tables\Prefab\ReportTable;
use TawasulOS\Domain\User\FamilyGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/report_familyAddress_byStudent.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $viewMode = $_REQUEST['format'] ?? '';
    $choices = $_POST['tawasulPersonID'] ?? [];
    $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');

    if (isset($_GET['tawasulPersonIDList'])) {
        $choices = explode(',', $_GET['tawasulPersonIDList']);
    } else {
        $_GET['tawasulPersonIDList'] = implode(',', $choices);
    }

    if (empty($viewMode)) {
        $page->breadcrumbs->add(__('Family Address by Student'));

        $form = Form::create('action', $session->get('absoluteURL')."/index.php?q=/modules/TawasulStudents/report_familyAddress_byStudent.php");
        $form->setTitle(__('Choose Students'));
        $form->setFactory(DatabaseFormFactory::create($pdo));
        $form->setClass('noIntBorder w-full');

        $row = $form->addRow();
            $row->addLabel('tawasulPersonID', __('Students'));
            $row->addSelectStudent('tawasulPersonID', $tawasulSchoolYearID, array("allStudents" => false, "byName" => true, "byForm" => true))
                ->isRequired()
                ->selectMultiple()
                ->selected($choices);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSearchSubmit($session);

        echo $form->getOutput();
    }

    if (empty($choices)) {
        return;
    }

    $familyGateway = $container->get(FamilyGateway::class);

    // CRITERIA
    $criteria = $familyGateway->newQueryCriteria(true)
        ->sortBy(['tawasulFamily.name'])
        ->pageSize(!empty($viewMode) ? 0 : 50)
        ->fromPOST();

    $families = $familyGateway->queryFamiliesByStudent($criteria, $choices);

    // Join a set of student data per family
    $familyIDs = $families->getColumn('tawasulFamilyID');
    $childrenData = $familyGateway->selectChildrenByFamily($familyIDs)->fetchGrouped();
    $families->joinColumn('tawasulFamilyID', 'children', $childrenData);

    // DATA TABLE
    $table = ReportTable::createPaginated('familyAddressByStudent', $criteria)->setViewMode($viewMode, $session);
    $table->setTitle(__('Family Address by Student'));
    $table->setDescription(__('This report attempts to print the family address(es) based on parents who are labelled as Contact Priority 1.'));

    $table->addMetaData('post', ['tawasulPersonID' => $choices]);
    
    $table->addColumn('name', __('Family'));
    $table->addColumn('students', __('Selected Students'))
        ->notSortable()
        ->format(function ($family) {
            $students = array_filter($family['children'], function ($child) use ($family) {
                return stripos($family['tawasulPersonIDList'], $child['tawasulPersonID']) !== false;
            });
            return Format::nameList($students);
        });

    $view = new View($container->get('twig'));

    $table->addColumn('homeAddress', __('Home Address'))
        ->width('50%')
        ->sortable(['homeAddressCountry', 'homeAddressDistrict', 'homeAddress'])
        ->format(function ($family) use ($view) {
            return $view->fetchFromTemplate(
                'formats/familyAddresses.twig.html',
                ['families' => [$family], 'includeAddressName' => true]
            );
        });

    echo $table->render($families);
}
