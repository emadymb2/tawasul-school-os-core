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

namespace TawasulOS\UI\Timetable\Layers;

use TawasulOS\Http\Url;
use TawasulOS\Services\Format;
use TawasulOS\Support\Facades\Access;
use TawasulOS\UI\Timetable\TimetableContext;
use TawasulOS\Domain\Timetable\TimetableDayGateway;
use TawasulOS\Domain\Timetable\TimetableDayDateGateway;
use TawasulOS\Domain\School\SchoolYearSpecialDayGateway;
use TawasulOS\Domain\Planner\PlannerEntryGateway;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Contracts\Services\Session;

/**
 * Timetable UI: ClassesLayer
 *
 * @version  v29
 * @since    v29
 */
class ClassesLayer extends AbstractTimetableLayer
{
    protected $session;
    protected $plannerEntryGateway;
    protected $timetableDayGateway;
    protected $timetableDayDateGateway;
    protected $specialDayGateway;
    protected $userGateway;
    protected $actionGateway;

    public function __construct(Session $session, PlannerEntryGateway $plannerEntryGateway, TimetableDayGateway $timetableDayGateway, TimetableDayDateGateway $timetableDayDateGateway, SchoolYearSpecialDayGateway $specialDayGateway, UserGateway $userGateway)
    {
        $this->session = $session;

        $this->plannerEntryGateway = $plannerEntryGateway;
        $this->timetableDayGateway = $timetableDayGateway;
        $this->timetableDayDateGateway = $timetableDayDateGateway;
        $this->specialDayGateway = $specialDayGateway;
        $this->userGateway = $userGateway;

        $this->name = 'Classes';
        $this->color = 'blue';
        $this->order = 20;
    }

    public function checkAccess(TimetableContext $context) : bool
    {
        return true;
    }
    
    public function loadItems(\DatePeriod $dateRange, TimetableContext $context) 
    {
        if ($context->has('tawasulPersonID')) {
            $this->loadItemsByPerson($dateRange, $context);
        } else if ($context->has('tawasulSpaceID')) {
            $this->loadItemsByFacility($dateRange, $context);
        }
    }

