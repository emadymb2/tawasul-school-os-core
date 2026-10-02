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

namespace Tos\Module\TawasulAdmissions\Forms;

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Forms\Builder\FormBuilderInterface;
use TawasulOS\Contracts\Services\Session;
use TawasulOS\Domain\Admissions\AdmissionsAccountGateway;
use TawasulOS\Services\Format;

/**
 * ApplicationAccountForm
 *
 * @version v24
 * @since   v24
 */
class ApplicationAccountForm extends Form
{
    protected $session;
    protected $admissionsAccountGateway;

    public function __construct(Session $session, AdmissionsAccountGateway $admissionsAccountGateway)
    {
        $this->session = $session;
        $this->admissionsAccountGateway = $admissionsAccountGateway;
    }

    public function createForm($urlParams, $tawasulAdmissionsAccountID)
    {
        // QUERY
        $criteria = $this->admissionsAccountGateway->newQueryCriteria()
            ->sortBy(['sortOrder', 'surname', 'preferredName']);

        $accounts = $this->admissionsAccountGateway->queryAdmissionsAccounts($criteria);
        $accounts = array_reduce($accounts->toArray(), function ($group, $item) {
            $role = !empty($item['roleName']) ? $item['roleName'] : __('Unknown');
            $name = !empty($item['surname']) ? Format::name('', $item['preferredName'], $item['surname'], 'Parent', true) : __('N/A');
            $group[$role][$item['tawasulAdmissionsAccountID']] = "{$name} ({$item['email']})";
            return $group;
        }, []);

        // FORM
        $form = Form::create('applicationAccount', Url::fromHandlerRoute('modules/TawasulAdmissions/applications_manage_editAccountProcess.php'));
        $form->addClass('mb-4');

        $form->addHiddenValue('address', $this->session->get('address'));
        $form->addHiddenValues($urlParams);
        $form->addHiddenValue('tab', 5);

        $row = $form->addRow()->addClass('-mb-px');
            $row->addLabel('tawasulAdmissionsAccountID', __('Admissions Account'));
            $row->addSelect('tawasulAdmissionsAccountID')->fromArray($accounts)->required()->selected($tawasulAdmissionsAccountID);

        $form->toggleVisibilityByClass('accountChange')->onSelect('tawasulAdmissionsAccountID')->whenNot($tawasulAdmissionsAccountID);

        $col = $form->addRow()->addClass('accountChange')->addColumn();
        $col->addContent(Format::alert(__('Changing the admissions account for this application will not only change the family this application is attached to, it will give the owner of the new admissions account access to this application form data. Please use care and caution when making this change.'), 'warning'));

        $col->addSubmit();

        return $form;
    }
}
