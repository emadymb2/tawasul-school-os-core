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
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Tables\Prefab\ReportTable;
use Tos\Module\TawasulAttendance\AttendanceView;
use TawasulOS\Domain\Attendance\AttendanceLogPersonGateway;
use TawasulOS\Http\Url;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/report_studentsNotOnsite_byDate.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $settingGateway = $container->get(SettingGateway::class);
    $viewMode = $_REQUEST['format'] ?? '';
    $allStudents = $_GET['allStudents'] ?? 'N';
    $sort = !empty($_GET['sort']) ? $_GET['sort'] : 'surname';
    $tawasulYearGroupIDList = $_GET['tawasulYearGroupIDList'] ?? [];
    $currentDate = isset($_GET['currentDate']) ? Format::dateConvert($_GET['currentDate']) : date('Y-m-d');
    $countClassAsSchool = $settingGateway->getSettingByScope('Attendance', 'countClassAsSchool');

    if (empty($viewMode)) {
        $page->breadcrumbs->add(__('Students Not Onsite'));

        $form = Form::create('action', $session->get('absoluteURL') . '/index.php', 'get');

        $form->setFactory(DatabaseFormFactory::create($pdo));
        $form->setTitle(__('Choose Date'));
        $form->setClass('noIntBorder w-full');

        $form->addHiddenValue('q', "/modules/" . $session->get('module') . "/report_studentsNotOnsite_byDate.php");

        $row = $form->addRow();
            $row->addLabel('currentDate', __('Date'));
            $row->addDate('currentDate')->setValue(Format::date($currentDate))->required();

        $sortOptions = ['surname' => __('Surname'), 'preferredName' => __('Preferred Name'), 'formGroup' => __('Form Group')];
        $row = $form->addRow();
            $row->addLabel('sort', __('Sort By'));
            $row->addSelect('sort')->fromArray($sortOptions)->selected($sort)->required();

        $row = $form->addRow();
            $row->addLabel('allStudents', __('All Students'))->description(__('Include all students, even those where attendance has not yet been recorded.'));
            $row->addCheckbox('allStudents')->checked($allStudents)->setValue('Y');

        $row = $form->addRow();
        $row->addLabel('tawasulYearGroupIDList', __('Year Groups'))->description(__('Relevant student year groups'));
        if (!empty($tawasulYearGroupIDList)) {
            $values['tawasulYearGroupIDList'] = $tawasulYearGroupIDList;
            $row->addCheckboxYearGroup('tawasulYearGroupIDList')->addCheckAllNone()->loadFrom($values);
        } else {
            $row->addCheckboxYearGroup('tawasulYearGroupIDList')->addCheckAllNone()->checkAll();
        }

        $row = $form->addRow();
            $row->addFooter();
            $row->addSearchSubmit($session);

        echo $form->getOutput();
    }

    if (empty($currentDate)) {
        return;
    }

    $attendanceGateway = $container->get(AttendanceLogPersonGateway::class);
    $criteria = $attendanceGateway->newQueryCriteria()
        ->filterBy('yearGroup', implode(',', $tawasulYearGroupIDList));

    switch ($sort) {
        case 'preferredName':
            $criteria->sortBy(['tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulFormGroup.nameShort']); break;
        case 'formGroup':
            $criteria->sortBy(['tawasulFormGroup.nameShort', 'tawasulPerson.surname', 'tawasulPerson.preferredName']); break;
        default:
        case 'surname':
            $criteria->sortBy(['tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulFormGroup.nameShort']); break;
    }
    $criteria->fromPOST();

    $attendance = $attendanceGateway->queryStudentsNotOnsite($criteria, $session->get('tawasulSchoolYearID'), $currentDate, $allStudents, $countClassAsSchool);

    $students = $attendance->getColumn('tawasulPersonID');
    $attendanceConflictData = $attendanceGateway->selectNonAbsentAttendanceLogsByDate($students, $currentDate)->fetchKeyPair();
    $attendance->joinColumn('tawasulPersonID', 'conflicts', $attendanceConflictData);

    $table = ReportTable::createPaginated('attendanceReport', $criteria)->setViewMode($viewMode, $session);
    $table->setTitle(__('Report Data'));

    $table->addMetaData('blankSlate', __('All students are present.'));
    $table->addRowCountColumn($attendance->getPageFrom())->context('primary');

    $table->addColumn('formGroup', __('Form Group'))->context('primary')->width('10%');
    $table->addColumn('name', __('Name'))
        ->context('primary')
        ->sortable(['tawasulPerson.surname', 'tawasulPerson.preferredName'])
        ->format(function ($student) use ($currentDate) {
            $url = Url::fromModuleRoute('TawasulAttendance', 'attendance_take_byPerson.php')->withQueryParams(['tawasulPersonID' => $student['tawasulPersonID'], 'currentDate' => $currentDate]);
            return Format::link($url, Format::name('', $student['preferredName'], $student['surname'], 'Student', true, true));
        });
    $table->addColumn('status', __('Status'))
        ->context('primary')
        ->width('20%')
        ->format(function ($student) {
            $output = !empty($student['type']) ? __($student['type']) : Format::small(__('Not registered'));
            if (!empty($student['conflicts']) && !empty($student['type']) && $student['conflicts'] != $student['type'] && stripos($student['type'], 'Left') === false) {
                $output .= Format::tag(__('Conflict'), 'warning text-xxs ml-2', $student['conflicts']);
            }
            return $output;
        });
    $table->addColumn('reason', __('Reason'))->context('secondary');
    $table->addColumn('comment', __('Comment'))
        ->format(Format::using('truncate', 'comment'));

    echo $table->render($attendance);
}
