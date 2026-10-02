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

use TawasulOS\Contracts\Services\Session;
use TawasulOS\Domain\School\HouseGateway;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Forms\Builder\AbstractFormProcess;
use TawasulOS\Forms\Builder\FormBuilderInterface;
use TawasulOS\Forms\Builder\Storage\FormDataInterface;
use TawasulOS\Forms\Builder\View\AssignHouseView;

class AssignHouse extends AbstractFormProcess implements ViewableProcess
{
    protected $requiredFields = ['tawasulSchoolYearIDEntry', 'tawasulYearGroupIDEntry', 'gender'];

    private $session;
    private $userGateway;
    private $houseGateway;

    public function __construct(Session $session, UserGateway $userGateway, HouseGateway $houseGateway)
    {
        $this->session = $session;
        $this->userGateway = $userGateway;
        $this->houseGateway = $houseGateway;
    }

    public function getViewClass() : string
    {
        return AssignHouseView::class;
    }

    public function isEnabled(FormBuilderInterface $builder)
    {
        return $builder->getConfig('autoHouseAssign') == 'Y';
    }

    public function process(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->has('tawasulPersonIDStudent')) return;

        $assignedHouse = null;
        $tawasulFamilyID = $formData->get('tawasulFamilyID');
        $tawasulPersonIDStudent = $formData->get('tawasulPersonIDStudent');

        if (!empty($tawasulFamilyID)) {
            $assignedHouse = $this->houseGateway->selectExistingHouseByFamilyID($tawasulFamilyID, $tawasulPersonIDStudent);
        }

        // Fallback to gender-based assignment if no family assignment found
        if (empty($assignedHouse)) {
            $assignedHouse = $this->houseGateway->selectAssignedHouseByGender($this->session->get('tawasulSchoolYearIDCurrent'), $formData->get('tawasulYearGroupIDEntry'), $formData->get('gender'))->fetch();
        }

        if (empty($assignedHouse)) return;

        $formData->set('tawasulHouseID', $assignedHouse['tawasulHouseID']);

        // Update the user data for this student
        $this->userGateway->update($tawasulPersonIDStudent, [
            'tawasulHouseID' => $formData->get('tawasulHouseID'),
        ]);

        $this->setResult($assignedHouse['house']);
    }

    public function rollback(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->has('tawasulPersonIDStudent')) return;

        $this->userGateway->update($formData->get('tawasulPersonIDStudent'), [
            'tawasulHouseID' => null,
        ]);

        $formData->setResult('assignHouseResult', false);
    }
}
