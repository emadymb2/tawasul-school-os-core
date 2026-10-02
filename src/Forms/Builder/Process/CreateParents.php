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

use TawasulOS\Data\PasswordPolicy;
use TawasulOS\Data\UsernameGenerator;
use TawasulOS\Domain\User\FamilyAdultGateway;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\User\UserStatusLogGateway;
use TawasulOS\Domain\User\PersonalDocumentGateway;
use TawasulOS\Domain\System\CustomFieldGateway;
use TawasulOS\Forms\Builder\FormBuilderInterface;
use TawasulOS\Forms\Builder\Process\CreateStudent;
use TawasulOS\Forms\Builder\Storage\FormDataInterface;
use TawasulOS\Forms\Builder\View\CreateParentsView;
use TawasulOS\UI\Components\Alert;

class CreateParents extends CreateStudent implements ViewableProcess
{
    protected $requiredFields = ['parent1preferredName', 'parent1surname', 'parent1relationship'];

    protected $familyAdultGateway;

    public function __construct(
        UserGateway $userGateway,
        UserStatusLogGateway $userStatusLogGateway,
        UsernameGenerator $usernameGenerator,
        CustomFieldGateway $customFieldGateway,
        PersonalDocumentGateway $personalDocumentGateway,
        FamilyAdultGateway $familyAdultGateway,
        PasswordPolicy $passwordPolicy,
        Alert $alert
    )
    {
        $this->familyAdultGateway = $familyAdultGateway;

        parent::__construct(
            $userGateway,
            $userStatusLogGateway,
            $usernameGenerator,
            $customFieldGateway,
            $personalDocumentGateway,
            $passwordPolicy,
            $alert
        );
    }

    public function getViewClass() : string
    {
        return CreateParentsView::class;
    }

    public function isEnabled(FormBuilderInterface $builder)
    {
        return $builder->getConfig('createParents') == 'Y';
    }

    public function process(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        // Create Parent 1
        if (!$formData->has('tawasulPersonIDParent1') && $formData->has('parent1surname') && $formData->hasAny(['parent1preferredName','parent1firstName'])) {
            $this->createParentAccount($builder, $formData, '1');
        }

        // Update new or existing Parent 1
        if ($formData->has('tawasulPersonIDParent1')) {
            $this->updateParentRole($formData, '1');
            $this->updateParentData($formData, '1');
            $this->addParentToFamily($formData, '1');
        }

        // Create Parent 2
        if (!$formData->has('tawasulPersonIDParent2') && $formData->has('parent2surname')&& $formData->hasAny(['parent2preferredName','parent2firstName'])) {
            $this->createParentAccount($builder, $formData, '2');
        }

        // Update new or existing Parent 2
        if ($formData->has('tawasulPersonIDParent2')) {
            $this->updateParentRole($formData, '2');
            $this->updateParentData($formData, '2');
            $this->addParentToFamily($formData, '2');
        }

        $this->setResult(true);
    }

    public function rollback(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->has('tawasulPersonIDParent1')) return;

        // Remove the relationships, they are always new
        $this->familyAdultGateway->deleteFamilyRelationship($formData->get('tawasulFamilyID'), $formData->get('tawasulPersonIDParent1'), $formData->get('tawasulPersonIDStudent'));
        $this->familyAdultGateway->deleteFamilyRelationship($formData->get('tawasulFamilyID'), $formData->get('tawasulPersonIDParent2'), $formData->get('tawasulPersonIDStudent'));

        // Only disconnect family if they were connected during this process
        if ($formData->has('parent1adultAdded')) {
            $this->familyAdultGateway->deleteFamilyAdult($formData->get('tawasulFamilyID'), $formData->get('tawasulPersonIDParent1'));
        }

        if ($formData->has('parent2adultAdded')) {
            $this->familyAdultGateway->deleteFamilyAdult($formData->get('tawasulFamilyID'), $formData->get('tawasulPersonIDParent2'));
        }

        // Only remove roles if they were added during this process
        if ($formData->has('parent1roleChanged')) {
            $this->userGateway->removeRoleFromUser($formData->get('tawasulPersonIDParent1'), '004');
        }

