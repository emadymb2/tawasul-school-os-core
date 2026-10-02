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
use TawasulOS\Domain\Messenger\GroupGateway;

$page->breadcrumbs
    ->add(__('Manage Groups'), 'groups_manage.php')
    ->add(__('Edit Group'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/groups_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulGroupID = (isset($_GET['tawasulGroupID']))? $_GET['tawasulGroupID'] : null;

    //Check if tawasulGroupID specified
    if ($tawasulGroupID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $groupGateway = $container->get(GroupGateway::class);
        
        $highestAction = getHighestGroupedAction($guid, '/modules/TawasulMessenger/groups_manage.php', $connection2);
        if ($highestAction == 'Manage Groups_all') {
            $result = $groupGateway->selectGroupByID($tawasulGroupID);
        } else {
            $result = $groupGateway->selectGroupByIDAndOwner($tawasulGroupID, $session->get('tawasulPersonID'));
        }

        if ($result->isEmpty()) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $form = Form::create('groups', $session->get('absoluteURL').'/modules/'.$session->get('module')."/groups_manage_editProcess.php?tawasulGroupID=$tawasulGroupID");
            $form->setFactory(DatabaseFormFactory::create($pdo));

			$form->addHiddenValue('address', $session->get('address'));
			
            $row = $form->addRow();
                $row->addLabel('name', __('Name'));
                $row->addTextField('name')->required()->maxLength(60);

            $row = $form->addRow();
                $col = $row->addColumn();
                    $col->addLabel('members', __('Members'));
                    $col->addSelectUsers('members', $session->get('tawasulSchoolYearID'), ['includeStudents' => true, 'useMultiSelect' => true])
                        ->mergeGroupings();
            	
			$row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();
                
            $form->loadAllValuesFrom($values);
				
            echo $form->getOutput();

            echo '<h2>';
            echo __('Current Members');
            echo '</h2>';

            $criteria = $groupGateway->newQueryCriteria(true)
                ->sortBy(['roleCategory', 'surname', 'preferredName'])
                ->fromPOST();

            $members = $groupGateway->queryGroupMembers($criteria, $tawasulGroupID);

            $table = DataTable::createPaginated('groupsManage', $criteria);

            $table->addColumn('name', __('Name'))
                ->sortable(['surname', 'preferredName'])
                ->format(Format::using('name', ['', 'preferredName', 'surname', 'Student', true]));

            $table->addColumn('roleCategory', __('Role'))->sortable();

            $table->addColumn('email', __('Email'))->sortable();

            $table->addActionColumn()
                ->addParam('tawasulGroupID')
                ->addParam('tawasulPersonID')
                ->format(function ($person, $actions) {
                    $actions->addAction('delete', __('Delete'))
                            ->setURL('/modules/TawasulMessenger/groups_manage_edit_delete.php');
                });

            echo $table->render($members);
        }
    }
}
