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

namespace TawasulOS\Domain\IndividualNeeds;

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
class INGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulIN';
    private static $primaryKey = 'tawasulINID';

    private static $searchableColumns = ['preferredName', 'surname', 'username'];

    private static $scrubbableKey = 'tawasulPersonID';
    private static $scrubbableColumns = ['strategies' => '','targets' => '','notes' => ''];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryINBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols([
                'tawasulINID', 'tawasulPerson.tawasulPersonID', 'preferredName', 'surname', 'tawasulYearGroup.nameShort AS yearGroup', 'tawasulFormGroup.nameShort AS formGroup', 'dateStart', 'dateEnd', 'status'
            ])
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulIN.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->innerJoin('tawasulINPersonDescriptor', 'tawasulINPersonDescriptor.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        $criteria->addFilterRules([
            'descriptor' => function ($query, $tawasulINDescriptorID) {
                return $query
                    ->where('tawasulINPersonDescriptor.tawasulINDescriptorID = :tawasulINDescriptorID')
                    ->bindValue('tawasulINDescriptorID', $tawasulINDescriptorID);
            },

            'alert' => function ($query, $tawasulAlertLevelID) {
                return $query
                    ->where('tawasulINPersonDescriptor.tawasulAlertLevelID = :tawasulAlertLevelID')
                    ->bindValue('tawasulAlertLevelID', $tawasulAlertLevelID);
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

    public function queryIndividualNeedsPersonDescriptors(QueryCriteria $criteria)
    {
      $query = $this
        ->newQuery()
        ->from('tawasulINPersonDescriptor')
        ->innerJoin('tawasulAlertLevel','tawasulAlertLevel.tawasulAlertLevelID = tawasulINPersonDescriptor.tawasulAlertLevelID')
        ->cols([
          'tawasulINPersonDescriptor.tawasulINPersonDescriptorID',
          'tawasulINPersonDescriptor.tawasulPersonID',
          'tawasulINPersonDescriptor.tawasulINDescriptorID',
          'tawasulINPersonDescriptor.tawasulAlertLevelID',
          'tawasulAlertLevel.tawasulAlertLevelID'
        ]);

      $criteria->addFilterRules([
        'tawasulPersonID' => function($query,$tawasulPersonID)
        {
          return $query
            ->where('tawasulINPersonDescriptor.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID',$tawasulPersonID);
        }
      ]);

      return $this->runQuery($query,$criteria);
    }

    public function queryIndividualNeedsDescriptors(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulINDescriptor')
            ->orderBy(['tawasulINDescriptor.sequenceNumber'])
            ->cols([
                'tawasulINDescriptorID', 'name', 'nameShort', 'description', 'sequenceNumber'
            ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryINCountsBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulYearGroupID = '')
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from('tawasulStudentEnrolment')
            ->cols(['tawasulYearGroup.name as labelName',
                    'tawasulYearGroup.tawasulYearGroupID as labelID',
                    'COUNT(DISTINCT tawasulStudentEnrolment.tawasulPersonID) as studentCount',
                    'COUNT(DISTINCT tawasulINPersonDescriptor.tawasulPersonID) as inCount',
            ])
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulINPersonDescriptor', 'tawasulINPersonDescriptor.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where("tawasulPerson.status='Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)')
            ->bindValue('today', date('Y-m-d'))
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        if (!empty($tawasulYearGroupID)) {
            // Grouped by Form Groups within a Year Group
            $query->cols([
                'tawasulFormGroup.name as labelName',
                'tawasulFormGroup.tawasulFormGroupID as labelID',
                'COUNT(DISTINCT tawasulStudentEnrolment.tawasulPersonID) as studentCount',
                'COUNT(DISTINCT tawasulINPersonDescriptor.tawasulPersonID) as inCount',
            ])
            ->where('tawasulStudentEnrolment.tawasulYearGroupID = :tawasulYearGroupID')
            ->bindValue('tawasulYearGroupID', $tawasulYearGroupID)
            ->groupBy(['tawasulFormGroup.tawasulFormGroupID']);
        } else {
            // Grouped by Year Group
            $query->cols([
                'tawasulYearGroup.name as labelName',
                'tawasulYearGroup.tawasulYearGroupID as labelID',
                'COUNT(DISTINCT tawasulStudentEnrolment.tawasulPersonID) as studentCount',
                'COUNT(DISTINCT tawasulINPersonDescriptor.tawasulPersonID) as inCount',
            ])
            ->groupBy(['tawasulYearGroup.tawasulYearGroupID']);
        }

        return $this->runQuery($query, $criteria);
    }

    public function queryAlertLevels(QueryCriteria $criteria)
    {
      $query = $this
        ->newQuery()
        ->distinct()
        ->from('tawasulAlertLevel')
        ->orderBy(['sequenceNumber'])
        ->cols([
          'tawasulAlertLevel.tawasulAlertLevelID',
          'tawasulAlertLevel.name',
          'tawasulAlertLevel.nameShort',
          'tawasulAlertLevel.description',
          'tawasulAlertLevel.color',
          'tawasulAlertLevel.sequenceNumber'
        ]);

      return $this->runQuery($query,$criteria);
    }

    public function selectIndividualNeedsDescriptorsByStudent($tawasulPersonID)
    {
      $query = $this
        ->newSelect()
        ->from('tawasulINPersonDescriptor')
        ->innerJoin('tawasulAlertLevel','tawasulAlertLevel.tawasulAlertLevelID = tawasulINPersonDescriptor.tawasulAlertLevelID')
        ->cols([
          'tawasulINPersonDescriptor.tawasulINPersonDescriptorID',
          'tawasulINPersonDescriptor.tawasulPersonID',
          'tawasulINPersonDescriptor.tawasulINDescriptorID',
          'tawasulINPersonDescriptor.tawasulAlertLevelID',
          'tawasulAlertLevel.tawasulAlertLevelID'
        ])
        ->where('tawasulINPersonDescriptor.tawasulPersonID = :tawasulPersonID')
        ->bindValue('tawasulPersonID',$tawasulPersonID);

        return $this->runSelect($query);
    }

    public function selectINStudentsBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName, tawasulFormGroup.nameShort as formGroup FROM tawasulPerson JOIN tawasulIN ON (tawasulIN.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID) WHERE status='Full' AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function getINStudentByPersonID($tawasulPersonID)
    {
      $data = ['tawasulPersonID' => $tawasulPersonID];
      $sql = "SELECT surname, preferredName, tawasulIN.* FROM tawasulPerson JOIN tawasulIN ON (tawasulIN.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND tawasulPerson.tawasulPersonID=:tawasulPersonID ORDER BY surname, preferredName";

      return $this->db()->select($sql, $data);
    }

    public function selectINDescriptor()
    {
      $data = [];
      $sql = "SELECT tawasulINDescriptorID as value, name FROM tawasulINDescriptor ORDER BY sequenceNumber";
      
      return $this->db()->select($sql, $data);
    }
}
