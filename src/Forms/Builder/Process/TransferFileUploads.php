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
use TawasulOS\Contracts\Services\Session;
use TawasulOS\Domain\Forms\FormUploadGateway;
use TawasulOS\Domain\Students\StudentNoteGateway;
use TawasulOS\Forms\Builder\AbstractFormProcess;
use TawasulOS\Forms\Builder\FormBuilderInterface;
use TawasulOS\Forms\Builder\Storage\FormDataInterface;

class TransferFileUploads extends AbstractFormProcess
{
    protected $requiredFields = [];

    private $session;
    private $formUploadGateway;
    private $studentNoteGateway;

    public function __construct(Session $session, FormUploadGateway $formUploadGateway, StudentNoteGateway $studentNoteGateway)
    {
        $this->session = $session;
        $this->formUploadGateway = $formUploadGateway;
        $this->studentNoteGateway = $studentNoteGateway;

    }
    public function isEnabled(FormBuilderInterface $builder)
    {
        return $builder->getConfig('createStudent') == 'Y';
    }

    public function process(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->has('tawasulPersonIDStudent')) return;

        $foreignTable = $builder->getDetail('type') == 'Application' ? 'tawasulAdmissionsApplication' : 'tawasulFormSubmission';
        $foreignTableID = $builder->getConfig('foreignTableID');

        $uploads = $this->formUploadGateway->selectAllUploadsByContext($builder->getFormID(), $foreignTable, $foreignTableID)->fetchKeyPair();

        if (empty($uploads)) return;

        $documents = [];
        foreach ($uploads as $name => $path) {
            $documents[] = Format::link($this->session->get('absoluteURL').'/'.$path, $name);
        }

        $this->studentNoteGateway->insert([
            'tawasulPersonID'        => $formData->get('tawasulPersonIDStudent'),
            'title'                 => __('Application Documents'),
            'note'                  => '<p>'.implode('<br/>', $documents).'</p>',
            'tawasulPersonIDCreator' => $this->session->get('tawasulPersonID'),
            'timestamp'             => date('Y-m-d H:i:s'),
        ]);
    }

    public function rollback(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->has('tawasulPersonIDStudent')) return;

        $this->studentNoteGateway->deleteWhere([
            'tawasulPersonID' => $formData->get('tawasulPersonIDStudent'),
            'title'          => __('Application Documents'),
        ]);
    }
}
