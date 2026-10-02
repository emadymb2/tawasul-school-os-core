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

namespace Tos\Module\TawasulActivities\Tables;

use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Contracts\Services\Session;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Http\Url;
use TawasulOS\Support\Facades\Access;

/**
 * ActivitiesViewParent
 *
 * @version v30
 * @since   v30
 */
class ActivitiesViewParent extends DataTable
{
    protected $activityGateway;

    public function __construct(ActivityGateway $activityGateway)
    {
        $this->activityGateway = $activityGateway;
    }

    public function createTable($tawasulSchoolYearID, $tawasulPersonID, $child)
    {
        $canSignUp = Access::allows('Activities', 'explore_activity_signUp', 'Activities_registerByParent');
        $canExplore = Access::allows('Activities', 'explore');

        $criteria = $this->activityGateway->newQueryCriteria()
            ->sortBy(['sequenceNumber', 'accessOpenDate'])
            ->fromPOST();

        $activities = $this->activityGateway->queryActivitiesByParticipant($criteria, $tawasulSchoolYearID, $tawasulPersonID);

        $table = DataTable::create('activities')->withData($activities);
        $table->setTitle(Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true));

        if ($canExplore) {
            $table->addHeaderAction('explore', __('Explore Activities'))
                ->setURL(Url::fromModuleRoute('TawasulActivities', 'explore.php')->withQueryParam('tawasulPersonID', $tawasulPersonID))
                ->setIcon('squares');
        }

        $table->addColumn('category', __('Category'))
            ->sortable(['category'])
            ->context('primary')
            ->width('20%')
            ->format(function ($activity) use ($canExplore) {
                $url = Url::fromModuleRoute('TawasulActivities', 'explore_category.php')->withQueryParams(['tawasulActivityCategoryID' => $activity['tawasulActivityCategoryID'], 'sidebar' => 'false']);
                return $canExplore 
                    ? Format::link($url, $activity['category'])
                    : $activity['category'];
            });

            $table->addColumn('choices', __('Activity'))
                ->context('primary')
                ->width('40%')
                ->format(function ($activity) {
                    if (empty($activity['choices'])) {
                        return $activity['name'].'<br/>'.Format::small($activity['type']);
                    }
                    
                    $choices = explode(',', $activity['choices']);
                    return Format::small(__('Activity Choices')).':<br/>'.Format::list($choices, 'ol', 'ml-2 my-0 text-xs');
                });

            $table->addColumn('status', __('Status'))
                ->width('12%')
                ->format(function ($activity) {
                    if (empty($activity['status'])) return Format::small(__('N/A'));

                    return $activity['status'] == 'Pending' 
                        ? (!empty($activity['choices']) ? Format::tag($activity['status'], 'message')  : '')
                        : $activity['status'];
                });
        
        $table->addActionColumn()
            ->addParam('tawasulActivityCategoryID')
            ->addParam('tawasulActivityID')
            ->format(function ($activity, $actions) use ($child, $canExplore, $canSignUp) {
                if ($canExplore && !empty($activity['tawasulActivityID'])) {
                    $actions->addAction('view', __('View Details'))
                        ->addParam('sidebar', 'false')
                        ->setURL('/modules/TawasulActivities/explore_activity.php');
                }

                if (empty($activity['tawasulActivityID'])) {
                    // Check that sign up is open based on the date
                    $signUpIsOpen = false;

                    $categoryYearGroups = explode(',', $activity['tawasulYearGroupIDParentRegister'] ?? ''); 
                    if (!in_array($child['tawasulYearGroupID'], $categoryYearGroups)) {
                        return;
                    }
    
                    if (!empty($activity['accessOpenDate']) && !empty($activity['accessCloseDate'])) {
                        $accessOpenDate = \DateTime::createFromFormat('Y-m-d H:i:s', $activity['accessOpenDate'])->format('U');
                        $accessCloseDate = \DateTime::createFromFormat('Y-m-d H:i:s', $activity['accessCloseDate'])->format('U');
                        $now = (new \DateTime('now'))->format('U');
    
                        $signUpIsOpen = $accessOpenDate <= $now && $accessCloseDate >= $now;
                    }
    
                    if ($signUpIsOpen && $canSignUp) {
                        $actions->addAction('add', __('Sign Up'))
                                ->setURL('/modules/TawasulActivities/explore_activity_signUp.php')
                                ->addParam('tawasulActivityCategoryID', $activity['tawasulActivityCategoryID'])
                                ->addParam('tawasulPersonID', $child['tawasulPersonID'])
                                ->setIcon('attendance')
                                ->modalWindow(750, 440);
                    }
    
                    return;
                }
            });

        return $table;
    }
}
