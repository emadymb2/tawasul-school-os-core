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

use TawasulOS\Forms\Form;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Activities\ActivityCategoryGateway;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Activities\ActivityChoiceGateway;
use TawasulOS\Forms\DatabaseFormFactory;

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/choices_manage_addEdit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $params = [
        'mode'                => $_REQUEST['mode'] ?? '',
        'tawasulActivityCategoryID' => $_REQUEST['tawasulActivityCategoryID'] ?? '',
        'tawasulPersonID'      => $_REQUEST['tawasulPersonID'] ?? '',
    ];

    $page->breadcrumbs
        ->add(__('Manage Choices'), 'choices_manage.php')
        ->add($params['mode'] == 'add' ? __('Add Choices') : __('Edit Choices'));
     
    $page->return->addReturns([
        'error4' => __('Sign up is currently not available for this Deep Learning event.'),
        'error5' => __('There was an error verifying your Deep Learning choices. Please try again.'),
    ]);

    if ($params['mode'] == 'add' && isset($_GET['editID']) && isset($_GET['editID2'])) {
        $page->return->setEditLink($session->get('absoluteURL').'/index.php?q=/modules/TawasulActivities/choices_manage_addEdit.php&mode=edit&tawasulActivityCategoryID='.$_GET['editID'].'&tawasulPersonID='.$_GET['editID2']);
    }

    $categoryGateway = $container->get(ActivityCategoryGateway::class);
    $activityGateway = $container->get(ActivityGateway::class);
    $choiceGateway = $container->get(ActivityChoiceGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    // Get events and experiences
    $categories = $categoryGateway->selectCategoriesBySchoolYear($session->get('tawasulSchoolYearID'));
    $activities = $activityGateway->selectActivitiesByCategoryAndSchoolYear($session->get('tawasulSchoolYearID'))->fetchAll();

    $activityList = array_combine(array_column($activities, 'tawasulActivityID'), array_column($activities, 'name'));
    $activityChainedTo = array_combine(array_column($activities, 'tawasulActivityID'), array_column($activities, 'tawasulActivityCategoryID'));
    $activityCategories = array_count_values(array_filter($activityChainedTo));

    $category = $categoryGateway->getByID($params['tawasulActivityCategoryID']);
    $signUpChoices = $category['signUpChoices'] ?? 3;
    $choiceList = [1 => __('First Choice'), 2 => __('Second Choice'), 3 => __('Third Choice'), 4 => __('Fourth Choice'), 5 => __('Fifth Choice')];

    if ($params['mode'] == 'edit') {    
        // Lower the choice limit if there are less options
        $categoryCount = $activityCategories[$params['tawasulActivityCategoryID']] ?? 0;
        if ($categoryCount < $signUpChoices) {
            $signUpChoices = $categoryCount;
        }

        $choices = $choiceGateway->selectChoicesByPerson($params['tawasulActivityCategoryID'], $params['tawasulPersonID'])->fetchGroupedUnique();
        $choice = [];
        for ($i = 1; $i <= $signUpChoices; $i++) {
            $choice[$i] = $choices[$i]['tawasulActivityID'] ?? $choices[$i] ?? '';
        }
    }

    // FORM
    $form = Form::create('choices', $session->get('absoluteURL').'/modules/'.$session->get('module').'/choices_manage_addEditProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('mode', $params['mode']);
    
    $row = $form->addRow();
        $row->addLabel('tawasulActivityCategoryID', __('Category'));
        $row->addSelect('tawasulActivityCategoryID')
            ->fromResults($categories)
            ->required()
            ->placeholder()
            ->selected($params['tawasulActivityCategoryID'])
            ->readOnly($params['mode'] == 'edit');

    $row = $form->addRow();
        $row->addLabel('tawasulPersonID', __('Person'));
        $row->addSelectStudent('tawasulPersonID', $session->get('tawasulSchoolYearID'))
            ->required()
            ->placeholder()
            ->selected($params['mode'] == 'edit' ? $params['tawasulPersonID'] : '')
            ->readOnly($params['mode'] == 'edit');

    for ($i = 1; $i <= $signUpChoices; $i++) {
        $row = $form->addRow();
        $row->addLabel("choices[{$i}]", $choiceList[$i] ?? $i);
        $row->addSelect("choices[{$i}]")
            ->fromArray($activityList)
            ->setID("choices{$i}")
            ->addClass('choicesChoice')
            ->chainedTo('tawasulActivityCategoryID', $activityChainedTo)
            ->required()
            ->placeholder()
            ->selected($choice[$i] ?? '');
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
?>

<script>
$(document).on('change input', '.choicesChoice', function () {
    var currentChoice = this;

    $('.choicesChoice').not(this).each(function() {
        if ($(currentChoice).val() == $(this).val()) {
            $(this).val($(this).find("option:first-child").val());
        }
    });
});
</script>
