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

namespace TawasulOS\Domain\User;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByPerson;
use TawasulOS\Domain\Traits\SharedUserLogic;

/**
 * User Gateway
 *
 * @version v16
 * @since   v16
 */
class UserGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use SharedUserLogic;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulPerson';
    private static $primaryKey = 'tawasulPersonID';

    private static $searchableColumns = ['preferredName', 'firstName', 'surname', 'tawasulPerson.nameInCharacters', 'username', 'studentID', 'email', 'emailAlternate', 'phone1', 'phone2', 'phone3', 'phone4', 'vehicleRegistration', 'tawasulRole.name'];

    private static $scrubbableKey = false;
    private static $scrubbableColumns = ['passwordStrong' => 'randomString', 'passwordStrongSalt' => 'randomString', 'address1' => '', 'address1District' => '', 'address1Country' => '', 'address2' => '', 'address2District' => '', 'address2Country' => '', 'phone1Type' => '', 'phone1CountryCode' => '', 'phone1' => '', 'phone3Type' => '', 'phone3CountryCode' => '', 'phone3' => '', 'phone2Type' => '', 'phone2CountryCode' => '', 'phone2' => '', 'phone4Type' => '', 'phone4CountryCode' => '', 'phone4' => '', 'website' => '', 'languageFirst' => '', 'languageSecond' => '', 'languageThird' => '', 'countryOfBirth' => '',  'ethnicity' => '', 'religion' => '', 'profession' => '', 'employer' => '', 'jobTitle' => '', 'emergency1Name' => '', 'emergency1Number1' => '', 'emergency1Number2' => '', 'emergency1Relationship' => '', 'emergency2Name' => '', 'emergency2Number1' => '', 'emergency2Number2' => '', 'emergency2Relationship' => '', 'transport' => '', 'transportNotes' => '', 'calendarFeedPersonal' => '', 'lockerNumber' => '', 'vehicleRegistration' => '', 'personalBackground' => '', 'studentAgreements' =>null, 'fields' => ''];

    private static $safeUserFields = ['tawasulPersonID', 'username', 'surname', 'firstName', 'preferredName', 'officialName', 'email', 'emailAlternate', 'website', 'gender', 'status', 'image_240', 'lastTimestamp', 'messengerLastRead', 'calendarFeedPersonal', 'viewCalendarSchool', 'viewCalendarPersonal', 'viewCalendarSpaceBooking', 'dateStart', 'personalBackground', 'tawasuli18nIDPersonal', 'googleAPIRefreshToken', 'microsoftAPIRefreshToken', 'genericAPIRefreshToken', 'receiveNotificationEmails', 'mfaToken' , 'cookieConsent', 'tawasulHouseID', 'passwordForceReset'];

    /**
     * Queries the list of users for the Manage Users page.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAllUsers(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.username',
                'tawasulPerson.image_240', 'tawasulPerson.status', 'tawasulRole.name as primaryRole', 'tawasulRole.category as roleCategory'
            ])
            ->leftJoin('tawasulRole', 'tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID');

        $criteria->addFilterRules($this->getSharedUserFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Gets basic user and role fields required for login.
     *
     * @param string $username
     * @return Result
     */
    public function selectLoginDetailsByUsername($username)
    {
        $data = ['username' => $username];
        $sql = "SELECT 
                    tawasulPerson.tawasulPersonID,
                    tawasulPerson.username,
                    tawasulPerson.passwordStrong,
                    tawasulPerson.passwordStrongSalt,
                    tawasulPerson.tawasulRoleIDPrimary,
                    tawasulPerson.tawasulRoleIDAll,
                    tawasulPerson.canLogin,
                    tawasulPerson.failCount,
                    tawasulRole.futureYearsLogin,
                    tawasulRole.pastYearsLogin,
                    tawasulRole.name as roleName,
                    tawasulRole.category as roleCategory,
                    tawasulPerson.tawasuli18nIDPersonal,
                    tawasulPerson.tawasulThemeIDPersonal
                FROM tawasulPerson 
                LEFT JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) 
                WHERE (
                    (username=:username OR (LOCATE('@', :username)>0 AND email=:username)) 
                    AND status='Full' 
                )";

        return $this->db()->select($sql, $data);
    }

    /**
     * Gets a set of fields to populate the session data, excluding unsafe fields such as passwords.
     *
     * @param string $tawasulPersonID
     * @return Result
     */
    public function getSafeUserData($tawasulPersonID)
    {
        return $this->getByID($tawasulPersonID, self::$safeUserFields);
    }

    /**
     * Returns basic user details, including name and user photo, and also returns 
     * form group information if this user is a student.
     *
     * @param string $tawasulPersonID
     * @return array
     */
    public function getUserDetails($tawasulPersonID, $tawasulSchoolYearID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulPerson.tawasulPersonID, title, surname, preferredName, email, image_240, gender, dateStart, dateEnd, status, tawasulStudentEnrolment.tawasulStudentEnrolmentID, tawasulStudentEnrolment.tawasulSchoolYearID, tawasulYearGroup.tawasulYearGroupID, tawasulYearGroup.nameShort AS yearGroup, tawasulYearGroup.name AS yearGroupName, tawasulFormGroup.tawasulFormGroupID, tawasulFormGroup.nameShort AS formGroup, tawasulFormGroup.name AS formGroupName, tawasulRole.category as roleCategory
                FROM tawasulPerson
                JOIN tawasulRole ON (tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary)
                LEFT JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID)
                LEFT JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Selects the family info for a subset of users. Primarily used to join family data to the queryAllUsers results.
     *
     * @param string|array $tawasulPersonIDList
     * @return Result
     */
    public function selectFamilyDetailsByPersonID($tawasulPersonIDList)
    {
        $idList = is_array($tawasulPersonIDList) ? implode(',', $tawasulPersonIDList) : $tawasulPersonIDList;
        $data = array('idList' => $idList);
        $sql = "(
            SELECT LPAD(tawasulFamilyAdult.tawasulPersonID, 10, '0'), tawasulFamilyAdult.tawasulFamilyID, 'adult' AS role, tawasulFamily.name, (SELECT tawasulFamilyChild.tawasulPersonID FROM tawasulFamilyChild JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID ORDER BY tawasulPerson.dob DESC LIMIT 1) as tawasulPersonIDStudent
            FROM tawasulFamily
            JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
            WHERE FIND_IN_SET(tawasulFamilyAdult.tawasulPersonID, :idList)
        ) UNION (
            SELECT LPAD(tawasulFamilyChild.tawasulPersonID, 10, '0'), tawasulFamilyChild.tawasulFamilyID, 'child' AS role, tawasulFamily.name, tawasulFamilyChild.tawasulPersonID as tawasulPersonIDStudent
            FROM tawasulFamily
            JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
            WHERE FIND_IN_SET(tawasulFamilyChild.tawasulPersonID, :idList)
        ) ORDER BY tawasulFamilyID";

        return $this->db()->select($sql, $data);
    }

    public function selectUserNamesByStatus($status = 'Full', $category = null, $tawasulSchoolYearID = null)
    {
        $data = array('statusList' => is_array($status) ? implode(',', $status) : $status );
        $sql = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName, status, dateStart, dateEnd, username, lastTimestamp, tawasulRole.category as roleCategory";

        if (!empty($tawasulSchoolYearID)) {
            $data['tawasulSchoolYearID'] = $tawasulSchoolYearID;
            $sql .= ", tawasulFormGroup.name AS formGroupName
                FROM tawasulPerson
                JOIN tawasulRole ON (tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary)
                LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID)
                LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                WHERE FIND_IN_SET(tawasulPerson.status, :statusList)";
        } else {
            $sql .= "
                FROM tawasulPerson
                JOIN tawasulRole ON (tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary)
                WHERE FIND_IN_SET(tawasulPerson.status, :statusList)";
        }

        if (!is_null($category)) {
            $data['category'] = $category;
            $sql .= " AND tawasulRole.category=:category";
        }

        if (!empty($tawasulSchoolYearID)) {
            $sql .= " GROUP BY tawasulPerson.tawasulPersonID";
        }

        $sql .= " ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectNotificationDetailsByPerson($tawasulPersonID)
    {
        $tawasulPersonIDList = is_array($tawasulPersonID)? $tawasulPersonID : [$tawasulPersonID];

        $data = ['tawasulPersonIDList' => implode(',', $tawasulPersonIDList)];
        $sql = "SELECT tawasulPerson.tawasulPersonID as groupBy, tawasulPerson.tawasulPersonID, title, surname, preferredName, tawasulPerson.status, image_240, username, email, phone1, phone1CountryCode, phone1Type, tawasulRole.category as roleCategory, tawasulStaff.jobTitle, tawasulStaff.type
                FROM tawasulPerson
                JOIN tawasulRole ON (tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary)
                LEFT JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE FIND_IN_SET(tawasulPerson.tawasulPersonID, :tawasulPersonIDList)
                ORDER BY FIND_IN_SET(tawasulPerson.tawasulPersonID, :tawasulPersonIDList), surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function addRoleToUser($tawasulPersonID, $tawasulRoleID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'tawasulRoleID' => $tawasulRoleID];
        $sql = "UPDATE tawasulPerson SET tawasulRoleIDAll=concat(tawasulRoleIDAll, ',', :tawasulRoleID) WHERE tawasulPersonID=:tawasulPersonID AND tawasulRoleIDAll NOT LIKE CONCAT('%', :tawasulRoleID, '%')";

        return $this->db()->affectingStatement($sql, $data);
    }

    public function removeRoleFromUser($tawasulPersonID, $tawasulRoleID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'tawasulRoleID' => $tawasulRoleID];
        $sql = "UPDATE tawasulPerson SET tawasulRoleIDAll=REPLACE(REPLACE(tawasulRoleIDAll, :tawasulRoleID, ''), ',,', '') WHERE tawasulPersonID=:tawasulPersonID AND tawasulRoleIDAll LIKE CONCAT('%', :tawasulRoleID, '%')";

        return $this->db()->update($sql, $data);
    }
    
    public function selectActiveUsersBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulPerson.tawasulPersonID, preferredName, surname, username, tawasulFormGroup.name AS formGroupName, tawasulRole.category FROM tawasulPerson JOIN tawasulRole ON (tawasulRole.tawasulRoleID = tawasulPerson.tawasulRoleIDPrimary) LEFT JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID = tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID) LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID = tawasulFormGroup.tawasulFormGroupID) WHERE tawasulPerson.status = 'Full' ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectTransportList() 
    {
        $sql = "SELECT DISTINCT transport FROM tawasulPerson WHERE status = 'Full' AND NOT transport='' ORDER BY transport";

        return $this->db()->select($sql);
    }

    public function getUserPreferences($tawasulPersonID) 
    {
        $user = $this->getByID($tawasulPersonID, ['preferences']);
        $preferences = !empty($user['preferences']) ? json_decode($user['preferences'] ?? '', true) : []; 

        return $preferences;
    }

    public function getUserPreferenceByScope($tawasulPersonID, $scope, $key = null, $default = null) 
    {
        $preferences = $this->getUserPreferences($tawasulPersonID);

        return !empty($key) ? ($preferences[$scope][$key] ?? $default) : ($preferences[$scope] ?? $default);
    }

    public function setUserPreferences($tawasulPersonID, $newPreferences, $replace = false) 
    {
        $preferences = $replace
            ? $newPreferences
            : array_replace($this->getUserPreferences($tawasulPersonID), $newPreferences);

        return $this->update($tawasulPersonID, [
            'preferences' => json_encode($preferences),
        ]);
    }

    public function setUserPreferenceByScope($tawasulPersonID, $scope, $key, $value) 
    {
        $preferences = $this->getUserPreferences($tawasulPersonID);

        $preferences[$scope][$key] = $value;

        return $this->update($tawasulPersonID, [
            'preferences' => json_encode($preferences),
        ]);
    }
}
