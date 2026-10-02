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

namespace TawasulOS\UI\Timetable;

use TawasulOS\Domain\School\DaysOfWeekGateway;
use TawasulOS\Domain\School\SchoolYearTermGateway;
use TawasulOS\Domain\School\SchoolYearSpecialDayGateway;
use TawasulOS\Domain\Timetable\TimetableGateway;
use TawasulOS\Domain\Timetable\TimetableDayGateway;
use TawasulOS\Domain\Timetable\TimetableColumnGateway;
use TawasulOS\UI\Timetable\Palette;

/**
 * Timetable UI
 *
 * @version  v29
 * @since    v29
 */
class Structure
{
    protected $daysOfWeekGateway;
    protected $specialDayGateway;
    protected $schoolYearTermGateway;
    protected $timetableGateway;
    protected $timetableDayGateway;
    protected $timetableColumnGateway;

    protected $weekdays;
    protected $columns;
    protected $timetables;
    protected $timetableDays;
    protected $specialDays;
    protected $tawasulTTID;

    protected $currentDate;
    protected $activeDay;
    protected $today;

    protected $dateRange;
    
    protected $timeStart;
    protected $timeEnd;

    protected $timeRangeStart;
    protected $timeRangeEnd;

    protected $timestampStart;
    protected $timestampEnd;

    protected $pixelRatio = 1.2;
    protected $palette;

    public function __construct(Palette $palette, DaysOfWeekGateway $daysOfWeekGateway, SchoolYearSpecialDayGateway $specialDayGateway, SchoolYearTermGateway $schoolYearTermGateway, TimetableGateway $timetableGateway, TimetableDayGateway $timetableDayGateway, TimetableColumnGateway $timetableColumnGateway)
    {
        $this->palette = $palette;
        $this->daysOfWeekGateway = $daysOfWeekGateway;
        $this->specialDayGateway = $specialDayGateway;
        $this->schoolYearTermGateway = $schoolYearTermGateway;
        $this->timetableGateway = $timetableGateway;
        $this->timetableDayGateway = $timetableDayGateway;
        $this->timetableColumnGateway = $timetableColumnGateway;
    }

    public function getToday()
    {
        return $this->today;
    }

    public function getCurrentTime()
    {
        return $this->today->format('G:i');
    }

    public function getCurrentDate()
    {
        return $this->currentDate;
    }

    public function getActiveDay()
    {
        return $this->activeDay;
    }

    public function getActiveTimetable()
    {
        return $this->tawasulTTID;
    }

    public function setDate($date)
    {
        $this->currentDate = \DateTimeImmutable::createFromFormat('Y-m-d', substr($date ?? date('Y-m-d'), 0, 10));
        $this->today = new \DateTimeImmutable('now');

        $this->weekdays = $this->loadWeekdays();
        $this->dateRange = $this->calculateDateRange();
        $timeRange = $this->getTimeRange();
    }

    public function setTimetable($tawasulSchoolYearID, $tawasulTTID)
    {
        $this->timetables = $this->loadTimetables($tawasulSchoolYearID, $tawasulTTID);
        $this->specialDays = $this->loadSpecialDays();
        $this->timetableDays = $this->loadTimetableDays($this->tawasulTTID);
        $this->columns = $this->loadColumns($this->tawasulTTID);

        return $this->tawasulTTID;
    }

    public function expandTimeRange($timeStart, $timeEnd)
    {
        if ($timeStart < $this->timeStart || empty($this->timeStart)) {
            $this->timeStart = $timeStart;
        }
        if ($timeEnd > $this->timeEnd || empty($this->timeEnd)) {
            $this->timeEnd = $timeEnd;
        }
    }

