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
use TawasulOS\Tables\DataTable;
use TawasulOS\Forms\DatabaseFormFactory;
use Tos\Module\TawasulAttendance\AttendanceView;
use TawasulOS\Domain\Timetable\CourseEnrolmentGateway;
use TawasulOS\Domain\Attendance\AttendanceLogPersonGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\Messenger\GroupGateway;
use TawasulOS\Domain\Activities\ActivityGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';
require_once __DIR__ . '/src/AttendanceView.php';

// set page breadcrumb
$page->breadcrumbs->add(__('Set Future Absence'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/attendance_future_byPerson.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->return->addReturns([
        'warning2' => __('Your request was successful, but some data was not properly saved.') .' '. __('The specified date is not in the future, or is not a school day.'),
        'error7' => __('Your request failed because the student has already been marked absent for the full day.'),
        'error8' => __('Your request failed because the selected date is not in the future.'),
    ]);

    $attendance = new AttendanceView($tawasul, $pdo, $container->get(SettingGateway::class));
    $attendanceLogGateway = $container->get(AttendanceLogPersonGateway::class);
    $courseEnrolmentGateway = $container->get(CourseEnrolmentGateway::class);

    $scope = (isset($_GET['scope']))? $_GET['scope'] : 'single';

    $tawasulPersonIDList = $_GET['tawasulPersonIDList'] ?? $_GET['tawasulPersonID'] ?? [];
    if (!empty($tawasulPersonIDList)) {
        $tawasulPersonIDList = is_array($tawasulPersonIDList)
            ? array_unique($tawasulPersonIDList)
            : explode(",", $tawasulPersonIDList);
    }

    if (empty($tawasulPersonIDList)) $tawasulPersonIDList = [];

    $target = $_GET['target'] ?? '';
    $tawasulActivityID = $_GET['tawasulActivityID'] ?? '';
    $tawasulGroupID = $_GET['tawasulGroupID'] ?? '';
    $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
    $absenceType = $_GET['absenceType'] ?? 'full';
    $date = $_GET['date'] ?? '';
    $dateStart = $_GET['dateStart'] ?? '';
    $dateEnd = $_GET['dateEnd'] ?? '';
    $timeStart = $_GET['timeStart'] ?? '';
    $timeEnd = $_GET['timeEnd'] ?? '';
    $foreignTable = $_GET['foreignTable'] ?? '';
    $foreignTableID = $_GET['foreignTableID'] ?? '';

    $urlParams = compact('target', 'tawasulActivityID', 'tawasulGroupID', 'tawasulCourseClassID', 'absenceType', 'date', 'timeStart', 'timeEnd');

    $targetDate = !empty($date) ? Format::dateConvert($date) : date('Y-m-d');
    $effectiveStart = strtotime($targetDate.' '.$timeStart);
    $effectiveEnd = strtotime($targetDate.' '.$timeEnd);

    $canTakeAdHocAttendance = isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/attendance_take_adHoc.php');

    // Generate choose student form
    $form = Form::create('attendanceSearch',$session->get('absoluteURL') . '/index.php','GET');
    $form->setTitle(__('Choose Student'));
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q','/modules/'.$session->get('module').'/attendance_future_byPerson.php');

    $availableScopes = [
        'single' => __('Single Student'),
        'multiple' => __('Multiple Students'),
    ];
    $row = $form->addRow();
        $row->addLabel('scope', __('Scope'));
        $row->addSelect('scope')->fromArray($availableScopes)->selected($scope);

    $form->toggleVisibilityByClass('single')->onSelect('scope')->when('single');
    $form->toggleVisibilityByClass('multiple')->onSelect('scope')->when('multiple');

    $row = $form->addRow()->addClass('single');
        $row->addLabel('tawasulPersonID', __('Student'));
        $row->addSelectStudent('tawasulPersonID', $session->get('tawasulSchoolYearID'))->setID('tawasulPersonIDSingle')->required()->placeholder()->selected($tawasulPersonIDList[0] ?? '')->photo(true, 'small');

    if ($canTakeAdHocAttendance) {
        // Show the ad hoc attendance groups
        $targetOptions = [
            'Messenger' => __('Messenger Group'),
            'Activity'  => __('Activity Enrolment'),
            'Class'   => __('Class Enrolment'),
            'Select'    => __('Select Students'),
        ];
        $row = $form->addRow()->addClass('multiple');
            $row->addLabel('target', __('Target'));
            $row->addSelect('target')->fromArray($targetOptions)->required()->selected($target)->placeholder();

        $form->toggleVisibilityByClass('targetActivity')->onSelect('target')->when('Activity');
        $form->toggleVisibilityByClass('targetMessenger')->onSelect('target')->when('Messenger');
        $form->toggleVisibilityByClass('targetClass')->onSelect('target')->when('Class');
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

        // Class Enrolments
        $row = $form->addRow()->addClass('targetClass');
            $row->addLabel('tawasulCourseClassID', __('Class'));
            $row->addSelectClass('tawasulCourseClassID', $session->get('tawasulSchoolYearID'), $session->get('tawasulPersonID'))->selected($tawasulCourseClassID)->required()->placeholder();
    }

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

    $col = $form->addRow()->addClass($canTakeAdHocAttendance ? 'targetSelect' : 'multiple')->addColumn();
        $col->addLabel('tawasulPersonIDList', __('Students'));
        $select = $col->addMultiSelect('tawasulPersonIDList')->isRequired();
        $select->addSortableAttribute(__('Form Group'), $studentList['form'] ?? '');
        $select->source()->fromArray($studentList['students']['source'] ?? []);
        $select->destination()->fromArray($studentList['students']['destination'] ?? []);

    if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/attendance_take_byCourseClass.php')) {
        $availableAbsenceTypes = [
            'full'    => __('Full Day'),
            'partial' => __('Partial'),
        ];

        $row = $form->addRow();
            $row->addLabel('absenceType', __('Absence Type'));
            $row->addSelect('absenceType')->fromArray($availableAbsenceTypes)->selected($absenceType);

        $form->toggleVisibilityByClass('partialDateRow')->onSelect('absenceType')->when('partial');
        $row = $form->addRow()->addClass('partialDateRow');
            $row->addLabel('date', __('Date'));
            $row->addDate('date')->required()->setValue($date)->minimum(date('Y-m-d'));

        $row = $form->addRow()->addClass('partialDateRow');
            $row->addLabel('timeStart', __('Start Time'));
            $row->addTime('timeStart')
                ->required()
                ->setValue($timeStart);

        $row = $form->addRow()->addClass('partialDateRow');
            $row->addLabel('timeEnd', __('End Time'));
            $row->addTime('timeEnd')
                ->required()
                ->chainedTo('timeStart')
                ->setValue($timeEnd);
    }


    $form->addRow()->addSearchSubmit($session);

    echo $form->getOutput();

    // Get list of students for selected target
    if (!empty($target)) {
        switch ($target) {
            case 'Activity':
                $targetID = $tawasulActivityID; break;
            case 'Messenger':
                $targetID = $tawasulGroupID; break;
            case 'Class':
                $targetID = $tawasulCourseClassID; break;
            default:      
                $targetID = $tawasulPersonIDList; break;
        }

        $students = $attendanceLogGateway->selectAdHocAttendanceStudents($session->get('tawasulSchoolYearID'), $target, $targetID, $targetDate)->fetchAll();
        $tawasulPersonIDList = empty($tawasulPersonIDList) ? array_column($students, 'tawasulPersonID') : $tawasulPersonIDList;
    }

    if(!empty($tawasulPersonIDList)) {
        $today = date('Y-m-d');
        $attendanceLog = '';

        if (!empty($date) && Format::dateConvert($date) < $today) {
            echo Format::alert(__('The specified date is not in the future, or is not a school day.'), 'error');
            return;
        }

        $form = Form::create('attendanceSet',$session->get('absoluteURL') . '/modules/' . $session->get('module') . '/attendance_future_byPersonProcess.php');

        if ($scope == 'single') {
            // Get attendance logs
            $logs = $attendanceLogGateway->selectFutureAttendanceLogsByPersonAndDate($tawasulPersonIDList[0], $targetDate)->fetchAll();

            //Get classes for partial attendance
            $classes = $courseEnrolmentGateway->selectClassesByPersonAndDate($session->get('tawasulSchoolYearID'), $tawasulPersonIDList[0], $targetDate)->fetchAll();

            if ($absenceType == 'partial' && empty($classes)) {
                echo Format::alert(__('Cannot record a partial absence. This student does not have timetabled classes for this day.'));
                return;
            }

            // Filter only classes that are attendanceable
            $classes = array_filter($classes, function ($item) {
                return $item['attendance'] == 'Y';
            });

            // Display attendance logs
            if (!empty($logs)) {
                $table = DataTable::create('logs');
                $table->setTitle(__('Attendance Log'));
                $table->setDescription(__('The following future absences have been set for the selected student.'));

                $table->modifyRows(function ($log, $row) use (&$attendance) {
                    if ($attendance->isTypeAbsent($log['type'])) $row->addClass('error');
                    elseif ($attendance->isTypeOffsite($log['type']) || $log['direction'] == 'Out') $row->addClass('message');
                    elseif ($attendance->isTypeLate($log['type'])) $row->addClass('warning');
                    else $row->addClass('success');

                    return $row;
                });

                $table->addColumn('date', __('Date'))->format(Format::using('date', 'date'));
                $table->addColumn('attendance', __('Attendance'))
                    ->format(function($log) {
                        $output = '<b>'.__($log['direction']).'</b> ('.__($log['type']). (!empty($log['reason'])? ', '.$log['reason'] : '') .')';
                        if (!empty($log['comment']) ) {
                            $output .= Format::tooltip(icon('solid', 'chat-bubble-text', 'size-4'), htmlPrep($log['comment']));
                        }
                        return $output;
                    });
                $table->addColumn('where', __('Where'))->format(function ($log) {
                    if (($log['context'] == 'Future' || $log['context'] == 'Class') && $log['tawasulCourseClassID'] > 0) {
                        return __($log['context']).' ('.$log['courseName'].'.'.$log['className'].')';
                    } else {
                        return __($log['context']);
                    }
                });
                $table->addColumn('staff', __('Recorded By'))->format(Format::using('name', ['', 'preferredName', 'surname', 'Staff', false, true]));
                $table->addColumn('timestamp', __('On'))->format(Format::using('dateTimeReadable', 'timestampTaken'));

                $table->addActionColumn()
                    ->addParam('tawasulPersonID', $tawasulPersonIDList[0] ?? '')
                    ->addParam('tawasulAttendanceLogPersonID')
                    ->addParams($urlParams)
                    ->format(function ($row, $actions) {
                        $actions->addAction('deleteInstant', __('Delete'))
                            ->setIcon('garbage')
                            ->setURL('/modules/TawasulAttendance/attendance_future_byPersonDeleteProcess.php')
                            ->addConfirmation(__('Are you sure you want to delete this record? Unsaved changes will be lost.'))
                            ->directLink();
                    });

                echo $table->render($logs);
            }

        } elseif ($scope == 'multiple' && !empty($students)) {

            $form->addRow()->addHeading('Students', __('Students'));
            $grid = $form->addRow()->addGrid('attendance')->setBreakpoints('w-1/2 sm:w-1/4 md:w-1/5 lg:w-1/4');

            foreach ($students as $count => $student) {
                $cell = $grid->addCell()
                    ->setClass('text-center py-2 px-1 -mr-px -mb-px flex flex-col justify-between')
                    ->addClass($student['cellHighlight'] ?? '');

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
                $cell->addContent($student['formGroup'])->wrap('<div class="text-xxs italic">', '</div>');

                if ($absenceType == 'partial') {
                    $col = $cell->addColumn()->addClass('mx-auto flex flex-col items-start');

                    //Get classes for partial attendance
                    $logs = $attendanceLogGateway->selectFutureAttendanceLogsByPersonAndDate($student['tawasulPersonID'], $targetDate)->fetchAll();
                    $classes = $courseEnrolmentGateway->selectClassesByPersonAndDate($session->get('tawasulSchoolYearID'), $student['tawasulPersonID'], $targetDate)->fetchAll();
                    
                    // Filter only classes that are attendanceable
                    $classes = array_filter($classes, function ($item) {
                        return $item['attendance'] == 'Y';
                    });

                    if (!empty($classes)) {
                        $classOptions = array_reduce($classes, function ($group, $class) use (&$logs, $targetDate) {
                            $name = $class['columnName'] . ' - ' . $class['courseNameShort'] . '.' . $class['classNameShort'];

                            foreach ($logs as $log) {
                                if ($log['context'] == 'Class' && $class['tawasulCourseClassID'] == $log['tawasulCourseClassID'] && $log['date'] == $targetDate) {
                                    $name = $log['type'] . ' - ' . $class['courseNameShort'] . '.' . $class['classNameShort'];
                                } else if ($log['context'] == 'Future' && $log['date'] == $targetDate) {
                                    $name = $class['columnName'] . ' - ' . $log['type'] . ' '. $log['reason'];
                                }
                            }

                            $group[$class['tawasulCourseClassID'].'-'.$class['tawasulTTDayRowClassID']] = $name;
                            return $group;
                        }, []);

                        // Check for overlap with this class
                        $checked = array_reduce($classes, function ($group, $class) use ($targetDate, $effectiveStart, $effectiveEnd) {
                            $classStart = strtotime($targetDate.' '.$class['timeStart']);
                            $classEnd = strtotime($targetDate.' '.$class['timeEnd']);
                            if (($classStart >= $effectiveStart && $classStart < $effectiveEnd)
                                    || ($effectiveStart >= $classStart && $effectiveStart < $classEnd)) {
                                $group[] = $class['tawasulCourseClassID'].'-'.$class['tawasulTTDayRowClassID'];
                            }

                            return $group;
                        }, []);

                        $disabled = array_reduce($classes, function ($group, $class) use (&$logs, $targetDate) {
                            foreach ($logs as $log) {
                                if ($log['context'] == 'Class' && $class['tawasulCourseClassID'] == $log['tawasulCourseClassID'] && $log['date'] == $targetDate && (empty($log['tawasulTTDayRowClassID']) || ($class['tawasulTTDayRowClassID'] == $log['tawasulTTDayRowClassID'])) ) {
                                    $group[] = $class['tawasulCourseClassID'].'-'.$class['tawasulTTDayRowClassID'];
                                }
                            }

                            return $group;
                        }, []);

                        // Account for whole-day future absences that this student already has
                        $futureAbsences = array_filter($logs, function ($log) use ($targetDate) {
                            return $log['context'] == 'Future' && $log['date'] == $targetDate;
                        });
                        if (count($futureAbsences) > 0) {
                            $disabled = array_keys($classOptions);
                            $checked = [];
                        }

                        $col->addCheckbox("courses[{$student['tawasulPersonID']}][]")
                            ->setID("classes{$student['tawasulPersonID']}")
                            ->fromArray($classOptions)
                            ->setClass('')
                            ->alignLeft()
                            ->checked(empty($futureAbsences) ? $checked + $disabled : $checked)
                            ->disabled($disabled);

                    } else {
                        $col->addContent(Format::small(__('N/A')));
                    }
                }
            }

            $form->addRow()->addAlert(__('Total students:').' '. count($tawasulPersonIDList), 'success')->setClass('right')->wrap('<b>', '</b>');
        }

        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('scope', $scope);
        $form->addHiddenValue('absenceType', $absenceType);
        $form->addHiddenValue('target', $target);
        $form->addHiddenValue('tawasulActivityID', $tawasulActivityID);
        $form->addHiddenValue('tawasulGroupID', $tawasulGroupID);
        $form->addHiddenValue('tawasulCourseClassID', $tawasulCourseClassID);
        $form->addHiddenValue('date', $date);
        $form->addHiddenValue('timeStart', $timeStart);
        $form->addHiddenValue('timeEnd', $timeEnd);
        $form->addHiddenValue('tawasulPersonIDList', implode(",", $tawasulPersonIDList));
        $form->addHiddenValue('foreignTable', $foreignTable);
        $form->addHiddenValue('foreignTableID', $foreignTableID);

        $form->addRow()->addHeading('Set Future Attendance', __('Set Future Attendance'));

        if ($absenceType == 'full') {
            $row = $form->addRow();
                $row->addLabel('dateStart', __('Start Date'));
                $row->addDate('dateStart')->required()->minimum(date('Y-m-d'))->setValue($dateStart);

            $row = $form->addRow();
                $row->addLabel('dateEnd', __('End Date'));
                $row->addDate('dateEnd')->minimum(date('Y-m-d'))->setValue($dateEnd);
        } else {
            $form->addHiddenValue('dateStart', $date);
            $form->addHiddenValue('dateEnd', $date);

            if ($scope == 'single') {
                $row = $form->addRow();
                $row->addLabel('periodSelectContainer', __('Periods Absent'));

                $table = $row->addTable('periodSelectContainer')->setClass('standardWidth');
                $table->addHeaderRow()->addHeading(Format::dateReadable(Format::dateConvert($date), Format::LONG));

                foreach ($classes as $class) {
                    $name = $class['columnName'] . ' - ' . $class['courseNameShort'] . '.' . $class['classNameShort'];
                    $logName = $name;

                    $classStart = strtotime($targetDate.' '.$class['timeStart']);
                    $classEnd = strtotime($targetDate.' '.$class['timeEnd']);

                    $checked = (($classStart >= $effectiveStart && $classStart < $effectiveEnd)
                            || ($effectiveStart >= $classStart && $effectiveStart < $classEnd));

                    $disabled = false;
                    foreach (array_reverse($logs) as $log) {
                        if ($log['context'] == 'Class' && $class['tawasulCourseClassID'] == $log['tawasulCourseClassID'] && $log['date'] == $targetDate) {
                            $logName = $name . ' ('.$log['type'].')';
                            break;
                        }

                        if ($log['context'] != 'Class' && $log['date'] == $targetDate) {
                            $logName = $name . ' ('.$log['type'].')';
                        }
                    }

                    // Account for whole-day future absences that this student already has
                    $futureAbsences = array_filter($logs, function ($log) use ($targetDate) {
                        return $log['context'] == 'Future' && $log['date'] == $targetDate;
                    });
                    if (count($futureAbsences) > 0) {
                        $disabled = true;
                        $checked = false;
                    }

                    $row = $table->addRow();
                    $row->addCheckbox("courses[{$tawasulPersonIDList[0]}][]")
                        ->description($logName)
                        ->setValue($class['tawasulCourseClassID'].'-'.$class['tawasulTTDayRowClassID'])
                        ->inline()
                        ->setClass('')
                        ->checked($checked ? $class['tawasulCourseClassID'] : '')
                        ->disabled($disabled);
                }
            } else {

            }
        }

        $row = $form->addRow();
            $row->addLabel('type', __('Type'));
            $row->addSelect('type')->fromArray($attendance->getFutureAttendanceTypes())->required()->selected($scope == 'multiple' ? 'Present - Offsite' : 'Absent');

        $row = $form->addRow();
            $row->addLabel('reason', __('Reason'));
            $row->addSelect('reason')->fromArray($attendance->getAttendanceReasons());

        $row = $form->addRow();
            $row->addLabel('comment', __('Comment'))->description(__('255 character limit'));
            $row->addTextArea('comment')->setRows(3)->maxLength(255);

        $form->addRow()->addSubmit();

        echo $attendanceLog;
        echo $form->getOutput();
    }
}
?>

<script type='text/javascript'>
    $("#absenceType").change(function(){
        if ($("#scope").val() != 'multiple') {
            $("#attendanceLog").css("display","none");
            $("#attendanceSet").css("display","none");
        }
    });
    $("#scope").change(function(){
        $("#attendanceLog").css("display","none");
        $("#attendanceSet").css("display","none");
    });
</script>
