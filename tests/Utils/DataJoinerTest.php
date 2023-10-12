<?php

namespace PhelixJuma\DataTransformer\Tests\Utils;

use PhelixJuma\DataTransformer\Actions\FunctionAction;
use PhelixJuma\DataTransformer\Utils\ConfigurationValidator;
use PhelixJuma\DataTransformer\Utils\DataJoiner;
use PhelixJuma\DataTransformer\Utils\Filter;
use PHPUnit\Framework\TestCase;

class DataJoinerTest extends TestCase
{

    public function __testJoinFunction()
    {
        $data = [
            [
                'customer' => 'Naivas',
                'order_number' => '009876',
                'location' => [
                    'address' => 'Kilimani',
                    'region' => 'Nairobi'
                ],
                'products' => [
                    ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200, "brand" => "Kenchic"],
                    ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300, "brand" => "Kenchic"],
                    ['name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200, "brand" => "kenmeat"],
                ]
            ],
            [
                'customer' => 'Tumaini',
                'order_number' => '019876',
                'location' => [
                    'address' => 'Kilimani',
                    'region' => 'Nairobi'
                ],
                'products' => [
                    ['name' => 'Chicken Sausages 100g', 'quantity' => 5, 'unit_price' => 200, "brand" => "kenmeat"],
                ]
            ],
            [
                'customer' => 'Naivas',
                'order_number' => '009876',
                'location' => [
                    'address' => 'Kilimani',
                    'region' => 'Nairobi'
                ],
                'products' => [
                    ['name' => 'Chicken Sausages 250g', 'quantity' => 5, 'unit_price' => 200, "brand" => "kenmeat"],
                ]
            ],
            [
                'customer' => 'QuickMart',
                'order_number' => '009876',
                'location' => [
                    'address' => 'Kilimani',
                    'region' => 'Nairobi'
                ],
                'products' => [
                    ['name' => 'Chicken Sausages 100g', 'quantity' => 5, 'unit_price' => 200, "brand" => "kenmeat"],
                ]
            ]
        ];


        $joinPaths = ['products'];

        $condition = [
            'operator' => 'OR',
            'conditions' => [
                [
                    'path' => 'customer',
                    'operator' => '==',
                ],
                [
                    'path' => 'order_number',
                    'operator' => '==',
                ]
            ]
        ];

//        $condition = [
//            'path' => 'customer',
//            'operator' => '==',
//        ];

        $dataJoiner = new DataJoiner($data, $joinPaths, $condition);

        $mergedData = $dataJoiner->mergeData();

        //print_r($mergedData);

        $expectedData = [];

        //$action = new FunctionAction("", [$this, 'split'], ['split_path' => "products",'criteria_path' => "products.*.brand"]);

        //$action->execute($data);

        //print_r($data);

        $this->assertEquals($mergedData, $expectedData);
    }

}
