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

namespace TawasulOS\Forms\Builder\Processor;

use TawasulOS\Forms\Builder\AbstractFormProcessor;
use TawasulOS\Forms\Builder\Process\SendSubmissionEmail;
use TawasulOS\Forms\Builder\Process\SendAcceptanceEmail;
use TawasulOS\Forms\Builder\Process\SendReferenceRequest;
use TawasulOS\Forms\Builder\Process\ApplicationCheck;
use TawasulOS\Forms\Builder\Process\ApplicationSubmit;
use TawasulOS\Forms\Builder\Process\ApplicationAccept;
use TawasulOS\Forms\Builder\Process\CreateStudent;
use TawasulOS\Forms\Builder\Process\CreateFamily;
use TawasulOS\Forms\Builder\Process\CreateParents;
use TawasulOS\Forms\Builder\Process\CreateMedicalRecord;
use TawasulOS\Forms\Builder\Process\CreateINRecord;
use TawasulOS\Forms\Builder\Process\CreateInvoicee;
use TawasulOS\Forms\Builder\Process\EnrolStudent;
use TawasulOS\Forms\Builder\Process\AssignHouse;
use TawasulOS\Forms\Builder\Process\NewStudentDetails;
use TawasulOS\Forms\Builder\Process\TransferFileUploads;
use TawasulOS\Forms\Builder\Process\PaySubmissionFee;
use TawasulOS\Forms\Builder\Process\PayProcessingFee;

class ApplicationFormProcessor extends AbstractFormProcessor 
{
    protected function submitProcess()
    {
        $this->run(ApplicationSubmit::class);
        $this->run(SendReferenceRequest::class);
        $this->run(SendSubmissionEmail::class);
        $this->run(PaySubmissionFee::class);
    }

    protected function editProcess()
    {
        $this->run(SendSubmissionEmail::class);
        $this->run(SendReferenceRequest::class);
        $this->run(SendAcceptanceEmail::class);
        $this->run(PayProcessingFee::class);
    }

    protected function acceptProcess()
    {
        $this->run(ApplicationCheck::class);
        $this->run(CreateStudent::class);
        $this->run(CreateFamily::class);
        $this->run(CreateParents::class);
        $this->run(EnrolStudent::class);
        $this->run(AssignHouse::class);
        $this->run(NewStudentDetails::class);
        $this->run(TransferFileUploads::class);
        $this->run(CreateMedicalRecord::class);
        $this->run(CreateINRecord::class);
        $this->run(CreateInvoicee::class);
        $this->run(ApplicationAccept::class);
        $this->run(SendAcceptanceEmail::class);
    }
}
