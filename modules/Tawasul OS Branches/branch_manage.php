<?php
/*
Tawasul OS Branches — add or edit a branch.
*/

use TawasulOS\Forms\Form;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Branches/branch_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Editing also requires the delegated-manager check, not just the action
// permission, so a role granted "Add Branch" by default still cannot edit
// unless Branch Settings names it as a manager role.
if (!tosBranchCanManage($session, $container)) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$branchID = isset($_GET['tawasulBranchID']) ? (int) $_GET['tawasulBranchID'] : 0;
$branch = null;

if ($branchID > 0) {
    $branch = $pdo->select('SELECT * FROM tawasulBranch WHERE tawasulBranchID=:b', ['b' => $branchID])->fetch();

    if (empty($branch)) {
        $page->addError(__('Branch not found.'));
        return;
    }
}

$page->breadcrumbs->add(__('Branches'), $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Branches/branches.php');
$page->breadcrumbs->add(empty($branch) ? __('Add Branch') : __('Edit Branch'));
$page->stylesheets->add('tos-branches', 'modules/Tawasul OS Branches/css/module.css');

// Staff eligible to head a branch: anyone who is staff.
$staff = $pdo->select("SELECT p.tawasulPersonID, p.title, p.preferredName, p.surname, s.jobTitle
                       FROM tawasulPerson p JOIN tawasulStaff s ON (s.tawasulPersonID = p.tawasulPersonID)
                       WHERE p.status='Full' ORDER BY p.surname, p.preferredName")->fetchAll();

$form = Form::create('tosBranchManage', $session->get('absoluteURL').'/modules/Tawasul OS Branches/branch_manageProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('tawasulBranchID', $branchID);

$row = $form->addRow();
    $row->addLabel('code', __('Code'))->description(__('Short unique reference, letters, numbers and dashes. Used in reports and imports.'));
    $row->addTextField('code')->setValue($branch['code'] ?? '')->required()->maxLength(20);

$row = $form->addRow();
    $row->addLabel('nameEn', __('Name (English)'));
    $row->addTextField('nameEn')->setValue($branch['nameEn'] ?? '')->required()->maxLength(150);

$row = $form->addRow();
    $row->addLabel('nameAr', __('Name (Arabic)'))->description(__('Shown on Arabic screens. Leave blank to reuse the English name.'));
    $row->addTextField('nameAr')->setValue($branch['nameAr'] ?? '')->maxLength(150)->setAttribute('dir', 'rtl');

$row = $form->addRow();
    $row->addLabel('addressEn', __('Address (English)'));
    $row->addTextField('addressEn')->setValue($branch['addressEn'] ?? '')->maxLength(255);

$row = $form->addRow();
    $row->addLabel('addressAr', __('Address (Arabic)'));
    $row->addTextField('addressAr')->setValue($branch['addressAr'] ?? '')->maxLength(255)->setAttribute('dir', 'rtl');

$row = $form->addRow();
    $row->addLabel('phone', __('Phone'));
    $row->addTextField('phone')->setValue($branch['phone'] ?? '')->maxLength(40);

$row = $form->addRow();
    $row->addLabel('email', __('Email'));
    $row->addEmail('email')->setValue($branch['email'] ?? '')->maxLength(150);

$headOptions = ['' => __('None')];
foreach ($staff as $s) {
    $headOptions[(int) $s['tawasulPersonID']] = trim(\TawasulOS\Services\Format::name($s['title'], $s['preferredName'], $s['surname'], 'Staff').($s['jobTitle'] ? ' — '.$s['jobTitle'] : ''));
}
$headOptions = array_filter($headOptions);

$row = $form->addRow();
    $row->addLabel('headPersonID', __('Branch Head'))->description(__('Optional. The person responsible for this campus.'));
    $row->addSelect('headPersonID')->fromArray($headOptions)->selected((string) ($branch['headPersonID'] ?? ''))->placeholder(__('None'));

$row = $form->addRow();
    $row->addLabel('noteEn', __('Notes (English)'));
    $row->addTextArea('noteEn')->setValue($branch['noteEn'] ?? '')->maxLength(2000)->setRows(3);

$row = $form->addRow();
    $row->addLabel('noteAr', __('Notes (Arabic)'));
    $row->addTextArea('noteAr')->setValue($branch['noteAr'] ?? '')->maxLength(2000)->setRows(3)->setAttribute('dir', 'rtl');

if (!empty($branch)) {
    $row = $form->addRow();
        $row->addLabel('active', __('Status'))->description(__('Inactive branches stay in the register but disappear from the switcher and cannot be selected.'));
        $row->addYesNoRadio('active')->setValue($branch['active'])->required();
}

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(empty($branch) ? __('Add Branch') : __('Save'));

echo $form->getOutput();