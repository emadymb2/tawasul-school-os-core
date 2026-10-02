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

//TawasulOS system-wide includes
require_once __DIR__ . '/../../tawasul.php';

echo "<link rel='stylesheet' type='text/css' href='".$session->get('absoluteURL').'/themes/'.$session->get('tawasulThemeName')."/css/main.css' />";
?>

<div id="wrap">
	<div id="header">
		<div id="header-left">
			<a href='<?php echo $session->get('absoluteURL') ?>'><img height='100px' width='400px' class="logo" alt="Logo" title="Logo" src="<?php echo $session->get('absoluteURL').'/'.$session->get('organisationLogo'); ?>"/></a>
		</div>
		<div id="header-right">

		</div>
	</div>
	<div id="content-wrap">
		<div id="content">
			<?php
            if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/resources_view_full.php') == false) {
                //Acess denied
                $page->addError(__('Your request failed because you do not have access to this action.'));
            } else {
                //Proceed!
                //Get class variable
                $tawasulResourceID = $_GET['tawasulResourceID'] ?? '';
                if ($tawasulResourceID == '') {
                    echo "<div class='warning'>";
                    echo 'Resource has not been specified .';
                    echo '</div>';
                }
                //Check existence of and access to this resource.
                else {

                        $data = array('tawasulResourceID' => $tawasulResourceID);
                        $sql = 'SELECT * FROM tawasulResource WHERE tawasulResourceID=:tawasulResourceID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);

                    if ($result->rowCount() != 1) {
                        echo "<div class='warning'>";
                        echo __('The specified record does not exist.');
                        echo '</div>';
                    } else {
                        $row = $result->fetch();

                        echo '<h1>';
                        echo $row['name'];
                        echo '</h1>';

                        echo $row['content'];
                    }
                }
            }
            ?>
		</div>
		<div id="sidebar">
		</div>
	</div>
	<div id="footer">
		<a href="https://tawasuledu.org">TawasulOS</a> v<?php echo $version ?> | &#169; 2011, <a href="http://rossparker.org">Ross Parker</a> at <a href="http://www.ichk.edu.hk">International College Hong Kong</a> | Created under the <a href="https://www.gnu.org/licenses/gpl.html">GNU General Public License</a>
	</div>
</div>
