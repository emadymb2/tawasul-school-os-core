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

namespace TawasulOS\Forms\Builder\Process;

use TawasulOS\Domain\Students\MedicalGateway;
use TawasulOS\Domain\System\CustomFieldGateway;
use TawasulOS\Forms\Builder\AbstractFormProcess;
use TawasulOS\Forms\Builder\FormBuilderInterface;
use TawasulOS\Forms\Builder\Storage\FormDataInterface;
use TawasulOS\Forms\Builder\View\CreateMedicalRecordView;

class CreateMedicalRecord extends AbstractFormProcess implements ViewableProcess
{
    protected $requiredFields = ['medical'];

    protected $medicalGateway;
    protected $customFieldGateway;

    public function __construct(MedicalGateway $medicalGateway, CustomFieldGateway $customFieldGateway)
    {
        $this->medicalGateway = $medicalGateway;
        $this->customFieldGateway = $customFieldGateway;
    }

    public function getViewClass() : string
    {
        return CreateMedicalRecordView::class;
    }

    public function isEnabled(FormBuilderInterface $builder)
    {
        return $builder->getConfig('createMedicalRecord') == 'Y';
    }

    public function process(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->hasAll(['tawasulPersonIDStudent', 'medical'])) {
            return;
        }

        // Create a new medical record
        $tawasulPersonMedicalID = $this->medicalGateway->insert([
            'tawasulPersonID'            => $formData->get('tawasulPersonIDStudent'),
            'comment'                   => $formData->get('medicalInformation', ''),
            'longTermMedication'        => $formData->get('longTermMedication', 'N'),
            'longTermMedicationDetails' => $formData->get('longTermMedicationDetails', ''),
            'fields'                    => $this->getCustomFields($formData),
        ]);

        $formData->set('tawasulPersonMedicalID', $tawasulPersonMedicalID);
        $this->setResult($tawasulPersonMedicalID);
    }

    public function rollback(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->has('tawasulPersonMedicalID')) return;

        $this->medicalGateway->delete($formData->get('tawasulPersonMedicalID'));
        
        $formData->set('tawasulPersonMedicalID', null);
    }

    /**
     * Transfer values from form data into json custom field data
     *
     * @param FormDataInterface $formData
     */
    protected function getCustomFields(FormDataInterface $formData)
    {
        $customFields = $this->customFieldGateway->selectCustomFields('Medical Form', [])->fetchAll();
        $fields = [];

        foreach ($customFields as $field) {
            $id = 'custom'.$field['tawasulCustomFieldID'];
            if (!$formData->has($id)) continue;

            $fields[$field['tawasulCustomFieldID']] = $formData->get($id);
        }

        return json_encode($fields);
    }
}
