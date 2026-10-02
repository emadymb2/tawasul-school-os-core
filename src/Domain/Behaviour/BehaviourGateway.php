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

namespace TawasulOS\Domain\Behaviour;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByPerson;

/**
 * Behaviour Gateway
 *
 * @version v17
 * @since   v17
 */
class BehaviourGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulBehaviour';
    private static $primaryKey = 'tawasulBehaviourID';

    private static $searchableColumns = ['tawasulBehaviour.tawasulBehaviourID','tawasulBehaviour.type', 'tawasulBehaviour.descriptor', 'tawasulBehaviour.level', 'tawasulBehaviour.date', 'tawasulBehaviour.timestamp', 'tawasulBehaviour.comment', 'tawasulPerson.preferredName'];

    private static $scrubbableKey = 'tawasulPersonID';
    private static $scrubbableColumns = ['descriptor' => null, 'level' => null, 'comment' => ''];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryBehaviourBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonIDCreator = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulBehaviour.tawasulBehaviourID',
                'tawasulBehaviour.type',
                'tawasulBehaviour.descriptor',
                'tawasulBehaviour.level',
                'tawasulBehaviour.date',
                'tawasulBehaviour.timestamp',
                'tawasulBehaviour.comment',
                'tawasulBehaviour.tawasulPersonIDCreator',
                'tawasulStudentEnrolment.tawasulFormGroupID',
                'tawasulStudentEnrolment.tawasulYearGroupID',
                'student.tawasulPersonID',
                'student.surname',
                'student.preferredName',
                'tawasulFormGroup.nameShort AS formGroup',
                'creator.title AS titleCreator',
                'creator.surname AS surnameCreator',
                'creator.preferredName AS preferredNameCreator',
            ])
            ->innerJoin('tawasulPerson AS student', 'tawasulBehaviour.tawasulPersonID=student.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulPerson AS creator', 'tawasulBehaviour.tawasulPersonIDCreator=creator.tawasulPersonID')
            ->where('tawasulBehaviour.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID=tawasulBehaviour.tawasulSchoolYearID');

        if (!empty($tawasulPersonIDCreator)) {
            $query->where('tawasulBehaviour.tawasulPersonIDCreator = :tawasulPersonIDCreator')
                ->bindValue('tawasulPersonIDCreator', $tawasulPersonIDCreator);
        }

        $criteria->addFilterRules([
            'student' => function ($query, $tawasulPersonID) {
                return $query
                    ->where('tawasulBehaviour.tawasulPersonID = :tawasulPersonID')
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
            'type' => function ($query, $type) {
                return $query
                    ->where('tawasulBehaviour.type = :type')
                    ->bindValue('type', $type);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryBehaviourByFormGroup(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulFormGroupID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulBehaviour.tawasulBehaviourID',
                'tawasulBehaviour.type',
                'tawasulBehaviour.descriptor',
                'tawasulBehaviour.level',
                'tawasulBehaviour.date',
                'tawasulBehaviour.timestamp',
                'tawasulBehaviour.comment',
                'tawasulBehaviour.tawasulPersonIDCreator',
                'tawasulStudentEnrolment.tawasulFormGroupID',
                'tawasulStudentEnrolment.tawasulYearGroupID',
                'student.tawasulPersonID',
                'student.surname',
                'student.preferredName',
                'tawasulFormGroup.nameShort AS formGroup',
                'creator.title AS titleCreator',
                'creator.surname AS surnameCreator',
                'creator.preferredName AS preferredNameCreator',
            ])
            ->innerJoin('tawasulPerson AS student', 'tawasulBehaviour.tawasulPersonID=student.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'student.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulBehaviour.tawasulSchoolYearID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulPerson AS creator', 'tawasulBehaviour.tawasulPersonIDCreator=creator.tawasulPersonID')
            ->where('tawasulBehaviour.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStudentEnrolment.tawasulFormGroupID = :tawasulFormGroupID')
            ->bindValue('tawasulFormGroupID', $tawasulFormGroupID);

        return $this->runQuery($query, $criteria);
    }

    public function queryBehaviourPatternsBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID',
                'tawasulStudentEnrolmentID',
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulYearGroup.nameShort AS yearGroup',
                'tawasulFormGroup.nameShort AS formGroup',
                'tawasulPerson.dateStart',
                'tawasulPerson.dateEnd',
                "COUNT(DISTINCT tawasulBehaviourID) AS count",
            ])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
            ->leftJoin('tawasulBehaviour', "tawasulBehaviour.tawasulPersonID=tawasulPerson.tawasulPersonID 
                AND tawasulBehaviour.tawasulSchoolYearID=tawasulStudentEnrolment.tawasulSchoolYearID")
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulPerson.status = 'Full'")
            ->groupBy(['tawasulPerson.tawasulPersonID']);

        $criteria->addFilterRules([
            'type' => function ($query, $type) {
                return $query
                    ->where('(tawasulBehaviourID IS NULL OR tawasulBehaviour.type = :type)')
                    ->bindValue('type', $type);
            },
            'descriptor' => function ($query, $descriptor) {
                return $query
                    ->where('(tawasulBehaviourID IS NULL OR tawasulBehaviour.descriptor = :descriptor)')
                    ->bindValue('descriptor', $descriptor);
            },
            'level' => function ($query, $level) {
                return $query
                    ->where('(tawasulBehaviourID IS NULL OR tawasulBehaviour.level = :level)')
                    ->bindValue('level', $level);
            },
            'fromDate' => function ($query, $fromDate) {
                return $query
                    ->where('(tawasulBehaviourID IS NULL OR tawasulBehaviour.date >= :fromDate)')
                    ->bindValue('fromDate', $fromDate);
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
            'minimumCount' => function ($query, $minimumCount) {
                return $query
                    ->having('count >= :minimumCount')
                    ->bindValue('minimumCount', $minimumCount);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryBehaviourLettersBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulBehaviourLetter')
            ->cols([
                'tawasulBehaviourLetter.*',
                'tawasulPerson.tawasulPersonID',
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulFormGroup.nameShort AS formGroup',
            ])
            ->innerJoin('tawasulPerson', 'tawasulBehaviourLetter.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID 
                AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulBehaviourLetter.tawasulSchoolYearID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->where('tawasulBehaviourLetter.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulPerson.status = 'Full'");

        $criteria->addFilterRules([
            'student' => function ($query, $tawasulPersonID) {
                return $query
                    ->where('tawasulBehaviourLetter.tawasulPersonID = :tawasulPersonID')
                    ->bindValue('tawasulPersonID', $tawasulPersonID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryBehaviourRecordsByPerson(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID, $tawasulPersonIDCreator = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulBehaviour.*',
                'creator.title AS titleCreator',
                'creator.surname AS surnameCreator',
                'creator.preferredName AS preferredNameCreator',
            ])
            ->leftJoin('tawasulPerson AS creator', 'tawasulBehaviour.tawasulPersonIDCreator=creator.tawasulPersonID')
            ->where('tawasulBehaviour.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulBehaviour.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        if (!empty($tawasulPersonIDCreator)) {
            $query   
                ->where('tawasulBehaviour.tawasulPersonIDCreator = :tawasulPersonIDCreator')
                ->bindValue('tawasulPersonIDCreator', $tawasulPersonIDCreator);
            }    

        return $this->runQuery($query, $criteria);
    }

    public function queryAllBehaviourStudentsBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonIDCreator = null)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulStudentEnrolmentID', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.image_240',  'tawasulYearGroup.tawasulYearGroupID', 'tawasulYearGroup.nameShort AS yearGroup', 'tawasulFormGroup.tawasulFormGroupID', 'tawasulFormGroup.nameShort AS formGroup', 'tawasulStudentEnrolment.rollOrder', 'tawasulPerson.dateStart', 'tawasulPerson.dateEnd', 'tawasulPerson.status', "'Student' as roleCategory"
            ])
            ->leftJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->leftJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->leftJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        if ($criteria->hasFilter('all')) {
            $query->innerJoin('tawasulRole', 'FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)')
                  ->where("tawasulRole.category='Student'");
        } else {
            $query->where("tawasulStudentEnrolment.tawasulStudentEnrolmentID IS NOT NULL")
                  ->where("tawasulPerson.status = 'Full'")
                  ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
                  ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
                  ->bindValue('today', date('Y-m-d'));
        }

        if (!empty($tawasulPersonIDCreator)) {
            $query
            ->innerJoin('tawasulBehaviour', 'tawasulBehaviour.tawasulPersonID = tawasulPerson.tawasulPersonID')    
            ->where('tawasulBehaviour.tawasulPersonIDCreator = :tawasulPersonIDCreator ')
            ->bindValue('tawasulPersonIDCreator', $tawasulPersonIDCreator)
            ->groupBy(['tawasulPerson.tawasulPersonID']);
        }

        return $this->runQuery($query, $criteria);
    }

    public function getBehaviourDetails($tawasulSchoolYearID, $tawasulBehaviourID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulBehaviourID' => $tawasulBehaviourID];
        $sql = 'SELECT tawasulBehaviour.*, student.surname AS surnameStudent, student.preferredName AS preferredNameStudent, creator.surname AS surnameCreator, creator.preferredName AS preferredNameCreator, creator.title as titleCreator, creator.image_240 as imageCreator FROM tawasulBehaviour JOIN tawasulPerson AS student ON (tawasulBehaviour.tawasulPersonID=student.tawasulPersonID) JOIN tawasulPerson AS creator ON (tawasulBehaviour.tawasulPersonIDCreator=creator.tawasulPersonID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulBehaviourID=:tawasulBehaviourID ORDER BY date DESC';
        
        return $this->db()->selectOne($sql, $data);
    }

    public function getBehaviourDetailsByCreator($tawasulSchoolYearID, $tawasulBehaviourID, $tawasulPersonID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulBehaviourID' => $tawasulBehaviourID, 'tawasulPersonID' => $tawasulPersonID);
        $sql = 'SELECT tawasulBehaviour.*, student.surname AS surnameStudent, student.preferredName AS preferredNameStudent, creator.surname AS surnameCreator, creator.preferredName AS preferredNameCreator, creator.title as titleCreator, creator.image_240 FROM tawasulBehaviour JOIN tawasulPerson AS student ON (tawasulBehaviour.tawasulPersonID=student.tawasulPersonID) JOIN tawasulPerson AS creator ON (tawasulBehaviour.tawasulPersonIDCreator=creator.tawasulPersonID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulBehaviourID=:tawasulBehaviourID AND tawasulPersonIDCreator=:tawasulPersonID ORDER BY date DESC';

        return $this->db()->selectOne($sql, $data);
    }

    public function selectMultipleStudentsOfOneIncident($tawasulMultiIncidentID)
    {
        $data = ['tawasulMultiIncidentID' => $tawasulMultiIncidentID];
        $sql = 'SELECT tawasulBehaviour.tawasulPersonID AS tawasulPersonID, student.preferredName AS preferredNameStudent, student.surname AS surnameStudent FROM tawasulBehaviour JOIN tawasulPerson AS student ON (tawasulBehaviour.tawasulPersonID=student.tawasulPersonID)WHERE tawasulMultiIncidentID = :tawasulMultiIncidentID ORDER BY preferredNameStudent';

        return $this->db()->select($sql, $data);
    }

    public function selectBehavioursByCreator($tawasulSchoolYearID, $tawasulPersonIDCreator, $tawasulBehaviourID) {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonIDCreator' => $tawasulPersonIDCreator, 'tawasulBehaviourID' => $tawasulBehaviourID];                
        $sql = 'SELECT tawasulBehaviour.tawasulBehaviourID as value, CONCAT(tawasulPerson.firstName, " ", tawasulPerson.surname, "  (", DATE_FORMAT(tawasulBehaviour.date,  "%d/%c/%Y"), "), ", tawasulBehaviour.type, ", ", tawasulBehaviour.descriptor) FROM tawasulBehaviour JOIN tawasulPerson ON (tawasulBehaviour.tawasulPersonID = tawasulPerson.tawasulPersonID) WHERE (tawasulBehaviour.tawasulBehaviourID != :tawasulBehaviourID) AND (tawasulBehaviour.tawasulPersonIDCreator = :tawasulPersonIDCreator) AND (tawasulSchoolYearID=:tawasulSchoolYearID) AND (tawasulBehaviour.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) ORDER BY tawasulBehaviour.date DESC';

        return $this->db()->select($sql, $data);
    }

    public function selectBehavioursByCreatorAndStudent($tawasulSchoolYearID, $tawasulPersonIDCreator, $tawasulPersonID) {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonIDCreator' => $tawasulPersonIDCreator, 'tawasulPersonID' => $tawasulPersonID, 'today' => date('Y-m-d')];               
        $sql = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.nameShort AS formGroup FROM tawasulBehaviour JOIN tawasulPerson ON (tawasulBehaviour.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulBehaviour.tawasulPersonIDCreator=:tawasulPersonIDCreator AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL OR dateEnd>=:today) AND tawasulPerson.tawasulPersonID=:tawasulPersonID GROUP BY tawasulPerson.tawasulPersonID, yearGroup, formGroup ORDER BY surname, preferredName";
        
        return $this->db()->select($sql, $data);
    }

    public function updateMultiIncidentIDByBehaviourID($tawasulBehaviourID, $tawasulMultiIncidentID)
    {
        $data = ['tawasulBehaviourID' => $tawasulBehaviourID, 'tawasulMultiIncidentID' => $tawasulMultiIncidentID];
        $sql = 'UPDATE tawasulBehaviour SET tawasulMultiIncidentID=:tawasulMultiIncidentID WHERE tawasulBehaviourID=:tawasulBehaviourID';
                
        return $this->db()->update($sql, $data);
    }
    
    public function getBehaviourRecordByID($tawasulBehaviourID)
    {
        $data = ['tawasulBehaviourID' => $tawasulBehaviourID];
        $sql = "SELECT tawasulPerson.tawasulPersonID, tawasulPerson.preferredName, tawasulPerson.surname FROM tawasulBehaviour JOIN tawasulPerson ON (tawasulBehaviour.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulBehaviour.tawasulSchoolYearID=tawasulStudentEnrolment.tawasulSchoolYearID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL OR dateEnd>='".date('Y-m-d')."') AND tawasulBehaviourID=:tawasulBehaviourID";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectNegativeBehaviourByStudentAndDate($tawasulPersonID, $days = 60)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'date' => date('Y-m-d', (time() - (24 * 60 * 60 * $days)))];
        $sql = "SELECT * FROM tawasulBehaviour WHERE tawasulPersonID=:tawasulPersonID AND type = 'Negative' AND date>:date";
        
        return $this->db()->select($sql, $data);
    }
}
