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

use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\School\SchoolYearTermGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Prefab\BulkActionForm;
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;
use TawasulOS\Services\Format;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\Activities\ActivityCategoryGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Set returnTo point for upcoming pages
    $page->breadcrumbs->add(__('Manage Activities'));

    /** @var SettingGateway        $settingGateway */
    $settingGateway = $container->get(SettingGateway::class);
    /** @var SchoolYearTermGateway $schoolYearTermGateway */
    $schoolYearTermGateway = $container->get(SchoolYearTermGateway::class);

    $search = $_GET['search'] ?? '';
    $tawasulSchoolYearTermID = $_GET['tawasulSchoolYearTermID'] ?? '';
    $tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';
    $tawasulActivityCategoryID = $_GET['tawasulActivityCategoryID'] ?? '';
    $dateType = $settingGateway->getSettingByScope('Activities', 'dateType');
    $enrolmentType = $settingGateway->getSettingByScope('Activities', 'enrolmentType');
    $schoolTerms = $schoolYearTermGateway->selectTermsBySchoolYear((int) $session->get('tawasulSchoolYearID'))->fetchKeyPair();
    $yearGroups = getYearGroups($connection2);

    $activityGateway = $container->get(ActivityGateway::class);

    // CRITERIA
    $criteria = $activityGateway->newQueryCriteria(true)
        ->searchBy($activityGateway->getSearchableColumns(), $search)
        ->filterBy('term', $tawasulSchoolYearTermID)
        ->filterBy('yearGroup', $tawasulYearGroupID)
        ->filterBy('category', $tawasulActivityCategoryID)
        ->sortBy($dateType != 'Date' ? 'tawasulSchoolYearTermIDList' : 'programStart', $dateType != 'Date' ? 'ASC' : 'DESC')
        ->sortBy('name');

    $criteria->fromPOST();

    echo '<h2>';
    echo __('Search & Filter');
    echo '</h2>';

    $paymentOn = $settingGateway->getSettingByScope('Activities', 'payment') != 'None' and $settingGateway->getSettingByScope('Activities', 'payment') != 'Single';

    $form = Form::create('searchForm', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', "/modules/".$session->get('module')."/activities_manage.php");

    $row = $form->addRow();
        $row->addLabel('search', __('Search'))->description(__('Activity name.'));
        $row->addTextField('search')->setValue($criteria->getSearchText());

    if ($dateType != 'Date') {
        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
        $sql = "SELECT tawasulSchoolYearTermID as value, name FROM tawasulSchoolYearTerm WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY sequenceNumber";
        $row = $form->addRow();
            $row->addLabel('tawasulSchoolYearTermID', __('Term'));
            $row->addSelect('tawasulSchoolYearTermID')->fromQuery($pdo, $sql, $data)->selected($tawasulSchoolYearTermID)->placeholder();
    }

    $row = $form->addRow();
        $row->addLabel('tawasulYearGroupID', __('Year Group'));
        $row->addSelectYearGroup('tawasulYearGroupID')->placeholder()->selected($tawasulYearGroupID);

    $categories = $container->get(ActivityCategoryGateway::class)->selectCategoriesBySchoolYear($session->get('tawasulSchoolYearID'))->fetchKeyPair();
    $row = $form->addRow();
        $row->addLabel('tawasulActivityCategoryID', __('Category'));
        $row->addSelect('tawasulActivityCategoryID')->fromArray($categories)->placeholder()->selected($tawasulActivityCategoryID);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Search'));

    echo $form->getOutput();

    echo '<h2>';
    echo __('Activities');
    echo '</h2>';

    $activities = $activityGateway->queryActivitiesBySchoolYear($criteria, $session->get('tawasulSchoolYearID'));

    // FORM
    $form = BulkActionForm::create('bulkAction', $session->get('absoluteURL').'/modules/'.$session->get('module').'/activities_manageProcessBulk.php');
    $form->addHiddenValue('search', $search);

    $bulkActions = array(
        'Duplicate' => __('Duplicate'),
        'DuplicateParticipants' => __('Duplicate With Participants'),
        'Delete' => __('Delete'),
    );
    $sql = "SELECT tawasulSchoolYearID as value, tawasulSchoolYear.name FROM tawasulSchoolYear WHERE (status='Upcoming' OR status='Current') ORDER BY sequenceNumber LIMIT 0, 2";

    $col = $form->createBulkActionColumn($bulkActions);
        $col->addSelect('tawasulSchoolYearIDCopyTo')
            ->fromQuery($pdo, $sql)
            ->setClass('shortWidth schoolYear');
        $col->addSubmit(__('Go'));

    $form->toggleVisibilityByClass('schoolYear')->onSelect('action')->when(array('Duplicate', 'DuplicateParticipants'));

    // DATA TABLE
    $table = $form->addRow()->addDataTable('activities', $criteria)->withData($activities);

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/TawasulActivities/activities_manage_add.php')
        ->addParam('search', $search)
        ->addParam('tawasulSchoolYearTermID', $tawasulSchoolYearTermID)
        ->displayLabel();

    $table->modifyRows(function ($activity, $row) {
        if ($activity['active'] == 'N') return $row->addClass('error');
        if ($activity['registration'] == 'N') return $row->addClass('warning');
        return $row;
    });

    $table->addMetaData('filterOptions', [
        'active:Y'          => __('Active').': '.__('Yes'),
        'active:N'          => __('Active').': '.__('No'),
        'registration:Y'    => __('Registration').': '.__('Yes'),
        'registration:N'    => __('Registration').': '.__('No'),
        'enrolment:less'    => __('Enrolment').': &lt; '.__('Full'),
        'enrolment:full'    => __('Enrolment').': '.__('Full'),
        'enrolment:greater' => __('Enrolment').': &gt; '.__('Full'),
    ]);

    if ($enrolmentType == 'Competitive') {
        $table->addMetaData('filterOptions', ['status:waiting' => __('Waiting List')]);
    } else {
        $table->addMetaData('filterOptions', ['status:pending' => __('Pending')]);
    }

    $table->addMetaData('bulkActions', $col);

    // COLUMNS
    $table->addColumn('name', __('Activity'))
        ->format(function($activity) {
            return $activity['name'].'<br/><span class="text-xs italic">'.$activity['type'].'</span>';
        });

    $table->addColumn('days', __('Days'))
        ->notSortable()
        ->format(function($activity) use ($activityGateway) {
            return implode(', ', array_map('__', $activityGateway->selectWeekdayNamesByActivity($activity['tawasulActivityID'])->fetchAll(\PDO::FETCH_COLUMN)));
        });

    $table->addColumn('yearGroups', __('Years'))
        ->format(function($activity) use ($yearGroups) {
            if ($activity['active'] == 'N') return Format::tag(__('Inactive'), 'error');
            if ($activity['registration'] == 'N') return Format::tag(__('Registration').': '.__('Off'), 'warning whitespace-nowrap');

            return ($activity['yearGroupCount'] >= count($yearGroups)/2)? '<i>'.__('All').'</i>' : $activity['yearGroups'];
        });

    $table->addColumn('date', $dateType != 'Date'? __('Term') : __('Dates'))
        ->sortable($dateType != 'Date' ? ['tawasulSchoolYearTermIDList'] : ['programStart', 'programEnd'])
        ->format(function($activity) use ($dateType, $schoolTerms) {
            if (empty($schoolTerms)) return '';
            if ($dateType != 'Date') {
                $termList = array_intersect_key($schoolTerms, array_flip(explode(',', $activity['tawasulSchoolYearTermIDList'] ?? '')));
                if (!empty($termList)) {
                    return implode('<br/>', $termList);
                }
            } else {
                return Format::dateRangeReadable($activity['programStart'], $activity['programEnd']);
            }
        });

    if ($paymentOn) {
        $table->addColumn('payment', __('Cost'))
            ->description($session->get('currency'))
            ->format(function($activity) {
                $payment = ($activity['payment'] > 0)
                    ? Format::currency($activity['payment']) . '<br/>' . __($activity['paymentType'])
                    : '<i>'.__('None').'</i>';
                if ($activity['paymentFirmness'] != 'Finalised') $payment .= '<br/><i>'.__($activity['paymentFirmness']).'</i>';

                return $payment;
            });
    }

    $table->addColumn('provider', __('Provider'))
        ->format(function($activity) use ($session){
            return ($activity['provider'] == 'School')? $session->get('organisationNameShort') : __('External');
        });

    $table->addColumn('enrolment', __('Enrolment'))
        ->format(function($activity) {
            return $activity['enrolment'] .' / '. $activity['maxParticipants']
                . (!empty($activity['waiting'])? '<br><small><i>' .$activity['waiting'].' '.__('Waiting') .'</i></small>' : '')
                . (!empty($activity['pending'])? '<br><small><i>' .$activity['pending'].' '.__('Pending') .'</i></small>' : '');
        });

    // ACTIONS
    $table->addActionColumn()
        ->addParam('tawasulActivityID')
        ->addParam('search', $criteria->getSearchText(true))
        ->addParam('tawasulSchoolYearTermID', $tawasulSchoolYearTermID)
        ->format(function ($activity, $actions) {
            $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulActivities/activities_manage_edit.php');

            $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/TawasulActivities/activities_manage_delete.php');

            $actions->addAction('enrolment', __('Enrolment'))
                    ->setURL('/modules/TawasulActivities/activities_manage_enrolment.php')
                    ->setIcon('attendance');
        });

    $table->addCheckboxColumn('tawasulActivityID');

    echo $form->getOutput();
}
