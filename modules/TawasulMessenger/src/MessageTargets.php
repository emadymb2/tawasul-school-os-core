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

namespace Tos\Module\TawasulMessenger;

use TawasulOS\Services\Format;
use TawasulOS\Data\PasswordPolicy;
use TawasulOS\Domain\User\RoleGateway;
use TawasulOS\Domain\System\LogGateway;
use TawasulOS\Contracts\Services\Session;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Contracts\Database\Connection;
use TawasulOS\Domain\Messenger\MessengerReceiptGateway;

/**
 * MessageTargets
 *
 * @version v25
 * @since   v25
 */
class MessageTargets
{
    protected $report;
    protected $roleGateway;
    protected $messengerReceiptGateway;
    protected $logGateway;
    protected $settingGateway;
    protected $session;
    protected $db;

    public function __construct(
        Session $session,
        Connection $db,
        SettingGateway $settingGateway,
        LogGateway $logGateway,
        RoleGateway $roleGateway,
        MessengerReceiptGateway $messengerReceiptGateway
    )
    {
        $this->session = $session;
        $this->db = $db;
        $this->settingGateway = $settingGateway;
        $this->logGateway = $logGateway;
        $this->roleGateway = $roleGateway;
        $this->messengerReceiptGateway = $messengerReceiptGateway;
    }

    public function createMessageTargets($tawasulMessengerID, &$partialFail = false)
    {
        $guid = $this->session->get('guid');
        $connection2 = $this->db->getConnection();

        //Roles
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_role")) {
            if (!empty($_POST["role"]) && $_POST["role"]=="Y") {
                $choices = $_POST["roles"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "t"=>$t);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Role', id=:t";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }


        //Role Categories
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_role") || isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_postQuickWall.php")) {
            if (!empty($_POST['roleCategory']) && $_POST['roleCategory'] == 'Y') {
                $choices=$_POST['roleCategories'] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "t"=>$t);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Role Category', id=:t";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }

        //Year Groups
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_yearGroups_any")) {
            if (!empty($_POST['yearGroup']) && $_POST["yearGroup"]=="Y") {
                $staff = $_POST["yearGroupsStaff"] ?? [];
                $students = $_POST["yearGroupsStudents"] ?? [];
                $parents="N";
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_yearGroups_parents")) {
                    $parents = $_POST["yearGroupsParents"] ?? [];
                }
                $choices = $_POST["yearGroups"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "t"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Year Group', id=:t, staff=:staff, students=:students, parents=:parents";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }

        //Form Groups
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_formGroups_my") OR isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_formGroups_any")) {
            if (!empty($_POST['formGroup']) && $_POST["formGroup"]=="Y") {
                $staff = $_POST["formGroupsStaff"] ?? [];
                $students = $_POST["formGroupsStudents"] ?? [];
                $parents="N";
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_formGroups_parents")) {
                    $parents = $_POST["formGroupsParents"] ?? [];
                }
                $choices = $_POST["formGroups"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "t"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Form Group', id=:t, staff=:staff, students=:students, parents=:parents";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }

        //Course Groups
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_courses_my") OR isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_courses_any")) {
            if (!empty($_POST['course']) && $_POST["course"]=="Y") {
                $staff = $_POST["coursesStaff"] ?? [];
                $students = $_POST["coursesStudents"] ?? [];
                $parents="N";
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_courses_parents")) {
                    $parents = $_POST["coursesParents"] ?? [];
                }
                $choices = $_POST["courses"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "id"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Course', id=:id, staff=:staff, students=:students, parents=:parents";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }

        //Class Groups
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_classes_my") OR isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_classes_any")) {
            if (!empty($_POST['class']) && $_POST["class"]=="Y") {
                $staff = $_POST["classesStaff"] ?? [];
                $students = $_POST["classesStudents"] ?? [];
                $parents="N";
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_classes_parents")) {
                    $parents = $_POST["classesParents"] ?? [];
                }
                $choices = $_POST["classes"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "id"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Class', id=:id, staff=:staff, students=:students, parents=:parents";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }

        //Activity Groups
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_activities_my") OR isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_activities_any")) {
            if (!empty($_POST['activity']) && $_POST["activity"]=="Y") {
                $staff = $_POST["activitiesStaff"] ?? [];
                $students = $_POST["activitiesStudents"] ?? [];
                $parents="N";
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_activities_parents")) {
                    $parents = $_POST["activitiesParents"] ?? [];
                }
                $choices = $_POST["activities"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "id"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Activity', id=:id, staff=:staff, students=:students, parents=:parents";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }

        //Applicants
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_applicants")) {
            if (!empty($_POST['applicants']) && $_POST["applicants"]=="Y") {
                $students = $_POST["applicantsStudents"] ;
                $parents = $_POST["applicantsParents"] ;
                $choices = $_POST["applicantList"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "id"=>$t);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Applicants', id=:id, students=:students, parents=:parents";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }

        //Houses
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_houses_all") OR isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_houses_my")) {
            if (!empty($_POST['houses']) && $_POST["houses"]=="Y") {
                $choices = $_POST["houseList"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "id"=>$t);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Houses', id=:id";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }

        //Transport
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_transport_any")) {
            if (!empty($_POST['transport']) && $_POST["transport"]=="Y") {
                        $staff = $_POST["transportStaff"] ?? [];
                        $students = $_POST["transportStudents"] ?? [];
                        $parents="N";
            if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_transport_parents")) {
                $parents = $_POST["transportParents"] ?? [];
            }
            $choices = $_POST["transports"] ?? [];
            if (!empty($choices)) {
                foreach ($choices as $t) {
                try {
                    $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "id"=>$t, "students"=>$students, "parents"=>$parents, "staff"=>$staff);
                    $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Transport', id=:id, students=:students, staff=:staff, parents=:parents";
                    $result=$connection2->prepare($sqlTarget);
                    $result->execute($dataTarget);
                }
                catch(\PDOException $e) {
                    $partialFail = true;
                }
                }
            }
            }
        }

