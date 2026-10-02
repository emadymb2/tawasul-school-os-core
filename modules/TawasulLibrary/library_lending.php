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
use TawasulOS\Domain\Library\LibraryGateway;
use TawasulOS\Services\Format;

$page->breadcrumbs->add(__('Lending & Activity Log'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_lending.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    echo '<h3>';
    echo __('Search & Filter');
    echo '</h3>';

    //Get current filter values
    $name = $_REQUEST['name'] ?? '';
    $tawasulLibraryTypeID = $_REQUEST['tawasulLibraryTypeID'] ?? '';
    $tawasulSpaceID = $_REQUEST['tawasulSpaceID'] ?? '';
    $status = $_REQUEST['status'] ?? '';

    $form = Form::create('action', $session->get('absoluteURL') . '/modules/TawasulLibrary/library_lendingProcess.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder w-full');

    $row = $form->addRow();
    $row->addLabel('name', __('ID/Name/Producer'))->description(__('If only one match exists, it will be automatically opened.'));
    $row->addTextField('name')->setValue($name)->maxLength(50);

    $data = array();
    $sql = "SELECT tawasulLibraryTypeID AS value, name FROM tawasulLibraryType WHERE active='Y' ORDER BY name";
    $row = $form->addRow();
    $row->addLabel('tawasulLibraryTypeID', __('Type'));
    $row->addSelect('tawasulLibraryTypeID')->fromQuery($pdo, $sql, $data)->placeholder()->selected($tawasulLibraryTypeID);

    $row = $form->addRow();
    $row->addLabel('tawasulSpaceID', __('Space'));
    $row->addSelectSpace('tawasulSpaceID')->placeholder()->selected($tawasulSpaceID);

    $statuses = array(
        'Available' => __('Available'),
        'On Loan' => __('On Loan'),
        'Repair' => __('Repair'),
        'Reserved' => __('Reserved')
    );
    $row = $form->addRow();
    $row->addLabel('status', __('Status'));
    $row->addSelect('status')->fromArray($statuses)->selected($status)->placeholder();

    $row = $form->addRow();
    $row->addFooter();
    $row->addSearchSubmit($session);

    echo $form->getOutput();

    $gateway = $container->get(LibraryGateway::class);
    $criteria = $gateway->newQueryCriteria(true)
        ->sortBy(['timestampStatus'], 'DESC')
        ->filterBy('name', $name)
        ->filterBy('tawasulLibraryTypeID', $tawasulLibraryTypeID)
        ->filterBy('tawasulSpaceID', $tawasulSpaceID)
        ->filterBy('status', $status)
        ->fromPOST();
    $items = $gateway->queryLending($criteria);

    $table = DataTable::createPaginated('lending', $criteria);

    $table->setTitle(__('Lending & Activity Log'));

    $table->addColumn('id', __('ID'));
    $table->addColumn('name', __('Name'))->format(function ($item) {
        return sprintf('<b>%1$s</b><br/>%2$s', $item['name'], Format::small($item['producer']));
    });
    $table->addColumn('typeName', __('Type'))->translatable();
    $table->addColumn('location', __('Location'))
        ->sortable(['spaceName', 'locationDetail'])
        ->format(function ($item) {
            return sprintf('<b>%1$s</b><br/>%2$s', $item['spaceName'], Format::small($item['locationDetail']));
        });
    $table->addColumn('timestampStatus', __('Status'))
        ->description(__('Return'))
        ->format(function ($item) {
            $statusDetail = "";
            if ($item['returnExpected'] != null) {
                $statusDetail .= sprintf(
                    '<br/>%1$s<br/>%2$s',
                    Format::small(Format::date($item['returnExpected'])),
                    Format::small(Format::name($item['title'], $item['preferredName'], $item['surname'], 'Student', false, true))
                );
            }
            return sprintf(
                '<b>%1$s</b>%2$s',
                __($item['status']),
                $statusDetail
            );
        });

    $table->addActionColumn()
        ->addParam('tawasulLibraryItemID')
        ->addParam('tawasulLibraryItemEventID')
        ->addParam('name', $name)
        ->addParam('tawasulLibraryTypeID', $tawasulLibraryTypeID)
        ->addParam('tawasulSpaceID', $tawasulSpaceID)
        ->addParam('status', $status)
        ->format(function ($item, $actions) {

            if ($item['status'] == 'Available') {
                $actions->addAction('signout', __('Sign Out'))
                    ->setURL('/modules/TawasulLibrary/library_lending_item_signout.php')
                    ->setIcon('page_right');
            } elseif ($item['status'] == 'On Loan' && !empty($item['tawasulPersonIDStatusResponsible'])) {
                if (!empty($item['tawasulPersonIDStatusResponsible'])) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulLibrary/library_lending_item_edit.php');
                }

                $actions->addAction('return', __('Return'))
                    ->setIcon('page_left')
                    ->setURL('/modules/TawasulLibrary/library_lending_item_return.php');

                if (!empty($item['tawasulPersonIDStatusResponsible'])) {
                    $actions->addAction('renew', __('Renew'))
                        ->setIcon('page_right')
                        ->setURL('/modules/TawasulLibrary/library_lending_item_renew.php');
                }
            }
            $actions->addAction('lending', __('Lending'))
                ->setURL('/modules/TawasulLibrary/library_lending_item.php')
                ->setIcon('attendance');
        });

    $table->modifyRows(function ($item, $row) {
        switch ($item['status']) {
            case 'On Loan':
                if ($item['pastDue'] == "Y") {
                    $row->addClass('error');
                } else {
                    $row->addClass('success');
                }
                break;
        }
        return $row;
    });
    echo $table->render($items);
}
