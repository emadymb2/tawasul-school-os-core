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

use TawasulOS\Data\Validator;
use TawasulOS\Services\Format;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Domain\FormGroups\FormGroupGateway;
use TawasulOS\Domain\Timetable\CourseEnrolmentGateway;
use TawasulOS\Domain\School\YearGroupGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulStudentEnrolmentID = $_POST['tawasulStudentEnrolmentID'] ?? '';
$search = $_GET['search'] ?? '';

if ($tawasulStudentEnrolmentID == '' or $tawasulSchoolYearID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/studentEnrolment_manage_edit.php&tawasulStudentEnrolmentID=$tawasulStudentEnrolmentID&tawasulSchoolYearID=$tawasulSchoolYearID&search=$search";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/studentEnrolment_manage_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    } else {
        //Proceed!
        //Check if person specified
        if ($tawasulStudentEnrolmentID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        } else {

            $customRequireFail = false;
            $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('Student Enrolment', [], $customRequireFail);

            if ($customRequireFail) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit;
            }
            
            try {
                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID);
                $sql = 'SELECT tawasulFormGroup.tawasulFormGroupID, tawasulYearGroup.tawasulYearGroupID,tawasulStudentEnrolmentID, tawasulPerson.tawasulPersonID, surname, preferredName, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.nameShort AS formGroup FROM tawasulPerson, tawasulStudentEnrolment, tawasulYearGroup, tawasulFormGroup WHERE (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) AND (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) AND (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) AND tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID ORDER BY surname, preferredName';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit;
            }

            if ($result->rowCount() != 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit;
            } else {
                $row = $result->fetch();

                // Fetch old record for file comparison
                try {
                    $dataOld = ['tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID];
                    $sqlOld = 'SELECT fields FROM tawasulStudentEnrolment WHERE tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID';
                    $resultOld = $connection2->prepare($sqlOld);
                    $resultOld->execute($dataOld);
                    $oldEnrolmentRecord = $resultOld->fetch();
                } catch (PDOException $e) {
                    $oldEnrolmentRecord = null;
                }

                $tawasulYearGroupID = $_POST['tawasulYearGroupID'] ?? '';
                $tawasulFormGroupID = $_POST['tawasulFormGroupID'] ?? '';
                $tawasulFormGroupIDOriginal = $_POST['tawasulFormGroupIDOriginal'] ?? 'N';
                $formGroupOriginalNameShort = $_POST['formGroupOriginalNameShort'] ?? '';
                $tawasulPersonID = $row['tawasulPersonID'];

                $formGroupTo = $container->get(FormGroupGateway::class)->getFormGroupByID($tawasulFormGroupID);
                $formGroupToName = $formGroupTo['nameShort'];

                $rollOrder = $_POST['rollOrder'] ?? '';
                if ($rollOrder == '') {
                    $rollOrder = null;
                }

                //Check unique inputs for uniquness
                try {
                    $data = array('tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID, 'rollOrder' => $rollOrder, 'tawasulFormGroupID' => $tawasulFormGroupID);
                    $sql = "SELECT * FROM tawasulStudentEnrolment WHERE rollOrder=:rollOrder AND tawasulFormGroupID=:tawasulFormGroupID AND NOT tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID AND NOT rollOrder=''";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit;
                }

                if ($result->rowCount() > 0) {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                    exit;
                } else {
                    //Write to database
                    try {
                        $data = array('tawasulYearGroupID' => $tawasulYearGroupID, 'tawasulFormGroupID' => $tawasulFormGroupID, 'rollOrder' => $rollOrder, 'fields' => $fields, 'tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID);
                        $sql = 'UPDATE tawasulStudentEnrolment SET tawasulYearGroupID=:tawasulYearGroupID, tawasulFormGroupID=:tawasulFormGroupID, rollOrder=:rollOrder, fields=:fields WHERE tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit;
                    }

                    // Manage custom field file uploads
                    if (!empty($fields)) {
                        $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('Student Enrolment', [], $fields, 'tawasulStudentEnrolment', $tawasulStudentEnrolmentID, $oldEnrolmentRecord['fields'] ?? null);
                    }
                    
                    $partialFail = false;

                    // Handle automatic course enrolment if enabled
                    $autoEnrolStudent = $_POST['autoEnrolStudent'] ?? 'N';
                    if ($autoEnrolStudent == 'Y') {
                        $courseEnrolmentGateway = $container->get(CourseEnrolmentGateway::class);

                        // Remove existing auto-enrolment: moving a student from one Form Group to another
                        $courseEnrolmentGateway->unenrolAutomaticCourseEnrolments($tawasulFormGroupIDOriginal, $tawasulStudentEnrolmentID);
                        
                        $partialFail &= !$pdo->getQuerySuccess();

                        // Update existing course enrolments for new Form Group
                        $courseEnrolmentGateway->updateAutomaticCourseEnrolments($tawasulFormGroupID, $tawasulStudentEnrolmentID);

                        $partialFail &= !$pdo->getQuerySuccess();

                        // Add course enrolments for new Form Group
                        $courseEnrolmentGateway->insertAutomaticCourseEnrolments($tawasulFormGroupID, $tawasulPersonID);

                        $partialFail &= !$pdo->getQuerySuccess();
                    }

                    // If form group is changed
                    if ($tawasulFormGroupID != $tawasulFormGroupIDOriginal) {
                        // Add student note
                        $data = array('title' => __('Change of Form Group'), 'note' => __('Student\'s form group was changed from {formGroupFrom} to {formGroupTo} on {date}', ['formGroupFrom' => $formGroupOriginalNameShort, 'formGroupTo' => $formGroupToName, 'date' => Format::date(date('Y-m-d'))]), 'tawasulPersonID' => $tawasulPersonID, 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'), 'timestamp' => date('Y-m-d H:i:s', time()));
                        $sql = 'INSERT INTO tawasulStudentNote SET title=:title, note=:note, tawasulPersonID=:tawasulPersonID, tawasulPersonIDCreator=:tawasulPersonIDCreator, timestamp=:timestamp';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);

                        if ($pdo->getQuerySuccess() == false) {
                            $partialFail = true;
                        }

                        // Create the notification body
                        $studentName = Format::name('', $row['preferredName'], $row['surname'], 'Student', false, true);
                        $notificationString = __('{student}\'s form group was changed from {formGroupFrom} to {formGroupTo} on {date}', [
                            'student'   => $studentName,
                            'formGroupFrom' => $formGroupOriginalNameShort,
                            'formGroupTo' => $formGroupToName,
                            'date' => Format::date(date('Y-m-d')),
                        ]);

                        // Raise a new notification event
                        $event = new NotificationEvent('Admissions', 'Student Form Group Changed');
                        $event->addScope('tawasulYearGroupID', $tawasulYearGroupID);
                        $event->setNotificationText($notificationString);
                        $event->setActionLink('/index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID='.$tawasulPersonID.'&search=&sort=&allStudents=on');

                        // Head of Year
                        $yearGroup = $container->get(YearGroupGateway::class)->getByID($tawasulYearGroupID);
                        $event->addRecipient($yearGroup['tawasulPersonIDHOY']);

                        // Form Tutor: original
                        $formGroup = $container->get(FormGroupGateway::class)->getByID($tawasulFormGroupIDOriginal);
                        $event->addRecipient($formGroup['tawasulPersonIDTutor']);
                        $event->addRecipient($formGroup['tawasulPersonIDTutor2']);
                        $event->addRecipient($formGroup['tawasulPersonIDTutor3']);

                        // Form Tutor: new
                        $formGroup = $container->get(FormGroupGateway::class)->getByID($tawasulFormGroupID);
                        $event->addRecipient($formGroup['tawasulPersonIDTutor']);
                        $event->addRecipient($formGroup['tawasulPersonIDTutor2']);
                        $event->addRecipient($formGroup['tawasulPersonIDTutor3']);

                        // Add event listeners to the notification sender
                        $event->sendNotifications($pdo, $session);
                    }

                    $URL .= $partialFail
                        ? '&return=warning1'
                        : '&return=success0';
                    header("Location: {$URL}");
                    exit;
                }
            }
        }
    }
}
