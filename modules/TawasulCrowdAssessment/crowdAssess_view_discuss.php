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

use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\Planner\PlannerEntryHomeworkGateway;
use TawasulOS\Domain\CrowdAssessment\CrowdAssessDiscussGateway;
use TawasulOS\Data\Validator;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulCrowdAssessment/crowdAssess_view_discuss.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get class variable
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    $tawasulPlannerEntryID = $_GET['tawasulPlannerEntryID'] ?? '';
    $tawasulPlannerEntryHomeworkID = $_GET['tawasulPlannerEntryHomeworkID'] ?? '';

    $urlParams = ['tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulPersonID' => $tawasulPersonID];
    $page->breadcrumbs
        ->add(__('View All Assessments'), 'crowdAssess.php')
        ->add(__('View Assessment'), 'crowdAssess_view.php', $urlParams)
        ->add(__('Discuss'));

    $plannerHomeworkGateway = $container->get(PlannerEntryHomeworkGateway::class);
    $crowdDiscussionGateway = $container->get(CrowdAssessDiscussGateway::class);
    $validator = $container->get(Validator::class);

    // Check required values
    if (empty($tawasulPersonID) || empty($tawasulPlannerEntryID) || empty($tawasulPlannerEntryHomeworkID)) {
        $page->addError(__('Student, lesson or homework has not been specified .'));
        return;
    }

    // Check existence of and access to this lesson.
    $and = " AND tawasulPlannerEntryID=$tawasulPlannerEntryID";
    $sql = getLessons($guid, $connection2, $and);
    $lesson = $pdo->select($sql[1], $sql[0])->fetch();

    if (empty($lesson)) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }

    $role = getCARole($guid, $connection2, $lesson['tawasulCourseClassID']);

    // Get Student work
    $sqlList = getStudents($guid, $connection2, $role, $lesson['tawasulCourseClassID'], $lesson['homeworkCrowdAssessOtherTeachersRead'], $lesson['homeworkCrowdAssessOtherParentsRead'], $lesson['homeworkCrowdAssessSubmitterParentsRead'], $lesson['homeworkCrowdAssessClassmatesParentsRead'], $lesson['homeworkCrowdAssessOtherStudentsRead'], $lesson['homeworkCrowdAssessClassmatesRead'], " AND tawasulPerson.tawasulPersonID=$tawasulPersonID");

    if (empty($sqlList)) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }

    $student = $pdo->select($sqlList[1], $sqlList[0])->fetch();
    if (empty($student)) {
        $page->addError(__('There is currently no work to assess.'));
        return;
    }

    $homework = $plannerHomeworkGateway->selectBy(['tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulPersonID' => $tawasulPersonID])->fetch();

    // DETAILS
    $table = DataTable::createDetails('crowdAssessment');

    $table->addHeaderAction('add', __m('Add'))
        ->addParam('tawasulPersonID', $tawasulPersonID)
        ->addParam('tawasulPlannerEntryID', $tawasulPlannerEntryID)
        ->addParam('tawasulPlannerEntryHomeworkID', $tawasulPlannerEntryHomeworkID)
        ->setURL('/modules/TawasulCrowdAssessment/crowdAssess_view_discuss_post.php')
        ->displayLabel();

    $table->addColumn('name', __('Student'))
        ->format(Format::using('nameLinked', ['tawasulPersonID', '', 'preferredName', 'surname', 'Student', false, true]));
        $table->addColumn('read', __('Read'))
        ->format(function($student) {
            if (empty($student)) return '';

            if ($student['status'] == 'Exemption') {
                $linkText = 'Exemption';
            } elseif ($student['version'] == 'Final') {
                $linkText = 'Final';
            } else {
                $linkText = 'Draft'.$student['count'];
            }

            if ($student['type'] == 'File' || $student['type'] == 'Link') {
                $url = $student['type'] == 'File'  ? './'.$student['location'] : $student['location'];
                $title = $student['version'].' '.sprintf(__('Submitted at %1$s on %2$s'), substr($student['timestamp'], 11, 5), Format::date($student['timestamp']));
                return Format::link($url, $linkText, ['title' => $title]);
            } else {
                $title = sprintf(__('Recorded at %1$s on %2$s'), substr($student['timestamp'], 11, 5), Format::date($student['timestamp']));
                return Format::tooltip($linkText, $title);
            }
        });

    echo $table->render([$student + $homework]);
    echo '<br/>';

   
    // DISCUSSION - recursive
    $getDiscussion = function ($tawasulPlannerEntryHomeworkID, $urlParams, $parent = null, $level = null) use (&$getDiscussion, &$crowdDiscussionGateway, &$validator) {
        $discussion = [];
        $items = $crowdDiscussionGateway->selectDiscussionByHomeworkID($tawasulPlannerEntryHomeworkID, $parent)->fetchAll();
        foreach ($items as $item) {
            $item['replies'] = $getDiscussion($tawasulPlannerEntryHomeworkID, $urlParams, $item['tawasulCrowdAssessDiscussID'], $level + 1);
            $item['comment'] = $validator->sanitizeRichText($item['comment']);

            if ($level < 3) {
                $item['attachmentLocation'] = "index.php?q=/modules/TawasulCrowdAssessment/crowdAssess_view_discuss_post.php&".http_build_query($urlParams)."&tawasulPlannerEntryHomeworkID=$tawasulPlannerEntryHomeworkID&replyTo=".$item['tawasulCrowdAssessDiscussID'];
                $item['attachmentText'] = __('Reply');
                $item['attachmentTarget'] = '';
            }
            $discussion[] = $item;
        }

        return $discussion;
    };
    
    $discussion = $getDiscussion($homework['tawasulPlannerEntryHomeworkID'], $urlParams, 0);

    echo $page->fetchFromTemplate('ui/discussion.twig.html', [
        'title' => '',
        'compact' => true, 
        'discussion' => $discussion
    ]);
}
