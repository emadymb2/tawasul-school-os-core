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

namespace TawasulOS\Domain\Messenger;

use TawasulOS\Contracts\Database\Connection;
use TawasulOS\Contracts\Services\Session;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\User\RoleGateway;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Data\Validator;

/**
 * MessengerGateway
 *
 * @version v19
 * @since   v19
 */
class MessengerGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulMessenger';
    private static $primaryKey = 'tawasulMessengerID';
    private static $searchableColumns = ['tawasulMessenger.subject', 'tawasulMessenger.body'];


    /**
     * @var Session
     */
    private $session;

    /**
     * @var RoleGateway
     */
    private $roleGateway;

    /**
     * @var Validator
     */
    private $validator;

    public function __construct(
        Connection $db,
        Session $session,
        Validator $validator,
        RoleGateway $roleGateway
    )
    {
        parent::__construct($db);
        $this->session = $session;
        $this->roleGateway = $roleGateway;
        $this->validator = $validator;
    }

    /**
     * Queries the list of messages for the Manage Messages page, optionally filtered for the current user.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryMessages(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulMessenger.tawasulMessengerID', 'tawasulMessenger.subject', 'tawasulMessenger.timestamp', 'tawasulMessenger.email', 'tawasulMessenger.messageWall', 'tawasulMessenger.sms', 'tawasulMessenger.messageWall_dateStart', 'tawasulMessenger.messageWall_dateEnd', 'tawasulMessenger.emailReceipt', 'tawasulMessenger.confidential', 'tawasulMessenger.status', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulRole.category',
            ])
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulMessenger.tawasulPersonID')
            ->innerJoin('tawasulRole', 'tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary')
            ->where('tawasulMessenger.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        if (!empty($tawasulPersonID)) {
            $query->where('tawasulMessenger.tawasulPersonID=:tawasulPersonID')
                ->bindValue('tawasulPersonID', $tawasulPersonID);
        }

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('tawasulMessenger.status=:status')
                    ->bindValue('status', $status);
            },
            'confidential' => function ($query, $tawasulPersonIDCreatedBy) {
                return $query
                    ->where('(tawasulMessenger.confidential="N" OR (tawasulMessenger.confidential="Y" AND tawasulMessenger.tawasulPersonID=:tawasulPersonIDCreatedBy))')
                    ->bindValue('tawasulPersonIDCreatedBy', $tawasulPersonIDCreatedBy);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function getRecentMessageWallTimestamp()
    {
        $sql = "SELECT UNIX_TIMESTAMP(timestamp) FROM tawasulMessenger WHERE messageWall='Y' ORDER BY timestamp DESC LIMIT 1";

        return $this->db()->selectOne($sql);
    }

    public function getSendingMessages()
    {
        $query = $this
            ->newSelect()
            ->from('tawasulLog')
            ->cols(['tawasulLog.tawasulLogID', 'tawasulLog.serialisedArray'])
            ->where("tawasulLog.title='Background Process - MessageProcess'")
            ->where("(tawasulLog.serialisedArray LIKE '%s:7:\"Running\";%' OR tawasulLog.serialisedArray LIKE '%s:7:\"Ready\";%')")
            ->orderBy(['tawasulLog.timestamp DESC']);

        $logs = $this->runSelect($query)->fetchAll();

        return array_filter(array_reduce($logs, function ($group, $item) {
            $item['data'] = unserialize($item['serialisedArray']) ?? [];
            $tawasulMessengerID =  str_pad(($item['data']['data'][0] ?? 0), 12, '0', STR_PAD_LEFT);

            if (!empty($tawasulMessengerID)) {
                $group[$tawasulMessengerID] = $item['tawasulLogID'];
            }

            return $group;
        }, []));
    }

    public function getMessageDetailsByID($tawasulMessengerID)
    {
        $data = ['tawasulMessengerID' => $tawasulMessengerID];
        $sql = "SELECT tawasulMessenger.*, title, surname, preferredName FROM tawasulMessenger LEFT JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulMessengerID=:tawasulMessengerID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getMessageDetailsByIDAndOwner($tawasulMessengerID, $tawasulPersonID)
    {
        $data = ['tawasulMessengerID' => $tawasulMessengerID, 'tawasulPersonID' => $tawasulPersonID];
        $sql="SELECT tawasulMessenger.*, title, surname, preferredName FROM tawasulMessenger LEFT JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulMessengerID=:tawasulMessengerID AND tawasulMessenger.tawasulPersonID=:tawasulPersonID";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectMessageTargetsByID($tawasulMessengerID)
    {
        $data = ['tawasulMessengerID' => $tawasulMessengerID];
        $sql = "SELECT * FROM tawasulMessengerTarget WHERE tawasulMessengerID=:tawasulMessengerID ORDER BY type";

        return $this->db()->select($sql, $data);
    }

    /**
     * Retrieve messages into the format specified by $mode parameter.
     *
     * @param string $mode  Mode may be:
     *                      "print" (return table of messages); or
     *                      "array" (return array of messages); or
     *                      "count" (return message count); or
     *                      "result" (return database query result).
     *                      Default: "print".
     * @param string $date  Date in YYYY-MM-DD format. Default: today's date.
     *
     * @return string|int|Result  Format specified by $mode parameter.
     */
    public function getMessages(string $mode = 'print', string $date = '')
    {
        $session = $this->session;
        $connection2 = $this->db()->getConnection();

        $return = '';
        $dataPosts = [];

        if ($date == '') {
            $date = date('Y-m-d');
        }
        if ($mode != 'print' and $mode != 'count' and $mode != 'result' and $mode != 'array') {
            $mode = 'print';
        }

        // Work out all role categories this user has, ignoring "Other"
        $roles = $session->get('tawasulRoleIDAll');
        $roleCategory = '';
        $staff = false;
        $student = false;
        $parent = false;
        for ($i = 0; $i < count($roles); ++$i) {
            $roleCategory = $this->roleGateway->getRoleCategory($roles[$i][0]);
            if ($roleCategory == 'Staff') {
                $staff = true;
            } elseif ($roleCategory == 'Student') {
                $student = true;
            } elseif ($roleCategory == 'Parent') {
                $parent = true;
            }
        }

        // If parent get a list of student IDs
        if ($parent) {
            $children = [];

            $data = ['tawasulPersonID' => $session->get('tawasulPersonID')];
            $sql = "SELECT * FROM tawasulFamilyAdult WHERE tawasulPersonID=:tawasulPersonID AND childDataAccess='Y'";
            $result = $connection2->prepare($sql);
            $result->execute($data);
            while ($row = $result->fetch()) {
                $dataChild = array('tawasulFamilyID' => $row['tawasulFamilyID'], 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'today' => date('Y-m-d'));
                $sqlChild = "SELECT * FROM tawasulFamilyChild JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) WHERE tawasulFamilyID=:tawasulFamilyID AND tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today) AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY surname, preferredName ";
                $resultChild = $connection2->prepare($sqlChild);
                $resultChild->execute($dataChild);

                while ($rowChild = $resultChild->fetch()) {
                    $children[] = $rowChild['tawasulPersonID'];
                }
            }
        }

        $dataPosts['date'] = $date;
        $dateWhere = "(:date BETWEEN tawasulMessenger.messageWall_dateStart AND tawasulMessenger.messageWall_dateEnd)";

        // My roles
        $roles = $session->get('tawasulRoleIDAll');
        $sqlWhere = '(';
        if (count($roles) > 0) {
            for ($i = 0; $i < count($roles); ++$i) {
                $dataPosts['role'.$i] = $roles[$i][0];
                $sqlWhere .= 'id=:role'.$i.' OR ';
            }
            $sqlWhere = substr($sqlWhere, 0, -3).')';
        }

        if ($sqlWhere != '(') {
            $sqlPosts = "(SELECT tawasulMessenger.*, title, surname, preferredName, authorRole.category AS category, image_240, concat('Role: ', tawasulRole.name) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole AS authorRole ON (tawasulPerson.tawasulRoleIDPrimary=authorRole.tawasulRoleID) JOIN tawasulRole ON (tawasulMessengerTarget.id=tawasulRole.tawasulRoleID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Role' AND $dateWhere AND $sqlWhere)";
        }

        //My role categories
        try {
            $dataRoleCategory = array('tawasulPersonID' => $session->get('tawasulPersonID'));
            $sqlRoleCategory = "SELECT DISTINCT category FROM tawasulRole JOIN tawasulPerson ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)) WHERE tawasulPersonID=:tawasulPersonID";
            $resultRoleCategory = $connection2->prepare($sqlRoleCategory);
            $resultRoleCategory->execute($dataRoleCategory);
        } catch (\PDOException $e) {
        }
        $sqlWhere = '(';
        if ($resultRoleCategory->rowCount() > 0) {
            $i = 0;
            while ($rowRoleCategory = $resultRoleCategory->fetch()) {
                $dataPosts['roleCategory'.$i] = $rowRoleCategory['category'];
                $sqlWhere .= 'id=:roleCategory'.$i.' OR ';
                ++$i;
            }
            $sqlWhere = substr($sqlWhere, 0, -3).')';
        }
        if ($sqlWhere != '(') {
            $sqlPosts = $sqlPosts." UNION (SELECT DISTINCT tawasulMessenger.*, title, surname, preferredName, authorRole.category AS category, image_240, concat('Role Category: ', tawasulRole.category) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole AS authorRole ON (tawasulPerson.tawasulRoleIDPrimary=authorRole.tawasulRoleID) JOIN tawasulRole ON (tawasulMessengerTarget.id=tawasulRole.category) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Role Category' AND $dateWhere AND $sqlWhere)";
        }

        //My year groups
        if ($staff) {
            $dataPosts['tawasulSchoolYearID0'] = $session->get('tawasulSchoolYearID');
            $dataPosts['tawasulPersonID0'] = $session->get('tawasulPersonID');
            // Include staff by courses taught in the same year group.
            $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, 'Year Groups' AS source
                    FROM tawasulMessenger
                    JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID)
                    JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
                    JOIN tawasulCourse ON (FIND_IN_SET(tawasulMessengerTarget.id, tawasulCourse.tawasulYearGroupIDList))
                    JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                    JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                    JOIN tawasulStaff ON (tawasulCourseClassPerson.tawasulPersonID=tawasulStaff.tawasulPersonID)
                    WHERE tawasulMessenger.status='Sent' AND tawasulStaff.tawasulPersonID=:tawasulPersonID0
                    AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID0
                    AND tawasulMessengerTarget.type='Year Group' AND tawasulMessengerTarget.staff='Y' 
                    AND $dateWhere
                    GROUP BY tawasulMessenger.tawasulMessengerID )";
            // Include staff who are tutors of any student in the same year group.
            $sqlPosts .= "UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, 'Year Groups' AS source
                    FROM tawasulMessenger
                    JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID)
                    JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
                    JOIN tawasulYearGroup ON (tawasulYearGroup.tawasulYearGroupID=tawasulMessengerTarget.id)
                    JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                    JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
                    JOIN tawasulStaff ON (tawasulFormGroup.tawasulPersonIDTutor=tawasulStaff.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulStaff.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulStaff.tawasulPersonID)
                    WHERE tawasulMessenger.status='Sent' AND tawasulStaff.tawasulPersonID=:tawasulPersonID0
                    AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID0
                    AND tawasulMessengerTarget.type='Year Group' AND tawasulMessengerTarget.staff='Y' 
                    AND $dateWhere
                    GROUP BY tawasulMessenger.tawasulMessengerID)";
        }
        if ($student) {
            $dataPosts['tawasulSchoolYearID1'] = $session->get('tawasulSchoolYearID');
            $dataPosts['tawasulPersonID1'] = $session->get('tawasulPersonID');
            $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Year Group ', tawasulYearGroup.nameShort) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulStudentEnrolment ON (tawasulMessengerTarget.id=tawasulStudentEnrolment.tawasulYearGroupID) JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) WHERE tawasulMessenger.status='Sent' AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID1 AND tawasulMessengerTarget.type='Year Group' AND $dateWhere AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID1 AND students='Y')";
        }
        if ($parent and !empty($children)) {
            $dataPosts['tawasulSchoolYearID2'] = $session->get('tawasulSchoolYearID');
            $dataPosts['children'] = implode(',', $children);
            $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Year Group: ', tawasulYearGroup.nameShort) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulStudentEnrolment ON (tawasulMessengerTarget.id=tawasulStudentEnrolment.tawasulYearGroupID) JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) WHERE tawasulMessenger.status='Sent' AND FIND_IN_SET(tawasulStudentEnrolment.tawasulPersonID, :children) AND tawasulMessengerTarget.type='Year Group' AND $dateWhere AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID2 AND parents='Y')";
        }

        //My form groups
        if ($staff) {
            $sqlWhere = '(';

            try {
                $dataFormGroup = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonIDTutor' => $session->get('tawasulPersonID'), 'tawasulPersonIDTutor2' => $session->get('tawasulPersonID'), 'tawasulPersonIDTutor3' => $session->get('tawasulPersonID'));
                $sqlFormGroup = 'SELECT * FROM tawasulFormGroup WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND (tawasulPersonIDTutor=:tawasulPersonIDTutor OR tawasulPersonIDTutor2=:tawasulPersonIDTutor2 OR tawasulPersonIDTutor3=:tawasulPersonIDTutor3)';
                $resultFormGroup = $connection2->prepare($sqlFormGroup);
                $resultFormGroup->execute($dataFormGroup);
            } catch (\PDOException $e) {
                $resultFormGroup = new \TawasulOS\Database\Result();
            }

            if ($resultFormGroup->rowCount() > 0) {
                $i = 0;
                while ($rowFormGroup = $resultFormGroup->fetch()) {
                    $dataPosts['form'.$i] = $rowFormGroup['tawasulFormGroupID'];
                    $sqlWhere .= 'id=:form'.$i.' OR ';
                    $i++;
                }
                $sqlWhere = substr($sqlWhere, 0, -3).')';
                if ($sqlWhere != '(') {
                    $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Form Group: ', tawasulFormGroup.nameShort) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulFormGroup ON (tawasulMessengerTarget.id=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Form Group' AND $dateWhere AND $sqlWhere AND staff='Y')";
                }
            }
        }
        if ($student) {
            $dataPosts['tawasulSchoolYearID3'] = $session->get('tawasulSchoolYearID');
            $dataPosts['tawasulPersonID2'] = $session->get('tawasulPersonID');
            $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Form Group: ', tawasulFormGroup.nameShort) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulStudentEnrolment ON (tawasulMessengerTarget.id=tawasulStudentEnrolment.tawasulFormGroupID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulMessenger.status='Sent' AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID2 AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID3 AND tawasulMessengerTarget.type='Form Group' AND $dateWhere AND students='Y')";
        }
        if ($parent and !empty($children)) {
            $dataPosts['tawasulSchoolYearID4'] = $session->get('tawasulSchoolYearID');
            $dataPosts['children'] = implode(',', $children);
            $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Form Group: ', tawasulFormGroup.nameShort) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulStudentEnrolment ON (tawasulMessengerTarget.id=tawasulStudentEnrolment.tawasulFormGroupID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulMessenger.status='Sent' AND FIND_IN_SET(tawasulStudentEnrolment.tawasulPersonID, :children) AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID4 AND tawasulMessengerTarget.type='Form Group' AND $dateWhere AND parents='Y')";
        }

        //My courses
        //First check for any course, then do specific parent check
        $dataClasses = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'));
        $sqlClasses = "SELECT DISTINCT tawasulCourseClass.tawasulCourseID FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND NOT role LIKE '%- Left'";
        $resultClasses = $connection2->prepare($sqlClasses);
        $resultClasses->execute($dataClasses);
        $sqlWhere = '(';
        if ($resultClasses->rowCount() > 0) {
            $i = 0;
            while ($rowClasses = $resultClasses->fetch()) {
                $dataPosts['course'.$i] = $rowClasses['tawasulCourseID'];
                $sqlWhere .= 'id=:course'.$i.' OR ';
                $i++;
            }
            $sqlWhere = substr($sqlWhere, 0, -3).')';
            if ($sqlWhere != '(') {
                if ($staff) {
                    $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Course: ', tawasulCourse.nameShort) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulCourse ON (tawasulMessengerTarget.id=tawasulCourse.tawasulCourseID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Course' AND $dateWhere AND $sqlWhere AND staff='Y')";
                }
                if ($student) {
                    $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Course: ', tawasulCourse.nameShort) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulCourse ON (tawasulMessengerTarget.id=tawasulCourse.tawasulCourseID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Course' AND $dateWhere AND $sqlWhere AND students='Y')";
                }
            }
        }
        if ($parent and !empty($children)) {
            $dataClasses = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'children' => implode(',', $children));
            $sqlClasses = "SELECT DISTINCT tawasulCourseClass.tawasulCourseID FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND FIND_IN_SET(tawasulCourseClassPerson.tawasulPersonID, :children) AND NOT role LIKE '%- Left'";
            $resultClasses = $connection2->prepare($sqlClasses);
            $resultClasses->execute($dataClasses);
            $sqlWhere = '(';
            if ($resultClasses->rowCount() > 0) {
                $i = 0;
                while ($rowClasses = $resultClasses->fetch()) {
                    $dataPosts['courseParent'.$i] = $rowClasses['tawasulCourseID'];
                    $sqlWhere .= 'id=:courseParent'.$i.' OR ';
                }
                $sqlWhere = substr($sqlWhere, 0, -3).')';
                if ($sqlWhere != '(') {
                    $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Course: ', tawasulCourse.nameShort) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulCourse ON (tawasulMessengerTarget.id=tawasulCourse.tawasulCourseID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Course' AND $dateWhere AND $sqlWhere AND parents='Y')";
                }
            }
        }

        // My classes
        // First check for any role, then do specific parent check
        $dataClasses = ['tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID')];
        $sqlClasses = "SELECT tawasulCourseClass.tawasulCourseClassID FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND NOT role LIKE '%- Left'";
        $resultClasses = $connection2->prepare($sqlClasses);
        $resultClasses->execute($dataClasses);
        $sqlWhere = '(';
        if ($resultClasses->rowCount() > 0) {
            $i = 0;
            while ($rowClasses = $resultClasses->fetch()) {
                $dataPosts['class'.$i] = $rowClasses['tawasulCourseClassID'];
                $sqlWhere .= 'id=:class'.$i.' OR ';
                $i++;
            }
            $sqlWhere = substr($sqlWhere, 0, -3).')';
            if ($sqlWhere != '(') {
                if ($staff) {
                    $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Class: ', tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulCourseClass ON (tawasulMessengerTarget.id=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Class' AND $dateWhere AND $sqlWhere AND staff='Y')";
                }
                if ($student) {
                    $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Class: ', tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulCourseClass ON (tawasulMessengerTarget.id=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Class' AND $dateWhere AND $sqlWhere AND students='Y')";
                }
            }
        }
        if ($parent and !empty($children)) {
            $dataClasses = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'children' => implode(',', $children));
            $sqlClasses = "SELECT tawasulCourseClass.tawasulCourseClassID FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND FIND_IN_SET(tawasulCourseClassPerson.tawasulPersonID, :children) AND NOT role LIKE '%- Left'";
            $resultClasses = $connection2->prepare($sqlClasses);
            $resultClasses->execute($dataClasses);
            $sqlWhere = '(';
            if ($resultClasses->rowCount() > 0) {
                $i = 0;
                while ($rowClasses = $resultClasses->fetch()) {
                    $dataPosts['classParent'.$i] = $rowClasses['tawasulCourseClassID'];
                    $sqlWhere .= 'id=:classParent'.$i.' OR ';
                    $i++;
                }
                $sqlWhere = substr($sqlWhere, 0, -3).')';
                if ($sqlWhere != '(') {
                    $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Class: ', tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulCourseClass ON (tawasulMessengerTarget.id=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Class' AND $dateWhere AND $sqlWhere AND parents='Y')";
                }
            }
        }

        //Activities
        if ($staff) {
            $dataActivities = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'));
            $sqlActivities = 'SELECT tawasulActivity.tawasulActivityID FROM tawasulActivity JOIN tawasulActivityStaff ON (tawasulActivityStaff.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulActivityStaff.tawasulPersonID=:tawasulPersonID';
            $resultActivities = $connection2->prepare($sqlActivities);
            $resultActivities->execute($dataActivities);
            $sqlWhere = '(';
            if ($resultActivities->rowCount() > 0) {
                $i = 0;
                while ($rowActivities = $resultActivities->fetch()) {
                    $dataPosts['activityStaff'.$i] = $rowActivities['tawasulActivityID'];
                    $sqlWhere .= 'id=:activityStaff'.$i.' OR ';
                    $i++;
                }
                $sqlWhere = substr($sqlWhere, 0, -3).')';
                if ($sqlWhere != '(') {
                    $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Activity: ', tawasulActivity.name) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulActivity ON (tawasulMessengerTarget.id=tawasulActivity.tawasulActivityID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Activity' AND $dateWhere AND $sqlWhere AND staff='Y')";
                }
            }
        }
        if ($student) {
            $dataActivities = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'));
            $sqlActivities = "SELECT tawasulActivity.tawasulActivityID FROM tawasulActivity JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulActivityStudent.tawasulPersonID=:tawasulPersonID AND status='Accepted'";
            $resultActivities = $connection2->prepare($sqlActivities);
            $resultActivities->execute($dataActivities);
            $sqlWhere = '(';
            if ($resultActivities->rowCount() > 0) {
                $i = 0;
                while ($rowActivities = $resultActivities->fetch()) {
                    $dataPosts['activity'.$i] = $rowActivities['tawasulActivityID'];
                    $sqlWhere .= 'id=:activity'.$i.' OR ';
                    $i++;
                }
                $sqlWhere = substr($sqlWhere, 0, -3).')';
                if ($sqlWhere != '(') {
                    $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Activity: ', tawasulActivity.name) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulMessengerTarget.id=tawasulActivity.tawasulActivityID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Activity' AND $dateWhere AND $sqlWhere AND students='Y')";
                }
            }
        }
        if ($parent and !empty($children)) {
            $dataActivities = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'children' => implode(',', $children));
            $sqlActivities = "SELECT tawasulActivity.tawasulActivityID FROM tawasulActivity JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND FIND_IN_SET(tawasulActivityStudent.tawasulPersonID, :children) AND status='Accepted'";
            $resultActivities = $connection2->prepare($sqlActivities);
            $resultActivities->execute($dataActivities);
            $sqlWhere = '(';
            if ($resultActivities->rowCount() > 0) {
                $i = 0;
                while ($rowActivities = $resultActivities->fetch()) {
                    $dataPosts['activityParent'.$i] = $rowActivities['tawasulActivityID'];
                    $sqlWhere .= 'id=:activityParent'.$i.' OR ';
                    $i++;
                }
                $sqlWhere = substr($sqlWhere, 0, -3).')';
                if ($sqlWhere != '(') {
                    $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat('Activity: ', tawasulActivity.name) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulActivity ON (tawasulMessengerTarget.id=tawasulActivity.tawasulActivityID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Activity' AND $dateWhere AND $sqlWhere AND parents='Y')";
                }
            }
        }

        //Houses
        $dataPosts['tawasulPersonID3'] = $session->get('tawasulPersonID');
        $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.preferredName, category, tawasulPerson.image_240, concat('Houses: ', tawasulHouse.name) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulPerson AS inHouse ON (tawasulMessengerTarget.id=inHouse.tawasulHouseID) JOIN tawasulHouse ON (tawasulPerson.tawasulHouseID=tawasulHouse.tawasulHouseID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Houses' AND $dateWhere AND inHouse.tawasulPersonID=:tawasulPersonID3)";

        //Individuals
        $dataPosts['tawasulPersonID4'] = $session->get('tawasulPersonID');
        $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.preferredName, category, tawasulPerson.image_240, 'Individual: You' AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulPerson AS individual ON (tawasulMessengerTarget.id=individual.tawasulPersonID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Individuals' AND $dateWhere AND individual.tawasulPersonID=:tawasulPersonID4)";

        //Attendance
        if ($student) {
            try {
              $dataAttendance=array( "tawasulPersonID" => $session->get('tawasulPersonID'), "selectedDate"=>$date, "tawasulSchoolYearID"=>$session->get("tawasulSchoolYearID"), "nowDate"=>date("Y-m-d") );
              $sqlAttendance="SELECT galp.tawasulAttendanceLogPersonID, galp.type, galp.date FROM tawasulAttendanceLogPerson AS galp JOIN tawasulStudentEnrolment AS gse ON (galp.tawasulPersonID=gse.tawasulPersonID) JOIN tawasulPerson AS gp ON (gse.tawasulPersonID=gp.tawasulPersonID) WHERE gp.status='Full' AND (gp.dateStart IS NULL OR gp.dateStart<=:nowDate) AND (gp.dateEnd IS NULL OR gp.dateEnd>=:nowDate) AND gse.tawasulSchoolYearID=:tawasulSchoolYearID AND galp.date=:selectedDate AND galp.tawasulPersonID=:tawasulPersonID ORDER BY galp.tawasulAttendanceLogPersonID DESC LIMIT 1" ;
              $resultAttendance=$connection2->prepare($sqlAttendance);
              $resultAttendance->execute($dataAttendance);
            }
            catch(\PDOException $e) { }

            if ($resultAttendance->rowCount() > 0) {
                $studentAttendance = $resultAttendance->fetch();
                $dataPosts['attendanceType1'] = $studentAttendance['type'].' '.$date;
                $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.preferredName, category, tawasulPerson.image_240, concat('Attendance:', tawasulMessengerTarget.id) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Attendance' AND tawasulMessengerTarget.id=:attendanceType1 AND $dateWhere )";
            }
        }
        if ($parent and !empty($children)) {
            try {
              $dataAttendance=array( "tawasulPersonID" => $session->get('tawasulPersonID'), "selectedDate"=>$date, "tawasulSchoolYearID"=>$session->get("tawasulSchoolYearID"), "nowDate"=>date("Y-m-d"), 'children' => implode(',', $children) );
              $sqlAttendance="SELECT galp.tawasulAttendanceLogPersonID, galp.type, gp.firstName FROM tawasulAttendanceLogPerson AS galp JOIN tawasulStudentEnrolment AS gse ON (galp.tawasulPersonID=gse.tawasulPersonID) JOIN tawasulPerson AS gp ON (gse.tawasulPersonID=gp.tawasulPersonID) WHERE gp.status='Full' AND (gp.dateStart IS NULL OR gp.dateStart<=:nowDate) AND (gp.dateEnd IS NULL OR gp.dateEnd>=:nowDate) AND gse.tawasulSchoolYearID=:tawasulSchoolYearID AND galp.date=:selectedDate AND FIND_IN_SET(galp.tawasulPersonID, :children) ORDER BY galp.tawasulAttendanceLogPersonID DESC LIMIT 1" ;
              $resultAttendance=$connection2->prepare($sqlAttendance);
              $resultAttendance->execute($dataAttendance);
            }
            catch(\PDOException $e) { }

            if ($resultAttendance->rowCount() > 0) {
                $studentAttendance = $resultAttendance->fetch();
                $dataPosts['attendanceType2'] = $studentAttendance['type'].' '.$date;
                $dataPosts['attendanceFirstName'] = $studentAttendance['firstName'];
                $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.preferredName, category, tawasulPerson.image_240, concat('Attendance:', tawasulMessengerTarget.id, ' for ', :attendanceFirstName) AS source FROM tawasulMessenger JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID) JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) WHERE tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Attendance' AND tawasulMessengerTarget.id=:attendanceType2 AND $dateWhere )";
            }
        }

        // Groups
        if ($staff) {
            $dataPosts['tawasulPersonID5'] = $session->get('tawasulPersonID');
            $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat(tawasulGroup.name, ' Group') AS source
            FROM tawasulMessenger
            JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID)
            JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID)
            JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
            JOIN tawasulGroup ON (tawasulMessengerTarget.id=tawasulGroup.tawasulGroupID)
            JOIN tawasulGroupPerson ON (tawasulGroup.tawasulGroupID=tawasulGroupPerson.tawasulGroupID)
            WHERE tawasulGroupPerson.tawasulPersonID=:tawasulPersonID5
            AND tawasulMessenger.status='Sent' AND tawasulMessengerTarget.type='Group' AND tawasulMessengerTarget.staff='Y'
            AND $dateWhere )";
        }
        if ($student) {
            $dataPosts['tawasulPersonID6'] = $session->get('tawasulPersonID');
            $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat(tawasulGroup.name, ' Group') AS source
            FROM tawasulMessenger
            JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID)
            JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID)
            JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
            JOIN tawasulGroup ON (tawasulMessengerTarget.id=tawasulGroup.tawasulGroupID)
            JOIN tawasulGroupPerson ON (tawasulGroup.tawasulGroupID=tawasulGroupPerson.tawasulGroupID)
            WHERE tawasulMessenger.status='Sent' AND tawasulGroupPerson.tawasulPersonID=:tawasulPersonID6
            AND tawasulMessengerTarget.type='Group' AND tawasulMessengerTarget.students='Y'
            AND $dateWhere )";
        }
        if ($parent and !empty($children)) {
            $dataPosts['tawasulPersonID7'] = $session->get('tawasulPersonID');
            $dataPosts['children'] = implode(',', $children);
            $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, title, surname, preferredName, category, image_240, concat(tawasulGroup.name, ' Group') AS source
            FROM tawasulMessenger
            JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID)
            JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID)
            JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
            JOIN tawasulGroup ON (tawasulMessengerTarget.id=tawasulGroup.tawasulGroupID)
            JOIN tawasulGroupPerson ON (tawasulGroup.tawasulGroupID=tawasulGroupPerson.tawasulGroupID)
            WHERE tawasulMessenger.status='Sent' AND (tawasulGroupPerson.tawasulPersonID=:tawasulPersonID7 OR FIND_IN_SET(tawasulGroupPerson.tawasulPersonID, :children))
            AND tawasulMessengerTarget.type='Group' AND tawasulMessengerTarget.parents='Y'
            AND $dateWhere )";
        }

        // Transport
        if ($staff) {
            $dataPosts['tawasulPersonID8'] = $session->get('tawasulPersonID');
            $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.preferredName, category, tawasulPerson.image_240, concat('Transport ', transportee.transport) AS source FROM tawasulMessenger
            JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID)
            JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID)
            JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
            JOIN tawasulPerson as transportee ON (FIND_IN_SET(tawasulMessengerTarget.id, transportee.transport))
            WHERE tawasulMessenger.status='Sent' AND transportee.tawasulPersonID=:tawasulPersonID8
            AND tawasulMessengerTarget.type='Transport' AND tawasulMessengerTarget.staff='Y'
            AND $dateWhere )";
        }
        if ($student) {
            $dataPosts['tawasulPersonID9'] = $session->get('tawasulPersonID');
            $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.preferredName, category, tawasulPerson.image_240, concat('Transport ', transportee.transport) AS source FROM tawasulMessenger
            JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID)
            JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID)
            JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
            JOIN tawasulPerson as transportee ON (FIND_IN_SET(tawasulMessengerTarget.id, transportee.transport))
            WHERE tawasulMessenger.status='Sent' AND transportee.tawasulPersonID=:tawasulPersonID9
            AND tawasulMessengerTarget.type='Transport' AND tawasulMessengerTarget.students='Y'
            AND $dateWhere )";
        }
        if ($parent and !empty($children)) {
            $dataPosts['tawasulPersonID10'] = $session->get('tawasulPersonID');
            $dataPosts['children'] = implode(',', $children);
            $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.preferredName, category, tawasulPerson.image_240, concat('Transport ', transportee.transport) AS source FROM tawasulMessenger
            JOIN tawasulMessengerTarget ON (tawasulMessengerTarget.tawasulMessengerID=tawasulMessenger.tawasulMessengerID)
            JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID)
            JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
            JOIN tawasulPerson as transportee ON (FIND_IN_SET(tawasulMessengerTarget.id, transportee.transport))
            WHERE tawasulMessenger.status='Sent' AND (transportee.tawasulPersonID=:tawasulPersonID10 OR FIND_IN_SET(transportee.tawasulPersonID, :children))
            AND tawasulMessengerTarget.type='Transport' AND tawasulMessengerTarget.parents='Y'
            AND $dateWhere )";
        }

        // Post Owner - show messages created by the user
        $dataPosts['tawasulPersonIDOwner'] = $session->get('tawasulPersonID');
        $sqlPosts = $sqlPosts." UNION (SELECT tawasulMessenger.*, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.preferredName, category, tawasulPerson.image_240, 'Post Owner' AS source FROM tawasulMessenger JOIN tawasulPerson ON (tawasulMessenger.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) WHERE tawasulMessenger.status='Sent' AND tawasulMessenger.tawasulPersonID=:tawasulPersonIDOwner AND $dateWhere)";

        // SPIT OUT RESULTS
        if ($mode == 'result') {
            $resultReturn = [];
            $resultReturn[0] = $dataPosts;
            $resultReturn[1] = $sqlPosts.' ORDER BY messageWallPin DESC, timestamp DESC, tawasulMessengerID, source';

            return serialize($resultReturn);
        } elseif ($mode == 'array') {
            try {
                $sqlPosts = $sqlPosts.' ORDER BY messageWallPin DESC, timestamp DESC, tawasulMessengerID, source';
                $resultPosts = $connection2->prepare($sqlPosts);
                $resultPosts->execute($dataPosts);
            } catch (\PDOException $e) {
            }

            $arrayPosts = $resultPosts->rowCount() > 0 ? $resultPosts->fetchAll() : [];

            $arrayPosts = array_reduce($arrayPosts, function ($group, $item) {
                if (isset($group[$item['tawasulMessengerID']]['source'])) {
                    $item['source'] .= str_replace(':', ', ', strrchr($group[$item['tawasulMessengerID']]['source'], ':'));
                }
                $group[$item['tawasulMessengerID']] = $item;
                return $group;
            }, []);

            return $arrayPosts;
        } else {
            $count = 0;
            try {
                $sqlPosts = $sqlPosts.' ORDER BY messageWallPin DESC, timestamp DESC, tawasulMessengerID, source';
                $resultPosts = $connection2->prepare($sqlPosts);
                $resultPosts->execute($dataPosts);
            } catch (\PDOException $e) {
            }

            if ($resultPosts->rowCount() < 1) {
                $return .= Format::alert(__('There are no records to display.'), 'message');
            } else {
                $output = [];
                $last = '';
                while ($rowPosts = $resultPosts->fetch()) {
                    if ($last == $rowPosts['tawasulMessengerID']) {
                        $output[($count - 1)]['source'] = $output[($count - 1)]['source'].'<br/>'.$rowPosts['source'];
                    } else {
                        $output[$count]['photo'] = $rowPosts['image_240'];
                        $output[$count]['subject'] = $rowPosts['subject'];
                        $output[$count]['details'] = $rowPosts['body'];
                        $output[$count]['author'] = Format::name($rowPosts['title'], $rowPosts['preferredName'], $rowPosts['surname'], $rowPosts['category']);
                        $output[$count]['source'] = $rowPosts['source'];
                        $output[$count]['tawasulMessengerID'] = $rowPosts['tawasulMessengerID'];
                        $output[$count]['tawasulPersonID'] = $rowPosts['tawasulPersonID'];
                        $output[$count]['messageWallPin'] = $rowPosts['messageWallPin'];

                        ++$count;
                        $last = $rowPosts['tawasulMessengerID'];
                    }
                }

                $table = DataTable::create('messages');
                $table->addMetaData('allowHTML', ['details']);
                $table->modifyRows(function($message, $row) {
                    if ($message['messageWallPin'] == "Y") {
                        $row->addClass('selected');
                    }
                    return $row;
                });

                $table->addColumn('sharing', __('Sharing'))
                    ->width('100px')
                    ->addClass('textCenter align-top')
                    ->format(function ($message) {
                        $output = '<a name="' . $message['tawasulMessengerID'] . '"></a>';

                        $output .= Format::userPhoto($message['photo']);
                        $output .= '<br/>';

                        $output .= '<b><u>' . __('Posted By') . '</b></u><br/>';
                        $output .= $message['author'] . '<br/><br/>';

                        $output .= '<b><u>' . __('Shared Via') . '</b></u><br/>';
                        $output .= $message['source'] . '<br/><br/>';

                        if ($message['messageWallPin'] == "Y") {
                            $output .= '<i>' . __('Pinned To Top') . '</i><br/>';
                        }

                        return $output;
                    });

                $table->addColumn('message', __('Message'))
                    ->width('640px')
                    ->addClass('align-top overflow-x-scroll max-w-lg')
                    ->format(function ($message) {
                        $output = '<h3 style="margin-top: 3px">';
                        $output .= $this->validator->sanitizePlainText($message['subject']);
                        $output .= '</h3>';

                        $output .= '</p>';
                        $output .= $this->validator->sanitizeRichText($message['details']);
                        $output .= '</p>';

                        return $output;
                    });

                $return .= $table->render($output);
            }
            if ($mode == 'print') {
                return $return;
            } else {
                return $count;
            }
        }
    }
}
