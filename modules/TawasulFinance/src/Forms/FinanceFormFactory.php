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

namespace Tos\Module\TawasulFinance\Forms;

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Contracts\Database\Connection;
use TawasulOS\Services\Format;

/**
 * FinanceFormFactory
 *
 * @version v16
 * @since   v16
 */
class FinanceFormFactory extends DatabaseFormFactory
{

    public function __construct(Connection $pdo = null)
    {
        parent::__construct($pdo);
    }

    /**
     * Create and return an instance of DatabaseFormFactory.
     * @return  object DatabaseFormFactory
     */
    public static function create(Connection $pdo = null)
    {
        return new FinanceFormFactory($pdo);
    }

    public function createSelectInvoicee($name, $tawasulSchoolYearID = '', $params = array())
    {
        global $container;

        // Check params and set defaults if not defined
        $params = array_replace(array('allStudents' => false, 'byClass' => false), $params);

        $values = array();

        // Opt Groups
        if ($params['allStudents'] != true) {
            $byFormGroup = __('All Enrolled Students by Form Group');
            $byName = __('All Enrolled Students by Alphabet');
            $byClass = __('All Enrolled Students by Class');
        }
        else {
            $byFormGroup = __('All Students by Form Group');
            $byName = __('All Students by Alphabet');
            $byClass = __('All Students by Class');
        }

        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        if ($params['allStudents'] != true) {
            $sql = "SELECT tawasulFinanceInvoiceeID, preferredName, surname, tawasulFormGroup.nameShort AS formGroupName, dayType
                FROM tawasulPerson
                    JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                    JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                    JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulPerson.status='Full'
                    AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                ORDER BY tawasulFormGroup.nameShort, surname, preferredName";
        }
        else {
            $sql = "SELECT tawasulFinanceInvoiceeID, preferredName, surname, tawasulFormGroup.nameShort AS formGroupName, dayType
                FROM tawasulPerson
                    JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                    JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                    JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                ORDER BY tawasulFormGroup.nameShort, surname, preferredName";
        }

        $results = $this->pdo->executeQuery($data, $sql);
        $students = ($results->rowCount() > 0)? $results->fetchAll() : array();

        // Add students by Form Group and Name
        foreach ($students as $student) {
            $fullName = Format::name('', $student['preferredName'], $student['surname'], 'Student', true);

            $values[$byFormGroup][$student['tawasulFinanceInvoiceeID']] = $student['formGroupName'].' - '.$fullName;
            $values[$byName][$student['tawasulFinanceInvoiceeID']] = $fullName.' - '.$student['formGroupName'];
        }

        // Sort the byName list so it's not byFormGroup
        if (!empty($values[$byName]) && is_array($values[$byName])) {
            asort($values[$byName]);
        }

        // Add students by class (optionally)
        if ($params["byClass"]) {
            $sql = "SELECT tawasulFinanceInvoiceeID, tawasulCourseClass.tawasulCourseClassID, preferredName, surname, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS class
                FROM tawasulPerson
                    JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                    JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                    JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                    JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                    AND tawasulCourseClassPerson.role='Student'
                ORDER BY tawasulCourse.nameShort, tawasulCourseClass.nameShort, surname, preferredName";

            $results = $this->pdo->executeQuery($data, $sql);
            $studentsByClass = ($results->rowCount() > 0)? $results->fetchAll() : array();

            foreach ($studentsByClass as $student) {
                $fullName = Format::name('', $student['preferredName'], $student['surname'], 'Student', true);

                $values[$byClass][$student['tawasulCourseClassID']."-".$student['tawasulFinanceInvoiceeID']] = $student['class'].' - '.$fullName;
            }
        }

        // Add students by Day Type (optionally)
        $dayTypeOptions = $container->get(SettingGateway::class)->getSettingByScope('User Admin', 'dayTypeOptions');
        if (!empty($dayTypeOptions)) {
            $dayTypes = explode(',', $dayTypeOptions);

            foreach ($students as $student) {
                if (empty($student['dayType']) || !in_array($student['dayType'], $dayTypes)) continue;

                $byDayType = $student['dayType'].' '.__('Students by Form Groups');
                $fullName = Format::name('', $student['preferredName'], $student['surname'], 'Student', true);

                $values[$byDayType][$student['tawasulFinanceInvoiceeID']] = $student['formGroupName'].' - '.$fullName;
            }
        }

        return $this->createSelect($name)->fromArray($values)->placeholder();
    }

