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

    public function _testQuantityValidationFunction()
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
            ],
            [
                'description' => 'item 5',
                'unit_of_measure' => [
                    [
                        'quantity' => 100,
                        'unit_of_measure' => 'PCS'
                    ]
                ],
                'unit_price' => 110.5,
                'total_price' => 331.5
            ],
            [
                'description' => 'item 6',
                'unit_of_measure' => [
                    [
                        'quantity' => 600,
                        'unit_of_measure' => 'PCS'
                    ]
                ],
                'unit_price' => 102,
                'total_price' => 611.99
            ],
            [
                'description' => 'item 6',
                'unit_of_measure' => [
                    [
                        'quantity' => 100,
                        'unit_of_measure' => 'PCS'
                    ]
                ],
                'unit_price' => 288.01,
                'total_price' => 2880.05
            ],
            [
                'description' => 'item 7',
                'unit_of_measure' => [
                    [
                        'quantity' => 500,
                        'unit_of_measure' => 'PCS'
                    ]
                ],
                'unit_price' => 38.25,
                'total_price' => 191.23
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

    public function _testDataStructureFunction()
    {

        $items = [
            "customer_name" => "Phelix",
            "items" => [
                [
                    "quantity" => 10
                ]
            ],
            "delivery_date" => "223",
            "products" => [
                [
                    "description" => "item 1",
                    "unit_of_measure" => [
                        [
                            "quantity" => 4,
                            "unit" => "pcs"
                        ]
                    ]
                ],
                [
                    "description" => "item 2",
                    "unit_of_measure" => [
                        [
                            "quantity" => "8",
                            "unit" => "pcs"
                        ]
                    ]
                ]
            ]
        ];

        $validations = [
            [
                "path"  => "customer_name",
                "rules" => [
                    DataValidator::VALIDATION_RULE_PATH_EXISTS
                ]
            ],
            [
                "path"  => "delivery_location",
                "rules" => [
                    DataValidator::VALIDATION_RULE_PATH_EXISTS
                ]
            ],
            [
                "path"  => "delivery_date",
                "rules" => [
                    DataValidator::VALIDATION_RULE_IS_DATE
                ]
            ],
            [
                "path"  => "products.*.description",
                "rules" => [
                    DataValidator::VALIDATION_RULE_PATH_EXISTS
                ]
            ],
            [
                "path"  => "products.*.uom.quantity",
                "rules" => [
                    DataValidator::VALIDATION_RULE_PATH_EXISTS, DataValidator::VALIDATION_RULE_IS_NOT_EMPTY, DataValidator::VALIDATION_RULE_IS_NUMERIC
                ]
            ],
            [
                "path"  => "products.*.unit_of_measure.*.quantity",
                "rules" => [
                    DataValidator::VALIDATION_RULE_IS_NON_ZERO_NUMBER
                ]
            ],
            [
                "path"  => "products.*.unit_of_measure.0",
                "rules" => [
                    DataValidator::VALIDATION_RULE_IS_DICTIONARY
                ]
            ]
        ];

        $validationResponse = DataValidator::validateDataStructure($items, $validations, true);

        //print_r($validationResponse);

        $expectedData = [];

        //$this->assertEquals($mergedData, $expectedData);
    }

}
