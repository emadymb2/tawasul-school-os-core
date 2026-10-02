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

namespace Tos\Module\TawasulPlanner\Tables;

use TawasulOS\Domain\DataSet;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Contracts\Services\Session;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Contracts\Database\Connection;
use TawasulOS\Domain\Planner\PlannerEntryGateway;
use TawasulOS\Domain\School\SchoolYearTermGateway;
use TawasulOS\Domain\School\SchoolYearSpecialDayGateway;
use TawasulOS\Support\Facades\Access;

/**
 * LessonTable
 * 
 * Reusable DataTable class for displaying lesson plans.
 *
 * @version v28
 * @since   v28
 */
class LessonTable
{
    protected $session;
    protected $db;
    protected $settingGateway;
    protected $plannerEntryGateway;
    protected $schoolYearTermGateway;
    protected $schoolYearSpecialDayGateway;
    protected $homeworkNameSingular;
    protected $homeworkNamePlural;

    public function __construct(Session $session, Connection $db, SettingGateway $settingGateway, PlannerEntryGateway $plannerEntryGateway, SchoolYearTermGateway $schoolYearTermGateway, SchoolYearSpecialDayGateway $schoolYearSpecialDayGateway)
    {
        $this->session = $session;
        $this->db = $db;
        $this->settingGateway = $settingGateway;
        $this->plannerEntryGateway = $plannerEntryGateway;
        $this->schoolYearTermGateway = $schoolYearTermGateway;
        $this->schoolYearSpecialDayGateway = $schoolYearSpecialDayGateway;

        $this->homeworkNameSingular = $settingGateway->getSettingByScope('Planner', 'homeworkNameSingular');
        $this->homeworkNamePlural = $settingGateway->getSettingByScope('Planner', 'homeworkNamePlural');
    }

