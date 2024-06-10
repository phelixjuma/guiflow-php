<?php

namespace PhelixJuma\GUIFlow\Tests\Utils;

use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PhelixJuma\GUIFlow\Utils\DataJoiner;
use PhelixJuma\GUIFlow\Utils\DataValidator;
use PhelixJuma\GUIFlow\Utils\Filter;
use PHPUnit\Framework\TestCase;

class DataValidatorTest extends TestCase
{

    public function testQuantityValidationFunction()
    {


        $items = [
            [
                'description' => 'item 1',
                'unit_of_measure' => [
                    [
                        'quantity' => 2,
                        'unit_of_measure' => 'PCS'
                    ]
                ],
                'unit_price' => 200,
                'total_price' => 400
            ],
            [
                'description' => 'item 2',
                'unit_of_measure' => [
                    [
                        'quantity' => 0,
                        'unit_of_measure' => 'PCS'
                    ]
                ],
                'unit_price' => 250,
                'total_price' => 500
            ],
            [
                'description' => 'item 3',
                'unit_of_measure' => [
                    [
                        'quantity' => 11,
                        'unit_of_measure' => 'PCS'
                    ]
                ],
                'unit_price' => 56,
                'total_price' => 56
            ],
            [
                'description' => 'item 4',
                'unit_of_measure' => [
                    [
                        'quantity' => 300,
                        'unit_of_measure' => 'PCS'
                    ]
                ],
                'unit_price' => 90,
                'total_price' => 720
            ]
        ];

        $correctedItems = DataValidator::validateAndCorrectQuantityUsingPrice($items, 'unit_of_measure.0.quantity', 'unit_price', 'total_price');

        print_r($correctedItems);

        $expectedData = [];

        //$action = new FunctionAction("", [$this, 'split'], ['split_path' => "products",'criteria_path' => "products.*.brand"]);

        //$action->execute($data);

        //print_r($data);

        //$this->assertEquals($mergedData, $expectedData);
    }

}
