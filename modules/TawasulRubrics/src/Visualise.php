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

namespace Tos\Module\TawasulRubrics;

use TawasulOS\Http\Url;
use TawasulOS\UI\Chart\Chart;

/**
 * Attendance display & edit class
 *
 * @version v18
 * @since   v18
 */
class Visualise
{
    protected $absoluteURL;

    protected $page;

    protected $guid;

    protected $tawasulPersonID;

    protected $columns;

    protected $rows;

    protected $cells;

    protected $contexts;

    /**
     * Constructor
     *
     * @version  v18
     * @since    v18
     * @return   void
     */
    public function __construct($absoluteURL, $page, $tawasulPersonID, array $columns, array $rows, array $cells, array $contexts)
    {
        $this->absoluteURL = $absoluteURL;

        $this->page = $page;

        $this->tawasulPersonID = $tawasulPersonID;

        $this->columns = $columns;

        $this->rows = $rows;

        $this->cells = $cells;

        $this->contexts = $contexts;
    }

    /**
     * renderVisualise
     *
     * @version  v18
     * @since    v18
     * @param   $legend should the legend be included?
     * @param   $image should the chart be saved as an image
     * @param   $path if image is saved, where should it be saved (defaults to standard upload location)
     * @param   $id optionally outputs the image path to the value of the given id
     * @return   void
     */
    public function renderVisualise($legend = true, $image = false, $path = '', $id = '')
    {
        //Filter out columns to ignore from visualisation
        $this->columns = array_filter($this->columns, function ($item) {
            return (isset($item['visualise']) && $item['visualise'] == 'Y');
        });

        if (!empty($this->columns) && !empty($this->cells)) {
            //Cycle through rows to calculate means
            $means = array() ;
            foreach ($this->rows as $row) {
                $means[$row['tawasulRubricRowID']]['title'] = $row['title'];
                $means[$row['tawasulRubricRowID']]['cumulative'] = 0;
                $means[$row['tawasulRubricRowID']]['denonimator'] = 0;

                //Cycle through cells, and grab those for this row
                $cellCount = 1 ;
                foreach ($this->cells[$row['tawasulRubricRowID']] as $cell) {
                    $visualise = false ;
                    foreach ($this->columns as $column) {
                        if ($column['tawasulRubricColumnID'] == $cell['tawasulRubricColumnID']) {
                            $visualise = true ;
                        }
                    }

                    if ($visualise) {
                        foreach ($this->contexts as $entry) {
                            if ($entry['tawasulRubricCellID'] == $cell['tawasulRubricCellID']) {
                                $means[$row['tawasulRubricRowID']]['cumulative'] += $cellCount;
                                $means[$row['tawasulRubricRowID']]['denonimator']++;
                            }
                        }
                        $cellCount++;
                    }
                }
            }

            $columnCount = count($this->columns);
            $data = array_map(function ($mean) use ($columnCount) {
                return !empty($mean['denonimator'])
                ? round((($mean['cumulative']/$mean['denonimator'])/$columnCount), 2)
                : 0;
            }, $means);

            $this->page->scripts->add('chart');

            $chart = Chart::create('visualisation'.$this->tawasulPersonID, 'polarArea')
                ->setLegend(['display' => $legend, 'position' => 'right'])
                ->setLabels(array_column($means, 'title'))
                ->setColorOpacity(0.6);

            $options = [
                'responsive' => 'true',
                'maintainAspectRatio' => 'true',
                'aspectRatio' => 2,
                'height' => '32vw',
                'scale'  => [
                    'min' => 0.0,
                    'max' => 1.0,
                    'ticks' => [
                        'callback' => $chart->addFunction('function(tickValue, index, ticks) {
                            return Number(tickValue).toFixed(1);
                        }'),
                    ],
                ]
            ];
            if ($image) {
                $ajaxUrl = $this->absoluteURL . '/modules/TawasulRubrics/rubrics_visualise_saveAjax.php';
                $options['animation'] = [
                    'duration' => 0,
                    'onComplete' => $chart->addFunction('function(e) {
                        var img = visualisation'.$this->tawasulPersonID.'.toDataURL("image/png");
                        $.ajax({
                            url: ' . json_encode($ajaxUrl) . ',
                            type: "POST",
                            data: {
                                img: img,
                                tawasulPersonID: '.json_encode($this->tawasulPersonID).',
                                path: '.json_encode($path).'
                            },
                            dataType: "html",
                            success: function (data) {
                                '.( $id ? '$("#'.$id.'").val(data);' : '' ).'
                            }
                        });
                        this.options.animation.onComplete = null;
                    }'),
                ];
            }
            $chart->setOptions($options);

            // Handle custom colours only if there is one unique colour per row
            $rowColours = array_unique(array_column($this->rows, 'backgroundColor'));
            if (count($rowColours) == count($this->rows)) {
                $chart->setColors($rowColours);
            }

            $chart->addDataset('rubric')->setData($data);

            return $chart->render();
        }
    }
}
