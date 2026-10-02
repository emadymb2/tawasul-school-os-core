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

use TawasulOS\Domain\School\SchoolYearGateway;
use TawasulOS\Domain\School\YearGroupGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\User\UserStatusLogGateway;
use TawasulOS\Services\Format;
use TawasulOS\Session\SessionFactory;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/rollover.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Rollover'));

    $step = null;
    if (isset($_GET['step'])) {
        $step = $_GET['step'] ?? '';
    }
    if ($step != 1 and $step != 2 and $step != 3) {
        $step = 1;
    }

    /** @var UserStatusLogGateway */
    $userStatusLogGateway = $container->get(UserStatusLogGateway::class);
    /** @var YearGroupGateway */
    $yearGroupGateway = $container->get(YearGroupGateway::class);
    /** @var SchoolYearGateway */
    $schoolYearGateway = $container->get(SchoolYearGateway::class);

    //Step 1
    if ($step == 1) {
        echo '<h3>';
        echo __('Step 1');
        echo '</h3>';

        $maxInputVars = ini_get('max_input_vars');
       
        $settings = $tawasul->getSystemRequirement('settings');
        $requiredInputVars = $settings['max_input_vars'][2] ?? 5000;

        $canProceed = true;

        if ($maxInputVars < $requiredInputVars) {
            echo Format::alert(sprintf(__('Your PHP environment cannot handle all of the fields in this form (the current limit is %1$s). Ask your web host or system administrator to increase the value of the max_input_vars in php.ini to at least %2$s and try again.'), $maxInputVars, $requiredInputVars), 'error');
            return;
        }

        $nextYearBySession = $schoolYearGateway->getNextSchoolYearByID($session->get('tawasulSchoolYearID'));
        if ($nextYearBySession === false) {
            echo Format::alert(__('The next school year cannot be determined, so this action cannot be performed.'), 'error');
            return;
        } 

        if (empty($nextYearBySession)) {
            echo Format::alert(__('The next school year cannot be determined, so this action cannot be performed.'), 'error');
            return;
        } 

        $form = Form::create('action', $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/rollover.php&step=2');

        $form->setClass('smallIntBorder w-full');

        $form->addHiddenValue('nextYear', $nextYearBySession['tawasulSchoolYearID']);

        $row = $form->addRow();
            $row->addContent(sprintf(__('By clicking the "Proceed" button below you will initiate the rollover from %1$s to %2$s. In a big school this operation may take some time to complete. This will change data in numerous tables across the system! %3$sYou are really, very strongly advised to backup all data before you proceed%4$s.'), '<b>'.$session->get('tawasulSchoolYearName').'</b>', '<b>'.$nextYearBySession['name'].'</b>', '<span style="color: #cc0000"><i>', '</span>'));

        $row = $form->addRow();
            $row->addSubmit('Proceed');

        echo $form->getOutput(); 
        
    } elseif ($step == 2) {
        echo '<h3>';
        echo __('Step 2');
        echo '</h3>';

        $nextYearID = $_POST['nextYear'] ?? '';
        $nextYearBySession = $schoolYearGateway->getNextSchoolYearByID($session->get('tawasulSchoolYearID'));
        if (empty($nextYearID) or $nextYearBySession === false or $nextYearID != $nextYearBySession['tawasulSchoolYearID']) {
            echo Format::alert(__('The next school year cannot be determined, so this action cannot be performed.'), 'error');
        } else {

                $dataNext = array('tawasulSchoolYearID' => $nextYearID);
                $sqlNext = 'SELECT * FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID';
                $resultNext = $connection2->prepare($sqlNext);
                $resultNext->execute($dataNext);
            if ($resultNext->rowCount() == 1) {
                $rowNext = $resultNext->fetch();
            }
            $nameNext = $rowNext['name'];
            $sequenceNext = $rowNext['sequenceNumber'];
            if ($nameNext == '' or $sequenceNext == '') {
                echo Format::alert(__('The next school year cannot be determined, so this action cannot be performed.'), 'error');
            } else {
                echo '<p>';
                echo sprintf(__('In rolling over to %1$s, the following actions will take place. You may need to adjust some fields below to get the result you desire.'), $nameNext);
                echo '</p>';

                //Set up years, form groups and statuses arrays for use later on
                $yearGroups = array();

                    $dataSelect = array();
                    $sqlSelect = 'SELECT tawasulYearGroupID, name FROM tawasulYearGroup ORDER BY sequenceNumber';
                    $resultSelect = $connection2->prepare($sqlSelect);
                    $resultSelect->execute($dataSelect);
                while ($rowSelect = $resultSelect->fetch()) {
                    $yearGroups[$rowSelect['tawasulYearGroupID']] =  htmlPrep($rowSelect['name']);
                }

                $formGroups = array();

                    $dataSelect = array('tawasulSchoolYearID' => $nextYearID);
                    $sqlSelect = 'SELECT tawasulFormGroupID, name FROM tawasulFormGroup WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY name';
                    $resultSelect = $connection2->prepare($sqlSelect);
                    $resultSelect->execute($dataSelect);
                while ($rowSelect = $resultSelect->fetch()) {
                    $formGroups[$rowSelect['tawasulFormGroupID']] =  htmlPrep($rowSelect['name']);
                }

                $statuses = array(
                    'Expected'     => __('Expected'),
                    'Full'  => __('Full'),
                    'Left' => __('Left'),
                );

                //START FORM
                $form = Form::createTable('rollover', $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/rollover.php&step=3");

                $form->setFactory(DatabaseFormFactory::create($pdo));
                $form->setClass('smallIntBorder w-full');

                $form->addHiddenValue('nextYear', $nextYearID);

                //ADD YEAR FOLLOWING NEXT
                if ($schoolYearGateway->getNextSchoolYearByID($nextYearID) === false) {
                    $form->addRow()->addHeading(sprintf(__('Add Year Following %1$s'), $nameNext));

                    $subform = Form::create('rolloverYear', '');
                    $subform->setFactory(DatabaseFormFactory::create($pdo));

                    $row = $subform->addRow();
                        $row->addLabel('nextname', __('School Year Name'))->description(__('Must be unique.'));
                        $row->addTextField('nextname')->required()->maxLength(9)->addClass('w-64');

                    $row = $subform->addRow();
                        $row->addLabel('nextstatus', __('Status'));
                        $row->addTextField('nextstatus')->setValue(__('Upcoming'))->required()->readonly();

                    $row = $subform->addRow();
                        $row->addLabel('nextsequenceNumber', __('Sequence Number'))->description(__('Must be unique. Controls chronological ordering.'));
                        $row->addSequenceNumber('nextsequenceNumber', 'tawasulSchoolYear', '', 'sequenceNumber')->required()->maxLength(3)->readonly();

                    $row = $subform->addRow();
                        $row->addLabel('nextfirstDay', __('First Day'));
                        $row->addDate('nextfirstDay')->required()->addClass('w-64');

                    $row = $subform->addRow();
                        $row->addLabel('nextlastDay', __('Last Day'));
                        $row->addDate('nextlastDay')->required()->addClass('w-64');

                    $form->addRow()->addContent($subform->getOutput());
                }

                //SET EXPECTED USERS TO FULL
                $form->addRow()->addHeading('Set Expected Users To Full', __('Set Expected Users To Full'));
                $form->addRow()->addContent(__('This step primes newcomers who have status set to "Expected" to be enrolled as students or added as staff (below).'));

                try {
                    $dataExpect = array();
                    $sqlExpect = "SELECT tawasulPersonID, surname, preferredName, name FROM tawasulPerson JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) WHERE status='Expected' ORDER BY name, surname, preferredName";
                    $resultExpect = $connection2->prepare($sqlExpect);
                    $resultExpect->execute($dataExpect);
                } catch (PDOException $e) {
                    $form->addRow()->addAlert($e->getMessage(), 'error');
                }
                if ($resultExpect->rowCount() < 1) {
                    $form->addRow()->addAlert(__('There are no records to display.'), 'warning');
                } else {
                    $row = $form->addRow()->addClass('head break');
                        $row->addColumn()->addContent(__('Name'));
                        $row->addColumn()->addContent(__('Primary Role'));
                        $row->addColumn()->addContent(__('Current Status'));
                        $row->addColumn()->addContent(__('New Status'));

                    $count = 0;
                    while ($rowExpect = $resultExpect->fetch()) {
                        $count++;
                        $form->addHiddenValue($count."-expect-tawasulPersonID", $rowExpect['tawasulPersonID']);
                        $row = $form->addRow();
                            $row->addColumn()->addContent(Format::name('', $rowExpect['preferredName'], $rowExpect['surname'], 'Student', true));
                            $row->addColumn()->addContent(__($rowExpect['name']));
                            $row->addColumn()->addContent(__('Expected'));
                            $column = $row->addColumn();
                                $column->addSelect($count."-expect-status")->fromArray($statuses)->required()->setClass('shortWidth floatNone')->selected('Full');
                    }
                    $form->addHiddenValue("expect-count", $count);
                }

                //ENROL NEW STUDENTS - EXPECTED
                $form->addRow()->addHeading(__('Enrol New Students (Status Expected)'));
                $form->addRow()->addContent(__('Take students who are marked expected and enrol them. All parents of new students who are enrolled below will have their status set to "Full". If a student is not enrolled, they will be set to "Left".'));

                if (count($yearGroups) < 1 or count($formGroups) < 1) {
                    $form->addRow()->addAlert(__('Year groups or form groups are not properly set up, so you cannot proceed with this section.'), 'error');
                } else {
                    try {
                        $dataEnrol = array();
                        $sqlEnrol = "SELECT tawasulPersonID, surname, preferredName, name, category FROM tawasulPerson JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) WHERE status='Expected' AND category='Student' ORDER BY surname, preferredName";
                        $resultEnrol = $connection2->prepare($sqlEnrol);
                        $resultEnrol->execute($dataEnrol);
                    } catch (PDOException $e) {
                        $form->addRow()->addAlert($e->getMessage(), 'error');
                    }

                    if ($resultEnrol->rowCount() < 1) {
                        $form->addRow()->addAlert(__('There are no records to display.'), 'warning');
                    } else {
                        $row = $form->addRow()->addClass('head break');
                            $row->addColumn()->addContent(__('Name'));
                            $row->addColumn()->addContent(__('Primary Role'));
                            $row->addColumn()->addContent(__('Enrol'));
                            $row->addColumn()->addContent(__('Year Group'));
                            $row->addColumn()->addContent(__('Form Group'));

                        $count = 0;
                        while ($rowEnrol = $resultEnrol->fetch()) {
                            $count++;
                            $form->addHiddenValue($count."-enrol-tawasulPersonID", $rowEnrol['tawasulPersonID']);
                            $row = $form->addRow();
                                $row->addColumn()->addContent(Format::name('', $rowEnrol['preferredName'], $rowEnrol['surname'], 'Student', true));
                                $row->addColumn()->addContent(__($rowEnrol['name']));
                                $column = $row->addColumn();
                                    $column->addCheckbox($count."-enrol-enrol")->setValue('Y')->checked('Y')->alignLeft();
                                $column = $row->addColumn();
                                    $column->addSelect($count."-enrol-tawasulYearGroupID")->fromArray($yearGroups)->required()->setClass('shortWidth floatNone');
                                $column = $row->addColumn();
                                    $column->addSelect($count."-enrol-tawasulFormGroupID")->fromArray($formGroups)->required()->setClass('shortWidth floatNone');
                        }
                        $form->addHiddenValue("enrol-count", $count);
                    }
                }

                //ENROL NEW STUDENTS - FULL
                $form->addRow()->addHeading(__('Enrol New Students (Status Full)'));
                $form->addRow()->addContent(__('Take new students who are already set as full, but who were not enrolled last year, and enrol them. These students probably came through the Online Application form, and may already be enrolled in next year: if this is the case, their enrolment will be updated as per the information below. All parents of new students who are enrolled below will have their status set to "Full". If a student is not enrolled, they will be set to "Left"'));

                if (count($yearGroups) < 1 or count($formGroups) < 1) {
                    $form->addRow()->addAlert(__('Year groups or form groups are not properly set up, so you cannot proceed with this section.'), 'error');
                } else {
                    $students = array();
                    $count = 0;
                    try {
                        $dataEnrol = array();
                        $sqlEnrol = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName, tawasulRole.name, category
                            FROM tawasulPerson
                                JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
                                LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                                LEFT JOIN tawasulSchoolYear ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                            WHERE tawasulPerson.status='Full'
                                AND category='Student'
                                AND (tawasulStudentEnrolment.tawasulPersonID IS NULL OR tawasulSchoolYear.status='Upcoming')
                            ORDER BY surname, preferredName";
                        $resultEnrol = $connection2->prepare($sqlEnrol);
                        $resultEnrol->execute($dataEnrol);
                    } catch (PDOException $e) {
                        $form->addRow()->addAlert($e->getMessage());
                    }

                    if ($resultEnrol->rowCount() < 1) {
                        $form->addRow()->addAlert(__('There are no records to display.'), 'warning');
                    } else {
                        while ($rowEnrol = $resultEnrol->fetch()) {
                            try {
                                $dataEnrolled = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $rowEnrol['tawasulPersonID']);
                                $sqlEnrolled = "SELECT tawasulStudentEnrolment.* FROM tawasulPerson JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND status='Full' AND category='Student' AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID ORDER BY surname, preferredName";
                                $resultEnrolled = $connection2->prepare($sqlEnrolled);
                                $resultEnrolled->execute($dataEnrolled);
                            } catch (PDOException $e) {
                                $form->addRow()->addAlert($e->getMessage(), 'error');
                            }
                            if ($resultEnrolled->rowCount() < 1) {
                                $students[$count][0] = $rowEnrol['tawasulPersonID'];
                                $students[$count][1] = $rowEnrol['surname'];
                                $students[$count][2] = $rowEnrol['preferredName'];
                                $students[$count][3] = $rowEnrol['name'];
                                ++$count;
                            }
                        }

                        if ($count < 1) {
                            $form->addRow()->addAlert(__('There are no records to display.'), 'warning');
                        } else {
                            $row = $form->addRow()->addClass('head break');
                                $row->addColumn()->addContent(__('Name'));
                                $row->addColumn()->addContent(__('Primary Role'));
                                $row->addColumn()->addContent(__('Enrol'));
                                $row->addColumn()->addContent(__('Year Group'));
                                $row->addColumn()->addContent(__('Form Group'));

                            $count = 0;
                            foreach ($students AS $student) {
                                $count++;
                                //Check for enrolment in next year (caused by automated enrolment on application form accept)
                                $yearGroupSelect = '';
                                $formGroupSelect = '';
                                try {
                                    $dataEnrolled = array('tawasulSchoolYearID' => $nextYearID, 'tawasulPersonID' => $student[0]);
                                    $sqlEnrolled = 'SELECT * FROM tawasulStudentEnrolment WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID';
                                    $resultEnrolled = $connection2->prepare($sqlEnrolled);
                                    $resultEnrolled->execute($dataEnrolled);
                                } catch (PDOException $e) {
                                    $form->addRow()->addAlert($e->getMessage(), 'error');
                                }
                                if ($resultEnrolled->rowCount() == 1) {
                                    $rowEnrolled = $resultEnrolled->fetch();
                                    $yearGroupSelect = $rowEnrolled['tawasulYearGroupID'];
                                    $formGroupSelect = $rowEnrolled['tawasulFormGroupID'];
                                }

                                $form->addHiddenValue($count."-enrolFull-tawasulPersonID", $student[0]);
                                $row = $form->addRow();
                                    $row->addColumn()->addContent(Format::name('', $student[2], $student[1], 'Student', true));
                                    $row->addColumn()->addContent(__($student[3]));
                                    $column = $row->addColumn();
                                        $column->addCheckbox($count."-enrolFull-enrol")->setValue('Y')->checked('Y')->alignLeft();
                                    $column = $row->addColumn();
                                        $column->addSelect($count."-enrolFull-tawasulYearGroupID")->fromArray($yearGroups)->required()->setClass('shortWidth floatNone')->selected($yearGroupSelect);
                                    $column = $row->addColumn();
                                        $column->addSelect($count."-enrolFull-tawasulFormGroupID")->fromArray($formGroups)->required()->setClass('shortWidth floatNone')->selected($formGroupSelect);
                            }
                            $form->addHiddenValue("enrolFull-count", $count);
                        }
                    }
                }

                //RE-ENROL OTHER STUDENTS
                $form->addRow()->addHeading('Re-Enrol Other Students', __('Re-Enrol Other Students'));
                $form->addRow()->addContent(__('Any students who are not re-enrolled will have their status set to "Left".').' '.__('Students who are already enrolled will have their enrolment updated.'));

                $lastYearGroup = $yearGroupGateway->getLastYearGroupID();

                if (count($yearGroups) < 1 or count($formGroups) < 1) {
                    $form->addRow()->addAlert(__('Year groups or form groups are not properly set up, so you cannot proceed with this section.'), 'error');
                } else {
                    try {
                        $dataReenrol = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulYearGroupID' => $lastYearGroup);
                        $sqlReenrol = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName, tawasulRole.name, category, tawasulStudentEnrolment.tawasulYearGroupID, tawasulFormGroupIDNext
                            FROM tawasulPerson
                                JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
                                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                                JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                            WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND status='Full' AND category='Student' AND NOT tawasulYearGroupID=:tawasulYearGroupID ORDER BY surname, preferredName";
                        $resultReenrol = $connection2->prepare($sqlReenrol);
                        $resultReenrol->execute($dataReenrol);
                    } catch (PDOException $e) {
                        $form->addRow()->addAlert($e->getMessage(), 'error');
                    }

                    if ($resultReenrol->rowCount() < 1) {
                        $form->addRow()->addAlert(__('There are no records to display.'), 'warning');
                    } else {
                        $row = $form->addRow()->addClass('head break');
                            $row->addColumn()->addContent(__('Name'));
                            $row->addColumn()->addContent(__('Primary Role'));
                            $row->addColumn()->addContent(__('Re-Enrol'));
                            $row->addColumn()->addContent(__('Year Group'));
                            $row->addColumn()->addContent(__('Form Group'));

                        $count = 0;
                        while ($rowReenrol = $resultReenrol->fetch()) {
                            $count++;
                            //Check for enrolment in next year
                            try {
                                $dataEnrolmentCheck = array('tawasulPersonID' => $rowReenrol['tawasulPersonID'], 'tawasulSchoolYearID' => $nextYearID);
                                $sqlEnrolmentCheck = 'SELECT * FROM tawasulStudentEnrolment WHERE tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID';
                                $resultEnrolmentCheck = $connection2->prepare($sqlEnrolmentCheck);
                                $resultEnrolmentCheck->execute($dataEnrolmentCheck);
                            } catch (PDOException $e) {
                                $form->addRow()->addAlert($e->getMessage(), 'error');
                            }
                            $enrolmentCheckYearGroup = null;
                            $enrolmentCheckFormGroup = null;
                            if ($resultEnrolmentCheck->rowCount() == 1) {
                                $rowEnrolmentCheck = $resultEnrolmentCheck->fetch();
                                $enrolmentCheckYearGroup = $rowEnrolmentCheck['tawasulYearGroupID'];
                                $enrolmentCheckFormGroup = $rowEnrolmentCheck['tawasulFormGroupID'];
                            }

                            $form->addHiddenValue($count."-reenrol-tawasulPersonID", $rowReenrol['tawasulPersonID']);
                            $row = $form->addRow();
                                $row->addColumn()->addContent(Format::name('', $rowReenrol['preferredName'], $rowReenrol['surname'], 'Student', true));
                                $row->addColumn()->addContent(__($rowReenrol['name']));
                                $column = $row->addColumn();
                                    $column->addCheckbox($count."-reenrol-enrol")->setValue('Y')->checked('Y')->alignLeft();
                                //If no enrolment, try and work out next year and form group
                                if (is_null($enrolmentCheckYearGroup)) {
                                    $enrolmentCheckYearGroup=$yearGroupGateway->getNextYearGroupID($rowReenrol['tawasulYearGroupID']);
                                    $enrolmentCheckFormGroup=$rowReenrol['tawasulFormGroupIDNext'];
                                }
                                $column = $row->addColumn();
                                    $column->addSelect($count."-reenrol-tawasulYearGroupID")->fromArray($yearGroups)->required()->setClass('shortWidth floatNone')->selected($enrolmentCheckYearGroup);
                                $column = $row->addColumn();
                                        $column->addSelect($count."-reenrol-tawasulFormGroupID")->fromArray($formGroups)->required()->setClass('shortWidth floatNone')->selected($enrolmentCheckFormGroup);
                        }
                        $form->addHiddenValue("reenrol-count", $count);
                    }
                }

                //SET FINAL YEAR USERS TO LEFT
                $form->addRow()->addHeading('Set Final Year Students To Left', __('Set Final Year Students To Left'));
                $form->addRow()->addContent(__('This step finds students in the last year of school and sets their status.'));

                try {
                    $dataFinal = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulYearGroupID' => $lastYearGroup);
                    $sqlFinal = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName, name, category FROM tawasulPerson JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND status='Full' AND category='Student' AND tawasulYearGroupID=:tawasulYearGroupID ORDER BY surname, preferredName";
                    $resultFinal = $connection2->prepare($sqlFinal);
                    $resultFinal->execute($dataFinal);
                } catch (PDOException $e) {
                    $form->addRow()->addAlert($e->getMessage(), 'error');
                }
                if ($resultFinal->rowCount() < 1) {
                    $form->addRow()->addAlert(__('There are no records to display.'), 'warning');
                } else {
                    $row = $form->addRow()->addClass('head break');
                        $row->addColumn()->addContent(__('Name'));
                        $row->addColumn()->addContent(__('Primary Role'));
                        $row->addColumn()->addContent(__('Current Status'));
                        $row->addColumn()->addContent(__('New Status'));
                        $row->addColumn()->addContent(__('Departure Reason'));

                    $count = 0;
                    while ($rowFinal = $resultFinal->fetch()) {
                        $count++;
                        $form->addHiddenValue($count."-final-tawasulPersonID", $rowFinal['tawasulPersonID']);
                        $row = $form->addRow();
                            $row->addContent(Format::name('', $rowFinal['preferredName'], $rowFinal['surname'], 'Student', true));
                            $row->addContent(__($rowFinal['name']));
                            $row->addContent(__('Full'));
                            $row->addSelect($count."-final-status")->fromArray($statuses)->required()->setClass('shortWidth floatNone')->selected('Left');
                            $row->addTextField($count.'-departureReason')->setValue(__('Graduated'))->setSize(12)->setClass('w-32');
                    }
                    $form->addHiddenValue("final-count", $count);
                }

                //REGISTER NEW STAFF
                $form->addRow()->addHeading('Register New Staff', __('Register New Staff'));
                $form->addRow()->addContent(__('Any staff who are not registered will have their status set to "Left".'));

                try {
                    $dataRegister = array();
                    $sqlRegister = "SELECT tawasulPersonID, surname, preferredName, name, category FROM tawasulPerson JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) WHERE status='Expected' AND category='Staff' ORDER BY surname, preferredName";
                    $resultRegister = $connection2->prepare($sqlRegister);
                    $resultRegister->execute($dataRegister);
                } catch (PDOException $e) {
                    $form->addRow()->addAlert($e->getMessage(), 'error');
                }
                if ($resultRegister->rowCount() < 1) {
                    $form->addRow()->addAlert(__('There are no records to display.'), 'warning');
                } else {
                    $row = $form->addRow()->addClass('head break');
                        $row->addColumn()->addContent(__('Name'));
                        $row->addColumn()->addContent(__('Primary Role'));
                        $row->addColumn()->addContent(__('Register'));
                        $row->addColumn()->addContent(__('Type'));
                        $row->addColumn()->addContent(__('Job Title'));

                    $count = 0;
                    while ($rowRegister = $resultRegister->fetch()) {
                        $count++;
                        $form->addHiddenValue($count."-register-tawasulPersonID", $rowRegister['tawasulPersonID']);
                        $row = $form->addRow();
                            $row->addColumn()->addContent(Format::name('', $rowRegister['preferredName'], $rowRegister['surname'], 'Student', true));
                            $row->addColumn()->addContent(__($rowRegister['name']));
                            $column = $row->addColumn();
                                $column->addCheckbox($count."-register-enrol")->setValue('Y')->checked('Y')->alignLeft();
                            $column = $row->addColumn();
                                $column->addSelect($count."-register-type")->fromArray(array('Teaching' => __('Teaching'), 'Support' => __('Support')))->required()->setClass('shortWidth floatNone');
                            $column = $row->addColumn();
                                $column->addtextField($count."-register-jobTitle")->setClass('shortWidth floatNone')->maxLength(100);
                    }
                    $form->addHiddenValue("register-count", $count);
                }


                $row = $form->addRow();
                    $row->addFooter();
                    $row->addSubmit('Proceed');

                echo $form->getOutput();
            }
        }
    } elseif ($step == 3) {
        $nextYearID = $_POST['nextYear'] ?? '';
        $nextYearBySession = $schoolYearGateway->getNextSchoolYearByID($session->get('tawasulSchoolYearID'));
        if (empty($nextYearID) or $nextYearBySession === false or $nextYearID != $nextYearBySession['tawasulSchoolYearID']) {
            echo Format::alert(__('The next school year cannot be determined, so this action cannot be performed.'), 'error');
        } else {

                $dataNext = array('tawasulSchoolYearID' => $nextYearID);
                $sqlNext = 'SELECT * FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID';
                $resultNext = $connection2->prepare($sqlNext);
                $resultNext->execute($dataNext);
            if ($resultNext->rowCount() == 1) {
                $rowNext = $resultNext->fetch();
            }
            $nameNext = $rowNext['name'] ?? '';
            $sequenceNext = $rowNext['sequenceNumber'] ?? '';
            if ($nameNext == '' or $sequenceNext == '') {
                echo Format::alert(__('The next school year cannot be determined, so this action cannot be performed.'), 'error');
            } else {
                echo '<h3>';
                echo __('Step 3');
                echo '</h3>';

                //ADD YEAR FOLLOWING NEXT
                if ($schoolYearGateway->getNextSchoolYearByID($nextYearID) === false) {
                    //ADD YEAR FOLLOWING NEXT
                    echo '<h4>';
                    echo sprintf(__('Add Year Following %1$s'), $nameNext);
                    echo '</h4>';

                    $name = $_POST['nextname'] ?? '';
                    $status = $_POST['nextstatus'] ?? '';
                    $sequenceNumber = $_POST['nextsequenceNumber'] ?? '';
                    $firstDay = Format::dateConvert($_POST['nextfirstDay'] ?? '');
                    $lastDay = Format::dateConvert($_POST['nextlastDay'] ?? '');

                    if ($name == '' or $status == '' or $sequenceNumber == '' or is_numeric($sequenceNumber) == false or $firstDay == '' or $lastDay == '') {
                        echo Format::alert(__('Your request failed because your inputs were invalid.'), 'error');
                    } else {
                        //Check unique inputs for uniqueness

                            $data = array('name' => $name, 'sequenceNumber' => $sequenceNumber);
                            $sql = 'SELECT * FROM tawasulSchoolYear WHERE name=:name OR sequenceNumber=:sequenceNumber';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);

                        if ($result->rowCount() > 0) {
                            echo Format::alert(__('Your request failed because your inputs were invalid.'), 'error');
                        } else {
                            //Write to database
                            $fail = false;
                            try {
                                $data = array('name' => $name, 'status' => $status, 'sequenceNumber' => $sequenceNumber, 'firstDay' => $firstDay, 'lastDay' => $lastDay);
                                $sql = 'INSERT INTO tawasulSchoolYear SET name=:name, status=:status, sequenceNumber=:sequenceNumber, firstDay=:firstDay, lastDay=:lastDay';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $fail = true;
                            }
                            if ($fail == false) {
                                echo Format::alert(__('Your request was completed successfully.'), 'success');
                            }
                        }
                    }
                }

                //Remember year end date of current year before advance
                $dateEnd = $session->get('tawasulSchoolYearLastDay');

                //ADVANCE SCHOOL YEAR
                echo '<h4>';
                echo __('Advance School Year');
                echo '</h4>';

                //Write to database
                $advance = true;
                try {
                    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                    $sql = "UPDATE tawasulSchoolYear SET status='Past' WHERE tawasulSchoolYearID=:tawasulSchoolYearID";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    echo Format::alert(__('Your request failed due to a database error.'), 'error');
                    $advance = false;
                }
                if ($advance) {
                    $advance2 = true;
                    try {
                        $data = array('tawasulSchoolYearID' => $nextYearID);
                        $sql = "UPDATE tawasulSchoolYear SET status='Current' WHERE tawasulSchoolYearID=:tawasulSchoolYearID";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        echo Format::alert(__('Your request failed due to a database error.'), 'error');
                        $advance2 = false;
                    }
                    if ($advance2) {
                        $session->forget('tawasulSchoolYearIDCurrent');
                        SessionFactory::setCurrentSchoolYear($session, $nextYearBySession);

                        echo Format::alert(__('Advance was successful, you are now in a new academic year!'), 'success');

                        //SET EXPECTED USERS TO FULL
                        echo '<h4>';
                        echo __('Set Expected Users To Full');
                        echo '</h4>';

                        $count = $_POST['expect-count'] ?? 0;
                        if (empty($count)) {
                            echo Format::alert(__('No actions were selected in Step 2, and so no changes have been made.'), 'warning');
                        } else {
                            $success = 0;
                            for ($i = 1; $i <= $count; ++$i) {
                                $tawasulPersonID = $_POST["$i-expect-tawasulPersonID"] ?? '';
                                $status = $_POST["$i-expect-status"] ?? 'Expected';

                                //Write to database
                                $expected = true;
                                try {
                                    if ($status == 'Full') {
                                        $data = array('status' => $status, 'tawasulPersonID' => $tawasulPersonID, 'dateStart' => $session->get('tawasulSchoolYearFirstDay'));
                                        $sql = 'UPDATE tawasulPerson SET status=:status, dateStart=:dateStart WHERE tawasulPersonID=:tawasulPersonID';
                                    } elseif ($status == 'Left' or $status == 'Expected') {
                                        $data = array('status' => $status, 'tawasulPersonID' => $tawasulPersonID);
                                        $sql = 'UPDATE tawasulPerson SET status=:status WHERE tawasulPersonID=:tawasulPersonID';
                                    }
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $expected = false;
                                }
                                if ($expected) {
                                    ++$success;
                                }
                            }

                            //Feedback result!
                            if ($success == 0) {
                                echo Format::alert(__('Your request failed.'), 'error');
                            } elseif ($success < $count) {
                                echo Format::alert(sprintf(__('%1$s updates failed.'), ($count - $success)), 'warning');
                            } else {
                                echo Format::alert(__('Your request was completed successfully.'), 'success');
                            }
                        }

                        //ENROL NEW STUDENTS
                        echo '<h4>';
                        echo __('Enrol New Students (Status Expected)');
                        echo '</h4>';

                        $count = $_POST['enrol-count'] ?? 0;
                        if (empty($count)) {
                            echo Format::alert(__('No actions were selected in Step 2, and so no changes have been made.'), 'warning');
                        } else {
                            $success = 0;
                            for ($i = 1; $i <= $count; ++$i) {
                                $tawasulPersonID = $_POST["$i-enrol-tawasulPersonID"] ?? '';
                                $enrol = $_POST["$i-enrol-enrol"] ?? 'N';
                                $tawasulYearGroupID = $_POST["$i-enrol-tawasulYearGroupID"] ?? '';
                                $tawasulFormGroupID = $_POST["$i-enrol-tawasulFormGroupID"] ?? '';

                                //Write to database
                                if ($enrol == 'Y') {
                                    $enrolled = true;
                                    try {
                                        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID, 'tawasulYearGroupID' => $tawasulYearGroupID, 'tawasulFormGroupID' => $tawasulFormGroupID);
                                        $sql = 'INSERT INTO tawasulStudentEnrolment SET tawasulSchoolYearID=:tawasulSchoolYearID, tawasulPersonID=:tawasulPersonID, tawasulYearGroupID=:tawasulYearGroupID, tawasulFormGroupID=:tawasulFormGroupID';
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        $enrolled = false;
                                    }
                                    if ($enrolled) {
                                        ++$success;


                                            $dataFamily = array('tawasulPersonID' => $tawasulPersonID);
                                            $sqlFamily = 'SELECT tawasulFamilyID FROM tawasulFamilyChild WHERE tawasulPersonID=:tawasulPersonID';
                                            $resultFamily = $connection2->prepare($sqlFamily);
                                            $resultFamily->execute($dataFamily);
                                        while ($rowFamily = $resultFamily->fetch()) {

                                                $dataFamily2 = array('tawasulFamilyID' => $rowFamily['tawasulFamilyID']);
                                                $sqlFamily2 = 'SELECT tawasulPerson.tawasulPersonID, tawasulPerson.status FROM tawasulFamilyAdult JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulFamilyAdult.tawasulPersonID) WHERE tawasulFamilyID=:tawasulFamilyID';
                                                $resultFamily2 = $connection2->prepare($sqlFamily2);
                                                $resultFamily2->execute($dataFamily2);
                                            while ($rowFamily2 = $resultFamily2->fetch()) {

                                                    $dataFamily3 = array('tawasulPersonID' => $rowFamily2['tawasulPersonID']);
                                                    $sqlFamily3 = "UPDATE tawasulPerson SET status='Full' WHERE tawasulPersonID=:tawasulPersonID";
                                                    $resultFamily3 = $connection2->prepare($sqlFamily3);
                                                    $resultFamily3->execute($dataFamily3);

                                                    if ($rowFamily2['status'] != 'Full') {
                                                        $userStatusLogGateway->insert(['tawasulPersonID' => $rowFamily2['tawasulPersonID'], 'statusOld' => $rowFamily2['status'], 'statusNew' => 'Full', 'reason' => __('Rollover'), 'tawasulPersonIDModified' => $session->get('tawasulPersonID')]);
                                                    }
                                            }
                                        }
                                    }
                                } else {
                                    $ok = true;
                                    try {
                                        $data = array('tawasulPersonID' => $tawasulPersonID, 'dateEnd' => $dateEnd);
                                        $sql = "UPDATE tawasulPerson SET status='Left', dateEnd=:dateEnd WHERE tawasulPersonID=:tawasulPersonID";
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        $ok == false;
                                    }
                                    if ($ok = true) {
                                        ++$success;

                                        $userStatusLogGateway->insert(['tawasulPersonID' => $tawasulPersonID, 'statusOld' => 'Expected', 'statusNew' => 'Left', 'reason' => __('Rollover'), 'tawasulPersonIDModified' => $session->get('tawasulPersonID')]);
                                    }
                                }
                            }

                            //Feedback result!
                            if ($success == 0) {
                                echo Format::alert(__('Your request failed.'), 'error');
                            } elseif ($success < $count) {
                                echo Format::alert(sprintf(__('%1$s adds failed.'), ($count - $success)), 'warning');
                            } else {
                                echo Format::alert(__('Your request was completed successfully.'), 'success');
                            }
                        }

                        //ENROL NEW STUDENTS
                        echo '<h4>';
                        echo __('Enrol New Students (Status Full)');
                        echo '</h4>';

                        $count = null;
                        if (isset($_POST['enrolFull-count'])) {
                            $count = $_POST['enrolFull-count'] ?? 0;
                        }
                        if ($count == '') {
                            echo Format::alert(__('No actions were selected in Step 2, and so no changes have been made.'), 'warning');
                        } else {
                            $success = 0;
                            for ($i = 1; $i <= $count; ++$i) {
                                $tawasulPersonID = $_POST["$i-enrolFull-tawasulPersonID"] ?? '';
                                $enrol = $_POST["$i-enrolFull-enrol"] ?? 'N';
                                $tawasulYearGroupID = $_POST["$i-enrolFull-tawasulYearGroupID"] ?? '';
                                $tawasulFormGroupID = $_POST["$i-enrolFull-tawasulFormGroupID"] ?? '';

                                //Write to database
                                if ($enrol == 'Y') {
                                    $enrolled = true;

                                    try {
                                        //Check for enrolment
                                        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID);
                                        $sql = 'SELECT * FROM tawasulStudentEnrolment WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID';
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        $enrolled = false;
                                    }
                                    if ($enrolled) {
                                        if ($result->rowCount() == 0) {
                                            try {
                                                $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID, 'tawasulYearGroupID' => $tawasulYearGroupID, 'tawasulFormGroupID' => $tawasulFormGroupID);
                                                $sql = 'INSERT INTO tawasulStudentEnrolment SET tawasulSchoolYearID=:tawasulSchoolYearID, tawasulPersonID=:tawasulPersonID, tawasulYearGroupID=:tawasulYearGroupID, tawasulFormGroupID=:tawasulFormGroupID';
                                                $result = $connection2->prepare($sql);
                                                $result->execute($data);
                                            } catch (PDOException $e) {
                                                $enrolled = false;
                                            }
                                        } elseif ($result->rowCount() == 1) {
                                            try {
                                                $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID, 'tawasulYearGroupID' => $tawasulYearGroupID, 'tawasulFormGroupID' => $tawasulFormGroupID);
                                                $sql = 'UPDATE tawasulStudentEnrolment SET tawasulYearGroupID=:tawasulYearGroupID, tawasulFormGroupID=:tawasulFormGroupID WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID';
                                                $result = $connection2->prepare($sql);
                                                $result->execute($data);
                                            } catch (PDOException $e) {
                                                $enrolled = false;
                                            }
                                        } else {
                                            $enrolled = false;
                                        }
                                    }

                                    if ($enrolled) {
                                        ++$success;

                                            $dataFamily = array('tawasulPersonID' => $tawasulPersonID);
                                            $sqlFamily = 'SELECT tawasulFamilyID FROM tawasulFamilyChild WHERE tawasulPersonID=:tawasulPersonID';
                                            $resultFamily = $connection2->prepare($sqlFamily);
                                            $resultFamily->execute($dataFamily);
                                        while ($rowFamily = $resultFamily->fetch()) {

                                                $dataFamily2 = array('tawasulFamilyID' => $rowFamily['tawasulFamilyID']);
                                                $sqlFamily2 = 'SELECT tawasulPerson.tawasulPersonID, tawasulPerson.status FROM tawasulFamilyAdult JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulFamilyAdult.tawasulPersonID) WHERE tawasulFamilyID=:tawasulFamilyID';
                                                $resultFamily2 = $connection2->prepare($sqlFamily2);
                                                $resultFamily2->execute($dataFamily2);
                                            while ($rowFamily2 = $resultFamily2->fetch()) {

                                                    $dataFamily3 = array('tawasulPersonID' => $rowFamily2['tawasulPersonID']);
                                                    $sqlFamily3 = "UPDATE tawasulPerson SET status='Full' WHERE tawasulPersonID=:tawasulPersonID";
                                                    $resultFamily3 = $connection2->prepare($sqlFamily3);
                                                    $resultFamily3->execute($dataFamily3);

                                                    if ($rowFamily2['status'] != 'Full') {
                                                    $userStatusLogGateway->insert(['tawasulPersonID' => $rowFamily2['tawasulPersonID'], 'statusOld' => $rowFamily2['status'], 'statusNew' => 'Full', 'reason' => __('Rollover'), 'tawasulPersonIDModified' => $session->get('tawasulPersonID')]);
                                                    }
                                            }
                                        }
                                    }
                                } else {
                                    $ok = true;
                                    try {
                                        $data = array('tawasulPersonID' => $tawasulPersonID, 'dateEnd' => $dateEnd);
                                        $sql = "UPDATE tawasulPerson SET status='Left', dateEnd=:dateEnd WHERE tawasulPersonID=:tawasulPersonID";
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        $ok == false;
                                    }
                                    if ($ok = true) {
                                        ++$success;

                                        $userStatusLogGateway->insert(['tawasulPersonID' => $tawasulPersonID, 'statusOld' => 'Full', 'statusNew' => 'Left', 'reason' => __('Rollover'), 'tawasulPersonIDModified' => $session->get('tawasulPersonID')]);
                                    }
                                }
                            }

                            //Feedback result!
                            if ($success == 0) {
                                echo Format::alert(__('Your request failed.'), 'error');
                            } elseif ($success < $count) {
                                echo Format::alert( sprintf(__('%1$s adds failed.'), ($count - $success)), 'warning');
                            } else {
                                echo Format::alert(__('Your request was completed successfully.'), 'success');
                            }
                        }

                        //RE-ENROL OTHER STUDENTS
                        echo '<h4>';
                        echo __('Re-Enrol Other Students');
                        echo '</h4>';

                        $count = null;
                        if (isset($_POST['reenrol-count'])) {
                            $count = $_POST['reenrol-count'] ?? 0;
                        }
                        if ($count == '') {
                            echo Format::alert(__('No actions were selected in Step 2, and so no changes have been made.'), 'warning');
                        } else {
                            $success = 0;

                            for ($i = 1; $i <= $count; ++$i) {
                                $tawasulPersonID = $_POST["$i-reenrol-tawasulPersonID"] ?? '';
                                $enrol = $_POST["$i-reenrol-enrol"] ?? 'N';
                                $tawasulYearGroupID = $_POST["$i-reenrol-tawasulYearGroupID"] ?? '';
                                $tawasulFormGroupID = $_POST["$i-reenrol-tawasulFormGroupID"] ?? '';

                                //Write to database
                                if ($enrol == 'Y') {
                                    $reenrolled = true;
                                    //Check for existing record...if exists, update
                                    try {
                                        $data = array('tawasulSchoolYearID' => $nextYearID, 'tawasulPersonID' => $tawasulPersonID);
                                        $sql = 'SELECT * FROM tawasulStudentEnrolment WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID';
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        $reenrolled = false;
                                    }

                                    if ($result->rowCount() != 1 and $result->rowCount() != 0) {
                                        $reenrolled = false;
                                        echo "<div class='warning'>".__('Potential duplicate enrolment found for user ID {user}', ['user' => $tawasulPersonID]).'</div>';
                                    } elseif ($result->rowCount() == 1) {
                                        try {
                                            $data2 = array('tawasulSchoolYearID' => $nextYearID, 'tawasulPersonID' => $tawasulPersonID, 'tawasulYearGroupID' => $tawasulYearGroupID, 'tawasulFormGroupID' => $tawasulFormGroupID);
                                            $sql2 = 'UPDATE tawasulStudentEnrolment SET tawasulYearGroupID=:tawasulYearGroupID, tawasulFormGroupID=:tawasulFormGroupID WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID';
                                            $result2 = $connection2->prepare($sql2);
                                            $result2->execute($data2);
                                        } catch (PDOException $e) {
                                            $reenrolled = false;
                                        }
                                        if ($reenrolled) {
                                            ++$success;
                                        }
                                    } elseif ($result->rowCount() == 0) {
                                        //Else, write
                                        try {
                                            $data2 = array('tawasulSchoolYearID' => $nextYearID, 'tawasulPersonID' => $tawasulPersonID, 'tawasulYearGroupID' => $tawasulYearGroupID, 'tawasulFormGroupID' => $tawasulFormGroupID);
                                            $sql2 = 'INSERT INTO tawasulStudentEnrolment SET tawasulSchoolYearID=:tawasulSchoolYearID, tawasulPersonID=:tawasulPersonID, tawasulYearGroupID=:tawasulYearGroupID, tawasulFormGroupID=:tawasulFormGroupID';
                                            $result2 = $connection2->prepare($sql2);
                                            $result2->execute($data2);
                                        } catch (PDOException $e) {
                                            $reenrolled = false;
                                        }
                                        if ($reenrolled) {
                                            ++$success;
                                        }
                                    }
                                } else {
                                    $reenrolled = true;
                                    try {
                                        $data = array('tawasulPersonID' => $tawasulPersonID, 'dateEnd' => $dateEnd);
                                        $sql = "UPDATE tawasulPerson SET status='Left', dateEnd=:dateEnd WHERE tawasulPersonID=:tawasulPersonID";
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        $reenrolled = false;
                                    }
                                    if ($reenrolled) {
                                        ++$success;

                                        $userStatusLogGateway->insert(['tawasulPersonID' => $tawasulPersonID, 'statusOld' => 'Full', 'statusNew' => 'Left', 'reason' => __('Rollover'), 'tawasulPersonIDModified' => $session->get('tawasulPersonID')]);
                                    }
                                }
                            }

                            //Feedback result!
                            if ($success == 0) {
                                echo Format::alert(__('Your request failed.'), 'error');
                            } elseif ($success < $count) {
                                echo Format::alert(sprintf(__('%1$s adds failed.'), ($count - $success)), 'warning');
                            } else {
                                echo Format::alert(__('Your request was completed successfully.'), 'success');
                            }
                        }

                        //SET FINAL YEAR STUDENTS TO LEFT
                        echo '<h4>';
                        echo __('Set Final Year Students To Left');
                        echo '</h4>';

                        $count = $_POST['final-count'] ?? 0;

                        if (empty($count)) {
                            echo Format::alert(__('No actions were selected in Step 2, and so no changes have been made.'), 'warning');
                        } else {
                            $success = 0;
                            for ($i = 1; $i <= $count; ++$i) {
                                $tawasulPersonID = $_POST["$i-final-tawasulPersonID"] ?? '';
                                $status = $_POST["$i-final-status"] ?? 'Left';
                                $departureReason = $_POST["$i-departureReason"] ?? '';

                                //Write to database
                                $left = true;
                                try {
                                    $data = array('tawasulPersonID' => $tawasulPersonID, 'dateEnd' => $dateEnd, 'status' => $status, 'departureReason' => $departureReason);
                                    $sql = 'UPDATE tawasulPerson SET status=:status, dateEnd=:dateEnd, departureReason=:departureReason WHERE tawasulPersonID=:tawasulPersonID';
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $left = false;
                                }
                                if ($left) {
                                    ++$success;

                                    $userStatusLogGateway->insert(['tawasulPersonID' => $tawasulPersonID, 'statusOld' => 'Full', 'statusNew' => $status, 'reason' => __('Rollover').': '.__('Set Final Year Students To Left'), 'tawasulPersonIDModified' => $session->get('tawasulPersonID')]);
                                }
                            }

                            //Feedback result!
                            if ($success == 0) {
                                echo Format::alert(__('Your request failed.'), 'error');
                            } elseif ($success < $count) {
                                echo Format::alert(sprintf(__('%1$s updates failed.'), ($count - $success)), 'warning');
                            } else {
                                echo Format::alert(__('Your request was completed successfully.'), 'success');
                            }
                        }

                        //REGISTER NEW STAFF
                        echo '<h4>';
                        echo __('Register New Staff');
                        echo '</h4>';

                        $count = $_POST['register-count'] ?? 0;
                        if (empty($count)) {
                            echo Format::alert(__('No actions were selected in Step 2, and so no changes have been made.'), 'warning');
                        } else {
                            $success = 0;
                            for ($i = 1; $i <= $count; ++$i) {
                                $tawasulPersonID = $_POST["$i-register-tawasulPersonID"] ?? '';
                                $enrol = $_POST["$i-register-enrol"] ?? 'N';
                                $type = $_POST["$i-register-type"] ?? '';
                                $jobTitle = $_POST["$i-register-jobTitle"] ?? '';

                                //Write to database
                                if ($enrol == 'Y') {
                                    $enrolled = true;
                                    //Check for existing record
                                    try {
                                        $dataCheck = array('tawasulPersonID' => $tawasulPersonID);
                                        $sqlCheck = 'SELECT * FROM tawasulStaff WHERE tawasulPersonID=:tawasulPersonID';
                                        $resultCheck = $connection2->prepare($sqlCheck);
                                        $resultCheck->execute($dataCheck);
                                    } catch (PDOException $e) {
                                        $enrolled = false;
                                    }
                                    if ($resultCheck->rowCount() == 0) {
                                        try {
                                            $data = array('tawasulPersonID' => $tawasulPersonID, 'type' => $type, 'jobTitle' => $jobTitle);
                                            $sql = 'INSERT INTO tawasulStaff SET tawasulPersonID=:tawasulPersonID, type=:type, jobTitle=:jobTitle';
                                            $result = $connection2->prepare($sql);
                                            $result->execute($data);
                                        } catch (PDOException $e) {
                                            $enrolled = false;
                                        }
                                        if ($enrolled) {
                                            ++$success;
                                        }
                                    } elseif ($resultCheck->rowCount() == 1) {
                                        try {
                                            $data = array('tawasulPersonID' => $tawasulPersonID, 'type' => $type, 'jobTitle' => $jobTitle);
                                            $sql = 'UPDATE tawasulStaff SET type=:type, jobTitle=:jobTitle WHERE tawasulPersonID=:tawasulPersonID';
                                            $result = $connection2->prepare($sql);
                                            $result->execute($data);
                                        } catch (PDOException $e) {
                                            $enrolled = false;
                                        }
                                        if ($enrolled) {
                                            ++$success;
                                        }
                                    }
                                } else {
                                    $left = true;
                                    try {
                                        $data = array('tawasulPersonID' => $tawasulPersonID, 'dateEnd' => $dateEnd);
                                        $sql = "UPDATE tawasulPerson SET status='Left', dateEnd=:dateEnd WHERE tawasulPersonID=:tawasulPersonID";
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        $left = false;
                                    }
                                    if ($left) {
                                        ++$success;

                                        $userStatusLogGateway->insert(['tawasulPersonID' => $tawasulPersonID, 'statusOld' => 'Expected', 'statusNew' => 'Left', 'reason' => __('Rollover').': '.__('Register New Staff'), 'tawasulPersonIDModified' => $session->get('tawasulPersonID')]);
                                    }
                                }
                            }

                            //Feedback result!
                            if ($success == 0) {
                                echo Format::alert(__('Your request failed.'), 'error');
                            } elseif ($success < $count) {
                                echo Format::alert(sprintf(__('%1$s adds failed.'), ($count - $success)), 'warning');
                            } else {
                                echo Format::alert(__('Your request was completed successfully.'), 'success');
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
