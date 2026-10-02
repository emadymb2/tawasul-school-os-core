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

TawasulOS — Manage chart of accounts.
*/

use Tos\Module\TawasulFinance\Domain\AccountGateway;
use TawasulOS\Tables\DataTable;
use TawasulOS\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/accounts_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__m('Chart of Accounts'));

    /** @var AccountGateway $gateway */
    $gateway = $container->get(AccountGateway::class);

    $criteria = $gateway
        ->newQueryCriteria(true)
        ->sortBy('code', 'ASC')
        ->fromPOST();

    $accounts = $gateway->queryAccounts($criteria);

    $table = DataTable::createPaginated('accounts', $criteria);
    $table->setDescription(__m('The chart of accounts is the full list of ledger accounts that every transaction is posted against. Accounts marked as a heading group their children and cannot be posted to directly.'));

    $table->addHeaderAction('add', __m('Add Account'))
        ->setURL('/modules/TawasulFinance/accounts_add.php')
        ->displayLabel();

    $table->addColumn('code', __m('Code'))->nowrap();
    $table->addColumn('name', __m('Account Name'));
    $table->addColumn('type', __m('Type'))
        ->format(function ($item) {
            $types = AccountGateway::ARABIC_TYPES;
            $type = $item['type'];

            return ($types[$type] ?? $type).' ('.$type.')';
        });
    $table->addColumn('isPosting', __m('Posting'))
        ->format(Format::using('yesNo', 'isPosting'));
    $table->addColumn('active', __m('Active'))
        ->format(Format::using('yesNo', 'active'));

    $table->addActionColumn()
        ->addParam('tawasulFinanceAccountID')
        ->format(function ($item, $actions) {
            $actions
                ->addAction('edit', __m('Edit'))
                ->setURL('/modules/TawasulFinance/accounts_edit.php');

            $actions
                ->addAction('delete', __m('Delete'))
                ->setURL('/modules/TawasulFinance/accounts_delete.php');
        });

    $table->modifyRows(function ($item, $row) {
        return $item['active'] == 'N' ? $row->addClass('error') : $row;
    });

    echo $table->render($accounts);
}