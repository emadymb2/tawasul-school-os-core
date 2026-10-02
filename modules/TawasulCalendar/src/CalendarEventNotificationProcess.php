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

namespace Tos\Module\TawasulCalendar;

use TawasulOS\View\View;
use TawasulOS\Services\Format;
use TawasulOS\Contracts\Comms\Mailer;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\Staff\StaffGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\School\YearGroupGateway;
use TawasulOS\Domain\FormGroups\FormGroupGateway;
use TawasulOS\Domain\Calendar\CalendarEventGateway;
use TawasulOS\Domain\Timetable\CourseEnrolmentGateway;
use TawasulOS\Domain\IndividualNeeds\INAssistantGateway;
use TawasulOS\Domain\Calendar\CalendarEventPersonGateway;
use TawasulOS\Domain\Timetable\TimetableDayDateGateway;
use TawasulOS\Domain\Attendance\AttendanceLogPersonGateway;
use TawasulOS\Services\BackgroundProcess;

/**
 * CalendarEventNotificationProcess
 *
 * @version v30
 * @since   v30
 */
class CalendarEventNotificationProcess extends BackgroundProcess
{
    protected $view;
    protected $mail;
    protected $userGateway;
    protected $staffGateway;
    protected $studentGateway;
    protected $calendarEventGateway;
    protected $calendarEventPersonGateway;
    protected $timetableDayDateGateway;
    protected $attendanceLogGateway;

    public function __construct(
        View $view,
        Mailer $mail,
        UserGateway $userGateway,
        StaffGateway $staffGateway,
        StudentGateway $studentGateway,
        CalendarEventGateway $calendarEventGateway,
        CalendarEventPersonGateway $calendarEventPersonGateway,
        TimetableDayDateGateway $timetableDayDateGateway,
        AttendanceLogPersonGateway $attendanceLogGateway,
    ) {
        $this->view = $view;
        $this->mail = $mail;
        $this->userGateway = $userGateway;
        $this->staffGateway = $staffGateway;
        $this->studentGateway = $studentGateway;
        $this->calendarEventGateway = $calendarEventGateway;
        $this->calendarEventPersonGateway = $calendarEventPersonGateway;
        $this->timetableDayDateGateway = $timetableDayDateGateway;
        $this->attendanceLogGateway = $attendanceLogGateway;
    }

