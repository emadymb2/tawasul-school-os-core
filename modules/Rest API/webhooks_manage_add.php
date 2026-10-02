<?php
/*
Gibbon REST API — Add Webhook
Licensed under the GNU General Public License v3 or later.
*/

use TawasulOS\Forms\Form;
use Gibbon\Module\RestAPI\Resource\Registry;

require_once __DIR__.'/src/bootstrap.php';

if (isActionAccessible($guid, $connection2, '/modules/Rest API/webhooks_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('Manage Webhooks'), 'webhooks_manage.php')
        ->add(__('Add Webhook'));

    $events = [];
    foreach (Registry::events() as $event => $label) {
        $events[$event] = $event.' — '.$label;
    }

    $form = Form::create('webhookAdd', $session->get('absoluteURL').'/modules/Rest API/webhooks_manage_addProcess.php');
    $form->setFactory(\TawasulOS\Forms\DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addLabel('name', __('Name'))->description(__('The system that will receive these events.'));
        $row->addTextField('name')->maxLength(60)->required();

    $row = $form->addRow();
        $row->addLabel('url', __('URL'))->description(__('Events are sent here as a POST request with a JSON body. Use an https address.'));
        $row->addURL('url')->maxLength(500)->required();

    $row = $form->addRow();
        $row->addLabel('events', __('Events'))
            ->description(__('Choose what this system should hear about. A pattern such as students.* covers every change to students.'));
        $row->addSelect('events')->fromArray($events)->selectMultiple()->setSize(14)->required();

    $row = $form->addRow();
        $row->addLabel('headers', __('Extra Headers'))
            ->description(__('Optional. One header per line, in the form Name: value. Useful when the receiver needs its own token.'));
        $row->addTextArea('headers')->setRows(3);

    $row = $form->addRow();
        $row->addLabel('active', __('Active'));
        $row->addYesNo('active')->selected('Y')->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
