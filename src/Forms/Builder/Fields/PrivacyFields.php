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
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Builder\AbstractFieldGroup;
use TawasulOS\Forms\Builder\FormBuilderInterface;

class PrivacyFields extends AbstractFieldGroup
{
    protected $settingGateway;

    public function __construct(SettingGateway $settingGateway)
    {
        $this->settingGateway = $settingGateway;
        $privacyBlurb = $this->settingGateway->getSettingByScope('User Admin', 'privacyBlurb');

        $this->fields = [
            'headingPrivacyStatement' => [
                'label'       => __('Privacy Statement'),
                'description' => $privacyBlurb ?? __('This is example text. Edit it to suit your school context.'),
                'type'        => 'subheading',
            ],
            'privacyOptions' => [
                'label'       => __('Privacy Options'),
            ],
        ];
    }

    public function getDescription() : string
    {
        return '';
    }

    public function addFieldToForm(FormBuilderInterface $formBuilder, Form $form, array $field) : Row
    {
        $required = $this->getRequired($formBuilder, $field);
        $default = $field['defaultValue'] ?? '';

        $row = $form->addRow();

        switch ($field['fieldName']) {
            case 'privacyBlurb':
                $row->addSubheading(__($field['label']))->append(__($field['description']));
                break;

            case 'privacyOptions':
                $privacyOptions = $this->settingGateway->getSettingByScope('User Admin', 'privacyOptions');
                $options = array_map('trim', explode(',', $privacyOptions));

                $row->addLabel('privacyOptions[]', __($field['label']))->description(__($field['description']));
                $row->addCheckbox('privacyOptions[]')->fromArray($options)->addClass('md:max-w-lg')->required($required)->checked(explode(',', $default));
                break;
        }

        return $row;
    }
}