    protected function loadItemsByPerson(\DatePeriod $dateRange, TimetableContext $context) 
    {
        $specialDays = $context->get('specialDays', []);
        $offTimetable = array_reduce($specialDays, function ($group, $item) use ($context) {
            $group[$item['date']] = $this->specialDayGateway->getIsStudentOffTimetableByDate($context->get('tawasulSchoolYearID'), $context->get('tawasulPersonID'), $item['date']) ? $item['name'] : '';

            return $group;
        }, []);

        $lessons = $this->plannerEntryGateway->selectPlannerEntriesByPersonAndDateRange($context->get('tawasulPersonID'), $dateRange->getStartDate()->format('Y-m-d'), $dateRange->getEndDate()->format('Y-m-d'))->fetchGroupedUnique();

        $classes = $this->timetableDayDateGateway->selectTimetabledPeriodsByPersonAndDateRange($context->get('tawasulPersonID'), $dateRange->getStartDate()->format('Y-m-d'), $dateRange->getEndDate()->format('Y-m-d'))->fetchAll();

        $ttRowClassIDs = array_column($classes, 'tawasulTTDayRowClassID');
        $classTeachers = $this->timetableDayGateway->selectTTDayRowClassTeachersByID($ttRowClassIDs)->fetchGrouped();

        $canViewLessons = Access::allows('Planner', 'planner_view_full');
        $canAddLessons = Access::allows('Planner', 'planner_add') && $this->session->get('tawasulPersonID') == $context->get('tawasulPersonID');
        $canViewClasses = Access::allows('Departments', 'department_course_class');
        $canViewCoverage = Access::allows('Staff', 'coverage_my');
        $canEditCoverage = Access::allows('Staff', 'coverage_view_edit') && $this->session->get('tawasulPersonID') == $context->get('tawasulPersonID');

        foreach ($classes as $class) {
            $teachers = $classTeachers[$class['tawasulTTDayRowClassID']] ?? [];
            $specialDay = $specialDays[$class['date']] ?? [];

            if (!empty($specialDay['cancelClasses']) && $specialDay['cancelClasses'] == 'Y') continue;

            $item = $this->createItem($class['date'])->loadData([
                'type'          => __('Class'),
                'period'        => $class['period'],
                'title'         => Format::courseClassName($class['courseNameShort'], $class['classNameShort']),
                'label'         => $class['courseName'],
                'description'   => !empty($teachers) 
                    ? __n('Teacher', 'Teachers', count($teachers)).': '. Format::nameList($teachers, 'Staff', false, true, ', ') : '',
                'subtitle'      => $class['roomName'] ?? '',
                'location'      => $class['roomName'] ?? '',
                'phone'         => $class['phone'] ?? '',
                'timeStart'     => $class['timeStart'],
                'timeEnd'       => $class['timeEnd'],
                'link'          => $canViewClasses ? Url::fromModuleRoute('TawasulDepartments', 'department_course_class')->withQueryParams(['tawasulCourseClassID' => $class['tawasulCourseClassID'], 'currentDate' => $class['date']]) : '',
            ]);

            // Handle off timetable days
            if (!empty($specialDay) && $specialDay['type'] == 'Off Timetable') {
                if (!empty($offTimetable[$class['date']]) || $this->specialDayGateway->getIsClassOffTimetableByDate($class['tawasulSchoolYearID'], $class['tawasulCourseClassID'], $class['date'])) {
                    $item->addStatus('offTimetable')
                        ->set('type', __('Off Timetable'))
                        ->set('subtitle', $specialDay['name'])
                        ->set('style', 'stripe')
                        ->set('color', 'gray');
                }
            }

            // Handle room changes
            if (!empty($class['spaceChanged']) && !$item->hasStatus('offTimetable')) {
                $item->addStatus('spaceChanged')
                    ->set('location', $class['roomNameChange'] ?? __('No Facility'))
                    ->set('subtitle', $class['roomNameChange'] ?? __('No Facility'))
                    ->set('phone', $class['phoneChange']);
            }

            // Handle covered class
            if ($canViewCoverage && !empty($class['coverageID'])) {
                

                if ($class['coverageStatus'] == 'Accepted') {
                    $person = $this->userGateway->getByID($class['coveragePerson'], ['title', 'surname', 'preferredName']);
                    $description = !empty($person)
                        ? __('Covered by {name}', ['name' => Format::name($person['title'], $person['preferredName'], $person['surname'], 'Staff', false, true)])
                        : __('Coverage').': '.$class['coverageStatus'];
                } else {
                    $person = '';
                    $description = $class['coverageStatus'];
                }

                $item->addStatus('covered')
                    ->set('description', $description);

                $item->set('secondaryAction', [
                    'name'      => 'cover',
                    'label'     => $description,
                    'url'       => $canEditCoverage ? Url::fromModuleRoute('TawasulStaff', 'coverage_view_edit')->withQueryParams(['viewBy' => 'class', 'tawasulStaffCoverageID' => $class['coverageID']]) : Url::fromModuleRoute('TawasulStaff', 'report_absences_weekly'),
                    'icon'      => 'user',
                    'iconClass' => !empty($person) ? 'text-pink-500 hover:text-pink-800' : 'text-gray-600 hover:text-gray-800',
                ]);
            }

            $planner = $lessons[$class['lessonID']] ?? [];
            if (!empty($planner)) {
                unset($lessons[$class['lessonID']]);
            }
            
            if ($context->get('edit')) {
                // Add timetable-editing buttons for Edit mode
                $canEditTimetable = Access::allows('Timetable Admin', 'courseEnrolment_manage_byPerson_edit');
                if ($canEditTimetable && $class['date'] >= date('Y-m-d')) {
                    $item->set('primaryAction', [
                        'name'      => 'change',
                        'label'     => __('Add Facility Change'),
                        'url'       => Url::fromModuleRoute('TawasulTimetable', 'spaceChange_manage_add')->withQueryParams(['step' => '2', 'tawasulTTDayRowClassID' => $class['tawasulTTDayRowClassID'].'-'.$class['date'], 'tawasulCourseClassID' => $class['tawasulCourseClassID'], 'source' => $context->get('tawasulSpaceID')]),
                        'icon'      => 'next',
                        'iconClass' => 'text-gray-600 hover:text-gray-800',
                    ]);
                }

                if ($canEditTimetable) {
                    $item->set('secondaryAction', [
                        'name'      => 'edit',
                        'label'     => __('Add Exception'),
                        'url'       => Url::fromModuleRoute('TawasulTimetableAdmin', 'tt_edit_day_edit_class_exception_addProcess')->withQueryParams(['tawasulSchoolYearID' => $context->get('tawasulSchoolYearID'), 'tawasulTTID' => $class['tawasulTTID'], 'tawasulTTDayID' => $class['tawasulTTDayID'], 'tawasulTTDayRowClassID' => $class['tawasulTTDayRowClassID'], 'tawasulTTColumnRowID' => $class['tawasulTTColumnRowID'], 'tawasulCourseClassID' => $class['tawasulCourseClassID'], 'tawasulPersonID' => $context->get('tawasulPersonID')])->directLink(),
                        'icon'      => 'user-minus',
                        'iconClass' => 'text-gray-600 hover:text-gray-800',
                    ]);
                }
            } else {
                // Add buttons to access or create lesson plans
                if ($canViewLessons && !empty($planner)) {
                    $item->set('primaryAction', [
                        'name'      => 'view',
                        'label'     => __('Lesson planned: {name}',['name' => htmlPrep($planner['name'])]),
                        'url'       => Url::fromModuleRoute('TawasulPlanner', 'planner_view_full')->withQueryParams(['viewBy' => 'class', 'tawasulCourseClassID' => $planner['tawasulCourseClassID'], 'tawasulPlannerEntryID' => $planner['tawasulPlannerEntryID'], 'search' => $context->get('tawasulPersonID')]),
                        'icon'      => 'check',
                        'iconClass' => 'text-blue-500 hover:text-blue-800',
                    ]);
                }
                if ($canAddLessons && empty($planner)) {
                    $item->set('primaryAction', [
                        'name'      => 'add',
                        'label'     => __('Add lesson plan'),
                        'url'       => Url::fromModuleRoute('TawasulPlanner', 'planner_add')->withQueryParams(['viewBy' => 'class', 'tawasulCourseClassID' => $class['tawasulCourseClassID'], 'date' => $class['date'], 'timeStart' => $class['timeStart'], 'timeEnd' => $class['timeEnd'], 'tawasulTTDayRowClassID' => $class['tawasulTTDayRowClassID']]),
                        'icon'      => 'add',
                        'iconClass' => 'text-gray-600 hover:text-gray-800',
                    ]);
                }
            }
            
            
        }

        foreach ($lessons as $lesson) {
            $specialDay = $specialDays[$lesson['date']] ?? [];
            if (!empty($specialDay['cancelClasses']) && $specialDay['cancelClasses'] == 'Y') continue;

            // Build class shortcode prefix (same as slotted lessons)
            $prefix = Format::courseClassName(
                $lesson['courseNameShort'] ?? '',
                $lesson['classNameShort'] ?? ''
            );

            // Look up teachers using tawasulCourseClassID (correct for loose lessons)
            // Convert flat teacher fields into an array
            $teachers = [];

            if (!empty($lesson['teachers_surname'])) {
                $teachers[] = [
                    'title' => $lesson['teachers_title'],
                    'preferredName' => $lesson['teachers_preferredName'],
                    'surname' => $lesson['teachers_surname'],
                ];
            }

            $teacherList = !empty($teachers)
                ? __n('Teacher', 'Teachers', count($teachers)).': '.Format::nameList($teachers, 'Staff', false, true, ', ')
                : '';

            // Build enriched description
            $description =
                (!empty($teacherList) ? $teacherList.'<br>' : '');

            $item = $this->createItem($lesson['date'])->loadData([
                'type'        => __('Lesson'),
                'title'       => $prefix,
                'label'       => $lesson['courseName'],
                'description' => $description,
                'subtitle'    => $lesson['plannerRoomName'] ?? '',
                'location'    => $lesson['plannerRoomName'] ?? '',
                'phone'       => $lesson['plannerRoomPhone'] ?? '',
                'timeStart'   => $lesson['timeStart'],
                'timeEnd'     => $lesson['timeEnd'],
                'link'        => Url::fromModuleRoute('TawasulPlanner', 'planner_view_full')->withQueryParams([
                    'viewBy'              => 'class',
                    'tawasulCourseClassID' => $lesson['tawasulCourseClassID'],
                    'tawasulPlannerEntryID'=> $lesson['tawasulPlannerEntryID']
                ]),
            ]);
 
            // Add a button for the lesson plan
            $item->set('primaryAction', [
                'name'      => 'view',
                'label'     => __('Lesson planned: {name}',['name' => htmlPrep($lesson['name'])]),
                'url'       => Url::fromModuleRoute('TawasulPlanner', 'planner_view_full')->withQueryParams(['viewBy' => 'class', 'tawasulCourseClassID' => $lesson['tawasulCourseClassID'], 'tawasulPlannerEntryID' => $lesson['tawasulPlannerEntryID']]),
                'icon'      => 'check',
                'iconClass' => 'text-blue-500 hover:text-blue-800',
            ]);

            // Handle off timetable days
            if (!empty($specialDay) && $specialDay['type'] == 'Off Timetable') {
                if ($this->specialDayGateway->getIsClassOffTimetableByDate($lesson['tawasulSchoolYearID'], $lesson['tawasulCourseClassID'], $lesson['date'])) {
                    $item->addStatus('offTimetable')
                        ->set('type', __('Off Timetable'))
                        ->set('subtitle', $specialDay['name'])
                        ->set('style', 'stripe')
                        ->set('color', 'gray');
                }
            }

        }

        // Add off timetable days as all day events for students
        foreach ($offTimetable as $date => $specialDayName) {
            if (!empty($specialDayName)) {
                $this->createItem($date, true)->loadData([
                    'type'      => __('Off Timetable'),
                    'title'     => $specialDayName,
                    'allDay'    => true,
                    'timeStart' => null,
                    'timeEnd'   => null,
                    'color'     => 'gray',
                    'style'     => 'stripe',
                ]);
            }
        }
    }

