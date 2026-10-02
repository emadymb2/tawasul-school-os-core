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

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Domain\DataSet;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\UI\Components\Alert;
use TawasulOS\Tables\View\GridView;
use TawasulOS\UI\Timetable\Timetable;
use TawasulOS\Domain\User\RoleGateway;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Domain\System\HookGateway;
use TawasulOS\Domain\User\FamilyGateway;
use TawasulOS\Domain\School\HouseGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\UI\Timetable\TimetableContext;
use TawasulOS\Domain\School\YearGroupGateway;
use TawasulOS\Domain\Students\MedicalGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\School\SchoolYearGateway;
use TawasulOS\Domain\Students\FirstAidGateway;
use TawasulOS\Domain\System\AlertLevelGateway;
use TawasulOS\Domain\FormGroups\FormGroupGateway;
use TawasulOS\Domain\Planner\PlannerEntryGateway;
use TawasulOS\Domain\Students\StudentNoteGateway;;
use TawasulOS\Domain\School\SchoolYearTermGateway;
use TawasulOS\Domain\User\PersonalDocumentGateway;
use TawasulOS\Module\Planner\Tables\HomeworkTable;
use TawasulOS\Domain\Departments\DepartmentGateway;
use TawasulOS\Module\Attendance\StudentHistoryData;
use TawasulOS\Module\Attendance\StudentHistoryView;
use TawasulOS\Domain\Planner\PlannerEntryHomeworkGateway;
use Tos\Module\TawasulStudents\StudentAttendanceStatus;
use Tos\Module\TawasulStudents\View\LibraryBorrowingView;
use TawasulOS\Module\Reports\Domain\ReportArchiveEntryGateway;