    public function createSelectInvoiceStatus($name, $currentStatus = 'All')
    {
        if ($currentStatus == 'Pending' || $currentStatus == 'Cancelled' || $currentStatus == 'Refunded') {
            return $this->createTextField($name)->readonly()->setValue(__($currentStatus));
        }

        $statuses = array();
        if ($currentStatus == 'All') {
            $statuses = array(
                ''                => __('All'),
                'Pending'          => __('Pending'),
                'Issued'           => __('Issued'),
                'Issued - Overdue' => __('Issued - Overdue'),
                'Paid'             => __('Paid'),
                'Paid - Partial'   => __('Paid - Partial'),
                'Paid - Late'      => __('Paid - Late'),
                'Cancelled'        => __('Cancelled'),
                'Refunded'         => __('Refunded'),
            );
        } else if ($currentStatus == 'Issued') {
            $statuses = array(
                'Issued'         => __('Issued'),
                'Paid'           => __('Paid'),
                'Paid - Partial' => __('Paid - Partial'),
                'Cancelled'      => __('Cancelled'),
            );
        } else if ($currentStatus == 'Paid') {
            $statuses = array(
                'Paid'     => __('Paid'),
                'Refunded' => __('Refunded'),
            );
        } else if ($currentStatus == 'Paid - Partial') {
            $statuses = array(
                'Paid - Partial'  => __('Paid - Partial'),
                'Paid - Complete' => __('Paid - Complete'),
                'Refunded'        => __('Refunded'),
            );
        }

        return $this->createSelect($name)->fromArray($statuses);
    }

    public function createSelectBillingSchedule($name, $tawasulSchoolYearID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT tawasulFinanceBillingScheduleID as value, name FROM tawasulFinanceBillingSchedule
                WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY name";

        return $this->createSelect($name)->fromQuery($this->pdo, $sql, $data)->placeholder();
    }

    public function createSelectFee($name, $tawasulSchoolYearID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT tawasulFinanceFeeCategory.name as groupBy, tawasulFinanceFee.tawasulFinanceFeeID as value, tawasulFinanceFee.name
                FROM tawasulFinanceFee
                JOIN tawasulFinanceFeeCategory ON (tawasulFinanceFee.tawasulFinanceFeeCategoryID=tawasulFinanceFeeCategory.tawasulFinanceFeeCategoryID)
                WHERE tawasulFinanceFee.tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulFinanceFee.active='Y'
                ORDER BY tawasulFinanceFeeCategory.name, tawasulFinanceFee.name";

        return $this->createSelect($name)
            ->fromArray(array('' => __('Choose a fee to add it')))
            ->fromArray(array('Ad Hoc Fee' => __('Ad Hoc Fee')))
            ->fromQuery($this->pdo, $sql, $data, 'groupBy');
    }

    public function createSelectFeeCategory($name)
    {
        $sql = "SELECT tawasulFinanceFeeCategoryID as value, name FROM tawasulFinanceFeeCategory WHERE active='Y' ORDER BY (tawasulFinanceFeeCategoryID=1) DESC, name";

        return $this->createSelect($name)->fromQuery($this->pdo, $sql);
    }

    public function createSelectPaymentMethod($name)
    {
        $sql = "SELECT value FROM tawasulSetting WHERE scope='Finance' AND name='paymentTypeOptions'";
        $result = $this->pdo->selectOne($sql);

        $methods = array_map('trim', explode(',', $result));
        $methods = array_reduce($methods, function ($group, $item) {
            $group[$item] = __($item);
            return $group;
        }, []);

        return $this->createSelect($name)->fromArray($methods)->placeholder();
    }

    public function createSelectMonth($name)
    {
        $months = array_reduce(range(1,12), function($group, $item) {
            $monthTimestamp = mktime(0, 0, 0, $item, 1, 0);
            $month = Format::monthDigits($monthTimestamp);
            $group[$month] = $month.' - '.Format::monthName($monthTimestamp);
            return $group;
        }, array());

        return $this->createSelect($name)->fromArray($months)->placeholder();
    }