        if ($formData->has('parent2roleChanged')) {
            $this->userGateway->removeRoleFromUser($formData->get('tawasulPersonIDParent2'), '004');
        }

        // Only remove users if they were created during this process
        if ($formData->has('parent1created')) {
            $this->userGateway->delete($formData->get('tawasulPersonIDParent1'));
            $formData->set('tawasulPersonIDParent1', null);
        }

        if ($formData->has('parent2created')) {
            $this->userGateway->delete($formData->get('tawasulPersonIDParent2'));
            $formData->set('tawasulPersonIDParent2', null);
        }
    }

    protected function createParentAccount(FormBuilderInterface $builder, FormDataInterface $formData, $i)
    {
        // Generate user details
        $this->generateUsername($formData, '004', "parent{$i}");
        $this->generatePassword($formData, "parent{$i}");

        // Set and assign default values
        $this->setStatus($formData, "parent{$i}");
        $this->setDefaults($formData, "parent{$i}");
        $this->setCustomFields($formData, "parent{$i}");

        // Create and store the new parent account
        $tawasulPersonID = $this->userGateway->insert($this->getUserData($formData, '004', "parent{$i}"));
        $formData->set("tawasulPersonIDParent{$i}", $tawasulPersonID);
        $formData->set("parent{$i}created", !empty($tawasulPersonID));

        // Create the status log
        $this->userStatusLogGateway->insert(['tawasulPersonID' => $tawasulPersonID, 'statusOld' => '', 'statusNew' => $formData->get("parent{$i}status"), 'reason' => __('Created')]);

        // Update existing data
        $this->transferPersonalDocuments($builder, $formData, $tawasulPersonID);
    }

    protected function updateParentRole(FormDataInterface $formData, $i)
    {
        $updated = $this->userGateway->addRoleToUser($formData->get("tawasulPersonIDParent{$i}"), '004');
        $formData->set("parent{$i}roleChanged", $updated);
    }

    protected function updateParentData(FormDataInterface $formData, $i)
    {
        $excludeFields = ['tawasulRoleIDPrimary', 'tawasulRoleIDAll', 'username', 'passwordStrong', 'passwordStrongSalt'];

        $userData = $this->getUserData($formData, '004', "parent{$i}");
        $userData = array_diff_key($userData, array_flip($excludeFields));

        $person = $this->userGateway->getByID($formData->get("tawasulPersonIDParent{$i}"), array_keys($userData));

        if ($person['status'] != 'Left') return;

        $updatedData = array_merge($person, $userData);

        $updated = $this->userGateway->update($formData->get("tawasulPersonIDParent{$i}"), $updatedData);
        $formData->set("parent{$i}updated", $updated);
    }

    protected function addParentToFamily(FormDataInterface $formData, $i)
    {
        if (!$formData->hasAll(["tawasulFamilyID", "parent{$i}relationship", "tawasulPersonIDParent{$i}", "tawasulPersonIDStudent"])) {
            return;
        }

        $existing = $this->familyAdultGateway->selectBy(['tawasulFamilyID' => $formData->get('tawasulFamilyID'), 'tawasulPersonID' => $formData->get("tawasulPersonIDParent{$i}")])->fetch();

        if (empty($existing)) {
            $tawasulFamilyAdultID = $this->familyAdultGateway->insert([
                'tawasulFamilyID'  => $formData->get('tawasulFamilyID'),
                'tawasulPersonID'  => $formData->get("tawasulPersonIDParent{$i}"),
                'childDataAccess' => 'Y',
                'contactPriority' => $i,
                'contactCall'     => 'Y',
                'contactSMS'      => 'Y',
                'contactEmail'    => 'Y',
                'contactMail'     => 'Y',
            ]);
            $formData->set("parent{$i}adultAdded", !empty($tawasulFamilyAdultID));
        } else {
            $tawasulFamilyAdultID = $existing['tawasulFamilyAdultID'];
        }

        $this->familyAdultGateway->insertFamilyRelationship($formData->get('tawasulFamilyID'), $formData->get("tawasulPersonIDParent{$i}"), $formData->get('tawasulPersonIDStudent'), $formData->get("parent{$i}relationship"));

        $formData->set("parent{$i}adultLinked", !empty($tawasulFamilyAdultID));
    }
}