    public function runNotifyStaff($tawasulCalendarEventID, $subject, $notes, $notifyGroups, $allStaff, $notificationList, $tawasulPersonIDSender, $tawasulSchoolYearID, $organisationEmail)
    {
        $staff = [];
        $staffContexts = [];
        $formGroups = [];

        $event = $this->calendarEventGateway->getByID($tawasulCalendarEventID);

        // Get all Attendees 
        $criteria = $this->calendarEventPersonGateway->newQueryCriteria()
            ->sortBy(['yearGroupSequence', 'formGroup', 'surname', 'preferredName', 'category']);
        $students = $this->calendarEventPersonGateway->queryEventAttendees($criteria, $tawasulCalendarEventID)->toArray();

        // Query all attendance logs for future absence records on the event date and time
        $futureAbsences = $event['allDay'] == 'Y'
            ? $this->attendanceLogGateway->selectFutureAttendanceLogsByDate($event['dateStart'], $event['dateEnd'])->fetchGroupedUnique()
            : $this->attendanceLogGateway->selectFutureAttendanceLogsByDateAndTime($event['dateStart'], $event['dateEnd'], $event['timeStart'], $event['timeEnd'])->fetchGroupedUnique();
        
        // Get timetable details for student participants, to cross-check for student lists in emails
        foreach ($students as $index => $student) {
            if ($student['roleCategory'] != 'Student') continue;
            if (!empty($student['formGroup'])) $formGroups[] = $student['formGroup'];

            $periods = $this->timetableDayDateGateway->selectTimetablePeriodsByPersonAndDate($tawasulSchoolYearID, $student['tawasulPersonID'], $event['dateStart'], $event['dateEnd'], $event['timeStart'], $event['timeEnd'], true)->fetchAll();
            $periods = array_map(function ($item) {
                $item['teacherIDs'] = explode(',',$item['teacherIDs']);
                return $item;
            }, $periods);

            $students[$index]['timetable'] = $periods;
            $students[$index]['attendance'] = $futureAbsences[$student['tawasulPersonID']] ?? [];
            $students[$index]['staff'] = $this->studentGateway->selectAllRelatedUsersByStudent($tawasulSchoolYearID, $student['tawasulYearGroupID'], $student['tawasulFormGroupID'], $student['tawasulPersonID'], true)->fetchAll();
        }

        // All Staff
        if ($allStaff == 'Y') {
            $criteria = $this->staffGateway->newQueryCriteria();
            $results = $this->staffGateway->queryAllStaff($criteria);

            foreach ($results as $result) {
                $staff[] = $result['tawasulPersonID'];
            }    
        } else {
            foreach ($students as $student) {
                foreach ($student['staff'] as $person) {
                    $tawasulPersonIDTeacher = str_pad($person['tawasulPersonID'], 10, '0', STR_PAD_LEFT);

                    // Head of Year
                    if (in_array('HOY', $notifyGroups) && $person['type'] == 'Head of Year') {
                        $staff[] = $tawasulPersonIDTeacher;
                        $staffContexts[$tawasulPersonIDTeacher][] = __('Head of Year');
                    }

                    // Form Tutors
                    if (in_array('tutors', $notifyGroups) && $person['type'] == 'Form Tutor') {
                        $staff[] = $tawasulPersonIDTeacher;
                        $staffContexts[$tawasulPersonIDTeacher][] = __('Form Tutor');
                    }

                    // Teachers (all)
                    if (in_array('teachersAll', $notifyGroups) && $person['type'] == 'Class Teacher') {
                        $staff[] = $tawasulPersonIDTeacher;
                        $staffContexts[$tawasulPersonIDTeacher][] = __('Class Teacher');
                    }

                    // Educational Assistants
                    if (in_array('INAssistant', $notifyGroups) && ($person['type'] == 'Educational Assistant' || $person['type'] == 'IN Assistant')) {
                        $staff[] = $tawasulPersonIDTeacher;
                        $staffContexts[$tawasulPersonIDTeacher][] = __('Educational Assistant');
                    }
                }

                // Teachers - Affected
                if (in_array('teachersAffected', $notifyGroups) && !empty($student['timetable'])) {
                    foreach ($student['timetable'] as $period) {
                        foreach($period['teacherIDs'] as $tawasulPersonIDTeacher) {
                            $staff[] = $tawasulPersonIDTeacher;
                            $staffContexts[$tawasulPersonIDTeacher][] = __('Class Teacher');
                        }
                    }
                }
            }

            // Staff Participants
            if (in_array('participants', $notifyGroups)) {
                $participants = $this->calendarEventPersonGateway->queryAllEventParticipants($criteria, $tawasulCalendarEventID)->toArray();
                foreach ($participants as $participant) {
                    if ($participant['roleCategory'] != 'Staff') continue;
                    $staff[] = $participant['tawasulPersonID'];
                }
            }
        
            // Notify Additional People
            if (!empty($notificationList)) {
                $staff = array_merge($staff, $notificationList);
            }
        }

        $staffPersonIDs = isset($staff) ? array_values(array_filter(array_unique($staff))) : [];

        // Ensure the sender receives a copy
        $staffPersonIDs[] = $tawasulPersonIDSender;
        $staffContexts[$tawasulPersonIDSender][] = __('Sender');

        $staffDetails = $this->userGateway->selectNotificationDetailsByPerson($staffPersonIDs)->fetchAll();

        $this->mail->SMTPKeepAlive = true;

        $sender = $this->userGateway->getByID($tawasulPersonIDSender, ['tawasulPersonID', 'title', 'preferredName', 'surname', 'email']);
        $replyTo = $sender['email'];
        $replyToName = Format::name($sender['title'], $sender['preferredName'], $sender['surname'], 'Staff');
        $sendReport = ['emailSent' => 0, 'emailFailed' => 0, 'emailErrors' => ''];

        foreach ($staffDetails as $staffDetail) {
            if (empty($staffDetail['email'])) continue;

            $tawasulPersonIDTeacher = $staffDetail['tawasulPersonID'];

            // Get the relevant students of this staff
            $relevantStudents = $affectedStudents = $attendanceStudents = 0;
            foreach ($students as $index => $student) {
                $students[$index]['context'] = '';
                $students[$index]['affected'] = [];
                $students[$index]['absence'] = '';


                // Add attendance details for future absence
                if (!empty($student['attendance'])) {
                    $students[$index]['absence'] = $student['attendance']['type'] ?? '';
                    $attendanceStudents++;
                }

                // Add details of affected classes
                if (!empty($student['timetable']) && is_array($student['timetable'])) {
                    foreach ($student['timetable'] as $period) {
                        if (in_array($tawasulPersonIDTeacher, $period['teacherIDs'])) {
                            $students[$index]['affected'][] = Format::courseClassName($period['courseName'], $period['className']).' - '.$period['periodNameShort'];
                            $affectedStudents++;
                        }
                    }
                }

                // Check for relevant contexts for this student 
                $contexts = [];
                foreach ($student['staff'] as $person) {
                    $tawasulPersonID = str_pad($person['tawasulPersonID'], 10, '0', STR_PAD_LEFT);
                    if ($tawasulPersonID == $tawasulPersonIDTeacher) {
                        if ($person['type'] == 'Class Teacher') {
                            $affectedStudents++;
                        } else {
                            $contexts[] = __($person['type']);
                        }
                    }
                }

                // Get all the contexts for this student-teacher pair
                if (!empty($contexts)) {
                    $students[$index]['context'] = implode(', ', array_unique($contexts));
                    $relevantStudents++;
                }
            }

            $buttonURL = "index.php?q=/modules/TawasulCalendar/calendar_event_view.php&tawasulCalendarEventID=".$tawasulCalendarEventID;
            $subject = !empty($subject) ? $subject : __('Event').': '. $event['name'] . ($event['allDay'] != 'Y' ? ', ' .Format::dateRangeReadable($event['dateStart'], $event['dateEnd']) : '');
        
            // Generate content from template
            $content = $this->view->fetchFromTemplate('calendarEvents.twig.html', [
                'students'   => $students,
                'sender'     => $sender,
                'allStaff'   => $allStaff,
                'contexts'   => !empty($staffContexts[$tawasulPersonIDTeacher]) ? implode(', ', array_unique($staffContexts[$tawasulPersonIDTeacher])) : '',
                'relevant'   => $relevantStudents,
                'affected'   => $affectedStudents,
                'attendance' => $attendanceStudents,
                'formGroups' => count($formGroups),
                'event'      => $event ?? [],
                'notes'      => $notes ?? '',
            ]);


            $this->mail->AddReplyTo($replyTo ?? $organisationEmail, $replyToName ?? '');
            $this->mail->AddAddress($staffDetail['email'], $staffDetail['surname'].', '.$staffDetail['preferredName']);

            $this->mail->setDefaultSender($subject);
            $this->mail->renderBody('mail/message.twig.html', [
                'title'  => $subject,
                'body'   => $content,
                'button' => [
                    'url'  => $buttonURL,
                    'text' => __('View Details'),
                ],
            ]);

            // Send
            if ($this->mail->Send()) {
                $sendReport['emailSent']++;
            } else {
                $sendReport['emailFailed']++;
                $sendReport['emailErrors'] .= sprintf(__('An error (%1$s) occurred sending an email to %2$s.'), 'email send failed', $staffDetail['preferredName'].' '.$staffDetail['surname']).'<br/>';
            }

            $this->mail->ClearAllRecipients();
            $this->mail->ClearAddresses();
            $this->mail->clearReplyTos();
        }
        
        $reportSubject = __('Email Report for Event: ').$event['name'];
        $reportBody  = '<strong>'.__('Summary').':</strong><br/>';
        $reportBody .= sprintf(__('Total Emails Sent: %1$s'), $sendReport['emailSent'] + $sendReport['emailFailed']) . '<br/>';
        $reportBody .= sprintf(__('Emails Sent: %1$s'), $sendReport['emailSent']) . '<br/>';
        $reportBody .= sprintf(__('Emails Failed: %1$s'), $sendReport['emailFailed']) . '<br/>';
        
        if (!empty($sendReport['emailErrors'])) {
            $reportBody .= '<br/><strong>'.__('Errors').':</strong><br/>';
            $reportBody .= $sendReport['emailErrors'];
        }

        $this->mail->AddAddress($sender['email'], $sender['surname'].', '.$sender['preferredName']);
        $this->mail->setDefaultSender($reportSubject);
        $this->mail->renderBody('mail/message.twig.html', [
            'title'  => __('Email Report'),
            'body'   => $reportBody,
        ]);

        $this->mail->Send();

        // Close SMTP connection
        $this->mail->smtpClose();

        return $sendReport['emailFailed'] == 0;
    }
}
