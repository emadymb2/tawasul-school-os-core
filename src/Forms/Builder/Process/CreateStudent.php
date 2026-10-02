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
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\User\UserStatusLogGateway;
use TawasulOS\Domain\System\CustomFieldGateway;
use TawasulOS\Domain\User\PersonalDocumentGateway;
use TawasulOS\Forms\Builder\AbstractFormProcess;
use TawasulOS\Forms\Builder\FormBuilderInterface;
use TawasulOS\Forms\Builder\Storage\FormDataInterface;
use TawasulOS\Forms\Builder\View\CreateStudentView;
use TawasulOS\Forms\Builder\Exception\FormProcessException;
use TawasulOS\UI\Components\Alert;

class CreateStudent extends AbstractFormProcess implements ViewableProcess
{
    /**
     * An array of required fields.
     *
     * @var string[]
     */
    protected $requiredFields = ['preferredName', 'surname'];

    /**
     * The UserGateway instance.
     *
     * @var UserGateway
     */
    protected $userGateway;

    /**
     * The UserStatusLogGateway instance.
     *
     * @var UserStatusLogGateway
     */
    protected $userStatusLogGateway;

    /**
     * The UsernameGenerator instance.
     *
     * @var UsernameGenerator
     */
    protected $usernameGenerator;

    /**
     * The CustomFieldGateway instance.
     *
     * @var CustomFieldGateway
     */
    protected $customFieldGateway;

    /**
     * The PersonalDocumentGateway instance.
     *
     * @var PersonalDocumentGateway
     */
    protected $personalDocumentGateway;

    /**
     * The PasswordPolicy instance to generate password with.
     *
     * @var PasswordPolicy
     */
    protected $passwordPolicy;
    
    /**
     * The Alert instance to calculate student alerts.
     *
     * @var Alert
     */
    protected $alert;

    public function __construct(
        UserGateway $userGateway,
        UserStatusLogGateway $userStatusLogGateway,
        UsernameGenerator $usernameGenerator,
        CustomFieldGateway $customFieldGateway,
        PersonalDocumentGateway $personalDocumentGateway,
        PasswordPolicy $passwordPolicy,
        Alert $alert
    )
    {
        $this->userGateway = $userGateway;
        $this->userStatusLogGateway = $userStatusLogGateway;
        $this->usernameGenerator = $usernameGenerator;
        $this->customFieldGateway = $customFieldGateway;
        $this->personalDocumentGateway = $personalDocumentGateway;
        $this->passwordPolicy = $passwordPolicy;
        $this->alert = $alert;
    }

    public function getViewClass() : string
    {
        return CreateStudentView::class;
    }

    public function isEnabled(FormBuilderInterface $builder)
    {
        return $builder->getConfig('createStudent') == 'Y';
    }

    public function process(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        // Generate user details
        $this->generateUsername($formData, '003');
        $this->generatePassword($formData);

        if (!$formData->has('username') || !$formData->has('passwordStrong')) {
            throw new FormProcessException('Failed to generate username or password');
        }

        // Set and assign default values
        $this->setStatus($formData);
        $this->setDefaults($formData);
        $this->setCustomFields($formData);

        // Create new student account
        $tawasulPersonIDStudent = $this->userGateway->insert($this->getUserData($formData, '003'));
        if (empty($tawasulPersonIDStudent)) {
            throw new FormProcessException('Failed to insert student into the database');
        }

        // Create the status log
        $this->userStatusLogGateway->insert(['tawasulPersonID' => $tawasulPersonIDStudent, 'statusOld' => '', 'statusNew' => $formData->get('status'), 'reason' => __('Created')]);

        // Update existing data
        $this->transferPersonalDocuments($builder, $formData, $tawasulPersonIDStudent);

        // ALERTS: possible change to Privacy alert status, recalculate alerts
        $this->alert->recalculateAlerts($tawasulPersonIDStudent);

        $formData->set('tawasulPersonIDStudent', $tawasulPersonIDStudent);
        $this->setResult($tawasulPersonIDStudent);
    }

