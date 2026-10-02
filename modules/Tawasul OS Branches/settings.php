<?php
/*
Tawasul OS Branches — module settings.
*/

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Form;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Branches/settings.php') == false
    || !tosBranchCanManage($session, $container)) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Branches'), $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Branches/branches.php');
$page->breadcrumbs->add(__('Branch Settings'));
$page->stylesheets->add('tos-branches', 'modules/Tawasul OS Branches/css/module.css');

$settingGateway = $container->get(SettingGateway::class);
$currentDefault = (int) $settingGateway->getSettingByScope(TOS_BRANCH_SCOPE, 'branchDefaultID');
$currentRoles = (string) $settingGateway->getSettingByScope(TOS_BRANCH_SCOPE, 'branchManagerRoles');
$currentFilter = ((string) $settingGateway->getSettingByScope(TOS_BRANCH_SCOPE, 'branchFilterEnabled')) === 'Y';

$branches = tosBranchSelectAll($pdo, false);
$activeCount = count($branches);
$unassigned = tosBranchUnassignedCount($pdo);

// Warn before anyone switches filtering on with untriaged data.
if ($activeCount > 1 && !$currentFilter) {
    echo '<div class="tos-notice"><strong>'.__('Before you switch filtering on').'</strong> — '.__('Modules will start hiding records that belong to other branches. Records with no branch assigned stay visible everywhere, so anything untriaged will keep appearing in every branch.').'</div>';
}

if ($activeCount <= 1) {
    echo '<div class="tos-notice"><strong>'.__('Filtering is inert for now').'</strong> — '.__('You have one active branch. Add a second branch before turning this on; until then modules show everything regardless of the setting.').'</div>';
}

$roles = $pdo->select("SELECT tawasulRoleID, name, nameShort FROM tawasulRole WHERE category='Staff' AND tawasulRoleID <> '001' ORDER BY name")->fetchAll();
$selectedRoles = array_filter(array_map('trim', explode(',', $currentRoles)), function ($v) { return $v !== ''; });

$form = Form::create('tosBranchSettings', $session->get('absoluteURL').'/modules/Tawasul OS Branches/settingsProcess.php');
$form->addHiddenValue('address', $session->get('address'));

$row = $form->addRow();
    $row->addLabel('branchFilterEnabled', __('Filter Modules By Branch'))->description(__('When Yes, branch-aware modules limit themselves to the branch you are viewing. Leave off until your data is assigned.'));
    $row->addYesNoRadio('branchFilterEnabled')->setValue($currentFilter ? 'Y' : 'N')->required();

$defaultOptions = [];
foreach ($branches as $b) {
    $defaultOptions[(int) $b['tawasulBranchID']] = $b['code'].' — '.$b['nameEn'];
}

$row = $form->addRow();
    $row->addLabel('branchDefaultID', __('Default Branch'))->description(__('Used when a user has not chosen a branch, and for students and parents who cannot switch.'));
    $row->addSelect('branchDefaultID')->fromArray($defaultOptions)->selected((string) $currentDefault)->placeholder(__('First active branch'))->required();

$row = $form->addRow();
    $row->addLabel('branchManagerRoles', __('Branch Manager Roles'))->description(__('Roles, in addition to Administrator, that may create and edit branches and change assignments.'));

// MultiSelect is an ajax-driven component with no static option source, so the
// role list is rendered as plain checkboxes inside the form.
$roleChecks = '';
foreach ($roles as $r) {
    $roleID = (string) $r['tawasulRoleID'];
    $checked = in_array($roleID, array_map('strval', $selectedRoles), true) ? ' checked' : '';
    $roleChecks .= '<label class="tos-role">'
        .'<input type="checkbox" name="branchManagerRoles[]" value="'.htmlspecialchars($roleID).'"'.$checked.'>'
        .htmlspecialchars($r['name'])
        .'</label>';
}

if ($roleChecks === '') {
    $roleChecks = '<span class="tos-assign-sub">'.__('No other staff roles exist yet.').'</span>';
}

$row->addContent('<div class="tos-roles">'.$roleChecks.'</div>');

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__('Save'));

echo $form->getOutput();