    public function create($tawasulSchoolYearID, $tawasulCourseClassID, $tawasulPersonID, $date, $viewBy = 'date')
    {
        $guid = $this->session->get('guid');
        $connection2 = $this->db->getConnection();

        $highestAction = getHighestGroupedAction($guid, '/modules/TawasulPlanner/planner.php', $connection2);
        $roleCategory = $this->session->get('tawasulRoleIDCurrentCategory');
        $tawasulPersonIDSelf = $this->session->get('tawasulPersonID');

        $viewingAs = $this->getViewingAs($highestAction, $roleCategory);
        $editAccess = $this->getEditAccess($highestAction, $roleCategory);
        $unitAccess = Access::allows('Planner', 'units');

        if ($editAccess || $highestAction == 'Lesson Planner_viewOnly') {
            $tawasulPersonID = $viewBy == 'date' && $viewingAs == 'Teacher' ? $tawasulPersonIDSelf : null;
        } else {
            $tawasulPersonID = $tawasulPersonID ?? $tawasulPersonIDSelf;
        }

        $criteria = $this->plannerEntryGateway->newQueryCriteria($viewBy != 'year')
            ->sortBy('date', $viewBy == 'year' ? 'ASC' : 'DESC')
            ->sortBy('timeStart', $viewBy != 'class' ? 'ASC' : 'DESC')
            ->fromPOST();

        if ($viewBy == 'year') {
            $lessons = $this->plannerEntryGateway->queryPlannerTimeSlotsByClass($criteria, $tawasulSchoolYearID, $tawasulCourseClassID);
        } else if ($viewBy == 'class') {
            $lessons = $this->plannerEntryGateway->queryPlannerByClass($criteria, $tawasulSchoolYearID, $tawasulPersonID, $tawasulCourseClassID, $viewingAs);
        } else {
            $lessons = $this->plannerEntryGateway->queryPlannerByDate($criteria, $tawasulSchoolYearID, $tawasulPersonID, $date, $viewingAs);
        }

        $lessonCount = 1;
        $lessons->transform(function (&$values) use (&$lessonCount, $roleCategory, $tawasulPersonIDSelf) {
            $teacherList = explode(',', $values['teacherIDs'] ?? '');
            $values['isTeacher'] = $roleCategory == 'Staff' && !empty($teacherList) && in_array($tawasulPersonIDSelf, $teacherList) === true;
            $values['lessonNumber'] = __('Lesson').' '.$lessonCount;
            $lessonCount++;
        });

        if ($viewBy == 'year') {
            $lessons = $this->addSchoolClosureDates($tawasulSchoolYearID, $lessons->toArray());
        }

        $lessonData = is_array($lessons) ? $lessons[0] : $lessons->getRow(0);

        $table = DataTable::createPaginated('lessonPlanner', $criteria)->withData($lessons);

        $table->addMetaData('blankSlate', $viewBy == 'class' ? __('There are no lessons for this class.') : __('There are no lessons on this date.'));

        $table->modifyRows(function ($values, $row) {
            $now = date('H:i:s');
            $today = date('Y-m-d');
            
            if (!empty($values['closure'])) {
                $row->addClass('warning');
            } elseif (!empty($values['offTimetableDay'])) {
                $row->addClass('bg-stripe-overlay');
            } elseif ($now > $values['timeStart'] && $now < $values['timeEnd'] && $values['date'] == $today) {
                $row->addClass('current');
            } else if ($values['date'] < $today || ($values['date'] == $today && $now > $values['timeEnd']) ) {
                $row->addClass('dull');
            }
            return $row;
        });

        if ($editAccess && ($viewBy == 'class' || $viewBy == 'year')) {
            $table->addHeaderAction('lessonView', __('Lesson View'))
                ->setURL('/modules/TawasulPlanner/planner.php')
                ->addParam('tawasulCourseClassID', $tawasulCourseClassID)
                ->addParam('viewBy', 'class')
                ->addParam('subView', 'lesson')
                ->setIcon('book-open')
                ->addClass($viewBy == 'class' ? 'ring-1 ring-blue-500 border-blue-500' : '')
                ->displayLabel();

            $table->addHeaderAction('yearOverview', __('Year Overview'))
                ->setURL('/modules/TawasulPlanner/planner.php')
                ->addParam('tawasulCourseClassID', $tawasulCourseClassID)
                ->addParam('viewBy', 'class')
                ->addParam('subView', 'year')
                ->setIcon('calendar')
                ->addClass($viewBy == 'year' ? 'ring-1 ring-blue-500 border-blue-500' : '')
                ->displayLabel();
        }

        if ($unitAccess) {
            $table->addHeaderAction('units', __('Unit Planner'))
                ->setURL('/modules/TawasulPlanner/units.php')
                ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                ->addParam('tawasulCourseClassID', $tawasulCourseClassID)
                ->addParam('tawasulCourseID', $lessonData['tawasulCourseID'] ?? '')
                ->setIcon('squares-plus')
                ->displayLabel();
        }

        if ($editAccess) {
            $table->addHeaderAction('add', __('Add'))
                ->setURL('/modules/TawasulPlanner/planner_add.php')
                ->addParam('tawasulCourseClassID', $tawasulCourseClassID)
                ->addParam('tawasulPersonID', $tawasulPersonID)
                ->addParam('date', $date)
                ->addParam('viewBy', $viewBy)
                ->displayLabel();
        }

        if ($viewBy == 'year') {
            $this->addYearOverviewColumns($table);
        } else {
            $this->addLessonViewColumns($table, $viewBy);
        }

        $table->addActionColumn()
            ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->addParam('tawasulPersonID', $tawasulPersonID)
            ->addParam('tawasulPlannerEntryID')
            ->addParam('tawasulCourseClassID')
            ->addParam('date')
            ->addParam('timeStart')
            ->addParam('timeEnd')
            ->addParam('search', $tawasulPersonID)
            ->addParam('viewBy', $viewBy == 'date' ? 'date' : 'class')
            ->addParam('subView', $viewBy == 'year' ? 'year' : 'lesson')
            ->format(function ($values, $actions) use ($editAccess, $highestAction, $viewBy) {
                $fullEditAccess = $editAccess && ($highestAction == 'Lesson Planner_viewEditAllClasses' || !empty($values['isTeacher']));

                if (!empty($values['closure'])) return;

                if (empty($values['lesson'])) {
                    if ($fullEditAccess) {
                        $actions->addAction('add', __('Add'))
                            ->setURL('/modules/TawasulPlanner/planner_add.php');
                        return;
                    }
                }

                if ($fullEditAccess) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulPlanner/planner_edit.php');

                    $actions->addAction('view', __('View'))
                        ->setURL('/modules/TawasulPlanner/planner_view_full.php');
                } else {
                    $actions->addAction('view', __('View'))
                        ->setURL('/modules/TawasulPlanner/planner_view_full.php');
                }

                if ($fullEditAccess && ($viewBy == 'class' || $viewBy == 'year')) {
                    $actions->addAction('copyforward', __('Bump'))
                        ->setURL('/modules/TawasulPlanner/planner_bump.php');
                }

                if ($editAccess) {
                    $actions->addAction('duplicate', __('Duplicate'))
                        ->setURL('/modules/TawasulPlanner/planner_duplicate.php');
                }

                if ($fullEditAccess) {
                    $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/TawasulPlanner/planner_delete.php');
                }
            });

