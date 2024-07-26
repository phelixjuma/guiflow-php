<?php

namespace PhelixJuma\GUIFlow\Tests\Utils;

use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Conditions\SimpleCondition;
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
            "customer_name" => "Naivas Ltd",
            "items" => [
                [
                    "quantity" => 10
                ]
            ],
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

        $items = json_decode('{"purchase_order_number":"24026726","order_date":"2024-07-24","ordered_by_name":"PHILIP CAROLAN","customer_name":"CARREFOUR MARKET GARDEN CITY MAL","customer_email":"","customer_phone":"254728600531","delivery_location":"SM KEN NBO GARDEN CITY MALL","seller_name":"Kenchic","items":[{"serial_number":"1","item_bar_code":"","description":"NS FRESH CHICKEN BREAST BONELESS PK","unit_of_measure":[{"selling_quantity":"30","selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2024-07-29"}]},{"serial_number":"2","item_bar_code":"","description":"NS FRESH CHICKEN BREAST BONELESS PK","unit_of_measure":[{"selling_quantity":"301","selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2024-07-29"}]}],"pack_configuration":{"pack_size":"","unit_size":"0"},"currency":"KES","total_number_of_items":"2","total_number_of_extracted_items":"2","sub_total":"","total_amount":"","vat_no":""}', JSON_FORCE_OBJECT);

        $validations = [
            [
                "path"          => "purchase_order_number", "rules" => [DataValidator::VALIDATION_RULE_PATH_EXISTS],
                "description"   => "Purchase Order Number must be extracted and included in the response as per the schema"
            ],
            [
                "path"          => "customer_name",
                "rules"         => [DataValidator::VALIDATION_RULE_IS_NOT_EMPTY, ["operator" => "not similar_to", "value" => 'Kenchic', "similarity_threshold" => 80]],
                "description"   => "Customer Name cannot be empty and must not be similar to 'Kenchic'. Allowable entities are name of a business, business branch or person. If not found, set value to be same as 'ordered_by_name'"
            ],
            [
                "path"          => "delivery_location", "rules" => [DataValidator::VALIDATION_RULE_PATH_EXISTS],
                "description"   => "Delivery Location must be extracted and included in the response as per the schema"
            ],
            [
                "path"          => "delivery_date", "rules" => [DataValidator::VALIDATION_RULE_PATH_EXISTS, DataValidator::VALIDATION_RULE_IS_NOT_EMPTY, DataValidator::VALIDATION_RULE_IS_DATE],
                "description"   => "Delivery Date is mandatory and must be extracted and included in the response as per the instructions. It must also be a valid date in the format of YYY-MM-DD. First check for common phrases and tags indicating delivery date and if none exists, set it as the current date"
            ],
            [
                "path"          => "items", "rules" => [DataValidator::VALIDATION_RULE_IS_LIST],
                "description"   => "Items (list of products) must be an array as per the schema"
            ],
            [
                "path"          => "items.*.description", "rules" => [DataValidator::VALIDATION_RULE_IS_NOT_EMPTY],
                "description"   => "For every product/item extracted, the description cannot be empty"
            ],
            [
                "path"          => "items.*.unit_of_measure.*.selling_quantity", "rules" => [DataValidator::VALIDATION_RULE_IS_NUMERIC],
                "description"   => "For every product/item extracted, the selling quantity must be a numeric"
            ]
        ];

        $validationResponse = DataValidator::validateDataStructure($items, $validations, true);

        //print($validationResponse ? "True" : "False");
        print_r($validationResponse);

        $expectedData = [];

        //$this->assertEquals($mergedData, $expectedData);
    }

}
