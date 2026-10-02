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

//Module includes
use TawasulOS\Domain\System\SettingGateway;

require_once __DIR__ . '/moduleFunctions.php';

// common variables
$makeUnitsPublic = $container->get(SettingGateway::class)->getSettingByScope('Planner', 'makeUnitsPublic');
$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulUnitID = $_GET['tawasulUnitID'] ?? '';

$page->breadcrumbs
    ->add(__('Learn With Us'), 'modules/TawasulPlanner/units_public.php', [
        'tawasulSchoolYearID' => $tawasulSchoolYearID,
        'sidebar' => 'false',
    ])
    ->add(__('View Unit'));

if ($makeUnitsPublic != 'Y') {
    //Acess denied
    $page->addError(__('Your request failed because you do not have access to this action.'));
} else {
    //Check if courseschool year specified
    if ($tawasulUnitID == '' or $tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulUnitID' => $tawasulUnitID);
            $sql = "SELECT tawasulCourse.nameShort AS courseName, tawasulSchoolYearID, tawasulUnit.* FROM tawasulUnit JOIN tawasulCourse ON (tawasulUnit.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulUnitID=:tawasulUnitID AND sharedPublic='Y'";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $row = $result->fetch(); ?>
			<script type='text/javascript'>
				$(function() {
					$( "#tabs" ).tabs({
						ajaxOptions: {
							error: function( xhr, status, index, anchor ) {
								$( anchor.hash ).html(
									"Couldn't load this tab." );
							}
						}
					});
				});
			</script>

			<?php
            echo '<h2>';
            echo $row['name'];
            echo '</h2>';

            echo "<div id='tabs' style='width: 100%; margin: 20px 0'>";
                //Prep classes in this unit

                    $dataClass = array('tawasulUnitID' => $tawasulUnitID);
                    $sqlClass = 'SELECT tawasulUnitClass.tawasulCourseClassID, tawasulCourseClass.nameShort FROM tawasulUnitClass JOIN tawasulCourseClass ON (tawasulUnitClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulUnitID=:tawasulUnitID ORDER BY nameShort';
                    $resultClass = $connection2->prepare($sqlClass);
                    $resultClass->execute($dataClass);

                //Tab links
                echo '<ul>';
            echo "<li><a href='#tabs1'>".__('Overview').'</a></li>';
            echo "<li><a href='#tabs2'>".__('Content').'</a></li>';
            echo "<li><a href='#tabs3'>".__('Resources').'</a></li>';
            echo "<li><a href='#tabs4'>".__('Outcomes').'</a></li>';
            echo '</ul>';

                //Tabs
                echo "<div id='tabs1'>";
            echo '<h4>';
            echo __('Description');
            echo '</h4>';
            if ($row['description'] == '') {
                echo $page->getBlankSlate();
            } else {
                echo '<p>';
                echo $row['description'];
                echo '</p>';
            }

            if ($row['license'] != '') {
                echo '<h4>';
                echo __('License');
                echo '</h4>';
                echo '<p>';
                echo __('This work is shared under the following license:').' '.$row['license'];
                echo '</p>';
            }
            echo '</div>';
            echo "<div id='tabs2'>";

                $dataBlocks = array('tawasulUnitID' => $tawasulUnitID);
                $sqlBlocks = 'SELECT * FROM tawasulUnitBlock WHERE tawasulUnitID=:tawasulUnitID ORDER BY sequenceNumber';
                $resultBlocks = $connection2->prepare($sqlBlocks);
                $resultBlocks->execute($dataBlocks);

            $resourceContents = '';

            while ($rowBlocks = $resultBlocks->fetch()) {
                if ($rowBlocks['title'] != '' or $rowBlocks['type'] != '' or $rowBlocks['length'] != '') {
                    echo '<hr/>';
                    echo "<div class='blockView' style='min-height: 35px'>";
                    if ($rowBlocks['type'] != '' or $rowBlocks['length'] != '') {
                        $width = '69%';
                    } else {
                        $width = '100%';
                    }
                    echo "<div style='padding-left: 3px; width: $width; float: left;'>";
                    if ($rowBlocks['title'] != '') {
                        echo "<h5 style='padding-bottom: 2px'>".$rowBlocks['title'].'</h5>';
                    }
                    echo '</div>';
                    if ($rowBlocks['type'] != '' or $rowBlocks['length'] != '') {
                        echo "<div style='float: right; width: 29%; padding-right: 3px; height: 25px'>";
                        echo "<div style='text-align: right; font-size: 75%; font-style: italic; margin-top: 5px; border-bottom: 1px solid #ddd; height: 21px'>";
                        if ($rowBlocks['type'] != '') {
                            echo $rowBlocks['type'];
                            if ($rowBlocks['length'] != '') {
                                echo ' | ';
                            }
                        }
                        if ($rowBlocks['length'] != '') {
                            echo $rowBlocks['length'].' min';
                        }
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                if ($rowBlocks['contents'] != '') {
                    echo "<div style='padding: 15px 3px 10px 3px; width: 100%; text-align: justify; border-bottom: 1px solid #ddd'>".$rowBlocks['contents'].'</div>';
                    $resourceContents .= $rowBlocks['contents'];
                }
            }
            echo '</div>';
            echo "<div id='tabs3'>";
			//Resources
			$noReosurces = true;

            if (!empty($resourceContents)) {
                $resourceContents = '<?xml version="1.0" encoding="UTF-8"?>'.$resourceContents;

                //Links
                $links = '';
                $linksArray = array();
                $linksCount = 0;
                $dom = new DOMDocument();
                $dom->loadHTML($resourceContents);
                foreach ($dom->getElementsByTagName('a') as $node) {
                    if ($node->nodeValue != '') {
                        $linksArray[$linksCount] = "<li><a href='".$node->getAttribute('href')."'>".$node->nodeValue.'</a></li>';
                        ++$linksCount;
                    }
                }

                $linksArray = array_unique($linksArray);
                natcasesort($linksArray);

                foreach ($linksArray as $link) {
                    $links .= $link;
                }

                if ($links != '') {
                    echo '<h2>';
                    echo 'Links';
                    echo '</h2>';
                    echo '<ul>';
                    echo $links;
                    echo '</ul>';
                    $noReosurces = false;
                }

                //Images
                $images = '';
                $imagesArray = array();
                $imagesCount = 0;
                $dom2 = new DOMDocument();
                $dom2->loadHTML($resourceContents);
                foreach ($dom2->getElementsByTagName('img') as $node) {
                    if ($node->getAttribute('src') != '') {
                        $imagesArray[$imagesCount] = "<img class='resource' style='margin: 10px 0; max-width: 560px' src='".$node->getAttribute('src')."'/><br/>";
                        ++$imagesCount;
                    }
                }

                $imagesArray = array_unique($imagesArray);
                natcasesort($imagesArray);

                foreach ($imagesArray as $image) {
                    $images .= $image;
                }

                if ($images != '') {
                    echo '<h2>';
                    echo 'Images';
                    echo '</h2>';
                    echo $images;
                    $noReosurces = false;
                }

                //Embeds
                $embeds = '';
                $embedsArray = array();
                $embedsCount = 0;
                $dom2 = new DOMDocument();
                $dom2->loadHTML($resourceContents);
                foreach ($dom2->getElementsByTagName('iframe') as $node) {
                    if ($node->getAttribute('src') != '') {
                        $embedsArray[$embedsCount] = "<iframe style='max-width: 560px' width='".$node->getAttribute('width')."' height='".$node->getAttribute('height')."' src='".$node->getAttribute('src')."' frameborder='".$node->getAttribute('frameborder')."'></iframe>";
                        ++$embedsCount;
                    }
                }

                $embedsArray = array_unique($embedsArray);
                natcasesort($embedsArray);

                foreach ($embedsArray as $embed) {
                    $embeds .= $embed.'<br/><br/>';
                }

                if ($embeds != '') {
                    echo '<h2>';
                    echo 'Embeds';
                    echo '</h2>';
                    echo $embeds;
                    $noReosurces = false;
                }
            }

			//No resources!
			if ($noReosurces) {
				echo $page->getBlankSlate();
			}
            echo '</div>';
            echo "<div id='tabs4'>";
				//Spit out outcomes

					$dataBlocks = array('tawasulUnitID' => $tawasulUnitID);
					$sqlBlocks = "SELECT tawasulUnitOutcome.*, scope, name, nameShort, category, tawasulYearGroupIDList FROM tawasulUnitOutcome JOIN tawasulOutcome ON (tawasulUnitOutcome.tawasulOutcomeID=tawasulOutcome.tawasulOutcomeID) WHERE tawasulUnitID=:tawasulUnitID AND active='Y' ORDER BY sequenceNumber";
					$resultBlocks = $connection2->prepare($sqlBlocks);
					$resultBlocks->execute($dataBlocks);
            if ($resultBlocks->rowCount() > 0) {
                echo "<table cellspacing='0' style='width: 100%'>";
                echo "<tr class='head'>";
                echo '<th>';
                echo __('Scope');
                echo '</th>';
                echo '<th>';
                echo __('Category');
                echo '</th>';
                echo '<th>';
                echo __('Name');
                echo '</th>';
                echo '<th>';
                echo __('Year Groups');
                echo '</th>';
                echo '<th>';
                echo __('Actions');
                echo '</th>';
                echo '</tr>';

                $count = 0;
                $rowNum = 'odd';
                while ($rowBlocks = $resultBlocks->fetch()) {
                    if ($count % 2 == 0) {
                        $rowNum = 'even';
                    } else {
                        $rowNum = 'odd';
                    }

					//COLOR ROW BY STATUS!
					echo "<tr class=$rowNum>";
                    echo '<td>';
                    echo '<b>'.$rowBlocks['scope'].'</b><br/>';
                    echo '</td>';
                    echo '<td>';
                    echo '<b>'.$rowBlocks['category'].'</b><br/>';
                    echo '</td>';
                    echo '<td>';
                    echo '<b>'.$rowBlocks['nameShort'].'</b><br/>';
                    echo "<span style='font-size: 75%; font-style: italic'>".$rowBlocks['name'].'</span>';
                    echo '</td>';
                    echo '<td>';
                    echo getYearGroupsFromIDList($guid, $connection2, $rowBlocks['tawasulYearGroupIDList']);
                    echo '</td>';
                    echo '<td>';
                    echo "<script type='text/javascript'>";
                    echo '$(document).ready(function(){';
                    echo "\$(\".description-$count\").hide();";
                    echo "\$(\".show_hide-$count\").fadeIn(1000);";
                    echo "\$(\".show_hide-$count\").click(function(){";
                    echo "\$(\".description-$count\").fadeToggle(1000);";
                    echo '});';
                    echo '});';
                    echo '</script>';
                    if ($rowBlocks['content'] != '') {
                        echo "<a title='".__('View Description')."' class='show_hide-$count' onclick='false' href='#'><img style='padding-left: 0px' src='".$session->get('absoluteURL').'/themes/'.$session->get('tawasulThemeName')."/img/page_down.png' alt='".__('Show Comment')."' onclick='return false;' /></a>";
                    }
                    echo '</td>';
                    echo '</tr>';
                    if ($rowBlocks['content'] != '') {
                        echo "<tr class='description-$count' id='description-$count'>";
                        echo '<td colspan=6>';
                        echo $rowBlocks['content'];
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tr>';

                    ++$count;
                }
                echo '</table>';
            }

            echo '</div>';
            echo '</div>';
        }
    }
}
