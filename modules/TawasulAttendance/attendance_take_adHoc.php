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
use TawasulOS\Domain\Messenger\GroupGateway;
use TawasulOS\Domain\Students\StudentGateway;
use Tos\Module\TawasulAttendance\AttendanceView;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Attendance\AttendanceLogPersonGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';
require_once __DIR__ . '/src/AttendanceView.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/attendance_take_adHoc.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Take Ad Hoc Attendance'));
    $page->return->addReturns(['error3' => __('Your request failed because the specified date is in the future, or is not a school day.')]);


    $today = date('Y-m-d');
    $currentDate = isset($_GET['currentDate'])? Format::dateConvert($_GET['currentDate']) : $today;
    $target = $_GET['target'] ?? '';
    $tawasulActivityID = $_GET['tawasulActivityID'] ?? '';
    $tawasulGroupID = $_GET['tawasulGroupID'] ?? '';
    $tawasulPersonIDList = $_GET['tawasulPersonIDList'] ?? [];

    // FORM
    $form = Form::create('filter', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setTitle(__('Choose Students'));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/TawasulAttendance/attendance_take_adHoc.php');

    $targetOptions = [
        'Messenger'    => __('Messenger Group'),
        'Activity' => __('Activity Enrolment'),
        'Select'   => __('Select Students'),
    ];
    $row = $form->addRow();
        $row->addLabel('target', __('Target'));
        $row->addSelect('target')->fromArray($targetOptions)->required()->selected($target)->placeholder();

    $form->toggleVisibilityByClass('targetActivity')->onSelect('target')->when('Activity');
    $form->toggleVisibilityByClass('targetMessenger')->onSelect('target')->when('Messenger');
    $form->toggleVisibilityByClass('targetSelect')->onSelect('target')->when('Select');

    // Activity
    $activities = $container->get(ActivityGateway::class)->selectActivitiesBySchoolYear($session->get('tawasulSchoolYearID'))->fetchKeyPair();
    $row = $form->addRow()->addClass('targetActivity');
        $row->addLabel('tawasulActivityID', __('Activity'));
        $row->addSelect('tawasulActivityID')->fromArray($activities)->selected($tawasulActivityID)->required()->placeholder();

    // Messenger Groups
    $groups = $container->get(GroupGateway::class)->selectGroupsBySchoolYear($session->get('tawasulSchoolYearID'))->fetchKeyPair();
    $row = $form->addRow()->addClass('targetMessenger');
        $row->addLabel('tawasulGroupID', __('Messenger Group'));
        $row->addSelect('tawasulGroupID')->fromArray($groups)->selected($tawasulGroupID)->required()->placeholder();

    // Select Students
    $studentGateway = $container->get(StudentGateway::class);
    $studentCriteria = $studentGateway->newQueryCriteria()
        ->sortBy(['surname', 'preferredName']);

    $studentList = $studentGateway->queryStudentsBySchoolYear($studentCriteria, $session->get('tawasulSchoolYearID'));
    $studentList = array_reduce($studentList->toArray(), function ($group, $student) use ($tawasulPersonIDList) {
        $list = in_array($student['tawasulPersonID'], $tawasulPersonIDList) ? 'destination' : 'source';
        $group['students'][$list][$student['tawasulPersonID']] = Format::name($student['title'], $student['preferredName'], $student['surname'], 'Student', true) . ' - ' . $student['formGroup']; 
        $group['form'][$student['tawasulPersonID']] = $student['formGroup'];
        return $group;
    });

    $col = $form->addRow()->addClass('targetSelect')->addColumn();
        $col->addLabel('tawasulPersonIDList', __('Students'));
        $select = $col->addMultiSelect('tawasulPersonIDList')->isRequired();
        $select->addSortableAttribute(__('Form Group'), $studentList['form']);
        $select->source()->fromArray($studentList['students']['source'] ?? []);
        $select->destination()->fromArray($studentList['students']['destination'] ?? []);

    $row = $form->addRow();
        $row->addLabel('currentDate', __('Date'));
        $row->addDate('currentDate')->required()->setValue(Format::date($currentDate));

    $row = $form->addRow();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    if (isSchoolOpen($guid, $currentDate, $connection2) == false) {
        echo Format::alert(__('School is closed on the specified date, and so attendance information cannot be recorded.'));
        return;
    }

    if ($currentDate > $today) {
        echo Format::alert(__('The specified date is in the future: it must be today or earlier.'));
        return;
    }

    // Cancel out here if nothing is selected
    if (empty($tawasulActivityID) && empty($tawasulGroupID) && empty($tawasulPersonIDList)) {
        return;
    }

    $settingGateway = $container->get(SettingGateway::class);

    $attendance = new AttendanceView($tawasul, $pdo, $settingGateway);
    $attendanceLogGateway = $container->get(AttendanceLogPersonGateway::class);

    $defaultAttendanceType = $settingGateway->getSettingByScope('Attendance', 'defaultFormGroupAttendanceType');
    $countClassAsSchool = $settingGateway->getSettingByScope('Attendance', 'countClassAsSchool');
    
    // Get list of students for selected target
    $targetID = $target == 'Activity' ? $tawasulActivityID : ($target == 'Messenger' ? $tawasulGroupID : $tawasulPersonIDList);
    $students = $attendanceLogGateway->selectAdHocAttendanceStudents($session->get('tawasulSchoolYearID'), $target, $targetID, $currentDate)->fetchAll();

    if (empty($students)) {
        echo $page->getBlankSlate();
        return;
    } 

    $count = 0;
    $countPresent = 0;

    $defaults = ['type' => $defaultAttendanceType, 'reason' => '', 'comment' => '', 'context' => '', 'prefill' => 'Y', 'tawasulFormGroupID' => 0];

    // Build the attendance log data per student
    foreach ($students as $key => $student) {
        $result = $attendanceLogGateway->selectAttendanceLogsByPersonAndDate($student['tawasulPersonID'], $currentDate, $countClassAsSchool);
        $log = ($result->rowCount() > 0)? $result->fetch() : $defaults;

        $students[$key]['cellHighlight'] = '';
        if ($attendance->isTypeAbsent($log['type'])) {
            $students[$key]['cellHighlight'] = 'dayAbsent';
        } elseif ($attendance->isTypeOffsite($log['type'])) {
            $students[$key]['cellHighlight'] = 'dayMessage';
        } elseif ($attendance->isTypeLate($log['type'])) {
            $students[$key]['cellHighlight'] = 'dayPartial';
        }

        if ($attendance->isTypePresent($log['type']) && $attendance->isTypeOnsite($log['type'])) {
            $countPresent++;
        }

        $students[$key]['log'] = $log;
    }

    $form = Form::create('attendanceAdHoc', $session->get('absoluteURL').'/modules/'.$session->get('module'). '/attendance_take_adHocProcess.php');
    $form->setAutocomplete('off');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('target', $target);
    $form->addHiddenValue('tawasulActivityID', $tawasulActivityID);
    $form->addHiddenValue('tawasulGroupID', $tawasulGroupID);
    $form->addHiddenValue('tawasulPersonIDList', implode(',', $tawasulPersonIDList));
    $form->addHiddenValue('currentDate', $currentDate);
    $form->addHiddenValue('count', count($students));

    $form->addRow()->addHeading('Take Attendance', __('Take Attendance'));

    $grid = $form->addRow()->addGrid('attendance')->setBreakpoints('w-1/2 sm:w-1/4 md:w-1/5 lg:w-1/4');

    foreach ($students as $student) {
        $form->addHiddenValue($count . '-tawasulPersonID', $student['tawasulPersonID']);

        $cell = $grid->addCell()
            ->setClass('text-center py-2 px-1 -mr-px -mb-px flex flex-col justify-between')
            ->addClass($student['cellHighlight']);

        $studentLink = './index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID='.$student['tawasulPersonID'].'&subpage=Attendance';
        $icon = Format::userBirthdayIcon($student['dob'], $student['preferredName']);

        $cell->addContent(Format::link($studentLink, Format::userPhoto($student['image_240'], 75)))
            ->setClass('relative')
            ->append($icon ?? '');
        $cell->addWebLink(Format::name('', htmlPrep($student['preferredName']), htmlPrep($student['surname']), 'Student', false))
                ->setURL('index.php?q=/modules/TawasulStudents/student_view_details.php')
                ->addParam('tawasulPersonID', $student['tawasulPersonID'])
                ->addParam('subpage', 'Attendance')
                ->setClass('pt-2 font-bold underline');
        $cell->addContent($student['formGroup'])->wrap('<div class="text-xxs italic py-2">', '</div>');
        $restricted = $attendance->isTypeRestricted($student['log']['type']);
        $cell->addSelect($count.'-type')
                ->fromArray($attendance->getAttendanceTypes($restricted))
                ->selected($student['log']['type'])
                ->setClass('mx-auto float-none w-32 m-0 mb-px')
                ->readOnly($restricted);
        $cell->addSelect($count.'-reason')
                ->fromArray($attendance->getAttendanceReasons())
                ->selected($student['log']['reason'])
                ->setClass('mx-auto float-none w-32 m-0 mb-px');
        $cell->addTextField($count.'-comment')
                ->maxLength(255)
                ->setValue($student['log']['comment'])
                ->setClass('mx-auto float-none w-32 m-0 mb-2');
        $cell->addContent($attendance->renderMiniHistory($student['tawasulPersonID'], 'Person'));

        $count++;
    }

    $form->addRow()->addAlert(__('Total students:').' '. $count, 'success')->setClass('right')->wrap('<b>', '</b>');

    // Drop-downs to change the whole group at once
    $row = $form->addRow()->setAttribute('x-data', "{'changeAll': false}");
    $row->addButton(__('Change All').'?')
        ->setAttribute('@click', 'changeAll = !changeAll')
        ->addClass('flex-shrink m-px sm:self-center');

    $col = $row->addColumn()
        ->setClass('flex-grow flex flex-col sm:flex-row items-stretch sm:items-center')
        ->setAttribute('x-show', 'changeAll')
        ->setAttribute('x-cloak');
        
    $col->addSelect('set-all-type')->fromArray($attendance->getAttendanceTypes())->groupAlign('left')->setClass('flex-1');
    $col->addSelect('set-all-reason')->fromArray($attendance->getAttendanceReasons())->groupAlign('middle')->setClass('flex-1 -ml-px');
    $col->addTextField('set-all-comment')->maxLength(255)->groupAlign('middle')->setClass('flex-1 -ml-px');
    $col->addButton(__('Apply'))->setID('set-all')->groupAlign('right')->setAttribute('@click', 'changeAll = false');

    $row->addSubmit()->addClass('flex-shrink');

    echo $form->getOutput();

}
?>

<script type='text/javascript'>
    $("#target").change(function(){
        $("#attendanceAdHoc").css("display","none");
    });
    $("#tawasulActivityID").change(function(){
        $("#attendanceAdHoc").css("display","none");
    });
    $("#tawasulGroupID").change(function(){
        $("#attendanceAdHoc").css("display","none");
    });
</script>
