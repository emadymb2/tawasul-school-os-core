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

use TawasulOS\Domain\Finance\InvoiceGateway;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feeCategories_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Fee Categories'));

    $gateway = $container->get(InvoiceGateway::class);
    $criteria = $gateway->newQueryCriteria(true)->fromPOST();
    $feeCategories = $gateway->queryFeeCategories($criteria);

    $table = DataTable::createPaginated('feeCategories', $criteria);
    $table->setDescription(__('Categories are used to group fees together into related sets. Some examples might be Tuition Fees, Learning Support Fees or Transport Fees. Categories enable you to control who receives invoices for different kinds of fees.'));

    $table->modifyRows(function ($item, $row) {
        return $item['active'] == 'N' ? $row->addClass('error') : $row;
    });

    $table->addHeaderAction('add', __('Add'))
          ->setURL('/modules/TawasulFinance/feeCategories_manage_add.php')
          ->displayLabel();

    $table->addColumn('name', __('Name'));
    $table->addColumn('nameShort', __('Short Name'));
    $table->addColumn('description', __('Description'));
    $table->addColumn('active', __('Active'))->format(Format::using('yesNo', 'active'));

    $table->addActionColumn()
          ->addParam('tawasulFinanceFeeCategoryID')
          ->format(function ($item, $actions) {
            if ($item['tawasulFinanceFeeCategoryID'] == 1) {
                echo Format::small(__('This category cannot be edited or deleted.'));
            } else {
                $actions->addAction('edit', __('Edit'))
                      ->setURL('/modules/TawasulFinance/feeCategories_manage_edit.php');
                $actions->addAction('delete', __('Delete'))
                      ->setURL('/modules/TawasulFinance/feeCategories_manage_delete.php');
            }
          });

    echo $table->render($feeCategories);
}
