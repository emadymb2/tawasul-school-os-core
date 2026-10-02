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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\Timetable\CourseEnrolmentGateway;
use TawasulOS\Forms\Builder\AbstractFormProcess;
use TawasulOS\Forms\Builder\FormBuilderInterface;
use TawasulOS\Forms\Builder\Storage\FormDataInterface;
use TawasulOS\Forms\Builder\View\EnrolStudentView;
use TawasulOS\Forms\Builder\Exception\FormProcessException;

class EnrolStudent extends AbstractFormProcess implements ViewableProcess
{
    protected $requiredFields = ['tawasulSchoolYearIDEntry', 'tawasulYearGroupIDEntry'];

    private $settingGateway;
    private $studentGateway;
    private $courseEnrolmentGateway;

    public function __construct(SettingGateway $settingGateway, StudentGateway $studentGateway, CourseEnrolmentGateway $courseEnrolmentGateway)
    {
        $this->settingGateway = $settingGateway;
        $this->studentGateway = $studentGateway;
        $this->courseEnrolmentGateway = $courseEnrolmentGateway;
    }

    public function getViewClass() : string
    {
        return EnrolStudentView::class;
    }

    public function isEnabled(FormBuilderInterface $builder)
    {
        return $builder->getConfig('enrolStudent') == 'Y';
    }

    public function process(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->hasAll(['tawasulPersonIDStudent', 'tawasulFormGroupIDEntry'])) {
            return;
        }

        // Enrol the student with the following data
        $data = [
            'tawasulPersonID'     => $formData->get('tawasulPersonIDStudent'),
            'tawasulSchoolYearID' => $formData->get('tawasulSchoolYearIDEntry'),
            'tawasulYearGroupID'  => $formData->get('tawasulYearGroupIDEntry'),
            'tawasulFormGroupID'  => $formData->get('tawasulFormGroupIDEntry'),
        ];

        $tawasulStudentEnrolmentID = $this->studentGateway->insert($data);

        if (empty($tawasulStudentEnrolmentID)) {
            return;
        }

        $formData->setResult('tawasulStudentEnrolmentID', $tawasulStudentEnrolmentID);

        // Attempt to auto-enrol this student in any synced courses
        if ($this->settingGateway->getSettingByScope('Timetable Admin', 'autoEnrolCourses') == 'Y') {
            $enrolmentDate = $this->courseEnrolmentGateway->getEnrolmentDateBySchoolYear($formData->get('tawasulSchoolYearIDEntry'));

            $inserted = $this->courseEnrolmentGateway->insertAutomaticCourseEnrolments($formData->get('tawasulFormGroupIDEntry'), $formData->get('tawasulPersonIDStudent'), $enrolmentDate);

            $formData->setResult('autoEnrolCoursesResult', $inserted);
        }
    }

    public function rollback(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->has('tawasulStudentEnrolmentID')) return;

        $this->courseEnrolmentGateway->deleteAutomaticCourseEnrolments($formData->get('tawasulFormGroupIDEntry'), $formData->get('tawasulStudentEnrolmentID'));
        $formData->setResult('autoEnrolCoursesResult', false);

        $this->studentGateway->delete($formData->get('tawasulStudentEnrolmentID'));
        $formData->set('tawasulStudentEnrolmentID', null);
    }

}
