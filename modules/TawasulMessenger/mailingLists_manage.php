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


use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\Messenger\MailingListGateway;

$page->breadcrumbs->add(__('Manage Mailing Lists'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/mailingLists_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
       
    // QUERY
    $mailingListGateway = $container->get(MailingListGateway::class);
    $criteria = $mailingListGateway->newQueryCriteria(true)
        ->sortBy('name')
        ->fromPOST();

    $mailingLists = $mailingListGateway->queryMailingList($criteria);

    // TABLE
    $table = DataTable::createPaginated('mailingLists', $criteria);

    $table->addHeaderAction('add', __('Add'))
        ->displayLabel()
        ->setURL('/modules/TawasulMessenger/mailingLists_manage_add.php');
    
    $table->modifyRows(function ($values, $row) {
        if ($values['active'] == 'N') $row->addClass('error');
        return $row;
    });

    $table->addColumn('name', __('Name'));
    
    $table->addColumn('active', __('Active'))->format(Format::using('yesNo', 'active'));

    $table->addActionColumn()
        ->addParam('tawasulMessengerMailingListID')
        ->format(function ($mailingList, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/TawasulMessenger/mailingLists_manage_edit.php');

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/TawasulMessenger/mailingLists_manage_delete.php');
        });

    echo $table->render($mailingLists);
}
