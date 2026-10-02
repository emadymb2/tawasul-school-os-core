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

namespace Tos\Module\TawasulMessenger;

use TawasulOS\Contracts\Services\Session;
use TawasulOS\Contracts\Database\Connection;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\View\Sandbox;

/**
 * Signature
 *
 * @version v26
 * @since   v26
 */
class Signature
{
    protected $session;
    protected $db;
    protected $sandbox;
    protected $settingGateway;
    protected $signatureTemplate;

    public function __construct(Session $session, Connection $db, Sandbox $sandbox, SettingGateway $settingGateway)
    {
        $this->session = $session;
        $this->db = $db;
        $this->sandbox = $sandbox;
        $this->settingGateway = $settingGateway;

        $this->signatureTemplate = $this->settingGateway->getSettingByScope('Messenger', 'signatureTemplate');
    }

    /**
     * Build an email signature for the specified user using the signature template.
     *
     * @param string $tawasulPersonID
     * @return string
     */
    public function getSignature($tawasulPersonID)
    {
        $signature = '';

        $data = ['tawasulPersonID' => $tawasulPersonID];
        $sql = 'SELECT tawasulStaff.*, surname, firstName, preferredName, email FROM tawasulStaff JOIN tawasulPerson ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID';
        $result = $this->db->select($sql, $data);

        if ($result->rowCount() == 1) {
            $values = $result->fetch();

            $signatureData = $values + [
                'organisationName' => $this->session->get('organisationName'),
            ];
            $signature = '<p></p>'.$this->sandbox->render($this->signatureTemplate, $signatureData);
        }

        return $signature;
    }
}
