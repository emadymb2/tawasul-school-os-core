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

namespace TawasulOS\Forms\Builder\Process;

use TawasulOS\Services\Format;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Comms\NotificationSender;
use TawasulOS\Contracts\Services\Session;
use TawasulOS\Domain\System\NotificationGateway;
use TawasulOS\Forms\Builder\AbstractFormProcess;
use TawasulOS\Forms\Builder\FormBuilderInterface;
use TawasulOS\Forms\Builder\Storage\FormDataInterface;
use TawasulOS\Forms\Builder\Process\ViewableProcess;
use TawasulOS\Forms\Builder\View\ApplicationSubmitView;

class ApplicationSubmit extends AbstractFormProcess implements ViewableProcess
{
    protected $requiredFields = [];
    protected $initialStatus;

    protected $session;
    protected $notificationSender;
    protected $notificationGateway;

    public function __construct(Session $session, NotificationSender $notificationSender, NotificationGateway $notificationGateway)
    {
        $this->session = $session;
        $this->notificationSender = $notificationSender;
        $this->notificationGateway = $notificationGateway;
    }

    public function getViewClass() : string
    {
        return ApplicationSubmitView::class;
    }

    public function isEnabled(FormBuilderInterface $builder)
    {
        return true;
    }

    public function process(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        $this->initialStatus = $formData->getStatus();

        $formData->setStatus('Pending');
        $formData->setResult('statusDate', date('Y-m-d H:i:s'));

        // Ensure there is always a school year set
        if (!$formData->has('tawasulSchoolYearIDEntry')) {
            $formData->set('tawasulSchoolYearIDEntry', $this->session->get('tawasulSchoolYearID'));
        }

        $this->sendNotifications($builder, $formData);
    }

    public function rollback(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        $formData->setStatus($this->initialStatus);
        $formData->setResult('statusDate', null);
    }

    protected function sendNotifications(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->hasAll(['preferredName', 'surname'])) return;
        
        $studentName = Format::name('', $formData->get('preferredName'), $formData->get('surname'), 'Student');

        // Raise a new notification event for Admissions
        $event = new NotificationEvent('Admissions', 'New Application Form');

        $event->addScope('tawasulYearGroupID', $formData->get('tawasulYearGroupIDEntry'));
        $event->addRecipient($this->session->get('organisationAdmissions'));
        $event->setNotificationText(sprintf(__('An application form has been submitted for %1$s.'), $studentName));
        $event->setActionLink("/index.php?q=/modules/TawasulAdmissions/applications_manage_edit.php&tawasulAdmissionsApplicationID=".$builder->getConfig('foreignTableID')."&tawasulSchoolYearID=".$formData->get('tawasulSchoolYearIDEntry')."&search=");

        $event->pushNotifications($this->notificationGateway, $this->notificationSender);
        
        $this->notificationSender->sendNotifications();
    }
}
