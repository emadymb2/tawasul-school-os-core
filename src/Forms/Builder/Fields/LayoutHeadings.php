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

namespace TawasulOS\Forms\Builder\Fields;

use TawasulOS\Forms\Form;
use TawasulOS\Forms\Layout\Row;
use TawasulOS\Forms\Builder\AbstractFieldGroup;
use TawasulOS\Forms\Builder\FormBuilderInterface;

class LayoutHeadings extends AbstractFieldGroup
{
    public function __construct()
    {
        $this->fields = [
            'heading' => [
                'label' => __('Heading'),
                'type'  => 'heading',
            ],
            'subheading' => [
                'label' => __('Subheading'),
                'type'  => 'subheading',
            ],
        ];
    }

    public function getDescription() : string
    {
        return __('Headings enable you to break your form up into sections as well as add additional instructions.');
    }

    public function addFieldToForm(FormBuilderInterface $formBuilder, Form $form, array $field): Row
    {
        $row = $form->addRow();

        // TODO: Fix this. Hack to make logged-in parent fields work :(
        if ($formBuilder->hasConfig('tawasulPersonID') && $field['fieldName'] == 'headingParentGuardian1PersonalData') {
            $field['options'] = '';
        }
        
        switch ($field['fieldType']) {
            case 'heading':
                $row->addClass($field['options'] ?? '')
                    ->addHeading($field['label'], __($field['label']))
                    ->append(__($field['description']));
                break;
            case 'subheading':
                $row->addClass($field['options'] ?? '')
                    ->addSubheading($field['label'], __($field['label']))
                    ->append(__($field['description']));
                break;
        }

        return $row;
    }
}
