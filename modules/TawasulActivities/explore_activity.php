<?php
/*
TawasulOS, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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

use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Activities\ActivityCategoryGateway;
use TawasulOS\Domain\Activities\ActivityPhotoGateway;
use TawasulOS\Domain\Activities\ActivityStaffGateway;
use TawasulOS\Domain\Activities\ActivityStudentGateway;
use TawasulOS\Domain\Students\StudentGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/explore_activity.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $tawasulActivityCategoryID = $_REQUEST['tawasulActivityCategoryID'] ?? '';
    $tawasulActivityID = $_REQUEST['tawasulActivityID'] ?? '';
    $tawasulPersonID = $session->get('tawasulPersonID');

    $page->breadcrumbs
        ->add(__('Explore Activities'), 'explore.php')
        ->add(__('Category'), 'explore_category.php', ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'sidebar' => 'false'])
        ->add(__('Activity'));

    $page->return->addReturns([
        'error4' => __m('Sign up is currently not available for this activity.'),
        'error5' => __m('There was an error verifying your activity choices. Please try again.'),
    ]);

    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $canSignUp = isActionAccessible($guid, $connection2, '/modules/TawasulActivities/explore_activity_signUp.php', 'Explore Activities_studentRegister');
    $canViewInactive = isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage.php');

    // Check records exist and are available
    $categoryGateway = $container->get(ActivityCategoryGateway::class);
    $activityGateway = $container->get(ActivityGateway::class);
    $enrolmentGateway = $container->get(ActivityStudentGateway::class);
    $activityPhotoGateway = $container->get(ActivityPhotoGateway::class);
    $staffGateway = $container->get(ActivityStaffGateway::class);

    if (empty($tawasulActivityID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $activity = $activityGateway->getActivityDetailsByID($tawasulActivityID);
    $category = $categoryGateway->getCategoryDetailsByID($activity['tawasulActivityCategoryID'] ?? '');

    if (empty($activity) || empty($category)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    if (($activity['active'] != 'Y' || $category['active'] != 'Y') && !$canViewInactive) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    if ($category['viewable'] != 'Y'  && !$canViewInactive) {
        $page->addMessage(__m('This activity is not viewable at this time. Please return to the categories page to explore a different activity.'));
        return;
    }

    // Can register for family children
    $signUpChildren = [];
    if ($highestAction == 'Explore Activities_registerByParent') {
        $categoryYearGroups = explode(',', $category['tawasulYearGroupIDParentRegister'] ?? ''); 
        $children = $container->get(StudentGateway::class)
            ->selectAnyStudentsByFamilyAdult($session->get('tawasulSchoolYearID'), $session->get('tawasulPersonID'))
            ->fetchAll();

        $canSignUp = true;
        foreach ($children as $child) {
            if ($category['tawasulSchoolYearID'] != $session->get('tawasulSchoolYearID')) continue;

            if (in_array($child['tawasulYearGroupID'], $categoryYearGroups)) {
                $child['signUpCategory'] = $categoryGateway->getCategorySignUpAccess($activity['tawasulActivityCategoryID'], $child['tawasulPersonID']);
                $child['signUpActivity'] = $activityGateway->getActivitySignUpAccess($tawasulActivityID, $child['tawasulPersonID']);
                
                if ($child['signUpCategory'] && $child['signUpActivity']) {
                    $signUpChildren[] = $child;
                }
            }
        }
    }

    // Get photos & blocks
    $activity['photos'] = $activityPhotoGateway->selectPhotosByActivity($tawasulActivityID)->fetchAll();
    // $activity['blocks'] = $unitBlockGateway->selectBlocksByUnit($activity['deepLearningUnitID'])->fetchAll();

    // Check sign-up access
    $now = (new DateTime('now'))->format('U');
    $signUpIsOpen = false;
    $isPastEvent = false;

    if (!empty($category['accessOpenDate']) && !empty($category['accessCloseDate'])) {
        $accessOpenDate = DateTime::createFromFormat('Y-m-d H:i:s', $category['accessOpenDate'])->format('U');
        $accessCloseDate = DateTime::createFromFormat('Y-m-d H:i:s', $category['accessCloseDate'])->format('U');

        $signUpIsOpen = $accessOpenDate <= $now && $accessCloseDate >= $now;
    }

    if (!empty($category['endDate'])) {
        $endDate = DateTime::createFromFormat('Y-m-d', $category['endDate'])->format('U');
        $isPastEvent = $now >= $endDate;
    }

    $signUpCategory = $categoryGateway->getCategorySignUpAccess($activity['tawasulActivityCategoryID'], $session->get('tawasulPersonID'));
    $signUpActivity = $activityGateway->getActivitySignUpAccess($tawasulActivityID, $session->get('tawasulPersonID'));

    // echo '<pre>';
    // print_r("highestAction = ".$highestAction.'<br/>');
    // print_r("canSignUp = ".$canSignUp.'<br/>');
    // print_r("signUpIsOpen = ".$signUpIsOpen.'<br/>');
    // print_r("signUpCategory = ".$signUpCategory.'<br/>');
    // print_r("signUpActivity = ".$signUpActivity.'<br/>');
    // print_r("signUpAccess = ".(!empty($signUpCategory) && !empty($signUpActivity)) .'<br/>');
    // print_r("signUpParentAccess = ".(!empty($signUpChildren)).'<br/>');
    // echo '</pre>';

    // $enrolment = $enrolmentGateway->getActivityDetailsByEnrolment($activity['tawasulActivityCategoryID'], $session->get('tawasulPersonID'), $tawasulActivityID);

    $canEdit = isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage.php');
    $isStaff = $staffGateway->getActivityAccessByStaff($tawasulActivityID, $session->get('tawasulPersonID'));

    $page->writeFromTemplate('activity.twig.html', [
        'category'      => $category,
        'activity'      => $activity,

        'nextActivity' => $activityGateway->getNextActivityByID($tawasulActivityCategoryID, $tawasulActivityID),
        'prevActivity' => $activityGateway->getPreviousActivityByID($tawasulActivityCategoryID, $tawasulActivityID),

        'canViewInactive' => $canViewInactive,
        'canSignUp'  => $canSignUp,
        'signUpIsOpen' => $signUpIsOpen,
        'signUpAccess' => $signUpCategory && $signUpActivity,

        'signUpParentAccess' => !empty($signUpChildren),
        'signUpChildren' => $signUpChildren,

        'isPastEvent' => $isPastEvent,
        'isEnrolled' => !empty($enrolment) && $enrolment['tawasulActivityID'] == $tawasulActivityID,
        // 'enrolment' => $enrolment,

        'canEdit' => $canEdit,
        'isStaff' => !empty($isStaff),
    ]);
}
