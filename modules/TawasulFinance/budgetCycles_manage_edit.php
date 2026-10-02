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
use TawasulOS\Domain\Finance\FinanceBudgetCycleGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/budgetCycles_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('Manage Budget Cycles'), 'budgetCycles_manage.php')
        ->add(__('Edit Budget Cycle'));

    //Check if tawasulFinanceBudgetCycleID specified
    $tawasulFinanceBudgetCycleID = $_GET['tawasulFinanceBudgetCycleID'] ?? '';
    if ($tawasulFinanceBudgetCycleID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
            $result = $container->get(FinanceBudgetCycleGateway::class)->getByID($tawasulFinanceBudgetCycleID);

        if (empty($result)) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result;

            $form = Form::create('budgetCycle', $session->get('absoluteURL').'/modules/'.$session->get('module').'/budgetCycles_manage_editProcess.php?tawasulFinanceBudgetCycleID='.$tawasulFinanceBudgetCycleID);
            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue("address", $session->get('address'));

            $row = $form->addRow();
                $row->addHeading("Basic Information", __("Basic Information"));

            $row = $form->addRow();
                $row->addLabel("name", __("Name"))->description(__("Must be unique."));
                $row->addTextField("name")->required()->maxLength(7);

            $statusTypes = array(
                'Upcoming' => __("Upcoming"),
                'Current' =>  __("Current"),
                'Past' => __("Past")
            );

            $row = $form->addRow();
                $row->addLabel("status", __("Status"));
                $row->addSelect("status")->fromArray($statusTypes);

            $row = $form->addRow();
                $row->addLabel('sequenceNumber', __('Sequence Number'))->description(__('Must be unique. Controls chronological ordering.'));
                $row->addSequenceNumber('sequenceNumber', 'tawasulFinanceBudgetCycle', $values['sequenceNumber'])->required()->maxLength(3);

            $row = $form->addRow();
                $row->addLabel("dateStart", __("Start Date"))->description(__('Format:').' ')->append($session->get('i18n')['dateFormat']);
                $row->addDate("dateStart")->required();

            $row = $form->addRow();
                $row->addLabel("dateEnd", __("End Date"))->description(__('Format:').' ')->append($session->get('i18n')['dateFormat']);
                $row->addDate("dateEnd")->required();

            $row = $form->addRow();
                $row->addHeading("Budget Allocations", __("Budget Allocations"));


                $dataBudget = array('tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID);
                $sqlBudget = 'SELECT tawasulFinanceBudget.*, value FROM tawasulFinanceBudget LEFT JOIN tawasulFinanceBudgetCycleAllocation ON (tawasulFinanceBudgetCycleAllocation.tawasulFinanceBudgetID=tawasulFinanceBudget.tawasulFinanceBudgetID AND tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID) ORDER BY name';
                $resultBudget = $connection2->prepare($sqlBudget);
                $resultBudget->execute($dataBudget);
            if ($resultBudget->rowCount() < 1) {
                $form->addRow()->addAlert(__('There are no records to display.'), 'error');
            } else {
                while ($rowBudget = $resultBudget->fetch()) {
                    $row = $form->addRow();
                        $row->addLabel($rowBudget['tawasulFinanceBudgetID'], $rowBudget['name']);
                        $row->addCurrency($rowBudget['tawasulFinanceBudgetID'])->setName('values[]')->required()->maxLength(15)->setValue((is_null($rowBudget['value'])) ? '0.00' : $rowBudget['value']);
                    $form->addHiddenValue('tawasulFinanceBudgetIDs[]', $rowBudget['tawasulFinanceBudgetID']);
                }
            }

            $form->loadAllValuesFrom($values);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            print $form->getOutput();
        }
    }
}
?>
