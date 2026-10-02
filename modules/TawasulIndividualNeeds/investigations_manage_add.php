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

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Forms\DatabaseFormFactory;

if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/investigations_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get action with highest precedence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        $page->breadcrumbs
            ->add(__('Manage Investigations'), 'investigations_manage.php')
            ->add(__('Add'));

        $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
        $tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
        $tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';

        $editLink = '';
        $editID = '';
        if (isset($_GET['editID'])) {
            $editID = $_GET['editID'] ?? '';
            $editLink = $session->get('absoluteURL')."/index.php?q=/modules/TawasulIndividualNeeds/investigations_manage_edit.php&tawasulINInvestigationID=$editID&tawasulPersonID=$tawasulPersonID&tawasulFormGroupID=$tawasulFormGroupID&tawasulYearGroupID=$tawasulYearGroupID";
        }
        $page->return->setEditLink($editLink);


        if ($tawasulPersonID != '' or $tawasulFormGroupID != '' or $tawasulYearGroupID != '') {
           $params = [
                "tawasulPersonID" => $tawasulPersonID,
                "tawasulFormGroupID" => $tawasulFormGroupID,
                "tawasulYearGroupID" => $tawasulYearGroupID
            ];
            $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulIndividualNeeds', 'investigations_manage.php')->withQueryParams($params));
        }

        $form = Form::create('addform', $session->get('absoluteURL')."/modules/TawasulIndividualNeeds/investigations_manage_addProcess.php?tawasulPersonID=$tawasulPersonID&tawasulFormGroupID=$tawasulFormGroupID&tawasulYearGroupID=$tawasulYearGroupID");
        $form->setFactory(DatabaseFormFactory::create($pdo));
        $form->addHiddenValue('address', "/modules/TawasulIndividualNeeds/investigations_manage_add.php");
        $form->addRow()->addHeading('Basic Information', __('Basic Information'));

        //Student
        $row = $form->addRow();
        	$row->addLabel('tawasulPersonIDStudent', __('Student'));
        	$row->addSelectStudent('tawasulPersonIDStudent', $session->get('tawasulSchoolYearID'))->placeholder()->selected($tawasulPersonID)->required();

        //Status
        $row = $form->addRow();
        	$row->addLabel('status', __('Status'));
        	$row->addTextField('status')->setValue(__('Referral'))->required()->readonly();

        //Date
        $row = $form->addRow();
        	$row->addLabel('date', __('Date'));
        	$row->addDate('date')->setValue(date($session->get('i18n')['dateFormatPHP']))->required();

		//Reason
        $row = $form->addRow();
            $column = $row->addColumn();
            $column->addLabel('reason', __('Reason'))->description(__('Why should this student\'s individual needs be investigated?'));
        	$column->addTextArea('reason')->setRows(5)->setClass('w-full')->required();

        //Strategies Tried
        $row = $form->addRow();
        	$column = $row->addColumn();
        	$column->addLabel('strategiesTried', __('Strategies Tried'));
        	$column->addTextArea('strategiesTried')->setRows(5)->setClass('w-full');

        //Parents Informed?
        $row = $form->addRow();
            $row->addLabel('parentsInformed', __('Parents Informed?'))->description(__('For example, via a phone call, email, Markbook, meeting or other means.'));
            $row->addYesNo('parentsInformed')->required()->placeholder()->selected('N');

        $form->toggleVisibilityByClass('parentsInformedYes')->onSelect('parentsInformed')->when('Y');
        $form->toggleVisibilityByClass('parentsInformedNo')->onSelect('parentsInformed')->when('N');

        //Parent Response
        $row = $form->addRow()->addClass('parentsInformedYes');
        	$column = $row->addColumn();
        	$column->addLabel('parentsResponseYes', __('Parent Response'));
        	$column->addTextArea('parentsResponseYes')->setName('parentsResponse')->setRows(5)->setClass('w-full');

        $row = $form->addRow()->addClass('parentsInformedNo');
        	$column = $row->addColumn();
        	$column->addLabel('parentsResponseNo', __('Reason'))->description(__('Reasons why parents are not aware of the situation.'));
        	$column->addTextArea('parentsResponseNo')->setName('parentsResponse')->setRows(5)->setClass('w-full')->required();

        $form->addRow()->addAlert(__("Submitting this referral will notify the student's form tutor for further investigation."), 'message');

        $row = $form->addRow();
        	$row->addFooter();
        	$row->addSubmit();

        echo $form->getOutput();
    }
}