//Module includes for User Admin (for custom fields)
include './modules/TawasulUserAdmin/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/student_view_details.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->scripts->add('chart');

    /** @var RoleGateway */
    $roleGateway = $container->get(RoleGateway::class);

    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    } else {
        $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
        $search = $_GET['search'] ?? '';
        $allStudents = $_GET['allStudents'] ?? '';
        $sort = $_GET['sort'] ?? '';

        if ($tawasulPersonID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
            return;
        } else {
            $settingGateway = $container->get(SettingGateway::class);
            $hookGateway = $container->get(HookGateway::class);
            $enableStudentNotes = $settingGateway->getSettingByScope('Students', 'enableStudentNotes');
            $skipBrief = false;

            //Skip brief for those with _full or _fullNoNotes, and _brief
            if ($highestAction == 'View Student Profile_fullEditAllNotes' || $highestAction == 'View Student Profile_full' || $highestAction == 'View Student Profile_fullNoNotes') {
                $skipBrief = true;
            }

            //Test if View Student Profile_myChildren is available and parent has access to this student...if so, skip brief, and go to full.
            if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/student_view_details.php', 'View Student Profile_myChildren')) {
                    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID1' => $_GET['tawasulPersonID'], 'tawasulPersonID2' => $session->get('tawasulPersonID'));
                    $sql = "SELECT * FROM tawasulFamilyChild JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND tawasulFamilyChild.tawasulPersonID=:tawasulPersonID1 AND tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID2 AND childDataAccess='Y'";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                if ($result->rowCount() == 1) {
                    $skipBrief = true;
                }
            }

            if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/student_view_details.php', 'View Student Profile_my')) {
                if ($tawasulPersonID == $session->get('tawasulPersonID')) {
                    $skipBrief = true;
                } elseif (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/student_view_details.php', 'View Student Profile_brief')) {
                    $highestAction = 'View Student Profile_brief';
                } else {
                    //Acess denied
                    $page->addError(__('You do not have access to this action.'));
                    return;
                }
            }

            if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/student_view_details.php', 'View Student Profile_brief') and $skipBrief == false) {
                //Proceed!
                $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID);
                $sql = "SELECT tawasulPerson.*, tawasulStudentEnrolment.tawasulSchoolYearID, tawasulStudentEnrolment.tawasulYearGroupID, tawasulStudentEnrolment.tawasulFormGroupID, tawasulStudentEnrolment.rollOrder FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND tawasulPerson.tawasulPersonID=:tawasulPersonID";
                $result = $connection2->prepare($sql);
                $result->execute($data);

                if ($result->rowCount() != 1) {
                    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                } else {
                    $row = $result->fetch();
                    $studentImage=$row['image_240'] ;

                    $page->breadcrumbs
                        ->add(__('View Student Profiles'), 'student_view.php')
                        ->add(Format::name('', $row['preferredName'], $row['surname'], 'Student'));

                    echo "<table class='smallIntBorder' cellspacing='0' style='width: 100%'>";
                    echo '<tr>';
                    echo "<td style='width: 33%; vertical-align: top'>";
                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Year Group').'</span><br/>';

                    $dataDetail = array('tawasulYearGroupID' => $row['tawasulYearGroupID']);
                    $sqlDetail = 'SELECT * FROM tawasulYearGroup WHERE tawasulYearGroupID=:tawasulYearGroupID';
                    $resultDetail = $connection2->prepare($sqlDetail);
                    $resultDetail->execute($dataDetail);
                    if ($resultDetail->rowCount() == 1) {
                        $rowDetail = $resultDetail->fetch();
                        echo __($rowDetail['name']);
                    }
                    echo '</td>';
                    echo "<td style='width: 34%; vertical-align: top'>";
                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Form Group').'</span><br/>';

                    $dataDetail = array('tawasulFormGroupID' => $row['tawasulFormGroupID']);
                    $sqlDetail = 'SELECT * FROM tawasulFormGroup WHERE tawasulFormGroupID=:tawasulFormGroupID';
                    $resultDetail = $connection2->prepare($sqlDetail);
                    $resultDetail->execute($dataDetail);
                    if ($resultDetail->rowCount() == 1) {
                        $rowDetail = $resultDetail->fetch();
                        echo $rowDetail['name'];
                    }
                    echo '</td>';
                    echo "<td style='width: 34%; vertical-align: top'>";
                    echo "<span style='font-size: 115%; font-weight: bold'>".__('House').'</span><br/>';

                    $dataDetail = array('tawasulHouseID' => $row['tawasulHouseID']);
                    $sqlDetail = 'SELECT * FROM tawasulHouse WHERE tawasulHouseID=:tawasulHouseID';
                    $resultDetail = $connection2->prepare($sqlDetail);
                    $resultDetail->execute($dataDetail);
                    if ($resultDetail->rowCount() == 1) {
                        $rowDetail = $resultDetail->fetch();
                        echo $rowDetail['name'];
                    }
                    echo '</td>';
                    echo '</tr>';
                    echo '<tr>';
                    echo "<td style='width: 33%; padding-top: 15px; vertical-align: top'>";
                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Email').'</span><br/>';
                    if ($row['email'] != '') {
                        $row['email'] = filter_var(trim($row['email']), FILTER_SANITIZE_EMAIL);
                        echo "<i><a href='mailto:".$row['email']."'>".$row['email'].'</a></i>';
                    }
                    echo '</td>';
                    echo "<td style='width: 33%; padding-top: 15px; vertical-align: top'>";
                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Website').'</span><br/>';
                    if ($row['website'] != '') {
                        echo "<i><a href='".$row['website']."'>".$row['website'].'</a></i>';
                    }
                    echo '</td>';
                    echo "<td style='width: 33%; padding-top: 15px; vertical-align: top'></td>";
                    echo '</tr>';
                    echo '</table>';

                    //Set sidebar
                    $session->set('sidebarExtra', Format::userPhoto($row['image_240'], 240));
                }
                return;
            } else {
                try {
                    if ($highestAction == 'View Student Profile_myChildren') {
                        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID1' => $_GET['tawasulPersonID'], 'tawasulPersonID2' => $session->get('tawasulPersonID'), 'today' => date('Y-m-d'));
                        $sql = "SELECT tawasulPerson.*, tawasulStudentEnrolment.tawasulSchoolYearID, tawasulStudentEnrolment.tawasulYearGroupID, tawasulStudentEnrolment.tawasulFormGroupID, tawasulStudentEnrolment.rollOrder FROM tawasulFamilyChild
                            JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                            JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                            JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID)
                            JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                            WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPerson.status='Full'
                            AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today)
                            AND tawasulFamilyChild.tawasulPersonID=:tawasulPersonID1
                            AND tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID2
                            AND childDataAccess='Y'";
                    } elseif ($highestAction == 'View Student Profile_my') {
                        $tawasulPersonID = $session->get('tawasulPersonID');
                        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID, 'today' => date('Y-m-d'));
                        $sql = "SELECT tawasulPerson.*, tawasulStudentEnrolment.tawasulSchoolYearID, tawasulStudentEnrolment.tawasulYearGroupID, tawasulStudentEnrolment.tawasulFormGroupID, tawasulStudentEnrolment.rollOrder FROM tawasulPerson
                            LEFT JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                            WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID
                            AND tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPerson.status='Full'
                            AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL OR dateEnd>=:today)";
                    } elseif ($highestAction == 'View Student Profile_fullEditAllNotes' || $highestAction == 'View Student Profile_full' || $highestAction == 'View Student Profile_fullNoNotes') {
                        if ($allStudents != 'on') {
                            $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID, 'today' => date('Y-m-d'));
                            $sql = "SELECT tawasulPerson.*, tawasulStudentEnrolment.tawasulSchoolYearID, tawasulStudentEnrolment.tawasulYearGroupID, tawasulStudentEnrolment.tawasulFormGroupID, tawasulStudentEnrolment.rollOrder FROM tawasulPerson
                                JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                                WHERE tawasulSchoolYearID=:tawasulSchoolYearID
                                AND tawasulPerson.tawasulPersonID=:tawasulPersonID AND status='Full'
                                AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today) ";
                        } else {
                            $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                            $sql = "SELECT tawasulPerson.*, tawasulStudentEnrolment.tawasulSchoolYearID, tawasulStudentEnrolment.tawasulYearGroupID, tawasulStudentEnrolment.tawasulFormGroupID, tawasulStudentEnrolment.rollOrder FROM tawasulPerson
                                LEFT JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID)
                                WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID";
                        }
                    } else {
                        //Acess denied
                        $page->addError(__('You do not have access to this action.'));
                        return;
                    }
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    return;
                }

                if ($result->rowCount() != 1) {
                    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                    return;
                } else {
                    $row = $result->fetch();
                    $studentImage=$row['image_240'] ;

                    $page->breadcrumbs
                    ->add(__('View Student Profiles'), 'student_view.php')
                    ->add(Format::name('', $row['preferredName'], $row['surname'], 'Student'));

                    $subpage = $_GET['subpage'] ?? '';
                    $hook = $_GET['hook'] ?? '';

                    // When viewing left students, they won't have a year group ID
                    if (empty($row['tawasulYearGroupID'])) {
                        $row['tawasulYearGroupID'] = '';
                    }

                    if ($subpage == '' and $hook == '') {
                        $subpage = 'Overview';
                    }

                    if ($search != '' or $allStudents != '') {
                         $params = [
                            "search" => $search,
                            "allStudents" => $allStudents,
                        ];
                        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulStudents', 'student_view.php')->withQueryParams($params));
                    }

                    echo '<h2>';
                    if ($subpage == 'Homework') {
                        $homeworkNamePlural = $settingGateway->getSettingByScope('Planner', 'homeworkNamePlural');
                        echo __($homeworkNamePlural);
                    } elseif ($subpage != '') {
                        echo __($subpage);
                    } else {
                        echo $hook;
                    }
                    echo '</h2>';

                    if ($subpage == 'Overview') {
                        /** @var MedicalGateway */
                        $medicalGateway = $container->get(MedicalGateway::class);
                        //Medical alert!
                        $alert = $medicalGateway->getHighestMedicalRisk($tawasulPersonID);
                        if (!empty($alert)) {
                            echo "<div class='error' style='background-color: #".$alert['colorBG'].'; border: 1px solid #'.$alert['color'].'; color: #'.$alert['color']."'>";
                            echo '<b>'.__('This student has one or more {level} risk medical conditions.', ['level' => __($alert['name'])]).'</b>';
                            echo '</div>';
                        }

                        // Show student's attendance and Current Location
                        $currentAttendanceStatus = $container->get(StudentAttendanceStatus::class)->getCurrentAttendanceStatus($session->get('tawasulSchoolYearID'), $tawasulPersonID, $row['preferredName'], $row['surname']);
                        
                        echo $currentAttendanceStatus ?? '';

                        $table = DataTable::createDetails('generalInfo');

                        $table->setTitle(__('General Information'));

                        if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/user_manage.php') == true) {
                            $table->addHeaderAction('view', __('View Status Log'))
                                    ->displayLabel()
                                    ->addParam('tawasulPersonID', $tawasulPersonID)
                                    ->setURL('/modules/TawasulUserAdmin/user_manage_view_status_log.php')
                                    ->modalWindow();

                            $table->addHeaderAction('edit', __('Edit'))
                                    ->displayLabel()
                                    ->addParam('tawasulPersonID', $tawasulPersonID)
                                    ->setURL('/modules/TawasulUserAdmin/user_manage_edit.php');
                        }

                        $table->addColumn('name', __('Name'))
                                ->format(Format::using('name', ['', 'preferredName', 'surname', 'Student']));

                        $table->addColumn('officialName', __('Official Name'));

                        $table->addColumn('nameInCharacters', __('Name In Characters'));

                        $table->addColumn('yearGroup', __('Year Group'))
                                ->format(function($row) use ($container, $settingGateway) {
                                    if (isset($row['tawasulYearGroupID'])) {
                                        $yearGroupGateway = $container->get(YearGroupGateway::class);
                                        $yearGroup = $yearGroupGateway->getByID($row['tawasulYearGroupID']);
                                        $output = '';
                                        if (!empty($yearGroup)) {
                                            $output .= __($yearGroup['name']);
                                            $dayTypeOptions = $settingGateway->getSettingByScope('User Admin', 'dayTypeOptions');
                                            if (!empty($dayTypeOptions) && !empty($row['dayType'])) {
                                                $output .= ' ('.$row['dayType'].')';
                                            }
                                            $output .= '</i><br/>';
                                        }
                                        return $output;
                                    }
                                });

                        $table->addColumn('formGroup', __('Form Group'))
                                ->format(function($row) use ($container, $guid, $connection2, $session) {
                                    if (isset($row['tawasulFormGroupID'])) {
                                        $formGroupGateway = $container->get(FormGroupGateway::class);
                                        $formGroup = $formGroupGateway->getByID($row['tawasulFormGroupID']);
                                        $output = '';
                                        if (!empty($formGroup)) {
                                            if (isActionAccessible($guid, $connection2, '/modules/TawasulFormGroups/formGroups_details.php')) {
                                                $output .= Format::link('./index.php?q=/modules/TawasulFormGroups/formGroups_details.php&tawasulFormGroupID='.$formGroup['tawasulFormGroupID'], $formGroup['name']);
                                            } else {
                                                $output .= $formGroup['name'];
                                            }
                                        }
                                        return $output;
                                    }
                                });

                        $table->addColumn('tutors', __('Tutors'))
                                ->format(function($row) use ($connection2, $guid, $container) {
                                    $output = '';

                                    $formGroupGateway = $container->get(FormGroupGateway::class);
                                    $formGroup = $formGroupGateway->getByID($row['tawasulFormGroupID']);

                                    if (isset($formGroup['tawasulPersonIDTutor'])) {
                                        $dataDetail = array('tawasulFormGroupID' => $row['tawasulFormGroupID']);
                                        $sqlDetail = 'SELECT tawasulPersonID, title, surname, preferredName FROM tawasulFormGroup JOIN tawasulPerson ON (tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID) WHERE tawasulFormGroupID=:tawasulFormGroupID ORDER BY surname, preferredName';
                                        $resultDetail = $connection2->prepare($sqlDetail);
                                        $resultDetail->execute($dataDetail);

                                        while ($rowDetail = $resultDetail->fetch()) {
                                            if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_view_details.php')) {
                                                $output .= Format::nameLinked($rowDetail['tawasulPersonID'], '', $rowDetail['preferredName'], $rowDetail['surname'], 'Staff', false, true);
                                            } else {
                                                $output .= Format::name($rowDetail['title'], $rowDetail['preferredName'], $rowDetail['surname'], 'Staff');
                                            }
                                            if ($rowDetail['tawasulPersonID'] == $formGroup['tawasulPersonIDTutor'] && $resultDetail->rowCount() > 1) {
                                                $output .= ' ('.__('Main Tutor').')';
                                            }
                                            $output .= '<br/>';
                                        }
                                    }
                                    return $output;
                                });

                        $table->addColumn('username', __('Username'));

                        $table->addColumn('age', __('Age'))
                                ->format(function($row) {
                                    if (!is_null($row['dob']) && $row['dob'] != '0000-00-00') {
                                        return Format::age($row['dob']);
                                    }
                                    return '';
                                });

                        $table->addColumn('headOfYear', __('Head of Year'))
                                ->format(function($row) use ($container, $guid, $connection2) {
                                    $yearGroupGateway = $container->get(YearGroupGateway::class);
                                    $yearGroup = $yearGroupGateway->getByID($row['tawasulYearGroupID']);
                                    if (!empty($yearGroup) && !empty($yearGroup['tawasulPersonIDHOY'])) {
                                        $userGateway = $container->get(UserGateway::class);
                                        $hoy = $userGateway->getByID($yearGroup['tawasulPersonIDHOY']);
                                        if (!empty($hoy) && $hoy['status'] == 'Full') {
                                            if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_view_details.php')) {
                                                return Format::nameLinked($hoy['tawasulPersonID'], $hoy['title'], $hoy['preferredName'], $hoy['surname'], 'Staff');
                                            } else {
                                                return Format::name($hoy['title'], $hoy['preferredName'], $hoy['surname'], 'Staff');
                                            }
                                        }
                                    }

                                    return '';
                                });

                        $table->addColumn('website', __('Website'))
                                ->format(Format::using('link', ['website']));

                        $table->addColumn('email', __('Email'))
                                ->format(Format::using('link', ['email']));

                        $studentGateway = $container->get(StudentGateway::class);
                        $table->addColumn('schoolHistory', __('School History'))
                        ->format(function($row) use ($connection2, $studentGateway ) {
                            if ($row['dateStart'] != '') {
                                echo '<u>'.__('Start Date').'</u>: '.Format::date($row['dateStart']).'</br>';
                            }

                            $resultSelect = $studentGateway->selectStudentEnrolmentHistory($row['tawasulPersonID']);
                            
                            while ($rowSelect = $resultSelect->fetch()) {
                                echo '<u>'.$rowSelect['schoolYear'].'</u>: '.$rowSelect['formGroup'].' ('.$rowSelect['studyYear'].')'.'<br/>';
                            }

                            if ($row['dateEnd'] != '') {
                                echo '<u>'.__('End Date').'</u>: '.Format::date($row['dateEnd']).'</br>';
                            }
                        });

                        $table->addColumn('lockerNumber', __('Locker Number'));

                        $table->addColumn('studentID', __('Student ID'));

                        $table->addColumn('house', __('House'))
                                ->format(function($row) use ($container) {
                                    $houseGateway = $container->get(HouseGateway::class);
                                    $house = $houseGateway->getByID($row['tawasulHouseID']);
                                    if (!empty($house)) {
                                        return $house['name'];
                                    }
                                    return '';
                                });

                        $privacySetting = $settingGateway->getSettingByScope('User Admin', 'privacy');
                        if ($privacySetting == 'Y') {
                            $table->addColumn('privacy', __('Privacy'))
                                ->format(function($row) {
                                    $output = '';

                                    if ($row['privacy'] != '') {
                                        $output .= "<span style='color: #cc0000; background-color: #F6CECB'>";
                                        $output .= __('Privacy required:').' '.$row['privacy'];
                                        $output .= '</span>';
                                    } else {
                                        $output .= "<span style='color: #390; background-color: #D4F6DC;'>";
                                        $output .= __('Privacy not required or not set.');
                                        $output .= '</span>';
                                    }

                                    return $output;
                                });
                        }

                        $studentAgreementOptions = $settingGateway->getSettingByScope('School Admin', 'studentAgreementOptions');
                        if ($studentAgreementOptions != '') {
                            $table->addColumn('studentAgreements', __('Student Agreements'))
                                ->format(function($row) {
                                    return __('Agreements Signed:').' '.$row['studentAgreements'];
                                });
                        }

                        echo $table->render([$row]);

                        //Get and display a list of student's teachers
                        $studentGateway = $container->get(StudentGateway::class);
                        $staff = $studentGateway->selectAllRelatedUsersByStudent($session->get('tawasulSchoolYearID'), $row['tawasulYearGroupID'], $row['tawasulFormGroupID'], $tawasulPersonID)->fetchAll();
                        $canViewStaff = isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_view_details.php');
                        $criteria = $studentGateway->newQueryCriteria();

                        if ($staff) {
                            echo '<h4>';
                            echo __('Teachers Of {student}', ['student' => $row['preferredName']]);
                            echo '</h4>';
                            echo '<p>';
                            echo __('Includes Teachers, Tutors, Educational Assistants and Head of Year.');
                            echo '</p>';

                            $table = DataTable::createPaginated('staffView', $criteria);
                            $table->addMetaData('listOptions', [
                                'list' => __('List'),
                                'grid' => __('Grid'),
                            ]);

                            $view = $_GET['view'] ?? 'grid';
                            if ($view == 'grid') {
                                /** @var GridView */
                                $gridView = $container->get(GridView::class);
                                $table->setRenderer($gridView->setCriteria($criteria));

                                $table->addMetaData('gridClass', 'rounded-sm bg-gray-100 border');
                                $table->addMetaData('gridItemClass', 'w-1/2 sm:w-1/4 md:w-1/5 my-4 text-center text-xs');

                                $table->addColumn('image_240', __('Photo'))
                                    ->context('primary')
                                    ->format(function ($person) use ($canViewStaff) {
                                        $photo = Format::userPhoto($person['image_240'], 'sm');
                                        $url = './index.php?q=/modules/TawasulStaff/staff_view_details.php&tawasulPersonID='.$person['tawasulPersonID'];
                                        return $canViewStaff
                                            ? Format::link($url, $photo)
                                            : $photo;
                                    });

                                $table->addColumn('fullName', __('Name'))
                                    ->context('primary')
                                    ->sortable(['surname', 'preferredName'])
                                    ->width('20%')
                                    ->format(function ($person) use ($canViewStaff) {
                                        $text = Format::name('', $person['preferredName'], $person['surname'], 'Staff', false, true);
                                        $url = './index.php?q=/modules/TawasulStaff/staff_view_details.php&tawasulPersonID='.$person['tawasulPersonID'];
                                        return $canViewStaff
                                            ? Format::link($url, $text, ['class' => 'font-bold underline leading-normal'])
                                            : $text;
                                    });
                            } else {
                                $table->addColumn('fullName', __('Name'))
                                    ->notSortable()
                                    ->format(function ($person) {
                                        return Format::name('', $person['preferredName'], $person['surname'], 'Staff', false, true);
                                    });
                                $table->addColumn('email', __('Email'))
                                    ->notSortable()
                                    ->format(function ($person) {
                                        $person['email'] = filter_var(trim($person['email']), FILTER_SANITIZE_EMAIL);
                                        return htmlPrep('<'.$person['email'].'>');
                                    });
                            }

                            $table->addColumn('context', __('Context'))
                                ->notSortable()
                                ->format(function ($person) use ($view) {
                                    $class = $view == 'grid'? 'unselectable text-xxs italic text-gray-800' : 'unselectable';
                                    $context = $person['type'] == 'Class Teacher' ? $person['context'] : $person['type'];
                                    if (!empty($person['classID'])) {
                                        return Format::link('./index.php?q=/modules/TawasulDepartments/department_course_class.php&tawasulCourseClassID='.$person['classID'], __($context), ['class' => $class.' underline']);
                                    } else {
                                        return '<span class="'.$class.'">'.__($context).'</span>';
                                    }
                                });

                            echo $table->render(new DataSet($staff));
                        }


                        //Show timetable
                        echo "<a name='timetable'></a>";
                        //Display timetable if available, otherwise just list classes
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/tt_view.php') == true) {
                            echo '<h4>';
                            echo __('Timetable');
                            echo '</h4>';

                            // Timetable Links
                            $table = DataTable::createDetails('timetable');

                            if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php') == true) {
                                $role = $roleGateway->getRoleCategory($row['tawasulRoleIDPrimary']);
                                if ($role == 'Student' or $role == 'Staff') {
                                    $table->addHeaderAction('edit', __('Edit'))
                                    ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php')
                                    ->addParam('tawasulPersonID', $tawasulPersonID)
                                    ->addParam('tawasulSchoolYearID', $session->get('tawasulSchoolYearID'))
                                    ->addParam('type', $role)
                                    ->addParam('allUsers', $allStudents)
                                    ->displayLabel();
                                }
                            }

                            if ($tawasulPersonID == $session->get('tawasulPersonID')) {
                                $table->addHeaderAction('export', __('Export'))
                                    ->modalWindow()
                                    ->setURL('/modules/TawasulTimetable/tt_manage_subscription.php')
                                    ->addParam('tawasulPersonID', $tawasulPersonID)
                                    ->setIcon('download')
                                    ->displayLabel();
                            }

                            echo $table->render([['' => '']]);

                            $ttDate = !empty($_REQUEST['ttDate']) ? Format::dateConvert($_REQUEST['ttDate']) : null;
                            $tawasulTTID = $_REQUEST['tawasulTTID'] ?? '';
                            
                            // Create timetable context
                            $context = $container->get(TimetableContext::class)
                                ->set('tawasulSchoolYearID', $session->get('tawasulSchoolYearID'))
                                ->set('tawasulPersonID', $tawasulPersonID)
                                ->set('tawasulTTID', $tawasulTTID);

                            // Build and render timetable
                            echo $container->get(Timetable::class)
                                ->setDate($ttDate)
                                ->setContext($context)
                                ->addCoreLayers($container)
                                ->getOutput();
                        } else {
                            echo '<h4>';
                            echo __('Class List');
                            echo '</h4>';

                                $dataDetail = array('tawasulPersonID' => $tawasulPersonID);
                                $sqlDetail = "SELECT DISTINCT tawasulCourse.name AS courseFull, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class
                                    FROM tawasulCourseClassPerson
                                        JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                                        JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                                    WHERE tawasulCourseClassPerson.role='Student' AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND tawasulCourse.tawasulSchoolYearID=(SELECT tawasulSchoolYearID FROM tawasulSchoolYear WHERE status='Current') ORDER BY course, class";
                                $resultDetail = $connection2->prepare($sqlDetail);
                                $resultDetail->execute($dataDetail);
                            if ($resultDetail->rowCount() < 1) {
                                echo $page->getBlankSlate();
                            } else {
                                echo '<ul>';
                                while ($rowDetail = $resultDetail->fetch()) {
                                    echo '<li>';
                                        echo htmlPrep($rowDetail['courseFull'].' ('.$rowDetail['course'].'.'.$rowDetail['class'].')');
                                    echo '</li>';
                                }
                                echo '</ul>';
                            }
                        }
                    } elseif ($subpage == 'Personal') {
                        $schoolYearGateway = $container->get(SchoolYearGateway::class);
                        $yearGroupGateway = $container->get(YearGroupGateway::class);
                        $formGroupGateway = $container->get(FormGroupGateway::class);
                        $studentGateway = $container->get(StudentGateway::class);

                        $student = $studentGateway->selectActiveStudentByPerson($session->get('tawasulSchoolYearID'), $tawasulPersonID, false)->fetch();
                        $tutors = $formGroupGateway->selectTutorsByFormGroup($student['tawasulFormGroupID'] ?? '')->fetchAll();
                        $yearGroup = $yearGroupGateway->getByID($student['tawasulYearGroupID'] ?? '', ['name', 'tawasulPersonIDHOY']);
                        $headOfYear = $container->get(UserGateway::class)->getByID($yearGroup['tawasulPersonIDHOY'] ?? '', ['title', 'surname', 'preferredName', 'tawasulPersonID']);
                        $house = $container->get(HouseGateway::class)->getByID($row['tawasulHouseID'] ?? '', ['name']);

                        $table = DataTable::createDetails('overview');

                        if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/user_manage.php')) {
                            $table->addHeaderAction('edit', __('Edit User'))
                                ->setURL('/modules/TawasulUserAdmin/user_manage_edit.php')
                                ->addParam('tawasulPersonID', $tawasulPersonID)
                                ->displayLabel();
                        }

                        $col = $table->addColumn('Basic Information');

                        $col->addColumn('surname', __('Surname'));
                        $col->addColumn('firstName', __('First Name'))->addClass('col-span-2');
                        $col->addColumn('preferredName', __('Preferred Name'));
                        $col->addColumn('officialName', __('Official Name'));
                        $col->addColumn('nameInCharacters', __('Name In Characters'));
                        $col->addColumn('gender', __('Gender'))
                                ->format(Format::using('genderName', 'gender'));
                        $col->addColumn('dob', __('Date of Birth'))->format(Format::using('date', 'dob'));
                        $col->addColumn('age', __('Age'))->format(Format::using('age', 'dob'));

                        $col = $table->addColumn('Contact Information', __('Contact Information'));

                        for ($i = 1; $i <= 4; $i++) {
                            if (empty($row["phone$i"])) continue;
                            $col->addColumn("phone$i", __('Phone '.$i))->format(Format::using('phone', ["phone{$i}", "phone{$i}CountryCode", "phone{$i}Type"]));
                        }
                        $col->addColumn('email', __('Email'))->format(Format::using('link', 'email'));
                        $col->addColumn('emailAlternate', __('Alternate Email'))->format(Format::using('link', 'emailAlternate'));
                        $col->addColumn('website', __('Website'))->format(Format::using('link', 'website'));

                        $col = $table->addColumn('School Information', __('School Information'));

                        if (!empty($student)) {
                            $col->addColumn('yearGroup', __('Year Group'))->format(function ($values) use ($student) {
                                return $student['yearGroupName'];
                            });
                            $col->addColumn('tawasulFormGroupID', __('Form Group'))->format(function ($values) use ($student) {
                                return Format::link('./index.php?q=/modules/TawasulFormGroups/formGroups_details.php&tawasulFormGroupID='.$student['tawasulFormGroupID'], $student['formGroupName']);
                            });
                        }
                        $col->addColumn('email', __('Tutors'))->format(function ($values) use ($tutors) {
                            if (count($tutors) > 1) $tutors[0]['surname'] .= ' ('.__('Main Tutor').')';
                            return Format::nameList($tutors, 'Staff', false, true);
                        });
                        $col->addColumn('tawasulHouseID', __('House'))->format(function ($values) use ($house) {
                            return !empty($house['name']) ? $house['name'] : '';
                        });
                        $col->addColumn('studentID', __('Student ID'));
                        $col->addColumn('headOfYear', __('Head of Year'))->format(function ($values) use ($headOfYear) {
                            return !empty($headOfYear)
                                ? Format::nameLinked($headOfYear['tawasulPersonID'], '', $headOfYear['preferredName'], $headOfYear['surname'], 'Staff')
                                : '';
                        });

                        $col->addColumn('lastSchool', __('Last School'));
                        $col->addColumn('dateStart', __('Start Date'))->format(Format::using('date', 'dateStart'));
                        $col->addColumn('classOf', __('Class Of'))->format(function ($values) use ($schoolYearGateway) {
                            if (empty($values['tawasulSchoolYearIDClassOf'])) return Format::small(__('N/A'));
                            $schoolYear = $schoolYearGateway->getByID($values['tawasulSchoolYearIDClassOf'], ['name']);
                            return $schoolYear['name'] ?? '';
                        });
                        $col->addColumn('nextSchool', __('Next School'));
                        $col->addColumn('dateEnd', __('End Date'))->format(Format::using('date', 'dateEnd'));
                        $col->addColumn('departureReason', __('Departure Reason'));

                        $container->get(CustomFieldHandler::class)->addCustomFieldsToTable($table, 'Student Enrolment', [], $student['fields'] ?? '');

                        $col = $table->addColumn('Background Information', __('Background Information'));
                        $country = $session->get('country');

                        $col->addColumn('countryOfBirth', __('Country of Birth'))->translatable();
                        $col->addColumn('ethnicity', __('Ethnicity'));
                        $col->addColumn('religion', __('Religion'));

                        $col->addColumn('languageFirst', __('First Language'))->translatable();
                        $col->addColumn('languageSecond', __('Second Language'))->translatable();
                        $col->addColumn('languageThird', __('Third Language'))->translatable();

                        $col = $table->addColumn('System Access', __('System Access'));

                        $col->addColumn('username', __('Username'));
                        $col->addColumn('canLogin', __('Can Login?'))->format(Format::using('yesNo', 'canLogin'));
                        $col->addColumn('lastIPAddress', __('Last IP Address'));

                        $col = $table->addColumn('Miscellaneous', __('Miscellaneous'));

                        $col->addColumn('transport', __('Transport'))->format(function ($values) {
                            $output = $values['transport'];
                            if (!empty($values['transportNotes'])) {
                                $output .= '<br/>'.$values['transportNotes'];
                            }
                            return $output;
                        });
                        $col->addColumn('vehicleRegistration', __('Vehicle Registration'));
                        $col->addColumn('lockerNumber', __('Locker Number'));

                        $privacySetting = $settingGateway->getSettingByScope('User Admin', 'privacy');
                        if ($privacySetting == 'Y') {
                            $col->addColumn('privacy', __('Privacy'))->format(function ($values) {
                                if (!empty($values['privacy'])) {
                                    return Format::tag(__('Privacy required:').' '.$values['privacy'], 'error');
                                } else {
                                    return Format::tag(__('Privacy not required or not set.'), 'success');
                                }
                            });
                        }
                        $studentAgreementOptions = $settingGateway->getSettingByScope('School Admin', 'studentAgreementOptions');
                        if (!empty($studentAgreementOptions)) {
                            $col->addColumn('studentAgreements', __('Student Agreements:'))->format(function ($values) {
                                return __('Agreements Signed:') .' '.$values['studentAgreements'];
                            });
                        }

                        // CUSTOM FIELDS
                        $container->get(CustomFieldHandler::class)->addCustomFieldsToTable($table, 'User', ['student' => 1], $row['fields']);
                        echo $table->render([$row]);

                        // PERSONAL DOCUMENTS
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/report_student_personalDocumentSummary.php')) {
                            $params = ['student' => true, 'notEmpty' => true];
                            $documents = $container->get(PersonalDocumentGateway::class)->selectPersonalDocuments('tawasulPerson', $tawasulPersonID, $params)->fetchAll();

                            echo $page->fetchFromTemplate('ui/personalDocuments.twig.html', ['documents' => $documents]);
                        }


                    } elseif ($subpage == 'Family') {

                            $dataFamily = array('tawasulPersonID' => $tawasulPersonID);
                            $sqlFamily = 'SELECT * FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamily.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID';
                            $resultFamily = $connection2->prepare($sqlFamily);
                            $resultFamily->execute($dataFamily);

                        if ($resultFamily->rowCount() < 1) {
                            echo $page->getBlankSlate();
                        } else {
                            while ($rowFamily = $resultFamily->fetch()) {
                                $count = 1;

                                if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/family_manage.php') == true) {
                                    $form = Form::createBlank('buttons');
                                    $form->addHeaderAction('edit', __('Edit Family'))
                                        ->setURL('/modules/TawasulUserAdmin/family_manage_edit.php')
                                        ->addParam('tawasulFamilyID', $rowFamily['tawasulFamilyID'])
                                        ->displayLabel();
                                    echo $form->getOutput();
                                } else {
                                    echo '<br/><br/>';
                                }

                                //Print family information
                                echo "<table class='smallIntBorder' cellspacing='0' style='width: 100%'>";
                                echo '<tr>';
                                echo "<td style='width: 33%; vertical-align: top'>";
                                echo "<span style='font-size: 115%; font-weight: bold'>".__('Family Name').'</span><br/>';
                                echo $rowFamily['name'];
                                echo '</td>';
                                echo "<td style='width: 33%; vertical-align: top'>";
                                echo "<span style='font-size: 115%; font-weight: bold'>".__('Family Status').'</span><br/>';
                                echo __($rowFamily['status']);
                                echo '</td>';
                                echo "<td style='width: 34%; vertical-align: top' colspan=2>";
                                echo "<span style='font-size: 115%; font-weight: bold'>".__('Home Languages').'</span><br/>';
                                if ($rowFamily['languageHomePrimary'] != '') {
                                    echo __($rowFamily['languageHomePrimary']).'<br/>';
                                }
                                if ($rowFamily['languageHomeSecondary'] != '') {
                                    echo __($rowFamily['languageHomeSecondary']).'<br/>';
                                }
                                echo '</td>';
                                echo '</tr>';
                                echo '<tr>';
                                echo "<td style='width: 33%; padding-top: 15px; vertical-align: top'>";
                                echo "<span style='font-size: 115%; font-weight: bold'>".__('Address Name').'</span><br/>';
                                echo $rowFamily['nameAddress'];
                                echo '</td>';
                                echo "<td style='width: 33%; padding-top: 15px; vertical-align: top'>";
                                echo '</td>';
                                echo "<td style='width: 33%; padding-top: 15px; vertical-align: top'>";
                                echo '</td>';
                                echo '</tr>';

                                echo '<tr>';
                                echo "<td style='width: 33%; padding-top: 15px; vertical-align: top'>";
                                echo "<span style='font-size: 115%; font-weight: bold'>".__('Home Address').'</span><br/>';
                                echo $rowFamily['homeAddress'];
                                echo '</td>';
                                echo "<td style='width: 33%; padding-top: 15px; vertical-align: top'>";
                                echo "<span style='font-size: 115%; font-weight: bold'>".__('Home Address (District)').'</span><br/>';
                                echo $rowFamily['homeAddressDistrict'];
                                echo '</td>';
                                echo "<td style='width: 33%; padding-top: 15px; vertical-align: top'>";
                                echo "<span style='font-size: 115%; font-weight: bold'>".__('Home Address (Country)').'</span><br/>';
                                echo __($rowFamily['homeAddressCountry']);
                                echo '</td>';
                                echo '</tr>';
                                echo '</table>';

                                $table = DataTable::createDetails('family');
                                $container->get(CustomFieldHandler::class)->addCustomFieldsToTable($table, 'Family', [], $rowFamily['fields'] ?? '');
                                echo $table->render([['' => '']]);

                                //Get adults

                                    $dataMember = array('tawasulFamilyID' => $rowFamily['tawasulFamilyID']);
                                    $sqlMember = 'SELECT * FROM tawasulFamilyAdult JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulFamilyID=:tawasulFamilyID ORDER BY contactPriority, surname, preferredName';
                                    $resultMember = $connection2->prepare($sqlMember);
                                    $resultMember->execute($dataMember);

                                while ($rowMember = $resultMember->fetch()) {
                                    $class='';
                                    if ($rowMember['status'] != 'Full') {
                                        $class = "class='error'";
                                    }
                                    echo '<h4>';
                                    echo __('Adult').' '.$count;
                                    echo '</h4>';
                                    echo "<table class='smallIntBorder' cellspacing='0' style='width: 100%'>";
                                    echo '<tr>';
                                    echo "<td $class style='width: 33%; vertical-align: top' rowspan=2>";
                                    echo Format::userPhoto($rowMember['image_240'], 75);
                                    echo '</td>';
                                    echo "<td $class style='width: 33%; vertical-align: top'>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Name').'</span><br/>';
                                    echo Format::name($rowMember['title'], $rowMember['preferredName'], $rowMember['surname'], 'Parent');
                                    if ($rowMember['status'] != 'Full') {
                                        echo "<span style='font-weight: normal; font-style: italic'> (".__($rowMember['status']).')</span>';
                                    }
                                    echo "<div style='font-size: 85%; font-style: italic'>";

                                        $dataRelationship = array('tawasulPersonID1' => $rowMember['tawasulPersonID'], 'tawasulPersonID2' => $tawasulPersonID, 'tawasulFamilyID' => $rowFamily['tawasulFamilyID']);
                                        $sqlRelationship = 'SELECT * FROM tawasulFamilyRelationship WHERE tawasulPersonID1=:tawasulPersonID1 AND tawasulPersonID2=:tawasulPersonID2 AND tawasulFamilyID=:tawasulFamilyID';
                                        $resultRelationship = $connection2->prepare($sqlRelationship);
                                        $resultRelationship->execute($dataRelationship);
                                    if ($resultRelationship->rowCount() == 1) {
                                        $rowRelationship = $resultRelationship->fetch();
                                        echo __($rowRelationship['relationship']);
                                    } else {
                                        echo '<i>'.__('Relationship Unknown').'</i>';
                                    }
                                    echo '</div>';
                                    echo '</td>';
                                    echo "<td $class style='width: 34%; vertical-align: top' colspan=2>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Contact Priority').'</span><br/>';
                                    echo $rowMember['contactPriority'];
                                    echo '</td>';
                                    echo '</tr>';
                                    echo '<tr>';
                                    echo "<td $class style='width: 33%; padding-top: 15px; vertical-align: top'>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('First Language').'</span><br/>';
                                    echo __($rowMember['languageFirst']);
                                    echo '</td>';
                                    echo "<td $class style='width: 33%; padding-top: 15px; vertical-align: top'>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Second Language').'</span><br/>';
                                    echo __($rowMember['languageSecond']);
                                    echo '</td>';
                                    echo '</tr>';
                                    echo '<tr>';
                                    echo "<td $class style='width: 33%; padding-top: 15px; width: 33%; vertical-align: top'>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Contact By Phone').'</span><br/>';
                                    if ($rowMember['contactCall'] == 'N') {
                                        echo __('Do not contact by phone.');
                                    } elseif ($rowMember['contactCall'] == 'Y' and ($rowMember['phone1'] != '' or $rowMember['phone2'] != '' or $rowMember['phone3'] != '' or $rowMember['phone4'] != '')) {
                                        for ($i = 1; $i < 5; ++$i) {
                                            if ($rowMember['phone'.$i] != '') {
                                                if ($rowMember['phone'.$i.'Type'] != '') {
                                                    echo $rowMember['phone'.$i.'Type'].':</i> ';
                                                }
                                                if ($rowMember['phone'.$i.'CountryCode'] != '') {
                                                    echo '+'.$rowMember['phone'.$i.'CountryCode'].' ';
                                                }
                                                echo Format::phone($rowMember['phone'.$i]).'<br/>';
                                            }
                                        }
                                    } else {
                                        echo Format::small(__('N/A'));
                                    }
                                    echo '</td>';
                                    echo "<td $class style='width: 33%; padding-top: 15px; width: 33%; vertical-align: top'>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Contact By SMS').'</span><br/>';
                                    if ($rowMember['contactSMS'] == 'N') {
                                        echo __('Do not contact by SMS.');
                                    } elseif ($rowMember['contactSMS'] == 'Y' and ($rowMember['phone1'] != '' or $rowMember['phone2'] != '' or $rowMember['phone3'] != '' or $rowMember['phone4'] != '')) {
                                        for ($i = 1; $i < 5; ++$i) {
                                            if ($rowMember['phone'.$i] != '' and $rowMember['phone'.$i.'Type'] == 'Mobile') {
                                                if ($rowMember['phone'.$i.'Type'] != '') {
                                                    echo $rowMember['phone'.$i.'Type'].':</i> ';
                                                }
                                                if ($rowMember['phone'.$i.'CountryCode'] != '') {
                                                    echo '+'.$rowMember['phone'.$i.'CountryCode'].' ';
                                                }
                                                echo Format::phone($rowMember['phone'.$i]).'<br/>';
                                            }
                                        }
                                    } else {
                                        echo Format::small(__('N/A'));
                                    }
                                    echo '</td>';
                                    echo "<td $class style='width: 33%; padding-top: 15px; width: 34%; vertical-align: top' colspan=2>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Contact By Email').'</span><br/>';
                                    if ($rowMember['contactEmail'] == 'N') {
                                        echo __('Do not contact by email.');
                                    } elseif ($rowMember['contactEmail'] == 'Y' and ($rowMember['email'] != '' or $rowMember['emailAlternate'] != '')) {
                                        if ($rowMember['email'] != '') {
                                            $rowMember['email'] = filter_var(trim($rowMember['email']), FILTER_SANITIZE_EMAIL);
                                            echo __('Email').": <a href='mailto:".$rowMember['email']."'>".$rowMember['email'].'</a><br/>';
                                        }
                                        if ($rowMember['emailAlternate'] != '') {
                                            $rowMember['emailAlternate'] = filter_var(trim($rowMember['emailAlternate']), FILTER_SANITIZE_EMAIL);
                                            echo __('Email')." 2: <a href='mailto:".$rowMember['emailAlternate']."'>".$rowMember['emailAlternate'].'</a><br/>';
                                        }
                                        echo '<br/>';
                                    } else {
                                        echo Format::small(__('N/A'));
                                    }
                                    echo '</td>';
                                    echo '</tr>';
                                    echo '<tr>';
                                    echo "<td $class style='width: 33%; padding-top: 15px; vertical-align: top'>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Profession').'</span><br/>';
                                    echo $rowMember['profession'];
                                    echo '</td>';
                                    echo "<td $class style='width: 33%; padding-top: 15px; vertical-align: top'>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Employer').'</span><br/>';
                                    echo $rowMember['employer'];
                                    echo '</td>';
                                    echo "<td $class style='width: 33%; padding-top: 15px; vertical-align: top'>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Job Title').'</span><br/>';
                                    echo $rowMember['jobTitle'];
                                    echo '</td>';
                                    echo '</tr>';

                                    echo '<tr>';
                                    echo "<td $class style='width: 33%; padding-top: 15px; vertical-align: top'>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Vehicle Registration').'</span><br/>';
                                    echo $rowMember['vehicleRegistration'];
                                    echo '</td>';
                                    echo "<td $class style='width: 33%; padding-top: 15px; vertical-align: top'>";

                                    echo '</td>';
                                    echo "<td $class style='width: 33%; padding-top: 15px; vertical-align: top'>";

                                    echo '</td>';
                                    echo '</tr>';

                                    // Check to ensure only people with full profile access can view these comments
                                    if ($rowMember['comment'] != '' && ($highestAction == 'View Student Profile_fullEditAllNotes' || $highestAction == 'View Student Profile_full' || $highestAction == 'View Student Profile_fullNoNotes')) {
                                        echo '<tr>';
                                        echo "<td $class style='width: 33%; vertical-align: top' colspan=3>";
                                        echo "<span style='font-size: 115%; font-weight: bold'>".__('Comment').'</span><br/>';
                                        echo Format::alert($rowMember['comment'], 'message');
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                    echo '</table>';
                                    ++$count;
                                }

                                //Get siblings

                                    $dataMember = array('tawasulFamilyID' => $rowFamily['tawasulFamilyID'], 'tawasulPersonID' => $tawasulPersonID, 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                                    $sqlMember = 'SELECT tawasulPerson.tawasulPersonID, image_240, preferredName, surname, status, tawasulStudentEnrolmentID FROM tawasulFamilyChild JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID) WHERE tawasulFamilyID=:tawasulFamilyID AND NOT tawasulPerson.tawasulPersonID=:tawasulPersonID ORDER BY surname, preferredName';
                                    $resultMember = $connection2->prepare($sqlMember);
                                    $resultMember->execute($dataMember);

                                if ($resultMember->rowCount() > 0) {
                                    echo '<h4>';
                                    echo __('Siblings');
                                    echo '</h4>';

                                    echo "<table class='smallIntBorder' cellspacing='0' style='width:100%'>";
                                    $count = 0;
                                    $columns = 3;
                                    $highlightClass = '';

                                    while ($rowMember = $resultMember->fetch()) {
                                        if ($count % $columns == 0) {
                                            echo '<tr>';
                                        }
                                        $highlightClass = $rowMember['status'] != 'Full'? 'error' : '';
                                        echo "<td style='width:30%; text-align: left; vertical-align: top' class='".$highlightClass."'>";
                                        //User photo
                                        echo Format::userPhoto($rowMember['image_240'], 75);
                                        echo "<div style='padding-top: 5px'><b>";

                                        if ($rowMember['tawasulStudentEnrolmentID'] == null) {
                                            $allStudents = 'on';
                                        }

                                        echo "<a href='index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID=".$rowMember['tawasulPersonID']."&allStudents=".$allStudents."'>".Format::name('', $rowMember['preferredName'], $rowMember['surname'], 'Student').'</a><br/>';

                                        echo "<span style='font-weight: normal; font-style: italic'>".__('Status').': '.__($rowMember['status']).'</span>';
                                        echo '</div>';
                                        echo '</td>';

                                        if ($count % $columns == ($columns - 1)) {
                                            echo '</tr>';
                                        }
                                        ++$count;
                                    }

                                    for ($i = 0; $i < $columns - ($count % $columns); ++$i) {
                                        echo '<td class="'.$highlightClass.'"></td>';
                                    }

                                    if ($count % $columns != 0) {
                                        echo '</tr>';
                                    }

                                    echo '</table>';
                                }
                            }
                        }
                    } elseif ($subpage == 'Emergency Contacts') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/user_manage.php') == true) {
                            $form = Form::createBlank('buttons');
                            $form->addHeaderAction('edit', __('Edit User'))
                                ->setURL('/modules/TawasulUserAdmin/user_manage_edit.php')
                                ->addParam('tawasulPersonID', $tawasulPersonID)
                                ->displayLabel();
                            echo $form->getOutput();
                        }

                        echo '<p>';
                        echo __('In an emergency, please try and contact the adult family members listed below first. If these cannot be reached, then try the emergency contacts below.');
                        echo '</p>';

                        echo '<h4>';
                        echo __('Adult Family Members');
                        echo '</h4>';


                            $dataFamily = array('tawasulPersonID' => $tawasulPersonID);
                            $sqlFamily = 'SELECT * FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamily.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID';
                            $resultFamily = $connection2->prepare($sqlFamily);
                            $resultFamily->execute($dataFamily);

                        if ($resultFamily->rowCount() == 0) {
                            echo $page->getBlankSlate();
                        } else {
                            while ($rowFamily = $resultFamily->fetch()) {
                                $count = 1;
                                //Get adults

                                $dataMember = array('tawasulFamilyID' => $rowFamily['tawasulFamilyID']);
                                $sqlMember = 'SELECT * FROM tawasulFamilyAdult JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulFamilyID=:tawasulFamilyID ORDER BY contactPriority, surname, preferredName';
                                $resultMember = $connection2->prepare($sqlMember);
                                $resultMember->execute($dataMember);

                                while ($rowMember = $resultMember->fetch()) {
                                    echo "<table class='smallIntBorder mb-2' cellspacing='0' style='width: 100%'>";
                                    echo '<tr>';
                                    echo "<td style='width: 33%; vertical-align: top'>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Name').'</span><br/>';
                                    echo Format::name($rowMember['title'], $rowMember['preferredName'], $rowMember['surname'], 'Parent');
                                    echo '</td>';
                                    echo "<td style='width: 33%; vertical-align: top'>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Relationship').'</span><br/>';

                                    $dataRelationship = array('tawasulPersonID1' => $rowMember['tawasulPersonID'], 'tawasulPersonID2' => $tawasulPersonID, 'tawasulFamilyID' => $rowFamily['tawasulFamilyID']);
                                    $sqlRelationship = 'SELECT * FROM tawasulFamilyRelationship WHERE tawasulPersonID1=:tawasulPersonID1 AND tawasulPersonID2=:tawasulPersonID2 AND tawasulFamilyID=:tawasulFamilyID';
                                    $resultRelationship = $connection2->prepare($sqlRelationship);
                                    $resultRelationship->execute($dataRelationship);
                                    if ($resultRelationship->rowCount() == 1) {
                                        $rowRelationship = $resultRelationship->fetch();
                                        echo __($rowRelationship['relationship']);
                                    } else {
                                        echo '<i>'.__('Unknown').'</i>';
                                    }

                                    echo '</td>';
                                    echo "<td style='width: 34%; vertical-align: top'>";
                                    echo "<span style='font-size: 115%; font-weight: bold'>".__('Contact By Phone').'</span><br/>';
                                    for ($i = 1; $i < 5; ++$i) {
                                        if ($rowMember['phone'.$i] != '') {
                                            if ($rowMember['phone'.$i.'Type'] != '') {
                                                echo $rowMember['phone'.$i.'Type'].':</i> ';
                                            }
                                            if ($rowMember['phone'.$i.'CountryCode'] != '') {
                                                echo '+'.$rowMember['phone'.$i.'CountryCode'].' ';
                                            }
                                            echo __($rowMember['phone'.$i]).'<br/>';
                                        }
                                    }
                                    echo '</td>';
                                    echo '</tr>';
                                    echo '</table>';
                                    ++$count;
                                }
                            }
                        }
                        
                        echo '<h4>';
                        echo __('Emergency Contacts');
                        echo '</h4>';
                        echo "<table class='smallIntBorder' cellspacing='0' style='width: 100%'>";
                        echo '<tr>';
                        echo "<td style='width: 33%; vertical-align: top'>";
                        echo "<span style='font-size: 115%; font-weight: bold'>".__('Contact 1').'</span><br/>';
                        echo $row['emergency1Name'];
                        if ($row['emergency1Relationship'] != '') {
                            echo ' ('.__($row['emergency1Relationship']).')';
                        }
                        echo '</td>';
                        echo "<td style='width: 33%; vertical-align: top'>";
                        echo "<span style='font-size: 115%; font-weight: bold'>".__('Number 1').'</span><br/>';
                        echo $row['emergency1Number1'];
                        echo '</td>';
                        echo "<td style=width: 34%; 'vertical-align: top'>";
                        echo "<span style='font-size: 115%; font-weight: bold'>".__('Number 2').'</span><br/>';
                        if ($row['emergency1Number2'] != '') {
                            echo $row['emergency1Number2'];
                        }
                        echo '</td>';
                        echo '</tr>';
                        echo '<tr>';
                        echo "<td style='width: 33%; padding-top: 15px; vertical-align: top'>";
                        echo "<span style='font-size: 115%; font-weight: bold'>".__('Contact 2').'</span><br/>';
                        echo $row['emergency2Name'];
                        if ($row['emergency2Relationship'] != '') {
                            echo ' ('.__($row['emergency2Relationship']).')';
                        }
                        echo '</td>';
                        echo "<td style='width: 33%; padding-top: 15px; vertical-align: top'>";
                        echo "<span style='font-size: 115%; font-weight: bold'>".__('Number 1').'</span><br/>';
                        echo $row['emergency2Number1'];
                        echo '</td>';
                        echo "<td style='width: 33%; padding-top: 15px; vertical-align: top'>";
                        echo "<span style='font-size: 115%; font-weight: bold'>".__('Number 2').'</span><br/>';
                        if ($row['emergency2Number2'] != '') {
                            echo $row['emergency2Number2'];
                        }
                        echo '</td>';
                        echo '</tr>';
                        echo '</table>';
                        echo '<br/><br/>';

                        // Follow-up Contacts
                        $contacts = [];
                        $emergencyFollowUpGroup = $settingGateway->getSettingByScope('Students', 'emergencyFollowUpGroup');

                        if (!empty($emergencyFollowUpGroup)) {
                            $contactsList = explode(',', $emergencyFollowUpGroup) ?? [];
                            $contacts = $container->get(UserGateway::class)->selectNotificationDetailsByPerson($contactsList)->fetchAll();
                        }

                        $staff = $container->get(StudentGateway::class)->selectAllRelatedUsersByStudent($session->get('tawasulSchoolYearID'), $row['tawasulYearGroupID'], $row['tawasulFormGroupID'], $tawasulPersonID, false)->fetchAll();

                        $familyAdults = $container->get(FamilyGateway::class)->selectFamilyAdultsByStudent($tawasulPersonID)->fetchAll();
                        $familyAdults = array_filter($familyAdults, function ($parent) {
                            return $parent['contactEmail'] == 'Y';
                        });

                        $table = DataTable::create('followupMedicalContacts');
                        $table->setTitle(__('Follow-up Contacts'));
                        $table->setDescription(__('These contacts can be used when following up on an emergency, or for less serious issues, when parents and staff need to be notified by email.'));

                        $table->addColumn('fullName', __('Name'))
                                ->notSortable()
                                ->format(function ($person) {
                                    return Format::name('', $person['preferredName'], $person['surname'], 'Staff', false, true);
                                });
                        $table->addColumn('email', __('Email'))
                                ->notSortable()
                                ->format(function ($person) {
                                    $person['email'] = filter_var(trim($person['email']), FILTER_SANITIZE_EMAIL);
                                    return htmlPrep('<'.$person['email'].'>');
                                });
                        $table->addColumn('context', __('Context'))
                            ->notSortable()
                            ->format(function ($person)  {
                                if ($person['type'] == 'Family') {
                                    $person['type'] = $person['type'].', '.$person['relationship'];
                                } elseif ($person['type'] == 'Teaching' || $person['type'] == 'Support') {
                                    $person['type'] = $person['jobTitle'];
                                }

                                if (!empty($person['classID'])) {
                                    return Format::link('./index.php?q=/modules/TawasulDepartments/department_course_class.php&tawasulCourseClassID='.$person['classID'], __($person['type']), ['class' => 'unselectable underline']);
                                } else {
                                    return '<span class="unselectable">'.__($person['type']).'</span>';
                                }
                            });

                        echo $table->render(new DataSet(array_merge($familyAdults, $contacts, $staff)));

                    } elseif ($subpage == 'Medical') {
                        if (!$skipBrief || isActionAccessible($guid, $connection2, '/modules/TawasulStudents/student_view_details.php', 'View Student Profile_my')) {
                            echo Format::alert(__('Your request failed because you do not have access to this action.'));
                        } else {
                            /** @var MedicalGateway */
                            $medicalGateway = $container->get(MedicalGateway::class);

                            $medical = $medicalGateway->getMedicalFormByPerson($tawasulPersonID);
                            $conditions = $medicalGateway->selectMedicalConditionsByID($medical['tawasulPersonMedicalID'] ?? null)->fetchAll();

                            //Medical alert!
                            $alert = $medicalGateway->getHighestMedicalRisk($tawasulPersonID);
                            if (!empty($alert)) {
                                echo "<div class='error' style='background-color: #".$alert['colorBG'].'; border: 1px solid #'.$alert['color'].'; color: #'.$alert['color']."'>";
                                echo '<b>'.__('This student has one or more {level} risk medical conditions.', ['level' => __($alert['name'])]).'</b>';
                                echo '</div>';
                            }

                            // MEDICAL DETAILS
                            $table = DataTable::createDetails('medical');

                            if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/medicalForm_manage.php')) {
                                if (empty($medical)) {
                                    $table->addHeaderAction('add', __('Add Medical Form'))
                                        ->setURL('/modules/TawasulStudents/medicalForm_manage_add.php')
                                        ->addParam('tawasulPersonID', $tawasulPersonID)
                                        ->addParam('search', $search)
                                        ->displayLabel();
                                } else {
                                    $table->addHeaderAction('edit', __('Edit Medical Form'))
                                        ->setURL('/modules/TawasulStudents/medicalForm_manage_edit.php')
                                        ->addParam('tawasulPersonID', $tawasulPersonID)
                                        ->addParam('tawasulPersonMedicalID', $medical['tawasulPersonMedicalID'])
                                        ->addParam('search', $search)
                                        ->displayLabel();
                                }
                            }

                            $col = $table->addColumn('General Information');

                            $col->addColumn('longTermMedication', __('Long Term Medication'))
                                ->format(Format::using('yesno', 'longTermMedication'));

                            $col->addColumn('longTermMedicationDetails', __('Details'))
                                ->addClass('col-span-2')
                                ->format(function ($medical) {
                                    return !empty($medical['longTermMedication'])
                                        ? $medical['longTermMedicationDetails']
                                        : Format::small(__('Unknown'));
                                });

                            $container->get(CustomFieldHandler::class)->addCustomFieldsToTable($table, 'Medical Form', [], $medical['fields'] ?? '', $table);

                            $col->addColumn('medicalConditions', __('Medical Conditions?'))
                                ->addClass('col-span-3')
                                ->format(function ($medical) use ($conditions) {
                                    return count($conditions) > 0
                                        ? __('Yes').'. '.__('Details below.')
                                        : __('No');
                                });

                            if (!empty($medical['comment'])) {
                                $col->addColumn('comment', __('Comment'))->addClass('col-span-3');
                            }


                            if (!empty($medical['fields']) && is_string($medical['fields'])) {
                                $fields = json_decode($medical['fields'], true);
                                $medical = is_array($fields) ? array_merge($medical, $fields) : $medical;
                            }

                            echo $table->render([$medical]);

                            // MEDICAL CONDITIONS
                            foreach ($conditions as $condition) {
                                $table = DataTable::createDetails('medicalConditions');
                                $table->setTitle(__($condition['name'])." <span style='color: ".$condition['alertColor']."'>(".__($condition['risk']).' '.__('Risk').')</span>');
                                $table->setDescription($condition['description']);
                                $table->addMetaData('gridClass', 'grid-cols-1 md:grid-cols-2');

                                $table->addColumn('triggers', __('Triggers'));
                                $table->addColumn('reaction', __('Reaction'));
                                $table->addColumn('response', __('Response'));
                                $table->addColumn('medication', __('Medication'));
                                $table->addColumn('lastEpisode', __('Last Episode Date'))
                                    ->format(Format::using('date', 'lastEpisode'));
                                $table->addColumn('lastEpisodeTreatment', __('Last Episode Treatment'));
                                $table->addColumn('comment', __('Comments'))->addClass('col-span-2');

                                if (!empty($condition['attachment'])) {
                                    $table->addColumn('attachment', __('Attachment'))
                                        ->addClass('col-span-2')
                                        ->format(function ($condition) {
                                            return Format::link('./'.$condition['attachment'], __('View Attachment'), ['target' => '_blank']);
                                        });
                                }

                                echo $table->render([$condition]);
                            }
                        }
                    } elseif ($subpage == 'First Aid') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/firstAidRecord.php') == false) {
                            echo Format::alert(__('Your request failed because you do not have access to this action.'));
                        } else {

                            $firstAidGateway = $container->get(FirstAidGateway::class);
                            $criteria = $firstAidGateway->newQueryCriteria()
                                ->sortBy(['date', 'timeIn'], 'DESC')
                                ->fromPOST('firstAid');

                            $firstAidRecords = $firstAidGateway->queryFirstAidByStudent($criteria, $session->get('tawasulSchoolYearID'), $tawasulPersonID);

                            // DATA TABLE
                            $table = DataTable::createPaginated('firstAidRecords', $criteria);

                            $table->addHeaderAction('add', __('Add'))
                                ->setURL('/modules/TawasulStudents/firstAidRecord_add.php')
                                ->addParam('tawasulPersonID', $tawasulPersonID)
                                ->displayLabel();

                            $table->addExpandableColumn('details')->format(function($person) use ($firstAidGateway) {
                                $output = '';
                                if ($person['description'] != '') $output .= '<b>'.__('Description').'</b><br/>'.nl2br($person['description']).'<br/><br/>';
                                if ($person['actionTaken'] != '') $output .= '<b>'.__('Action Taken').'</b><br/>'.nl2br($person['actionTaken']).'<br/><br/>';
                                if ($person['followUp'] != '') $output .= '<b>'.__("Follow Up by {name} at {date}", ['name' => Format::name('', $person['preferredNameFirstAider'], $person['surnameFirstAider']), 'date' => Format::dateTimeReadable($person['timestamp'])]).'</b><br/>'.nl2br($person['followUp']).'<br/><br/>';
                                $resultLog = $firstAidGateway->queryFollowUpByFirstAidID($person['tawasulFirstAidID']);
                                foreach ($resultLog AS $rowLog) {
                                    $output .= '<b>'.__("Follow Up by {name} at {date}", ['name' => Format::name('', $rowLog['preferredName'], $rowLog['surname']), 'date' => Format::dateTimeReadable($rowLog['timestamp'])]).'</b><br/>'.nl2br($rowLog['followUp']).'<br/><br/>';
                                }

                                return $output;
                            });

                            $table->addColumn('firstAider', __('First Aider'))
                                ->sortable(['surnameFirstAider', 'preferredNameFirstAider'])
                                ->format(Format::using('name', ['', 'preferredNameFirstAider', 'surnameFirstAider', 'Staff', false, true]));

                            $table->addColumn('date', __('Date'))
                                ->format(Format::using('date', ['date']));

                            $table->addColumn('time', __('Time'))
                                ->sortable(['timeIn', 'timeOut'])
                                ->format(Format::using('timeRange', ['timeIn', 'timeOut']));

                            $highestActionFirstAid = getHighestGroupedAction($guid, '/modules/TawasulStudents/firstAidRecord.php', $connection2);
                            $table->addActionColumn()
                                ->addParam('tawasulPersonID', $tawasulPersonID)
                                ->addParam('tawasulFormGroupID', $row['tawasulFormGroupID'])
                                ->addParam('tawasulYearGroupID', $row['tawasulYearGroupID'])
                                ->addParam('tawasulFirstAidID')
                                ->format(function ($person, $actions) use ($highestActionFirstAid) {
                                    if ($highestActionFirstAid == 'First Aid Record_editAll') {
                                        $actions->addAction('edit', __('Edit'))
                                            ->setURL('/modules/TawasulStudents/firstAidRecord_edit.php');
                                    } elseif ($highestActionFirstAid == 'First Aid Record_viewOnlyAddNotes') {
                                        $actions->addAction('view', __('View'))
                                            ->setURL('/modules/TawasulStudents/firstAidRecord_edit.php');
                                    }
                                });

                            echo $table->render($firstAidRecords);
                        }

                    } elseif ($subpage == 'Notes') {
                        if ($enableStudentNotes != 'Y') {
                            $page->addError(__('You do not have access to this action.'));
                        } else {
                            if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/student_view_details_notes_add.php') == false) {
                                $page->addError(__('Your request failed because you do not have access to this action.'));
                            } else {
                                echo '<p>';
                                echo __('Student Notes provide a way to store information on students which does not fit elsewhere in the system, or which you want to be able to see quickly in one place.').' <b>'.__('Please remember that notes are visible to other users who have access to full student profiles (this should not generally include parents).').'</b>';
                                echo '</p>';

                                $categories = false;
                                $category = null;
                                if (isset($_GET['category'])) {
                                    $category = $_GET['category'] ?? '';
                                }


                                    $dataCategories = array();
                                    $sqlCategories = "SELECT * FROM tawasulStudentNoteCategory WHERE active='Y' ORDER BY name";
                                    $resultCategories = $connection2->prepare($sqlCategories);
                                    $resultCategories->execute($dataCategories);
                                if ($resultCategories->rowCount() > 0) {
                                    $categories = true;

                                    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
                                    $form->setTitle(__('Filter'));
                                    $form->setClass('noIntBorder w-full');

                                    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/student_view_details.php');
                                    $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
                                    $form->addHiddenValue('allStudents', $allStudents);
                                    $form->addHiddenValue('search', $search);
                                    $form->addHiddenValue('subpage', 'Notes');

                                    $sql = "SELECT tawasulStudentNoteCategoryID as value, name FROM tawasulStudentNoteCategory WHERE active='Y' ORDER BY name";
                                    $rowFilter = $form->addRow();
                                        $rowFilter->addLabel('category', __('Category'));
                                        $rowFilter->addSelect('category')->fromQuery($pdo, $sql)->selected($category)->placeholder();

                                    $rowFilter = $form->addRow();
                                        $rowFilter->addSearchSubmit($session, __('Clear Filters'), array('tawasulPersonID', 'allStudents', 'search', 'subpage'));

                                    echo $form->getOutput();
                                }

                                try {
                                    if ($category == null) {
                                        $data = array('tawasulPersonID' => $tawasulPersonID);
                                        $sql = 'SELECT tawasulStudentNote.*, tawasulStudentNoteCategory.name AS category, surname, preferredName FROM tawasulStudentNote LEFT JOIN tawasulStudentNoteCategory ON (tawasulStudentNote.tawasulStudentNoteCategoryID=tawasulStudentNoteCategory.tawasulStudentNoteCategoryID) JOIN tawasulPerson ON (tawasulStudentNote.tawasulPersonIDCreator=tawasulPerson.tawasulPersonID) WHERE tawasulStudentNote.tawasulPersonID=:tawasulPersonID ORDER BY timestamp DESC';
                                    } else {
                                        $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulStudentNoteCategoryID' => $category);
                                        $sql = 'SELECT tawasulStudentNote.*, tawasulStudentNoteCategory.name AS category, surname, preferredName FROM tawasulStudentNote LEFT JOIN tawasulStudentNoteCategory ON (tawasulStudentNote.tawasulStudentNoteCategoryID=tawasulStudentNoteCategory.tawasulStudentNoteCategoryID) JOIN tawasulPerson ON (tawasulStudentNote.tawasulPersonIDCreator=tawasulPerson.tawasulPersonID) WHERE tawasulStudentNote.tawasulPersonID=:tawasulPersonID AND tawasulStudentNote.tawasulStudentNoteCategoryID=:tawasulStudentNoteCategoryID ORDER BY timestamp DESC';
                                    }
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                }

                                $notes = $pdo->select($sql, $data);
                                $noteGateway = $container->get(StudentNoteGateway::class);

                                // DATA TABLE
                                $table = DataTable::createPaginated('studentNotes', $noteGateway->newQueryCriteria(true));

                                $table->addExpandableColumn('note');

                                $table->addHeaderAction('add', __('Add'))
                                    ->setURL('/modules/TawasulStudents/student_view_details_notes_add.php')
                                    ->addParam('tawasulPersonID', $tawasulPersonID)
                                    ->addParam('allStudents', $allStudents)
                                    ->addParam('search', $search)
                                    ->addParam('subpage', 'Notes')
                                    ->addParam('category', $category ?? '')
                                    ->displayLabel();

                                $table->addColumn('date', __('Date'))
                                    ->description(__('Time'))
                                    ->format(function ($note) {
                                        return Format::date($note['timestamp']).'<br/>'.Format::small(Format::time($note['timestamp']));
                                    });

                                $table->addColumn('category', __('Category'))
                                    ->translatable();

                                $table->addColumn('title', __('Title'))
                                    ->description(__('Overview'))
                                    ->format(function ($note) {
                                        $title = !empty($note['title'])? $note['title'] : __('N/A');
                                        $overview = substr(strip_tags($note['note']), 0, 60);

                                        return $title.'<br/><span style="font-size: 75%; font-style: italic">'.$overview.'</span>';
                                    });

                                $table->addColumn('noteTaker', __('Note Taker'))
                                      ->format(Format::using('name', ['', 'preferredName', 'surname', 'Staff', false, true]));

                                // ACTIONS
                                $table->addActionColumn()
                                    ->addParam('tawasulStudentNoteID')
                                    ->addParam('tawasulPersonID', $tawasulPersonID)
                                    ->addParam('allStudents', $allStudents)
                                    ->addParam('search', $search)
                                    ->addParam('subpage', 'Notes')
                                    ->addParam('category', $category ?? '')
                                    ->format(function ($note, $actions) use ($highestAction, $session) {
                                        if ($note['tawasulPersonIDCreator'] == $session->get('tawasulPersonID') || $highestAction == "View Student Profile_fullEditAllNotes") {
                                            $actions->addAction('edit', __('Edit'))
                                                    ->setURL('/modules/TawasulStudents/student_view_details_notes_edit.php');
                                        }

                                        if ($highestAction == "View Student Profile_fullEditAllNotes") {
                                            $actions->addAction('delete', __('Delete'))
                                                    ->setURL('/modules/TawasulStudents/student_view_details_notes_delete.php');
                                        }
                                    });

                                echo $table->render($notes->toDataSet());
                            }
                        }
                    } elseif ($subpage == 'Attendance') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/report_studentHistory.php') == false) {
                            $page->addError(__('Your request failed because you do not have access to this action.'));
                        } else {
                            include './modules/TawasulAttendance/moduleFunctions.php';
                            include './modules/TawasulAttendance/src/StudentHistoryData.php';
                            include './modules/TawasulAttendance/src/StudentHistoryView.php';

                            // ATTENDANCE DATA
                            $attendanceData = $container->get(StudentHistoryData::class)
                                ->getAttendanceData($session->get('tawasulSchoolYearID'), $tawasulPersonID, $row['dateStart'], $row['dateEnd']);

                            // DATA TABLE
                            $renderer = $container->get(StudentHistoryView::class);
                            $renderer->addData('canTakeAttendanceByPerson', isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/attendance_take_byPerson.php'));

                            $table = DataTable::create('studentHistory', $renderer);
                            echo $table->render($attendanceData);
                        }
                    } elseif ($subpage == 'Markbook') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_view.php') == false) {
                            $page->addError(__('Your request failed because you do not have access to this action.'));
                        } else {
                            // Register scripts available to the core, but not included by default
                            $page->scripts->add('chart');

                            $highestAction2 = getHighestGroupedAction($guid, '/modules/TawasulMarkbook/markbook_view.php', $connection2);
                            if ($highestAction2 == false) {
                                $page->addError(__('The highest grouped action cannot be determined.'));
                            } else {
                                //Module includes
                                include './modules/TawasulMarkbook/moduleFunctions.php';

                                //Get settings
                                $enableEffort = $settingGateway->getSettingByScope('Markbook', 'enableEffort');
                                $enableRubrics = $settingGateway->getSettingByScope('Markbook', 'enableRubrics');
                                $attainmentAlternativeName = $settingGateway->getSettingByScope('Markbook', 'attainmentAlternativeName');
                                $attainmentAlternativeNameAbrev = $settingGateway->getSettingByScope('Markbook', 'attainmentAlternativeNameAbrev');
                                $effortAlternativeName = $settingGateway->getSettingByScope('Markbook', 'effortAlternativeName');
                                $effortAlternativeNameAbrev = $settingGateway->getSettingByScope('Markbook', 'effortAlternativeNameAbrev');
                                $enableModifiedAssessment = $settingGateway->getSettingByScope('Markbook', 'enableModifiedAssessment');

                                /**
                                 * @var AlertLevelGateway
                                 */
                                $alertLevelGateway = $container->get(AlertLevelGateway::class);
                                $alert = $alertLevelGateway->getByID(AlertLevelGateway::LEVEL_MEDIUM);
                                $role = $session->get('tawasulRoleIDCurrentCategory');
                                if ($role == 'Parent') {
                                    $showParentAttainmentWarning = $settingGateway->getSettingByScope('Markbook', 'showParentAttainmentWarning');
                                    $showParentEffortWarning = $settingGateway->getSettingByScope('Markbook', 'showParentEffortWarning');
                                } else {
                                    $showParentAttainmentWarning = 'Y';
                                    $showParentEffortWarning = 'Y';
                                }
                                $entryCount = 0;

                                $and = '';
                                $and2 = '';
                                $dataList = array();
                                $dataEntry = array();
                                $tawasulSchoolYearID = isset($_REQUEST['tawasulSchoolYearID'])? $_REQUEST['tawasulSchoolYearID'] : $session->get('tawasulSchoolYearID');

                                if ($tawasulSchoolYearID != '*') {
                                    $dataList['tawasulSchoolYearID'] = $tawasulSchoolYearID;
                                    $and .= ' AND tawasulSchoolYearID=:tawasulSchoolYearID';
                                }

                                $tawasulDepartmentID = isset($_REQUEST['tawasulDepartmentID'])? $_REQUEST['tawasulDepartmentID'] : '*';
                                if ($tawasulDepartmentID != '*') {
                                    $dataList['tawasulDepartmentID'] = $tawasulDepartmentID;
                                    $and .= ' AND tawasulDepartmentID=:tawasulDepartmentID';
                                }

                                $type = isset($_REQUEST['type'])? $_REQUEST['type'] : '';
                                if ($type != '') {
                                    $dataEntry['type'] = $type;
                                    $and2 .= ' AND type=:type';
                                }

                                $enableGroupByTerm = $settingGateway->getSettingByScope('Markbook', 'enableGroupByTerm');
                                if ($enableGroupByTerm == "Y") {
                                    $termDefault = '';
                                    $schoolYearTermGateway = $container->get(SchoolYearTermGateway::class);
                                    $termCurrent = $schoolYearTermGateway->getCurrentTermByDate(date('Y-m-d'));
                                    $termDefault = (is_array($termCurrent) && $termCurrent['tawasulSchoolYearID'] == $tawasulSchoolYearID) ? $termCurrent['tawasulSchoolYearTermID'] : '' ;
                                    $tawasulSchoolYearTermID = isset($_REQUEST['tawasulSchoolYearTermID']) ? $_REQUEST['tawasulSchoolYearTermID'] : $termDefault;
                                    if (!empty($tawasulSchoolYearTermID)) {
                                        $term = $schoolYearTermGateway->getByID($tawasulSchoolYearTermID);
                                        $dataEntry['firstDay'] = $term['firstDay'];
                                        $dataEntry['lastDay'] = $term['lastDay'];
                                        $and2 .= ' AND completeDate>=:firstDay AND completeDate<=:lastDay';
                                    }
                                }

                                echo '<p>';
                                echo __('This page displays academic results for a student throughout their school career. Only subjects with published results are shown.');
                                echo '</p>';

                                $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
                                $form->setClass('noIntBorder w-full');

                                $form->addHiddenValue('q', '/modules/'.$session->get('module').'/student_view_details.php');
                                $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
                                $form->addHiddenValue('allStudents', $allStudents);
                                $form->addHiddenValue('search', $search);
                                $form->addHiddenValue('subpage', 'Markbook');

                                $results = $container->get(DepartmentGateway::class)->selectDepartmentsOfTypeLearningArea();
                                $rowFilter = $form->addRow();
                                    $rowFilter->addLabel('tawasulDepartmentID', __('Learning Areas'));
                                    $rowFilter->addSelect('tawasulDepartmentID')
                                        ->fromArray(array('*' => __('All Learning Areas')))
                                        ->fromResults($results)
                                        ->selected($tawasulDepartmentID);

                                $dataSelect = array('tawasulPersonID' => $tawasulPersonID);
                                $sqlSelect = "SELECT tawasulSchoolYear.tawasulSchoolYearID as value, CONCAT(tawasulSchoolYear.name, ' (', tawasulYearGroup.name, ')') AS name FROM tawasulStudentEnrolment JOIN tawasulSchoolYear ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) WHERE tawasulPersonID=:tawasulPersonID ORDER BY tawasulSchoolYear.sequenceNumber";
                                $rowFilter = $form->addRow();
                                    $rowFilter->addLabel('tawasulSchoolYearID', __('School Years'));
                                    $rowFilter->addSelect('tawasulSchoolYearID')
                                        ->fromArray(array('*' => __('All Years')))
                                        ->fromQuery($pdo, $sqlSelect, $dataSelect)
                                        ->selected($tawasulSchoolYearID);

                                if ($enableGroupByTerm == "Y") {
                                    $dataSelect = [];
                                    $sqlSelect = "SELECT tawasulSchoolYear.tawasulSchoolYearID as chainedTo, tawasulSchoolYearTerm.tawasulSchoolYearTermID as value, tawasulSchoolYearTerm.name FROM tawasulSchoolYearTerm JOIN tawasulSchoolYear ON (tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) ORDER BY tawasulSchoolYearTerm.sequenceNumber";
                                    $rowFilter = $form->addRow();
                                        $rowFilter->addLabel('tawasulSchoolYearTermID', __('Term'));
                                        $rowFilter->addSelect('tawasulSchoolYearTermID')
                                            ->fromQueryChained($pdo, $sqlSelect, $dataSelect, 'tawasulSchoolYearID')
                                            ->placeholder()
                                            ->selected($tawasulSchoolYearTermID ?? '');
                                }

                                $types = $settingGateway->getSettingByScope('Markbook', 'markbookType');
                                if (!empty($types)) {
                                    $rowFilter = $form->addRow();
                                    $rowFilter->addLabel('type', __('Type'));
                                    $rowFilter->addSelect('type')
                                        ->fromString($types)
                                        ->selected($type)
                                        ->placeholder();
                                }

                                $details = isset($_GET['details'])? $_GET['details'] : 'Yes';
                                $form->addHiddenValue('details', 'No');
                                $showHide = $form->getFactory()->createCheckbox('details')->addClass('details')->setValue('Yes')->checked($details)->inline(true)
                                    ->description(__('Show/Hide Details'))->wrap('&nbsp;<span class="text-xs italic inline-block">', '</span>');

                                $rowFilter = $form->addRow();
                                    $rowFilter->addSearchSubmit($session, __('Clear Filters'), array('tawasulPersonID', 'allStudents', 'search', 'subpage'))->prepend($showHide->getOutput());

                                echo $form->getOutput();
                                ?>

                                <script type="text/javascript">
                                    /* Show/Hide detail control */
                                    $(document).ready(function(){
                                        var updateDetails = function (){
                                            if ($('input[name=details]:checked').val()=="Yes" ) {
                                                $(".detailItem").slideDown("fast", $(".detailItem").css("{'display' : 'table-row'}"));
                                            }
                                            else {
                                                $(".detailItem").slideUp("fast");
                                            }
                                        }
                                        $(".details").click(updateDetails);
                                        updateDetails();
                                    });
                                </script>

                                <?php
                                if ($highestAction2 == 'View Markbook_myClasses') {
                                    // Get class list (limited to a teacher's classes)

                                        $dataList['tawasulPersonIDTeacher'] = $session->get('tawasulPersonID');
                                        $dataList['tawasulPersonIDStudent'] = $tawasulPersonID;
                                        $sqlList = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourse.name, tawasulCourseClass.tawasulCourseClassID, tawasulScaleGrade.value AS target
                                            FROM tawasulCourse
                                            JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                                            JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                                            JOIN tawasulCourseClassPerson as teacherParticipant ON (teacherParticipant.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                                            LEFT JOIN tawasulMarkbookTarget ON (
                                                tawasulMarkbookTarget.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID
                                                AND tawasulMarkbookTarget.tawasulPersonIDStudent=:tawasulPersonIDStudent)
                                            LEFT JOIN tawasulScaleGrade ON (tawasulMarkbookTarget.tawasulScaleGradeID=tawasulScaleGrade.tawasulScaleGradeID)
                                            WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonIDStudent
                                            AND teacherParticipant.tawasulPersonID=:tawasulPersonIDTeacher
                                            $and ORDER BY course, class";
                                        $resultList = $connection2->prepare($sqlList);
                                        $resultList->execute($dataList);
                                } else {
                                    // Get class list (all classes)

                                        $dataList['tawasulPersonIDStudent'] = $tawasulPersonID;
                                        $sqlList = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourse.name, tawasulCourseClass.tawasulCourseClassID, tawasulScaleGrade.value AS target
                                            FROM tawasulCourse
                                            JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                                            JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                                            LEFT JOIN tawasulMarkbookTarget ON (
                                                tawasulMarkbookTarget.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID
                                                AND tawasulMarkbookTarget.tawasulPersonIDStudent=:tawasulPersonIDStudent)
                                            LEFT JOIN tawasulScaleGrade ON (tawasulMarkbookTarget.tawasulScaleGradeID=tawasulScaleGrade.tawasulScaleGradeID)
                                            WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonIDStudent
                                            $and ORDER BY course, class";
                                        $resultList = $connection2->prepare($sqlList);
                                        $resultList->execute($dataList);
                                }


                                if ($resultList->rowCount() > 0) {
                                    while ($rowList = $resultList->fetch()) {
                                        try {
                                            $dataEntry['tawasulPersonID'] = $tawasulPersonID;
                                            $dataEntry['tawasulCourseClassID'] = $rowList['tawasulCourseClassID'];
                                            if ($highestAction2 == 'View Markbook_viewMyChildrensClasses') {
                                                $sqlEntry = "SELECT *, tawasulMarkbookColumn.comment AS commentOn, tawasulMarkbookColumn.uploadedResponse AS uploadedResponseOn, tawasulMarkbookEntry.comment AS comment FROM tawasulMarkbookEntry JOIN tawasulMarkbookColumn ON (tawasulMarkbookEntry.tawasulMarkbookColumnID=tawasulMarkbookColumn.tawasulMarkbookColumnID) WHERE tawasulPersonIDStudent=:tawasulPersonID AND tawasulCourseClassID=:tawasulCourseClassID AND complete='Y' AND completeDate<='".date('Y-m-d')."' AND viewableParents='Y' $and2 ORDER BY completeDate";
                                            } elseif ($highestAction2 == 'View Markbook_myMarks') {
                                                $sqlEntry = "SELECT *, tawasulMarkbookColumn.comment AS commentOn, tawasulMarkbookColumn.uploadedResponse AS uploadedResponseOn, tawasulMarkbookEntry.comment AS comment FROM tawasulMarkbookEntry JOIN tawasulMarkbookColumn ON (tawasulMarkbookEntry.tawasulMarkbookColumnID=tawasulMarkbookColumn.tawasulMarkbookColumnID) WHERE tawasulPersonIDStudent=:tawasulPersonID AND tawasulCourseClassID=:tawasulCourseClassID AND complete='Y' AND completeDate<='".date('Y-m-d')."' AND viewableStudents='Y' $and2 ORDER BY completeDate";
                                            } else {
                                                $sqlEntry = "SELECT *, tawasulMarkbookColumn.comment AS commentOn, tawasulMarkbookColumn.uploadedResponse AS uploadedResponseOn, tawasulMarkbookEntry.comment AS comment FROM tawasulMarkbookEntry JOIN tawasulMarkbookColumn ON (tawasulMarkbookEntry.tawasulMarkbookColumnID=tawasulMarkbookColumn.tawasulMarkbookColumnID) WHERE tawasulPersonIDStudent=:tawasulPersonID AND tawasulCourseClassID=:tawasulCourseClassID AND complete='Y' AND completeDate<='".date('Y-m-d')."' $and2 ORDER BY completeDate";
                                            }
                                            $resultEntry = $connection2->prepare($sqlEntry);
                                            $resultEntry->execute($dataEntry);
                                        } catch (PDOException $e) {
                                        }

                                        if ($resultEntry->rowCount() > 0) {
                                            echo "<a name='".$rowList['tawasulCourseClassID']."'></a><h4>".$rowList['course'].'.'.$rowList['class']." <span style='font-size:85%; font-style: italic'>(".$rowList['name'].')</span></h4>';


                                                $dataTeachers = array('tawasulCourseClassID' => $rowList['tawasulCourseClassID']);
                                                $sqlTeachers = "SELECT title, surname, preferredName, tawasulCourseClassPerson.reportable FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE role='Teacher' AND tawasulCourseClassID=:tawasulCourseClassID ORDER BY surname, preferredName";
                                                $resultTeachers = $connection2->prepare($sqlTeachers);
                                                $resultTeachers->execute($dataTeachers);

                                            $teachers = '<p><b>'.__('Taught by:').'</b> ';
                                            while ($rowTeachers = $resultTeachers->fetch()) {
                                                if ($rowTeachers['reportable'] != 'Y') continue;
                                                $teachers = $teachers.Format::name($rowTeachers['title'], $rowTeachers['preferredName'], $rowTeachers['surname'], 'Staff', false, false).', ';
                                            }
                                            $teachers = substr($teachers, 0, -2);
                                            $teachers = $teachers.'</p>';
                                            echo $teachers;

                                            if ($rowList['target'] != '') {
                                                echo "<div style='font-weight: bold' class='linkTop'>";
                                                echo __('Target').': '.$rowList['target'];
                                                echo '</div>';
                                            }

                                            echo "<table cellspacing='0' style='width: 100%'>";
                                            echo "<tr class='head'>";
                                            echo "<th style='width: 120px'>";
                                            echo __('Assessment');
                                            echo '</th>';
                                            if ($enableModifiedAssessment == 'Y') {
                                                echo "<th style='width: 75px'>";
                                                    echo __('Modified');
                                                echo '</th>';
                                            }
                                            echo "<th style='width: 75px; text-align: center'>";
                                            if ($attainmentAlternativeName != '') {
                                                echo $attainmentAlternativeName;
                                            } else {
                                                echo __('Attainment');
                                            }
                                            echo '</th>';
                                            if ($enableEffort == 'Y') {
                                                echo "<th style='width: 75px; text-align: center'>";
                                                if ($effortAlternativeName != '') {
                                                    echo $effortAlternativeName;
                                                } else {
                                                    echo __('Effort');
                                                }
                                                echo '</th>';
                                            }
                                            echo '<th>';
                                            echo __('Comment');
                                            echo '</th>';
                                            echo "<th style='width: 75px'>";
                                            echo __('Submission');
                                            echo '</th>';
                                            echo '</tr>';

                                            $count = 0;
                                            while ($rowEntry = $resultEntry->fetch()) {
                                                if ($count % 2 == 0) {
                                                    $rowNum = 'even';
                                                } else {
                                                    $rowNum = 'odd';
                                                }
                                                ++$count;
                                                ++$entryCount;

                                                echo "<tr class=$rowNum>";
                                                echo '<td>';
                                                echo "<span title='".htmlPrep($rowEntry['description'])."'><b><u>".$rowEntry['name'].'</u></b></span><br/>';
                                                echo "<span style='font-size: 90%; font-style: italic; font-weight: normal'>";
                                                $unit = getUnit($connection2, $rowEntry['tawasulUnitID'], $rowEntry['tawasulCourseClassID']);
                                                if (isset($unit[0])) {
                                                    echo $unit[0].'<br/>';
                                                }
                                                if (isset($unit[1])) {
                                                    if ($unit[1] != '') {
                                                        echo $unit[1].' '.__('Unit').'</i><br/>';
                                                    }
                                                }
                                                if ($rowEntry['completeDate'] != '') {
                                                    echo __('Marked on').' '.Format::date($rowEntry['completeDate']).'<br/>';
                                                } else {
                                                    echo __('Unmarked').'<br/>';
                                                }
                                                echo $rowEntry['type'];
                                                if ($rowEntry['attachment'] != '' and file_exists($session->get('absolutePath').'/'.$rowEntry['attachment'])) {
                                                    echo " | <a 'title='".__('Download more information')."' href='".$session->get('absoluteURL').'/'.$rowEntry['attachment']."'>".__('More info').'</a>';
                                                }
                                                echo '</span><br/>';
                                                echo '</td>';
                                                if ($enableModifiedAssessment == 'Y') {
                                                    if (!is_null($rowEntry['modifiedAssessment'])) {
                                                        echo "<td>";
                                                        echo Format::yesNo($rowEntry['modifiedAssessment']);
                                                        echo '</td>';
                                                    } else {
                                                        echo "<td class='dull' style='color: #bbb; text-align: center'>";
                                                        echo __('N/A');
                                                        echo '</td>';
                                                    }
                                                }
                                                if ($rowEntry['attainment'] == 'N' or ($rowEntry['tawasulScaleIDAttainment'] == '' and $rowEntry['tawasulRubricIDAttainment'] == '')) {
                                                    echo "<td class='dull' style='color: #bbb; text-align: center'>";
                                                    echo __('N/A');
                                                    echo '</td>';
                                                } else {
                                                    echo "<td style='text-align: center'>";
                                                    $attainmentExtra = '';

                                                        $dataAttainment = array('tawasulScaleIDAttainment' => $rowEntry['tawasulScaleIDAttainment']);
                                                        $sqlAttainment = 'SELECT * FROM tawasulScale WHERE tawasulScaleID=:tawasulScaleIDAttainment';
                                                        $resultAttainment = $connection2->prepare($sqlAttainment);
                                                        $resultAttainment->execute($dataAttainment);
                                                    if ($resultAttainment->rowCount() == 1) {
                                                        $rowAttainment = $resultAttainment->fetch();
                                                        $attainmentExtra = '<br/>'.__($rowAttainment['usage']);
                                                    }
                                                    $styleAttainment = "style='font-weight: bold'";
                                                    if ($rowEntry['attainmentConcern'] == 'Y' and $showParentAttainmentWarning == 'Y') {
                                                        $styleAttainment = "style='color: ".$alert['color'].'; font-weight: bold; border: 2px solid '.$alert['color'].'; padding: 2px 4px; background-color: '.$alert['colorBG']."'";
                                                    } elseif ($rowEntry['attainmentConcern'] == 'P' and $showParentAttainmentWarning == 'Y') {
                                                        $styleAttainment = "style='color: #390; font-weight: bold; border: 2px solid #390; padding: 2px 4px; background-color: #D4F6DC'";
                                                    }
                                                    echo "<div $styleAttainment>".$rowEntry['attainmentValue'];
                                                    if ($rowEntry['tawasulRubricIDAttainment'] != '' and $enableRubrics =='Y') {
                                                        echo "<a class='thickbox' href='".$session->get('absoluteURL').'/fullscreen.php?q=/modules/TawasulMarkbook/markbook_view_rubric.php&tawasulRubricID='.$rowEntry['tawasulRubricIDAttainment'].'&tawasulCourseClassID='.$rowList['tawasulCourseClassID'].'&tawasulMarkbookColumnID='.$rowEntry['tawasulMarkbookColumnID']."&tawasulPersonID=$tawasulPersonID&mark=FALSE&type=attainment&width=1100&height=550'><img style='margin-bottom: -3px; margin-left: 3px' title='View Rubric' src='./themes/".$session->get('tawasulThemeName')."/img/rubric.png'/></a>";
                                                    }
                                                    echo '</div>';
                                                    if ($rowEntry['attainmentValue'] != '') {
                                                        echo "<div class='detailItem' style='font-size: 75%; font-style: italic; margin-top: 2px'><b>".htmlPrep(__($rowEntry['attainmentDescriptor'])).'</b>'.__($attainmentExtra).'</div>';
                                                    }
                                                    echo '</td>';
                                                }
                                                if ($enableEffort == 'Y') {
                                                    if ($rowEntry['effort'] == 'N' or ($rowEntry['tawasulScaleIDEffort'] == '' and $rowEntry['tawasulRubricIDEffort'] == '')) {
                                                        echo "<td class='dull' style='color: #bbb; text-align: center'>";
                                                        echo __('N/A');
                                                        echo '</td>';
                                                    } else {
                                                        echo "<td style='text-align: center'>";
                                                        $effortExtra = '';

                                                            $dataEffort = array('tawasulScaleIDEffort' => $rowEntry['tawasulScaleIDEffort']);
                                                            $sqlEffort = 'SELECT * FROM tawasulScale WHERE tawasulScaleID=:tawasulScaleIDEffort';
                                                            $resultEffort = $connection2->prepare($sqlEffort);
                                                            $resultEffort->execute($dataEffort);

                                                        if ($resultEffort->rowCount() == 1) {
                                                            $rowEffort = $resultEffort->fetch();
                                                            $effortExtra = '<br/>'.__($rowEffort['usage']);
                                                        }
                                                        $styleEffort = "style='font-weight: bold'";
                                                        if ($rowEntry['effortConcern'] == 'Y' and $showParentEffortWarning == 'Y') {
                                                            $styleEffort = "style='color: ".$alert['color'].'; font-weight: bold; border: 2px solid '.$alert['color'].'; padding: 2px 4px; background-color: '.$alert['colorBG']."'";
                                                        }
                                                        echo "<div $styleEffort>".$rowEntry['effortValue'];
                                                        if ($rowEntry['tawasulRubricIDEffort'] != '' and $enableRubrics =='Y') {
                                                            echo "<a class='thickbox' href='".$session->get('absoluteURL').'/fullscreen.php?q=/modules/TawasulMarkbook/markbook_view_rubric.php&tawasulRubricID='.$rowEntry['tawasulRubricIDEffort'].'&tawasulCourseClassID='.$rowList['tawasulCourseClassID'].'&tawasulMarkbookColumnID='.$rowEntry['tawasulMarkbookColumnID']."&tawasulPersonID=$tawasulPersonID&mark=FALSE&type=effort&width=1100&height=550'><img style='margin-bottom: -3px; margin-left: 3px' title='View Rubric' src='./themes/".$session->get('tawasulThemeName')."/img/rubric.png'/></a>";
                                                        }
                                                        echo '</div>';
                                                        if ($rowEntry['effortValue'] != '') {
                                                            echo "<div class='detailItem' style='font-size: 75%; font-style: italic; margin-top: 2px'><b>".htmlPrep(__($rowEntry['effortDescriptor'])).'</b>'.__($effortExtra).'</div>';
                                                        }
                                                        echo '</td>';
                                                    }
                                                }
                                                if ($rowEntry['commentOn'] == 'N' and $rowEntry['uploadedResponseOn'] == 'N') {
                                                    echo "<td class='dull' style='color: #bbb; text-align: center'>";
                                                    echo __('N/A');
                                                    echo '</td>';
                                                } else {
                                                    echo '<td>';
                                                    if ($rowEntry['comment'] != '') {
                                                        if (mb_strlen($rowEntry['comment']) > 200) {
                                                            echo "<script type='text/javascript'>";
                                                            echo '$(document).ready(function(){';
                                                            echo "\$(\".comment-$entryCount\").hide();";
                                                            echo "\$(\".show_hide-$entryCount\").fadeIn(1000);";
                                                            echo "\$(\".show_hide-$entryCount\").click(function(){";
                                                            echo "\$(\".comment-$entryCount\").fadeToggle(1000);";
                                                            echo '});';
                                                            echo '});';
                                                            echo '</script>';
                                                            echo '<span>'.mb_substr($rowEntry['comment'], 0, 200).'...<br/>';
                                                            echo "<a title='".__('View Description')."' class='show_hide-$entryCount' onclick='return false;' href='#'>".__('Read more').'</a></span><br/>';
                                                        } else {
                                                            echo nl2br($rowEntry['comment']).'<br/>';
                                                        }
                                                    }
                                                    if ($rowEntry['response'] != '') {
                                                        echo "<a title='Uploaded Response' href='".$session->get('absoluteURL').'/'.$rowEntry['response']."'>".__('Uploaded Response').'</a><br/>';
                                                    }
                                                    echo '</td>';
                                                }
                                                if ($rowEntry['tawasulPlannerEntryID'] == 0) {
                                                    echo "<td class='dull' style='color: #bbb; text-align: center'>";
                                                    echo __('N/A');
                                                    echo '</td>';
                                                } else {

                                                        $dataSub = array('tawasulPlannerEntryID' => $rowEntry['tawasulPlannerEntryID']);
                                                        $sqlSub = "SELECT * FROM tawasulPlannerEntry WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID AND homeworkSubmission='Y'";
                                                        $resultSub = $connection2->prepare($sqlSub);
                                                        $resultSub->execute($dataSub);
                                                    if ($resultSub->rowCount() != 1) {
                                                        echo "<td class='dull' style='color: #bbb; text-align: center'>";
                                                        echo __('N/A');
                                                        echo '</td>';
                                                    } else {
                                                        echo '<td>';
                                                        $rowSub = $resultSub->fetch();
                                                        $resultWork = $container->get(PlannerEntryHomeworkGateway::class)->selectHomeworkByStudent($rowEntry['tawasulPlannerEntryID'], $_GET['tawasulPersonID']);

                                                        if ($resultWork->rowCount() > 0) {
                                                            $rowWork = $resultWork->fetch();

                                                            if ($rowWork['status'] == 'Exemption') {
                                                                $linkText = __('Exemption');
                                                            } elseif ($rowWork['version'] == 'Final') {
                                                                $linkText = __('Final');
                                                            } else {
                                                                $linkText = __('Draft').' '.$rowWork['count'];
                                                            }

                                                            $style = '';
                                                            $status = 'On Time';
                                                            if ($rowWork['status'] == 'Exemption') {
                                                                $status = __('Exemption');
                                                            } elseif ($rowWork['status'] == 'Late') {
                                                                $style = "style='color: #ff0000; font-weight: bold; border: 2px solid #ff0000; padding: 2px 4px'";
                                                                $status = __('Late');
                                                            }

                                                            if ($rowWork['type'] == 'File') {
                                                                echo "<span title='".$rowWork['version'].". $status. ".sprintf(__('Submitted at %1$s on %2$s'), substr($rowWork['timestamp'], 11, 5), Format::date(substr($rowWork['timestamp'], 0, 10)))."' $style><a href='".$session->get('absoluteURL').'/'.$rowWork['location']."'>$linkText</a></span>";
                                                            } elseif ($rowWork['type'] == 'Link') {
                                                                echo "<span title='".$rowWork['version'].". $status. ".sprintf(__('Submitted at %1$s on %2$s'), substr($rowWork['timestamp'], 11, 5), Format::date(substr($rowWork['timestamp'], 0, 10)))."' $style><a target='_blank' href='".$rowWork['location']."'>$linkText</a></span>";
                                                            } else {
                                                                echo "<span title='$status. ".sprintf(__('Recorded at %1$s on %2$s'), substr($rowWork['timestamp'], 11, 5), Format::date(substr($rowWork['timestamp'], 0, 10)))."' $style>$linkText</span>";
                                                            }
                                                        } else {
                                                            if (date('Y-m-d H:i:s') < $rowSub['homeworkDueDateTime']) {
                                                                echo "<span title='Pending'>".__('Pending').'</span>';
                                                            } else {
                                                                if ($row['dateStart'] > $rowSub['date']) {
                                                                    echo "<span title='".__('Student joined school after assessment was given.')."' style='color: #000; font-weight: normal; border: 2px none #ff0000; padding: 2px 4px'>".__('NA').'</span>';
                                                                } else {
                                                                    if ($rowSub['homeworkSubmissionRequired'] == 'Required') {
                                                                        echo "<div style='color: #ff0000; font-weight: bold; border: 2px solid #ff0000; padding: 2px 4px; margin: 2px 0px'>".__('Incomplete').'</div>';
                                                                    } else {
                                                                        echo __('Not submitted online');
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        echo '</td>';
                                                    }
                                                }
                                                echo '</tr>';
                                                if ($rowEntry['commentOn'] == 'Y' && mb_strlen($rowEntry['comment']) > 200) {
                                                    echo "<tr class='comment-$entryCount' id='comment-$entryCount'>";
                                                    echo '<td colspan=6>';
                                                    echo nl2br($rowEntry['comment']);
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                            }

                                            $enableColumnWeighting = $settingGateway->getSettingByScope('Markbook', 'enableColumnWeighting');
                                            $enableDisplayCumulativeMarks = $settingGateway->getSettingByScope('Markbook', 'enableDisplayCumulativeMarks');

                                            if ($enableColumnWeighting == 'Y' && $enableDisplayCumulativeMarks == 'Y') {
                                                renderStudentCumulativeMarks($tawasul, $pdo, $_GET['tawasulPersonID'], $rowList['tawasulCourseClassID'], $tawasulSchoolYearTermID ?? '');
                                            }

                                            echo '</table>';
                                        }
                                    }
                                }
                                if ($entryCount < 1) {
                                    echo "<div class='message'>";
                                    echo __('There are no records to display.');
                                    echo '</div>';
                                }
                            }
                        }
                    } elseif ($subpage == 'Internal Assessment') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/internalAssessment_view.php') == false) {
                            $page->addError(__('Your request failed because you do not have access to this action.'));
                        } else {
                            $highestAction2 = getHighestGroupedAction($guid, '/modules/TawasulFormalAssessment/internalAssessment_view.php', $connection2);
                            if ($highestAction2 == false) {
                                $page->addError(__('The highest grouped action cannot be determined.'));
                            } else {
                                //Module includes
                                include './modules/TawasulFormalAssessment/moduleFunctions.php';

                                if ($highestAction2 == 'View Internal Assessments_all') {
                                    echo getInternalAssessmentRecord($guid, $connection2, $tawasulPersonID);
                                } elseif ($highestAction2 == 'View Internal Assessments_myChildrens') {
                                    echo getInternalAssessmentRecord($guid, $connection2, $tawasulPersonID, 'parent');
                                } elseif ($highestAction2 == 'View Internal Assessments_mine') {
                                    echo getInternalAssessmentRecord($guid, $connection2, $session->get('tawasulPersonID'), 'student');
                                }
                            }
                        }
                    } elseif ($subpage == 'External Assessment') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/externalAssessment_details.php') == false and isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/externalAssessment_view.php') == false) {
                            $page->addError(__('Your request failed because you do not have access to this action.'));
                        } else {
                            //Module includes
                            include './modules/TawasulFormalAssessment/moduleFunctions.php';

                            //Print assessments
                            $tawasulYearGroupID = '';
                            if (isset($row['tawasulYearGroupID'])) {
                                $tawasulYearGroupID = $row['tawasulYearGroupID'];
                            }
                            externalAssessmentDetails($guid, $tawasulPersonID, $connection2, $tawasulYearGroupID);
                        }
                    } elseif ($subpage == 'Reports') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/archive_byStudent_view.php') == false) {
                            $page->addError(__('Your request failed because you do not have access to this action.'));
                        } else {
                            $highestActionReports = getHighestGroupedAction($guid, '/modules/TawasulReports/archive_byStudent_view.php', $connection2);
                            $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');

                            if ($highestActionReports == 'View by Student') {
                                $student = $container->get(UserGateway::class)->getByID($tawasulPersonID);
                            } else if ($highestActionReports == 'View Reports_myChildren') {
                                $studentGateway = $container->get(StudentGateway::class);
                                $children = $studentGateway
                                    ->selectAnyStudentsByFamilyAdult($tawasulSchoolYearID, $session->get('tawasulPersonID'))
                                    ->fetchGroupedUnique();

                                if (!empty($children[$tawasulPersonID])) {
                                    $student = $container->get(UserGateway::class)->getByID($tawasulPersonID);
                                }
                            } else if ($highestActionReports == 'View Reports_mine') {
                                $tawasulPersonID = $session->get('tawasulPersonID');
                                $student =  $container->get(StudentGateway::class)->selectActiveStudentByPerson($tawasulSchoolYearID, $tawasulPersonID)->fetch();
                            }

                            if (empty($student)) {
                                $page->addError(__('You do not have access to this action.'));
                                return;
                            }

                            $archiveInformation = $container->get(SettingGateway::class)->getSettingByScope('Reports', 'archiveInformation');

                            // CRITERIA
                            include './modules/TawasulReports/src/Domain/ReportArchiveEntryGateway.php';
                            $reportArchiveEntryGateway = $container->get(ReportArchiveEntryGateway::class);
                            $criteria = $reportArchiveEntryGateway->newQueryCriteria()
                                ->sortBy('sequenceNumber', 'DESC')
                                ->sortBy(['timestampCreated'])
                                ->fromPOST();

                            // QUERY
                            $canViewDraftReports = isActionAccessible($guid, $connection2, '/modules/TawasulReports/archive_byStudent.php', 'View Draft Reports');
                            $canViewPastReports = isActionAccessible($guid, $connection2, '/modules/TawasulReports/archive_byStudent.php', 'View Past Reports');
                            $roleCategory = $session->get('tawasulRoleIDCurrentCategory');

                            $reports = $reportArchiveEntryGateway->queryArchiveByStudent($criteria, $tawasulPersonID, $roleCategory, $canViewDraftReports, $canViewPastReports);

                            $reportsBySchoolYear = array_reduce($reports->toArray(), function ($group, $item) {
                                $group[$item['schoolYear']][] = $item;
                                return $group;
                            }, []);

                            if (empty($reportsBySchoolYear)) {
                                $reportsBySchoolYear = [__('Reports') => []];
                            }

                            foreach ($reportsBySchoolYear as $schoolYear => $reports) {
                                // DATA TABLE
                                $table = DataTable::create('reportsView');
                                if ($schoolYear != 'Reports') {
                                    $table->setTitle($schoolYear);
                                }

                                $table->addColumn('reportName', __('Report'))
                                    ->width('30%')
                                    ->format(function ($report) {
                                        return !empty($report['reportName'])? $report['reportName'] : $report['reportIdentifier'];
                                    });

                                $table->addColumn('yearGroup', __('Year Group'))->width('15%');
                                $table->addColumn('formGroup', __('Form Group'))->width('15%');
                                $table->addColumn('timestampModified', __('Date'))
                                    ->width('30%')
                                    ->format(function ($report) {
                                        $output = Format::dateReadable($report['timestampModified']);
                                        if ($report['status'] == 'Draft') {
                                            $output .= '<span class="tag ml-2 dull">'.__($report['status']).'</span>';
                                        }

                                        if (!empty($report['timestampAccessed'])) {
                                            $title = Format::name($report['parentTitle'], $report['parentPreferredName'], $report['parentSurname'], 'Parent', false).': '.Format::relativeTime($report['timestampAccessed'], false);
                                            $output .= '<span class="tag ml-2 success" title="'.$title.'">'.__('Read').'</span>';
                                        }

                                        return $output;
                                    });

                                $table->addActionColumn()
                                    ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                                    ->format(function ($report, $actions) {
                                        $actions->addAction('view', __('View'))
                                            ->directLink()
                                            ->addParam('action', 'view')
                                            ->addParam('tawasulReportArchiveEntryID', $report['tawasulReportArchiveEntryID'] ?? '')
                                            ->addParam('tawasulPersonID', $report['tawasulPersonID'] ?? '')
                                            ->setURL('/modules/TawasulReports/archive_byStudent_download.php');

                                        $actions->addAction('download', __('Download'))
                                            ->setIcon('download')
                                            ->directLink()
                                            ->addParam('tawasulReportArchiveEntryID', $report['tawasulReportArchiveEntryID'] ?? '')
                                            ->addParam('tawasulPersonID', $report['tawasulPersonID'] ?? '')
                                            ->setURL('/modules/TawasulReports/archive_byStudent_download.php');
                                    });

                                echo $table->render(new DataSet($reports));
                            }
                        }
                    } elseif ($subpage == 'Individual Needs') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/in_view.php') == false) {
                            $page->addError(__('Your request failed because you do not have access to this action.'));
                        } else {
                            //Edit link
                            if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/in_edit.php') == true) {
                                $form = Form::createBlank('buttons');
                                $form->addHeaderAction('edit', __('Edit Individual Needs Record'))
                                    ->setURL('/modules/TawasulIndividualNeeds/in_edit.php')
                                    ->addParam('tawasulPersonID', $tawasulPersonID)
                                    ->displayLabel();
                                if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/in_supportPlan_manage.php') == true) {
                                    $form->addHeaderAction('manage', __('Student Support Plans'))
                                        ->setIcon('planner')
                                        ->setURL('/modules/TawasulIndividualNeeds/in_supportPlan_manage.php')
                                        ->addParam('tawasulPersonID', $tawasulPersonID)
                                        ->displayLabel();
                                }
                                echo $form->getOutput();
                            }

                            //Module includes
                            include './modules/TawasulIndividualNeeds/moduleFunctions.php';

                            $statusTable = printINStatusTable($connection2, $guid, $tawasulPersonID, 'disabled');
                            if ($statusTable == false) {
                                $page->addError(__('Your request failed due to a database error.'));
                            } else {
                                echo $statusTable;
                            }

                            //Get and display a list of student's educational assistants

                                $dataDetail = array('tawasulPersonID1' => $tawasulPersonID, 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID2' => $tawasulPersonID);
                                $sqlDetail = "(SELECT DISTINCT surname, preferredName, email
                                    FROM tawasulPerson
                                        JOIN tawasulINAssistant ON (tawasulINAssistant.tawasulPersonIDAssistant=tawasulPerson.tawasulPersonID)
                                        JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID)
                                    WHERE status='Full'
                                        AND tawasulPersonIDStudent=:tawasulPersonID1)
                                UNION
                                (SELECT DISTINCT surname, preferredName, email
                                    FROM tawasulPerson
                                        JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulPersonIDEA=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDEA2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDEA3=tawasulPerson.tawasulPersonID)
                                        JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                                        JOIN tawasulSchoolYear ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                                    WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                                        AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID2
                                )
                                ORDER BY preferredName, surname, email";
                                $resultDetail = $connection2->prepare($sqlDetail);
                                $resultDetail->execute($dataDetail);
                            if ($resultDetail->rowCount() > 0) {
                                echo '<h3>';
                                echo __('Educational Assistants');
                                echo '</h3>';

                                echo '<ul>';
                                while ($rowDetail = $resultDetail->fetch()) {
                                    echo '<li>'.htmlPrep(Format::name('', $rowDetail['preferredName'], $rowDetail['surname'], 'Student', false));
                                    if ($rowDetail['email'] != '') {
                                        $rowDetail['email'] = filter_var(trim($rowDetail['email']), FILTER_SANITIZE_EMAIL);
                                        echo htmlPrep(' <'.$rowDetail['email'].'>');
                                    }
                                    echo '</li>';
                                }
                                echo '</ul>';
                            }

                            echo '<h3>';
                            echo __('Individual Education Plan');
                            echo '</h3>';

                            $dataIN = array('tawasulPersonID' => $tawasulPersonID);
                            $sqlIN = 'SELECT * FROM tawasulIN WHERE tawasulPersonID=:tawasulPersonID';
                            $rowIN = $pdo->select($sqlIN, $dataIN)->fetch();

                            if (empty($rowIN)) {
                                echo $page->getBlankSlate();
                            } else {
                                echo "<div style='font-weight: bold'>".__('Targets').'</div>';
                                echo '<p>'.$rowIN['targets'].'</p>';

                                echo "<div style='font-weight: bold; margin-top: 30px'>".__('Teaching Strategies').'</div>';
                                echo '<p>'.$rowIN['strategies'].'</p>';

                                echo "<div style='font-weight: bold; margin-top: 30px'>".__('Notes & Review').'s</div>';
                                echo '<p>'.$rowIN['notes'].'</p>';
                            }

                            // CUSTOM FIELDS
                            if (!empty($rowIN['fields'])) {
                                $table = DataTable::createDetails('inFields');

                                $container->get(CustomFieldHandler::class)->addCustomFieldsToTable($table, 'Individual Needs', ['student' => 1], $rowIN['fields']);

                                echo $table->render([$rowIN]);
                            }
                        }
                    } elseif ($subpage == 'Library Borrowing') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_browse.php') == false) {
                            $page->addError(__('Your request failed because you do not have access to this action.'));
                        } else {
                            $page->return->addReturns([
                                'warning1' => __('This action is not possible for the selected record. Please check the status in the Lending & Activity Log.'),
                                'warning3' => __('The specified record cannot be found.'),
                            ]);

                            //Print borrowing record
                            $libraryBorrowing = $container->get(LibraryBorrowingView::class);
                            $libraryBorrowing->setStudent($tawasulPersonID)->compose($page);
                        }
                    } elseif ($subpage == 'Timetable') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/tt_view.php') == false) {
                            $page->addError(__('Your request failed because you do not have access to this action.'));
                        } else {
                            if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php') == true) {
                                $role = $roleGateway->getRoleCategory($row['tawasulRoleIDPrimary']);
                                if ($role == 'Student' or $role == 'Staff') {
                                    $form = Form::createBlank('buttons');
                                    $form->addHeaderAction('edit', __('Edit'))
                                        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php')
                                        ->addParam('tawasulPersonID', $tawasulPersonID)
                                        ->addParam('tawasulSchoolYearID', $session->get('tawasulSchoolYearID'))
                                        ->addParam('type', $role)
                                        ->displayLabel();
                                    echo $form->getOutput();
                                }
                            }

                            $ttDate = !empty($_REQUEST['ttDate']) ? Format::dateConvert($_REQUEST['ttDate']) : null;
                            $tawasulTTID = $_REQUEST['tawasulTTID'] ?? '';
                            
                            // Create timetable context
                            $context = $container->get(TimetableContext::class)
                                ->set('tawasulSchoolYearID', $session->get('tawasulSchoolYearID'))
                                ->set('tawasulPersonID', $tawasulPersonID)
                                ->set('tawasulTTID', $tawasulTTID);

                            // Build and render timetable
                            echo $container->get(Timetable::class)
                                ->setDate($ttDate)
                                ->setContext($context)
                                ->addCoreLayers($container)
                                ->getOutput();
                        }
                    } elseif ($subpage == 'Activities') {
                        if (!(isActionAccessible($guid, $connection2, '/modules/TawasulActivities/report_activityChoices_byStudent')) && !isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_view_myChildren.php') && !(isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_my.php'))) {
                            $page->addError(__('Your request failed because you do not have access to this action.'));
                        } else {
                            echo '<p>';
                            echo __('This report shows the current and historical activities that a student has enrolled in.');
                            echo '</p>';

                            $dateType = $settingGateway->getSettingByScope('Activities', 'dateType');
                            if ($dateType == 'Term') {
                                $maxPerTerm = $settingGateway->getSettingByScope('Activities', 'maxPerTerm');
                            }
                                $dataYears = array('tawasulPersonID' => $tawasulPersonID);
                                $sqlYears = 'SELECT * FROM tawasulStudentEnrolment JOIN tawasulSchoolYear ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) WHERE tawasulPersonID=:tawasulPersonID ORDER BY sequenceNumber DESC';
                                $resultYears = $connection2->prepare($sqlYears);
                                $resultYears->execute($dataYears);

                            if ($resultYears->rowCount() < 1) {
                                echo $page->getBlankSlate();
                            } else {
                                $yearCount = 0;
                                while ($rowYears = $resultYears->fetch()) {
                                    $class = '';
                                    if ($yearCount == 0) {
                                        $class = "class='top'";
                                    }

                                    ++$yearCount;
                                    try {
                                        $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulSchoolYearID' => $rowYears['tawasulSchoolYearID']);
                                        $sql = "SELECT tawasulActivity.tawasulActivityID, tawasulActivity.tawasulSchoolYearID, tawasulActivity.name, tawasulActivity.type, tawasulActivity.programStart, tawasulActivity.programEnd, GROUP_CONCAT(tawasulSchoolYearTerm.nameShort ORDER BY tawasulSchoolYearTerm.sequenceNumber SEPARATOR ', ') as terms, tawasulActivityStudent.status, NULL AS role 
                                        FROM tawasulActivity 
                                        JOIN tawasulActivityStudent ON (tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID) 
                                        LEFT JOIN tawasulActivityCategory ON (tawasulActivityCategory.tawasulActivityCategoryID=tawasulActivity.tawasulActivityCategoryID) 
                                        LEFT JOIN tawasulSchoolYearTerm ON (FIND_IN_SET(tawasulSchoolYearTerm.tawasulSchoolYearTermID, tawasulActivity.tawasulSchoolYearTermIDList)) 
                                        WHERE tawasulActivityStudent.tawasulPersonID=:tawasulPersonID 
                                        AND tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID 
                                        AND tawasulActivity.active='Y' 
                                        AND tawasulActivityStudent.status <> 'Not Accepted' 
                                        AND (tawasulActivityCategory.tawasulActivityCategoryID IS NULL OR CURRENT_TIMESTAMP >= tawasulActivityCategory.accessEnrolmentDate)
                                        GROUP BY tawasulActivity.tawasulActivityID, tawasulActivityStudent.status ORDER BY tawasulActivityStudent.status, tawasulActivity.name";
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                        $resultData = $result->fetchAll();
                                    } catch (PDOException $e) {
                                    }

                                    $table = DataTable::create('activities');
                                    $table->setTitle($rowYears['name']);

                                    $table->modifyRows(function ($values, $row) {
                                        if ($values['status'] == 'Pending') $row->addClass('warning');
                                        if ($values['status'] == 'Waiting List') $row->addClass('warning');
                                        if ($values['status'] == 'Not Accepted') $row->addClass('dull');
                                        if ($values['status'] == 'Left') $row->addClass('dull');
                                        return $row;
                                    });

                                    $table->addColumn('name', __('Activity'));
                                    $table->addColumn('type', __('Type'));
                                    $table->addColumn('date', $dateType == "Date" ? __('Dates') : __('Term'))
                                          ->format(function ($row) use ($dateType) {
                                            if ($dateType != 'Date') {
                                                return $row['terms'];
                                            } else {
                                                return Format::dateRangeReadable($row['programStart'], $row['programEnd']);
                                            }
                                          });
                                    $table->addColumn('status', __('Status'))->translatable();

                                    $canViewActivities = isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_view_full.php');
                                    $table->addActionColumn()
                                          ->format(function ($activity, $actions) use ($session, $canViewActivities) {
                                            $role = $session->get('tawasulRoleIDCurrentCategory');
                                            
                                            if ($canViewActivities) {
                                                $actions->addAction('view', __('View Details'))
                                                    ->setURL('/modules/TawasulActivities/activities_view_full.php')
                                                    ->addParam('tawasulActivityID', $activity['tawasulActivityID'])
                                                    ->modalWindow(1000, 500);
                                            } else if ($role == 'Student' && $activity['tawasulSchoolYearID'] == $session->get('tawasulSchoolYearID')) { 
                                                $actions->addAction('view', __('View Details'))
                                                    ->setURL('/modules/TawasulActivities/explore_activity.php')
                                                    ->addParam('sidebar', 'false')
                                                    ->addParam('tawasulActivityID', $activity['tawasulActivityID'])
                                                    ->modalWindow(1200, 600);
                                            }
                                          });
                                    echo $table->render($resultData);
                                }
                            }
                        }
                    } elseif ($subpage == 'Homework') {
                        if (!(isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_edit.php') or isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_view_full.php'))) {
                            $page->addError(__('Your request failed because you do not have access to this action.'));
                        } else {
                            $role = $session->get('tawasulRoleIDCurrentCategory');
                            $plannerGateway = $container->get(PlannerEntryGateway::class);

                            // DEADLINES
                            $deadlines = $plannerGateway->selectUpcomingHomeworkByStudent($session->get('tawasulSchoolYearID'), $tawasulPersonID, $role == 'Student' ? 'viewableStudents' : 'viewableParents')->fetchAll();

                            echo $page->fetchFromTemplate('ui/upcomingDeadlines.twig.html', [
                                'tawasulPersonID' => $tawasulPersonID,
                                'deadlines' => $deadlines,
                                'heading' => 'h4'
                            ]);

                            // HOMEWORK TABLE
                            include './modules/TawasulPlanner/src/Tables/HomeworkTable.php';
                            $page->scripts->add('planner', '/modules/TawasulPlanner/js/module.js');

                            $table = $container->get(HomeworkTable::class)->create($session->get('tawasulSchoolYearID'), $tawasulPersonID, $role == 'Student' ? 'Student' : 'Parent');

                            echo $table->getOutput();
                        }
                    } elseif ($subpage == 'Behaviour') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulBehaviour/behaviour_view.php') == false) {
                            $page->addError(__('Your request failed because you do not have access to this action.'));
                        } else {
                            include './modules/TawasulBehaviour/moduleFunctions.php';
                            
                            $highestActionBehaviour = getHighestGroupedAction($guid, '/modules/TawasulBehaviour/behaviour_view.php', $connection2);
                            
                            //Print assessments
                            if ($highestActionBehaviour == 'View Behaviour Records_my') {
                                echo getBehaviourRecord($container, $tawasulPersonID, $session->get('tawasulPersonID'));
                            } else {
                                echo getBehaviourRecord($container, $tawasulPersonID);
                            }
                        }
                    }

                    // Handle Student Profile Hooks
                    if (!empty($hook)) {
                        $rowHook = $hookGateway->getByID($_GET['tawasulHookID'] ?? '');
                        if (empty($rowHook)) {
                            echo $page->getBlankSlate();
                        } else {
                            $options = unserialize($rowHook['options']);

                            // Check for permission to hook
                            $hookPermission = $hookGateway->getHookPermission($rowHook['tawasulHookID'], $session->get('tawasulRoleIDCurrent'), $options['sourceModuleName'] ?? '', $options['sourceModuleAction'] ?? '');

                            if (empty($options) || empty($hookPermission)) {
                                echo Format::alert(__('Your request failed because you do not have access to this action.'), 'error');
                            } else {
                                $include = $session->get('absolutePath').'/modules/'.$options['sourceModuleName'].'/'.$options['sourceModuleInclude'];
                                if (!file_exists($include)) {
                                    echo Format::alert(__('The selected page cannot be displayed due to a hook error.'), 'error');
                                } else {
                                    include $include;
                                }
                            }
                        }
                    }

                    //Set sidebar
                    $session->set('sidebarExtra', '');

                    $sidebarExtra = '';
                    $alert = '';
                    //Show alerts
                    if ($highestAction == 'View Student Profile_fullEditAllNotes' || $highestAction == 'View Student Profile_full' || $highestAction == 'View Student Profile_fullNoNotes') {
                        $alert = $container->get(Alert::class)->getAlertBar($tawasulPersonID, ['wrap' => false, 'large' => true]);
                        $sidebarExtra .= '<div class="w-48 sm:w-64 h-10 mb-2">'.$alert.'</div>';
                    }
                    
                    $sidebarExtra .= Format::userPhoto($studentImage, 240);

                    //PERSONAL DATA MENU ITEMS
                     $sidebarExtra .= '<div class="column-no-break">';
                     $sidebarExtra .= '<h4>'.__('Personal').'</h4>';
                     $sidebarExtra .= "<ul class='moduleMenu'>";
                    $style = '';
                    if ($subpage == 'Overview') {
                        $style = "style='font-weight: bold'";
                    }
                     $sidebarExtra .= "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Overview'>".__('Overview').'</a></li>';
                    $style = '';
                    if ($subpage == 'Personal') {
                        $style = "style='font-weight: bold'";
                    }
                     $sidebarExtra .= "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Personal'>".__('Personal').'</a></li>';
                    $style = '';
                    if ($subpage == 'Family') {
                        $style = "style='font-weight: bold'";
                    }
                     $sidebarExtra .= "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Family'>".__('Family').'</a></li>';
                    $style = '';
                    if ($subpage == 'Emergency Contacts') {
                        $style = "style='font-weight: bold'";
                    }
                     $sidebarExtra .= "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Emergency Contacts'>".__('Emergency Contacts').'</a></li>';
                    $style = '';
                    if ($subpage == 'Medical') {
                        $style = "style='font-weight: bold'";
                    }
                    if (!isActionAccessible($guid, $connection2, '/modules/TawasulStudents/student_view_details.php', 'View Student Profile_my')) {
                     $sidebarExtra .= "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Medical'>".__('Medical').'</a></li>';
                    }

                    if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/firstAidRecord.php')) {
                        $style = '';
                        if ($subpage == 'First Aid') {
                            $style = "style='font-weight: bold'";
                        }
                        $sidebarExtra .= "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=First Aid'>".__('First Aid').'</a></li>';

                    }

                    if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/student_view_details_notes_add.php')) {
                        if ($enableStudentNotes == 'Y') {
                            $style = '';
                            if ($subpage == 'Notes') {
                                $style = "style='font-weight: bold'";
                            }
                             $sidebarExtra .= "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Notes'>".__('Notes').'</a></li>';
                        }
                    }
                     $sidebarExtra .= '</ul>';

                    //OTHER MENU ITEMS, DYANMICALLY ARRANGED TO MATCH CUSTOM TOP MENU
                    //Get all modules, with the categories

                        $dataMenu = array();
                        $sqlMenu = "SELECT tawasulModuleID, category, name FROM tawasulModule WHERE active='Y' ORDER BY category, name";
                        $resultMenu = $connection2->prepare($sqlMenu);
                        $resultMenu->execute($dataMenu);
                    $mainMenu = array();
                    while ($rowMenu = $resultMenu->fetch()) {
                        $mainMenu[$rowMenu['name']] = $rowMenu['category'];
                    }
                    $studentMenuCategory = [];
                    $studentMenuName = [];
                    $studentMenuLink = [];
                    $studentMenuCount = 0;

                    //Store items in an array
                    if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_view.php')) {
                        $style = '';
                        if ($subpage == 'Markbook') {
                            $style = "style='font-weight: bold'";
                        }
                        $studentMenuCategory[$studentMenuCount] = $mainMenu['Markbook'];
                        $studentMenuName[$studentMenuCount] = __('Markbook');
                        $studentMenuLink[$studentMenuCount] = "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Markbook'>".__('Markbook').'</a></li>';
                        ++$studentMenuCount;
                    }
                    if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/internalAssessment_view.php')) {
                        $style = '';
                        if ($subpage == 'Internal Assessment') {
                            $style = "style='font-weight: bold'";
                        }
                        $studentMenuCategory[$studentMenuCount] = $mainMenu['Formal Assessment'];
                        $studentMenuName[$studentMenuCount] = __('Formal Assessment');
                        $studentMenuLink[$studentMenuCount] = "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Internal%20Assessment'>".__('Internal Assessment').'</a></li>';
                        ++$studentMenuCount;
                    }
                    if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/externalAssessment_details.php') or isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/externalAssessment_view.php')) {
                        $style = '';
                        if ($subpage == 'External Assessment') {
                            $style = "style='font-weight: bold'";
                        }
                        $studentMenuCategory[$studentMenuCount] = $mainMenu['Formal Assessment'];
                        $studentMenuName[$studentMenuCount] = __('External Assessment');
                        $studentMenuLink[$studentMenuCount] = "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=External Assessment'>".__('External Assessment').'</a></li>';
                        ++$studentMenuCount;
                    }
                    if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/archive_byStudent_view.php')) {
                        $style = '';
                        if ($subpage == 'Reports') {
                            $style = "style='font-weight: bold'";
                        }
                        $studentMenuCategory[$studentMenuCount] = $mainMenu['Reports'];
                        $studentMenuName[$studentMenuCount] = __('Reports');
                        $studentMenuLink[$studentMenuCount] = "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Reports'>".__('Reports').'</a></li>';
                        ++$studentMenuCount;
                    }
                    if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/report_activityChoices_byStudent.php') || isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_view_myChildren.php') || isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_my.php')) {
                        $style = '';
                        if ($subpage == 'Activities') {
                            $style = "style='font-weight: bold'";
                        }
                        $studentMenuCategory[$studentMenuCount] = $mainMenu['Activities'];
                        $studentMenuName[$studentMenuCount] = __('Activities');
                        $studentMenuLink[$studentMenuCount] = "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Activities'>".__('Activities').'</a></li>';
                        ++$studentMenuCount;
                    }
                    if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_edit.php') or isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_view_full.php')) {
                        $style = '';
                        if ($subpage == 'Homework') {
                            $style = "style='font-weight: bold'";
                        }
                        $homeworkNamePlural = $settingGateway->getSettingByScope('Planner', 'homeworkNamePlural');
                        $studentMenuCategory[$studentMenuCount] = $mainMenu['Planner'];
                        $studentMenuName[$studentMenuCount] = __($homeworkNamePlural);
                        $studentMenuLink[$studentMenuCount] = "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Homework'>".__($homeworkNamePlural).'</a></li>';
                        ++$studentMenuCount;
                    }
                    if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/in_view.php')) {
                        $style = '';
                        if ($subpage == 'Individual Needs') {
                            $style = "style='font-weight: bold'";
                        }
                        $studentMenuCategory[$studentMenuCount] = $mainMenu['Individual Needs'];
                        $studentMenuName[$studentMenuCount] = __('Individual Needs');
                        $studentMenuLink[$studentMenuCount] = "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Individual Needs'>".__('Individual Needs').'</a></li>';
                        ++$studentMenuCount;
                    }
                    if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_browse.php')) {
                        $style = '';
                        if ($subpage == 'Library Borrowing') {
                            $style = "style='font-weight: bold'";
                        }
                        $studentMenuCategory[$studentMenuCount] = $mainMenu['Library'];
                        $studentMenuName[$studentMenuCount] = __('Library Borrowing');
                        $studentMenuLink[$studentMenuCount] = "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Library Borrowing'>".__('Library Borrowing').'</a></li>';
                        ++$studentMenuCount;
                    }
                    if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/tt_view.php')) {
                        $style = '';
                        if ($subpage == 'Timetable') {
                            $style = "style='font-weight: bold'";
                        }
                        $studentMenuCategory[$studentMenuCount] = $mainMenu['Timetable'];
                        $studentMenuName[$studentMenuCount] = __('Timetable');
                        $studentMenuLink[$studentMenuCount] = "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Timetable'>".__('Timetable').'</a></li>';
                        ++$studentMenuCount;
                    }if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/report_studentHistory.php')) {
                        $style = '';
                        if ($subpage == 'Attendance') {
                            $style = "style='font-weight: bold'";
                        }
                        $studentMenuCategory[$studentMenuCount] = $mainMenu['Attendance'];
                        $studentMenuName[$studentMenuCount] = __('Attendance');
                        $studentMenuLink[$studentMenuCount] = "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Attendance'>".__('Attendance').'</a></li>';
                        ++$studentMenuCount;
                    }
                    if (isActionAccessible($guid, $connection2, '/modules/TawasulBehaviour/behaviour_view.php')) {
                        $style = '';
                        if ($subpage == 'Behaviour') {
                            $style = "style='font-weight: bold'";
                        }
                        $studentMenuCategory[$studentMenuCount] = $mainMenu['Behaviour'];
                        $studentMenuName[$studentMenuCount] = __('Behaviour');
                        $studentMenuLink[$studentMenuCount] = "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search."&search=$search&allStudents=$allStudents&subpage=Behaviour'>".__('Behaviour').'</a></li>';
                        ++$studentMenuCount;
                    }


                    //Check for hooks, and slot them into array
                    $hooks = $hookGateway->selectHooksByType('Student Profile')->fetchGroupedUnique();

                    if (!empty($hooks)) {
                        $count = 0;
                        foreach ($hooks as $rowHook) {
                            if (empty($rowHook) || empty($rowHook['options'])) continue;

                            $options = unserialize($rowHook['options']);
                            $hookPermission = $hookGateway->getHookPermission($rowHook['tawasulHookID'], $session->get('tawasulRoleIDCurrent'), $options['sourceModuleName'] ?? '', $options['sourceModuleAction'] ?? '');

                            //Check for permission to hook

                            if (!empty($hookPermission)) {
                                $style = '';
                                if ($hook == $rowHook['name']) {
                                    $style = "style='font-weight: bold'";
                                }
                                $studentMenuCategory[$studentMenuCount] = $mainMenu[$options['sourceModuleName']];
                                $studentMenuName[$studentMenuCount] = __($rowHook['name']);
                                $studentMenuLink[$studentMenuCount] = "<li><a $style href='".$session->get('absoluteURL').'/index.php?q='.$_GET['q']."&tawasulPersonID=$tawasulPersonID&search=".$search.'&allStudents='.$allStudents.'&hook='.$rowHook['name'].'&module='.$options['sourceModuleName'].'&action='.$options['sourceModuleAction'].'&tawasulHookID='.$rowHook['tawasulHookID']."'>".__($rowHook['name']).'</a></li>';
                                ++$studentMenuCount;
                                ++$count;
                            }
                        }
                    }

                    //Menu ordering categories
                    $mainMenuCategoryOrder = $settingGateway->getSettingByScope('System', 'mainMenuCategoryOrder');
                    $orders = explode(',', $mainMenuCategoryOrder);

                    //Sort array
                    @array_multisort($studentMenuCategory, $studentMenuName, $studentMenuLink);

                    //Spit out array whilt sorting by $mainMenuCategoryOrder
                    if (count($studentMenuCategory) > 0) {
                        foreach ($orders as $order) {
                            //Check for entries
                            $countEntries = 0;
                            for ($i = 0; $i < count($studentMenuCategory); ++$i) {
                                if ($studentMenuCategory[$i] == $order) {
                                    $countEntries ++;
                                }
                            }

                            if ($countEntries > 0) {
                                 $sidebarExtra .= '<h4>'.__($order).'</h4>';
                                 $sidebarExtra .= "<ul class='moduleMenu'>";
                                for ($i = 0; $i < count($studentMenuCategory); ++$i) {
                                    if ($studentMenuCategory[$i] == $order) {
                                         $sidebarExtra .= $studentMenuLink[$i];
                                    }
                                }

                                 $sidebarExtra .= '</ul>';
                            }
                        }
                    }

                    $sidebarExtra .= '</div>';

                    $session->set('sidebarExtra', $sidebarExtra);
                }
            }
        }
    }
}
