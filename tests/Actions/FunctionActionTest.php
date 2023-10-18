<?php

namespace PhelixJuma\DataTransformer\Tests\Actions;

use PhelixJuma\DataTransformer\Actions\FunctionAction;
use PhelixJuma\DataTransformer\Actions\SetValueAction;
use PhelixJuma\DataTransformer\Utils\Filter;
use PHPUnit\Framework\TestCase;

class FunctionActionTest extends TestCase
{
    public function _testSet()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100]
            ],
        ];
        $expectedData = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100]
            ],
            'delivery_date' => '2023-09-04'
        ];

        $valueMapping = [
            'Nairobi' => '2023-09-04',
            'Kisumu' => '2023-09-04',
            'Mombasa' => '2023-09-04',
        ];

        $action = new SetValueAction("delivery_date", null, "location.region", $valueMapping);

        $action->execute($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testSetStaticValue()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
            ],
        ];
        $expectedData = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
            ],
            'total_unit_price' => 500
        ];

        $action = new FunctionAction("total_unit_price", [$this, 'summate'], [['path' => 'products.*.unit_price']]);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testFilterFunction()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200],
            ],
        ];
        $criteria = [
            "operator"      => "OR",
            "conditions"    => [
                ['term' => ['path' => 'products.0.name'], 'mode' => Filter::EQUAL, 'key' => 'name'],
                [
                    "operator" => "AND",
                    "conditions"    => [
                        ['term' => ['path' => 'products.0.name'], 'mode' => Filter::CONTAINS, 'key' => 'name'],
                        ['term' => 200, 'mode' => Filter::GREATER, 'key' => 'unit_price'],
                    ]
                ]
            ]
        ];

        $expectedData = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
            ],
            'total_unit_price' => 500
        ];

        $action = new FunctionAction("products", [$this, 'filter'], ['filter_criteria' => $criteria], 'filtered_products');

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testStrToUpperFunction()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200],
            ],
        ];

        $expectedData = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
            ],
            'total_unit_price' => 500
        ];

        $action = new FunctionAction("products.0.name", [$this, 'strtoupper'], [], 'first_product');

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testDateFunction()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Nyalenda',
                'region' => 'Kisumu'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200],
            ],
            "delivery_schedule" => [
                [
                    "region" => "Nairobi",
                    "schedule" => ["tomorrow"]
                ],
                [
                    "region" => "Kisumu",
                    "schedule" => ["next monday", "next thursday"]
                ],
                [
                    "region" => "Embu",
                    "schedule" => ["next tuesday", "next friday"]
                ]
            ]
        ];

        $criteria = ['term' => ['path' => "location.region"], 'mode' => Filter::EQUAL, 'key' => 'region'];

        $expectedData = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Nyalenda',
                'region' => 'Kisumu'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
            ],
            'total_unit_price' => 500
        ];

        $action = new FunctionAction("delivery_schedule", [$this, 'filter'], ["filter_criteria" => $criteria], 'customer_delivery_schedule');

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testDateFromScheduleFunction()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200],
            ],
            'customer_delivery_schedule'    => [
                [
                    'region'    => 'Nairobi',
                    'schedule'  => [
                        ['planned_delivery' => 'next monday'],
                        ['planned_delivery' => 'next thursday'],
                    ]
                ]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("customer_delivery_schedule.0.schedule.*.planned_delivery", [$this, 'strtotime'], [], "customer_delivery_schedule.0.schedule.*.planned_delivery_date");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testSortDeliveryDatesFunction()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200],
            ],
            'customer_delivery_schedule'    => [
                [
                    'region'    => 'Nairobi',
                    'schedule'  => [
                        ['planned_delivery' => 'next thursday', 'planned_delivery_date' => 1695254400],
                        ['planned_delivery' => 'next monday', 'planned_delivery_date' => 1694995200],
                    ]
                ]
            ],
            "current_date" => strtotime("today")
        ];

        $expectedData = [];

        $action = new FunctionAction("customer_delivery_schedule.0.schedule", [$this, 'sort_multi_by_key'], ['key' => 'planned_delivery_date', 'order' => 'asc'], "sorted_delivery_dates");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testGetNextDeliveryDateFromSortedDeliveryDatesFunction()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200],
            ],
            'customer_delivery_schedule'    => [
                [
                    'region'    => 'Nairobi',
                    'schedule'  => [
                        ['planned_delivery' => 'next thursday', 'planned_delivery_date' => 1695254400],
                        ['planned_delivery' => 'next monday', 'planned_delivery_date' => 1694995200],
                    ]
                ]
            ],
            'sorted_delivery_dates' => [
                ['planned_delivery' => 'next monday', 'planned_delivery_date' => 1694995200],
                ['planned_delivery' => 'next thursday', 'planned_delivery_date' => 1695254400],
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("sorted_delivery_dates.0.planned_delivery_date", [$this, 'format_date'], ['format' => 'Y-m-d'], "promised_delivery_date");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testConcatFunction()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'description' => 'Capon 1.2', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'description' => 'frozen', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'description' => ' sold in pieces', 'quantity' => 5, 'unit_price' => 200],
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("location.address", [$this, 'concat'], ["string1" => " - New"], 'location.full_address');

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    /**
     * @return void
     */
    public function _testMultiConcatFunction()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'description' => 'Capon 1.2', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'description' => 'frozen', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'description' => ' sold in pieces', 'quantity' => 5, 'unit_price' => 200],
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("products", [$this, 'concat_multi_array_assoc'], ['fields' => ['name', 'description'], 'newField' => 'search_string']);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testUnitConversion()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'description' => 'Capon 1.2', 'quantity' => 12, 'unit_price' => 200, "from_unit" => "PIECES", "to_unit" => "KGS"],
                ['name' => 'Chicken Sausages', 'description' => 'frozen', 'quantity' => 15, 'unit_price' => 300, "from_unit" => "PIECES", "to_unit" => "KGS"],
                ['name' => 'Chicken Sausages 500g', 'description' => ' sold in pieces', 'quantity' => 5, 'unit_price' => 200, "from_unit" => "PIECES", "to_unit" => "KGS"],
            ],
            "unit_conversion_matrix" => [
                ["from" => "PIECES", "to" => "KGS", "factor" => 6],
                ["from" => "PKT", "to" => "KGS", "factor" => 0.5]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("unit_conversion_matrix", [$this, 'convert_unit_multi'], ['products' => ['path' => 'products']], "products");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testSplitFunction()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200, "brand" => "Kenchic"],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300, "brand" => "Kenchic"],
                ['name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200, "brand" => "kenmeat"],
            ],
        ];

        //$data = json_decode('{"purchase_order_number":"PO-2023-09-28-11-48-09","order_date":"2023-09-28 11:48:09","customer_name":{"original_value":"Aggymart Supermarket","matched_value":{"Customer Code":"C-20008","BPNAME":"Aggymart Supermarket","Customer Type":"Supermarket"},"similarity":"99.256816414004"},"customer_email":"","customer_phone":"","delivery_location":"Aggymart Supermarket","delivery_date":"2023-09-28 11:48:09","seller_name":"","requested_by_name":"","requested_by_phone":"","requested_by_email":"","items":[{"original_value":{"name":"Blue band 1kg","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Pieces"}],"unit_price":"100","total_price":"10000","number_of_pieces":"100","number_of_cases":""},"matched_value":{"ItemName":"Blue Band Original-1kg","ItemCode":"UP00006","UPC":"12","Scale":"1","UOM":"Kg","PrincipalCode":"UP"},"similarity":"95.374464125392"},{"original_value":{"name":"Blue band 500g","unit_of_measure":[{"selling_quantity":"300","selling_unit":"Pieces"}],"unit_price":"300","total_price":"90000","number_of_pieces":"300","number_of_cases":""},"matched_value":{"ItemName":"Blue Band Original-500g","ItemCode":"UP00005","UPC":"24","Scale":"500","UOM":"Grams","PrincipalCode":"UP"},"similarity":"95.686883549906"},{"original_value":{"name":"Blue band 250g","unit_of_measure":[{"selling_quantity":"300","selling_unit":"Pieces"}],"unit_price":"300","total_price":"90000","number_of_pieces":"300","number_of_cases":""},"matched_value":{"ItemName":"Blue Band Original-250g","ItemCode":"UP00004","UPC":"48","Scale":"250","UOM":"Grams","PrincipalCode":"UP"},"similarity":"95.737936524197"},{"original_value":{"name":"Blu band 100g","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Pieces"}],"unit_price":"100","total_price":"10000","number_of_pieces":"100","number_of_cases":""},"matched_value":{"ItemName":"Blue Band Original-100g","ItemCode":"UP00003","UPC":"48","Scale":"100","UOM":"Grams","PrincipalCode":"UP"},"similarity":"92.372622912911"},{"original_value":{"name":"Ribena cordial 1ltr","unit_of_measure":[{"selling_quantity":"500","selling_unit":"Milliliters"}],"unit_price":"500","total_price":"250000","number_of_pieces":"","number_of_cases":""},"matched_value":{"ItemName":"Ribena Cordial 1l Pet Cordial","ItemCode":"SUN00036","UPC":"6","Scale":"1","UOM":"L","PrincipalCode":"SUN"},"similarity":"94.629886295685"},{"original_value":{"name":"Ribena cordial 500ml","unit_of_measure":[{"selling_quantity":"700","selling_unit":"Milliliters"}],"unit_price":"700","total_price":"490000","number_of_pieces":"","number_of_cases":""},"matched_value":{"ItemName":"Ribena Cordial 50cl Pet Cordial","ItemCode":"SUN00035","UPC":"12","Scale":"50","UOM":"Cl","PrincipalCode":"SUN"},"similarity":"92.621840295737"},{"original_value":{"name":"Ribena B 12*Ltr","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Liters"}],"unit_price":"100","total_price":"1200000","number_of_pieces":"","number_of_cases":""},"matched_value":{"ItemName":"Ribena Rtd Bc 1l Tet X12","ItemCode":"SUN00028","UPC":"12","Scale":"1","UOM":"L","PrincipalCode":"SUN"},"similarity":"91.85399415946"},{"original_value":{"name":"Ribena S 12*Ltr","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Liters"}],"unit_price":"100","total_price":"1200000","number_of_pieces":"","number_of_cases":""},"matched_value":{"ItemName":"Ribena Rtd Bc 1l Tet X12","ItemCode":"SUN00028","UPC":"12","Scale":"1","UOM":"L","PrincipalCode":"SUN"},"similarity":"92.523158966724"},{"original_value":{"name":"Lucazade 12*Ltr","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Liters"}],"unit_price":"100","total_price":"1200000","number_of_pieces":"","number_of_cases":""},"matched_value":{"ItemName":"Lucozade Boost Buzz 1l Pet X12","ItemCode":"SUN00020","UPC":"12","Scale":"1","UOM":"L","PrincipalCode":"SUN"},"similarity":"91.677946293234"}],"currency":"KES","total_amount":"6000000","customers_list":"","products_list":""}', JSON_FORCE_OBJECT);

        $expectedData = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
            ],
            'total_unit_price' => 500
        ];

        $action = new FunctionAction("", [$this, 'split'], ['split_path' => "products",'criteria_path' => "products.*.brand"]);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testPregReplace()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['code' => "DEL001", 'name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200, "brand" => "Kenchic"],
                ['code' => "DEL002",'name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300, "brand" => "Kenchic"],
                ['code' => "PIL003", 'name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200, "brand" => "kenmeat"],
            ],
        ];

        $expectedData = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
            ],
            'total_unit_price' => 500
        ];

        $action = new FunctionAction("products.*.code", [$this, 'custom_preg_replace'], ['pattern' => "/^([A-Z]{3}).*$/", "replacement" => "$1"], 'products.*.new_code');

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testArrayFind()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                [
                    'original_value' => [
                        'name' => 'Capon Chicken',
                        'unit_of_measure' => [
                            [
                                'selling_quantity' => 2,
                                'selling_unit'    => 'Pieces'
                            ]
                        ],
                        'unit_price' => 200
                    ]
                ],
                [
                    'original_value' => [
                        'name' => 'Chicken Sausages',
                        'unit_of_measure' => [
                            [
                                'selling_quantity' => 3,
                                'selling_unit'    => 'Cases'
                            ]
                        ],
                        'unit_price' => 300
                    ]
                ],
            ],
        ];

        $expectedData = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                [
                    'original_value' => [
                        'name' => 'Capon Chicken',
                        'unit_of_measure' => [
                          [
                              'selling_quantity' => 2,
                              'selling_unit'    => 'Pieces'
                          ]
                        ],
                        'unit_price' => 200
                    ]
                ],
                [
                    'original_value' => [
                        'name' => 'Chicken Sausages',
                        'unit_of_measure' => [
                            [
                                'selling_quantity' => 3,
                                'selling_unit'    => 'Pieces'
                            ]
                        ],
                        'unit_price' => 300
                    ]
                ],
            ],
            'total_unit_price' => 500
        ];

        $action = new FunctionAction("products.*.original_value.unit_of_measure", [$this, 'assoc_array_find'], ['condition_field' => "selling_unit", "condition_operator" => "similar_to", "condition_value" => "Pieces - PCS", "condition_threshold" => 80, "condition_tokenize" => false, "return_key" => "selling_quantity"], 'products.*.original_value.number_of_pieces');

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testArrayFindValueFromPath()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                [
                    'original_value' => [
                        'name' => 'Capon Chicken',
                        'unit_of_measure' => [
                            [
                                'selling_quantity' => 2,
                                'selling_unit'    => 'Pieces'
                            ]
                        ],
                        'unit_price' => 200
                    ]
                ],
                [
                    'original_value' => [
                        'name' => 'Chicken Sausages',
                        'unit_of_measure' => [
                            [
                                'selling_quantity' => 3,
                                'selling_unit'    => 'Cases'
                            ]
                        ],
                        'unit_price' => 300
                    ]
                ],
            ],
            "principal_code" => "WEET",
            "brands" => [
                ["name" => "Weetabix", "acc" => "WEET"],
                ["name" => "UPFIELD", "acc" => "WEET"],
            ]
        ];

        $expectedData = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => []
        ];

        $action = new FunctionAction("brands", [$this, 'assoc_array_find'], ['condition_field' => "acc", "condition_operator" => "==", "condition_value" => ["path" => "principal_code"], "condition_threshold" => 80, "condition_tokenize" => false, "return_key" => ""], 'brand_details');

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testModelMapping()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                [
                    'original_value' => [
                        'name' => 'Capon Chicken',
                        'unit_of_measure' => [
                            [
                                'selling_quantity' => 2,
                                'selling_unit'    => 'Pieces'
                            ]
                        ],
                        'unit_price' => 200
                    ]
                ],
                [
                    'original_value' => [
                        'name' => 'Chicken Sausages',
                        'unit_of_measure' => [
                            [
                                'selling_quantity' => 3,
                                'selling_unit'    => 'Cases'
                            ]
                        ],
                        'unit_price' => 300
                    ]
                ],
            ],
        ];

        $expectedData = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                [
                    'original_value' => [
                        'name' => 'Capon Chicken',
                        'unit_of_measure' => [
                            [
                                'selling_quantity' => 2,
                                'selling_unit'    => 'Pieces'
                            ]
                        ],
                        'unit_price' => 200
                    ]
                ],
                [
                    'original_value' => [
                        'name' => 'Chicken Sausages',
                        'unit_of_measure' => [
                            [
                                'selling_quantity' => 3,
                                'selling_unit'    => 'Pieces'
                            ]
                        ],
                        'unit_price' => 300
                    ]
                ],
            ],
            'total_unit_price' => 500
        ];

        $action = new FunctionAction("", [$this, 'model_mapping'], ['model' => ["customer" => "customer", "region" => "location.region", "products.*.name" => "products.*.original_value.name",
            "products.*.unit_price" => "products.*.original_value.unit_price"]], "");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testAbs()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                [
                    'name' => 'Capon Chicken',
                    'unit_of_measure' => [
                        [
                            'selling_quantity' => "-2",
                            'selling_unit'    => 'Cases'
                        ],
                        [
                            'selling_quantity' => 1,
                            'selling_unit'    => 'Pieces'
                        ]
                    ],
                    'unit_price' => -200
                ],
                [
                    'name' => 'Chicken Sausages',
                    'unit_of_measure' => [
                        [
                            'selling_quantity' => 3,
                            'selling_unit'    => 'Cases'
                        ]
                    ],
                    'unit_price' => 300
                ],
            ],
        ];

        $expectedData = [];

        $action = new FunctionAction("products.*.unit_of_measure.*.selling_quantity", [$this, "transform"], ["abs", "args" => [], "target_keys" => []]);

        //$response = Utils::transform_data($data['products'], "abs", [], ['selling_quantity']);
        //print_r($response);
        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function testPregReplace()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                [
                    'name' => 'Capon Chicken -700G',
                    'unit_of_measure' => [
                        [
                            'selling_quantity' => "-2",
                            'selling_unit'    => 'Cases'
                        ],
                        [
                            'selling_quantity' => 1,
                            'selling_unit'    => 'Pieces'
                        ]
                    ],
                    'unit_price' => -200
                ],
                [
                    'name' => 'Chicken Sausages',
                    'unit_of_measure' => [
                        [
                            'selling_quantity' => 3,
                            'selling_unit'    => 'Cases'
                        ]
                    ],
                    'unit_price' => 300
                ],
            ],
        ];

        $expectedData = [];

        $action = new FunctionAction("products.*.name", [$this, "transform"], ["preg_replace", "args" => ["pattern" => "/-(?=\S)/", "replacement" => "- "], "target_keys" => []]);

        $action->execute($data);

        print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testValueMapping()
    {
        $data =
            ['customer' => 'Naivas',
                'location' => [
                    'address' => 'Kilimani',
                    'region' => 'Nairobi'
                ],
                'products' => [
                    ["ItemName" => "Luc Boost Buzz 1l Tet X12"],
                    ["ItemName" => "Radiant Hair Shampoo Strawberry 1l New"],
                    ["ItemName" => "Pride Liquid 1l"],
                    ["ItemName" => "Tropikal Tc Citrus 1 Ltr Free Gift"],
                    ["ItemName" => "Fruit Pardise Tp 1 Ltr"],
                    ["ItemName" => "CERES SEASON'S TREASURES JUICE 1L-"],
                    ["ItemName" => "BOKOMO N/SOURCE SUPER QUICK MORNING OATS 500G"],
                ]
            ];

        $expectedData = [];

        $valueMapping = [
            'Luc Boost Buzz 1l Tet X12' => 'Lucozade Boost Buzz 1l Pet',
            'Bokomo N/SOURCE SUPER QUICK MORNING OATS 500G' => "Bokomo Nature's Source Super Quick Morning Oats 500g"
        ];

        $action = new FunctionAction("products.*.ItemName", [$this, "transform"], ["dictionary_mapper", "args" => ["mappings" => $valueMapping], "target_keys" => []]);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testRegexMapper()
    {
        $data =
            [
                'customer' => 'Naivas',
                'location' => [
                    'address' => 'Kilimani',
                    'region' => 'Nairobi'
                ],
                'products' => [
                    ["ItemName" => "Ribena Cordial 50cl Pet Cordial"],
                    ["ItemName" => "Ribena Dil Bc 300ml Gla X12"],
                    ["ItemName" => "Ribena Dil Bc 600ml Gla X12"],
                    ["ItemName" => "Ribena Rtd Bc 1l Tet X12"],
                    ["ItemName" => "Ribena Rtd Bc 250ml Tet X24"],
                    ["ItemName" => "Ribena Rtd Bc&S/Berry 1l Tet X12"],
                    ["ItemName" => "Ribena Rtd Bc&S/Berry 250ml Tet X24"],
                    ["ItemName" => "Rib Rtd Bc 25cl Pet 2x24"],
                    ["ItemName" => "Rib Rtd Bc 50cl Pet X12"],
                    ["ItemName" => "Rib Spark Bc Can Regular 325ml X24"],
                    ["ItemName" => "Ribena Cordial 1l Pet Cordial"],
                    ["ItemName" => "Grape Blackberry-250ml"],
                    ["ItemName" => "Lucozade Boost Buzz 1l Pet X12"],
                    ["ItemName" => "Lucozade Nrg Boost 250ml Tet X24"],
                    ["ItemName" => "Lucozade Nrg Original 300ml Gla X12"],
                    ["ItemName" => "Lucozade Nrg Original 600ml Gla X12"],
                    ["ItemName" => "WEETABIX STANDARD - 48 PCS-210GMS"],
                    ["ItemName" => "WEETABIX JUMBO OATS 500G"],
                    ["ItemName" => "WEETABIX JUMBO OATS 1KG"],
                    ["ItemName" => "WEETABIX MINIS - CHOCOLATE 450G"],
                    ["ItemName" => "WEETABIX JUMBO OATS 500G"],
                    ["ItemName" => "CERES PASSION FRUIT JUICE 1L"],
                    ["ItemName" => "CERES CRANBERRY/KIWI JUICE 1L -"]
                ]
            ];

        $mapping = [
            "Dil"    => "Diluted",
            "Bc"    => "Black Currant",
            "Rtd"    => "Ready to Drink",
            "Nrg"    => "Energy",
            "S/Berry"   => "Straw Berry",
            "Rib"   => "Ribena",
            "Luc"   => "Lucozade",
            "Gla" => "",
            "Tet" => "",
            "X12" => "",
            "X24" => "",
            "Botdbke" => "",
            "n/source" => "Nature's Source"
        ];

        $data = [
            'items' => [
                [
                    'description' => [
                        'meta_data' => [
                            'other_details' => [
                                'Shipping_Address' => 'NAIVAS JUJA SHOP-JUJA SH'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $mapping = [
            "(?:s|sh|sho|shop)" => "Shops",
            "(?:d|de|del|deli)" => "Delis",
            "(?:b|bu|but|butc|butch|butche|butcher|butchery)" => "Butchery"
        ];

        $expectedData = [];

        $action = new FunctionAction("items.*.description.meta_data.other_details.Shipping_Address", [$this, "transform"], ["regex_mapper", "args" => ["mappings" => $mapping, "isCaseSensitive" => false], "target_keys" => []]);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testExtractOne()
    {

        $data = [
            'items' => [
                [
                    'description' => [
                        'meta_data' => [
                            'other_details' => [
                                'Shipping_Address' => 'NAIVAS JUJA Shop-JUJA Shop'
                            ]
                        ]
                    ]
                ],
                [
                    'description' => [
                        'meta_data' => [
                            'other_details' => [
                                'Shipping_Address' => 'NAIVAS JUJA Shop-JUJA Shop'
                            ]
                        ]
                    ]
                ],
                [
                    'description' => [
                        'meta_data' => [
                            'other_details' => [
                                'Shipping_Address' => 'NAIVAS NYERI Deli-NYERI Deli'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("items.*.description.meta_data.other_details.Shipping_Address", [$this, "fuzzy_extract_one"], ["choices" => ["Shop", "Deli", "Butchery"], 'min_score' => 50, 'default_choice' => "Shop", 'fuzzy_method' => 'tokenSetRatio'], "items.*.description.meta_data.other_details.Section");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testReducer()
    {

        $data = [
            'items' => [
                [
                    'description' => [
                        'meta_data' => [
                            'other_details' => [
                                'Shipping_Address' => 'NAIVAS JUJA Shop-JUJA Shop'
                            ]
                        ]
                    ]
                ],
                [
                    'description' => [
                        'meta_data' => [
                            'other_details' => [
                                'Shipping_Address' => 'NAIVAS JUJA Shop-JUJA Shop'
                            ]
                        ]
                    ]
                ],
                [
                    'description' => [
                        'meta_data' => [
                            'other_details' => [
                                'Shipping_Address' => 'NAIVAS NYERI Deli-NYERI Deli'
                            ]
                        ]
                    ]
                ]
            ],
            'sections' => ['Shop', 'Shop', 'Deli']
        ];

        $expectedData = [];

        $action = new FunctionAction("sections", [$this, "reducer"], ["reducer" => "modal_value", 'priority' => ['Shop' => 1, 'Deli' => 2, 'Butchery' => 3], "default" => 'Shop'], "section");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testJoinFunction()
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
            'operator' => 'AND',
            'conditions' => [
                [
                    'path' => 'location.address',
                    'operator' => '==',
                ],
                [
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
                ]
            ]
        ];

        $condition2 = [
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

        $expectedData = [];

        $action = new FunctionAction("", [$this, 'join'], ['join_paths' => $joinPaths, 'criteria' => $condition]);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testFuzzySearch()
    {
        $data = [
            'customer' => 'NAIVAS LIMITED NAIVAS CIATA SHOP-CIATA SHOP',
            'products' => [
                ['code' => "DEL001", 'name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200, "brand" => "Kenchic"],
                ['code' => "DEL002",'name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300, "brand" => "Kenchic"],
                ['code' => "PIL003", 'name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200, "brand" => "kenmeat"],
            ],
            "customers_list" => [
                ["name" => "NAIVAS CIATA- HENRY KIARIE", "id" => 1],
                ["name" => "NAIVAS DEVELOPMENT - JACOB SEKO LONZIE", "id" => 2],
                ["name" => "NAIVAS DIGO- DAVID MWAURA", "id" => 3],
                ["name" => "Quick Mart", "id" => 4],
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("customer", [$this, 'fuzzy_search'], ['corpus' => ["path" => "customers_list"], "corpus_search_key" => "name", "corpus_id_key" => "id", "master_data_type" => "customers", "similarity_threshold" => 20, 'number_of_matches' => 1, 'scorer' => 'tokenSetRatio']);

        $action->execute($data);

       // print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testFuzzyMatch()
    {
        $data = [
            'customer' => 'Naivas',
            'products' => [
                ['code' => "DEL001", 'name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200, "brand" => "Kenchic"],
                ['code' => "DEL002",'name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300, "brand" => "Kenchic"],
            ],
            "products_list" => [
                ["name" => "Capon 1.3", "id" => 1],
                ["name" => "Frozen Chicken Sausage 500 GMS", "id" => 2],
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("products", [$this, 'fuzzy_match'], ['search_key' => 'name', 'matching_key' => 'name','corpus' => ["path" => "products_list"], "corpus_search_key" => "name", "corpus_id_key" => "id", "master_data_type" => "all products", "similarity_threshold" => 20, 'number_of_matches' =>1 , 'scorer' => 'tokenSetRatio']);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }


    public function summate($data) {
        return array_sum($data);
    }

}
