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

use TawasulOS\Tables\DataTable;
use TawasulOS\Services\Format;
use TawasulOS\Domain\School\YearGroupGateway;
use TawasulOS\Domain\Activities\ActivityCategoryGateway;
use TawasulOS\Http\Url;

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_categories.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage Categories'));

    echo Format::alert("Welcome to the new activities sign-up system, which offers much more balanced and feature-rich sign-up process. These categories replace the old competitive activity sign-up system, which can still be found in School Admin > Activity Settings. This new system gives students better backup choices, and reduces the stress of sign-up by removing the time pressure, which is also better for server load. It also has a visual page to explore activities with photos and descriptions.<br/></br>If you do not plan to use the new system right away, please disable Explore Activities in User Admin > Manage Permissions. Otherwise, when using the new system, be sure to leave any of the old registration settings off in School Admin > Activity Settings<br/></br><b>The old competitive sign-up system will be deprecated and removed in TawasulOS v31.0.00</b>", 'message');

    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
    $page->navigator->addSchoolYearNavigation($tawasulSchoolYearID);

    // Query events
    $categoryGateway = $container->get(ActivityCategoryGateway::class);

    $criteria = $categoryGateway->newQueryCriteria()
        ->sortBy(['sequenceNumber'])
        ->fromPOST();

    $events = $categoryGateway->queryCategories($criteria, $tawasulSchoolYearID);
    $yearGroupCount = $container->get(YearGroupGateway::class)->getYearGroupCount();

    // Render table
    $table = DataTable::createPaginated('events', $criteria);

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/TawasulActivities/activities_categories_add.php')
        ->displayLabel();

    $table->modifyRows(function($values, $row) {
        if ($values['active'] == 'N') return $row->addClass('error');
        if (empty($values['viewableDate'])) $row->addClass('dull');
        return $row;
    });

    $table->addDraggableColumn('tawasulActivityCategoryID', $session->get('absoluteURL').'/modules/TawasulActivities/activities_categories_editOrderAjax.php');

    $table->addColumn('name', __('Name'))
        ->sortable(['tawasulActivityCategory.name'])
        ->context('primary')
        ->format(function ($values) {
            $url = Url::fromModuleRoute('TawasulActivities', 'view_event.php')->withQueryParams(['tawasulActivityCategoryID' => $values['tawasulActivityCategoryID'], 'sidebar' => 'false']);
            return $values['active'] == 'Y' && !empty($values['viewableDate']) 
                ? Format::link($url, $values['name'])
                : $values['name'];
        });

    $table->addColumn('viewableDate', __('Viewable'))
        ->width('10%')
        ->format(function ($values) {
            if (empty($values['viewableDate'])) {
                return Format::tag(__('No'), 'dull');
            }
            if (!empty($values['viewableDate']) && empty($values['backgroundImage'])) {
                return Format::tag(__('No'), 'warning', __('This event is missing a header image and is not viewable in the events list.'));
            }
            if ($values['viewable'] == 'Y') {
                return Format::tag(__('Yes'), 'success');
            } else {
                return Format::tag(__('No'), 'dull');
            }
        })
        ->formatDetails(function ($values) {
            return Format::small(Format::dateReadable($values['viewableDate']));
        });

    $table->addColumn('signUp', __('Sign-up'))
        ->sortable(['accessOpenDate'])
        ->format(function ($values) {
            if (empty($values['accessOpenDate']) || empty($values['accessCloseDate'])) {
                return Format::tag(__('No'), 'dull');
            }

            if (date('Y-m-d H:i:s') >= $values['accessCloseDate']) {
                return Format::tag(__('Closed'), 'dull');
            } elseif (date('Y-m-d H:i:s') >= $values['accessOpenDate']) {
                return Format::tag(__('Open'), 'success');
            } else {
                return Format::tag(__('Upcoming'), 'dull');
            }
        })
        ->formatDetails(function ($values) {
            return Format::small(Format::dateRangeReadable($values['accessOpenDate'], $values['accessCloseDate']));
        });

    $table->addColumn('accessEnrolmentDate', __('Revealed'))
        ->format(function ($values) {
            if (empty($values['accessEnrolmentDate'])) {
                return Format::tag(__('No'), 'dull');
            }

            if (!empty($values['accessEnrolmentDate']) && empty($values['backgroundImage'])) {
                return Format::tag(__('No'), 'warning', __('This event is missing a header image and is not viewable in the events list.'));
            }
            if (!empty($values['accessEnrolmentDate']) && date('Y-m-d H:i:s') >= $values['accessEnrolmentDate']) {
                return Format::tag(__('Yes'), 'success');
            } else {
                return Format::tag(__('No'), 'dull');
            }
        })
        ->formatDetails(function ($values) {
            return Format::small(Format::dateReadable($values['accessEnrolmentDate']));
        });
        
    $table->addColumn('activityCount', __('Activities'))
        ->sortable(['activityCount'])
        ->width('12%')
        ->format(function ($values) {
            $url = Url::fromModuleRoute('TawasulActivities', 'activities_manage.php')->withQueryParams(['tawasulActivityCategoryID' => $values['tawasulActivityCategoryID']]);

            return intval($values['activityCount']) > 0 
                ? Format::link($url, $values['activityCount'])
                : $values['activityCount'];
        });

    $table->addColumn('active', __('Active'))
        ->format(Format::using('yesNo', 'active'))
        ->width('10%');

    // ACTIONS
    $table->addActionColumn()
        ->addParam('tawasulActivityCategoryID')
        ->format(function ($values, $actions) {
            $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulActivities/activities_categories_edit.php');

            $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/TawasulActivities/activities_categories_delete.php')
                    ->modalWindow(650, 400);
        });

    echo $table->render($events);
}
