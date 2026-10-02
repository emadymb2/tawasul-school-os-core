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

namespace TawasulOS\Forms\Traits;

use TawasulOS\Forms\Layout\Row;
use TawasulOS\Forms\Input\Input;
use TawasulOS\Forms\OutputableInterface;
use TawasulOS\Forms\RowDependancyInterface;

/**
 * Dynamically add form fields via the factory.
 *
 * @version v30
 * @since   v30
 * 
 * @method \TawasulOS\Forms\Layout\Row addRow($id = '') {@see \TawasulOS\Forms\Layout\Row}
 * @method \TawasulOS\Forms\Layout\Column addColumn($id = '') {@see \TawasulOS\Forms\Layout\Column}
 * @method \TawasulOS\Forms\Layout\Meta addMeta() {@see \TawasulOS\Forms\Layout\Meta}
 * @method \TawasulOS\Forms\Layout\Table addTable($id = '') {@see \TawasulOS\Forms\Layout\Table}
 * @method \TawasulOS\Forms\Layout\DataTable addDataTable($id, $criteria = null) {@see \TawasulOS\Forms\Layout\DataTable}
 * @method \TawasulOS\Forms\Layout\TableCell addTableCell($content = '') {@see \TawasulOS\Forms\Layout\TableCell}
 * @method \TawasulOS\Forms\Layout\Grid addGrid($id = '', $columns = 1) {@see \TawasulOS\Forms\Layout\Grid}
 * @method \TawasulOS\Forms\Layout\Details addDetails($id = '') {@see \TawasulOS\Forms\Layout\Details}
 * @method \TawasulOS\Forms\Layout\Trigger addTrigger($selector = '') {@see \TawasulOS\Forms\Layout\Trigger}
 * @method \TawasulOS\Forms\Layout\Label addLabel($for, $label) {@see \TawasulOS\Forms\Layout\Label}
 * @method \TawasulOS\Forms\Layout\Heading addHeading($id = '', $content = null) {@see \TawasulOS\Forms\Layout\Heading}
 * @method \TawasulOS\Forms\Layout\Heading addSubheading($id = '', $content = null) {@see \TawasulOS\Forms\Layout\Heading}
 * @method \TawasulOS\Forms\Layout\Element addContent($content = '') {@see \TawasulOS\Forms\Layout\Element}
 * @method \TawasulOS\Forms\Layout\WebLink addWebLink($content = '') {@see \TawasulOS\Forms\Layout\WebLink}
 * @method \TawasulOS\Forms\Layout\Action addAction($name, $label = '') {@see \TawasulOS\Forms\Layout\Action}
 * 
 * @method \TawasulOS\Forms\Input\CustomField addCustomField(string $name, array $fields = []) {@see \TawasulOS\Forms\Input\CustomField}
 * @method \TawasulOS\Forms\Input\TextArea addTextArea(string $name) {@see \TawasulOS\Forms\Input\TextArea}
 * @method \TawasulOS\Forms\Input\TextField addTextField(string $name, string $label) {@see \TawasulOS\Forms\Input\TextField}
 * @method \TawasulOS\Forms\Input\TokenList addTokenList(string $name, string $label) {@see \TawasulOS\Forms\Input\TokenList}
 * @method \TawasulOS\Forms\Input\Range addRange() {@see \TawasulOS\Forms\Input\Range}
 * @method \TawasulOS\Forms\Input\Color addColor(string $name) {@see \TawasulOS\Forms\Input\Color}
 * @method \TawasulOS\Forms\Input\Finder addFinder(string $name) {@see \TawasulOS\Forms\Input\Finder}
 * @method \TawasulOS\Forms\Input\Editor addEditor(string $name) {@see \TawasulOS\Forms\Input\Editor}
 * @method \TawasulOS\Forms\Input\CodeEditor addCodeEditor(string $name) {@see \TawasulOS\Forms\Input\CodeEditor}
 * @method \TawasulOS\Forms\Input\CommentEditor addCommentEditor(string $name) {@see \TawasulOS\Forms\Input\CommentEditor}
 * @method \TawasulOS\Forms\Input\TextField addEmail(string $name) {@see \TawasulOS\Forms\Input\TextField}
 * @method \TawasulOS\Forms\Input\TextField addURL(string $name) {@see \TawasulOS\Forms\Input\TextField}
 * @method \TawasulOS\Forms\Input\Number addNumber(string $name) {@see \TawasulOS\Forms\Input\Number}
 * @method \TawasulOS\Forms\Input\Currency addCurrency(string $name) {@see \TawasulOS\Forms\Input\Currency}
 * @method \TawasulOS\Forms\Input\Password addPassword(string $name) {@see \TawasulOS\Forms\Input\Password}
 * @method \TawasulOS\Forms\Input\FileUpload addFileUpload(string $name) {@see \TawasulOS\Forms\Input\FileUpload}
 * @method \TawasulOS\Forms\Input\Date addDate(string $name) {@see \TawasulOS\Forms\Input\Date}
 * @method \TawasulOS\Forms\Input\Time addTime(string $name) {@see \TawasulOS\Forms\Input\Time}
 * @method \TawasulOS\Forms\Input\Checkbox addCheckbox(string $name) {@see \TawasulOS\Forms\Input\Checkbox}
 * @method \TawasulOS\Forms\Input\Radio addRadio(string $name) {@see \TawasulOS\Forms\Input\Radio}
 * @method \TawasulOS\Forms\Input\Toggle addToggle(string $name) {@see \TawasulOS\Forms\Input\Toggle}
 * @method \TawasulOS\Forms\Input\Select addSelect(string $name) {@see \TawasulOS\Forms\Input\Select}
 * @method \TawasulOS\Forms\Input\MultiSelect addMultiSelect(string $name) {@see \TawasulOS\Forms\Input\MultiSelect}
 * @method \TawasulOS\Forms\Input\Button addButton(string $label = 'Button', $onClick = null, $id = null) {@see \TawasulOS\Forms\Input\Button}
 * @method \TawasulOS\Forms\Input\CustomBlocks addCustomBlocks($name, Session $session, bool $canDelete = true) {@see \TawasulOS\Forms\Input\CustomBlocks}
 * @method \TawasulOS\Forms\Input\Documents addDocuments($name, $documents, $view, $absoluteURL, $mode = '') {@see \TawasulOS\Forms\Input\Documents}
 * @method \TawasulOS\Forms\Input\PersonalDocuments addPersonalDocuments($name, $documents, $view, $settingGateway) {@see \TawasulOS\Forms\Input\PersonalDocuments}
 * @method \TawasulOS\Forms\Input\Username addUsername(string $name) {@see \TawasulOS\Forms\Input\Username}
 * @method \TawasulOS\Forms\Input\Person addSelectPerson(string $name) {@see \TawasulOS\Forms\Input\Person}
 * @method \TawasulOS\Forms\Input\Scanner addScanner(string $name) {@see \TawasulOS\Forms\Input\Scanner}
 * 
 * @method \TawasulOS\Forms\Layout\Element createAlert(string $content, $level = 'warning') {@see \TawasulOS\Forms\FormFactory::createAlert() }
 * @method \TawasulOS\Forms\Layout\Button createSubmit($label = 'Submit', $id = null) {@see \TawasulOS\Forms\FormFactory::createSubmit() }
 * @method \TawasulOS\Forms\Layout\Button createSearchSubmit($session, $clearLabel = 'Clear Filters', $passParams = []) {@see \TawasulOS\Forms\FormFactory::createSearchSubmit() }
 * @method \TawasulOS\Forms\Layout\Button createConfirmSubmit($label = 'Yes', $cancel = false) {@see \TawasulOS\Forms\FormFactory::createConfirmSubmit() }
 * @method \TawasulOS\Forms\Layout\Button createAdvancedOptionsToggle() {@see \TawasulOS\Forms\FormFactory::createAdvancedOptionsToggle() }
 * @method \TawasulOS\Forms\Layout\Button createFooter($required = true) {@see \TawasulOS\Forms\FormFactory::createFooter() }

 * @method \TawasulOS\Forms\Input\Toggle addYesNo(string $name) {@see \TawasulOS\Forms\FormFactory::createYesNo() }
 * @method \TawasulOS\Forms\Input\Toggle addYesNoRadio(string $name) {@see \TawasulOS\Forms\FormFactory::createYesNoRadio() }
 * @method \TawasulOS\Forms\Input\Checkbox addCheckAll(string $name) {@see \TawasulOS\Forms\FormFactory::createCheckAll() }
 * @method \TawasulOS\Forms\Input\Select addSelectTitle(string $name) {@see \TawasulOS\Forms\FormFactory::createSelectTitle() }
 * @method \TawasulOS\Forms\Input\Select addSelectGender(string $name) {@see \TawasulOS\Forms\FormFactory::createSelectGender() }
 * @method \TawasulOS\Forms\Input\Select addSelectRelationship(string $name) {@see \TawasulOS\Forms\FormFactory::createSelectRelationship() }
 * @method \TawasulOS\Forms\Input\Select addSelectEmergencyRelationship(string $name) {@see \TawasulOS\Forms\FormFactory::createSelectEmergencyRelationship() }
 * @method \TawasulOS\Forms\Input\Select addSelectMaritalStatus(string $name) {@see \TawasulOS\Forms\FormFactory::createSelectMaritalStatus() }
 * @method \TawasulOS\Forms\Input\Select addSelectSystemLanguage(string $name) {@see \TawasulOS\Forms\FormFactory::createSelectSystemLanguage() }
 * @method \TawasulOS\Forms\Input\Select addSelectCurrency(string $name) {@see \TawasulOS\Forms\FormFactory::createSelectCurrency() }
 */