    public function createInvoiceEmailCheckboxes($checkboxName, $hiddenValueName, $values, $session)
    {
        $table = $this->createTable()->setClass('w-full');

        // Company Emails
        if ($values['invoiceTo'] == 'Company') {
            if (empty($values['companyEmail']) || empty($values['companyContact']) || empty($values['companyName'])) {
                $table->addRow()->addTableCell(__('There is no company contact available to send this invoice to.'))->colSpan(2)->wrap('<div class="warning">', '</div>');
            } else {
                $row = $table->addRow();
                    $row->addLabel($checkboxName, $values['companyContact'])->description($values['companyName']);
                    $row->addCheckbox($checkboxName)
                        ->description($values['companyEmail'])
                        ->setValue($values['companyEmail'])
                        ->checked($values['companyEmail'])
                        ->append('<input type="hidden" name="'.$hiddenValueName.'" value="'.$values['companyContact'].'">');
            }
        }

        // Family Emails
        if ($values['invoiceTo'] == 'Family' || ($values['invoiceTo'] == 'Company' && $values['companyCCFamily'] == 'Y')) {
            $data = array('tawasulFinanceInvoiceeID' => $values['tawasulFinanceInvoiceeID']);
            $sql = "SELECT parent.title, parent.surname, parent.preferredName, parent.email, tawasulFamilyRelationship.relationship
                    FROM tawasulFinanceInvoicee
                    JOIN tawasulPerson AS student ON (tawasulFinanceInvoicee.tawasulPersonID=student.tawasulPersonID)
                    JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=student.tawasulPersonID)
                    JOIN tawasulFamilyAdult ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID)
                    JOIN tawasulPerson AS parent ON (tawasulFamilyAdult.tawasulPersonID=parent.tawasulPersonID)
                    LEFT JOIN tawasulFamilyRelationship ON (tawasulFamilyRelationship.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID && tawasulFamilyRelationship.tawasulPersonID1=parent.tawasulPersonID && tawasulFamilyRelationship.tawasulPersonID2=student.tawasulPersonID)
                    WHERE tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID
                    AND (contactPriority=1 OR (contactPriority=2 AND contactEmail='Y'))
                    AND parent.status='Full'
                    GROUP BY parent.tawasulPersonID
                    ORDER BY contactPriority, surname, preferredName";

            $result = $this->pdo->executeQuery($data, $sql);

            if ($result->rowCount() == 0) {
                $table->addRow()->addTableCell(__('There are no family members available to send this receipt to.'))->colSpan(2)->wrap('<div class="warning">', '</div>');
            } else {
                while ($person = $result->fetch()) {
                    $name = Format::name(htmlPrep($person['title']), htmlPrep($person['preferredName']), htmlPrep($person['surname']), 'Parent', false);
                    $row = $table->addRow();
                        $row->addLabel($checkboxName, $name)->description($values['invoiceTo'] == 'Company'? __('(Family CC)') : '')->description($person['relationship']);
                        $row->onlyIf(!empty($person['email']))
                            ->addCheckbox($checkboxName)
                            ->description($person['email'])
                            ->setValue($person['email'])
                            ->checked($person['email'])
                            ->append('<input type="hidden" name="'.$hiddenValueName.'" value="'.$name.'">');
                        $row->onlyIf(empty($person['email']))
                            ->addContent(__('No email address.'))
                            ->addClass('right')
                            ->wrap('<span class="text-xs italic">', '</span>');
                }
            }
        }

        // CC Self
        if (!empty($session->get('email'))) {
            $name = Format::name('', htmlPrep($session->get('preferredName')), htmlPrep($session->get('surname')), 'Parent', false);
            $row = $table->addRow()->addClass('emailReceiptSection');
                $row->addLabel($checkboxName, $name)->description(__('(CC Self?)'));
                $row->addCheckbox($checkboxName)
                    ->description($session->get('email'))
                    ->setValue($session->get('email'))
                    ->append('<input type="hidden" name="'.$hiddenValueName.'" value="'.$name.'">');
        }

        return $table;
    }
}
