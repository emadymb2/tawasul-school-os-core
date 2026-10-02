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
use TawasulOS\Domain\Finance\BillingScheduleGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/billingSchedule_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Manage Billing Schedule'));

    $search = $_GET['search'] ?? '' ;
    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');

    echo '<p>';
    echo __('The billing schedule allows you to layout your overall timing for issueing invoices, making it easier to specify due dates in bulk. Invoices can be issued outside of the billing schedule, should ad hoc invoices be required.');
    echo '</p>';

    if ($tawasulSchoolYearID != '') {
       $page->navigator->addSchoolYearNavigation($tawasulSchoolYearID);

        echo '<h3>';
        echo __('Search');
        echo '</h3>';

        $form = Form::create("searchBox", $session->get('absoluteURL') . "/index.php", "get", "noIntBorder w-full standardForm");
        $form->setClass('noIntBorder w-full');
        $form->addHiddenValue("q", "/modules/TawasulFinance/billingSchedule_manage.php");
        $form->addHiddenValue("tawasulSchoolYearID", $tawasulSchoolYearID);

        $row = $form->addRow();
            $row->addLabel("search", __("Search For"))->description(__("Billing schedule name."));
            $row->addTextField("search")->maxLength(20)->setValue($search);

        $row = $form->addRow();
            $row->addSearchSubmit($session, __("Clear Search"), ['tawasulSchoolYearID']);

        echo $form->getOutput();

        $billingScheduleGateway = $container->get(BillingScheduleGateway::class);

        // QUERY
        $criteria = $billingScheduleGateway->newQueryCriteria(true)
            ->sortBy(['invoiceIssueDate', 'name'])
            ->pageSize(50)
            ->fromPOST();

        $billingSchedules = $billingScheduleGateway->queryBillingSchedules($criteria, $tawasulSchoolYearID, $search);

        // TABLE
        $table = DataTable::createPaginated('billingSchedule', $criteria);
        $table->setTitle(__('View'));

        $table->modifyRows(function ($value, $row) {
            if ($value['invoiceIssueDate'] < date('Y-m-d')) $row->addClass('warning');
            if ($value['invoiceDueDate'] < date('Y-m-d')) $row->addClass('error');
            return $row;
        });

        $table->addHeaderAction('add', __('Add'))
            ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->addParam('search', $search)
            ->setURL('/modules/TawasulFinance/billingSchedule_manage_add.php')
            ->displayLabel();

        $table->addExpandableColumn('comment')
            ->format(function($value) {
                return $value['description'];
            });

        $table->addColumn('name', __('Name'));

        $table->addColumn('invoiceIssueDate', __('Invoice Issue Date'))->format(Format::using('date', 'invoiceIssueDate'));

        $table->addColumn('invoiceDueDate', __('Invoice Due Date'))->format(Format::using('date', 'invoiceDueDate'));

        $actions = $table->addActionColumn()
            ->addParam('tawasulFinanceBillingScheduleID')
            ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->addParam('search', $search)
            ->format(function ($resource, $actions) {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulFinance/billingSchedule_manage_edit.php');
            });

        echo $table->render($billingSchedules);
    }
}
