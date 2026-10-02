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

use TawasulOS\Services\Format;

function getLessons($guid, $connection2, $and = '')
{
    global $session;

    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');

    $fields = 'tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, timeStart, timeEnd, viewableStudents, viewableParents, homework, homeworkDetails, date, tawasulPlannerEntry.tawasulCourseClassID, homeworkCrowdAssessOtherTeachersRead, homeworkCrowdAssessClassmatesRead, homeworkCrowdAssessOtherStudentsRead, homeworkCrowdAssessSubmitterParentsRead, homeworkCrowdAssessClassmatesParentsRead, homeworkCrowdAssessOtherParentsRead';
    //Get my classes (student, teacher, classmates)
    $data = array('today1' => $today, 'tawasulPersonID1' => $session->get('tawasulPersonID'), 'now1' => $now, 'tawasulSchoolYearID1' => $session->get('tawasulSchoolYearID'));
    $sql = "(SELECT $fields FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE homeworkSubmissionDateOpen<=:today1 AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID1 AND (role='Teacher' OR role='Student') AND homeworkCrowdAssess='Y' AND ADDTIME(date, '1344:00:00.0')>=:now1 AND tawasulSchoolYearID=:tawasulSchoolYearID1 $and)";

    //Get other classes if teacher
    $dataTeacher = array('tawasulPersonID' => $session->get('tawasulPersonID'));
    $sqlTeacher = "SELECT * FROM tawasulStaff WHERE tawasulPersonID=:tawasulPersonID AND type='Teaching'";
    $resultTeacher = $connection2->prepare($sqlTeacher);
    $resultTeacher->execute($dataTeacher);
    if ($resultTeacher->rowCount() == 1) {
        $data['today2'] = $today;
        $data['tawasulSchoolYearID2'] = $session->get('tawasulSchoolYearID');
        $data['now2'] = $now;
        $sql = $sql." UNION (SELECT $fields FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE homeworkSubmissionDateOpen<=:today2 AND homeworkCrowdAssess='Y' AND ADDTIME(date, '1344:00:00.0')>=:now2 AND tawasulSchoolYearID=:tawasulSchoolYearID2 AND homeworkCrowdAssessOtherTeachersRead='Y' $and)";
    }

    //Get other classes if student
    $dataStudent = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
    $sqlStudent = 'SELECT * FROM tawasulStudentEnrolment WHERE tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID';
    $resultStudent = $connection2->prepare($sqlStudent);
    $resultStudent->execute($dataStudent);
    if ($resultStudent->rowCount() == 1) {
        $data['today3'] = $today;
        $data['tawasulSchoolYearID3'] = $session->get('tawasulSchoolYearID');
        $data['now3'] = $now;
        $sql = $sql." UNION (SELECT $fields FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE homeworkSubmissionDateOpen<=:today3 AND homeworkCrowdAssess='Y' AND ADDTIME(date, '1344:00:00.0')>=:now3 AND tawasulSchoolYearID=:tawasulSchoolYearID3 AND homeworkCrowdAssessOtherStudentsRead='Y' $and)";
    }

    //Get classes if parent
    $dataParent = array('tawasulPersonID' => $session->get('tawasulPersonID'));
    $sqlParent = "SELECT * FROM tawasulFamilyAdult WHERE tawasulPersonID=:tawasulPersonID AND childDataAccess='Y'";
    $resultParent = $connection2->prepare($sqlParent);
    $resultParent->execute($dataParent);

    if ($resultParent->rowCount() > 0) {
        //Get child list for family
        $childCount = 0;
        while ($rowParent = $resultParent->fetch()) {
            $dataChild = array('tawasulFamilyID' => $rowParent['tawasulFamilyID']);
            $sqlChild = "SELECT tawasulPerson.tawasulPersonID, image_240, surname, preferredName, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.nameShort AS formGroup FROM tawasulFamilyChild JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulFamilyID=:tawasulFamilyID AND tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') ORDER BY surname, preferredName ";
            $resultChild = $connection2->prepare($sqlChild);
            $resultChild->execute($dataChild);
            while ($rowChild = $resultChild->fetch()) {
                //submitters+classmates parents
                $data['today4'.$childCount] = $today;
                $data['tawasulSchoolYearID4'.$childCount] = $session->get('tawasulSchoolYearID');
                $data['now4'.$childCount] = $now;
                $data['tawasulPersonID4'.$childCount] = $rowChild['tawasulPersonID'];
                $sql = $sql." UNION (SELECT $fields FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE homeworkSubmissionDateOpen<=:today4$childCount AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID4$childCount AND role='Student' AND homeworkCrowdAssess='Y' AND ADDTIME(date, '1344:00:00.0')>=:now4$childCount AND tawasulSchoolYearID=:tawasulSchoolYearID4$childCount AND (homeworkCrowdAssessSubmitterParentsRead='Y' OR homeworkCrowdAssessClassmatesParentsRead='Y') $and)";
                ++$childCount;
            }
        }
        //Other classes
        $data['today5'] = $today;
        $data['tawasulSchoolYearID5'] = $session->get('tawasulSchoolYearID');
        $data['now5'] = $now;
        $sql = $sql." UNION (SELECT $fields FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE homeworkSubmissionDateOpen<=:today5 AND homeworkCrowdAssess='Y' AND ADDTIME(date, '1344:00:00.0')>=:now5 AND tawasulSchoolYearID=:tawasulSchoolYearID5 AND homeworkCrowdAssessOtherParentsRead='Y' $and)";
    }

    return array($data, $sql);
}

