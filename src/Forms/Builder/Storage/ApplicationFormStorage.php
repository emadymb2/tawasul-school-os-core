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

namespace TawasulOS\Forms\Builder\Storage;

use TawasulOS\Forms\Builder\AbstractFormStorage;
use TawasulOS\Domain\Admissions\AdmissionsApplicationGateway;

class ApplicationFormStorage extends AbstractFormStorage
{
    private $tawasulAdmissionsApplicationID;
    private $admissionsApplicationGateway;
    private $context;
    
    public function __construct(AdmissionsApplicationGateway $admissionsApplicationGateway)
    {
        $this->admissionsApplicationGateway = $admissionsApplicationGateway;
    }

    public function setContext(string $tawasulFormID, ?string $tawasulFormPageID, string $foreignTable, string $foreignTableID, ?string $owner)
    {
        $this->context = [
            'tawasulFormID'     => $tawasulFormID,
            'tawasulFormPageID' => $tawasulFormPageID,
            'foreignTable'     => $foreignTable,
            'foreignTableID'   => $foreignTableID,
            'owner'            => $owner,
        ];

        return $this;
    }

    public function identify(string $identifier) : int
    {
        if (!empty($this->tawasulAdmissionsApplicationID)) {
            return $this->tawasulAdmissionsApplicationID;
        }

        $values = $this->admissionsApplicationGateway->getApplicationByIdentifier($this->context['tawasulFormID'], $identifier, $this->context['foreignTable'], $this->context['foreignTableID'],  ['tawasulAdmissionsApplicationID']);
        $this->tawasulAdmissionsApplicationID = $values['tawasulAdmissionsApplicationID'] ?? 0;

        return $this->tawasulAdmissionsApplicationID;
    }
    
    public function save(string $identifier) : bool
    {
        $values = $this->admissionsApplicationGateway->getApplicationByIdentifier($this->context['tawasulFormID'], $identifier, $this->context['foreignTable'], $this->context['foreignTableID']);
        
        if (!empty($values)) {
            // Update the existing submission
            $existingData = json_decode($values['data'] ?? '', true) ?? [];
            $data = array_merge($existingData, $this->getData());

            $saved = $this->admissionsApplicationGateway->update($values['tawasulAdmissionsApplicationID'], [
                'data'                   => json_encode($data, JSON_UNESCAPED_UNICODE),
                'result'                 => json_encode($this->getResults(), JSON_UNESCAPED_UNICODE),
                'status'                 => $this->getStatus(),
                'priority'               => $this->getAny('priority', $existingData['priority'] ?? ''),
                'tawasulFormPageID'       => $this->context['tawasulFormPageID'] ?? $values['tawasulFormPageID'],
                'tawasulSchoolYearID'     => $this->getAny('tawasulSchoolYearIDEntry'),
                'tawasulYearGroupID'      => $this->getOrNull('tawasulYearGroupIDEntry'),
                'tawasulFormGroupID'      => $this->getOrNull('tawasulFormGroupIDEntry'),
                'tawasulPaymentIDSubmit'  => $this->getResult('tawasulPaymentIDSubmit'),
                'tawasulPaymentIDProcess' => $this->getResult('tawasulPaymentIDProcess'),
                'timestampModified'      => date('Y-m-d H:i:s'),
                'timestampCreated'       => $this->getStatus() == 'Pending' && $values['status'] == 'Incomplete' 
                    ? date('Y-m-d H:i:s') 
                    : $values['timestampCreated'],
            ]);
        } else {
            // Create a new submission
            $saved = $this->admissionsApplicationGateway->insert($this->context + [
                'identifier'         => $identifier,
                'data'               => json_encode($this->getData(), JSON_UNESCAPED_UNICODE),
                'result'             => json_encode($this->getResults(), JSON_UNESCAPED_UNICODE),
                'tawasulSchoolYearID' => $this->getAny('tawasulSchoolYearIDEntry'),
                'tawasulYearGroupID'  => $this->getOrNull('tawasulYearGroupIDEntry'),
                'tawasulFormGroupID'  => $this->getOrNull('tawasulFormGroupIDEntry'),
                'timestampCreated'   => date('Y-m-d H:i:s'),
            ]);
        }

        return !empty($saved);
    }

    public function load(string $identifier) : bool
    {
        $values = $this->admissionsApplicationGateway->getApplicationByIdentifier($this->context['tawasulFormID'], $identifier, $this->context['foreignTable'], $this->context['foreignTableID']);

        if (empty($values)) return false;

        $this->tawasulAdmissionsApplicationID = $values['tawasulAdmissionsApplicationID'] ?? '';
        $this->setStatus($values['status'] ?? 'Incomplete');
        $this->setData(json_decode($values['data'] ?? '', true) ?? []);
        $this->setResults(json_decode($values['result'] ?? '', true) ?? []);

        return !empty($values);
    }
}
