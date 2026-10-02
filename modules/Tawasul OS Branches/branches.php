<?php
/*
Tawasul OS Branches — branch register.

Visible to everyone: the register lists branches and lets a user switch which
one they are working in. Editing is gated on tosBranchCanManage().
*/

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Branches/branches.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Branches'));
$page->stylesheets->add('tos-branches', 'modules/Tawasul OS Branches/css/module.css');

$canManage = tosBranchCanManage($session, $container);
$context = tosBranchContext($session, $container, $pdo);
$branches = tosBranchSelectAll($pdo);
$counts = tosBranchCounts($pdo);
$unassigned = tosBranchUnassignedCount($pdo);

$baseURL = $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Branches/';
$e = function ($v) { return htmlspecialchars((string) $v); };

// Branches offered in the picker. Inactive ones stay out of it.
$options = [];
foreach ($branches as $b) {
    if ($b['active'] === 'Y') {
        $label = $b['code'].' — '.$b['nameEn'];
        if ($b['nameAr'] !== $b['nameEn']) {
            $label .= ' / '.$b['nameAr'];
        }
        $options[(int) $b['tawasulBranchID']] = $label;
    }
}

echo '<div class="tos-branch-bar">';

if (count($options) > 1) {
    // More than one campus, so the choice is worth offering.
    echo '<form method="post" action="'.$e($baseURL.'branch_activeProcess.php').'" id="tosBranchSwitchForm">';
    echo '<input type="hidden" name="address" value="'.$e($session->get('address')).'">';
    echo '<label for="tawasulBranchActive">'.__('Branch').':</label>';
    echo '<select name="tawasulBranchActive" id="tawasulBranchActive">';
    foreach ($options as $id => $label) {
        echo '<option value="'.$e($id).'"'.(((int) $id === (int) $context['activeID']) ? ' selected' : '').'>'.$e($label).'</option>';
    }
    echo '</select>';
    echo '<button type="submit">'.__('Switch').'</button>';
    echo '</form>';
} else {
    // A single branch is not a choice, so no control is rendered at all.
    echo '<span class="tos-branch-current">'.(count($options) === 1 ? $e(reset($options)) : $e(__('No active branch'))).'</span>';
}

echo '</div>';

// Status of the branch filter itself, so an administrator can see at a glance
// whether other modules are currently restricting themselves to one campus.
echo '<div class="tos-notice">';
if ($context['single']) {
    echo '<strong>'.__('Single branch').'</strong> — '.__('You have one active branch, so modules show all records exactly as before. Add a second branch to switch on filtering.');
} elseif ($context['active']) {
    echo '<strong>'.__('Branch filtering is on').'</strong> — '.sprintf(__('Modules are showing branch %1$s.'), $e($options[$context['activeID']] ?? ('#'.$context['activeID'])));
} else {
    echo '<strong>'.__('Branch filtering is off').'</strong> — '.__('Modules show every branch. Turn it on in Branch Settings when you are ready.');
}
echo '</div>';

if ($unassigned > 0) {
    echo '<div class="tos-notice"><strong>'.__('Not yet assigned').'</strong> — '.sprintf(__('%1$s records (staff, students, classes, departments, units) are not assigned to any branch. They stay visible everywhere until you assign them.'), number_format($unassigned)).'</div>';
}

echo '<table class="tos-table">';
echo '<caption>'.__('Branch Register').'</caption>';
echo '<thead><tr>';
echo '<th>'.__('Code').'</th>';
echo '<th>'.__('Name').'</th>';
echo '<th>'.__('Status').'</th>';
echo '<th class="tos-num">'.__('Staff').'</th>';
echo '<th class="tos-num">'.__('Students').'</th>';
echo '<th class="tos-num">'.__('Classes').'</th>';
if ($canManage) {
    echo '<th>'.__('Actions').'</th>';
}
echo '</tr></thead><tbody>';

foreach ($branches as $b) {
    $id = (int) $b['tawasulBranchID'];
    $row = $counts[$id] ?? ['staffCount' => 0, 'studentCount' => 0, 'classCount' => 0];

    echo '<tr'.($b['active'] === 'Y' ? '' : ' class="tos-inactive"').'>';
    echo '<td><span class="tos-code">'.$e($b['code']).'</span></td>';
    echo '<td><span class="tos-bilingual"><span dir="rtl" lang="ar">'.$e($b['nameAr']).'</span><span>'.$e($b['nameEn']).'</span></span></td>';
    echo '<td><span class="tos-pill '.($b['active'] === 'Y' ? 'tos-pill-active' : 'tos-pill-inactive').'">'.($b['active'] === 'Y' ? __('Active') : __('Inactive')).'</span></td>';
    echo '<td class="tos-num">'.number_format((int) $row['staffCount']).'</td>';
    echo '<td class="tos-num">'.number_format((int) $row['studentCount']).'</td>';
    echo '<td class="tos-num">'.number_format((int) $row['classCount']).'</td>';

    if ($canManage) {
        echo '<td><div class="tos-actions"><a class="tos-secondary" href="'.$e($baseURL.'branch_manage.php').'&amp;tawasulBranchID='.$id.'">'.__('Edit').'</a></div></td>';
    }
    echo '</tr>';
}

echo '</tbody></table>';

if ($canManage) {
    echo '<div class="tos-actions" style="margin-top:16px">';
    echo '<a href="'.$e($baseURL.'branch_manage.php').'">'.__('Add Branch').'</a>';
    echo '<a class="tos-secondary" href="'.$e($baseURL.'assignments.php').'">'.__('Assign to Branch').'</a>';
    echo '<a class="tos-secondary" href="'.$e($baseURL.'settings.php').'">'.__('Branch Settings').'</a>';
    echo '</div>';
}