    public function loadItemsByFacility(\DatePeriod $dateRange, TimetableContext $context) 
    {
        $specialDays = $context->get('specialDays', []);

        $classes = $this->timetableDayDateGateway->selectTimetabledPeriodsByFacilityAndDateRange($context->get('tawasulSpaceID'), $dateRange->getStartDate()->format('Y-m-d'), $dateRange->getEndDate()->format('Y-m-d'))->fetchAll();

        $ttRowClassIDs = array_column($classes, 'tawasulTTDayRowClassID');
        $classTeachers = $this->timetableDayGateway->selectTTDayRowClassTeachersByID($ttRowClassIDs)->fetchGrouped();
        // Build a teacher map indexed by tawasulCourseClassID for loose lessons
        $teachersByCourseClass = [];
        foreach ($classTeachers as $rowClassID => $teachers) {
            foreach ($teachers as $t) {
                if (!empty($t['tawasulCourseClassID'])) {
                    $teachersByCourseClass[$t['tawasulCourseClassID']][] = $t;
                }
            }
        }

        $canViewClasses = Access::allows('Departments', 'department_course_class');
        $canAddChanges = Access::allows('Timetable', 'spaceChange_manage_add');
        $canEditLocation = Access::allows('Timetable', 'tt_space_edit');
        $canEditTTDays = Access::allows('Timetable Admin', 'tt_edit_day_edit_class_edit');

        foreach ($classes as $class) {
            $specialDay = $specialDays[$class['date']] ?? [];
            if (!empty($specialDay['cancelClasses']) && $specialDay['cancelClasses'] == 'Y') continue;

            $teachers = $classTeachers[$class['tawasulTTDayRowClassID']] ?? [];

            $item = $this->createItem($class['date'])->loadData([
                'type'          => __('Class'),
                'period'        => $class['period'],
                'title'         => Format::courseClassName($class['courseNameShort'], $class['classNameShort']),
                'label'         => $class['courseName'],
                'description'   => !empty($teachers) 
                    ? __n('Teacher', 'Teachers', count($teachers)).': '. Format::nameList($teachers, 'Staff', false, true, ', ') : '',
                'subtitle'      => $class['roomName'] ?? '',
                'location'      => $class['roomName'] ?? '',
                'phone'         => $class['phone'] ?? '',
                'timeStart'     => $class['timeStart'],
                'timeEnd'       => $class['timeEnd'],
                'link'          => $canViewClasses ? Url::fromModuleRoute('TawasulDepartments', 'department_course_class')->withQueryParams(['tawasulCourseClassID' => $class['tawasulCourseClassID'], 'currentDate' => $class['date']]) : '',
            ]);

            // Handle off timetable days
            if (!empty($specialDay) && $specialDay['type'] == 'Off Timetable') {
                if ($this->specialDayGateway->getIsClassOffTimetableByDate($class['tawasulSchoolYearID'], $class['tawasulCourseClassID'], $class['date'])) {
                    $item->addStatus('offTimetable')
                        ->set('type', __('Off Timetable'))
                        ->set('subtitle', $specialDay['name'])
                        ->set('style', 'stripe')
                        ->set('color', 'gray');
                }
            }

            // Handle room changes
            if (!empty($class['spaceChanged']) && !$item->hasStatus('offTimetable')) {
                $item->addStatus('spaceChanged');
            }

            $tawasulTTDayRowClassID = str_pad($class['tawasulTTDayRowClassID'], 12, '0', STR_PAD_LEFT);

            if ($canAddChanges && $class['date'] >= date('Y-m-d')) {
                $item->set('primaryAction', [
                    'name'      => 'change',
                    'label'     => __('Add Facility Change'),
                    'url'       => Url::fromModuleRoute('TawasulTimetable', 'spaceChange_manage_add')->withQueryParams(['step' => '2', 'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID.'-'.$class['date'], 'tawasulCourseClassID' => $class['tawasulCourseClassID'], 'source' => $context->get('tawasulSpaceID')]),
                    'icon'      => 'next',
                    'iconClass' => 'text-gray-600 hover:text-gray-800',
                ]);
            }

            if ($canEditTTDays || $canEditLocation) {
                $item->set('secondaryAction', [
                    'name'      => 'edit',
                    'label'     => $canEditLocation ? __('Edit Facility'): __('Edit Class in Period'),
                    'url'       => Url::fromModuleRoute('TawasulTimetableAdmin', 'tt_edit_day_edit_class_edit')->withQueryParams(['tawasulSchoolYearID' => $context->get('tawasulSchoolYearID'), 'tawasulTTID' => $class['tawasulTTID'], 'tawasulTTDayID' => $class['tawasulTTDayID'], 'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID, 'tawasulTTColumnRowID' => $class['tawasulTTColumnRowID'], 'tawasulCourseClassID' => $class['tawasulCourseClassID']]),
                    'icon'      => 'edit',
                    'iconClass' => 'text-gray-600 hover:text-gray-800',
                ]);
            }
        }
    }
}