        //Attendance
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_attendance")) {
            if (!empty($_POST['attendance']) && $_POST["attendance"]=="Y") {
                $choices = $_POST["attendanceStatus"] ?? [];
                $students = $_POST["attendanceStudents"] ?? [];
                $parents = $_POST["attendanceParents"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "id"=>$t, "students"=>$students, "parents"=>$parents);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Attendance', id=:id, students=:students, parents=:parents";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }

        //Groups
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_groups_any") || isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_groups_my")) {
            if (!empty($_POST['group']) && $_POST["group"] == "Y") {
                $staff = $_POST["groupsStaff"] ?? [];
                $students = $_POST["groupsStudents"] ?? [];
                $parents = "N";
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_groups_parents")) {
                    $parents = $_POST["groupsParents"] ?? [];
                }
                $choices = $_POST["groups"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "t"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Group', id=:t, staff=:staff, students=:students, parents=:parents";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }



        
        // Mailing List
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_mailingList")) {
            if (!empty($_POST['mailingList']) && $_POST["mailingList"] == "Y") {
                $choices = $_POST["mailingLists"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "t"=>$t);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Mailing List', id=:t, staff='N', students='N', parents='N'";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }

        //Individuals
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_individuals")) {
            if (!empty($_POST['individuals']) && $_POST["individuals"]=="Y") {
                $choices = $_POST["individualList"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $dataTarget=array("tawasulMessengerID"=>$tawasulMessengerID, "id"=>$t);
                            $sqlTarget="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Individuals', id=:id";
                            $result=$connection2->prepare($sqlTarget);
                            $result->execute($dataTarget);
                        }
                        catch(\PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }
            }
        }
    }

    public function createMessageRecipientsFromTargets($tawasulMessengerID, $data, &$partialFail = false) : array
    {
        $guid = $this->session->get('guid');
        $connection2 = $this->db->getConnection();

        //TARGETS
        $AI = $tawasulMessengerID;
        $sms = $data['sms'];
        $email = $data['email'];
        $emailReceipt = $data['emailReceipt'];
        $this->report = [];

        //Get country code
        $countryCode="" ;
        $country = $this->settingGateway->getSettingByScope("System", "country") ;
        $countryCodeTemp = '';
        try {
            $dataCountry=array("printable_name"=>$country);
            $sqlCountry="SELECT iddCountryCode FROM tawasulCountry WHERE printable_name=:printable_name" ;
            $resultCountry=$connection2->prepare($sqlCountry);
            $resultCountry->execute($dataCountry);
        }
        catch(\PDOException $e) { }
        if ($resultCountry->rowCount()==1) {
            $rowCountry=$resultCountry->fetch() ;
            $countryCode=$rowCountry["iddCountryCode"] ;
        }

        //Roles
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_role")) {
            if (!empty($_POST['role']) && $_POST["role"]=="Y") {
                $choices=$_POST["roles"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("AI"=>$AI, "t"=>$t);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:AI, type='Role', id=:t" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }

                        //Get email addresses
                        $category = $this->roleGateway->getRoleCategory($t);
                        $tawasulRoleID = str_pad(intval($t), 3, '0', STR_PAD_LEFT);
                        if ($email=="Y") {
                            if ($category=="Parent") {
                                try {
                                    $dataEmail=array('tawasulRoleID'=>$tawasulRoleID);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND FIND_IN_SET(:tawasulRoleID, tawasulPerson.tawasulRoleIDAll) AND status='Full' AND contactEmail='Y'" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);

                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Role', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            else {
                                try {
                                    $dataEmail=array('tawasulRoleID'=>$tawasulRoleID);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT email='' AND FIND_IN_SET(:tawasulRoleID, tawasulPerson.tawasulRoleIDAll) AND status='Full' AND (dateStart IS NULL OR dateStart<=CURRENT_DATE) AND (dateEnd IS NULL OR dateEnd>=CURRENT_DATE)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Role', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                        }
                        if ($sms=="Y" AND $countryCode!="") {
                            if ($category=="Parent") {
                                try {
                                    $dataEmail=array('tawasulRoleID'=>$tawasulRoleID);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND FIND_IN_SET(:tawasulRoleID, tawasulPerson.tawasulRoleIDAll) AND status='Full' AND contactSMS='Y')" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND FIND_IN_SET(:tawasulRoleID, tawasulPerson.tawasulRoleIDAll) AND status='Full' AND contactSMS='Y')" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND FIND_IN_SET(:tawasulRoleID, tawasulPerson.tawasulRoleIDAll) AND status='Full' AND contactSMS='Y')" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND FIND_IN_SET(:tawasulRoleID, tawasulPerson.tawasulRoleIDAll) AND status='Full' AND contactSMS='Y')" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Role', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            else {
                                try {
                                    $dataEmail=array('tawasulRoleID'=>$tawasulRoleID);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone1='' AND phone1Type='Mobile' AND FIND_IN_SET(:tawasulRoleID, tawasulPerson.tawasulRoleIDAll) AND status='Full' AND (dateStart IS NULL OR dateStart<=CURRENT_DATE) AND (dateEnd IS NULL OR dateEnd>=CURRENT_DATE))" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone2='' AND phone2Type='Mobile' AND FIND_IN_SET(:tawasulRoleID, tawasulPerson.tawasulRoleIDAll) AND status='Full' AND (dateStart IS NULL OR dateStart<=CURRENT_DATE) AND (dateEnd IS NULL OR dateEnd>=CURRENT_DATE))" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone3='' AND phone3Type='Mobile' AND FIND_IN_SET(:tawasulRoleID, tawasulPerson.tawasulRoleIDAll) AND status='Full' AND (dateStart IS NULL OR dateStart<=CURRENT_DATE) AND (dateEnd IS NULL OR dateEnd>=CURRENT_DATE))" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone4='' AND phone4Type='Mobile' AND FIND_IN_SET(:tawasulRoleID, tawasulPerson.tawasulRoleIDAll) AND status='Full' AND (dateStart IS NULL OR dateStart<=CURRENT_DATE) AND (dateEnd IS NULL OR dateEnd>=CURRENT_DATE))" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Role', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                        }
                    }
                }
            }
        }

        //Role Categories
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_role")) {
            if (!empty($_POST['roleCategory']) && $_POST["roleCategory"]=="Y") {
                $choices=$_POST["roleCategories"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("AI"=>$AI, "t"=>$t);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:AI, type='Role Category', id=:t" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }
                        //Get email addresses
                        if ($email=="Y") {
                            if ($t=="Parent") {
                                try {
                                    $dataEmail=array("category"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)) WHERE NOT email='' AND category=:category AND status='Full' AND contactEmail='Y' AND (dateStart IS NULL OR dateStart<=CURRENT_DATE) AND (dateEnd IS NULL OR dateEnd>=CURRENT_DATE)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Role Category', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            else {
                                try {
                                    $dataEmail=array("category"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)) WHERE NOT email='' AND category=:category AND status='Full' AND (dateStart IS NULL OR dateStart<=CURRENT_DATE) AND (dateEnd IS NULL OR dateEnd>=CURRENT_DATE)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Role Category', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                        }
                        if ($sms=="Y" AND $countryCode!="") {
                            if ($t=="Parent") {
                                try {
                                    $dataEmail=array("category"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)) WHERE NOT phone1='' AND phone1Type='Mobile' AND category=:category AND status='Full' AND contactSMS='Y')" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)) WHERE NOT phone2='' AND phone2Type='Mobile' AND category=:category AND status='Full' AND contactSMS='Y')" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)) WHERE NOT phone3='' AND phone3Type='Mobile' AND category=:category AND status='Full' AND contactSMS='Y')" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)) WHERE NOT phone4='' AND phone4Type='Mobile' AND category=:category AND status='Full' AND contactSMS='Y')" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) {}
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Role Category', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            else {
                                try {
                                    $dataEmail=array("category"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)) WHERE NOT phone1='' AND phone1Type='Mobile' AND category=:category AND status='Full' AND (dateStart IS NULL OR dateStart<=CURRENT_DATE) AND (dateEnd IS NULL OR dateEnd>=CURRENT_DATE))" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)) WHERE NOT phone2='' AND phone2Type='Mobile' AND category=:category AND status='Full' AND (dateStart IS NULL OR dateStart<=CURRENT_DATE) AND (dateEnd IS NULL OR dateEnd>=CURRENT_DATE))" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)) WHERE NOT phone3='' AND phone3Type='Mobile' AND category=:category AND status='Full' AND (dateStart IS NULL OR dateStart<=CURRENT_DATE) AND (dateEnd IS NULL OR dateEnd>=CURRENT_DATE))" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)) WHERE NOT phone4='' AND phone4Type='Mobile' AND category=:category AND status='Full' AND (dateStart IS NULL OR dateStart<=CURRENT_DATE) AND (dateEnd IS NULL OR dateEnd>=CURRENT_DATE))" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Role Category', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                        }
                    }
                }
            }
        }

        //Year Groups
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_yearGroups_any")) {
            if (!empty($_POST['yearGroup']) && $_POST["yearGroup"]=="Y") {
                $staff=$_POST["yearGroupsStaff"] ;
                $students=$_POST["yearGroupsStudents"] ;
                $parents="N" ;
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_yearGroups_parents")) {
                    $parents=$_POST["yearGroupsParents"] ;
                }
                $choices=$_POST["yearGroups"]  ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("AI"=>$AI, "t"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:AI, type='Year Group', id=:t, staff=:staff, students=:students, parents=:parents" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }

                        //Get email addresses
                        if ($email=="Y") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array('tawasulSchoolYearID'=>$this->session->get('tawasulSchoolYearID'), 'tawasulYearGroupID'=>$t);
                                    $sqlEmail="(SELECT DISTINCT email, tawasulPerson.tawasulPersonID
                                        FROM tawasulPerson
                                        JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID)
                                        JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulStaff.tawasulPersonID)
                                        JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                                        JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                                        WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
                                        AND FIND_IN_SET(:tawasulYearGroupID, tawasulCourse.tawasulYearGroupIDList)
                                        AND NOT tawasulPerson.email=''
                                        AND tawasulPerson.status='Full')
                                    UNION ALL (
                                        SELECT DISTINCT email, tawasulPerson.tawasulPersonID
                                        FROM tawasulPerson
                                        JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID)
                                        JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                                        WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                                        AND NOT email='' AND status='Full'
                                        AND tawasulStudentEnrolment.tawasulYearGroupID=:tawasulYearGroupID
                                        GROUP BY tawasulPerson.tawasulPersonID
                                    )" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Year Group', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "tawasulYearGroupID"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulYearGroupID=:tawasulYearGroupID" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Year Group', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataStudents=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "tawasulYearGroupID"=>$t);
                                    $sqlStudents="SELECT tawasulPerson.tawasulPersonID, surname, preferredName FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulYearGroupID=:tawasulYearGroupID" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents=$resultStudents->fetch()) {
                                    try {
                                        $dataFamily=array("tawasulPersonID"=>$rowStudents["tawasulPersonID"]);
                                        $sqlFamily="SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily=$connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily=$resultFamily->fetch()) {
                                        try {
                                            $dataEmail=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                            $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactEmail='Y'" ;
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail=$resultEmail->fetch()) {
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Year Group', $t, 'Email', $rowEmail["email"], $rowStudents['tawasulPersonID'], Format::name('', $rowStudents['preferredName'], $rowStudents['surname'], 'Student'));
                                        }
                                    }
                                }
                            }
                        }
                        if ($sms=="Y" AND $countryCode!="") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array();
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full')" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full')" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full')" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full')" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Year Group', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "tawasulYearGroupID"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulYearGroupID=:tawasulYearGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulYearGroupID=:tawasulYearGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulYearGroupID=:tawasulYearGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulYearGroupID=:tawasulYearGroupID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Year Group', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataStudents=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "tawasulYearGroupID"=>$t);
                                    $sqlStudents="SELECT tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulYearGroupID=:tawasulYearGroupID" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents=$resultStudents->fetch()) {
                                    try {
                                        $dataFamily=array("tawasulPersonID"=>$rowStudents["tawasulPersonID"]);
                                        $sqlFamily="SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily=$connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily=$resultFamily->fetch()) {
                                        try {
                                            $dataEmail=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                            $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail=$resultEmail->fetch()) {
                                            $countryCodeTemp = $countryCode;
                                            if ($rowEmail["countryCode"]=="")
                                                $countryCodeTemp = $rowEmail["countryCode"];
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Year Group', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        //Form Groups
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_formGroups_my") OR isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_formGroups_any")) {
            if (!empty($_POST['formGroup']) && $_POST["formGroup"]=="Y") {
                $staff=$_POST["formGroupsStaff"] ;
                $students=$_POST["formGroupsStudents"] ;
                $parents="N" ;
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_formGroups_parents")) {
                    $parents=$_POST["formGroupsParents"] ;
                }
                $choices=$_POST["formGroups"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("AI"=>$AI, "t"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:AI, type='Form Group', id=:t, staff=:staff, students=:students, parents=:parents" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }

                        //Get email addresses
                        if ($email=="Y") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array("t"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND tawasulFormGroupID=:t" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Form Group', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "tawasulFormGroupID"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFormGroupID=:tawasulFormGroupID" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Form Group', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataStudents=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "tawasulFormGroupID"=>$t);
                                    $sqlStudents="SELECT DISTINCT tawasulPerson.tawasulPersonID, surname, preferredName FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFormGroupID=:tawasulFormGroupID" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents=$resultStudents->fetch()) {
                                    try {
                                        $dataFamily=array("tawasulPersonID"=>$rowStudents["tawasulPersonID"]);
                                        $sqlFamily="SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily=$connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily=$resultFamily->fetch()) {
                                        try {
                                            $dataEmail=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                            $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactEmail='Y'" ;
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail=$resultEmail->fetch()) {
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Form Group', $t, 'Email', $rowEmail["email"], $rowStudents['tawasulPersonID'], Format::name('', $rowStudents['preferredName'], $rowStudents['surname'], 'Student'));
                                        }
                                    }
                                }
                            }
                        }
                        if ($sms=="Y" AND $countryCode!="") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array("tawasulFormGroupID"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulFormGroupID=:tawasulFormGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulFormGroupID=:tawasulFormGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulFormGroupID=:tawasulFormGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulFormGroupID=:tawasulFormGroupID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Form Group', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "tawasulFormGroupID"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFormGroupID=:tawasulFormGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFormGroupID=:tawasulFormGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFormGroupID=:tawasulFormGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFormGroupID=:tawasulFormGroupID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Form Group', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataStudents=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "tawasulFormGroupID"=>$t);
                                    $sqlStudents="SELECT DISTINCT tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulFormGroupID=:tawasulFormGroupID" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents=$resultStudents->fetch()) {
                                    try {
                                        $dataFamily=array("tawasulPersonID"=>$rowStudents["tawasulPersonID"]);
                                        $sqlFamily="SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily=$connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily=$resultFamily->fetch()) {
                                        try {
                                            $dataEmail=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                            $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail=$resultEmail->fetch()) {
                                            $countryCodeTemp = $countryCode;
                                            if ($rowEmail["countryCode"]=="")
                                                $countryCodeTemp = $rowEmail["countryCode"];
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Form Group', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        //Course Groups
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_courses_my") OR isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_courses_any")) {
            if (!empty($_POST['course']) && $_POST["course"]=="Y") {
                $staff=$_POST["coursesStaff"] ;
                $students=$_POST["coursesStudents"] ;
                $parents="N" ;
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_courses_parents")) {
                    $parents=$_POST["coursesParents"] ;
                }
                $choices=$_POST["courses"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("tawasulMessengerID"=>$AI, "id"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Course', id=:id, staff=:staff, students=:students, parents=:parents" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }

                        //Get email addresses
                        if ($email=="Y") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array("tawasulCourseID"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE (role='Teacher' OR role='Assistant' OR role='Technician') AND NOT email='' AND status='Full' AND tawasulCourse.tawasulCourseID=:tawasulCourseID" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Course', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulCourseID"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND NOT email='' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourse.tawasulCourseID=:tawasulCourseID" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Course', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataStudents=array("tawasulCourseID"=>$t);
                                    $sqlStudents="SELECT DISTINCT tawasulPerson.tawasulPersonID, surname, preferredName FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourse.tawasulCourseID=:tawasulCourseID" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents=$resultStudents->fetch()) {
                                    try {
                                        $dataFamily=array("tawasulPersonID"=>$rowStudents["tawasulPersonID"]);
                                        $sqlFamily="SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily=$connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily=$resultFamily->fetch()) {
                                        try {
                                            $dataEmail=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                            $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactEmail='Y'" ;
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail=$resultEmail->fetch()) {
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Course', $t, 'Email', $rowEmail["email"], $rowStudents['tawasulPersonID'], Format::name('', $rowStudents['preferredName'], $rowStudents['surname'], 'Student'));
                                        }
                                    }
                                }
                                try {
                                    $dataEmail=array("tawasulCourseID"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Parent' AND NOT email='' AND status='Full' AND tawasulCourse.tawasulCourseID=:tawasulCourseID" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Course', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                        }
                        if ($sms=="Y" AND $countryCode!="") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array("tawasulCourseID"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE (role='Teacher' OR role='Assistant' OR role='Technician') AND NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulCourse.tawasulCourseID=:tawasulCourseID)" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE (role='Teacher' OR role='Assistant' OR role='Technician') AND NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulCourse.tawasulCourseID=:tawasulCourseID)" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE (role='Teacher' OR role='Assistant' OR role='Technician') AND NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulCourse.tawasulCourseID=:tawasulCourseID)" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE (role='Teacher' OR role='Assistant' OR role='Technician') AND NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulCourse.tawasulCourseID=:tawasulCourseID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Course', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulCourseID"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourse.tawasulCourseID=:tawasulCourseID)" ;
                                    $sqlEmail.=" UNION (SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourse.tawasulCourseID=:tawasulCourseID)" ;
                                    $sqlEmail.=" UNION (SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourse.tawasulCourseID=:tawasulCourseID)" ;
                                    $sqlEmail.=" UNION (SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourse.tawasulCourseID=:tawasulCourseID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Course', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataStudents=array("tawasulCourseID"=>$t);
                                    $sqlStudents="SELECT DISTINCT tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourse.tawasulCourseID=:tawasulCourseID" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents=$resultStudents->fetch()) {
                                    try {
                                        $dataFamily=array("tawasulPersonID"=>$rowStudents["tawasulPersonID"]);
                                        $sqlFamily="SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily=$connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily=$resultFamily->fetch()) {
                                        try {
                                            $dataEmail=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                            $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail=$resultEmail->fetch()) {
                                            $countryCodeTemp = $countryCode;
                                            if ($rowEmail["countryCode"]=="")
                                                $countryCodeTemp = $rowEmail["countryCode"];
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Course', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                        }
                                    }
                                }
                                try {
                                    $dataEmail=array("tawasulCourseID"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Parent' AND NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulCourse.tawasulCourseID=:tawasulCourseID)" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Parent' AND NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulCourse.tawasulCourseID=:tawasulCourseID)" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Parent' AND NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulCourse.tawasulCourseID=:tawasulCourseID)" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Parent' AND NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulCourse.tawasulCourseID=:tawasulCourseID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Course', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                        }
                    }
                }
            }
        }

        //Class Groups
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_classes_my") OR isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_classes_any")) {
            if (!empty($_POST['class']) && $_POST["class"]=="Y") {
                $staff=$_POST["classesStaff"] ;
                $students=$_POST["classesStudents"] ;
                $parents="N" ;
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_classes_parents")) {
                    $parents=$_POST["classesParents"] ;
                }
                $choices=$_POST["classes"] ?? [];;
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("tawasulMessengerID"=>$AI, "id"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Class', id=:id, staff=:staff, students=:students, parents=:parents" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }

                        //Get email addresses
                        if ($email=="Y") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array("tawasulCourseClassID"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE (role='Teacher' OR role='Assistant' OR role='Technician') AND NOT email='' AND status='Full' AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Class', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulCourseClassID"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND NOT email='' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }

                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Class', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataStudents=array("tawasulCourseClassID"=>$t);
                                    $sqlStudents="SELECT DISTINCT tawasulPerson.tawasulPersonID, surname, preferredName FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents=$resultStudents->fetch()) {
                                    try {
                                        $dataFamily=array("tawasulPersonID"=>$rowStudents["tawasulPersonID"]);
                                        $sqlFamily="SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily=$connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily=$resultFamily->fetch()) {
                                        try {
                                            $dataEmail=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                            $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactEmail='Y'" ;
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail=$resultEmail->fetch()) {
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Class', $t, 'Email', $rowEmail["email"], $rowStudents['tawasulPersonID'], Format::name('', $rowStudents['preferredName'], $rowStudents['surname'], 'Student'));
                                        }
                                    }
                                }
                                try {
                                    $dataEmail=array("tawasulCourseClassID"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE role='Parent' AND NOT email='' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Class', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                        }
                        if ($sms=="Y" AND $countryCode!="") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array("tawasulCourseClassID"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE (role='Teacher' OR role='Assistant' OR role='Technician') AND NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE (role='Teacher' OR role='Assistant' OR role='Technician') AND NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE (role='Teacher' OR role='Assistant' OR role='Technician') AND NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE (role='Teacher' OR role='Assistant' OR role='Technician') AND NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Class', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulCourseClassID"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Class', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataStudents=array("tawasulCourseClassID"=>$t);
                                    $sqlStudents="SELECT DISTINCT tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE role='Student' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents=$resultStudents->fetch()) {
                                    try {
                                        $dataFamily=array("tawasulPersonID"=>$rowStudents["tawasulPersonID"]);
                                        $sqlFamily="SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily=$connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily=$resultFamily->fetch()) {
                                        try {
                                            $dataEmail=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                            $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail=$resultEmail->fetch()) {
                                            $countryCodeTemp = $countryCode;
                                            if ($rowEmail["countryCode"]=="")
                                                $countryCodeTemp = $rowEmail["countryCode"];
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Class', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                        }
                                    }
                                }
                                try {
                                    $dataEmail=array("tawasulCourseClassID"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE role='Parent' AND NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE role='Parent' AND NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE role='Parent' AND NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE role='Parent' AND NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Class', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                        }
                    }
                }
            }
        }

        //Activity Groups
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_activities_my") OR isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_activities_any")) {
            if (!empty($_POST['activity']) && $_POST["activity"]=="Y") {
                $staff=$_POST["activitiesStaff"] ;
                $students=$_POST["activitiesStudents"] ;
                $parents="N" ;
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_activities_parents")) {
                    $parents=$_POST["activitiesParents"] ;
                }
                $choices=$_POST["activities"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("tawasulMessengerID"=>$AI, "id"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Activity', id=:id, staff=:staff, students=:students, parents=:parents" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }

                        //Get email addresses
                        if ($email=="Y") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array("tawasulActivityID"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulActivityStaff ON (tawasulActivityStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStaff.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE NOT email='' AND status='Full' AND tawasulActivity.tawasulActivityID=:tawasulActivityID" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Activity', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulActivityID"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE NOT email='' AND tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulActivityStudent.status='Accepted' AND tawasulActivity.tawasulActivityID=:tawasulActivityID" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Activity', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataStudents=array("tawasulActivityID"=>$t);
                                    $sqlStudents="SELECT DISTINCT tawasulPerson.tawasulPersonID, surname, preferredName FROM tawasulPerson JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulActivityStudent.status='Accepted' AND tawasulActivity.tawasulActivityID=:tawasulActivityID" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents=$resultStudents->fetch()) {
                                    try {
                                        $dataFamily=array("tawasulPersonID"=>$rowStudents["tawasulPersonID"]);
                                        $sqlFamily="SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily=$connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily=$resultFamily->fetch()) {
                                        try {
                                            $dataEmail=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                            $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactEmail='Y'" ;
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail=$resultEmail->fetch()) {
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Activity', $t, 'Email', $rowEmail["email"], $rowStudents['tawasulPersonID'], Format::name('', $rowStudents['preferredName'], $rowStudents['surname'], 'Student'));
                                        }
                                    }
                                }
                            }
                        }
                        if ($sms=="Y" AND $countryCode!="") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array("tawasulActivityID"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulActivityStaff ON (tawasulActivityStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStaff.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulActivity.tawasulActivityID=:tawasulActivityID)" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulActivityStaff ON (tawasulActivityStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStaff.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulActivity.tawasulActivityID=:tawasulActivityID)" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulActivityStaff ON (tawasulActivityStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStaff.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulActivity.tawasulActivityID=:tawasulActivityID)" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulActivityStaff ON (tawasulActivityStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStaff.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulActivity.tawasulActivityID=:tawasulActivityID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Activity', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulActivityID"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE NOT phone1='' AND phone1Type='Mobile' AND tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulActivityStudent.status='Accepted' AND tawasulActivity.tawasulActivityID=:tawasulActivityID)" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE NOT phone2='' AND phone2Type='Mobile' AND tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulActivityStudent.status='Accepted' AND tawasulActivity.tawasulActivityID=:tawasulActivityID)" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE NOT phone3='' AND phone3Type='Mobile' AND tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulActivityStudent.status='Accepted' AND tawasulActivity.tawasulActivityID=:tawasulActivityID)" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE NOT phone4='' AND phone4Type='Mobile' AND tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulActivityStudent.status='Accepted' AND tawasulActivity.tawasulActivityID=:tawasulActivityID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Activity', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataStudents=array("tawasulActivityID"=>$t);
                                    $sqlStudents="SELECT DISTINCT tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulActivity ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulActivityStudent.status='Accepted' AND tawasulActivity.tawasulActivityID=:tawasulActivityID" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents=$resultStudents->fetch()) {
                                    try {
                                        $dataFamily=array("tawasulPersonID"=>$rowStudents["tawasulPersonID"]);
                                        $sqlFamily="SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily=$connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily=$resultFamily->fetch()) {
                                        try {
                                            $dataEmail=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                            $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) {}
                                        while ($rowEmail=$resultEmail->fetch()) {
                                            $countryCodeTemp = $countryCode;
                                            if ($rowEmail["countryCode"]=="")
                                                $countryCodeTemp = $rowEmail["countryCode"];
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Activity', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        //Applicants
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_applicants")) {
            if (!empty($_POST['applicants']) && $_POST["applicants"] == "Y") {
                $staff="N" ;
                $students = $_POST["applicantsStudents"] ;
                $parents = $_POST["applicantsParents"] ;
                $applicantsWhere = "AND NOT tawasulApplicationForm.status IN ('Waiting List', 'Rejected', 'Withdrawn', 'Pending')";

                $choices=$_POST["applicantList"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("tawasulMessengerID"=>$AI, "id"=>$t, "students" => $students, "parents" => $parents);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Applicants', id=:id, students=:students, parents=:parents" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }

                        if ($email == "Y") {
                            if ($students == "Y") {
                                //Get applicant emails
                                try {
                                    $dataEmail=array("tawasulSchoolYearIDEntry"=>$t);
                                    $sqlEmail="SELECT DISTINCT email FROM tawasulApplicationForm WHERE NOT email='' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, NULL, 'Applicants', $t, 'Email', $rowEmail["email"]);
                                }
                            }

                            if ($parents == "Y") {
                                // //Get parent 1 emails
                                // try {
                                //     $dataEmail=array("tawasulSchoolYearIDEntry"=>$t);
                                //     $sqlEmail="SELECT DISTINCT parent1email FROM tawasulApplicationForm WHERE NOT parent1email='' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere" ;
                                //     $resultEmail=$connection2->prepare($sqlEmail);
                                //     $resultEmail->execute($dataEmail);
                                // }
                                // catch(\PDOException $e) { }
                                // while ($rowEmail=$resultEmail->fetch()) {
                                //     $this->reportAdd($emailReceipt, NULL, 'Applicants', $t, 'Email', $rowEmail["parent1email"]);
                                // }

                                // //Get parent 2 emails
                                // try {
                                //     $dataEmail=array("tawasulSchoolYearIDEntry"=>$t);
                                //     $sqlEmail="SELECT DISTINCT parent2email FROM tawasulApplicationForm WHERE NOT parent2email='' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere" ;
                                //     $resultEmail=$connection2->prepare($sqlEmail);
                                //     $resultEmail->execute($dataEmail);
                                // }
                                // catch(\PDOException $e) { }
                                // while ($rowEmail=$resultEmail->fetch()) {
                                //     $this->reportAdd($emailReceipt, NULL, 'Applicants', $t, 'Email', $rowEmail["parent2email"]);
                                // }

                                //Get parent ID emails (when no family in system, but user is in system)
                                try {
                                    $dataEmail=array("tawasulSchoolYearIDEntry"=>$t);
                                    $sqlEmail="SELECT tawasulPerson.email, tawasulPerson.tawasulPersonID FROM tawasulApplicationForm JOIN tawasulPerson ON (tawasulApplicationForm.parent1tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT parent1tawasulPersonID='' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Applicants', $t, 'Email', $rowEmail["email"]);
                                }

                                //Get family emails
                                try {
                                    $dataEmail=array("tawasulSchoolYearIDEntry"=>$t);
                                    $sqlEmail="SELECT * FROM tawasulApplicationForm WHERE NOT tawasulFamilyID='' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    try {
                                        $dataEmail2=array("tawasulFamilyID"=>$rowEmail["tawasulFamilyID"]);
                                        $sqlEmail2="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND (status='Full' OR status='Expected') AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactEmail='Y'" ;
                                        $resultEmail2=$connection2->prepare($sqlEmail2);
                                        $resultEmail2->execute($dataEmail2);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowEmail2=$resultEmail2->fetch()) {
                                        $this->reportAdd($emailReceipt, $rowEmail2['tawasulPersonID'], 'Applicants', $t, 'Email', $rowEmail2["email"]);
                                    }
                                }
                            }
                        }
                        if ($sms=="Y" AND $countryCode!="") {
                            if ($students == "Y") {
                                //Get applicant phone numbers
                                try {
                                    $dataEmail=array("tawasulSchoolYearIDEntry"=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode FROM tawasulApplicationForm WHERE NOT phone1='' AND phone1Type='Mobile' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere)" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode FROM tawasulApplicationForm WHERE NOT phone2='' AND phone2Type='Mobile' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, NULL, 'Applicants', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }

                            if ($parents == "Y") {
                                //Get parent 1 numbers
                                try {
                                    $dataEmail=array("tawasulSchoolYearIDEntry"=>$t);
                                    $sqlEmail="(SELECT CONCAT(parent1phone1CountryCode,parent1phone1) AS phone, parent1phone1CountryCode AS countryCode FROM tawasulApplicationForm WHERE NOT parent1phone1='' AND parent1phone1Type='Mobile' AND parent1phone1CountryCode='$countryCode' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere)" ;
                                    $sqlEmail.=" UNION (SELECT CONCAT(parent1phone2CountryCode,parent1phone2) AS phone, parent1phone2CountryCode AS countryCode FROM tawasulApplicationForm WHERE NOT parent1phone2='' AND parent1phone2Type='Mobile' AND parent1phone2CountryCode='$countryCode' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, NULL, 'Applicants', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }

                                //Get parent 2 numbers
                                try {
                                    $dataEmail=array("tawasulSchoolYearIDEntry"=>$t);
                                    $sqlEmail="(SELECT CONCAT(parent2phone1CountryCode,parent2phone1) AS phone, parent2phone1CountryCode AS countryCode FROM tawasulApplicationForm WHERE NOT parent2phone1='' AND parent2phone1Type='Mobile' AND parent2phone1CountryCode='$countryCode' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere)" ;
                                    $sqlEmail.=" UNION (SELECT CONCAT(parent2phone2CountryCode,parent2phone2) AS phone, parent2phone2CountryCode AS countryCode FROM tawasulApplicationForm WHERE NOT parent2phone2='' AND parent2phone2Type='Mobile' AND parent2phone2CountryCode='$countryCode' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, NULL, 'Applicants', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }

                                //Get parent ID numbers (when no family in system, but user is in system)
                                try {
                                    $dataEmail=array("tawasulSchoolYearIDEntry"=>$t);
                                    $sqlEmail="(SELECT CONCAT(tawasulPerson.phone1CountryCode,tawasulPerson.phone1) AS phone, tawasulPerson.tawasulPersonID FROM tawasulApplicationForm JOIN tawasulPerson ON (tawasulApplicationForm.parent1tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT tawasulPerson.phone1='' AND tawasulPerson.phone1Type='Mobile' AND tawasulPerson.phone1CountryCode='$countryCode' AND NOT parent1tawasulPersonID='' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere)" ;
                                    $sqlEmail.=" UNION (SELECT CONCAT(tawasulPerson.phone2CountryCode,tawasulPerson.phone2) AS phone, tawasulPerson.tawasulPersonID FROM tawasulApplicationForm JOIN tawasulPerson ON (tawasulApplicationForm.parent1tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT tawasulPerson.phone2='' AND tawasulPerson.phone2Type='Mobile' AND tawasulPerson.phone2CountryCode='$countryCode' AND NOT parent1tawasulPersonID='' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere)" ;
                                    $sqlEmail.=" UNION (SELECT CONCAT(tawasulPerson.phone3CountryCode,tawasulPerson.phone3) AS phone, tawasulPerson.tawasulPersonID FROM tawasulApplicationForm JOIN tawasulPerson ON (tawasulApplicationForm.parent1tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT tawasulPerson.phone3='' AND tawasulPerson.phone3Type='Mobile' AND tawasulPerson.phone3CountryCode='$countryCode' AND NOT parent1tawasulPersonID='' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere)" ;
                                    $sqlEmail.=" UNION (SELECT CONCAT(tawasulPerson.phone4CountryCode,tawasulPerson.phone4) AS phone, tawasulPerson.tawasulPersonID FROM tawasulApplicationForm JOIN tawasulPerson ON (tawasulApplicationForm.parent1tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT tawasulPerson.phone4='' AND tawasulPerson.phone4Type='Mobile' AND tawasulPerson.phone4CountryCode='$countryCode' AND NOT parent1tawasulPersonID='' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Applicants', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }

                                //Get family numbers
                                try {
                                    $dataEmail=array("tawasulSchoolYearIDEntry"=>$t);
                                    $sqlEmail="SELECT * FROM tawasulApplicationForm WHERE NOT tawasulFamilyID='' AND tawasulSchoolYearIDEntry=:tawasulSchoolYearIDEntry $applicantsWhere" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    try {
                                        $dataEmail2=array("tawasulFamilyID"=>$rowEmail["tawasulFamilyID"]);
                                        $sqlEmail2="(SELECT CONCAT(tawasulPerson.phone1CountryCode,tawasulPerson.phone1) AS phone, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT tawasulPerson.phone1='' AND tawasulPerson.phone1Type='Mobile' AND tawasulPerson.phone1CountryCode='$countryCode' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID)" ;
                                        $sqlEmail2.=" UNION (SELECT CONCAT(tawasulPerson.phone2CountryCode,tawasulPerson.phone2) AS phone, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT tawasulPerson.phone2='' AND tawasulPerson.phone2Type='Mobile' AND tawasulPerson.phone2CountryCode='$countryCode' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID)" ;
                                        $sqlEmail2.=" UNION (SELECT CONCAT(tawasulPerson.phone3CountryCode,tawasulPerson.phone3) AS phone, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT tawasulPerson.phone3='' AND tawasulPerson.phone3Type='Mobile' AND tawasulPerson.phone3CountryCode='$countryCode' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID)" ;
                                        $sqlEmail2.=" UNION (SELECT CONCAT(tawasulPerson.phone4CountryCode,tawasulPerson.phone4) AS phone, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT tawasulPerson.phone4='' AND tawasulPerson.phone4Type='Mobile' AND tawasulPerson.phone4CountryCode='$countryCode' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID)" ;
                                        $resultEmail2=$connection2->prepare($sqlEmail2);
                                        $resultEmail2->execute($dataEmail2);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowEmail2=$resultEmail2->fetch()) {
                                        $countryCodeTemp = $countryCode;
                                        if ($rowEmail2["countryCode"]=="")
                                            $countryCodeTemp = $rowEmail2["countryCode"];
                                        $this->reportAdd($emailReceipt, $rowEmail2['tawasulPersonID'], 'Applicants', $t, 'SMS', $countryCodeTemp.$rowEmail2["phone"]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        //Houses
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_houses_all") OR isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_houses_my")) {
            if (!empty($_POST['houses']) && $_POST["houses"]=="Y") {
                $choices=$_POST["houseList"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("tawasulMessengerID"=>$AI, "id"=>$t);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Houses', id=:id" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }

                        if ($email=="Y") {
                            try {
                                $dataEmail=array("tawasulHouseID"=>$t);
                                $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT email='' AND tawasulHouseID=:tawasulHouseID AND status='Full'" ;
                                $resultEmail=$connection2->prepare($sqlEmail);
                                $resultEmail->execute($dataEmail);
                            }
                            catch(\PDOException $e) { }
                            while ($rowEmail=$resultEmail->fetch()) {
                                $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Houses', $t, 'Email', $rowEmail["email"]);
                            }
                        }
                        if ($sms=="Y" AND $countryCode!="") {
                            try {
                                $dataEmail=array("tawasulHouseID"=>$t);
                                $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone1='' AND phone1Type='Mobile' AND tawasulHouseID=:tawasulHouseID AND status='Full')" ;
                                $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone2='' AND phone2Type='Mobile' AND tawasulHouseID=:tawasulHouseID AND status='Full')" ;
                                $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone3='' AND phone3Type='Mobile' AND tawasulHouseID=:tawasulHouseID AND status='Full')" ;
                                $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone4='' AND phone4Type='Mobile' AND tawasulHouseID=:tawasulHouseID AND status='Full')" ;
                                $resultEmail=$connection2->prepare($sqlEmail);
                                $resultEmail->execute($dataEmail);
                            }
                            catch(\PDOException $e) { }
                            while ($rowEmail=$resultEmail->fetch()) {
                                $countryCodeTemp = $countryCode;
                                if ($rowEmail["countryCode"]=="")
                                    $countryCodeTemp = $rowEmail["countryCode"];
                                $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Houses', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                            }
                        }
                    }
                }
            }
        }

        //Transport
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_transport_any")) {
            if (!empty($_POST['transport']) && $_POST["transport"]=="Y") {
                $staff=$_POST["transportStaff"] ;
                $students=$_POST["transportStudents"] ;
                $parents="N" ;
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_transport_parents")) {
                    $parents=$_POST["transportParents"] ;
                }
                $choices=$_POST["transports"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("AI"=>$AI, "t"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:AI, type='Transport', id=:t, staff=:staff, students=:students, parents=:parents" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }

                        //Get email addresses
                        if ($email=="Y") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array('transport'=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND FIND_IN_SET(:transport, transport)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Transport', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), 'transport'=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND FIND_IN_SET(:transport, transport)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Transport', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataStudents=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), 'transport'=>$t);
                                    $sqlStudents="SELECT tawasulPerson.tawasulPersonID, surname, preferredName FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND FIND_IN_SET(:transport, transport)" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents=$resultStudents->fetch()) {
                                    try {
                                        $dataFamily=array("tawasulPersonID"=>$rowStudents["tawasulPersonID"]);
                                        $sqlFamily="SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily=$connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily=$resultFamily->fetch()) {
                                        try {
                                            $dataEmail=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                            $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactEmail='Y'" ;
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail=$resultEmail->fetch()) {
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Transport', $t, 'Email', $rowEmail["email"], $rowStudents['tawasulPersonID'], Format::name('', $rowStudents['preferredName'], $rowStudents['surname'], 'Student'));
                                        }
                                    }
                                }
                            }
                        }
                        if ($sms=="Y" AND $countryCode!="") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array('transport'=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND FIND_IN_SET(:transport, transport))" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND FIND_IN_SET(:transport, transport))" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND FIND_IN_SET(:transport, transport))" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND FIND_IN_SET(:transport, transport))" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Transport', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), 'transport'=>$t);
                                    $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND FIND_IN_SET(:transport, transport))" ;
                                    $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND FIND_IN_SET(:transport, transport))" ;
                                    $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND FIND_IN_SET(:transport, transport))" ;
                                    $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND FIND_IN_SET(:transport, transport))" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Transport', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataStudents=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), 'transport'=>$t);
                                    $sqlStudents="SELECT tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND FIND_IN_SET(:transport, transport)" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents=$resultStudents->fetch()) {
                                    try {
                                        $dataFamily=array("tawasulPersonID"=>$rowStudents["tawasulPersonID"]);
                                        $sqlFamily="SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily=$connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily=$resultFamily->fetch()) {
                                        try {
                                            $dataEmail=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                            $sqlEmail="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail=$resultEmail->fetch()) {
                                            $countryCodeTemp = $countryCode;
                                            if ($rowEmail["countryCode"]=="")
                                                $countryCodeTemp = $rowEmail["countryCode"];
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Transport', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        //Target Absent students / Attendance Status
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_attendance")) {
            if (!empty($_POST['attendance']) && $_POST["attendance"]=="Y") {
                $choices = $_POST["attendanceStatus"] ?? [];
                $students = $_POST["attendanceStudents"] ?? [];
                $parents = $_POST["attendanceParents"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("AI"=>$AI, "t"=>$t, "students"=>$students, "parents"=>$parents);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:AI, type='Attendance', id=:t, students=:students, parents=:parents" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }
                    }
                    //Get all logs by student, with latest log entry first.
                    try {
                        $data=array("selectedDate"=>date('Y-m-d'), "tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "nowDate"=>date("Y-m-d"));
                        $sql="SELECT galp.tawasulPersonID, galp.tawasulAttendanceLogPersonID, galp.type, galp.date FROM tawasulAttendanceLogPerson AS galp JOIN tawasulStudentEnrolment AS gse ON (galp.tawasulPersonID=gse.tawasulPersonID) JOIN tawasulPerson AS gp ON (gse.tawasulPersonID=gp.tawasulPersonID) WHERE gp.status='Full' AND (gp.dateStart IS NULL OR gp.dateStart<=:nowDate) AND (gp.dateEnd IS NULL OR gp.dateEnd>=:nowDate) AND gse.tawasulSchoolYearID=:tawasulSchoolYearID AND galp.date=:selectedDate ORDER BY galp.tawasulPersonID, tawasulAttendanceLogPersonID DESC" ;
                        $result=$connection2->prepare($sql);
                        $result->execute($data);
                    }
                    catch(\PDOException $e) { }

                    if ($result->rowCount()>=1) { //Log the personIDs of the students whose latest attendance log is in list of choices submitted by user
                        $selectedStudents=array();
                        $currentStudent="";
                        $lastStudent="";
                        while ($row=$result->fetch()) {
                            $currentStudent=$row["tawasulPersonID"] ;
                            if (in_array($row["type"], $choices) AND $currentStudent!=$lastStudent) {
                                $selectedStudents[]=$currentStudent ;
                            }
                            $lastStudent=$currentStudent ;
                        }

                        if (count($selectedStudents)>=1) {
                        //Get emails
                        if ($email=="Y") {
                            if ($parents=="Y") {
                            try {
                                $dataEmail=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "tawasulPersonIDs"=>implode(",",$selectedStudents));
                                $sqlEmail="SELECT DISTINCT parent.email, parent.tawasulPersonID, student.tawasulPersonID AS tawasulPersonIDStudent, student.surname, student.preferredName
                                    FROM tawasulPerson AS student
                                        JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=student.tawasulPersonID)
                                        JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=student.tawasulPersonID)
                                        JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                                        JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                                        JOIN tawasulPerson AS parent ON (tawasulFamilyAdult.tawasulPersonID=parent.tawasulPersonID)
                                    WHERE
                                        NOT parent.email=''
                                        AND student.status='Full'
                                        AND (student.dateStart IS NULL OR student.dateStart<='" . date("Y-m-d") . "')
                                        AND (student.dateEnd IS NULL  OR student.dateEnd>='" . date("Y-m-d") . "')
                                        AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                                        AND FIND_IN_SET(student.tawasulPersonID, :tawasulPersonIDs)
                                        AND parent.status='Full'
                                        AND contactEmail='Y'" ;
                                $resultEmail=$connection2->prepare($sqlEmail);
                                $resultEmail->execute($dataEmail);
                            }
                            catch(\PDOException $e) {}

                            while ($rowEmail=$resultEmail->fetch()) { //Add emails to list of receivers
                                $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Attendance', $t, 'Email', $rowEmail["email"], $rowEmail['tawasulPersonIDStudent'], Format::name('', $rowEmail['preferredName'], $rowEmail['surname'], 'Student'));
                            }
                            }
                            if ($students=="Y") {
                            try { //Get the email for each student
                                $dataEmail=array("tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "tawasulPersonIDs"=>implode(",", $selectedStudents));
                                $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPerson.tawasulPersonID AND FIND_IN_SET(tawasulPerson.tawasulPersonID, :tawasulPersonIDs)";
                                $resultEmail=$connection2->prepare($sqlEmail);
                                $resultEmail->execute($dataEmail);
                            }
                            catch(\PDOException $e) { }
                            while ($rowEmail=$resultEmail->fetch()) { //Add emails to list of receivers
                                $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Attendance', $t, 'Email', $rowEmail["email"]);
                            }
                            }
                        } //end get emails
                        
                        //Get SMS
                        if ($sms=="Y" AND $countryCode!="") {
                            if ($parents=="Y") {
                            try { //Get the familyIDs for each student logged
                                $dataFamily=array("tawasulPersonIDs"=>implode(",", $selectedStudents));
                                $sqlFamily="SELECT DISTINCT tawasulFamilyID FROM tawasulFamilyChild WHERE FIND_IN_SET(tawasulPersonID, :tawasulPersonIDs)" ;
                                $resultFamily=$connection2->prepare($sqlFamily);
                                $resultFamily->execute($dataFamily);
                                $resultFamilies = $resultFamily->fetchAll();
                            }
                            catch(\PDOException $e) { }

                            foreach ($resultFamilies as $rowFamily) { //Get the people for each familyID
                                try {
                                $dataPerson=array("tawasulFamilyID"=>$rowFamily["tawasulFamilyID"] );
                                $sqlPerson="SELECT DISTINCT tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y'" ;
                                $resultPerson=$connection2->prepare($sqlPerson);
                                $resultPerson->execute($dataPerson);
                                }
                                catch(\PDOException $e) { }
                                while ($rowPerson=$resultPerson->fetch()) { //Add phone numbers to SMS receivers
                                try {
                                    $dataSMS=array("tawasulPersonID"=>$rowPerson["tawasulPersonID"] );
                                    $sqlSMS="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone1='' AND phone1Type='Mobile' AND tawasulPersonID=:tawasulPersonID)" ;
                                    $sqlSMS.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone2='' AND phone2Type='Mobile' AND tawasulPersonID=:tawasulPersonID)" ;
                                    $sqlSMS.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone3='' AND phone3Type='Mobile' AND tawasulPersonID=:tawasulPersonID)" ;
                                    $sqlSMS.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone4='' AND phone4Type='Mobile' AND tawasulPersonID=:tawasulPersonID)" ;
                                    $resultSMS=$connection2->prepare($sqlSMS);
                                    $resultSMS->execute($dataSMS);
                                }
                                catch(\PDOException $e) { }
                                while ($rowSMS=$resultSMS->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                        if ($rowSMS["countryCode"]=="")
                                            $countryCodeTemp = $rowSMS["countryCode"];
                                        $this->reportAdd($emailReceipt, $rowSMS['tawasulPersonID'], 'Attendance', $t, 'SMS', $countryCodeTemp.$rowSMS["phone"]);
                                }
                                }
                            }
                            }
                            if ($students=="Y") {
                                try { //Get the phone numbers for each student
                                    foreach ($selectedStudents as $t) {
                                    $dataSMS=array("tawasulPersonID"=>$t);
                                    $sqlSMS="(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone1='' AND phone1Type='Mobile' AND tawasulPersonID=:tawasulPersonID AND status='Full')" ;
                                    $sqlSMS.=" UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone2='' AND phone2Type='Mobile' AND tawasulPersonID=:tawasulPersonID AND status='Full')" ;
                                    $sqlSMS.=" UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone3='' AND phone3Type='Mobile' AND tawasulPersonID=:tawasulPersonID AND status='Full')" ;
                                    $sqlSMS.=" UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT  phone4='' AND phone4Type='Mobile' AND tawasulPersonID=:tawasulPersonID AND status='Full')" ;
                                    $resultSMS=$connection2->prepare($sqlSMS);
                                    $resultSMS->execute($dataSMS);
                                    }
                                } catch(\PDOException $e) { }
                            
                                while ($rowSMS=$resultSMS->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                        if ($rowSMS["countryCode"]=="")
                                            $countryCodeTemp = $rowSMS["countryCode"];
                                        $this->reportAdd($emailReceipt, $rowSMS['tawasulPersonID'], 'Attendance', $t, 'SMS', $countryCodeTemp.$rowSMS["phone"]);
                                }
                            }
                        } //END SMS
                        }
                    }
                    }
                }
                }//END Target Absent students / Attendance Status


        //Groups
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_groups_my") OR isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_groups_any")) {
            if (!empty($_POST['group']) && $_POST["group"]=="Y") {
                $staff=$_POST["groupsStaff"] ;
                $students=$_POST["groupsStudents"] ;
                $parents="N" ;
                if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_groups_parents")) {
                    $parents=$_POST["groupsParents"] ;
                }
                $choices=$_POST["groups"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("tawasulMessengerID"=>$AI, "id"=>$t, "staff"=>$staff, "students"=>$students, "parents"=>$parents);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Group', id=:id, staff=:staff, students=:students, parents=:parents" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }

                        //Get email addresses
                        if ($email=="Y") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array("tawasulGroupID"=>$t);
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) WHERE NOT email='' AND status='Full' AND tawasulGroup.tawasulGroupID=:tawasulGroupID" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) {}
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Group', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulGroupID"=>$t, 'tawasulSchoolYearID' => $this->session->get('tawasulSchoolYearID'));
                                    $sqlEmail="SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) WHERE NOT email='' AND status='Full' AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulGroup.tawasulGroupID=:tawasulGroupID" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Group', $t, 'Email', $rowEmail["email"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataEmail=array("tawasulGroupID"=>$t, 'tawasulSchoolYearID' => $this->session->get('tawasulSchoolYearID'), "tawasulGroupID2"=>$t);
                                    $sqlEmail="(SELECT DISTINCT email, tawasulPerson.tawasulPersonID, NULL AS tawasulPersonIDStudent, NULL AS surname, NULL AS preferredName
                                        FROM tawasulPerson
                                            JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                                            JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID)
                                            JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID)
                                            JOIN tawasulFamily ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                                        WHERE
                                            NOT email=''
                                            AND contactEmail='Y'
                                            AND tawasulPerson.status='Full'
                                            AND tawasulGroup.tawasulGroupID=:tawasulGroupID)
                                        UNION
                                        (SELECT DISTINCT parent.email, parent.tawasulPersonID, student.tawasulPersonID AS tawasulPersonIDStudent, student.surname, student.preferredName
                                            FROM tawasulGroupPerson
                                                JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID)
                                                JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=tawasulGroupPerson.tawasulPersonID)
                                                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulFamilyChild.tawasulPersonID)
                                                JOIN tawasulPerson AS student ON (tawasulStudentEnrolment.tawasulPersonID=student.tawasulPersonID)
                                                JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                                                JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                                                JOIN tawasulPerson AS parent ON (tawasulFamilyAdult.tawasulPersonID=parent.tawasulPersonID)
                                            WHERE
                                                NOT parent.email=''
                                                AND contactEmail='Y'
                                                AND parent.status='Full'
                                                AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                                                AND tawasulGroup.tawasulGroupID=:tawasulGroupID2)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }

                                while ($rowEmail=$resultEmail->fetch()) {
                                    $paddedID = ($rowEmail['tawasulPersonIDStudent'] == '') ? NULL : str_pad($rowEmail['tawasulPersonIDStudent'], 10, '0', STR_PAD_LEFT);
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Group', $t, 'Email', $rowEmail["email"], $paddedID, Format::name('', $rowEmail['preferredName'], $rowEmail['surname'], 'Student'));
                                }
                            }
                        }
                        if ($sms=="Y" AND $countryCode!="") {
                            if ($staff=="Y") {
                                try {
                                    $dataEmail=array("tawasulGroupID"=>$t);
                                    $sqlEmail="(SELECT DISTINCT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulGroup.tawasulGroupID=:tawasulGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulGroup.tawasulGroupID=:tawasulGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulGroup.tawasulGroupID=:tawasulGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulGroup.tawasulGroupID=:tawasulGroupID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Group', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($students=="Y") {
                                try {
                                    $dataEmail=array("tawasulGroupID"=>$t, 'tawasulSchoolYearID' => $this->session->get('tawasulSchoolYearID'));
                                    $sqlEmail="(SELECT DISTINCT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulGroup.tawasulGroupID=:tawasulGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulGroup.tawasulGroupID=:tawasulGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulGroup.tawasulGroupID=:tawasulGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulGroup.tawasulGroupID=:tawasulGroupID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) { }
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Group', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                            if ($parents=="Y") {
                                try {
                                    $dataEmail=array("tawasulGroupID"=>$t, 'tawasulSchoolYearID' => $this->session->get('tawasulSchoolYearID'));
                                    $sqlEmail="(SELECT DISTINCT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulFamily ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE NOT phone1='' AND phone1Type='Mobile' AND contactSMS='Y' AND tawasulPerson.status='Full' AND tawasulGroup.tawasulGroupID=:tawasulGroupID)";
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulGroupPerson JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=tawasulGroupPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulFamilyChild.tawasulPersonID) JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND contactSMS='Y' AND tawasulPerson.status='Full' AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulGroup.tawasulGroupID=:tawasulGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulFamily ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE NOT phone2='' AND phone2Type='Mobile' AND contactSMS='Y' AND tawasulPerson.status='Full' AND tawasulGroup.tawasulGroupID=:tawasulGroupID)";
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulGroupPerson JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=tawasulGroupPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulFamilyChild.tawasulPersonID) JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND contactSMS='Y' AND tawasulPerson.status='Full' AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulGroup.tawasulGroupID=:tawasulGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulFamily ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE NOT phone3='' AND phone3Type='Mobile' AND contactSMS='Y' AND tawasulPerson.status='Full' AND tawasulGroup.tawasulGroupID=:tawasulGroupID)";
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulGroupPerson JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=tawasulGroupPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulFamilyChild.tawasulPersonID) JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND contactSMS='Y' AND tawasulPerson.status='Full' AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulGroup.tawasulGroupID=:tawasulGroupID)" ;
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulFamily ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE NOT phone4='' AND phone4Type='Mobile' AND contactSMS='Y' AND tawasulPerson.status='Full' AND tawasulGroup.tawasulGroupID=:tawasulGroupID)";
                                    $sqlEmail.=" UNION (SELECT DISTINCT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulGroupPerson JOIN tawasulGroup ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=tawasulGroupPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulFamilyChild.tawasulPersonID) JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND contactSMS='Y' AND tawasulPerson.status='Full' AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulGroup.tawasulGroupID=:tawasulGroupID)" ;
                                    $resultEmail=$connection2->prepare($sqlEmail);
                                    $resultEmail->execute($dataEmail);
                                }
                                catch(\PDOException $e) {}
                                while ($rowEmail=$resultEmail->fetch()) {
                                    $countryCodeTemp = $countryCode;
                                    if ($rowEmail["countryCode"]=="")
                                        $countryCodeTemp = $rowEmail["countryCode"];
                                    $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Group', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                }
                            }
                        }
                    }
                }
            }
        }

        // Mailing List
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_mailingList")) {
            if (!empty($_POST['mailingList']) && $_POST["mailingList"]=="Y") {
                $choices=$_POST["mailingLists"] ?? [];
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data=array("tawasulMessengerID"=>$AI, "id"=>$t);
                            $sql="INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Mailing List', id=:id, staff='N', students='N', parents='N'" ;
                            $result=$connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }

                        // Get email addresses
                        try {
                            $dataEmail = ["tawasulMessengerMailingListID"=>$t];
                            $sqlEmail = "SELECT DISTINCT email, tawasulMessengerMailingListRecipientID, `key` FROM tawasulMessengerMailingList JOIN tawasulMessengerMailingListRecipient ON (tawasulMessengerMailingListRecipient.tawasulMessengerMailingListIDList LIKE CONCAT('%', tawasulMessengerMailingList.tawasulMessengerMailingListID, '%')) WHERE NOT email='' AND tawasulMessengerMailingList.tawasulMessengerMailingListID=:tawasulMessengerMailingListID" ;
                            $resultEmail=$connection2->prepare($sqlEmail);
                            $resultEmail->execute($dataEmail);
                        }
                        catch(\PDOException $e) {}
                        while ($rowEmail=$resultEmail->fetch()) {
                            $this->reportAdd($emailReceipt, null, 'Mailing List', $t, 'Email', $rowEmail["email"], null, null, $rowEmail["key"]);
                        }
                    }       
                }
            }
        }

        // Individuals
        if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_post.php", "New Message_individuals")) {
            if ($_POST["individuals"]=="Y") {
                $choices=$_POST["individualList"] ?? [];
                $parents=$_POST["individualsParents"] ?? 'N';
                if (!empty($choices)) {
                    foreach ($choices as $t) {
                        try {
                            $data = ["tawasulMessengerID"=>$AI, "id"=>$t, "parents" => $parents];
                            $sql = "INSERT INTO tawasulMessengerTarget SET tawasulMessengerID=:tawasulMessengerID, type='Individuals', id=:id, staff='N', students='N', parents=:parents";
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        }
                        catch(\PDOException $e) {
                            $partialFail=TRUE;
                        }

                        if ($email=="Y") {
                            try {
                                $dataEmail = ["tawasulPersonID"=>$t];
                                $sqlEmail = "SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT email='' AND tawasulPersonID=:tawasulPersonID AND status='Full'" ;
                                $resultEmail = $connection2->prepare($sqlEmail);
                                $resultEmail->execute($dataEmail);
                            }
                            catch(\PDOException $e) { }
                            while ($rowEmail=$resultEmail->fetch()) {
                                $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Individuals', $t, 'Email', $rowEmail["email"]);
                            }

                            if ($parents=="Y") {
                                try {
                                    $dataStudents = ["tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "tawasulPersonID"=>$t];
                                    $sqlStudents = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPerson.tawasulPersonID=:tawasulPersonID";
                                    $resultStudents = $connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents = $resultStudents->fetch()) {
                                    try {
                                        $dataFamily = ["tawasulPersonID"=>$rowStudents["tawasulPersonID"]];
                                        $sqlFamily = "SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily = $connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily = $resultFamily->fetch()) {
                                        try {
                                            $dataEmail = ["tawasulFamilyID"=>$rowFamily["tawasulFamilyID"]];
                                            $sqlEmail = "SELECT DISTINCT email, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT email='' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactEmail='Y'" ;
                                            $resultEmail = $connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail = $resultEmail->fetch()) {
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Individuals', $t, 'Email', $rowEmail["email"], $rowStudents['tawasulPersonID'], Format::name('', $rowStudents['preferredName'], $rowStudents['surname'], 'Student'));
                                        }
                                    }
                                }
                            }
                        }

                        if ($sms == "Y" AND $countryCode != "") {
                            try {
                                $dataEmail = ["tawasulPersonID"=>$t];
                                $sqlEmail = "(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone1='' AND phone1Type='Mobile' AND tawasulPersonID=:tawasulPersonID AND status='Full')" ;
                                $sqlEmail.= " UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone2='' AND phone2Type='Mobile' AND tawasulPersonID=:tawasulPersonID AND status='Full')" ;
                                $sqlEmail.= " UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone3='' AND phone3Type='Mobile' AND tawasulPersonID=:tawasulPersonID AND status='Full')" ;
                                $sqlEmail.= " UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE NOT phone4='' AND phone4Type='Mobile' AND tawasulPersonID=:tawasulPersonID AND status='Full')" ;
                                $resultEmail = $connection2->prepare($sqlEmail);
                                $resultEmail->execute($dataEmail);
                            }
                            catch(\PDOException $e) { }
                            while ($rowEmail = $resultEmail->fetch()) {
                                $countryCodeTemp = $countryCode;
                                if ($rowEmail["countryCode"]=="")
                                    $countryCodeTemp = $rowEmail["countryCode"];
                                $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Individuals', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                            }

                            if ($parents=="Y") {
                                try {
                                    $dataStudents = ["tawasulSchoolYearID"=>$this->session->get('tawasulSchoolYearID'), "tawasulPersonID"=>$t];
                                    $sqlStudents = "SELECT tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='" . date("Y-m-d") . "') AND (dateEnd IS NULL  OR dateEnd>='" . date("Y-m-d") . "') AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPerson.tawasulPersonID=:tawasulPersonID" ;
                                    $resultStudents=$connection2->prepare($sqlStudents);
                                    $resultStudents->execute($dataStudents);
                                }
                                catch(\PDOException $e) { }
                                while ($rowStudents = $resultStudents->fetch()) {
                                    try {
                                        $dataFamily = ["tawasulPersonID"=>$rowStudents["tawasulPersonID"]];
                                        $sqlFamily = "SELECT DISTINCT tawasulFamily.tawasulFamilyID FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID" ;
                                        $resultFamily = $connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    }
                                    catch(\PDOException $e) { }
                                    while ($rowFamily = $resultFamily->fetch()) {
                                        try {
                                            $dataEmail = ["tawasulFamilyID"=>$rowFamily["tawasulFamilyID"]];
                                            $sqlEmail = "(SELECT phone1 AS phone, phone1CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone1='' AND phone1Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.= " UNION (SELECT phone2 AS phone, phone2CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone2='' AND phone2Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.= " UNION (SELECT phone3 AS phone, phone3CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone3='' AND phone3Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')" ;
                                            $sqlEmail.= " UNION (SELECT phone4 AS phone, phone4CountryCode AS countryCode, tawasulPerson.tawasulPersonID FROM tawasulPerson JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE NOT phone4='' AND phone4Type='Mobile' AND status='Full' AND tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID AND contactSMS='Y')";
                                            $resultEmail=$connection2->prepare($sqlEmail);
                                            $resultEmail->execute($dataEmail);
                                        }
                                        catch(\PDOException $e) { }
                                        while ($rowEmail = $resultEmail->fetch()) {
                                            $countryCodeTemp = $countryCode;
                                            if ($rowEmail["countryCode"] == "")
                                                $countryCodeTemp = $rowEmail["countryCode"];
                                            $this->reportAdd($emailReceipt, $rowEmail['tawasulPersonID'], 'Individuals', $t, 'SMS', $countryCodeTemp.$rowEmail["phone"]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $tawasulMessengerReceiptIDList = [];

        // Write report entries
        foreach ($this->report as $reportEntry) {
            try {
                $uniqueData = [
                    'tawasulMessengerID' => $AI,
                    'tawasulPersonID' => $reportEntry[0],
                ];

                // Prevent adding the record if it already exists in the table
                if (!empty($_POST['manualRecipient']) && !$this->messengerReceiptGateway->unique($uniqueData, ['tawasulMessengerID', 'tawasulPersonID'])) {
                    continue;
                }

                $confirmed = $reportEntry[5] != '' ? 'N' : null;

                $data = ["tawasulMessengerID"=>$AI, "tawasulPersonID"=>$reportEntry[0], "targetType"=>$reportEntry[1], "targetID"=>$reportEntry[2], "contactType"=>$reportEntry[3], "contactDetail"=>$reportEntry[4], "key"=>$reportEntry[5], "confirmed" => $confirmed, "tawasulPersonIDListStudent" => $reportEntry[6], 'nameListStudent' => json_encode($reportEntry[7]), 'unsubscribeKey' => $reportEntry[8]];
                $sql="INSERT INTO tawasulMessengerReceipt SET tawasulMessengerID=:tawasulMessengerID, tawasulPersonID=:tawasulPersonID, targetType=:targetType, targetID=:targetID, contactType=:contactType, contactDetail=:contactDetail, `key`=:key, confirmed=:confirmed, confirmedTimestamp=NULL, tawasulPersonIDListStudent=:tawasulPersonIDListStudent, nameListStudent=:nameListStudent, unsubscribeKey=:unsubscribeKey" ;
                $result=$connection2->prepare($sql);
                $result->execute($data);

                $tawasulMessengerReceiptID = str_pad($connection2->lastInsertID(), 14, '0', STR_PAD_LEFT);
                $tawasulMessengerReceiptIDList[] = $tawasulMessengerReceiptID;
            }
            catch(\PDOException $e) {
                $partialFail = true;
            }
        }
        
        return $tawasulMessengerReceiptIDList;
    }

    /**
     * Helps builds report array for setting tawasulMessengerReceipt
     *
     * @param [type] $emailReceipt
     * @param [type] $tawasulPersonID
     * @param [type] $targetType
     * @param [type] $targetID
     * @param [type] $contactType
     * @param [type] $contactDetail
     * @param [type] $tawasulPersonIDListStudent
     * @param [type] $nameStudent
     * @param [type] $unsubscribeKey
     * @return array
     */
    private function reportAdd($emailReceipt, $tawasulPersonID, $targetType, $targetID, $contactType, $contactDetail, $tawasulPersonIDListStudent = null, $nameStudent = null, $unsubscribeKey = null)
    {
        if ($contactDetail != '' AND is_null($contactDetail) == false) {
            $count = 0;
            $unique = true;
            $uniqueCount = 0;

            // Use password policy to generate random string
            $randStrGenerator = new PasswordPolicy(true, true, false, 40);

            foreach ($this->report as $reportEntry) {
                if ($reportEntry[4] == $contactDetail && $unique) {
                    $unique = false;
                    $uniqueCount = $count;
                }
                $count ++;
            }

            if ($unique) { //Entry is unique, so create
                $count = count($this->report);
                $this->report[$count][0] = $tawasulPersonID;
                $this->report[$count][1] = $targetType;
                $this->report[$count][2] = $targetID;
                $this->report[$count][3] = $contactType;
                $this->report[$count][4] = $contactDetail;
                if ($contactType == 'Email' and $emailReceipt == 'Y') {
                    $this->report[$count][5] = $randStrGenerator->generate();
                }
                else {
                    $this->report[$count][5] = null;
                }
                $this->report[$count][6] = $tawasulPersonIDListStudent;
                $this->report[$count][7] = [$nameStudent];
                $this->report[$count][8] = $unsubscribeKey;
                
            }
            else { //Entry is not unique, so apend student details
                $this->report[$uniqueCount][6] = (empty($this->report[$uniqueCount][6])) ? $tawasulPersonIDListStudent : (!empty($tawasulPersonIDListStudent) ? $this->report[$uniqueCount][6].','.$tawasulPersonIDListStudent : $this->report[$uniqueCount][6]);

                if (empty($this->report[$uniqueCount][7])) {
                    $this->report[$uniqueCount][7] = [$nameStudent];
                } else {
                    $this->report[$uniqueCount][7][] = $nameStudent;
                }
            }
        }
    }
}
