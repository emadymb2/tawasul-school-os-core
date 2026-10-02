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

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\Behaviour\BehaviourGateway;
use TawasulOS\Domain\Planner\PlannerEntryGateway;
use TawasulOS\Domain\Timetable\CourseEnrolmentGateway;
use TawasulOS\Domain\Behaviour\BehaviourFollowUpGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$settingGateway = $container->get(SettingGateway::class);
$enableDescriptors = $settingGateway->getSettingByScope('Behaviour', 'enableDescriptors');
$enableLevels = $settingGateway->getSettingByScope('Behaviour', 'enableLevels');
$behaviourGateway = $container->get(BehaviourGateway::class);

if (isActionAccessible($guid, $connection2, '/modules/TawasulBehaviour/behaviour_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get action with highest precedence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        // Proceed!
        $page->breadcrumbs
            ->add(__('Manage Behaviour Records'), 'behaviour_manage.php')
            ->add(__('Edit'));
        
        $tawasulBehaviourID = $_GET['tawasulBehaviourID'] ?? null;
        $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
        $tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
        $tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';
        $type = $_GET['type'] ?? '';
        
        // Check if tawasulBehaviourID specified
        $tawasulBehaviourID = $_GET['tawasulBehaviourID'] ?? '';
        if ($tawasulBehaviourID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {

            if ($highestAction == 'Manage Behaviour Records_all') {
                $values = $behaviourGateway->getBehaviourDetails($session->get('tawasulSchoolYearID'), $tawasulBehaviourID);
                $canEdit = true;
            } elseif ($highestAction == 'Manage Behaviour Records_my') {
                $values = $behaviourGateway->getBehaviourDetailsByCreator($session->get('tawasulSchoolYearID'), $tawasulBehaviourID, $session->get('tawasulPersonID'));
                $canEdit = true;
            }

            if (empty($values) && ($highestAction == 'Manage Behaviour Records_all' || $highestAction == 'Manage Behaviour Records_my')) {
                $values = $behaviourGateway->getBehaviourDetails($session->get('tawasulSchoolYearID'), $tawasulBehaviourID);
                $canEdit = false;
            }
            
            if (empty($values)) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                // Let's go!
                $form = Form::create('addform', $session->get('absoluteURL').'/modules/TawasulBehaviour/behaviour_manage_editProcess.php?tawasulBehaviourID='.$tawasulBehaviourID.'&tawasulPersonID='.$tawasulPersonID.'&tawasulFormGroupID='.$tawasulFormGroupID.'&tawasulYearGroupID='.$tawasulYearGroupID.'&type='.$type);
                
                $form->setFactory(DatabaseFormFactory::create($pdo));
                
                $policyLink = $settingGateway->getSettingByScope('Behaviour', 'policyLink');
                if (!empty($policyLink)) {
                    $form->addHeaderAction('viewPolicy', __('View Behaviour Policy'))
                        ->setExternalURL($policyLink);
                }
                if (!empty($tawasulPersonID) or !empty($tawasulFormGroupID) or !empty($tawasulYearGroupID) or !empty($type)) {
                    $form->addHeaderAction('back', __('Back to Search Results'))
                        ->setURL('/modules/TawasulBehaviour/behaviour_manage.php')
                        ->setIcon('search')
                        ->displayLabel()
                        ->addParam('tawasulPersonID', $tawasulPersonID)
                        ->addParam('tawasulFormGroupID', $_GET['tawasulFormGroupID'])
                        ->addParam('tawasulYearGroupID', $_GET['tawasulYearGroupID'])
                        ->addParam('type', $_GET['type'])
                        ->prepend((!empty($policyLink)) ? ' | ' : '');
                }

                if (!empty($tawasulPersonID)) {
                    $form->addHeaderAction('view', __('View Behaviour Records'))
                        ->setURL('/modules/TawasulBehaviour/behaviour_view_details.php')
                        ->displayLabel()
                        ->addParam('tawasulPersonID', $tawasulPersonID);
                }
            
                $form->addHiddenValue('address', "/modules/TawasulBehaviour/behaviour_manage_add.php");
                $form->addRow()->addHeading('Basic Information', __('Basic Information'));


                // To show other students involved in the incident
                if(!empty($values['tawasulMultiIncidentID'])) {
                    $students = $behaviourGateway->selectMultipleStudentsOfOneIncident($values['tawasulMultiIncidentID'])->fetchAll();
                }

                // Student
                $row = $form->addRow();
                    $row->addLabel('students', __('Student'));
                    $row->addTextField('students')->setValue(Format::name('', $values['preferredNameStudent'], $values['surnameStudent'], 'Student'))->readonly();
                    $form->addHiddenValue('tawasulPersonID', $values['tawasulPersonID']);

                // Other Students
                if (!empty($values['tawasulMultiIncidentID'])) {
                    $row = $form->addRow();
                    $row->addLabel('otherStudents0', __('Other Students Involved'));
                    $col = $row->addColumn();
                    
                    $studentNames = [];
                    foreach ($students as $i => $student) {
                        if ($student['tawasulPersonID'] != $values['tawasulPersonID']) {
                            $url = Url::fromModuleRoute('TawasulStudents', 'student_view_details')
                                ->withQueryParams(['tawasulPersonID' => $student['tawasulPersonID'], 'subpage' => 'Behaviour']);

                            $studentNames[] =  '<span class="font-bold">'.Format::link($url, Format::name('', $student['preferredNameStudent'], $student['surnameStudent'], 'Student', false, true)).'</span>';
                        }
                    }
                    $col->addContent(implode(',&nbsp;', $studentNames));
                }

                // Date
                $row = $form->addRow();
                	$row->addLabel('date', __('Date'));
                	$row->addDate('date')->setValue(Format::date($values['date']))->required()->readonly();

                // Type
                $row = $form->addRow();
                    $row->addLabel('type', __('Type'));
                    $row->addSelect('type')->fromArray(['Negative' => __('Negative'), 'Positive' => __('Positive'), 'Observation' => __('Observation')])->selected($values['type'])->required()->readonly();

                // Descriptor
                if ($enableDescriptors == 'Y') {
                    if ($values['type'] == 'Negative') {
                        $descriptors = $settingGateway->getSettingByScope('Behaviour', 'negativeDescriptors');
                    } elseif ($values['type'] == 'Positive') {
                        $descriptors = $settingGateway->getSettingByScope('Behaviour', 'positiveDescriptors');
                    } elseif ($values['type'] == 'Observation') {
                        $descriptors = $settingGateway->getSettingByScope('Behaviour', 'observationDescriptors');
                    }

                    $descriptors = (!empty($descriptors))? explode(',', $descriptors) : array();

                    $row = $form->addRow();
                        $row->addLabel('descriptor', __('Descriptor'));
                        $row->addSelect('descriptor')
                            ->fromArray($descriptors)
                            ->selected($values['descriptor'])
                            ->required()
                            ->readOnly(!$canEdit)
                            ->placeholder();
                }

                // Level
                if ($enableLevels == 'Y') {
                    $optionsLevels = $settingGateway->getSettingByScope('Behaviour', 'levels');
                    if ($optionsLevels != '') {
                        $optionsLevels = explode(',', $optionsLevels);
                    }
                    $row = $form->addRow();
                    	$row->addLabel('level', __('Level'));
                    	$row->addSelect('level')->fromArray($optionsLevels)->selected($values['level'])->placeholder();
                }

                $form->addRow()->addHeading('Details', __('Details'));

                // Incident
                if ($canEdit) {
                    $row = $form->addRow();
                        $column = $row->addColumn();
                        $column->addLabel('comment', __('Incident'));
                        $column->addTextArea('comment')->setRows(5)->setClass('w-full')->setValue($values['comment']);
                } else {
                    $row = $form->addRow();
                        $column = $row->addColumn();
                        $column->addLabel('comment', __('Incident'));
                        $column->addContent('<p class="text-sm leading-4">'.$values['comment'].'</p>');
                }
                $logs = [];

                // Print old-style followup as first log entry
                if (!empty($values['followup'])) {
                    $logs[] = [
                        'comment'       => $values['followup'],
                        'timestamp'     => $values['timestamp'],
                        'surname'       => $values['surnameCreator'],
                        'preferredName' => $values['preferredNameCreator'],
                        'image_240'     => $values['imageCreator'],
                    ];
                }

                // Print follow-up as log
                $behaviourGateway = $container->get(BehaviourGateway::class);
                $behaviourFollowUpGateway = $container->get(BehaviourFollowUpGateway::class);
                $logs = array_merge($logs, $behaviourFollowUpGateway->selectFollowUpByBehaviourID($tawasulBehaviourID)->fetchAll());

                if (!empty($logs) ) {
                    $column = $form->addRow()->addColumn();
                    $column->addLabel('followUpLog', __('Follow Up'))->addClass('-mb-4');
                    $form->addRow()->addContent($page->fetchFromTemplate('ui/discussion.twig.html', [
                    'discussion' => $logs
                ]));
                }

                // Allow entry of fresh followup
                $row = $form->addRow();
                    $column = $row->addColumn();
                    $column->addLabel('followUp', (empty($logs) ? __('Follow Up') : __('Further Follow Up')));
                    $column->addTextArea('followUp')->setRows(8)->setClass('w-full');
                
                // Lesson link
                $lessons = [];
                $minDate = date('Y-m-d', (strtotime($values['date']) - (24 * 60 * 60 * 30)));
                $resultSelect = $container->get(PlannerEntryGateway::class)->selectPlannerLessonsByStudent($session->get('tawasulSchoolYearID'), $values['tawasulPersonID'], date('Y-m-d', strtotime($values['date'])), $minDate);

                while ($rowSelect = $resultSelect->fetch()) {
                    $show = true;
                    if ($highestAction == 'Manage Behaviour Records_my') {
                        $resultShow = $container->get(CourseEnrolmentGateway::class)->selectBy(['tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $rowSelect['tawasulCourseClassID'], 'role' => 'Teacher']);

                        if ($resultShow->rowCount() != 1) {
                            $show = false;
                        }
                    }

                    if ($show == true) {
                        $submission = '';
                        if ($rowSelect['homework'] == 'Y') {
                            $submission = 'HW';
                            if ($rowSelect['homeworkSubmission'] == 'Y') {
                                $submission .= '+OS';
                            }
                        }

                        if ($submission != '') {
                            $submission = ' - '.$submission;
                        }

                        $selected = '';
                        if ($rowSelect['tawasulPlannerEntryID'] == $values['tawasulPlannerEntryID']) {
                            $selected = 'selected';
                        }

                        $lessons[$rowSelect['tawasulPlannerEntryID']] = htmlPrep($rowSelect['course']).'.'.htmlPrep($rowSelect['class']).' '.htmlPrep($rowSelect['lesson']).' - '.substr(Format::date($rowSelect['date']), 0, 5).$submission;
                    }
                }
                
                if ($canEdit) {
                    $row = $form->addRow();
                        $row->addLabel('tawasulPlannerEntryID', __('Link To Lesson?'))->description(__('From last 30 days'));
                        $row->addSelect('tawasulPlannerEntryID')->fromArray($lessons ?? [])->placeholder()->selected($values['tawasulPlannerEntryID']);

                    // Behaviour link
                    if(empty($values['tawasulMultiIncidentID'])) {
                        $resultSelect = $behaviourGateway->selectBehavioursByCreator($session->get('tawasulSchoolYearID'), $session->get('tawasulPersonID'), $tawasulBehaviourID);
                        $behaviours = $resultSelect->fetchKeyPair();

                        $row = $form->addRow();
                            $row->addLabel('tawasulBehaviourLinkToID', __('Link To Other Existing Behaviour'))->description(__('From last 30 days'));
                            $row->addSelect('tawasulBehaviourLinkToID')->fromArray($behaviours)->placeholder();
                    }
                
                    // CUSTOM FIELDS
                    $container->get(CustomFieldHandler::class)->addCustomFieldsToForm($form, 'Behaviour', [], $values['fields']);
                }
                
                $row = $form->addRow();
                    $row->addFooter();
                    $row->addSubmit();

                echo $form->getOutput();
            }
        }
    }
}
