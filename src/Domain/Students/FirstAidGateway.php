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

namespace TawasulOS\Domain\Students;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByPerson;

/**
 * @version v16
 * @since   v16
 */
class FirstAidGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulFirstAid';
    private static $primaryKey = 'tawasulFirstAidID';

    private static $searchableColumns = [''];
    
    private static $scrubbableKey = 'tawasulPersonIDPatient';
    private static $scrubbableColumns = ['description' => '','actionTaken' => '','followUp' => ''];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryFirstAidBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulFirstAidID', 'tawasulFirstAid.date', 'tawasulFirstAid.timeIn', 'tawasulFirstAid.timeOut', 'tawasulFirstAid.description', 'tawasulFirstAid.actionTaken', 'tawasulFirstAid.followUp', 'tawasulFirstAid.date', 'patient.surname AS surnamePatient', 'patient.preferredName AS preferredNamePatient', 'tawasulFirstAid.tawasulPersonIDPatient', 'tawasulFormGroup.name as formGroup', 'firstAider.title', 'firstAider.surname AS surnameFirstAider', 'firstAider.preferredName AS preferredNameFirstAider', 'timestamp'
            ])
            ->innerJoin('tawasulPerson AS patient', 'tawasulFirstAid.tawasulPersonIDPatient=patient.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'patient.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulPerson AS firstAider', 'tawasulFirstAid.tawasulPersonIDFirstAider=firstAider.tawasulPersonID')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->where('tawasulFirstAid.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        $criteria->addFilterRules([
            'student' => function ($query, $tawasulPersonID) {
                return $query
                    ->where('tawasulFirstAid.tawasulPersonIDPatient = :tawasulPersonID')
                    ->bindValue('tawasulPersonID', $tawasulPersonID);
            },

            'formGroup' => function ($query, $tawasulFormGroupID) {
                return $query
                    ->where('tawasulStudentEnrolment.tawasulFormGroupID = :tawasulFormGroupID')
                    ->bindValue('tawasulFormGroupID', $tawasulFormGroupID);
            },

            'yearGroup' => function ($query, $tawasulYearGroupID) {
                return $query
                    ->where('tawasulStudentEnrolment.tawasulYearGroupID = :tawasulYearGroupID')
                    ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryFirstAidByStudent(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulFirstAidID', 'tawasulFirstAid.date', 'tawasulFirstAid.timeIn', 'tawasulFirstAid.timeOut', 'tawasulFirstAid.description', 'tawasulFirstAid.actionTaken', 'tawasulFirstAid.followUp', 'tawasulFirstAid.date', 'patient.surname AS surnamePatient', 'patient.preferredName AS preferredNamePatient', 'tawasulFirstAid.tawasulPersonIDPatient', 'tawasulFormGroup.name as formGroup', 'firstAider.title', 'firstAider.surname AS surnameFirstAider', 'firstAider.preferredName AS preferredNameFirstAider', 'timestamp'
            ])
            ->innerJoin('tawasulPerson AS patient', 'tawasulFirstAid.tawasulPersonIDPatient=patient.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'patient.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulPerson AS firstAider', 'tawasulFirstAid.tawasulPersonIDFirstAider=firstAider.tawasulPersonID')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulFirstAid.tawasulPersonIDPatient = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);

        return $this->runQuery($query, $criteria);
    }

    public function queryFollowUpByFirstAidID($tawasulFirstAidID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'tawasulFirstAidFollowUp.*',
                'tawasulFirstAidFollowUp.followUp as comment',
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulPerson.image_240'
            ])
            ->innerJoin('tawasulFirstAidFollowUp', 'tawasulFirstAidFollowUp.tawasulFirstAidID=tawasulFirstAid.tawasulFirstAidID')
            ->innerJoin('tawasulPerson', 'tawasulFirstAidFollowUp.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where('tawasulFirstAidFollowUp.tawasulFirstAidID=:tawasulFirstAidID')
            ->bindValue('tawasulFirstAidID', $tawasulFirstAidID);

        return $this->runSelect($query);
    }
}
