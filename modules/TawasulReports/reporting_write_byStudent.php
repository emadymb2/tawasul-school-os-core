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
use TawasulOS\Tables\DataTable;
use TawasulOS\UI\Components\Alert;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\System\HookGateway;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\Timetable\CourseClassPersonGateway;
use Tos\Module\TawasulReports\Forms\ReportingSidebarForm;
use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;
use Tos\Module\TawasulReports\Domain\ReportingScopeGateway;
use Tos\Module\TawasulReports\Domain\ReportingAccessGateway;
use Tos\Module\TawasulReports\Domain\ReportingProgressGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_write_byStudent.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $tawasulPersonIDStudent = $_REQUEST['tawasulPersonIDStudent'] ?? '';
    $tawasulPersonID = isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_cycles_manage.php')
        ? $_REQUEST['tawasulPersonID'] ?? $session->get('tawasulPersonID')
        : $session->get('tawasulPersonID');
    $urlParams = [
        'tawasulSchoolYearID' => $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID'),
        'tawasulReportingCycleID' => $_GET['tawasulReportingCycleID'] ?? '',
        'tawasulReportingScopeID' => $_GET['tawasulReportingScopeID'] ?? '',
        'scopeTypeID' => $_GET['scopeTypeID'] ?? '',
        'tawasulPersonID' => $tawasulPersonID,
        'allStudents' => $_GET['allStudents'] ?? '',
    ];

    if (!empty($_GET['criteriaSelector'])) {
        list($urlParams['tawasulReportingScopeID'], $urlParams['scopeTypeID']) = explode('-', $_GET['criteriaSelector']);
    }

    // SIDEBAR: Dropdowns
    $sidebarForm = $container->get(ReportingSidebarForm::class)->createForm($urlParams);
    $session->set('sidebarExtra', $sidebarForm->getOutput());

    $page->scripts->add('chart');

    $page->breadcrumbs
        ->add(__('My Reporting'), 'reporting_my.php', ['tawasulPersonID' => $tawasulPersonID])
        ->add(__('Write Reports'), 'reporting_write.php', $urlParams)
        ->add(__('By Student'));

    $reportingAccessGateway = $container->get(ReportingAccessGateway::class);

    $reportingScope = $container->get(ReportingScopeGateway::class)->getByID($urlParams['tawasulReportingScopeID']);
    if (empty($reportingScope)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $reportingCycle = $container->get(ReportingCycleGateway::class)->getByID($reportingScope['tawasulReportingCycleID']);
    if (empty($reportingCycle)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // ACCESS CHECK: overall check (for high-level access) or per-scope check for general access
    $accessCheck = $reportingAccessGateway->getAccessToScopeByPerson($urlParams['tawasulReportingScopeID'], $session->get('tawasulPersonID'));
    if ($highestAction == 'Write Reports_editAll') {
        $reportingOpen = ($accessCheck['reportingOpen'] ?? 'N') == 'Y';
        $canAccessReport = true;
        $canWriteReport = true;
    } elseif ($highestAction == 'Write Reports_mine') {
        $writeCheck = $reportingAccessGateway->getAccessToScopeAndCriteriaGroupByPerson($urlParams['tawasulReportingScopeID'], $reportingScope['scopeType'], $urlParams['scopeTypeID'], $session->get('tawasulPersonID'));
        $reportingOpen = ($writeCheck['reportingOpen'] ?? 'N') == 'Y';
        $canAccessReport = ($accessCheck['canAccess'] ?? 'N') == 'Y';
        $canWriteReport = $reportingOpen && ($writeCheck['canWrite'] ?? 'N') == 'Y';
    }

    if (empty($canAccessReport)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    if ($reportingScope['scopeType'] == 'Year Group') {
        $scopeIdentifier = 'tawasulYearGroupID';
    } elseif ($reportingScope['scopeType'] == 'Form Group') {
        $scopeIdentifier = 'tawasulFormGroupID';
    } elseif ($reportingScope['scopeType'] == 'Course') {
        $scopeIdentifier = 'tawasulCourseClassID';
    }

    // Check for student enrolment info, fallback to user info
    $student = $container->get(StudentGateway::class)->selectActiveStudentByPerson($reportingCycle['tawasulSchoolYearID'], $tawasulPersonIDStudent)->fetch();
    if (empty($student)) {
        $student = $container->get(UserGateway::class)->getByID($tawasulPersonIDStudent);
    }

    if (empty($student)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $scopeDetails = $reportingAccessGateway->selectReportingDetailsByScope($urlParams['tawasulReportingScopeID'], $reportingScope['scopeType'], $urlParams['scopeTypeID'])->fetch();
    $relatedReports = $container->get(ReportingScopeGateway::class)->selectRelatedReportingScopesByID($urlParams['tawasulReportingScopeID'], $reportingScope['scopeType'], $urlParams['scopeTypeID'])->fetchAll();
    $reportingProgress = $container->get(ReportingProgressGateway::class)->selectBy(['tawasulReportingScopeID' => $urlParams['tawasulReportingScopeID'], $scopeIdentifier => $urlParams['scopeTypeID'], 'tawasulPersonIDStudent' => $tawasulPersonIDStudent])->fetch();
    $student['alerts'] = $container->get(Alert::class)->getAlertBar($tawasulPersonIDStudent, ['wrap' => false, 'target' => '_blank']);
    
    $progress = $reportingAccessGateway->selectReportingProgressByScope($urlParams['tawasulReportingScopeID'], $reportingScope['scopeType'], $urlParams['scopeTypeID'], $urlParams['allStudents'] == 'Y')->fetchGroupedUnique();
    $progressComplete = array_reduce($progress, function ($group, $item) {
        return $item['progress'] == 'Complete' ? $group + 1 : $group;
    }, 0);

    $keys = array_flip(array_keys($progress));
    $values = array_values($progress);

    if (!empty($keys[$tawasulPersonIDStudent])) {
        $prevStudent = $values[$keys[$tawasulPersonIDStudent] -1] ?? $values[count($values)-1] ?? 0;
        $nextStudent = $values[$keys[$tawasulPersonIDStudent] +1] ?? $values[0] ?? 0;
    }

    echo $page->fetchFromTemplate('ui/reportingStudentHeader.twig.html', [
        'canWriteReport' => $canWriteReport,
        'reportingOpen' => $reportingOpen,
        'student' => $student,
        'scopeDetails' => $scopeDetails,
        'relatedReports' => $relatedReports,
        'prevStudent' => $prevStudent ?? '',
        'nextStudent' => $nextStudent ?? '',
        'params' => $urlParams,
    ]);

    // PER STUDENT CRITERIA
    $reportingCriteria = $reportingAccessGateway->selectReportingCriteriaByStudentAndScope($reportingScope['tawasulReportingScopeID'], $reportingScope['scopeType'], $urlParams['scopeTypeID'], $tawasulPersonIDStudent)->fetchAll();

    // CLASS TEACHER CHECK
    $classTeachers = [];
    if ($reportingScope['scopeType'] == 'Course') {
        $classTeachers = $container->get(CourseClassPersonGateway::class)->selectTeachersByClass($urlParams['scopeTypeID'])->fetchGroupedUnique();
    }

    // FORM
    $form = Form::create('reportingWrite', $session->get('absoluteURL').'/modules/TawasulReports/reporting_write_byStudentProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->enableQuickSave();

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulSchoolYearID', $urlParams['tawasulSchoolYearID']);
    $form->addHiddenValue('tawasulReportingCycleID', $reportingScope['tawasulReportingCycleID']);
    $form->addHiddenValue('tawasulReportingScopeID', $reportingScope['tawasulReportingScopeID']);
    $form->addHiddenValue('tawasulPersonIDStudent', $tawasulPersonIDStudent);
    $form->addHiddenValue('tawasulPersonIDNext', $nextStudent['tawasulPersonID'] ?? '');
    $form->addHiddenValue('scopeTypeID', $urlParams['scopeTypeID']);
    $form->addHiddenValue('allStudents', $urlParams['allStudents']);
    $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);

    $form->addRow()->addClass('reportStatus')->addContent(Format::alert($scopeDetails['name'] ?? '', 'empty'))->wrap('<h4 class="p-0">', '</h4>');

    // HOOKS
    // Custom hooks can replace form fields by criteria type using a custom include.
    // Includes are loaded inside a function to limit their variable scope.
    $hooks = $container->get(HookGateway::class)->selectHooksByType('Report Writing')->fetchGroupedUnique();
    $hookInclude = function ($options, $criteria) use (&$session, &$container, &$form, $student, $scopeDetails, $reportingScope, $reportingCriteria, $urlParams, $canWriteReport) {
        $options = json_decode($options['options'] ?? '', true);
        $includePath = $session->get('absolutePath').'/modules/'.$options['sourceModuleName'].'/'.$options['sourceModuleInclude'];

        if (!empty($options) && is_file($includePath)) {
            include $includePath;
            return true;
        }

        return false;
    };

    $lastCategory = '';
    $manualAuthorSelect = null;

    foreach ($reportingCriteria as $criteria) {
        $fieldName = "value[{$criteria['tawasulReportingCriteriaID']}]";
        $fieldID = "value{$criteria['tawasulReportingCriteriaID']}";

        // All scopes: check if report author is not the same as report editor or current user
        // Course scope: check if report author not a class teacher
        if ((!empty($criteria['tawasulPersonIDCreated']) && $criteria['tawasulPersonIDCreated'] != $session->get('tawasulPersonID')) || (!empty($criteria['tawasulPersonIDModified']) && $criteria['tawasulPersonIDModified'] != $criteria['tawasulPersonIDCreated']) || (!empty($classTeachers) && empty($classTeachers[$criteria['tawasulPersonIDCreated']])) ) {
            $manualAuthorSelect = $criteria['tawasulPersonIDCreated'];
        }

        if (!empty($criteria['category']) && $criteria['category'] != $lastCategory) {
            $row = $form->addRow()->addContent($criteria['category'])->wrap('<h5 class="my-2 p-0 text-sm normal-case border-0">', '</h5>');
        }

        if (isset($hooks[$criteria['criteriaName']])) {
            // Attempt to load a hook, otherwise display an alert.
            if (!$hookInclude($hooks[$criteria['criteriaName']], $criteria)) {
                $form->addHiddenValue($fieldName, $criteria['value']);
                $form->addRow()->addAlert(__('Failed to load {name}', [
                    'name' => $criteria['criteriaName'],
                ]), 'error');
            }

        } elseif ($criteria['valueType'] == 'Comment' || $criteria['valueType'] == 'Remark') {
            $col = $form->addRow()->addColumn();
            $col->addLabel($fieldName, $criteria['name'])->description($criteria['description']);
            $col->addCommentEditor($fieldName)
                ->checkName($student['preferredName'])
                ->checkPronouns($student['gender'])
                ->addClass('reportCriteria')
                ->setID($fieldID)
                ->maxLength($criteria['characterLimit'])
                ->setValue($criteria['comment'])
                ->readonly(!$canWriteReport);
        } else {
            $row = $form->addRow();
            $row->addLabel($fieldName, $criteria['name'])->description($criteria['description']);

            if ($criteria['valueType'] == 'Grade Scale') {
                $gradeSelect = $row->addSelectGradeScaleGrade($fieldName, $criteria['tawasulScaleID'], ['valueMode' => 'value', 'labelMode' => 'both', 'honourDefault' => true])
                    ->addClass('reportCriteria')
                    ->setID($fieldID)
                    ->readonly(!$canWriteReport);

                if (!is_null($criteria['tawasulReportingValueID'])) {
                    $gradeSelect->selected($criteria['value']);
                }
            } elseif ($criteria['valueType'] == 'Yes/No') {
                $row->addYesNo($fieldName)
                    ->addClass('reportCriteria')
                    ->setID($fieldID)
                    ->selected($criteria['value'] ?? $criteria['defaultValue'])
                    ->placeholder()
                    ->readonly(!$canWriteReport);
            } elseif ($criteria['valueType'] == 'Number') {
                $row->addNumber($fieldName)
                    ->addClass('reportCriteria')
                    ->setID($fieldID)
                    ->setValue($criteria['value'])
                    ->maxLength(20)
                    ->onlyInteger(false)
                    ->readonly(!$canWriteReport);
            } elseif ($criteria['valueType'] == 'Image') {
                $row->addFileUpload('file'.$criteria['tawasulReportingCriteriaID'])
                    ->addClass('reportCriteria')
                    ->setID($fieldID)
                    ->setAttachment($fieldName, $session->get('absoluteURL'), $criteria['value'] ?? '')
                    ->readonly(!$canWriteReport);
            } else {
                $row->addTextField($fieldName)
                    ->addClass('reportCriteria')
                    ->setID($fieldID)
                    ->maxLength(255)
                    ->setValue($criteria['value'])
                    ->readonly(!$canWriteReport);
            }
        }

        $lastCategory = $criteria['category'];
    }

    // Enable manually selecting the author
    if ($canWriteReport && !empty($manualAuthorSelect)) {
        $row = $form->addRow();
        $row->addLabel($fieldName, __('Report Author'))->description(__('When a report is edited by multiple users, you can manually attribute the report to a specific staff member.'));
        $row->addSelectStaff('tawasulPersonIDCreated')->selected($manualAuthorSelect);
    }

    if ($reportingScope['scopeType'] == 'Form Group') {
        $reportingRemarks = $reportingAccessGateway->selectAllRemarksByStudent($reportingScope['tawasulReportingCycleID'], $tawasulPersonIDStudent)->fetchAll();

        if (!empty($reportingRemarks)) {

            $col = $form->addRow()->addColumn();
            $col->addLabel('remarks', __('Remarks'))
                ->addClass('sm:max-w-md')
                ->description(__('Remarks are comments shared by other staff members. They do not appear on the report.'));

            foreach ($reportingRemarks as $remark) {
                $remarkDetails = $reportingAccessGateway->selectReportingDetailsByScope($remark['tawasulReportingScopeID'], $remark['scopeType'], $remark['scopeTypeID'])->fetch();

                $remarkText = $page->fetchFromTemplate('ui/statusComment.twig.html', [
                    'name'    => Format::name($remark['title'], $remark['preferredName'], $remark['surname'], 'Staff', false, true),
                    'action'  => '',
                    'photo'   => $remark['image_240'],
                    'date'    => Format::relativeTime($remark['timestampModified']),
                    'status'  => $remarkDetails['name'] ?? $remark['name'],
                    'tag'     => '',
                    'comment'    => !empty($remark['comment']) ? $remark['comment'] : __('N/A'),
                ]);

                $col->addContent($remarkText);
            }
        }
    }

    $row = $form->addRow();
        $row->addLabel('complete', __('Progress'));
        $row->addCheckbox('complete')
            ->description(__('Complete'))
            ->addClass('align-middle reportCriteria')
            ->setLabelClass('inline-block pt-2 pb-1 px-2 text-base align-middle')
            ->checked($reportingProgress && $reportingProgress['status'] == 'Complete')
            ->readonly(!$canWriteReport)
            ->setDisabled(!$canWriteReport);

    if ($canWriteReport) {
        $col = $form->addRow()->addColumn()->setClass('flex items-center justify-end gap-2');
            $col->addSubmit(__('Save'))->onClick('save()')->setColor('gray')
                ->prepend(sprintf('<span class="unsavedChanges tag message mr-2" style="display:none;" title="%1$s">%1$s</span>', __('Unsaved Changes')));
            $col->addSubmit(__('Save & Next'));
    }

    echo $form->getOutput();

    // CRITERIA FROM ALL SCOPES
    if ($reportingScope['scopeType'] != 'Course') {
        $reportCriteria = $reportingAccessGateway->selectReportingCriteriaByStudent($reportingScope['tawasulReportingCycleID'], $tawasulPersonIDStudent)->fetchGrouped();

        echo $page->fetchFromTemplate('ui/writingStudentOverview.twig.html', [
            'student' => $student,
            'reportCriteria' => $reportCriteria,
            'params' => $urlParams,
            'canWriteReport' => $canWriteReport,
        ]);

        // FOOTER
        echo $page->fetchFromTemplate('ui/reportingStudentFooter.twig.html', [
            'student' => $student,
            'scopeDetails' => $scopeDetails,
            'prevStudent' => $prevStudent ?? '',
            'nextStudent' => $nextStudent ?? '',
            'params' => $urlParams,
        ]);
    }

    // SIDEBAR
    $session->set('sidebarExtra', $session->get('sidebarExtra') . $page->fetchFromTemplate('ui/writingSidebar.twig.html', [
        'tawasulPersonIDStudent' => $tawasulPersonIDStudent,
        'totalCount' => count($progress),
        'progressCount' => $progressComplete,
        'students' => $progress,
        'params' => $urlParams,
    ]));
}
?>

<script>
var edited = false;
var complete = false;
var readonly = <?php echo !empty($canWriteReport) && $canWriteReport ? 'false' : 'true'; ?>;

updateStatus();

$(document).ready(function(){
    autosize($('textarea'));
});

function save() {
    $('[name="tawasulPersonIDNext"]').val('');
    document.getElementById('reportingWrite').submit()
}

$('.reportCriteria').on('input', function() {
    edited = true;
    updateStatus();

    window.onbeforeunload = function(event) {
        if (event.explicitOriginalTarget.value=='Save' || event.explicitOriginalTarget.value=='Save & Next') return;
        return "<?php echo __('There are unsaved changes on this page.') ?>";
    };
});

function updateStatus() {
    complete = $('#complete:checked').length > 0;
    displayStatus();
}

function displayStatus(){
    if (readonly) {
        $('div.reportStatus div').removeClass('empty').addClass('dull');
        $('div.reportStatus h4').html('<?php echo __('Read-only') ?>');
    } else if (complete) {
        $('section.reportStatus').removeClass('border-blue-600').addClass('border-green-600');
        $('div.reportStatus div').removeClass('empty message').addClass('success');
        $('div.reportStatus h4').html('<?php echo __('Complete') ?>');
    } else if (edited) {
        $('section.reportStatus').removeClass('border-green-600').addClass('border-blue-600');
        $('div.reportStatus div').removeClass('empty success').addClass('message');
        $('div.reportStatus h4').html('<?php echo __('Editing') ?>');
    } else {
        $('section.reportStatus').removeClass('border-green-600');
        $('div.reportStatus div').removeClass('success');
    }

    $('button[value="Save & Next"]').toggle(complete);

    if (edited) {
        $('.unsavedChanges').show();
        $('div.reportStatus h4').html($('div.reportStatus h4').html() + '<span class="inline-block pl-4 normal-case font-normal text-gray-700 text-xs"><?php echo __('There are unsaved changes on this page.') ?></span>');
    }
}

</script>
