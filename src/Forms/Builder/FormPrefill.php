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

namespace TawasulOS\Forms\Builder;

use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\User\FamilyGateway;
use TawasulOS\Forms\Builder\Storage\FormDataInterface;
use TawasulOS\Contracts\Services\Session;
use TawasulOS\Domain\Admissions\AdmissionsApplicationGateway;
use TawasulOS\Domain\Admissions\AdmissionsAccountGateway;

class FormPrefill
{
    protected $session;
    protected $userGateway;
    protected $familyGateway;

    protected $prefillable = [];
    protected $prefilled = false;

    public function __construct(Session $session, UserGateway $userGateway, FamilyGateway $familyGateway)
    {
        $this->session = $session;
        $this->userGateway = $userGateway;
        $this->familyGateway = $familyGateway;
    }

    public function isPrefilled()
    {
        return $this->prefilled;
    }

    /**
     * Prefill values from the most recent application form attached to this account
     *
     * @param AdmissionsApplicationGateway $admissionsApplicationGateway
     * @param array $recentApplication
     * @return self
     */
    public function loadApplicationData(AdmissionsAccountGateway $admissionsAccountGateway, AdmissionsApplicationGateway $admissionsApplicationGateway, string $tawasulFormID, ?string $accessID, ?string $accessToken)
    {
        // Check if this account can prefill application form data
        $accountCheck = $admissionsAccountGateway->getAccountByAccessToken($accessID, $accessToken);
        if (empty($accountCheck)) {
            return $this;
        }

        $recentApplication = $admissionsApplicationGateway->selectMostRecentApplicationByContext($tawasulFormID, 'tawasulAdmissionsAccount', $accountCheck['tawasulAdmissionsAccountID'])->fetch();

        if (!empty($recentApplication)) {
            $this->prefillable = json_decode($recentApplication['data'] ?? '', true);
        }

        return $this;
    }

    /**
     * Prefill parent and family data, if this account has access to it.
     *
     * @param AdmissionsAccountGateway $admissionsAccountGateway
     * @param string $tawasulPersonID
     * @param string $accessID
     * @param string $accessToken
     * @return self
     */
    public function loadPersonalData(AdmissionsAccountGateway $admissionsAccountGateway, ?string $tawasulPersonID, ?string $accessID, ?string $accessToken)
    {
        if (empty($tawasulPersonID)) return $this;

        $canAccessAccount = $canAccessData = false;

        // Check if this account can prefill user data
        $accountCheck = $admissionsAccountGateway->getAccountByAccessToken($accessID, $accessToken);
        $canAccessAccount = !empty($accountCheck) && $tawasulPersonID == $accountCheck['tawasulPersonID'];
        $canAccessData = (!empty($accountCheck)) || ($this->session->get('tawasulPersonID') == $tawasulPersonID);

        if ($canAccessAccount && $canAccessData) {
            // Load and prefill values for Parent 1
            $person = $this->userGateway->getSafeUserData($tawasulPersonID);
            foreach ($person ?? [] as $fieldName => $value) {
                $this->prefillable['parent1'.$fieldName] = $value;
            }

            // Load and prefill values for the family
            $family = $this->familyGateway->getByID($accountCheck['tawasulFamilyID'] ?? '');
            foreach ($family ?? [] as $fieldName => $value) {
                if ($fieldName == 'name') $fieldName = 'familyName';
                $this->prefillable[$fieldName] = $value;
            }
        }

        return $this;
    }

    /**
     * Only prefill fields that are marked as prefillable, and don't already exist.
     *
     * @param FormBuilderInterface $formBuilder
     * @param FormDataInterface $formData
     * @param array $values
     * @return self
     */
    public function prefill(FormBuilderInterface $formBuilder, FormDataInterface $formData, $pageNumber, &$values)
    {
        // Only prefill fields that are marked as prefillable, and don't already exist
        foreach ($this->prefillable as $fieldName => $value) {
            if ($formData->exists($fieldName)) continue;
            if (!$formBuilder->hasField($fieldName)) continue;

            $field = $formBuilder->getField($fieldName);
            if ($field['pageNumber'] != $pageNumber && $pageNumber > 0) continue;
            if (empty($field['prefill']) || $field['prefill'] == 'N') continue;

            $values[$fieldName] = $value;
            $this->prefilled = true;
        }

        return $this;
    }
}
