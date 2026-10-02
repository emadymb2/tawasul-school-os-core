<?php
/*
Tawasul OS Branches — assign staff, students, classes, departments and units
to branches.

Bulk apply only: choose a branch and a set of rows, and every ticked row is
moved in one submission. Ticking no rows and pressing Apply is a no-op, which
keeps the form safe to re-submit.
*/

use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Branches/assignments.php') == false
    || !tosBranchCanManage($session, $container)) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Branches'), $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Branches/branches.php');
$page->breadcrumbs->add(__('Assign to Branch'));
$page->stylesheets->add('tos-branches', 'modules/Tawasul OS Branches/css/module.css');

$entities = tosBranchEntities();
$entityKey = tosBranchValidEntity($_GET['entity'] ?? 'staff');
$branchFilter = isset($_GET['branchID']) && (int) $_GET['branchID'] > 0 ? (int) $_GET['branchID'] : null;
$search = trim((string) ($_GET['search'] ?? ''));

$branches = tosBranchSelectAll($pdo, false);
$entity = $entities[$entityKey];
$rows = tosBranchEntityRows($pdo, $entityKey, $branchFilter, $search);

$branchOptions = [];
foreach ($branches as $b) {
    $branchOptions[(int) $b['tawasulBranchID']] = $b['code'].' — '.$b['nameEn'];
}

$entityLinks = '';
foreach ($entities as $key => $meta) {
    $current = ($key === $entityKey);
    $entityLinks .= '<a class="'.($current ? 'tos-secondary' : '').'" style="'.($current ? 'font-weight:800' : '').'" href="?entity='.urlencode($key).'">'
        .htmlspecialchars(__($meta['title'])).'</a>';
}
echo '<div class="tos-actions" style="margin-bottom:12px">'.$entityLinks.'</div>';

$form = Form::create('tosBranchAssign', $session->get('absoluteURL').'/modules/Tawasul OS Branches/assignmentsProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('entity', $entityKey);

$head = $form->addRow();
    $head->addLabel('targetBranch', __('Move ticked rows to'));
    $head->addSelect('targetBranch')->fromArray($branchOptions)->required()->placeholder(__('Choose a branch'));

$searchForm = Form::createSearch('tosBranchSearch', $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Branches/assignments.php');
$searchForm->hiddenValues(['entity' => $entityKey]);
$searchRow = $searchForm->addRow();
    $searchRow->addLabel('search', __('Search'));
    $searchRow->addTextField('search')->setValue($search)->maxLength(100);

if ($branchFilter !== null) {
    $searchForm->hiddenValues(['branchID' => $branchFilter]);
}

$searchRow = $searchForm->addRow();
    $searchRow->addFooter();
    $searchRow->addSubmit(__('Search'));

echo '<div class="tos-assign-head">'.$searchForm->getOutput().'</div>';

echo '<div class="tos-assign">';
echo $form->getOutput();

echo '<div class="tos-assign-list">';

if (empty($rows)) {
    echo '<div class="tos-assign-row"><span>'.__('No records found.').'</span></div>';
}

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $currentBranch = $row['tawasulBranchID'] === null ? null : (int) $row['tawasulBranchID'];

    if (in_array($entityKey, ['staff', 'students'], true)) {
        $label = Format::name($row['title'], $row['preferredName'], $row['surname'], $entity['roleCategory']);
        $sub = $entityKey === 'staff' ? $row['jobTitle'] : ($row['studentID'] ?? '');
    } else {
        $label = $row['name'];
        $sub = $row['nameShort'] ?? '';
    }

    echo '<div class="tos-assign-row">';
    echo '<label><input type="checkbox" name="ids[]" value="'.$id.'">';
    echo '<span>'.htmlspecialchars($label);
    if ($sub !== '' && $sub !== null) {
        echo '<span class="tos-assign-sub">'.htmlspecialchars($sub).'</span>';
    }
    echo '</span></label>';

    echo '<select disabled aria-label="'.htmlspecialchars(__('Current branch')).'">';
    if ($currentBranch === null) {
        echo '<option selected>'.__('Not assigned').'</option>';
    } elseif (isset($branchOptions[$currentBranch])) {
        echo '<option selected>'.htmlspecialchars($branchOptions[$currentBranch]).'</option>';
    } else {
        echo '<option selected>#'.$currentBranch.'</option>';
    }
    echo '</select>';
    echo '</div>';
}

echo '</div>';
echo '</div>';