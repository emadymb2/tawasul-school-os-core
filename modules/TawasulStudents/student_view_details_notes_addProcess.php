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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Services\Format;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\School\YearGroupGateway;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\FormGroups\FormGroupGateway;
use TawasulOS\Domain\Timetable\CourseEnrolmentGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\Students\StudentNoteGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['note' => 'HTML']);

$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
$subpage = $_GET['subpage'] ?? '';
$allStudents = $_GET['allStudents'] ?? '';
$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulStudents/student_view_details_notes_add.php&tawasulPersonID=$tawasulPersonID&search=".$_GET['search']."&subpage=$subpage&category=".$_GET['category']."&allStudents=$allStudents";

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/student_view_details_notes_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $settingGateway = $container->get(SettingGateway::class);
    $enableStudentNotes = $settingGateway->getSettingByScope('Students', 'enableStudentNotes');
    $noteCreationNotification = $settingGateway->getSettingByScope('Students', 'noteCreationNotification');
    $noteGateway = $container->get(StudentNoteGateway::class);

    if ($enableStudentNotes != 'Y') {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    } 

    if ($tawasulPersonID == '' or $subpage == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } 
    //Check for existence of student
    $student = $container->get(StudentGateway::class)->selectActiveStudentByPerson($session->get('tawasulSchoolYearID'), $tawasulPersonID, false)->fetch();

    if (empty($student)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $title = $_POST['title'] ?? '';
    $tawasulStudentNoteCategoryID = $_POST['tawasulStudentNoteCategoryID'] ?? null;
    $note = $_POST['note'] ?? '';

    if (empty($title) || empty($note)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
    
    //Write to database
    $tawasulStudentNoteID = $noteGateway->insert([
        'tawasulStudentNoteCategoryID' => $tawasulStudentNoteCategoryID,
        'title' => $title,
        'note' => $note,
        'tawasulPersonID' => $tawasulPersonID,
        'tawasulPersonIDCreator' => $session->get('tawasulPersonID'),
        'timestamp' => date('Y-m-d H:i:s', time()),
    ]);

    if (!$tawasulStudentNoteID) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Attempt to issue alerts form tutor(s) and teacher(s) according to settings
    if ($student['status'] == 'Full') {
        
        // Raise a new notification event
        $event = new NotificationEvent('Students', 'Student Notes');

        $staffName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);
        $studentName = Format::name('', $student['preferredName'], $student['surname'], 'Student', false);

        $event->setNotificationText(sprintf(__('%1$s has added a student note ("%2$s") about %3$s.'), $staffName, $title, $studentName));
        $event->setActionLink("/index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID=$tawasulPersonID&search=".$_GET['search']."&subpage=$subpage&category=".$_GET['category']);

        $event->addScope('tawasulPersonIDStudent', $tawasulPersonID);
        $event->addScope('tawasulYearGroupID', $student['tawasulYearGroupID']);

        if ($noteCreationNotification == 'Tutors' || $noteCreationNotification == 'Tutors & Teachers') {
            // Add form group tutors
            $tutors = $container->get(FormGroupGateway::class)->selectTutorsByStudent($session->get('tawasulSchoolYearID'), $tawasulPersonID)->fetchAll();
            foreach ($tutors as $tutor) {
                $event->addRecipient($tutor['tawasulPersonID']);
            }

            // Add the HOY if there is one
            $yearGroup = $container->get(YearGroupGateway::class)->getByID($student['tawasulYearGroupID']);
            if (!empty($yearGroup['tawasulPersonIDHOY'])) {
                $event->addRecipient($yearGroup['tawasulPersonIDHOY']);
            }

        }
        if ($noteCreationNotification == 'Tutors & Teachers') {
            $teachers = $container->get(CourseEnrolmentGateway::class)->selectClassTeachersByStudent($session->get('tawasulSchoolYearID'), $tawasulPersonID)->fetchAll();
            foreach ($teachers as $teacher) {
                $event->addRecipient($teacher['tawasulPersonID']);
            }
        }

        // Send notifications
        $event->sendNotifications($pdo, $session);
    }

    $URL .= '&return=success0';
    header("Location: {$URL}&editID={$tawasulStudentNoteID}");
}
