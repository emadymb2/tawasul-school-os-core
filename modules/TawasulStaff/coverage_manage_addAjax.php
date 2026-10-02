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

use TawasulOS\Tables\DataTable;
use TawasulOS\Services\Format;
use TawasulOS\Domain\DataSet;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\Staff\SubstituteGateway;
use TawasulOS\Domain\School\SchoolYearSpecialDayGateway;

require_once __DIR__ . '/../../tawasul.php';

$request = [
    'dateStart' => $_POST['dateStart'] ?? '',
    'dateEnd'   => $_POST['dateEnd'] ?? '',
    'allDay'    => $_POST['allDay'] ?? 'N',
    'timeStart' => isset($_POST['timeStart']) ? $_POST['timeStart'].':00' : '',
    'timeEnd'   => isset($_POST['timeEnd']) ? $_POST['timeEnd'].':00' : '',
];

$tawasulPersonIDCoverage = $_POST['tawasulPersonIDCoverage'] ?? '';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/coverage_manage_add.php') == false) {
    die(Format::alert(__('Your request failed because you do not have access to this action.')));
} elseif (empty($request['dateStart']) || empty($request['dateEnd'])|| $tawasulPersonIDCoverage == 'Please select...') {
    die();
} else {
    // Proceed!
    $userGateway = $container->get(UserGateway::class);
    $substituteGateway = $container->get(SubstituteGateway::class);
    $specialDayGateway = $container->get(SchoolYearSpecialDayGateway::class);

    // DATA TABLE
    $substitute = $substituteGateway->selectBy(['tawasulPersonID'=> $tawasulPersonIDCoverage])->fetch();
    $person = $container->get(UserGateway::class)->getByID($tawasulPersonIDCoverage);



    $start = new DateTime(Format::dateConvert($request['dateStart']).' 00:00:00');
    $end = new DateTime(Format::dateConvert($request['dateEnd']).' 23:00:00');

    $dates = [];
    $dateRange = new DatePeriod($start, new DateInterval('P1D'), $end);
    foreach ($dateRange as $date) {
        if (!isSchoolOpen($guid, $date->format('Y-m-d'), $connection2)) continue;

        $dates[] = ['date' => $date->format('Y-m-d')];
    }

    if (empty($dates) || empty($person)) {
        die();
    }

    $unavailable = $substituteGateway->selectUnavailableDatesBySub($tawasulPersonIDCoverage, $start->format('Y-m-d'), $end->format('Y-m-d'))->fetchGrouped();

    // Check for special days
    $specialDays = $specialDayGateway->selectSpecialDaysByDateRange($start->format('Y-m-d'), $end->format('Y-m-d'))->fetchGroupedUnique();

    $datesList = new DataSet($dates);
    $datesList->transform(function (&$date) use (&$unavailable, &$request, &$specialDays, &$specialDayGateway, &$session) {
        $specialDay = $specialDays[$date['date']] ?? [];

        // Is this date unavailable: absent, already booked, or has an availability exception
        if (isset($unavailable[$date['date']])) {
            $times = $unavailable[$date['date']];

            foreach ($times as $time) {
                // Handle full day and partial day unavailability
                if ($time['allDay'] == 'Y'
                || ($time['allDay'] == 'N' && $request['allDay'] == 'Y')
                || ($time['allDay'] == 'N' && $request['allDay'] == 'N'
                    && $time['timeStart'] < $request['timeEnd']
                    && $time['timeEnd'] > $request['timeStart'])) {

                    // Free up teachers if their class is off timetable
                    if ($time['status'] == 'Teaching' && !empty($specialDay) && $specialDay['type'] == 'Off Timetable') {
                        $offTimetable = $specialDayGateway->getIsClassOffTimetableByDate($session->get('tawasulSchoolYearID'), $time['contextID'], $date['date']);
                        if ($offTimetable) continue;
                    }

                    $date['status'] = __($time['status'] ?? 'Not Available');
                }
            }
        }
    });

    $fullName = Format::name('', $person['preferredName'], $person['surname'], 'Staff', false, true);

    $table = DataTable::create('staffAbsenceDates');
    $table->setTitle(__('Availability'));
    $table->setDescription('<strong>'.$fullName.'</strong><br/><br/>'.($substitute['details'] ?? ''));
    $table->getRenderer()->addData('class', 'bulkActionForm');

    $table->modifyRows(function ($values, $row) {
        return $row->addClass('h-10');
    });

    $table->addColumn('dateLabel', __('Date'))
        ->format(Format::using('dateReadable', 'date'));

    $table->addColumn('status', __('Status'))
        ->format(function ($date) {
            return !empty($date['status']) ? Format::tag($date['status'], 'error') : '';
        });

    $table->addColumn('override', '')
        ->width('4%')
        ->format(function ($date) {
            return !empty($date['status']) ? Format::small(__('Override').'?') : '';
        });

    $table->addCheckboxColumn('requestDates', 'date')
        ->width('15%')
        ->checked(function ($date) {
            return empty($date['status']) ? $date['date'] : '';
        });

    echo $table->render($datesList);
}
