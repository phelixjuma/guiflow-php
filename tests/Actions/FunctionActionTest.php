<?php

namespace PhelixJuma\DataTransformer\Tests\Actions;

use PhelixJuma\DataTransformer\Actions\FunctionAction;
use PhelixJuma\DataTransformer\Actions\SetValueAction;
use PhelixJuma\DataTransformer\Utils\Filter;
use PhelixJuma\DataTransformer\Utils\PathResolver;
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
                ['term' => 'capon chicken', 'mode' => Filter::EQUAL, 'key' => 'name'],
                [
                    "operator" => "AND",
                    "conditions"    => [
                        ['term' => 'sausages', 'mode' => Filter::CONTAINS, 'key' => 'name'],
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

        $action = new FunctionAction("location.address", [$this, 'concat'], ['region' => ['path' => 'location.region']], 'location.full_address');

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

    public function testSplitFunction()
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

        $action = new FunctionAction("", [$this, 'split'], ['path' => "products.*.brand"], '');

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

    public function testArrayFind()
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

        $action = new FunctionAction("products", [$this, 'assoc_array_find'], ['condition_field' => "name", "condition_operator" => "like", "condition_value" => "Capon", "name"], 'product_name');

        $action->execute($data);

        print_r($data);

        $this->assertEquals($data, $expectedData);
    }


    public function summate($data) {
        return array_sum($data);
    }

}
