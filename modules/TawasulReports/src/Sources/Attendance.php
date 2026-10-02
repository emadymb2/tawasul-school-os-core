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

namespace Tos\Module\TawasulReports\Sources;

use DatePeriod;
use DateInterval;
use DateTimeImmutable;
use Tos\Module\TawasulReports\DataSource;

class Attendance extends DataSource
{
    private static $schoolYearTerms;
    private static $daysOfWeek;
    private static $schoolClosures;
    private static $offTimetable;

    public function getSchema()
    {
        return [
            'total' => ['numberBetween', 150, 200],
            'present' => ['numberBetween', 100, 150],
            'absent' => ['numberBetween', 1, 25],
            'partial'   => ['numberBetween', 1, 25],
            'late'   => ['numberBetween', 1, 25],
            'left'   => ['numberBetween', 1, 25],
        ];
    }

    public function getData($ids = [])
    {
        if (empty(static::$schoolYearTerms)) {
            $data = ['tawasulReportID' => $ids['tawasulReportID']];
            $sql = "SELECT tawasulSchoolYear.tawasulSchoolYearID, tawasulSchoolYearTerm.firstDay, tawasulSchoolYearTerm.lastDay
                    FROM tawasulReport 
                    JOIN tawasulSchoolYear ON (tawasulSchoolYear.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                    JOIN tawasulSchoolYearTerm ON (tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                    WHERE tawasulReport.tawasulReportID=:tawasulReportID";
            static::$schoolYearTerms = $this->db()->select($sql, $data)->fetchAll();
        }

        if (empty(static::$daysOfWeek)) {
            $sql = "SELECT nameShort, name FROM tawasulDaysOfWeek where schoolDay='Y'";
            static::$daysOfWeek = $this->db()->select($sql)->fetchKeyPair();
        }

        if (empty(static::$schoolClosures)) {
            $data = ['tawasulReportID' => $ids['tawasulReportID']];
            $sql = "SELECT tawasulSchoolYearSpecialDay.date, tawasulSchoolYearSpecialDay.name 
                    FROM tawasulReport 
                    JOIN tawasulSchoolYearTerm ON (tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                    JOIN tawasulSchoolYearSpecialDay ON (tawasulSchoolYearTerm.tawasulSchoolYearTermID=tawasulSchoolYearSpecialDay.tawasulSchoolYearTermID)
                    WHERE tawasulReport.tawasulReportID=:tawasulReportID AND tawasulSchoolYearSpecialDay.type='School Closure'
                    ORDER BY date";
            static::$schoolClosures = $this->db()->select($sql, $data)->fetchKeyPair();
        }

        if (empty(static::$offTimetable)) {
            $data = ['tawasulReportID' => $ids['tawasulReportID']];
            $sql = "SELECT tawasulSchoolYearSpecialDay.date, tawasulSchoolYearSpecialDay.name
                    FROM tawasulReport 
                    JOIN tawasulSchoolYearTerm ON (tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                    JOIN tawasulSchoolYearSpecialDay ON (tawasulSchoolYearTerm.tawasulSchoolYearTermID=tawasulSchoolYearSpecialDay.tawasulSchoolYearTermID)
                    WHERE tawasulReport.tawasulReportID=:tawasulReportID AND tawasulSchoolYearSpecialDay.type='Off Timetable'
                    ORDER BY date";
            static::$offTimetable = $this->db()->select($sql, $data)->fetchKeyPair();
        }

        $data = [
            'tawasulStudentEnrolmentID' => $ids['tawasulStudentEnrolmentID'],
            'tawasulReportID'       => $ids['tawasulReportID']
        ];
        $sql = "SELECT tawasulAttendanceLogPerson.date, tawasulReport.tawasulReportID, tawasulAttendanceLogPerson.tawasulCourseClassID, tawasulAttendanceLogPerson.date, tawasulAttendanceLogPerson.timestampTaken, tawasulAttendanceLogPerson.type, tawasulAttendanceLogPerson.context, tawasulAttendanceCode.scope, tawasulAttendanceCode.direction
                FROM tawasulReport
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                JOIN tawasulSchoolYear ON (tawasulSchoolYear.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                JOIN tawasulAttendanceLogPerson ON (tawasulAttendanceLogPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulAttendanceCode ON (tawasulAttendanceCode.tawasulAttendanceCodeID=tawasulAttendanceLogPerson.tawasulAttendanceCodeID)
                WHERE tawasulReport.tawasulReportID=:tawasulReportID
                AND tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID
                AND tawasulAttendanceLogPerson.date>=tawasulSchoolYear.firstDay
                AND tawasulAttendanceLogPerson.date<=CURDATE()
                AND tawasulAttendanceLogPerson.context <> 'Class'
                GROUP BY tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID
                ORDER BY tawasulAttendanceLogPerson.date, tawasulAttendanceLogPerson.timestampTaken";

        $values = $this->db()->select($sql, $data)->fetchGrouped();

        $attendance = ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'left' => 0];

        foreach (static::$schoolYearTerms as $term) {
            $dateRange = new DatePeriod(
                new DateTimeImmutable($term['firstDay']),
                new DateInterval('P1D'),
                (new DateTimeImmutable($term['lastDay']))->modify('+1 day')
            );

            foreach ($dateRange as $date) {
                if ($date->format('Y-m-d') > date('Y-m-d')) continue;

                if (!isset(static::$daysOfWeek[$date->format('D')])) continue;

                if (isset(static::$schoolClosures[$date->format('Y-m-d')])) continue;

                $attendance['total']++;

                $logs = $values[$date->format('Y-m-d')] ?? [];
                $offTimetable = static::$offTimetable[$date->format('Y-m-d')] ?? [];

                $endOfDay = end($logs);

                // Handle off-timetable days where attendance is not taken
                if (empty($endOfDay) && !empty($offTimetable)) {
                    $endOfDay = [
                        'direction' => 'In',
                        'scope'     => 'Offsite',
                    ];
                }

                if (empty($endOfDay)) continue;

                if ($endOfDay['direction'] == 'Out' && $endOfDay['scope'] == 'Offsite') {
                    $attendance['absent']++;
                } elseif ($endOfDay['scope'] == 'Onsite - Late' || $endOfDay['scope'] == 'Offsite - Late') {
                    $attendance['late']++;
                } elseif ($endOfDay['scope'] == 'Offsite - Left') {
                    $attendance['left']++;
                } elseif ($endOfDay['direction'] == 'In' || $endOfDay['scope'] == 'Onsite') {
                    $attendance['present']++;
                }
            }
        }

        $attendance['partial'] = $attendance['late'] + $attendance['left'];
        
        return $attendance;
    }
}
