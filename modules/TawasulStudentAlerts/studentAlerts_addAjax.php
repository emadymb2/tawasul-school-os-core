<?php
/*
TawasulOS, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use TawasulOS\Services\Format;
use TawasulOS\Forms\FormFactory;
use TawasulOS\Domain\Timetable\CourseEnrolmentGateway;

require_once __DIR__ . '/../../tawasul.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulStudentAlerts/studentAlerts_add.php')) {
    // Access denied
    exit;
} else {
    $tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
    $tawasulCourseClassID = $_POST['tawasulCourseClassID'] ?? '';

    if (empty($tawasulCourseClassID)) exit;

    $students = $container->get(CourseEnrolmentGateway::class)->selectClassStudentEnrolment($tawasulCourseClassID)->fetchAll();

    echo $container->get(FormFactory::class)->createSelectPerson('tawasulPersonID')
        ->fromArray(Format::nameListArray($students, 'Student', true))
        ->placeholder()
        ->selected($tawasulPersonID)
        ->required()
        ->getOutput();
}
