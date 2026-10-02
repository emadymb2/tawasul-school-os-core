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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/budgets_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('Manage Budgets'),'budgets_manage.php')
        ->add(__('Edit Budget'));

    $page->return->addReturns(['error4' => __('Your request failed due to an attachment error.')]);

    //Check if tawasulFinanceBudgetID specified
    $tawasulFinanceBudgetID = $_GET['tawasulFinanceBudgetID'] ?? '';
    if ($tawasulFinanceBudgetID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulFinanceBudgetID' => $tawasulFinanceBudgetID);
            $sql = 'SELECT * FROM tawasulFinanceBudget WHERE tawasulFinanceBudgetID=:tawasulFinanceBudgetID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record does not exist.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/budgets_manage_editProcess.php?tawasulFinanceBudgetID=$tawasulFinanceBudgetID");
            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue('address', $session->get('address'));

            $form->addRow()->addHeading('General Settings', __('General Settings'));

            $row = $form->addRow();
                $row->addLabel('name', __('Name'))->description(__('Must be unique.'));
                $row->addTextField('name')->maxLength(100)->required();

            $row = $form->addRow();
                $row->addLabel('nameShort', __('Short Name'))->description(__('Must be unique.'));
                $row->addTextField('nameShort')->maxLength(8)->required();

            $row = $form->addRow();
                $row->addLabel('active', __('Active'));
                $row->addYesNo('active')->required();

            $categories = $container->get(SettingGateway::class)->getSettingByScope('Finance', 'budgetCategories');
            if (empty($categories)) {
                $categories = 'Other';
            }
            $row = $form->addRow();
                $row->addLabel('category', __('Category'));
                $row->addSelect('category')->fromString($categories)->placeholder()->required();

            $form->addRow()->addHeading('Current Staff', __('Current Staff'));

            $data = array('tawasulFinanceBudgetID' => $tawasulFinanceBudgetID);
            $sql = "SELECT preferredName, surname, tawasulFinanceBudgetPerson.* FROM tawasulFinanceBudgetPerson JOIN tawasulPerson ON (tawasulFinanceBudgetPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulFinanceBudgetID=:tawasulFinanceBudgetID AND tawasulPerson.status='Full' ORDER BY FIELD(access,'Full','Write','Read'), surname, preferredName";

            $results = $pdo->executeQuery($data, $sql);

            if ($results->rowCount() == 0) {
                $form->addRow()->addAlert(__('There are no records to display.'), 'error');
            } else {
                $form->addRow()->addContent('<b>'.__('Warning').'</b>: '.__('If you delete a member of staff, any unsaved changes to this record will be lost!'))->wrap('<i>', '</i>');

                $table = $form->addRow()->addTable()->addClass('colorOddEven');

                $header = $table->addHeaderRow();
                $header->addContent(__('Name'));
                $header->addContent(__('Access'));
                $header->addContent(__('Action'));

                while ($staff = $results->fetch()) {
                    $row = $table->addRow();
                    $row->addContent(Format::name('', $staff['preferredName'], $staff['surname'], 'Staff', true, true));
                    $row->addContent(__($staff['access']));
                    $row->addContent("<a onclick='return confirm(\"".__('Are you sure you wish to delete this record?')."\")' href='".$session->get('absoluteURL').'/modules/'.$session->get('module').'/budgets_manage_edit_staff_deleteProcess.php?address='.$_GET['q'].'&tawasulFinanceBudgetPersonID='.$staff['tawasulFinanceBudgetPersonID']."&tawasulFinanceBudgetID=$tawasulFinanceBudgetID'><img title='".__('Delete')."' src='./themes/".$session->get('tawasulThemeName')."/img/garbage.png'/></a>");
                }
            }

            $form->addRow()->addHeading('New Staff', __('New Staff'));

            $row = $form->addRow();
                $row->addLabel('staff', __('Staff'));
                $row->addSelectStaff('staff')->selectMultiple();

            $access = array(
                "Full" => __("Full"),
                "Write" => __("Write"),
                "Read" => __("Read")
            );
            $row = $form->addRow();
                $row->addLabel('access', __('Access'));
                $row->addSelect('access')->fromArray($access);

            $form->loadAllValuesFrom($values);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
?>