function getCARole($guid, $connection2, $tawasulCourseClassID)
{
    global $session;

    $role = '';
    if ($session->get('tawasulRoleIDCurrentCategory') == 'Parent') {
        $role = 'Parent';
        $childInClass = false;

        //Is child of this perosn in this class?
        $count = 0;
        $children = array();

        $dataParent = array('tawasulPersonID' => $session->get('tawasulPersonID'));
        $sqlParent = "SELECT * FROM tawasulFamilyAdult WHERE tawasulPersonID=:tawasulPersonID AND childDataAccess='Y'";
        $resultParent = $connection2->prepare($sqlParent);
        $resultParent->execute($dataParent);

        if ($resultParent->rowCount() > 0) {
            //Get child list for family
            while ($rowParent = $resultParent->fetch()) {

                    $dataChild = array('tawasulFamilyID' => $rowParent['tawasulFamilyID']);
                    $sqlChild = "SELECT tawasulPerson.tawasulPersonID, image_240, surname, preferredName, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.nameShort AS formGroup FROM tawasulFamilyChild JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulFamilyID=:tawasulFamilyID AND tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') ORDER BY surname, preferredName ";
                    $resultChild = $connection2->prepare($sqlChild);
                    $resultChild->execute($dataChild);
                while ($rowChild = $resultChild->fetch()) {

                        $dataInClass = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonID' => $rowChild['tawasulPersonID']);
                        $sqlInClass = "SELECT * FROM tawasulCourseClassPerson WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulPersonID=:tawasulPersonID AND role='Student'";
                        $resultInClass = $connection2->prepare($sqlInClass);
                        $resultInClass->execute($dataInClass);
                    if ($resultInClass->rowCount() == 1) {
                        $childInClass = true;
                        $rowInClass = $resultInClass->fetch();
                        $children[$count] = $rowInClass['tawasulPersonID'];
                        ++$count;
                    }
                }
            }
        }
        if ($childInClass == true) {
            $role = 'Parent - Child In Class';
        }
    } else {
        //Check if in staff table as teacher
        $dataTeacher = array('tawasulPersonID' => $session->get('tawasulPersonID'));
        $sqlTeacher = "SELECT * FROM tawasulStaff WHERE tawasulPersonID=:tawasulPersonID AND type='Teaching'";
        $resultTeacher = $connection2->prepare($sqlTeacher);
        $resultTeacher->execute($dataTeacher);

        if ($resultTeacher->rowCount() == 1) {
            $role = 'Teacher';
            $dataRole = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
            $sqlRole = "SELECT * FROM tawasulCourseClassPerson WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulPersonID=:tawasulPersonID AND role='Teacher'";
            $resultRole = $connection2->prepare($sqlRole);
            $resultRole->execute($dataRole);
            if ($resultRole->rowCount() >= 1) {
                $role = 'Teacher - In Class';
            }
        }

        //Check if student
        $dataStudent = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
        $sqlStudent = 'SELECT * FROM tawasulStudentEnrolment WHERE tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID';
        $resultStudent = $connection2->prepare($sqlStudent);
        $resultStudent->execute($dataStudent);

        if ($resultStudent->rowCount() == 1) {
            $role = 'Student';
            $dataRole = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
            $sqlRole = "SELECT * FROM tawasulCourseClassPerson WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulPersonID=:tawasulPersonID AND role='Student'";
            $resultRole = $connection2->prepare($sqlRole);
            $resultRole->execute($dataRole);
            if ($resultRole->rowCount() == 1) {
                $role = 'Student - In Class';
            }
        }
    }

    return $role;
}