trait FormFieldsTrait
{
    /**
     * Invoke factory method for creating elements when an "add" method is called on this row.
     * @param   string  $function
     * @param   array   $args
     * @return  object  Element
     */
    public function __call(string $function, array $args)
    {
        if (substr($function, 0, 3) != 'add') {
            return;
        }

        try {
            $function = substr_replace($function, 'create', 0, 3);
            
            $reflectionMethod = new \ReflectionMethod($this->factory, $function);

            $element = $reflectionMethod->invokeArgs($this->factory, $args);

            if ($this instanceof Row && $function == 'createSubmit') {
                $this->setHeading('submit');
            }

        } catch (\ReflectionException $e) {
            $element = $this->factory->createContent(strtr('Cannot {function}. This form element does not exist in the current FormFactory: {message}', [
                '{function}' => $function,
                '{message}' => $e->getMessage(),
            ]));
        } catch (\Exception $e) {
            $element = $this->factory->createContent(strtr('Cannot {function}. Error creating form element: {message}', [
                '{function}' => $function,
                '{message}' => $e->getMessage(),
            ]));
        } finally {
            if (!($element instanceof OutputableInterface)) {
                if (($element_type = gettype($element)) === 'object') $element_type = get_class($element);
                $element = $this->factory->createContent(strtr('{function} returned {type} instead of an outputable form element.', [
                    '{type}' => $element_type,
                    '{function}' => $function,
                ]));
            }
           
        }

        return $this->addElement($element);
    }
}
