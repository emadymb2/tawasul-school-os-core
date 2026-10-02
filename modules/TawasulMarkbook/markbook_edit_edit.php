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
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

//Get settings
$settingGateway = $container->get(SettingGateway::class);
$enableEffort = $settingGateway->getSettingByScope('Markbook', 'enableEffort');
$enableRubrics = $settingGateway->getSettingByScope('Markbook', 'enableRubrics');
$enableColumnWeighting = $settingGateway->getSettingByScope('Markbook', 'enableColumnWeighting');
$enableRawAttainment = $settingGateway->getSettingByScope('Markbook', 'enableRawAttainment');
$enableGroupByTerm = $settingGateway->getSettingByScope('Markbook', 'enableGroupByTerm');
$attainmentAlternativeName = $settingGateway->getSettingByScope('Markbook', 'attainmentAlternativeName');
$attainmentAlternativeNameAbrev = $settingGateway->getSettingByScope('Markbook', 'attainmentAlternativeNameAbrev');
$effortAlternativeName = $settingGateway->getSettingByScope('Markbook', 'effortAlternativeName');
$effortAlternativeNameAbrev = $settingGateway->getSettingByScope('Markbook', 'effortAlternativeNameAbrev');

if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_edit_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Check if tawasulCourseClassID and tawasulMarkbookColumnID specified
        $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
        $tawasulMarkbookColumnID = $_GET['tawasulMarkbookColumnID'] ?? '';
        if ($tawasulCourseClassID == '' or $tawasulMarkbookColumnID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            try {
                if ($highestAction == 'Edit Markbook_everything') {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse, tawasulCourseClass WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                } elseif ($highestAction == 'Edit Markbook_multipleClassesInDepartment') {
                    $data = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList
                    FROM tawasulCourse
                    JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                    LEFT JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulCourse.tawasulDepartmentID AND tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID)
                    LEFT JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID)
                    WHERE ((tawasulCourseClassPerson.tawasulCourseClassPersonID IS NOT NULL AND tawasulCourseClassPerson.role='Teacher')
                        OR (tawasulDepartmentStaff.tawasulDepartmentStaffID IS NOT NULL AND (tawasulDepartmentStaff.role = 'Coordinator' OR tawasulDepartmentStaff.role = 'Assistant Coordinator' OR tawasulDepartmentStaff.role= 'Teacher (Curriculum)'))
                        )
                    AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class";
                } else {
                    $data = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonID2' => $session->get('tawasulPersonID'), 'tawasulCourseClassID2' => $tawasulCourseClassID, 'tawasulMarkbookColumnID' => $tawasulMarkbookColumnID);
                    $sql = "(SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)
					UNION
					(SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) JOIN tawasulMarkbookColumn ON (tawasulMarkbookColumn.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulMarkbookColumn.tawasulPersonIDCreator=:tawasulPersonID2 AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID2 AND tawasulMarkbookColumnID=:tawasulMarkbookColumnID)
					ORDER BY course, class";
                }
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
            }

            if ($result->rowCount() != 1) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {

                    $data2 = array('tawasulMarkbookColumnID' => $tawasulMarkbookColumnID);
                    $sql2 = 'SELECT * FROM tawasulMarkbookColumn WHERE tawasulMarkbookColumnID=:tawasulMarkbookColumnID';
                    $result2 = $connection2->prepare($sql2);
                    $result2->execute($data2);

                if ($result2->rowCount() != 1) {
                    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                } else {
                    //Let's go!
                    $course = $result->fetch();
                    $values = $result2->fetch();

                    $page->breadcrumbs
                        ->add(
                            __('View {courseClass} Markbook', [
                                'courseClass' => Format::courseClassName($course['course'], $course['class']),
                            ]),
                            'markbook_view.php',
                            [
                                'tawasulCourseClassID' => $tawasulCourseClassID,
                            ]
                        )
                        ->add(__('Edit Column'));

                    if ($values['groupingID'] != '' && ($values['tawasulPersonIDCreator'] != $session->get('tawasulPersonID') && $highestAction != 'Edit Markbook_everything' && $highestAction != 'Edit Markbook_multipleClassesAcrossSchool' && $highestAction != 'Edit Markbook_multipleClassesInDepartment')) {
                        echo "<div class='error'>";
                        echo __('This column is part of a set of columns, which you did not create, and so cannot be individually edited.');
                        echo '</div>';
                    } else {
                        $returns = array();
                        $returns['error6'] = __('Your request failed because you already have one "End of Year" column for this class.');
                        $returns['success1'] = __('Planner was successfully added: you opted to add a linked Markbook column, and you can now do so below.');
                        $page->return->addReturns($returns);

                        $form = Form::create('markbook', $session->get('absoluteURL').'/modules/'.$session->get('module').'/markbook_edit_editProcess.php?tawasulMarkbookColumnID='.$tawasulMarkbookColumnID.'&tawasulCourseClassID='.$tawasulCourseClassID.'&address='.$session->get('address'));
                        $form->setFactory(DatabaseFormFactory::create($pdo));
                        $form->addHiddenValue('address', $session->get('address'));

                         // Add header actions
                        if (!empty($values['tawasulPlannerEntryID'])) {
                            $params = [
                                "viewBy" => 'class',
                                "tawasulCourseClassID" => $tawasulCourseClassID,
                                "tawasulPlannerEntryID" => $values['tawasulPlannerEntryID'],

                            ];
                            $form->addHeaderAction('view', __('View Linked Lesson'))
                                ->setURL('/modules/TawasulPlanner/planner_view_full.php')
                                ->addParams($params)
                                ->setIcon('planner')
                                ->displayLabel();
                        }
                        $params = [
                            "tawasulCourseClassID" => $tawasulCourseClassID,
                            "tawasulMarkbookColumnID" => $tawasulMarkbookColumnID,

                        ];
                        $form->addHeaderAction('data', __('Enter Data'))
                            ->setURL('/modules/TawasulMarkbook/markbook_edit_data.php')
                            ->addParams($params)
                            ->setIcon('markbook')
                            ->displayLabel()
                            ->prepend((!empty($values['tawasulPlannerEntryID'])) ? ' | ' : '');

                        $form->addRow()->addHeading('Basic Information', __('Basic Information'));

                        $row = $form->addRow();
                            $row->addLabel('courseName', __('Class'));
                            $row->addTextField('courseName')->required()->readOnly()->setValue($course['course'].'.'.$course['class']);

                        $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
                        $sql = "SELECT tawasulUnit.tawasulUnitID as value, tawasulUnit.name FROM tawasulUnit JOIN tawasulUnitClass ON (tawasulUnit.tawasulUnitID=tawasulUnitClass.tawasulUnitID) WHERE running='Y' AND tawasulCourseClassID=:tawasulCourseClassID ORDER BY ordering, name";

                        $row = $form->addRow();
                            $row->addLabel('tawasulUnitID', __('Unit'));
                            $units = $row->addSelect('tawasulUnitID')->fromQuery($pdo, $sql, $data)->placeholder();

                        $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
                        $sql = "SELECT tawasulUnitID as chainedTo, tawasulPlannerEntryID as value, name FROM tawasulPlannerEntry WHERE tawasulCourseClassID=:tawasulCourseClassID ORDER BY date, name";
                        $row = $form->addRow();
                            $row->addLabel('tawasulPlannerEntryID', __('Lesson'));
                            $row->addSelect('tawasulPlannerEntryID')->fromQueryChained($pdo, $sql, $data, 'tawasulUnitID')->placeholder();

                        $row = $form->addRow();
                            $row->addLabel('name', __('Name'));
                            $row->addTextField('name')->required()->maxLength(40);

                        $row = $form->addRow();
                            $row->addLabel('description', __('Description'));
                            $col = $row->addColumn('description');
                            $col->addTextField('description')->required()->maxLength(1000)->addClass('flex-1');
                            $col->addColor('columnColor')->hideField()->setPalette('background')->addClass('ml-2');

                        // TYPE
                        $types = $settingGateway->getSettingByScope('Markbook', 'markbookType');
                        if (!empty($types)) {
                            $row = $form->addRow();
                                $row->addLabel('type', __('Type'));
                                $typesSelect = $row->addSelect('type')->required()->placeholder();

                            if ($enableColumnWeighting == 'Y') {
                                $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'perTerm' => __('Per Term'), 'wholeYear' => __('Whole Year'));
                                $sql = "SELECT (CASE WHEN calculate='term' THEN :perTerm ELSE :wholeYear END) as groupBy, type as value, description as name FROM tawasulMarkbookWeight WHERE tawasulCourseClassID=:tawasulCourseClassID ORDER BY calculate, type";
                                $typesSelect->fromQuery($pdo, $sql, $data, 'groupBy');
                            }

                            if ($typesSelect->getOptionCount() == 0) {
                                $typesSelect->fromString($types);
                            }
                        }

                        $row = $form->addRow();
                            $row->addLabel('file', __('Attachment'));
                            $row->addFileUpload('file')->setAttachment('attachment', $session->get('absoluteURL'), $values['attachment']);

                        // DATE
                        if ($enableGroupByTerm == 'Y') {
                            $form->addRow()->addHeading('Term Date', __('Term Date'));

                            $row = $form->addRow();
                                $row->addLabel('tawasulSchoolYearTermID', __('Term'));
                                $row->addSelectSchoolYearTerm('tawasulSchoolYearTermID', $session->get('tawasulSchoolYearID'));

                            $row = $form->addRow();
                                $row->addLabel('date', __('Date'));
                                $row->addDate('date')->setValue(Format::date($values['date']))->required();
                        } else {
                            $form->addHiddenValue('tawasulSchoolYearTermID',$values['tawasulSchoolYearTermID']);
                            $form->addHiddenValue('date', Format::date($values['date']));
                        }

                        $form->addRow()->addHeading('Assessment', __('Assessment'));

                        // ATTAINMENT
                        $attainmentLabel = !empty($attainmentAltName)? sprintf(__('Assess %1$s?'), $attainmentAltName) : __('Assess Attainment?');
                        $attainmentScaleLabel = !empty($attainmentAltName)? $attainmentAltName.' '.__('Scale') : __('Attainment Scale');
                        $attainmentRawMaxLabel = !empty($attainmentAltName)? $attainmentAltName.' '.__('Total Mark') : __('Attainment Total Mark');
                        $attainmentWeightingLabel = !empty($attainmentAltName)? $attainmentAltName.' '.__('Weighting') : __('Attainment Weighting');
                        $attainmentRubricLabel = !empty($attainmentAltName)? $attainmentAltName.' '.__('Rubric') : __('Attainment Rubric');

                        $row = $form->addRow();
                            $row->addLabel('attainment', $attainmentLabel);
                            $row->addYesNoRadio('attainment')->required();

                        $form->toggleVisibilityByClass('attainmentRow')->onRadio('attainment')->when('Y');

                        $row = $form->addRow()->addClass('attainmentRow');
                            $row->addLabel('tawasulScaleIDAttainment', $attainmentScaleLabel);
                            $row->addSelectGradeScale('tawasulScaleIDAttainment')->required();

                        if ($enableRawAttainment == 'Y') {
                            $row = $form->addRow()->addClass('attainmentRow');
                                $row->addLabel('attainmentRawMax', $attainmentRawMaxLabel)->description(__('Leave blank to omit raw marks.'));
                                $row->addNumber('attainmentRawMax')->maxLength(8)->onlyInteger(false);
                        }

                        if ($enableColumnWeighting == 'Y') {
                            $row = $form->addRow()->addClass('attainmentRow');
                                $row->addLabel('attainmentWeighting', $attainmentWeightingLabel);
                                $row->addNumber('attainmentWeighting')->maxLength(5)->onlyInteger(false)->setValue(1);
                        }

                        if ($enableRubrics == 'Y') {
                            $row = $form->addRow()->addClass('attainmentRow');
                                $row->addLabel('tawasulRubricIDAttainment', $attainmentRubricLabel)->description(__('Choose predefined rubric, if desired.'));
                                $row->addSelectRubric('tawasulRubricIDAttainment', $course['tawasulYearGroupIDList'], $course['tawasulDepartmentID'])->placeholder();
                        }

                        // EFFORT
                        if ($enableEffort == 'Y') {
                            $effortLabel = !empty($effortAltName)? sprintf(__('Assess %1$s?'), $effortAltName) : __('Assess Effort?');
                            $effortScaleLabel = !empty($effortAltName)? $effortAltName.' '.__('Scale') : __('Effort Scale');
                            $effortRubricLabel = !empty($effortAltName)? $effortAltName.' '.__('Rubric') : __('Effort Rubric');

                            $row = $form->addRow();
                                $row->addLabel('effort', $effortLabel);
                                $row->addYesNoRadio('effort')->required();

                            $form->toggleVisibilityByClass('effortRow')->onRadio('effort')->when('Y');

                            $row = $form->addRow()->addClass('effortRow');
                                $row->addLabel('tawasulScaleIDEffort', $effortScaleLabel);
                                $row->addSelectGradeScale('tawasulScaleIDEffort')->required();

                            if ($enableRubrics == 'Y') {
                                $row = $form->addRow()->addClass('effortRow');
                                    $row->addLabel('tawasulRubricIDEffort', $effortRubricLabel)->description(__('Choose predefined rubric, if desired.'));
                                    $row->addSelectRubric('tawasulRubricIDEffort', $course['tawasulYearGroupIDList'], $course['tawasulDepartmentID'])->placeholder();
                            }
                        }

                        $row = $form->addRow();
                            $row->addLabel('comment', __('Include Comment?'));
                            $row->addYesNoRadio('comment')->required();

                        $row = $form->addRow();
                            $row->addLabel('uploadedResponse', __('Include Uploaded Response?'));
                            $row->addYesNoRadio('uploadedResponse')->required();

                        $form->addRow()->addHeading('Access', __('Access'));

                        $row = $form->addRow();
                            $row->addLabel('viewableStudents', __('Viewable by Students'));
                            $row->addYesNo('viewableStudents')->required();

                        $row = $form->addRow();
                            $row->addLabel('viewableParents', __('Viewable by Parents'));
                            $row->addYesNo('viewableParents')->required();

                        $row = $form->addRow();
                            $row->addLabel('completeDate', __('Go Live Date'))->prepend('1. ')->append('<br/>'.__('2. Column is hidden until date is reached.'));
                            $row->addDate('completeDate');

                        $row = $form->addRow();
                            $row->addFooter();
                            $row->addSubmit();

                        $form->loadAllValuesFrom($values);

                        echo $form->getOutput();
                    }
                }
            }
        }
    }

    // Print the sidebar
    $session->set('sidebarExtra', sidebarExtra($guid, $pdo, $session->get('tawasulPersonID'), $tawasulCourseClassID, 'markbook_view.php'));
}