    public function rollback(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->has('tawasulPersonIDStudent')) return;

        $this->userGateway->delete($formData->get('tawasulPersonIDStudent'));

        $foreignTable = $builder->getDetail('type') == 'Application' ? 'tawasulAdmissionsApplication' : 'tawasulFormSubmission';
        $foreignTableID = $builder->getConfig('foreignTableID');

        $this->personalDocumentGateway->updatePersonalDocumentOwnership('tawasulPerson', $formData->get('tawasulPersonIDStudent'), $foreignTable, $foreignTableID);

        $formData->set('tawasulPersonIDStudent', null);
    }

    protected function getUserData(FormDataInterface $formData, $tawasulRoleID, $prefix = '')
    {
        return [
            'tawasulRoleIDPrimary'    => $tawasulRoleID,
            'tawasulRoleIDAll'        => $tawasulRoleID,
            'username'               => $formData->get($prefix.'username'),
            'passwordStrong'         => $formData->get($prefix.'passwordStrong'),
            'passwordStrongSalt'     => $formData->get($prefix.'passwordStrongSalt'),
            'status'                 => $formData->get($prefix.'status'),
            'email'                  => $formData->get($prefix.'email'),
            'emailAlternate'         => $formData->get($prefix.'emailAlternate'),
            'title'                  => $formData->get($prefix.'title', ''),
            'surname'                => $formData->get($prefix.'surname'),
            'firstName'              => $formData->get($prefix.'firstName'),
            'preferredName'          => $formData->get($prefix.'preferredName'),
            'officialName'           => $formData->get($prefix.'officialName'),
            'nameInCharacters'       => $formData->get($prefix.'nameInCharacters', ''),
            'gender'                 => $formData->get($prefix.'gender', 'Unspecified'),
            'dob'                    => $formData->get($prefix.'dob'),
            'languageFirst'          => $formData->get($prefix.'languageFirst', ''),
            'languageSecond'         => $formData->get($prefix.'languageSecond', ''),
            'languageThird'          => $formData->get($prefix.'languageThird', ''),
            'countryOfBirth'         => $formData->get($prefix.'countryOfBirth', ''),
            'website'                => $formData->get($prefix.'website', ''),
            'phone1Type'             => $formData->get($prefix.'phone1Type', ''),
            'phone1CountryCode'      => $formData->get($prefix.'phone1CountryCode', ''),
            'phone1'                 => $formData->get($prefix.'phone1', ''),
            'phone2Type'             => $formData->get($prefix.'phone2Type', ''),
            'phone2CountryCode'      => $formData->get($prefix.'phone2CountryCode', ''),
            'phone2'                 => $formData->get($prefix.'phone2', ''),
            'dateStart'              => $formData->get('dateStart'),
            'privacy'                => $formData->get($prefix.'privacy'),
            'dayType'                => $formData->get($prefix.'dayType'),
            'profession'             => $formData->get($prefix.'profession', ''),
            'employer'               => $formData->get($prefix.'employer', ''),
            'jobTitle'               => $formData->get($prefix.'jobTitle', ''),
            'religion'               => $formData->get($prefix.'religion', ''),
            'ethnicity'              => $formData->get($prefix.'ethnicity', ''),
            'studentID'              => $formData->get($prefix.'studentID', ''),
            'emergency1Name'         => $formData->get($prefix.'emergency1Name', ''),
            'emergency1Relationship' => $formData->get($prefix.'emergency1Relationship', ''),
            'emergency1Number1'      => $formData->get($prefix.'emergency1Number1', ''),
            'emergency1Number2'      => $formData->get($prefix.'emergency1Number2', ''),
            'emergency2Name'         => $formData->get($prefix.'emergency2Name', ''),
            'emergency2Relationship' => $formData->get($prefix.'emergency2Relationship', ''),
            'emergency2Number1'      => $formData->get($prefix.'emergency2Number1', ''),
            'emergency2Number2'      => $formData->get($prefix.'emergency2Number2', ''),
            'fields'                 => $formData->get($prefix.'fields', ''),
        ];
    }

    /**
     * Generate a unique username for the new student, or use the pre-defined one.
     *
     * @param FormDataInterface $formData
     */
    protected function generateUsername(FormDataInterface $formData, $tawasulRoleID, $prefix = '')
    {
        if ($formData->has($prefix.'username')) {
            return;
        }

        $this->usernameGenerator->addToken('preferredName', $formData->get($prefix.'preferredName'));
        $this->usernameGenerator->addToken('firstName', $formData->get($prefix.'firstName'));
        $this->usernameGenerator->addToken('surname', $formData->get($prefix.'surname'));

        $formData->set($prefix.'username', $this->usernameGenerator->generateByRole($tawasulRoleID));
    }

    /**
     * Generate a random password
     *
     * @param FormDataInterface $formData
     */
    protected function generatePassword(FormDataInterface $formData, $prefix = '')
    {
        $salt = getSalt();
        $password = $this->passwordPolicy->generate();

        $formData->set($prefix.'password', $password);
        $formData->set($prefix.'passwordStrongSalt', $salt);
        $formData->set($prefix.'passwordStrong', hash('sha256', $salt.$password));
    }

    /**
     * Set the initial status for the student based on the school year of entry.
     *
     * @param FormDataInterface $formData
     */
    protected function setStatus(FormDataInterface $formData, $prefix = '')
    {
        $checkInform = !empty($prefix) ? $formData->getResult('informParents') : $formData->getResult('informStudent');
        $status = $formData->get('schoolYearStatus') == 'Upcoming' && $checkInform != 'Y' ? 'Expected' : 'Full';
        $formData->set($prefix.'status', $status);
    }

    /**
     * Set default values for those not provided by the form.
     *
     * @param FormDataInterface $formData
     */
    protected function setDefaults(FormDataInterface $formData, $prefix = '')
    {
        if (!$formData->has($prefix.'firstName')) {
            $formData->set($prefix.'firstName', $formData->get($prefix.'preferredName'));
        }

        if (!$formData->has($prefix.'preferredName')) {
            $formData->set($prefix.'preferredName', $formData->get($prefix.'firstName'));
        }

        if (!$formData->has($prefix.'officialName')) {
            $formData->set($prefix.'officialName', $formData->get($prefix.'firstName').' '.$formData->get($prefix.'surname'));
        }
    }

    /**
     * Transfer values from form data into json custom field data
     *
     * @param FormDataInterface $formData
     * @param string $prefix
     */
    protected function setCustomFields(FormDataInterface $formData, $prefix = '')
    {
        $customFields = $this->customFieldGateway->selectCustomFields('User', [])->fetchAll();
        $fields = [];

        foreach ($customFields as $field) {
            $id = 'custom'.$field['tawasulCustomFieldID'];
            if (!$formData->has($id)) continue;

            $fields[$field['tawasulCustomFieldID']] = $formData->get($id);
        }

        $formData->set($prefix.'fields', json_encode($fields));
    }

    /**
     * Transfer ownership of personal documents by updating the foreign table
     *
     * @param FormBuilderInterface $builder
     * @param FormDataInterface $formData
     * @param string $tawasulPersonID
     */
    protected function transferPersonalDocuments(FormBuilderInterface $builder, FormDataInterface $formData, string $tawasulPersonID)
    {
        $foreignTable = $builder->getDetail('type') == 'Application' ? 'tawasulAdmissionsApplication' : 'tawasulFormSubmission';
        $foreignTableID = $builder->getConfig('foreignTableID');

        $this->personalDocumentGateway->updatePersonalDocumentOwnership($foreignTable, $foreignTableID, 'tawasulPerson', $tawasulPersonID);
    }
}
