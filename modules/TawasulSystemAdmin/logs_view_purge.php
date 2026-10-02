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

require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/logs_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('View Logs'), 'logs_view.php')
        ->add(__('Purge Logs'));

    $form = Form::create('logs', $session->get('absoluteURL').'/modules/TawasulSystemAdmin/logs_view_purgeProcess.php');

    $form->addHiddenValue('address', $session->get('address'));

    $sql = "SELECT DISTINCT title AS value, title AS name FROM tawasulLog ORDER BY title";
    $row = $form->addRow();
        $row->addLabel('title', __('Title'));
        $row->addSearchSelect('title')->fromQuery($pdo, $sql)->selectMultiple()->required();

    $row = $form->addRow();
        $row->addLabel('cutoffDate', __('Cutoff Date'))->description(__('Delete all logs older than this date.'));
        $row->addDate('cutoffDate')->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
