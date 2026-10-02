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

namespace TawasulOS\Session;

use SessionHandler;
use TawasulOS\Session\Session;
use TawasulOS\Domain\System\SessionGateway;
use TawasulOS\Session\NativeSessionHandler;
use TawasulOS\Session\DatabaseSessionHandler;
use TawasulOS\Contracts\Database\Connection;
use TawasulOS\Contracts\Services\Session as SessionInterface;
use Psr\Container\ContainerInterface;

/**
 * SessionFactory Class
 *
 * @version	v24
 * @since	v23
 */
class SessionFactory
{
    /**
     * Method for creating Session from the ContainerInterface and
     * environment variables (i.e. $guid, $_GET, $_POST).
     *
     * @param array $config An array of data from the config file
     *
     * @return \TawasulOS\Session\Session The newly created session object.
     */
    public static function create(ContainerInterface $container): SessionInterface {
        global $guid;

        $config = $container->get('config')->getConfig();

        // If session guid is not set, fallback to global $guid.
        $_guid = $config['guid'] ?? $guid ?? '';

        // Start the session (this should be the first time called)
        if (session_status() !== PHP_SESSION_ACTIVE) {
            
            // Check if the database exists, if not, use the built-in PHP session handler class
            if (\SESSION_TABLE_AVAILABLE && $container->has(Connection::class)) {
                $sessionGateway = $container->get(SessionGateway::class);

                if (!empty($config['sessionHandler']) && $config['sessionHandler'] == 'database') {
                    $handler = new DatabaseSessionHandler($sessionGateway, $config['sessionEncryptionKey'] ?? null);
                } else {
                    $handler = new NativeSessionHandler($sessionGateway, $config['sessionEncryptionKey'] ?? null);
                }
            } else {
                $handler = new SessionHandler();
            }
        
            // Set the handler for the session, enabling non-default
            session_set_save_handler($handler, true);

            //Prevent breakage of back button on POST pages
            ini_set('session.cache_limiter', 'private');
            session_cache_limiter(false);

            session_start([
                'name'             => 'G'.substr(hash('sha256', $_guid), 0, 16),
                'cookie_samesite'  => 'Lax',
                'cookie_httponly'  => true,
                'cookie_secure'    => $config['sessionSecure'] ?? isset($_SERVER['HTTPS']),
            ]);
        }

        header('X-Frame-Options: SAMEORIGIN');
        header_remove('X-Powered-By');

        // Detect the current module from the GET 'q' param. Fallback to the POST 'address',
        // which is currently used in many Process pages.
        // TODO: replace this logic when switching to routing.
        $address = $_GET['q'] ?? $_POST['address'] ?? '';
        $path = !empty($address) ? $address : (php_sapi_name() === 'cli' ? realpath($_SERVER['argv'][0]) : $_SERVER['SCRIPT_FILENAME']);

        $module = getModuleName($path);
        $action = getActionName($path);
        
        // Create the instance from information of container
        // and environment.
        return new Session($_guid, $address, $module, $action);
    }

    /**
     * Populates the session with the essential System settings, required for both
     * logged in users and anonymous sessions.
     *
     * @param Session $session
     * @param Connection $db
     */
    public static function populateSettings(Session $session, Connection $db)
    {
        // System settings from tawasulSetting
        $result = $db->select('SELECT name, value FROM tawasulSetting WHERE scope=:scope', [
            ':scope' => 'System',
        ]);

        while ($row = $result->fetch()) {
            $session->set($row['name'], $row['value']);
        }

        // Language settings from tawasuli18n
        $result = $db->select('SELECT * FROM tawasuli18n WHERE systemDefault=:systemDefault', [
            ':systemDefault' => 'Y',
        ]);

        while ($row = $result->fetch()) {
            $session->set('i18n', $row);
        }
    }

    public static function setCurrentSchoolYear(Session $session, array $schoolYear)
    {
        if (empty($schoolYear['tawasulSchoolYearID']) || empty($schoolYear['name'])) {
            throw new \Exception();
        }

        $session->set('tawasulSchoolYearID', $schoolYear['tawasulSchoolYearID']);
        $session->set('tawasulSchoolYearName', $schoolYear['name']);
        $session->set('tawasulSchoolYearSequenceNumber', $schoolYear['sequenceNumber']);
        $session->set('tawasulSchoolYearFirstDay',$schoolYear['firstDay']);
        $session->set('tawasulSchoolYearLastDay', $schoolYear['lastDay']);

        if (!$session->exists('tawasulSchoolYearIDCurrent')) {
            $session->set('tawasulSchoolYearIDCurrent', $schoolYear['tawasulSchoolYearID']);
            $session->set('tawasulSchoolYearNameCurrent', $schoolYear['name']);
            $session->set('tawasulSchoolYearSequenceNumberCurrent', $schoolYear['sequenceNumber']);
        }
    }
}
