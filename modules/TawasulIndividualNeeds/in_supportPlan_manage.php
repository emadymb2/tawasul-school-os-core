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
use TawasulOS\Domain\DataSet;
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\IndividualNeeds\StudentSupportPlanGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/in_supportPlan_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get action with highest precedence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    }

    $page->breadcrumbs->add(__('Student Support Plans'));

    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';

    // Student selector form
    $form = Form::create('searchForm', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder fullWidth');
    $form->addHiddenValue('q', '/modules/TawasulIndividualNeeds/in_supportPlan_manage.php');

    $row = $form->addRow();
        $row->addLabel('tawasulPersonID', __('Student'));
        $row->addSelectStudent('tawasulPersonID', $session->get('tawasulSchoolYearID'))
            ->placeholder()
            ->selected($tawasulPersonID);

    $row = $form->addRow();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    if (!empty($tawasulPersonID)) {
        $student = $container->get(UserGateway::class)->getByID($tawasulPersonID);

        if (empty($student)) {
            $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            return;
        }

        // Query plans
        $studentSupportPlanGateway = $container->get(StudentSupportPlanGateway::class);
        $criteria = $studentSupportPlanGateway->newQueryCriteria()
            ->fromPOST();

        if ($highestAction == 'Student Support Plans_view') {
            $criteria->filterBy('viewableStaff', 'Y');
            $criteria->filterBy('active', 'Y');
        }

        $plans = $studentSupportPlanGateway->queryPlansByStudent($criteria, $tawasulPersonID);

        $plansBySchoolYear = array_reduce($plans->toArray(), function ($group, $item) {
            $group[$item['schoolYear']][] = $item;
            return $group;
        }, []);

        if (empty($plansBySchoolYear)) {
            $plansBySchoolYear = [__('Support Plans') => []];
        }

        $firstTable = true;
        foreach ($plansBySchoolYear as $schoolYear => $schoolYearPlans) {
            $table = DataTable::create('supportPlans_'.preg_replace('/[^A-Za-z0-9]/', '', $schoolYear));
            $table->setTitle($schoolYear);

            if ($firstTable && $highestAction == 'Student Support Plans_manage') {
                $table->addHeaderAction('add', __('Add Support Plan'))
                    ->setURL('/modules/TawasulIndividualNeeds/in_supportPlan_add.php')
                    ->addParam('tawasulPersonID', $tawasulPersonID)
                    ->displayLabel();
            }
            $firstTable = false;

            $table->addColumn('name', __('Name'));
            $table->addColumn('description', __('Description'));
            $table->addColumn('type', __('Type'))
                ->format(function ($plan) {
                    return __($plan['type']);
                });
            $table->addColumn('active', __('Active'))
                ->format(function ($plan) {
                    return $plan['active'] == 'Y' ? __('Yes') : __('No');
                });

            $table->addActionColumn()
                ->addParam('tawasulPersonID', $tawasulPersonID)
                ->addParam('tawasulStudentSupportPlanID', '')
                ->format(function ($plan, $actions) use ($highestAction) {
                    $actions->addParam('tawasulStudentSupportPlanID', $plan['tawasulStudentSupportPlanID']);

                    if ($plan['type'] == 'File') {
                        $actions->addAction('view', __('View'))
                            ->isModal(1500, 1400)
                            ->setURL('/modules/TawasulIndividualNeeds/in_supportPlan_view.php');
                        $actions->addAction('download', __('Download'))
                            ->directLink()
                            ->addParam('action', 'download')
                            ->setURL('/modules/TawasulIndividualNeeds/in_supportPlan_download.php');
                    } else {
                        $actions->addAction('view', __('View'))
                            ->directLink()
                            ->setTarget('_blank')
                            ->setURL('/modules/TawasulIndividualNeeds/in_supportPlan_download.php');
                    }

                    if ($highestAction == 'Student Support Plans_manage') {
                        $actions->addAction('edit', __('Edit'))
                            ->setURL('/modules/TawasulIndividualNeeds/in_supportPlan_edit.php');
                        $actions->addAction('delete', __('Delete'))
                            ->setURL('/modules/TawasulIndividualNeeds/in_supportPlan_delete.php');
                    }
                });

            echo $table->render(new DataSet($schoolYearPlans));
        }
    }
}