    public function getTimeRange() : \DatePeriod
    {
        $interval = new \DateInterval('PT1H');
        $this->timeRangeStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->getCurrentDate()->format('Y-m-d').' '.$this->timeStart);
        $this->timeRangeEnd = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->getCurrentDate()->format('Y-m-d').' '.$this->timeEnd);
        
        $timeRange = new \DatePeriod($this->timeRangeStart, $interval, $this->timeRangeEnd);
        
        return $timeRange;
    }

    public function getDateRange() : \DatePeriod
    {
        return $this->dateRange;
    }

    public function getStartDate() : string
    {
        return $this->dateRange->getStartDate()->format('Y-m-d');
    }

    public function getEndDate() : string
    {
        return $this->dateRange->getEndDate()->format('Y-m-d');
    }

    public function getStartTime() : string
    {
        return $this->timeStart;
    }

    public function getEndTime() : string
    {
        return $this->timeEnd;
    }

    public function getTimetables()
    {
        return $this->timetables;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getWeekdays()
    {
        return $this->weekdays;
    }

    public function getSpecialDays()
    {
        return $this->specialDays;
    }

    public function getSpecialDay(string $date)
    {
        return $this->specialDays[$date] ?? [];
    }

    public function getTimetableDay(string $date)
    {
        return $this->timetableDays[$date] ?? [];
    }

    public function getColumn(string $date)
    {
        return $this->columns[$date] ?? [];
    }

    public function getColors($color = null)
    {
        return $this->palette->getPalette($color);
    }

    public function daysInWeek()
    {
        return count($this->weekdays);
    }

    public function isCurrentWeek()
    {
        return $this->today->format('W') == $this->currentDate->format('W');
    }

    public function isSchoolClosed(string $date)
    {
        return !empty($this->specialDays[$date]) && $this->specialDays[$date]['type'] == 'School Closure';
    }

    public function isLayerVisible(string $layerType, string $date)
    {
        $column = $this->getColumn($date);

        if ($layerType == 'timetabled' && ($this->isSchoolClosed($date) || empty($column))) {
            return false;
        }

        return true;
    }

    public function minutesToPixels($minutes)
    {
        return round((float)$minutes * $this->pixelRatio);
    }

    public function timeToPixels($time)
    {
        if (empty($time)) return 0;

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->getCurrentDate()->format('Y-m-d').' '.$time);
        $diff = $date->diff($this->timeRangeStart);

        return $this->minutesToPixels(($diff->h * 60) + $diff->i);
    }

    public function timeDifference($time1, $time2)
    {
        if (empty($time1) || empty($time2)) return 0;

        if (strlen($time1) > 8) $time1 = substr($time1, -8, 8);
        if (strlen($time2) > 8) $time2 = substr($time2, -8, 8);

        $date1 = \DateTimeImmutable::createFromFormat('H:i:s', $time1 > $time2 ? $time1 : $time2);
        $date2 = \DateTimeImmutable::createFromFormat('H:i:s', $time1 > $time2 ? $time2 : $time1);

        $diff = $date1->diff($date2);

        return ($diff->h * 60) + $diff->i;
    }

    protected function loadWeekdays()
    {
        $weekdays = $this->daysOfWeekGateway->selectSchoolWeekdays()->fetchAll();
        $this->activeDay = $weekdays[0]['nameShort'] ?? $this->today->format('D');

        foreach ($weekdays as $weekday) {
            if ($this->today->format('D') == $weekday['nameShort']) $this->activeDay = $weekday;

            $this->expandTimeRange($weekday['schoolStart'], $weekday['schoolEnd']);
        }

        return $weekdays;
    }

    /**
     * Get special days and add school closures for dates outside of terms.
     *
     * @return array
     */
    protected function loadSpecialDays()
    {
        // Load special days and expand csv values into arrays
        $specialDays = $this->specialDayGateway->selectSpecialDaysByDateRange($this->getStartDate(), $this->getEndDate())->fetchGroupedUnique();

        // Add school closures for any date outside of a school term
        $termDates = $this->schoolYearTermGateway->getTermsDatesByDateRange($this->getStartDate(), $this->getEndDate());
        foreach ($this->getDateRange() as $dateObject) {
            $date = $dateObject->format('Y-m-d');
            
            if (empty($termDates) || $date < $termDates['firstDay'] || $date > $termDates['lastDay']) {
                $specialDays[$date] = [
                    'type' => 'School Closure',
                    'date' => $date
                ];
            }
        }

        return $specialDays;
    }

    protected function loadTimetables($tawasulSchoolYearID, $tawasulTTID)
    {   
        $timetables = $this->timetableGateway->selectActiveTimetables($tawasulSchoolYearID)->fetchKeyPair();
        $this->tawasulTTID = empty($tawasulTTID) || empty($timetables[$tawasulTTID]) ? key($timetables) : $tawasulTTID;

        return $timetables;
    }

    protected function loadTimetableDays($tawasulTTID)
    {   
        return $this->timetableDayGateway->selectTTDaysByDateRange($tawasulTTID, $this->getStartDate(), $this->getEndDate())->fetchGroupedUnique();
    }

    protected function loadColumns($tawasulTTID)
    {
        $columnList = $this->timetableColumnGateway->selectTTColumnsByDateRange($tawasulTTID, $this->getStartDate(), $this->getEndDate())->fetchAll();
        $columns = [];

        foreach ($columnList as $periodData) {
            $period = (new TimetableItem($periodData['date']))->loadData($periodData);

            // Constrain column rows based on school closures and timing changes
            if ($specialDay = $this->getSpecialDay($period->date)) {
                if ($specialDay['type'] == 'School Closure') continue;
                $period->constrainTiming($specialDay['schoolOpen'] ?? '', $specialDay['schoolClose'] ?? '');
            }

            if ($period->isActive()) {
                $this->expandTimeRange($period->timeStart, $period->timeEnd);
                $period->set('duration', $this->timeDifference($period->timeStart, $period->timeEnd));

                $columns[$period->date][$period->subtitle] = $period;
            }
        }

        return $columns;
    }

    protected function calculateDateRange()
    {
        $this->timestampStart = strtotime($this->currentDate->format('Y-m-d'));
        $firstSchoolDay = $this->weekdays[0]['nameShort'] ?? '';

        if ($this->currentDate->format('D') == 'Sun' && $firstSchoolDay != 'Sun' ) {
            $this->timestampStart += 86400;
        } else {
            for ($i = 0; $i < 7; $i++) {
                if (date('D', $this->timestampStart) == $firstSchoolDay) {
                    break;
                }
                $this->timestampStart -= 86400;
            }
        }

        $index = 0;
        for ($i = 0; $i < 7; $i++) {
            $timestamp = $this->timestampStart + (86400 * $i);
            $weekday = $this->weekdays[$index] ?? [];

            if (empty($weekday)) continue;

            if (date('D', $timestamp) == $weekday['nameShort']) {
                $this->weekdays[$index]['date'] = date('Y-m-d', $timestamp);
                $this->timestampEnd = $timestamp + 86399;
                $index++;
            }
        }

        return new \DatePeriod(
            (new \DateTime(date('Y-m-d H:i:s', $this->timestampStart))),
            new \DateInterval('P1D'),
            (new \DateTime(date('Y-m-d H:i:s', $this->timestampEnd)))
        );
    }

    protected function adjustColor($hexCode, $adjustPercent) {
        $hexCode = ltrim($hexCode, '#');
    
        if (strlen($hexCode) == 3) {
            $hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
        }
    
        $hexCode = array_map('hexdec', str_split($hexCode, 2));
    
        foreach ($hexCode as & $color) {
            $adjustableLimit = $adjustPercent < 0 ? $color : 255 - $color;
            $adjustAmount = ceil($adjustableLimit * $adjustPercent);
    
            $color = str_pad(dechex($color + $adjustAmount), 2, '0', STR_PAD_LEFT);
        }
    
        return '#' . implode($hexCode);
    }
}
