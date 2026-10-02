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
use TawasulOS\Domain\DataSet;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\IndividualNeeds\INGateway;
use TawasulOS\Domain\IndividualNeeds\StudentSupportPlanGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/iep_view_myChildren.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $entryCount = 0;
    $page->breadcrumbs->add(__('View Individual Education Plans'));

    echo '<p>';
    echo __('This section allows you to view individual education plans, where they exist, for children within your family.').'<br/>';
    echo '</p>';

    // Test data access field for permission
    $children = $container->get(StudentGateway::class)->selectActiveStudentsByFamilyAdult($session->get('tawasulSchoolYearID'), $session->get('tawasulPersonID'))->fetchGroupedUnique();

    if (empty($children)) {
        echo $page->getBlankSlate();
    } else {
        // Get child list
		$options = [];
        foreach($children as $child) {
            $options[$child['tawasulPersonID']] = Format::name('', $child['preferredName'], $child['surname'], 'Student', true);
        }

        $tawasulPersonID = (isset($_GET['tawasulPersonID'])) ? $_GET['tawasulPersonID'] : null;

        if (count($options) == 0) {
            echo $page->getBlankSlate();
        } elseif (count($options) == 1) {
            $tawasulPersonID = key($options);
        } else {
            echo '<h2>';
            echo 'Choose Student';
            echo '</h2>';

            $form = Form::create('searchForm', $session->get('absoluteURL').'/index.php', 'get');
            $form->setClass('noIntBorder w-full');

            $form->addHiddenValue('q', '/modules/'.$session->get('module').'/iep_view_myChildren.php');
            $form->addHiddenValue('address', $session->get('address'));

            $row = $form->addRow();
                $row->addLabel('tawasulPersonID', __('Student'));
                $row->addSelect('tawasulPersonID')->fromArray($options)->selected($tawasulPersonID)->placeholder();

            $row = $form->addRow();
                $row->addSearchSubmit($session);

            echo $form->getOutput();
        }

        if ($tawasulPersonID != '' && count($options) > 0) {
            
            if (empty($children[$tawasulPersonID])) {
                $page->addError(__('You do not have access to this action.'));
                return;
            }

            $result = $container->get(INGateway::class)->selectBy(['tawasulPersonID' => $tawasulPersonID]);

            if ($result->rowCount() != 1) {
                echo '<h3>';
                echo __('View');
                echo '</h3>';

                echo $page->getBlankSlate();
            } else {
                echo '<h3>';
                echo __('View');
                echo '</h3>';

                $row = $result->fetch(); ?>
                <table class='smallIntBorder w-full' cellspacing='0'>
                    <tr>
                        <td colspan=2 style='padding-top: 25px'>
                            <span style='font-weight: bold; font-size: 135%'><?php echo __('Targets') ?></span><br/>
                            <?php
                            echo '<p>'.$row['targets'].'</p>'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan=2>
                            <span style='font-weight: bold; font-size: 135%'><?php echo __('Teaching Strategies') ?></span><br/>
                            <?php
                            echo '<p>'.$row['strategies'].'</p>'; ?>
                        </td>
                    </tr>
                </table>
                <?php

            }

            // Student Support Plans section
            $planGateway = $container->get(StudentSupportPlanGateway::class);
            $planCriteria = $planGateway->newQueryCriteria()
                ->fromPOST();
            $planCriteria->filterBy('viewableParents', 'Y');
            $planCriteria->filterBy('active', 'Y');

            $plans = $planGateway->queryPlansByStudent($planCriteria, $tawasulPersonID);

            $plansBySchoolYear = array_reduce($plans->toArray(), function ($group, $item) {
                $group[$item['schoolYear']][] = $item;
                return $group;
            }, []);

            if (!empty($plansBySchoolYear)) {
                echo '<h3>'.__('Student Support Plans').'</h3>';

                foreach ($plansBySchoolYear as $schoolYear => $schoolYearPlans) {
                    $table = DataTable::create('supportPlans_'.preg_replace('/[^A-Za-z0-9]/', '', $schoolYear));
                    $table->setTitle($schoolYear);

                    $table->addColumn('name', __('Name'));
                    $table->addColumn('description', __('Description'));

                    $table->addActionColumn()
                        ->addParam('tawasulPersonID', $tawasulPersonID)
                        ->addParam('tawasulStudentSupportPlanID', '')
                        ->format(function ($plan, $actions) {
                            $actions->addParam('tawasulStudentSupportPlanID', $plan['tawasulStudentSupportPlanID']);

                            if ($plan['type'] == 'File') {
                                $actions->addAction('view', __('View'))
                                    ->isModal(1150, 1100)
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
                        });

                    echo $table->render(new DataSet($schoolYearPlans));
                }
            }
        }
    }
}
?>