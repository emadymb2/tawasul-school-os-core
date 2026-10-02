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
use TawasulOS\Tables\Action;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\Forms\FormGateway;
use TawasulOS\Forms\Builder\FormBuilder;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\Forms\FormPageGateway;
use TawasulOS\Domain\Forms\FormFieldGateway;
use TawasulOS\Forms\MultiPartForm;
use TawasulOS\Http\Url;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/formBuilder_page_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $tawasulFormID = $_REQUEST['tawasulFormID'] ?? '';

    $page->breadcrumbs
        ->add(__('Form Builder'), 'formBuilder.php')
        ->add(__('Edit Form'), 'formBuilder_edit.php', ['tawasulFormID' => $tawasulFormID])
        ->add(__('Design'));

    if (empty($tawasulFormID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    if (!empty($_GET['duplicate'])) {
        $page->return->addReturns([
            'warning1' => __('Your request was successful, but some fields were not unique and could not be added to the form: {duplicate}', ['duplicate' => '<b>'.$_GET['duplicate'].'</b>']),
        ]);
    }

    $formGateway = $container->get(FormGateway::class);
    $formPageGateway = $container->get(FormPageGateway::class);
    $formFieldGateway = $container->get(FormFieldGateway::class);

    $fieldGroup = $_REQUEST['fieldGroup'] ?? '';
    $tawasulFormPageID = $_REQUEST['tawasulFormPageID'] ?? '';
    if (empty($tawasulFormPageID)) {
        $tawasulFormPageID = $formPageGateway->getPageIDByNumber($tawasulFormID, 1);
    }

    // Check for existing submissions and warn about making changes
    $submissions = $formGateway->getSubmissionCountByForm($tawasulFormID);
    if ($submissions > 0) {
        $page->addAlert(Format::bold(__('Warning')).': '.__('This form is already in use. Changes to this form could affect the data for {count} existing submissions. Proceed with caution! If you are looking to make significant changes this form, it is safer to set it to inactive and create a new form, which will prevent changes that could affect your existing submissions.', ['count' => Format::bold($submissions)]), 'warning');
    }

    $urlParams = compact('tawasulFormID', 'tawasulFormPageID', 'fieldGroup');

    $formValues = $formGateway->getByID($tawasulFormID);
    $values = $formPageGateway->getByID($tawasulFormPageID);

    if (empty($formValues) || empty($values)) {
        echo $page->getBlankSlate();
        return;
    }

    // QUERY
    $criteria = $formGateway->newQueryCriteria()
        ->sortBy('sequenceNumber', 'ASC')
        ->fromPOST();

    $fields = $formFieldGateway->queryFieldsByPage($criteria, $tawasulFormPageID);
    $formBuilder = $container->get(FormBuilder::class);
    
    // FORM FIELDS
    $formFields = MultiPartForm::create('formFields', '');
    $formFields->setTitle(__($values['name']));
    $formFields->setFactory(DatabaseFormFactory::create($pdo));

    $formFields->setMaxPage($formPageGateway->getFinalPageNumber($tawasulFormID));
    $formFields->addData('drag-url', $session->get('absoluteURL').'/modules/TawasulSystemAdmin/formBuilder_page_editOrderAjax.php');
    $formFields->addData('drag-data', ['tawasulFormPageID' => $tawasulFormPageID]);

    $formPages = $formPageGateway->selectPagesByForm($tawasulFormID)->fetchGroupedUnique();

    if (count($formPages) > 1) {
        $formFields->setCurrentPage($values['sequenceNumber']);

        foreach ($formPages as $formPage) {
            $pageUrl = Url::fromModuleRoute('TawasulSystemAdmin', 'formBuilder_page_design.php')->withQueryParams(['tawasulFormPageID' => $formPage['tawasulFormPageID'], 'sidebar' => 'false'] + $urlParams);
            $formFields->addPage($formPage['sequenceNumber'], $formPage['name'], $pageUrl);
        }
    }
    
    foreach ($fields as $field) {
        $fieldGroupClass = $formBuilder->getFieldGroup($field['fieldGroup']);

        if (empty($fieldGroupClass)) {
            $formFields->addRow()->addContent(Format::alert(__('The specified record cannot be found.')));
            continue;
        }

        $row = $fieldGroupClass->addFieldToForm($formBuilder, $formFields, $field);

        $row->addClass('draggableRow')
            ->addData('drag-id', $field['tawasulFormFieldID']);

        if ($field['hidden'] == 'Y') {
            $row->addClass('bg-purple-200');
        }

        if ($element = $row->getElement($field['fieldName'])) {
            $element->addClass('flex-grow ');
        }

        $row->addContent((new Action('edit', __('Edit')))
            ->setURL('/modules/TawasulSystemAdmin/formBuilder_page_edit_field_edit.php')
            ->addParam('tawasulFormFieldID', $field['tawasulFormFieldID'])
            ->addParams($urlParams)
            ->modalWindow(900, 520)
            ->getOutput().
            (new Action('delete', __('Delete')))
            ->setURL('/modules/TawasulSystemAdmin/formBuilder_page_edit_field_delete.php')
            ->addParam('tawasulFormFieldID', $field['tawasulFormFieldID'])
            ->addParams($urlParams)
            ->getOutput()
        )->setClass('flex-shrink pl-4 relative flex justify-end items-center gap-2 text-right');
    }

    // $formFields->clearTriggers();

    // TEMPLATE
    echo $page->fetchFromTemplate('components/formBuilder.twig.html', [
        'tawasulFormID' => $tawasulFormID,
        'tawasulFormPageID' => $tawasulFormPageID,
        'fieldCount'   => count($fields),
        'fields'       => $formFields,
    ]);
}