        return $table;
    }

    protected function addLessonViewColumns(&$table, $viewBy)
    {
        if ($viewBy == 'class') {
            $table->addColumn('date', __('Date'))
                ->sortable(['date'])
                ->context('primary')
                ->format(function ($values) {
                    $output = Format::bold(Format::date($values['date'] ?? '')).'<br/>'
                        .Format::dayOfWeekName($values['date'] ?? '');
                    
                    return $output;
                });
        } else {
            $table->addColumn('class', __('Class'))
                ->sortable(['course', 'class'])
                ->context('primary')
                ->format(function ($values) {
                    return Format::bold(Format::courseClassName($values['course'], $values['class'])).'<br/>'
                        .Format::small(Format::date($values['date']));
                });
        }

        $table->addColumn('lesson', __('Lesson'))
            ->description(__('Unit'))
            ->context('primary')
            ->format(function ($values) {
                return !empty($values['unit'])
                    ? Format::bold($values['lesson']).'<br/>'.Format::small($values['unit'])
                    : Format::bold($values['lesson']);
            });

        $table->addColumn('timeStart', __('Time'))
            ->context('secondary')
            ->format(function ($values) {
                return Format::timeRange($values['timeStart'], $values['timeEnd']);
            });
          
        $table->addColumn('homework', __($this->homeworkNameSingular))
            ->format(function ($values) {
                $output = '';
                if ($values['homework'] == 'N' && empty($values['myHomeworkDueDateTime'])) {
                    $output .= __('No');
                } else {
                    if ($values['homework'] == 'Y') {
                        $output .= __('Yes').': '.__('Teacher Recorded').'<br/>';
                        if ($values['homeworkSubmission'] == 'Y') {
                            $output .= Format::small('+'.__('Submission')).'<br/>';
                            if ($values['homeworkCrowdAssess'] == 'Y') {
                                $output .= Format::small('+'.__('Crowd Assessment')).'<br/>';
                            }
                        }
                    }
                    if (!empty($values['myHomeworkDueDateTime'])) {
                        $output .= __('Yes').': '.__('Student Recorded').'</br>';
                    }
                }

                return $output;
            });

        $table->addColumn('viewableStudents', __('Access'))
            ->format(function ($values) {
                $viewableBy = [];
                if ($values['viewableStudents'] == 'Y') $viewableBy[] = __('Students');
                if ($values['viewableParents'] == 'Y') $viewableBy[] = __('Parents');

                return implode(', ', $viewableBy);
            });
    }

    protected function addYearOverviewColumns(&$table)
    {
        $table->addColumn('lessonNumber', __('Lesson<br/>Number'))
            ->notSortable()
            ->format(function ($values) {
                $output = !empty($values['offTimetableDay']) ? Format::bold($values['lessonNumber']).'<br/>'.Format::small($values['offTimetableDay']) : Format::bold($values['lessonNumber']);
                return $output;
            });

        $table->addColumn('date', __('Date'))
            ->description(__('Month'))
            ->sortable(['date'])
            ->context('primary')
            ->format(function ($values) {
                if (!empty($values['closure'])) return $values['closure'];

                $output = Format::bold(Format::date($values['date'])).'<br/>'.Format::monthName($values['date']);
                
                return $output;
            });

        $table->addColumn('period', __('TT Period'))
            ->description(__('Time')." & ".__('Facility'))
            ->context('secondary')
            ->format(function ($values) {
                if (empty($values['period'])) return;

                return $values['period'].'<br/>'.Format::small(Format::timeRange($values['timeStart'], $values['timeEnd'])).'<br/>'.Format::small($values['spaceName']);
            });

        $table->addColumn('lesson', __('Planned Lesson'))
            ->description(__('Unit'))
            ->context('primary')
            ->format(function ($values) {
                if (empty($values['lesson'])) return;

                return !empty($values['unit'])
                    ? Format::bold($values['lesson']).'<br/>'.Format::small($values['unit'])
                    : Format::bold($values['lesson']);
            });
    }

    protected function addSchoolClosureDates($tawasulSchoolYearID, array $lessons) : array
    {
        $terms = $this->schoolYearTermGateway->selectTermDetailsBySchoolYear($tawasulSchoolYearID)->fetchGroupedUnique();
        $closures = $this->schoolYearTermGateway->selectSchoolClosuresByTerm(array_keys($terms), true)->fetchGroupedUnique();
        $offTimetables = $this->schoolYearTermGateway->selectOffTimetablesByTerm(array_keys($terms), false)->fetchAll();

        $lessonData = [];
        $lessonCount = count($lessons);

        foreach ($lessons as $lessonIndex => $lesson) {
            
            foreach ($terms as $termID => $term) {
                if ($term['firstDay'] !== false && $lesson['date'] > $term['firstDay']) {
                    $lessonData[] = [
                        'lessonNumber' => __('Start of').' '.$term['name'],
                        'closure'      => Format::date($term['firstDay']),
                    ];
                    $terms[$termID]['firstDay'] = false;
                }

                if ($term['lastDay'] !== false && $lesson['date'] > $term['lastDay']) {
                    $lessonData[] = [
                        'lessonNumber' => __('End of').' '.$term['name'],
                        'closure'      => Format::date($term['lastDay']),
                    ];
                    $terms[$termID]['lastDay'] = false;
                }
            }

            foreach ($closures as $firstDay => $closure) {
                if ($closure !== false && $lesson['date'] > $firstDay && $closure['type'] == 'School Closure') {
                    $lessonData[] = [
                        'lessonNumber' => $closure['name'],
                        'closure' => Format::dateRange($closure['firstDay'], $closure['lastDay']),
                    ];
                    $closures[$firstDay] = false;
                }
            }

            $index = array_search($lesson['date'], array_column($offTimetables, 'date'));
            if ($index !== false) {
                $classOffTimetable = $this->schoolYearSpecialDayGateway->getIsClassOffTimetableByDate($tawasulSchoolYearID, $lesson['tawasulCourseClassID'], $lesson['date']);
                if ($classOffTimetable) {
                    $lesson['offTimetableDay'] = $offTimetables[$index]['name'];
                }
            }


            $lessonData[] = $lesson;

            if ($lessonIndex == $lessonCount-1) {
                $finalTerm = end($terms);
                if ($finalTerm['lastDay'] !== false) {
                    $lessonData[] = [
                        'lessonNumber' => __('End of').' '.$finalTerm['name'],
                        'closure' => Format::date($finalTerm['lastDay']),
                    ];
                }
            }
        }

        return $lessonData;
    }

    protected function getViewingAs($highestAction, $roleCategory)
    {
        if ($highestAction == 'Lesson Planner_viewMyChildrensClasses' || $roleCategory == 'Parent') {
            return 'Parent';
        } else if ($roleCategory == 'Student') {
            return 'Student';
        } else if ($highestAction == 'Lesson Planner_viewAllEditMyClasses') {
            return 'Teacher';
        } else if ($roleCategory == 'Student') {
            return $roleCategory;
        }
    }

    protected function getEditAccess($highestAction, $roleCategory)
    {
        return $roleCategory == 'Staff' && ($highestAction == 'Lesson Planner_viewEditAllClasses' || $highestAction == 'Lesson Planner_viewAllEditMyClasses');
    }
}