function getStudents($guid, $connection2, $role, $tawasulCourseClassID, $homeworkCrowdAssessOtherTeachersRead, $homeworkCrowdAssessOtherParentsRead, $homeworkCrowdAssessSubmitterParentsRead, $homeworkCrowdAssessClassmatesParentsRead, $homeworkCrowdAssessOtherStudentsRead, $homeworkCrowdAssessClassmatesRead, $and = '')
{
    global $session;

    $data = null;
    $sqlList = null;
    //Fetch and display assessible submissions
    $sqlList = '';
    if (($role == 'Teacher' and $homeworkCrowdAssessOtherTeachersRead == 'Y') or ($role == 'Teacher - In Class')) {
        //Get All students in class
        $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
        $sqlList = "SELECT * FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID) WHERE tawasulCourseClassID=:tawasulCourseClassID AND role='Student' AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') $and ORDER BY surname, preferredName";
    } elseif ($role == 'Parent' and $homeworkCrowdAssessOtherParentsRead == 'Y') {
        //Get all students in class
        $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
        $sqlList = "SELECT * FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID) WHERE tawasulCourseClassID=:tawasulCourseClassID AND role='Student' AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') $and ORDER BY surname, preferredName";
    } elseif ($role == 'Parent - Child In Class') {
        //Get array of children
        $count = 0;
        $children = array();
        $dataParent = array('tawasulPersonID' => $session->get('tawasulPersonID'));
        $sqlParent = "SELECT * FROM tawasulFamilyAdult WHERE tawasulPersonID=:tawasulPersonID AND childDataAccess='Y'";
        $resultParent = $connection2->prepare($sqlParent);
        $resultParent->execute($dataParent);
        if ($resultParent->rowCount() > 0) {
            //Get child list for family
            $childCount = 0;
            while ($rowParent = $resultParent->fetch()) {

                    $dataChild = array('tawasulFamilyID' => $rowParent['tawasulFamilyID']);
                    $sqlChild = "SELECT tawasulPerson.tawasulPersonID, image_240, surname, preferredName, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.nameShort AS formGroup FROM tawasulFamilyChild JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulFamilyID=:tawasulFamilyID AND tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') ORDER BY surname, preferredName ";
                    $resultChild = $connection2->prepare($sqlChild);
                    $resultChild->execute($dataChild);
                while ($rowChild = $resultChild->fetch()) {
                    $children[$count] = $rowChild['tawasulPersonID'];
                    ++$count;
                }
            }
        }

        if ($homeworkCrowdAssessSubmitterParentsRead == 'Y' and $homeworkCrowdAssessClassmatesParentsRead == 'Y') {
            //Get all students in class
            $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
            $sqlList = "SELECT * FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID) WHERE tawasulCourseClassID=:tawasulCourseClassID AND role='Student' AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') $and ORDER BY surname, preferredName";
        } elseif ($homeworkCrowdAssessSubmitterParentsRead == 'Y') {
            //Get only parent's children
            $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
            $sqlListWhere = 'AND (';
            for ($i = 0; $i < $count; ++$i) {
                $data[$children[$i]] = $children[$i];
                $sqlListWhere .= 'tawasulCourseClassPerson.tawasulPersonID=:'.$children[$i].' OR ';
            }
            if ($sqlListWhere == 'AND (') {
                $sqlListWhere = '';
            } else {
                $sqlListWhere = substr($sqlListWhere, 0, -4).')';
            }
            $sqlList = "SELECT * FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID) WHERE tawasulCourseClassID=:tawasulCourseClassID AND role='Student' AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') $sqlListWhere $and ORDER BY surname, preferredName";
        } elseif ($homeworkCrowdAssessClassmatesParentsRead == 'Y') {
            //Get all children except parent's children
            $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
            $sqlListWhere = '';
            for ($i = 0; $i < $count; ++$i) {
                $data[$children[$i]] = $children[$i];
                $sqlListWhere .= ' AND NOT tawasulCourseClassPerson.tawasulPersonID=:'.$children[$i];
            }
            $sqlList = "SELECT * FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID) WHERE tawasulCourseClassID=:tawasulCourseClassID AND role='Student' AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') $sqlListWhere $and ORDER BY surname, preferredName";
        }
    } elseif (($role == 'Student' and $homeworkCrowdAssessOtherStudentsRead == 'Y') or ($role == 'Student - In Class' and $homeworkCrowdAssessClassmatesRead == 'Y')) {
        $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
        $sqlList = "SELECT * FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID) WHERE tawasulCourseClassID=:tawasulCourseClassID AND role='Student' AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') $and ORDER BY surname, preferredName";
    } elseif ($role == 'Student - In Class') {
        $data = array('tawasulCourseClassID' => $tawasulCourseClassID,'tawasulPersonID' => $session->get('tawasulPersonID'));
        $sqlList = "SELECT * FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID) WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Student' AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') $and ORDER BY surname, preferredName";
    }

    return array($data, $sqlList);
}
