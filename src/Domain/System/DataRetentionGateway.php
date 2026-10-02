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

namespace TawasulOS\Domain\System;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\User\FamilyGateway;
use TawasulOS\Domain\Finance\InvoiceeGateway;
use TawasulOS\Domain\Students\MedicalGateway;
use TawasulOS\Domain\User\FamilyAdultGateway;
use TawasulOS\Domain\User\FamilyChildGateway;
use TawasulOS\Domain\Students\FirstAidGateway;
use TawasulOS\Domain\IndividualNeeds\INGateway;
use TawasulOS\Domain\Staff\StaffAbsenceGateway;
use TawasulOS\Domain\Behaviour\BehaviourGateway;
use TawasulOS\Domain\Students\StudentNoteGateway;
use TawasulOS\Domain\User\PersonalDocumentGateway;
use TawasulOS\Domain\DataUpdater\FamilyUpdateGateway;
use TawasulOS\Domain\DataUpdater\PersonUpdateGateway;
use TawasulOS\Domain\Students\ApplicationFormGateway;
use TawasulOS\Domain\Behaviour\BehaviourLetterGateway;
use TawasulOS\Domain\DataUpdater\FinanceUpdateGateway;
use TawasulOS\Domain\DataUpdater\MedicalUpdateGateway;
use TawasulOS\Domain\IndividualNeeds\INArchiveGateway;
use TawasulOS\Domain\Students\FirstAidFollowupGateway;
use TawasulOS\Domain\Students\MedicalConditionGateway;
use TawasulOS\Domain\Staff\StaffApplicationFormGateway;
use TawasulOS\Domain\Students\ApplicationFormFileGateway;
use TawasulOS\Domain\Staff\StaffApplicationFormFileGateway;
use TawasulOS\Domain\IndividualNeeds\INInvestigationGateway;
use TawasulOS\Domain\DataUpdater\MedicalConditionUpdateGateway;
use TawasulOS\Domain\IndividualNeeds\INPersonDescriptorGateway;
use TawasulOS\Domain\IndividualNeeds\INInvestigationContributionGateway;

/**
 * Data Retention Gateway
 *
 * @version v21
 * @since   v21
 */
class DataRetentionGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulDataRetention';
    private static $primaryKey = 'tawasulDataRetentionID';

    public function getDomains()
    {
        return [
            'Personal Documents' => [
                'description' => __('Clear all personal documents such as passports, ID cards, birth certificates and visas.'),
                'gateways' => [
                    PersonalDocumentGateway::class,
                ],
            ],
            'Student Personal Data' => [
                'description' => __('Clear personal data such as passwords, addresses, phone numbers, id numbers, etc.'),
                'context' => ['Student'],
                'gateways' => [
                    UserGateway::class,
                    PersonUpdateGateway::class,
                    StudentNoteGateway::class,
                ],
            ],
            'Medical Data' => [
                'description' => __('Clear student medical records including medical conditions and first aid records.'),
                'context' => ['Student'],
                'gateways' => [
                    MedicalGateway::class,
                    MedicalConditionGateway::class,
                    MedicalUpdateGateway::class,
                    MedicalConditionUpdateGateway::class,
                    FirstAidGateway::class,
                    FirstAidFollowupGateway::class,
                ],
            ],
            'Finance Data' => [
                'description' => __('Clear student finance data including billing information. Invoices will be retained.'),
                'context' => ['Student'],
                'gateways' => [
                    InvoiceeGateway::class,
                    FinanceUpdateGateway::class,
                ],
            ],
            'Behaviour Records' => [
                'description' => __('Clear all behaviour data including positive and negative behaviour records and any behaviour letters sent to parents.'),
                'context' => ['Student'],
                'gateways' => [
                    BehaviourGateway::class,
                    BehaviourLetterGateway::class
                ]
            ],
            'Individual Needs' => [
                'description' => __('Clear individual needs records including archived records and individual needs investigations.'),
                'context' => ['Student'],
                'gateways' => [
                    INGateway::class,
                    INArchiveGateway::class,
                    INPersonDescriptorGateway::class,
                    INInvestigationGateway::class,
                    INInvestigationContributionGateway::class,
                ],
            ],
            'Family Data'=> [
                'description' => __('Clear family data such as address, country, languages and marital status.'),
                'context' => ['Student', 'Parent', 'Staff', 'Other'],
                'gateways' => [
                    FamilyGateway::class,
                    FamilyUpdateGateway::class,
                    FamilyAdultGateway::class,
                    FamilyChildGateway::class,
                ],
            ],
            'Parent Personal Data'=> [
                'description' => __('Clear personal data such as passwords, addresses, phone numbers, id numbers, etc.'),
                'context' => ['Parent'],
                'gateways' => [
                    UserGateway::class,
                    PersonUpdateGateway::class,

                ],
            ],
            'Staff Personal Data' =>  [
                'description' => __('Clear personal data such as passwords, addresses, phone numbers, id numbers, etc.'),
                'context' => ['Staff'],
                'gateways' => [
                    UserGateway::class,
                    PersonUpdateGateway::class,
                    StaffAbsenceGateway::class,
                ],
            ],
            'Other Users Personal Data'=>  [
                'description' => __('Clear personal data such as passwords, addresses, phone numbers, id numbers, etc.'),
                'context' => ['Other'],
                'gateways' => [
                    UserGateway::class,
                    PersonUpdateGateway::class,
                ],
            ],
            'Student Application Forms' => [
                'description' => __('Clear all personal data submitted through the student application form.'),
                'context' => ['Student'],
                'gateways' => [
                    ApplicationFormGateway::class,
                    ApplicationFormFileGateway::class,
                ],
            ],
            'Staff Application Forms' => [
                'description' => __('Clear all personal data submitted through the staff application form.'),
                'gateways' => [
                    StaffApplicationFormGateway::class,
                    StaffApplicationFormFileGateway::class,
                ],
            ],
        ];
    }
}
