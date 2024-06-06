<?php

namespace PhelixJuma\GUIFlow\Tests\Utils;

use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PhelixJuma\GUIFlow\Utils\DataJoiner;
use PhelixJuma\GUIFlow\Utils\Filter;
use PHPUnit\Framework\TestCase;

class DataJoinerTest extends TestCase
{

    public function _testJoinFunction()
    {

        $data = json_decode('[{"purchase_order_number":"P020669395","order_date":"2024-06-03 00:00:00","ordered_by_name":"","customer_name":"NAIVAS LTD","customer_email":"","customer_phone":"","delivery_location":"WATERFRONT KAREN","delivery_date":"2024-06-04 00:00:00","seller_name":"DPL FESTIVE LTD","items":[{"description":"FESTIVE COFFEE QUEEN CAKE 250G 6 Pieces","unit_of_measure":[{"selling_quantity":"6","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"100","total_price":"600"},{"description":"FESTIVE ORANGE QUEEN CAKE 250G 6 Pieces","unit_of_measure":[{"selling_quantity":"6","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"100","total_price":"600"},{"description":"FESTIVE CHOCOLATE RING CAKE 90G","unit_of_measure":[{"selling_quantity":"13","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"34.48","total_price":"448.28"},{"description":"FESTIVE VANILLA RING CAKE 90G","unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"34.48","total_price":"413.79"},{"description":"FESTIVE STRAWBERRY RING CAKE 90G","unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"34.48","total_price":"413.79"},{"description":"FESTIVE TORTILLA WRAP 12 Pieces","unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"177.15","total_price":"2125.76"},{"description":"FESTIVE STRAWBERRY QUEEN CAKE 250G 6 Pieces","unit_of_measure":[{"selling_quantity":"6","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"100","total_price":"600"},{"description":"FESTIVE CHOCOLATE POUND CAKE 400G","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"127.59","total_price":"1275.86"},{"description":"FESTIVE BLUEBERRY POUND CAKE 400G","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"134.48","total_price":"1344.83"},{"description":"FESTIVE COFFEE POUND CAKE 400G","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"134.48","total_price":"1344.83"},{"description":"FESTIVE WHOLE BREAD 800GM","unit_of_measure":[{"selling_quantity":"7","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"115.82","total_price":"810.74"},{"description":"FESTIVE MILKY WHITE BREAD 800G","unit_of_measure":[{"selling_quantity":"56","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"115.82","total_price":"6485.92"},{"description":"FESTIVE WHITE BREAD 400GM","unit_of_measure":[{"selling_quantity":"2","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"56.96","total_price":"113.92"},{"description":"FESTIVE P\/SLICED B\/BURN 6 Pieces SIM","unit_of_measure":[{"selling_quantity":"6","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"94.13","total_price":"564.78"},{"description":"FESTIVE MILKY LOAF 400GM","unit_of_measure":[{"selling_quantity":"24","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"58.5","total_price":"1404"},{"description":"FESTIVE WHITE FAMILY BREAD 600","unit_of_measure":[{"selling_quantity":"64","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"89.37","total_price":"5719.68"},{"description":"FESTIVE LONG ROLLS SLICED 350G","unit_of_measure":[{"selling_quantity":"2","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"104.94","total_price":"209.88"},{"description":"FESTIVE FINEST S\/SEEDED 400G","unit_of_measure":[{"selling_quantity":"2","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"94.13","total_price":"188.26"},{"description":"FESTIVE P\/PLAIN B\/BURN 6 Pieces","unit_of_measure":[{"selling_quantity":"4","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"86.9","total_price":"347.59"},{"description":"FESTIVE WHOLEMEAL BREAD 400GM","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"58.5","total_price":"585"},{"description":"FESTIVE MILKY WHITE BREAD 1.5K PCS","unit_of_measure":[{"selling_quantity":"3","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"217.94","total_price":"653.82"},{"description":"FESTIVE BROWN BREAD 1.5KG","unit_of_measure":[{"selling_quantity":"2","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"217.94","total_price":"435.88"},{"description":"FESTIVE DELUXE ROOLS 6 Pieces","unit_of_measure":[{"selling_quantity":"3","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"86.9","total_price":"260.69"},{"description":"FESTIVE FAMILY BROWN BREAD 600","unit_of_measure":[{"selling_quantity":"24","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"89.37","total_price":"2144.88"},{"description":"FESTIVE NATURES GOLD WHT 400GM","unit_of_measure":[{"selling_quantity":"4","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"58.5","total_price":"234"},{"description":"FESTIVE SUB ROLLS SLICED 350G","unit_of_measure":[{"selling_quantity":"3","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"104.94","total_price":"314.82"},{"description":"FESTIVE TAMU TAMU BREAD 400GM","unit_of_measure":[{"selling_quantity":"2","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"58.5","total_price":"117"},{"description":"FESTIVE ZERO-WHITE SUGAR FREE 400GM","unit_of_measure":[{"selling_quantity":"4","selling_unit":"Pieces"}],"pack_configuration":{"pack_size":"","unit_size":""},"unit_price":"58.5","total_price":"234"}],"currency":"KES","total_amount":"31760.49","vat_no":""},{"purchase_order_number":"P020669395","order_date":"2024-06-03 00:00:00","ordered_by_name":"","customer_name":"NAIVAS LTD","customer_email":"","customer_phone":"","delivery_location":"WATERFRONT KAREN","delivery_date":"2024-06-04 00:00:00","seller_name":"DPL FESTIVE LTD","items":[],"currency":"","total_amount":"0","vat_no":""}]', JSON_FORCE_OBJECT);

        $joinPaths = ['items'];

        $condition = [
            'operator'      => 'AND',
            'conditions'    => [
                [
                    'path' => 'customer_name',
                    'operator' => '==',
                ],
                [
                    'operator' => 'OR',
                    'conditions' => [
                        [
                            'path' => 'purchase_order_number',
                            'operator' => '==',
                        ],
                        [
                            'path' => 'order_date',
                            'operator' => '==',
                        ]
                    ]
                ]
            ]
        ];

        $dataJoiner = new DataJoiner($data, $joinPaths, $condition);

        $mergedData = $dataJoiner->mergeData();

        //print_r($mergedData);

        $expectedData = [];

        //$action = new FunctionAction("", [$this, 'split'], ['split_path' => "products",'criteria_path' => "products.*.brand"]);

        //$action->execute($data);

        //print_r($data);

        //$this->assertEquals($mergedData, $expectedData);
    }

}
