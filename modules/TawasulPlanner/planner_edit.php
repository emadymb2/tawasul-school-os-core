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
use Tos\Module\TawasulPlanner\Forms\PlannerFormFactory;
use TawasulOS\Services\Format;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Domain\Planner\PlannerEntryGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Set variables
        $today = date('Y-m-d');

        $settingGateway = $container->get(SettingGateway::class);
        $homeworkNameSingular = $settingGateway->getSettingByScope('Planner', 'homeworkNameSingular');
        $homeworkNamePlural = $settingGateway->getSettingByScope('Planner', 'homeworkNamePlural');

        //Proceed!
        //Get viewBy, date and class variables
        $params = [];
        $viewBy = null;
        if (isset($_GET['viewBy'])) {
            $viewBy = $_GET['viewBy'] ?? '';
        }
        $subView = null;
        if (isset($_GET['subView'])) {
            $subView = $_GET['subView'] ?? '';
        }
        if ($viewBy != 'date' and $viewBy != 'class') {
            $viewBy = 'date';
        }
        $date = $_GET['date'] ?? '';
        $dateStamp = null;
        if ($viewBy == 'date') {
            if (isset($_GET['dateHuman'])) {
                $date = Format::dateConvert($_GET['dateHuman']);
            }
            if ($date == '') {
                $date = date('Y-m-d');
            }
            [$dateYear, $dateMonth, $dateDay] = explode('-', $date);
            $dateStamp = mktime(0, 0, 0, $dateMonth, $dateDay, $dateYear);
            $params += [
                'viewBy' => 'date',
                'date' => $date,
            ];
        } elseif ($viewBy == 'class') {
            $class = null;
            if (isset($_GET['class'])) {
                $class = $_GET['class'] ?? '';
            }
            $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
            $params += [
                'viewBy' => 'class',
                'date' => $date,
                'tawasulCourseClassID' => $tawasulCourseClassID,
                'subView' => $subView,
            ];
        }
        $paramsVar = '&' . http_build_query($params); // for backward compatibile uses below (should be get rid of)

        [$todayYear, $todayMonth, $todayDay] = explode('-', $today);
        $todayStamp = mktime(12, 0, 0, $todayMonth, $todayDay, $todayYear);

        //Check if tawasulPlannerEntryID and tawasulCourseClassID specified
        $tawasulCourseClassID = null;
        if (isset($_GET['tawasulCourseClassID'])) {
            $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
        }
        $tawasulPlannerEntryID = $_GET['tawasulPlannerEntryID'] ?? '';
        if ($tawasulPlannerEntryID == '' or ($viewBy == 'class' and $tawasulCourseClassID == 'Y')) {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            try {
                if ($viewBy == 'date') {
                    if ($highestAction == 'Lesson Planner_viewEditAllClasses') {
                        $data = array('date' => $date, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                        $sql = 'SELECT tawasulCourse.tawasulCourseID, tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourse.tawasulDepartmentID, tawasulPlannerEntry.*, tawasulCourse.tawasulYearGroupIDList FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE date=:date AND tawasulPlannerEntryID=:tawasulPlannerEntryID';
                    } else {
                        $data = array('date' => $date, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                        $sql = "SELECT tawasulCourse.tawasulCourseID, tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourse.tawasulDepartmentID, tawasulPlannerEntry.*, tawasulCourse.tawasulYearGroupIDList FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND date=:date AND tawasulPlannerEntryID=:tawasulPlannerEntryID";
                    }
                } else {
                    if ($highestAction == 'Lesson Planner_viewEditAllClasses') {
                        $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                        $sql = 'SELECT tawasulCourse.tawasulCourseID, tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourse.tawasulDepartmentID, tawasulPlannerEntry.*, tawasulCourse.tawasulYearGroupIDList FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPlannerEntryID=:tawasulPlannerEntryID';
                    } else {
                        $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                        $sql = "SELECT tawasulCourse.tawasulCourseID, tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourse.tawasulDepartmentID, tawasulPlannerEntry.*, tawasulCourse.tawasulYearGroupIDList FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPlannerEntryID=:tawasulPlannerEntryID";
                    }
                }
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
            }

            if ($result->rowCount() != 1) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                //Let's go!
                $values = $result->fetch();
                $fields = !empty($values['fields'])? json_decode($values['fields'], true) : [];

                if ($viewBy == 'date') {
                    $extra = Format::date($date);
                } else {
                    $extra = $values['course'].'.'.$values['class'];
                    
                }
                $tawasulDepartmentID = $values['tawasulDepartmentID'] ?? '';
                $tawasulYearGroupIDList = $values['tawasulYearGroupIDList'];

                $page->breadcrumbs
                    ->add(__('Planner for {classDesc}', [
                        'classDesc' => $extra,
                    ]), 'planner.php', $params)
                    ->add(__('Edit Lesson Plan'));

                //Get tawasulUnitClassID
                $tawasulUnitID = $values['tawasulUnitID'];
                $tawasulUnitClassID = null;

                $dataUnitClass = array('tawasulCourseClassID' => $values['tawasulCourseClassID'], 'tawasulUnitID' => $tawasulUnitID);
                $sqlUnitClass = 'SELECT tawasulUnitClassID FROM tawasulUnitClass WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulUnitID=:tawasulUnitID';
                $resultUnitClass = $connection2->prepare($sqlUnitClass);
                $resultUnitClass->execute($dataUnitClass);
                if ($resultUnitClass->rowCount() == 1) {
                    $rowUnitClass = $resultUnitClass->fetch();
                    $tawasulUnitClassID = $rowUnitClass['tawasulUnitClassID'];
                }

                $dataMarkbook = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                $sqlMarkbook = 'SELECT mb.tawasulMarkbookColumnID FROM tawasulMarkbookColumn AS mb WHERE :tawasulPlannerEntryID=mb.tawasulPlannerEntryID';
                $tawasulMarkbookColumnID = $pdo->selectOne($sqlMarkbook, $dataMarkbook);

                $returns = array();
                $returns['success1'] = __('Your request was completed successfully.').__('You can now edit more details of your newly duplicated entry.');
                $page->return->addReturns($returns);

                $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/planner_editProcess.php?tawasulPlannerEntryID=$tawasulPlannerEntryID&viewBy=$viewBy&subView=$subView&address=".$session->get('address'));
                $form->setFactory(PlannerFormFactory::create($pdo));
                $form->addMeta()->addDefaultContent('editProcess');

                $form->addHiddenValue('address', $session->get('address'));
                
                if (!empty($tawasulMarkbookColumnID)) {
                    $form->addHeaderAction('markbook', __('Linked Markbook'))
                        ->setURL('/modules/TawasulMarkbook/markbook_edit_data.php')
                        ->addParam('tawasulMarkbookColumnID', $tawasulMarkbookColumnID)
                        ->addParams($params)
                        ->displayLabel();
                }
                
                $params["tawasulPlannerEntryID"] = $tawasulPlannerEntryID;
                $form->addHeaderAction('view', __('View'))
                    ->setURL('/modules/TawasulPlanner/planner_view_full.php')
                    ->addParams($params)
                    ->setIcon('plus')
                    ->displayLabel();

                // Try and find the tawasulTTDayRowClassID for this lesson
                if (empty($values['tawasulTTDayRowClassID']) && !empty($values['date']) && !empty($values['timeStart']) && !empty($values['timeEnd'])) {
                    $lesson = $container->get(PlannerEntryGateway::class)->getPlannerTTByClassTimes($tawasulCourseClassID, $values['date'], $values['timeStart'], $values['timeEnd']);
                    $values['tawasulTTDayRowClassID'] = $lesson['tawasulTTDayRowClassID'] ?? '';
                    $form->addHiddenValue('tawasulTTDayRowClassID', $values['tawasulTTDayRowClassID']);
                }

                
                //BASIC INFORMATION
                $form->addRow()->addHeading('Basic Information', __('Basic Information'));

                if ($highestAction == 'Lesson Planner_viewEditAllClasses') {
                    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                    $sql = 'SELECT tawasulCourseClass.tawasulCourseClassID AS value, CONCAT(tawasulCourse.nameShort,".", tawasulCourseClass.nameShort) AS name FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY name';
                } else {
                    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'));
                    $sql = 'SELECT tawasulCourseClass.tawasulCourseClassID AS value, CONCAT(tawasulCourse.nameShort,".", tawasulCourseClass.nameShort) AS name FROM tawasulCourseClassPerson JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID ORDER BY name';
                }
                $row = $form->addRow();
                    $row->addLabel('tawasulCourseClassID', __('Class'));
                    $row->addSearchSelect('tawasulCourseClassID')->fromQuery($pdo, $sql, $data)->required()->placeholder();

                $sql = "SELECT GROUP_CONCAT(tawasulCourseClassID SEPARATOR ' ') AS chainedTo, tawasulUnit.tawasulUnitID as value, name FROM tawasulUnit JOIN tawasulUnitClass ON (tawasulUnit.tawasulUnitID=tawasulUnitClass.tawasulUnitID) WHERE active='Y' AND running='Y'  GROUP BY tawasulUnit.tawasulUnitID ORDER BY ordering, name";
                $row = $form->addRow();
                    $row->addLabel('tawasulUnitID', __('Unit'));
                    $row->addSelect('tawasulUnitID')->fromQueryChained($pdo, $sql, [], 'tawasulCourseClassID')->placeholder();

                $row = $form->addRow();
                    $row->addLabel('name', __('Lesson Name'));
                    $row->addTextField('name')->setValue()->maxLength(50)->required();

                $row = $form->addRow();
                    $row->addLabel('summary', __('Summary'));
                    $row->addTextField('summary')->setValue()->maxLength(255);

                $row = $form->addRow();
                    $row->addLabel('date', __('Date'));
                    $row->addDate('date')->required();

                $nextTimeStart = !empty($nextTimeStart) ? substr($nextTimeStart, 0, 5) : null;
                $row = $form->addRow();
                    $row->addLabel('timeStart', __('Start Time'));
                    $row->addTime('timeStart')->required();

                $nextTimeEnd = !empty($nextTimeEnd) ? substr($nextTimeEnd, 0, 5) : null;
                $row = $form->addRow();
                    $row->addLabel('timeEnd', __('End Time'));
                    $row->addTime('timeEnd')->required();

                if (empty($values['tawasulTTDayRowClassID'])) {
                    $row = $form->addRow();
                        $row->addLabel('tawasulSpaceID', __('Location'));
                        $row->addSelectSpace('tawasulSpaceID')
                            ->placeholder();
                }


                //LESSON
                $form->addRow()->addHeading('Lesson Content', __('Lesson Content'));

                $description = $settingGateway->getSettingByScope('Planner', 'lessonDetailsTemplate') ;
                $row = $form->addRow();
                    $column = $row->addColumn();
                    $column->addLabel('description', __('Lesson Details'));
                    $column->addEditor('description', $guid)->setRows(20)->showMedia()->setValue($description);

                $teachersNotes = $settingGateway->getSettingByScope('Planner', 'teachersNotesTemplate');
                $row = $form->addRow();
                    $column = $row->addColumn();
                    $column->addLabel('teachersNotes', __('Teacher\'s Notes'));
                    $column->addEditor('teachersNotes', $guid)->setRows(5)->showMedia()->setValue($teachersNotes);

                //SMART BLOCKS
                if (!empty($values['tawasulUnitID'])) {
                    $row = $form->addRow()->setClass('sm:items-center');
                    $row->addHeading('Smart Blocks', __('Smart Blocks'));
                    $row->addContent();
                    $row->addAction('edit', __('Edit Unit'))
                        ->addClass('text-right')
                        ->setURL('/modules/TawasulPlanner/units_edit_working.php')
                        ->setAttribute('target', '_blank')
                        ->addParams(['tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulCourseID' => $values['tawasulCourseID'], 'tawasulUnitID' => $values['tawasulUnitID'], 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulUnitClassID' => $tawasulUnitClassID]);

                    $row = $form->addRow();
                        $customBlocks = $row->addPlannerSmartBlocks('smart', $session, false);

                    $dataBlocks = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                    $sqlBlocks = 'SELECT * FROM tawasulUnitClassBlock WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID ORDER BY sequenceNumber';
                    $resultBlocks = $pdo->select($sqlBlocks, $dataBlocks);

                    while ($rowBlocks = $resultBlocks->fetch()) {
                        $smart = array(
                            'title' => $rowBlocks['title'],
                            'type' => $rowBlocks['type'],
                            'length' => $rowBlocks['length'],
                            'contents' => $rowBlocks['contents'],
                            'teachersNotes' => $rowBlocks['teachersNotes'],
                            'tawasulUnitClassBlockID' => $rowBlocks['tawasulUnitClassBlockID']
                        );
                        $customBlocks->addBlock($rowBlocks['tawasulUnitClassBlockID'], $smart);
                    }
                }

                //HOMEWORK
                $form->addRow()->addHeading('Homework', __($homeworkNameSingular));

                $form->toggleVisibilityByClass('homework')->onClick('homework')->when('Y');
                $row = $form->addRow();
                    $row->addLabel('homework', __('Add {homeworkName}?', ['homeworkName' => __($homeworkNameSingular)]));
                    $row->addYesNo('homework')->required()->checked('N');

                if (!empty($values['homeworkDueDateTime'])) {
                    $values['homeworkDueDate'] = substr(Format::date($values['homeworkDueDateTime'], 'Y-m-d H:i:s'), 0, 10);
                    $values['homeworkDueDateTime'] = substr($values['homeworkDueDateTime'], 11, 5);
                }

                $row = $form->addRow()->addClass('homework');
                    $row->addLabel('homeworkDueDate', __('Due Date'))->description(__('Date is required, time is optional.'));
                    $col = $row->addColumn('homeworkDueDate')->addClass('homework');
                    $col->addDate('homeworkDueDate')->addClass('mr-2')->required();
                    $col->addTime('homeworkDueDateTime');

                $row = $form->addRow()->addClass('homework');
                $row->addLabel('homeworkTimeCap', __('Time Cap?'))->description(__('The maximum time, in minutes, for students to work on this.'));
                    $row->addNumber('homeworkTimeCap');

                $row = $form->addRow()->addClass('homework');
                    $column = $row->addColumn();
                    $column->addLabel('homeworkDetails', __('{homeworkName} Details', ['homeworkName' => __($homeworkNameSingular)]));
                    $column->addEditor('homeworkDetails', $guid)->setRows(5)->showMedia()->setValue($description)->required();

                $form->toggleVisibilityByClass('homeworkSubmission')->onClick('homeworkSubmission')->when('Y');
                $row = $form->addRow()->addClass('homework');
                    $row->addLabel('homeworkSubmission', __('Online Submission?'));
                    $row->addYesNo('homeworkSubmission')->required()->checked('N');

                $values['homeworkSubmissionDateOpen'] = (!empty($values['homeworkSubmissionDateOpen'])) ? $values['homeworkSubmissionDateOpen'] : date('Y-m-d') ;
                $row = $form->addRow()->setClass('homeworkSubmission');
                    $row->addLabel('homeworkSubmissionDateOpen', __('Submission Open Date'));
                    $row->addDate('homeworkSubmissionDateOpen')->required();

                $row = $form->addRow()->setClass('homeworkSubmission');
                    $row->addLabel('homeworkSubmissionDrafts', __('Drafts'));
                    $row->addSelect('homeworkSubmissionDrafts')->fromArray(array('' => __('None'), '1' => __('1'), '2' => __('2'), '3' => __('3')));

                $row = $form->addRow()->setClass('homeworkSubmission');
                    $row->addLabel('homeworkSubmissionType', __('Submission Type'));
                    $row->addSelect('homeworkSubmissionType')->fromArray(array('Link' => __('Link'), 'File' => __('File'), 'Link/File' => __('Link/File')))->required();

                $row = $form->addRow()->setClass('homeworkSubmission');
                    $row->addLabel('homeworkSubmissionRequired', __('Submission Required'));
                    $row->addSelect('homeworkSubmissionRequired')->fromArray(array('Optional' => __('Optional'), 'Required' => __('Required')))->required();

                if (isActionAccessible($guid, $connection2, '/modules/TawasulCrowdAssessment/crowdAssess.php')) {
                    $form->toggleVisibilityByClass('homeworkCrowdAssess')->onClick('homeworkCrowdAssess')->when('Y');
                    $row = $form->addRow()->addClass('homeworkSubmission');
                        $row->addLabel('homeworkCrowdAssess', __('Crowd Assessment?'));
                        $row->addYesNo('homeworkCrowdAssess')->required();

                    $row = $form->addRow()->addClass('homeworkCrowdAssess');
                        $row->addLabel('homeworkCrowdAssessControl', __('Access Controls?'))->description(__('Decide who can see this homework.'));
                        $column = $row->addColumn()->setClass('flex-col items-end');
                            $column->addCheckbox('homeworkCrowdAssessClassTeacher')->checked(true)->description(__('Class Teacher'))->disabled();
                            $column->addCheckbox('homeworkCrowdAssessClassSubmitter')->checked(true)->description(__('Submitter'))->disabled();
                            $column->addCheckbox('homeworkCrowdAssessClassmatesRead')->setValue('Y')->description(__('Classmates'));
                            $column->addCheckbox('homeworkCrowdAssessOtherStudentsRead')->setValue('Y')->description(__('Other Students'));
                            $column->addCheckbox('homeworkCrowdAssessOtherTeachersRead')->setValue('Y')->description(__('Other Teachers'));
                            $column->addCheckbox('homeworkCrowdAssessSubmitterParentsRead')->setValue('Y')->description(__("Submitter's Parents"));
                            $column->addCheckbox('homeworkCrowdAssessClassmatesParentsRead')->setValue('Y')->description(__("Classmates's Parents"));
                            $column->addCheckbox('homeworkCrowdAssessOtherParentsRead')->setValue('Y')->description(__('Other Parents'));
                }

                // MARKBOOK
                $form->addRow()->addHeading(__('Markbook'));
                // Check database for a linked markbook column
                
                if (!empty($tawasulMarkbookColumnID)) {
                    $row = $form->addRow();
                    $row->addLabel('markbook', __('Markbook Column Already Created'))->description(__('A Markbook column has already been created for this assignment.'));
                } else {
                    $row = $form->addRow();
                    $row->addLabel('markbook', __('Create Markbook Column?'))->description(__('Linked to this lesson by default.'));
                    $row->addYesNo('markbook')->required()->checked('N');
                }

                // OUTCOMES
                $form->addRow()->addHeading('Outcomes', __('Outcomes'));
                $form->addRow()->addContent(__('Link this lesson to outcomes (defined in the Manage Outcomes section of the Planner), and track which outcomes are being met in which lessons.'));

                $allowOutcomeEditing = $settingGateway->getSettingByScope('Planner', 'allowOutcomeEditing');

                $row = $form->addRow();
                    $customBlocks = $row->addPlannerOutcomeBlocks('outcome', $session, $tawasulYearGroupIDList, $tawasulDepartmentID, $allowOutcomeEditing);

                $dataBlocks = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                $sqlBlocks = 'SELECT tawasulPlannerEntryOutcome.*, scope, name, category FROM tawasulPlannerEntryOutcome JOIN tawasulOutcome ON (tawasulPlannerEntryOutcome.tawasulOutcomeID=tawasulOutcome.tawasulOutcomeID) WHERE tawasulPlannerEntryOutcome.tawasulPlannerEntryID=:tawasulPlannerEntryID ORDER BY sequenceNumber';
                $resultBlocks = $pdo->select($sqlBlocks, $dataBlocks);

                while ($rowBlocks = $resultBlocks->fetch()) {
                    $customBlocks->addBlock($rowBlocks['tawasulOutcomeID'], [
                        'outcometitle' => $rowBlocks['name'],
                        'outcometawasulOutcomeID' => $rowBlocks['tawasulOutcomeID'],
                        'outcomecategory' => $rowBlocks['category'],
                        'outcomecontents' => $rowBlocks['content'],
                        'outcometawasulPlannerEntryOutcomeID' => $rowBlocks['tawasulPlannerEntryOutcomeID'],
                    ]);
                }
                

                //Access
                $form->addRow()->addHeading('Access', __('Access'));

                $row = $form->addRow();
                    $row->addLabel('viewableStudents', __('Viewable by Students'));
                    $row->addYesNo('viewableStudents')->required();

                $row = $form->addRow();
                    $row->addLabel('viewableParents', __('Viewable by Parents'));
                    $row->addYesNo('viewableParents')->required();

                $row = $form->addRow()->addClass('advanced');
                    $row->addLabel('videoLink', __('Online Lesson'))->description(__('Displays a video link for online lessons'));
                    $row->addURL('videoLink')->setValue($fields['videoLink'] ?? '');

                //Guests
                $form->addRow()->addHeading('Guests', __('Current Guests'));

                $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                $sql = "SELECT title, preferredName, surname, category, tawasulPlannerEntryGuest.* FROM tawasulPlannerEntryGuest JOIN tawasulPerson ON (tawasulPlannerEntryGuest.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID ORDER BY surname, preferredName";

                $results = $pdo->executeQuery($data, $sql);

                if ($results->rowCount() == 0) {
                    $form->addRow()->addAlert(__('There are no records to display.'), 'error');
                } else {
                    $form->addRow()->addContent('<b>'.__('Warning').'</b>: '.__('If you delete a guest, any unsaved changes to this planner entry will be lost!'))->wrap('<i>', '</i>');

                    $table = $form->addRow()->addTable()->addClass('colorOddEven');

                    $header = $table->addHeaderRow();
                    $header->addContent(__('Name'));
                    $header->addContent(__('Role'));
                    $header->addContent(__('Action'));

                    while ($staff = $results->fetch()) {
                        $row = $table->addRow();
                        $row->addContent(Format::name('', $staff['preferredName'], $staff['surname'], 'Staff', true, true));
                        $row->addContent($staff['role']);
                        $row->addContent("<a onclick='return confirm(\"".__('Are you sure you wish to delete this record?')."\")' href='".$session->get('absoluteURL')."/modules/".$session->get('module')."/planner_edit_guest_deleteProcess.php?tawasulPlannerEntryGuestID=".$staff['tawasulPlannerEntryGuestID']."&tawasulPlannerEntryID=".$tawasulPlannerEntryID."&viewBy=$viewBy&subView=$subView&tawasulCourseClassID=$tawasulCourseClassID&date=$date&address=".$_GET['q']."'><img title='".__('Delete')."' src='./themes/".$session->get('tawasulThemeName')."/img/garbage.png'/></a>");
                    }
                }

                $form->addRow()->addHeading('New Guests', __('New Guests'));

                $row = $form->addRow();
                    $row->addLabel('guests', __('Guest List'));
                    $row->addSelectUsers('guests', $session->get('tawasulSchoolYearID'))->selectMultiple();

                $roles = array(
                    'Guest Student' => __('Guest Student'),
                    'Guest Teacher' => __('Guest Teacher'),
                    'Guest Assistant' => __('Guest Assistant'),
                    'Guest Technician' => __('Guest Technician'),
                    'Guest Parent' => __('Guest Parent'),
                    'Other Guest' => __('Other Guest'),
                );
                $row = $form->addRow();
                    $row->addLabel('role', __('Role'));
                    $row->addSelect('role')->fromArray($roles);

                $row = $form->addRow();
                    $row->addCheckbox('notify')->description(__('Notify all class participants'));
                    $row->addSubmit();

                $form->loadAllValuesFrom($values);

                // CUSTOM FIELDS
                $container->get(CustomFieldHandler::class)->addCustomFieldsToForm($form, 'Lesson Plan', [], $values['fields'] ?? '');

                echo $form->getOutput();

            }
        }
        //Print sidebar
        $session->set('sidebarExtra', sidebarExtra($guid, $connection2, $todayStamp, $session->get('tawasulPersonID'), $dateStamp, $tawasulCourseClassID));
    }
}
