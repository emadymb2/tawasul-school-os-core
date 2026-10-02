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

use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Domain\User\RoleGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\School\SchoolYearGateway;
use Tos\Module\TawasulPlanner\Forms\PlannerFormFactory;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

// common variables
$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$tawasulUnitID = $_GET['tawasulUnitID'] ?? '';

$page->breadcrumbs
    ->add(__('Unit Planner'), 'units.php', [
        'tawasulSchoolYearID' => $tawasulSchoolYearID,
        'tawasulCourseID' => $tawasulCourseID,
        'tawasulCourseClassID' => $tawasulCourseClassID,
    ])
    ->add(__('Edit Unit'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Proceed!
        $returns = array();
        $returns['success1'] = __('Smart Blockify was successful.');
        $returns['success2'] = __('Copy was successful. The blocks from the selected working unit have replaced those in the master unit (see below for the new block listing).');
        $returns['success3'] = __('Your unit was successfully created: you can now edit and deploy it using the form below.');
        $page->return->addReturns($returns);

        //Check if courseschool year specified
        if ($tawasulCourseID == '' or $tawasulSchoolYearID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            $courseGateway = $container->get(CourseGateway::class);

            // Check access to specified course
            if ($highestAction == 'Unit Planner_all') {
                $result = $courseGateway->selectCourseDetailsByCourse($tawasulCourseID);
            } elseif ($highestAction == 'Unit Planner_learningAreas') {
                $result = $courseGateway->selectCourseDetailsByCourseAndPerson($tawasulCourseID, $session->get('tawasulPersonID'));
            }

            if ($result->rowCount() != 1) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                $values = $result->fetch();
                $yearName = $values['schoolYear'];
                $courseName = $values['name'];
                $courseNameShort = $values['nameShort'];
                $tawasulYearGroupIDList = $values['tawasulYearGroupIDList'];

                //Check if unit specified
                if ($tawasulUnitID == '') {
                    $page->addError(__('You have not specified one or more required parameters.'));
                } else {

                        $data = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseID' => $tawasulCourseID);
                        $sql = 'SELECT tawasulCourse.nameShort AS courseName, tawasulCourse.tawasulDepartmentID, tawasulUnit.* FROM tawasulUnit JOIN tawasulCourse ON (tawasulUnit.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulUnitID=:tawasulUnitID AND tawasulUnit.tawasulCourseID=:tawasulCourseID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    if ($result->rowCount() != 1) {
                        $page->addError(__('The specified record cannot be found.'));
                    } else {
                        //Let's go!
                        $values = $result->fetch();
                        $tawasulDepartmentID = $values['tawasulDepartmentID'];

                        $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/units_editProcess.php?tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseID&tawasulUnitID=$tawasulUnitID&address=".$_GET['q']);
                        $form->setFactory(PlannerFormFactory::create($pdo));
                        $form->addMeta()->addDefaultContent('editProcess');

                        $form->addHiddenValue('address', $session->get('address'));

                        //OVERVIEW
                        $form->addRow()->addHeading('Overview', __('Overview'));

                        $row = $form->addRow();
                            $row->addLabel('yearName', __('School Year'));
                            $row->addTextField('yearName')->readonly()->setValue($yearName)->required();

                        $row = $form->addRow();
                            $row->addLabel('courseName', __('Course'));
                            $row->addTextField('courseName')->readonly()->setValue($courseName)->required();

                        $row = $form->addRow();
                            $row->addLabel('name', __('Name'));
                            $row->addTextField('name')->required()->maxLength(40);

                        $row = $form->addRow();
                            $row->addLabel('description', __('Description'));
                            $row->addTextArea('description')->setRows(5)->required();

                        $row = $form->addRow();
                            $row->addLabel('active', __('Active'));
                            $row->addYesNo('active')->required();

                        $row = $form->addRow();
                            $row->addLabel('map', __('Include In Curriculum Map'));
                            $row->addYesNo('map')->required();

                        $row = $form->addRow();
                            $row->addLabel('ordering', __('Ordering'))->description(__('Units are arranged form lowest to highest ordering value, then alphabetically.'));
                            $row->addNumber('ordering')->maxLength(4)->decimalPlaces(0)->setValue("0")->required();

                        $tags = getTagList($connection2);
                        $tagsOutput = array();
                        foreach ($tags as $tag) {
                            if ($tag[0] > 0) {
                                $tagsOutput[$tag[1]] = $tag[1] . " (".$tag[2].")";
                            }
                        }
                        $row = $form->addRow()->addClass('tags');
                            $column = $row->addColumn();
                            $column->addLabel('tags', __('Concepts & Keywords'))->description(__('Use tags to describe unit and its contents.'));
                            $column->addFinder('tags')
                                ->fromArray($tagsOutput)
                                ->setParameter('hintText', __('Type a tag...'))
                                ->setParameter('allowFreeTagging', true);

                        //CLASSES
                        $form->addRow()->addHeading('Classes', __('Classes'))->append(__('Select classes which will have access to this unit.'));

                        $currentRole = $container->get(RoleGateway::class)->getByID($session->get('tawasulRoleIDCurrent'));
                        $selectedSchoolYear = $container->get(SchoolYearGateway::class)->getByID($tawasulSchoolYearID);

                        $isCurrentYear = $session->get('tawasulSchoolYearIDCurrent') == $tawasulSchoolYearID;
                        $canAccessUpcomingYear = !empty($currentRole) && $currentRole['futureYearsLogin'] == 'Y';
                        $canAccessPastYear = !empty($currentRole) && $currentRole['pastYearsLogin'] == 'Y';

                        if ($isCurrentYear || ($canAccessUpcomingYear && $selectedSchoolYear['status'] == 'Upcoming') || ($canAccessPastYear && $selectedSchoolYear['status'] == 'Past')) {
                            $dataClass = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseID' => $tawasulCourseID);
                            $sqlClass = "SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourseClass.nameShort, running, tawasulUnitClassID, tawasulUnitID
                                        FROM tawasulCourseClass
                                        LEFT JOIN tawasulUnitClass ON (tawasulUnitClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulUnitID=:tawasulUnitID)
                                        WHERE tawasulCourseID=:tawasulCourseID
                                        ORDER BY tawasulCourseClass.nameShort";
                            $resultClass = $pdo->select($sqlClass, $dataClass)->toDataSet();

                            if (count($resultClass) == 0) {
                                $form->addRow()->addAlert(__('There are no records to display.'), 'error');
                            } else {
                                $classCount = 0;

                                // Add the firstLesson date to each class, and
                                $resultClass->transform(function (&$class) use ($pdo, &$classCount, &$form) {
                                    if ($class['running'] == 'Y') {
                                        $dataDate = array('tawasulCourseClassID' => $class['tawasulCourseClassID'], 'tawasulUnitID' => $class['tawasulUnitID']);
                                        $sqlDate = "SELECT date FROM tawasulPlannerEntry WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulUnitID=:tawasulUnitID AND date IS NOT NULL ORDER BY date, timeStart";
                                        $class['firstLesson'] = $pdo->selectOne($sqlDate, $dataDate);
                                    }

                                    $form->addHiddenValue("tawasulCourseClassID{$classCount}", $class['tawasulCourseClassID']);
                                    $class['count'] = $classCount;
                                    $classCount++;
                                });

                                // Nested DataTable for course classes
                                $table = $form->addRow()->addDataTable('classes')->withData($resultClass);

                                $table->addColumn('class', __('Class'))
                                    ->width('20%')
                                    ->format(Format::using('courseClassName', [$courseNameShort, 'nameShort']));

                                $table->addColumn('running', __('Running'))
                                    ->description(__('Is class studying this unit?'))
                                    ->width('25%')
                                    ->format(function ($class) use (&$form) {
                                        return $form->getFactory()
                                            ->createYesNo('running'.$class['count'])
                                            ->setClass('w-32 float-none')
                                            ->selected($class['running'] ?? 'N')
                                            ->getOutput();
                                    });

                                $table->addColumn('firstLesson', __('First Lesson'))
                                    ->description($session->get('i18n')['dateFormat'] ?? 'dd/mm/yyyy')
                                    ->width('15%')
                                    ->format(function ($class) {
                                        return !empty($class['firstLesson']) ? Format::date($class['firstLesson']) : '';
                                    });

                                $table->addActionColumn()
                                    ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                                    ->addParam('tawasulUnitID', $tawasulUnitID)
                                    ->addParam('tawasulCourseID', $tawasulCourseID)
                                    ->addParam('tawasulCourseClassID')
                                    ->addParam('tawasulUnitClassID')
                                    ->format(function ($class, $actions) {
                                        if ($class['running'] == 'N' || empty($class['running'])) return;

                                        $actions->addAction('edit', __('Edit Unit'))
                                                ->setURL(empty($class['firstLesson'])
                                                    ? '/modules/TawasulPlanner/units_edit_deploy.php'
                                                    : '/modules/TawasulPlanner/units_edit_working.php');

                                        $actions->addAction('view', __('View Planner'))
                                                ->addParam('viewBy', 'class')
                                                ->setIcon('planner')
                                                ->setURL('/modules/TawasulPlanner/planner.php');

                                        $actions->addAction('copyBack', __('Copy Back'))
                                                ->setIcon('copyback')
                                                ->setURL('/modules/TawasulPlanner/units_edit_copyBack.php');

                                        $actions->addAction('copyForward', __('Copy Forward'))
                                                ->setIcon('copyforward')
                                                ->setURL('/modules/TawasulPlanner/units_edit_copyForward.php');

                                        $actions->addAction('smartBlockify', __('Smart Blockify'))
                                                ->setIcon('run')
                                                ->setURL('/modules/TawasulPlanner/units_edit_smartBlockify.php');
                                    });
                            }
                        }
                        else {
                            $row = $form->addRow();
                                $row->addAlert(__('You are currently not logged into the current year and/or are looking at units in another year, and so you cannot access your classes. Please log back into the current school year, and look at units in the current year.'), 'warning');
                        }

                        $form->addHiddenValue('classCount', $classCount ?? 0);

                        //UNIT OUTLINE
                        $form->addRow()->addHeading('Unit Outline', __('Unit Outline'));

                        $settingGateway = $container->get(SettingGateway::class);

                        $unitOutline = $settingGateway->getSettingByScope('Planner', 'unitOutlineTemplate');
                        $shareUnitOutline = $settingGateway->getSettingByScope('Planner', 'shareUnitOutline');
                        if ($shareUnitOutline == 'Y') {
                            $content = __('The contents of both the Unit Outline field and the Downloadable Unit Outline are available to all users who can access this unit via the Lesson Planner (possibly include parents and students).');
                        }
                        else {
                            $content = __('The contents of the Unit Outline field are viewable only to those with full access to the Planner (usually teachers and administrators, but not students and parents), whereas the downloadable version (below) is available to more users (usually parents).');
                        }
                        $row = $form->addRow();
                            $column = $row->addColumn();
                            $column->addAlert($content, 'message');
                            $column->addEditor('details', $guid)->setRows(30)->showMedia()->setValue($unitOutline);


                            $dataExt = array();
                            $sqlExt = 'SELECT * FROM tawasulFileExtension';
                            $resultExt = $connection2->prepare($sqlExt);
                            $resultExt->execute($dataExt);
                        $ext = '';
                        while ($rowExt = $resultExt->fetch()) {
                            $ext .= "'.".$rowExt['extension']."',";
                        }
                        $row = $form->addRow();
                            $row->addLabel('file', __('Downloadable Unit Outline'))->description(__("Available to most users."));
                            $row->addFileUpload('file')
                                ->accepts(substr($ext, 0, -2))
                                ->setAttachment('attachment', $session->get('absoluteURL'), $values['attachment']);

                        //OUTCOMES
                        $form->addRow()->addHeading('Outcomes', __('Outcomes'))->append(__('Link this unit to outcomes (defined in the Manage Outcomes section of the Planner), and track which outcomes are being met in which units, classes and courses.'));
                        $allowOutcomeEditing = $settingGateway->getSettingByScope('Planner', 'allowOutcomeEditing');
                        $row = $form->addRow();
                            $customBlocks = $row->addPlannerOutcomeBlocks('outcome', $session, $tawasulYearGroupIDList, $tawasulDepartmentID, $allowOutcomeEditing);

                        $dataBlocks = array('tawasulUnitID' => $tawasulUnitID);
                        $sqlBlocks = "SELECT tawasulUnitOutcome.*, scope, name, category FROM tawasulUnitOutcome JOIN tawasulOutcome ON (tawasulUnitOutcome.tawasulOutcomeID=tawasulOutcome.tawasulOutcomeID) WHERE tawasulUnitID=:tawasulUnitID AND active='Y' ORDER BY sequenceNumber";
                        $resultBlocks = $pdo->select($sqlBlocks, $dataBlocks);

                        while ($rowBlocks = $resultBlocks->fetch()) {
                            $outcome = array(
                                'outcometitle' => $rowBlocks['name'],
                                'outcometawasulOutcomeID' => $rowBlocks['tawasulOutcomeID'],
                                'outcomecategory' => $rowBlocks['category'],
                                'outcomecontents' => $rowBlocks['content']
                            );
                            $customBlocks->addBlock($rowBlocks['tawasulOutcomeID'], $outcome);
                        }

                        //SMART BLOCKS
                        $form->addRow()->addHeading('Smart Blocks', __('Smart Blocks'))->append(__('Smart Blocks aid unit planning by giving teachers help in creating and maintaining new units, splitting material into smaller units which can be deployed to lesson plans. As well as predefined fields to fill, Smart Units provide a visual view of the content blocks that make up a unit. Blocks may be any kind of content, such as discussion, assessments, group work, outcome etc.'));

                        $row = $form->addRow();
                            $customBlocks = $row->addPlannerSmartBlocks('smart', $session, true);

                        $dataBlocks = array('tawasulUnitID' => $tawasulUnitID);
                        $sqlBlocks = 'SELECT * FROM tawasulUnitBlock WHERE tawasulUnitID=:tawasulUnitID ORDER BY sequenceNumber';
                        $resultBlocks = $pdo->select($sqlBlocks, $dataBlocks);

                        while ($rowBlocks = $resultBlocks->fetch()) {
                            $smart = array(
                                'title' => $rowBlocks['title'],
                                'type' => $rowBlocks['type'],
                                'length' => $rowBlocks['length'],
                                'contents' => $rowBlocks['contents'],
                                'teachersNotes' => $rowBlocks['teachersNotes'],
                                'tawasulUnitBlockID' => $rowBlocks['tawasulUnitBlockID']
                            );
                            $customBlocks->addBlock($rowBlocks['tawasulUnitBlockID'], $smart);
                        }

                        //MISCELLANEOUS SETTINGS
                        $form->addRow()->addHeading('Miscellaneous Settings', __('Miscellaneous Settings'));

                        $licences = array(
                            "Copyright" => __("Copyright"),
                            "Creative Commons BY" => __("Creative Commons BY"),
                            "Creative Commons BY-SA" => __("Creative Commons BY-SA"),
                            "Creative Commons BY-SA-NC" => __("Creative Commons BY-SA-NC"),
                            "Public Domain" => __("Public Domain")
                        );
                        $row = $form->addRow();
                            $row->addLabel('license', __('License'))->description(__('Under what conditions can this work be reused?'));
                            $row->addSelect('license')->fromArray($licences)->placeholder();

                        $makeUnitsPublic = $settingGateway->getSettingByScope('Planner', 'makeUnitsPublic');
                        if ($makeUnitsPublic == 'Y') {
                            $row = $form->addRow();
                                $row->addLabel('sharedPublic', __('Shared Publicly'))->description(__('Share this unit via the public listing of units? Useful for building MOOCS.'));
                                $row->addYesNo('sharedPublic')->required();
                        }

                        $row = $form->addRow();
                            $row->addSubmit();

                        $form->loadAllValuesFrom($values);

                        echo $form->getOutput();
                    }
                }
            }
        }
    }
    //Print sidebar
    $session->set('sidebarExtra', sidebarExtraUnits($guid, $connection2, $tawasulCourseID, $tawasulSchoolYearID));
}
?>
