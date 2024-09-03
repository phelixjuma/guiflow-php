<?php

namespace PhelixJuma\GUIFlow\Tests\Actions;

use Kuza\Krypton\Framework\Helpers\UtilsHelper;
use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Actions\SetValueAction;
use PhelixJuma\GUIFlow\Utils\DataSplitter;
use PhelixJuma\GUIFlow\Utils\Filter;
use PhelixJuma\GUIFlow\Utils\Helpers;
use PhelixJuma\GUIFlow\Utils\PathResolver;
use PhelixJuma\GUIFlow\Utils\Utils;
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
            'products' => [
                ["Sell_to_Customer_No" => "PC00070","No" => "PC03267", 'name' => 'CAPON FRESH BUTCHERY.', 'quantity' => 2, 'unit_price' => 200],
                ["Sell_to_Customer_No" => "PC00071","No" => "1300120", 'name' => 'CAPON FRESH BUTCHERY', 'quantity' => 3, 'unit_price' => 300],
                ["Sell_to_Customer_No" => "PC00072","No" => "PC00987", 'name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200],
            ],
            'customer_name' => [
                'meta_data' => [
                    'id' => 'PC00072'
                ]
            ]
        ];
        $criteria = [
            "operator"      => "AND",
            "conditions"    => [
                ['term' => ['path' => 'customer_name.meta_data.id'], 'mode' => '==', 'key' => 'Sell_to_Customer_No'],
                ['term' => 'PC', 'mode' => 'contains', 'key' => 'No'],
            ]
        ];

        $expectedData = [];

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
            ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200],
            ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
            ['name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200]
        ];

        $expectedData = [];

        $action = new FunctionAction("", [$this, 'sort_multi_by_key'], ['key' => 'name', 'order' => 'desc']);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testDateDiff()
    {
        $data = [
            "delivery_date" => "2023-11-18T13:44:00Z",
            "now"           => "2023-11-17T12:44:00Z",
        ];

        $expectedData = [];

        $action = new FunctionAction("now", [$this, 'date_diff'], ['target' => ['path' => "delivery_date"], 'period' => 'h'], 'days_to_delivery');

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
            ],
            "delivery_date" => "2023-11-03 11:00:09"
        ];

        $expectedData = [];

        $action = new FunctionAction("delivery_date", [$this, 'format_date'], ['format' => 'Y-m-d'], "delivery_date");

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
                'region' => ' '
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'description' => 'Capon 1.2', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'description' => 'frozen', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'description' => ' sold in pieces', 'quantity' => 5, 'unit_price' => 200],
            ],
            'days' => ['Monday', 'Tuesday']
        ];

        $expectedData = [];

        $action = new FunctionAction("", [$this, 'concat'], ["strings" => ["Phelix", ["path" => "customer"],["path" => "location.address"], ["path" => "location.region"]], "separator" => "", "enclosure" => "brackets"], 'full_address');

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

        $action = new FunctionAction("products", [$this, 'concat_multi_array_assoc'], ['fields' => ['name', 'description'], 'newField' => 'search_string', 'separator' => '', 'enclosure' => 'brackets']);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testAppendFunction()
    {
        $data = [
            'products' => [
                ['name' => 'Capon Chicken', 'description' => 'Capon 1.2', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'description' => 'frozen', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'description' => ' sold in pieces', 'quantity' => 5, 'unit_price' => 200],
            ],
            "name" => "Juma",
            "surname" => "Phelix"
        ];

        $expectedData = [];

        $action = new FunctionAction("name", [$this, 'append'], ["strings" => ['(For Phelix)'], "separator" => ""]);

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

    public function _testExpandListFunction()
    {

        $data = [[
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                [
                    'name' => 'KFC CHICKEN BREAST FILLETS',
                    'unit_of_measure' => [
                        ['selling_quantity' => 2, 'scheduled_delivery_date_or_day_of_week' =>'Thursday'],
                        ['selling_quantity' => 3, 'scheduled_delivery_date_or_day_of_week' =>'Saturday'],
                    ]
                ],
                [
                    'name' => 'KFC CHICKEN COB 9 PIECE',
                    'unit_of_measure' => [
                        ['selling_quantity' => 60, 'scheduled_delivery_date_or_day_of_week' =>'Tuesday'],
                        ['selling_quantity' => 60, 'scheduled_delivery_date_or_day_of_week' =>'Thursday'],
                        ['selling_quantity' => 60, 'scheduled_delivery_date_or_day_of_week' =>'Saturday'],
                    ]
                ],
            ],
            'total_unit_price' => 500
        ]];

        $data = json_decode('[{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC EASTLEIGH","customer_email":"","customer_phone":"","delivery_location":"EASTLEIGH","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"},{"selling_quantity":3,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-17"},{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"},{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":3,"description":"KFC CHICKEN NUGGETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":4,"description":"KFC CHICKEN STRIPS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"},{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":6,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":3,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"},{"selling_quantity":3,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]}],"currency":"","total_number_of_items":10,"total_number_of_extracted_items":10,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"KFC Galleria","customer_name":"KFC Galleria","customer_email":"","customer_phone":"","delivery_location":"KFC Galleria","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"},{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"},{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":3,"description":"KFC CHICKEN NUGGETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":4,"description":"KFC CHICKEN STRIPS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":6,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"},{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]}],"currency":"","total_number_of_items":10,"total_number_of_extracted_items":10,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC Garden City","customer_email":"","customer_phone":"","delivery_location":"Garden City","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-10"},{"selling_quantity":40,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-12"},{"selling_quantity":40,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-14"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-10"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-14"}]}],"currency":"","total_number_of_items":5,"total_number_of_extracted_items":5,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"KFC Junction","customer_name":"KFC Junction","customer_email":"","customer_phone":"","delivery_location":"KFC Junction","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":50,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"},{"selling_quantity":50,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-05"},{"selling_quantity":50,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":3,"description":"KFC CHICKEN NUGGETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":4,"description":"KFC CHICKEN STRIPS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"},{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-05"},{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]}],"currency":"","total_number_of_items":10,"total_number_of_extracted_items":10,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC KAKAMEGA","customer_email":"","customer_phone":"","delivery_location":"KAKAMEGA","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"},{"selling_quantity":25,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-04"}]}],"currency":"KES","total_number_of_items":3,"total_number_of_extracted_items":3,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC KIAMBU ROAD","customer_email":"","customer_phone":"","delivery_location":"KIAMBU ROAD","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"},{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":3,"description":"KFC CHICKEN NUGGETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":4,"description":"KFC CHICKEN STRIPS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"},{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":6,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]}],"currency":"","total_number_of_items":10,"total_number_of_extracted_items":10,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC LANGATA","customer_email":"","customer_phone":"","delivery_location":"KFC LANGATA","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-17"},{"selling_quantity":40,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"},{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":3,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-17"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":4,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]}],"currency":"","total_number_of_items":9,"total_number_of_extracted_items":9,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC Limuru Road","customer_email":"","customer_phone":"","delivery_location":"Limuru Road","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":40,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"},{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"},{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":3,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]}],"currency":"","total_number_of_items":5,"total_number_of_extracted_items":5,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC MACHAKOS","customer_email":"","customer_phone":"","delivery_location":"MACHAKOS","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":20,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-23"},{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-26"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-26"}]}],"currency":"KES","total_number_of_items":3,"total_number_of_extracted_items":3,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC Meru","customer_email":"","customer_phone":"","delivery_location":"Meru","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-25"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":70,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-25"}]},{"serial_number":3,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-25"}]}],"currency":"","total_number_of_items":3,"total_number_of_extracted_items":3,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC Mombasa CBD","customer_email":"","customer_phone":"","delivery_location":"Mombasa CBD","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]}],"currency":"KES","total_number_of_items":2,"total_number_of_extracted_items":2,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC NAKURU","customer_email":"","customer_phone":"","delivery_location":"NAKURU","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-06-15"}],"pack_configuration":[],"unit_price":0,"total_price":0},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":70,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-06-13"}],"pack_configuration":[],"unit_price":0,"total_price":0},{"serial_number":3,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-06-15"}],"pack_configuration":[],"unit_price":0,"total_price":0}],"currency":"","total_number_of_items":3,"total_number_of_extracted_items":3,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC NYALI","customer_email":"","customer_phone":"","delivery_location":"KFC NYALI","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-10"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":40,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-10"},{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-13"}]},{"serial_number":3,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-10"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-13"}]},{"serial_number":4,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-10"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-13"}]}],"currency":"","total_number_of_items":7,"total_number_of_extracted_items":7,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC THIKA","customer_email":"","customer_phone":"","delivery_location":"THIKA","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":50,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-23"},{"selling_quantity":50,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-27"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-23"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-27"}]}],"currency":"","total_number_of_items":4,"total_number_of_extracted_items":4,"sub_total":0,"total_amount":0,"vat_no":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"KFC WOODVALE","customer_name":"KFC WOODVALE","customer_email":"","customer_phone":"","delivery_location":"KFC WOODVALE","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-02"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-04"},{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-02"},{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-04"},{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":3,"description":"KFC CHICKEN NUGGETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":4,"description":"KFC CHICKEN STRIPS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-02"},{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-02"},{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-04"},{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":6,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-04"},{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]}],"currency":"","total_number_of_items":14,"total_number_of_extracted_items":14,"sub_total":0,"total_amount":0,"vat_no":""}]', JSON_FORCE_OBJECT);

        foreach ($data as &$batchOrder) {

            $batchOrder['items'] = Utils::expandList($batchOrder['items']);

            // Rename the uom keys (removes the dot notation created by the expand list function)
            foreach ($batchOrder['items'] as &$item) {
                $newItem = [];
                foreach ($item as $key => $value) {
                    $keyParts = explode(".", $key);

                    if (sizeof($keyParts) == 1) {
                        $newItem[$key] = $value;
                    } else {
                        $newItem[$keyParts[0]][0][$keyParts[1]] = $value;
                    }
                }
                $item = $newItem;
            }
        }

        // Third, we set the delivery date on the LPO
        foreach ($data as &$order) {

            // We get the next delivery date
            $nextDeliveryDate = trim(PathResolver::getValueByPath($order, 'items.0.scheduled_delivery_date_or_day_of_week'));

            // If the next delivery date is a week day, we get the date
            if (preg_match('/^[a-zA-Z]+$/', $nextDeliveryDate)) {
                $dayDate = Utils::format_date("next $nextDeliveryDate", "Y-m-d");

                if (!str_contains($dayDate, "Invalid date format")) {
                    $nextDeliveryDate = $dayDate;
                }

            }

            // Set the delivery date
            PathResolver::setValueByPath($order, 'delivery_date', $nextDeliveryDate);
        }

        print_r(json_encode($data));

        //$this->assertEquals($data, $expectedData);
    }

    public function _testSplitOnNestedPathFunction()
    {

        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                [
                    'name' => 'KFC CHICKEN BREAST FILLETS',
                    'unit_of_measure'   => [[
                        'selling_quantity' => 2,
                        'scheduled_delivery_date_or_day_of_week' => 'Thursday'
                    ]],
                ],
                [
                    'name' => 'KFC CHICKEN BREAST FILLETS',
                    'unit_of_measure'   => [[
                        'selling_quantity' => 3,
                        'scheduled_delivery_date_or_day_of_week' => 'Saturday'
                    ]],
                ],
                [
                    'name' => 'KFC CHICKEN COB 9 PIECE',
                    'unit_of_measure'   => [[
                        'selling_quantity' => 60,
                        'scheduled_delivery_date_or_day_of_week' => 'Tuesday'
                    ]],
                ],
                [
                    'name' => 'KFC CHICKEN COB 9 PIECE',
                    'unit_of_measure'   => [[
                        'selling_quantity' => 60,
                        'scheduled_delivery_date_or_day_of_week' => 'Thursday'
                    ]]
                ],
                [
                    'name' => 'KFC CHICKEN COB 9 PIECE',
                    'unit_of_measure'   => [
                        [
                            'selling_quantity' => 60,
                            'scheduled_delivery_date_or_day_of_week' => 'Saturday'
                        ]
                    ],
                ],
            ],
            'total_unit_price' => 500
        ];

        $data = json_decode('[{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC EASTLEIGH","customer_email":"","customer_phone":"","delivery_location":"EASTLEIGH","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"}]},{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":3,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-17"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":3,"description":"KFC CHICKEN NUGGETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":4,"description":"KFC CHICKEN STRIPS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":6,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":3,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"}]},{"serial_number":6,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":3,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]}],"currency":"","total_number_of_items":10,"total_number_of_extracted_items":10,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"KFC Galleria","customer_name":"KFC Galleria","customer_email":"","customer_phone":"","delivery_location":"KFC Galleria","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":3,"description":"KFC CHICKEN NUGGETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":4,"description":"KFC CHICKEN STRIPS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":6,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":6,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]}],"currency":"","total_number_of_items":10,"total_number_of_extracted_items":10,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC Garden City","customer_email":"","customer_phone":"","delivery_location":"Garden City","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-10"}]},{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":40,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-12"}]},{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":40,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-14"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-10"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-14"}]}],"currency":"","total_number_of_items":5,"total_number_of_extracted_items":5,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"KFC Junction","customer_name":"KFC Junction","customer_email":"","customer_phone":"","delivery_location":"KFC Junction","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":50,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":50,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-05"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":50,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":3,"description":"KFC CHICKEN NUGGETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":4,"description":"KFC CHICKEN STRIPS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-05"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]}],"currency":"","total_number_of_items":10,"total_number_of_extracted_items":10,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC KAKAMEGA","customer_email":"","customer_phone":"","delivery_location":"KAKAMEGA","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":25,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-04"}]}],"currency":"KES","total_number_of_items":3,"total_number_of_extracted_items":3,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC KIAMBU ROAD","customer_email":"","customer_phone":"","delivery_location":"KIAMBU ROAD","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":3,"description":"KFC CHICKEN NUGGETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":4,"description":"KFC CHICKEN STRIPS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]},{"serial_number":6,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-03"}]},{"serial_number":6,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-07"}]}],"currency":"","total_number_of_items":10,"total_number_of_extracted_items":10,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC LANGATA","customer_email":"","customer_phone":"","delivery_location":"KFC LANGATA","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-17"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":40,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":3,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-17"}]},{"serial_number":3,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"}]},{"serial_number":3,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":4,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"}]},{"serial_number":4,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]}],"currency":"","total_number_of_items":9,"total_number_of_extracted_items":9,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC Limuru Road","customer_email":"","customer_phone":"","delivery_location":"Limuru Road","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":40,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"}]},{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-19"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]},{"serial_number":3,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-21"}]}],"currency":"","total_number_of_items":5,"total_number_of_extracted_items":5,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC MACHAKOS","customer_email":"","customer_phone":"","delivery_location":"MACHAKOS","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":20,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-23"}]},{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-26"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-26"}]}],"currency":"KES","total_number_of_items":3,"total_number_of_extracted_items":3,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC Meru","customer_email":"","customer_phone":"","delivery_location":"Meru","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-25"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":70,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-25"}]},{"serial_number":3,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-25"}]}],"currency":"","total_number_of_items":3,"total_number_of_extracted_items":3,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC Mombasa CBD","customer_email":"","customer_phone":"","delivery_location":"Mombasa CBD","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]}],"currency":"KES","total_number_of_items":2,"total_number_of_extracted_items":2,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC NAKURU","customer_email":"","customer_phone":"","delivery_location":"NAKURU","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_price":0,"total_price":0,"unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-06-15"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_price":0,"total_price":0,"unit_of_measure":[{"selling_quantity":70,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-06-13"}]},{"serial_number":3,"description":"KFC CHICKEN COB 9 PIECE","unit_price":0,"total_price":0,"unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-06-15"}]}],"currency":"","total_number_of_items":3,"total_number_of_extracted_items":3,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC NYALI","customer_email":"","customer_phone":"","delivery_location":"KFC NYALI","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-10"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":40,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-10"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":30,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-13"}]},{"serial_number":3,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-10"}]},{"serial_number":3,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-13"}]},{"serial_number":4,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-10"}]},{"serial_number":4,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-13"}]}],"currency":"","total_number_of_items":7,"total_number_of_extracted_items":7,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"John Doe","customer_name":"KFC THIKA","customer_email":"","customer_phone":"","delivery_location":"THIKA","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":50,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-23"}]},{"serial_number":1,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":50,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-27"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-23"}]},{"serial_number":2,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-27"}]}],"currency":"","total_number_of_items":4,"total_number_of_extracted_items":4,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""},{"purchase_order_number":"","order_date":"","ordered_by_name":"KFC WOODVALE","customer_name":"KFC WOODVALE","customer_email":"","customer_phone":"","delivery_location":"KFC WOODVALE","seller_name":"KENCHIC LIMITED","items":[{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-02"}]},{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-04"}]},{"serial_number":1,"description":"KFC CHICKEN BREAST FILLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-02"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-04"}]},{"serial_number":2,"description":"KFC CHICKEN COB 9 PIECE","unit_of_measure":[{"selling_quantity":60,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":3,"description":"KFC CHICKEN NUGGETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":4,"description":"KFC CHICKEN STRIPS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-02"}]},{"serial_number":4,"description":"KFC CHICKEN STRIPS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-02"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-04"}]},{"serial_number":5,"description":"KFC CHICKEN WINGLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]},{"serial_number":6,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":1,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-04"}]},{"serial_number":6,"description":"KFC FZN CHICKEN MINI FILLETS","unit_of_measure":[{"selling_quantity":2,"selling_unit":"Pieces","scheduled_delivery_date_or_day_of_week":"2023-10-06"}]}],"currency":"","total_number_of_items":14,"total_number_of_extracted_items":14,"sub_total":0,"total_amount":0,"vat_no":"","delivery_date":""}]',JSON_FORCE_OBJECT);

        $finalOrdersList = [];

        // Second, we split the orders along products based on scheduled delivery dates. This way, products to be delivered on the same date/day form the same order
        foreach ($data as $bOrder) {

            $splitOrder = DataSplitter::split($bOrder, '','items', 'items.*.unit_of_measure.0.scheduled_delivery_date_or_day_of_week', null, null);

            if (Helpers::isIndexedArray($splitOrder)) {
                $finalOrdersList = array_merge($finalOrdersList, $splitOrder);
            } else {
                $finalOrdersList[] = $splitOrder;
            }

        }

        print_r($finalOrdersList);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testSplitByRunningTotalFunction()
    {

        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                [
                    'name' => 'KFC CHICKEN BREAST',
                    'unit_of_measure'   => [[
                        'selling_quantity' => 8,
                        'scheduled_delivery_date_or_day_of_week' => 'Thursday'
                    ]],
                ],
                [
                    'name' => 'KFC CHICKEN BREAST FILLETS',
                    'unit_of_measure'   => [[
                        'selling_quantity' => 3,
                        'scheduled_delivery_date_or_day_of_week' => 'Saturday'
                    ]],
                ],
                [
                    'name' => 'KFC CHICKEN COB 9 PIECE',
                    'unit_of_measure'   => [[
                        'selling_quantity' => 60,
                        'scheduled_delivery_date_or_day_of_week' => 'Tuesday'
                    ]],
                ],
                [
                    'name' => 'KFC CHICKEN COB 6 PIECE',
                    'unit_of_measure'   => [[
                        'selling_quantity' => 12,
                        'scheduled_delivery_date_or_day_of_week' => 'Thursday'
                    ]]
                ],
                [
                    'name' => 'KFC CHICKEN COB 12 PIECE',
                    'unit_of_measure'   => [
                        [
                            'selling_quantity' => 30,
                            'scheduled_delivery_date_or_day_of_week' => 'Saturday'
                        ]
                    ],
                ],
            ],
            'total_unit_price' => 500
        ];

        $action = new FunctionAction("", [$this, 'split'], ['method' => 'running_total', 'split_path' => "products", "criteria_path" => "unit_of_measure.0.selling_quantity", "criteria" => "", "running_total_limit" => 50], '');

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
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
                ['name' => 'Capon Chicken 2kg', 'quantity' => 2, 'uom' => '2kgs'],
                ['name' => 'Chicken Sausages 1kg', 'quantity' => 1, 'uom' => '2kg'],
            ],
        ];

        $action = new FunctionAction("products.*.code", [$this, 'custom_preg_replace'], ['pattern' => "/^([A-Z]{3}).*$/", "replacement" => "$1"], 'products.*.new_code');

        $action->execute($data);

        //print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testStringDiff()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken 2kg', 'units' => [['quantity' => 2, 'uom' => '2kgs']]],
                ['name' => 'Chicken Sausages 1kg', 'units' =>[['quantity' => 1, 'uom' => '1kg']]],
                ['name' => 'Chicken Sausages 4PC QTR', 'units' => [['quantity' => 1, 'uom' => '4 PCS']]],
                ['name' => 'Chicken Sausages QTR 4PC', 'units' => [['quantity' => 1, 'uom' => '4 PCS']]],
                ['name' => 'Breast 6kgs', 'units' => [['quantity' => 1, 'uom' => '6 KGS']]],
            ],
        ];

        $action = new FunctionAction("products", [$this, 'string_diff'], ['key1' => "name", "key2" => "units.0.uom", "regex_pre_modifier" => "\b", "regex_post_modifier" => "?s?\b", "new_key" => "new_value"]);

        $action->execute($data);

        //$data = Utils::get_string_diff($data['products'], 'name', 'uom', '\b', '?s?\b','new_name');

        print_r($data);

        //$this->assertEquals($data, $expectedData);
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
            'total_unit_price' => 500,

        ];

        $action = new FunctionAction("products.*.original_value.unit_of_measure", [$this, 'assoc_array_find'], ['condition_field' => "selling_unit", "condition_operator" => "similar_to", "condition_value" => "Pieces - PCS", "condition_threshold" => 80, "condition_tokenize" => false, "return_key" => "selling_quantity"], 'products.*.original_value.number_of_pieces');

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testArraySetIf()
    {
        $data = [
            'products' => [
                [
                    "meta_data" => [
                        "Description" => "CHICKEN DRUMSTICKS 6PC - 700 GM",
                        "Section" => "Shop"
                    ]
                ],
                [
                    "meta_data" => [
                        "Description" => "HUNGARIAN CHOMA SAUSAGES 1 KG",
                        "Section" => "Shop"
                    ]
                ],
                [
                    "meta_data" => [
                        "Description" => "HUNGARIAN CHOMA SAUSAGES 500GMS",
                        "Section" => "Deli"
                    ]
                ],
            ],
        ];

        $expectedData = [];

        $operations = [
            [
                'operation_field' => 'Section',
                'operation_value' => 'Shop',
                'condition_field' => "Description",
                "condition_operator" => "==",
                "condition_value" => "HUNGARIAN CHOMA SAUSAGES 500GMS"
            ],
            [
                'operation_field' => 'Section',
                'operation_value' => 'Butchery',
                'condition_field' => "Description",
                "condition_operator" => "==",
                "condition_value" => "HUNGARIAN CHOMA SAUSAGES 1 KG"
            ]
        ];

        $action = new FunctionAction("products.*.meta_data", [$this, 'assoc_array_set_if'], ["operations" => $operations], '');

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

        $searchCondition = ["path" => " acc", "operator" => "==", "value" => ["path" => "principal_code"]];

        $action = new FunctionAction("brands", [$this, 'assoc_array_find'], ['search_condition' => $searchCondition, "return_key" => ""], 'brand_details');

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

    public function __testPregReplace()
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
                    'name' => 'CHICKEN DRUMSTICKS 6PC -700 GM',
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

        $action = new FunctionAction("products.*.name", [$this, "transform"], ["preg_replace", "args" => ["pattern" => "-(?=\S)", "replacement" => "-", "addSpacer" => true, "isCaseSensitive" => false], "target_keys" => []]);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testExplodeString()
    {
        $data = [
            'days' => "Monday/Tuesday"
        ];

        $expectedData = [];

        $action = new FunctionAction("days", [$this, "transform"], ["explode", "args" => ["separator" => "/"], "target_keys" => []]);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testDateFromString()
    {
        $data = [
            'days' => ["Monday", "Tuesday"]
        ];

        $expectedData = [];

        $action = new FunctionAction("days", [$this, "transform"], ["string_to_date_time", "args" => ["format" => "Y-m-d H:i:s","pre_modifier" => "Next", "post_modifier" => ""], "target_keys" => []]);

        $action->execute($data);

        //print_r($data);

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
        $data = [
            'items' => [
                ['description' => "CABLE ELETRO IEC TYPE N-1220642"],
                ['description' => "Oshozyme Liquid - 24X200ml/24x500ml"],
                ['description' => "Neemcide 0.3% -1L"],
            ]
        ];

        $mapping = [
            "\b\d+\s*(MG|G|GM|GRM|GMS|KG|KGS|PC|PCS|ML|L|LT|LTR|LTRS|M|X)?\b|[^\w\s]+|(\b|\d+)((?!BIO)[a-zA-Z]{1,3})\b" => "",
            "(\d+[X])" => ""
        ];

        $expectedData = [];

        $action = new FunctionAction("items.*.description", [$this, "transform"], ["regex_mapper", "args" => ["mappings" => $mapping, "is_case_sensitive" => false], "target_keys" => []], 'items.*.formatted_description');

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testSpellCorrection()
    {
        $data = [
            'items' => [
                ['description' => "OSHOTTANE 50"],
                ['description' => "OSHOLLANE 100G"],
                ['description' => "OSHOHANE 500G"],
                ['description' => "MATLE 100G"],
                ['description' => "MISLESS 240G"],
                ['description' => "EASY GROW VEGETATIVE ORHEMIAN SOMIS 120G"],
                ['description' => "RICKOUT 200MUS"],
                ['description' => "Orhemian 50ML"],
            ],
            "products" => [
                ['description' => "CABLE ELETRO IEC TYPE N-1220642"],
                ['description' => "Oshozyme Liquid - 24X200ml/24x500ml"],
                ['description' => "Neemcide 0.3% -1L"],
                ['description' => "MATCO 72 WP - 100GMS"],
                ['description' => "MATCO 72 WP - 50GMS"],
                ['description' => "MISTRESS 72 WP - 240GRM"],
                ['description' => "EASY GRO VEGETATIVE - 120GRM"],
                ['description' => "KICK OUT 480 SL - 200ML"],
                ['description' => "OSHOZYME 1L"],
                ['description' => "OSHOTHANE 80 WP - 200GMS"],
                ['description' => "OSHOTHION 50 EC - 50ML"],
                ['description' => "EASYGRO VEGETATIVE ROLLS-40GRM"],
                ['description' => "EASYGRO VEGETATIVE ROLLS-40GRM"],
                ['description' => "Easygro Vegetative 12 x 1Kg Printed Carton"],
                ['description' => "EASYGRO VEGETATIVE BULK"],
                ['description' => "FORMALIN - Kg"],
            ]
        ];

        $stemmingPatterns = [
            "\b\d+\s*(MG|G|GM|GRM|GMS|KG|KGS|PC|PCS|ML|L|LT|LTR|LTRS|M|X)?\b|[^\w\s]+|(\b|\d+)((?!BIO|OUT)[a-zA-Z]{1,3})\b",
            "(\d+[X])"
        ];

        $expectedData = [];

        $action = new FunctionAction("items", [$this, "pattern_based_stem_spell_corrections"], ["search_key" => "description", "corpus_list" => ["path" => "products"], "corpus_key" => "description", "search_stemming_patterns" => $stemmingPatterns, "corpus_stemming_patterns" => $stemmingPatterns, "similarity_threshold" => 70], 'items');

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

    public function _testModalReducer()
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

    public function _testPriorityReducer()
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
            'product_types' => ['Frozen', 'Frozen', 'Fresh']
        ];

        $expectedData = [];

        $action = new FunctionAction("product_types", [$this, "reducer"], ["reducer" => "priority_reducer", 'priority' => ['Fresh' => 1, 'Frozen' => 2], "default" => 'Frozen'], "product_type");

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

        $expectedData = [];

        $action = new FunctionAction("", [$this, 'join'], ['join_paths' => $joinPaths, 'criteria' => $condition]);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testFuzzySearch()
    {
        $data = [
            'customer' => 'NAIVAS LIMITED NAIVAS KATANI SHOP KATANI SH',
            'products' => [
                ['code' => "DEL001", 'name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 200, "brand" => "Kenchic"],
                ['code' => "DEL002",'name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300, "brand" => "Kenchic"],
                ['code' => "PIL003", 'name' => 'Chicken Sausages 500g', 'quantity' => 5, 'unit_price' => 200, "brand" => "kenmeat"],
            ],
            "customers_list" => [
                ["name" => "NAIVAS LIMURU -", "id" => 1],
                ["name" => "NAIVAS KATANI- CHARLES MATHEKA", "id" => 2]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("customer", [$this, 'fuzzy_search'], ['corpus' => ["path" => "customers_list"], "corpus_search_key" => "name", "corpus_id_key" => "id", "master_data_type" => "customers", "similarity_threshold" => 20, 'number_of_matches' => 1, 'scorer' => 'tokenSetRatio']);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
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

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }


    public function summate($data) {
        return array_sum($data);
    }

    public function _testFlattenObject()
    {
        $data = [
            'products' => [
                ['name' => 'Capon Chicken', 'description' => 'Capon 1.2', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'description' => 'frozen', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'description' => ' sold in pieces', 'quantity' => 5, 'unit_price' => 200],
            ],
            "location" => [
                "country" => "Kenya",
                "city"  => "Nairobi"
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("", [$this, 'flatten_and_expand'], []);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testRenameObjectKeys()
    {
        $data = [
            ['name' => 'Capon Chicken', 'description' => 'Capon 1.2', 'quantity' => 2, 'unit_price' => 200],
            ['name' => 'Chicken Sausages', 'description' => 'frozen', 'quantity' => 3, 'unit_price' => 300],
            ['name' => 'Chicken Sausages 500g', 'description' => ' sold in pieces', 'quantity' => 5, 'unit_price' => 200]
        ];

        $expectedData = [];

        $action = new FunctionAction("", [$this, 'rename_object_keys'], ["key_map"=> ["name" => "product_name", "quantity" => "qty"]]);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testConditionalFunction()
    {
        $data = [
            'products' => [
                ['name' => 'KENCHIO SMOKED CKN SAUSAGE 500G (FOR NAIVAS)', 'description' => 'Capon 1.2', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Naivas Chicken Sausages', 'description' => 'frozen', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'description' => ' sold in pieces', 'quantity' => 5, 'unit_price' => 200],
            ],
            "name" => "Juma"
        ];

        $expectedData = [];

        $condition = [
            "operator" => "AND",
            "conditions" => [
                [
                    "operator" => "AND",
                    "conditions"    => [
                        [
                            "operator" => "contains",
                            "value"    => "NAIVAS"
                        ],
                        [
                            "operator" => "not contains",
                            "value"    => "(FOR NAIVAS)"
                        ]
                    ]
                ]
            ]
        ];

        $action = new FunctionAction("products.*.name", [$this, 'append'], ["strings" => ['1KG'], "separator" => "", "use_data_as_path_value" => true, "valueKey" => ""], null, null, $condition);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testAppendAssoc()
    {
        $data = [
            'products' => [
                ['name' => 'QUICKMART WINGS 1KG  FRESH', 'uom' => 'KGS'],
                ['name' => 'CHICKEN WINGS  - 650GM PACK', 'uom' => 'PACK'],
                ['name' => 'WINGS', 'uom' => 'KGS'],
                ['name' => 'Fresh (HANDWRAP) CAPON (QUICKMART) 1.0 - 1.3 (CHICKEN)', 'uom' => 'KGS'],
            ]
        ];

        $expectedData = [];

        $condition = [
            "operator" => "AND",
            "conditions"    => [
                [
                    "operator" => "==",
                    "path" => "uom",
                    "value"    => "KGS"
                ],
                [
                    "operator" => "not contains",
                    "path" => "name",
                    "value"    => "1kg"
                ],
                [
                    "operator" => "not contains",
                    "path" => "name",
                    "value"    => "500"
                ]
            ]
        ];

        $action = new FunctionAction("products", [$this, 'append'], ["strings" => ['(PER KG)'], "separator" => "", "use_data_as_path_value" => "", "valueKey" => "name"], null, null, $condition);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testRemoveRepeatedWords()
    {
        $data = [
            'items' => [
                ['name' => 'Fresh QUICKMART BREAST BONELESS 1KG FRESH', 'description' => 'Capon 1.2', 'quantity' => 2, 'unit_price' => 200],
                ['name' => 'Chicken Sausages', 'description' => 'frozen', 'quantity' => 3, 'unit_price' => 300],
                ['name' => 'Chicken Sausages 500g', 'description' => ' sold in pieces', 'quantity' => 5, 'unit_price' => 200]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("items.*.name", [$this, 'remove_repeated_words'], []);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testDataMerge()
    {
        $data = [
            'all_products' => [
                ['code' => 'PC0001', 'name' => 'Fresh QUICKMART BREAST BONELESS 1KG FRESH', 'description' => 'Capon 1.2'],
                ['code' => 'PC0002', 'code', 'name' => 'Chicken Sausages', 'description' => 'frozen'],
                ['code' => 'PC0003', 'code', 'name' => 'Chicken Sausages 500g', 'description' => ' sold in pieces']
            ],
            "historical_products" => [
                ["code" => "PC0001", 'quantity' => 2, 'unit_price' => 200],
                ["code" => "PC0002", 'quantity' => 3, 'unit_price' => 300]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("", [$this, 'merge'], ['left' => ['path' => 'historical_products'], 'right' => ['path' => 'all_products'], 'join' => ['type' => "inner", "on" => "left.code = right.code"], 'fields' => ["left.code", "right.name", "right.description"], "group_by" => null]);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testRandomString()
    {
        $data = [
            'all_products' => [
                ['code' => 'PC0001', 'name' => 'Fresh QUICKMART BREAST BONELESS 1KG FRESH', 'description' => 'Capon 1.2'],
                ['code' => 'PC0002', 'code', 'name' => 'Chicken Sausages', 'description' => 'frozen'],
                ['code' => 'PC0003', 'code', 'name' => 'Chicken Sausages 500g', 'description' => ' sold in pieces']
            ],
            "historical_products" => [
                ["code" => "PC0001", 'quantity' => 2, 'unit_price' => 200],
                ["code" => "PC0002", 'quantity' => 3, 'unit_price' => 300]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("", [$this, 'get_random_string'], ['length' => 4, 'alphabet' => "0123456789"], "code" );

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testUnitConversionNew()
    {
        $data = [
            "items" => [
                ["code" => "PC0002", 'quantity' => 17, 'uom' => 'PCS',
                    "conversion_table" => [
                        ["from" => "PCS", "to" => "Bales", "factor" => "3"]
                    ]
                ]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("", [$this, 'convert_unit_multi'], ['items' => ["path" => "items"], 'conversionTable' => ['in_item_path' => 'conversion_table'], 'quantity' => ['in_item_path' => 'quantity'], 'from_unit' => ['in_item_path' => 'uom'], 'to_unit' => 'Bales', 'invert_factor' => 1, 'decimal_handler' => 'off', 'number_of_decimal_places' => "3", 'output_path' => 'converted_units'], "items");

        $action->execute($data);

        print_r($data);

//        $this->assertEquals($data, $expectedData);
    }

    public function _testMapSet()
    {
        $data = [
            "items" => [
                ["code" => "PC0001", "name" => "chicken chicken", 'quantity' => 200, 'uom' => 'PCS', 'factor' => 2,
                    'matched_value' => ['uom_list' => [['uom_code' => '19', 'uom_name' => '24KG BALE']]],
                    "conversion_table" => [
                        ["from" => "PCS", "to" => "24KG BALE", "factor" => "0"]
                    ]],

                ["code" => "PC0002", "name" => "beef", 'quantity' => 30, 'uom' => 'Bales', 'factor' => 3,
                    'matched_value' => ['uom_list' => [['uom_code' => '19', 'uom_name' => '8KG BALE']]],
                    "conversion_table" => [
                        ["from" => "PCS", "to" => "24KG BALE", "factor" => "0"]
                    ]]
            ]
        ];

        $expectedData = [];

        $conditionalValue = [
            [
                "condition" => [
                    "operator" => "AND",
                    "conditions" => [
                        [
                            "path"  => "uom",
                            "operator" => "in list any",
                            "value" => ["PCS", "PC", "Piece", "Bag"]
                        ],
                        [
                            "path"  => "matched_value.uom_list.0.uom_name",
                            "operator" => "not in list all",
                            "value" => ["PCS","PC", "Piece", "Bag"]
                        ]
                    ]
                ],
                'use_data_as_path_value' => false,
                "value"     => "",
                "valueFromField"    => "factor"
            ],
            [
                "condition" => [
                    "operator" => "OR",
                    "conditions" => [
                        [
                            "operator" => "AND",
                            "conditions" => [
                                [
                                    "path"  => "uom",
                                    "operator" => "in list any",
                                    "value" => ["Bale","Bales", "Carton", "Ctn", "Pack"]
                                ],
                                [
                                    "path"  => "matched_value.uom_list.0.uom_name",
                                    "operator" => "in list any",
                                    "value" => ["Bale","Bales", "Carton", "Ctn", "Pack"]
                                ]
                            ]
                        ],
                        [
                            "operator" => "AND",
                            "conditions" => [
                                [
                                    "path"  => "uom",
                                    "operator" => "in list any",
                                    "value" => ["PCS", "PC", "Piece", "Bag"]
                                ],
                                [
                                    "path"  => "matched_value.uom_list.0.uom_name",
                                    "operator" => "in list any",
                                    "value" => ["PCS","PC", "Piece", "Bag"]
                                ]
                            ]
                        ]
                    ]
                ],
                'use_data_as_path_value' => false,
                "value"     => "1",
                "valueFromField"    => ""
            ]
        ];

        $args = [
            'data_path' => "",
            'value' => null,
            'valueFromField' => null,
            'valueMapping' => null,
            'conditionalValue' => $conditionalValue,
            'newField'  => "conversion_table.0.factor"
        ];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => '', 'function' => 'set', 'args' => $args, 'newField' => null, 'strict' => 0, 'condition' => null], "new_items");

        $action->execute($data);

        print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testMapDateFormat()
    {
        $data = [
            "items" => [
                ["date" => "Phelix"],
                ["date" => "Omondi"]
            ]
        ];

        $expectedData = [];

        $args = [];

        $condition = [
            "path" => "date",
            "operator" => "!=",
            "value" => "Phelix"
        ];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => 'date', 'function' => 'strtoupper', 'args' => "", 'newField' => null, 'strict' => 0, 'condition' => null], "new_items", '0', $condition);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testObjectListUnique()
    {
        $data = [
            "items" => [
                [
                    "description" => [
                        "similarity" => 97.02811322688663,
                        "meta_data" => [
                            "id" => "FG2911003"
                        ]
                    ]
                ],
                [
                    "description" => [
                        "similarity" => 98.70738305553967,
                        "meta_data" => [
                            "id" => "FG2001002"
                        ]
                    ]
                ],
                [
                    "description" => [
                        "similarity" => 90.91947421699516,
                        "meta_data" => [
                            "id" => "FG2001002"
                        ]
                    ]
                ],
                [
                    "description" => [
                        "similarity" => 98.60738305553967,
                        "meta_data" => [
                            "id" => "FG2001002"
                        ]
                    ]
                ],
                [
                    "description" => [
                        "similarity" => 99.02811322688663,
                        "meta_data" => [
                            "id" => "FG2011003"
                        ]
                    ]
                ]
            ]
        ];

        $expectedData = [];


        $action = new FunctionAction("items", [$this, 'make_object_list_unique'], ['uniqueKeyPath' => ['description.meta_data.id'], 'rankKeyPath' => 'description.similarity', 'rankOrder' => 'desc'], "new_items");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testRegexExtract()
    {
        $data = [
            "items" => [
                ["description" => "450ML x 6"],
                ["description" => "2.89KG CARTON"],
                ["description" => "25KG BALE/BAG"],
                ["description" => "12KG BALE"],
                ["description" => "5KG BAG/PACK"],
                ["description" => "6.5KG PACK"],
                ["description" => "12 X 250ML"],
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => 'description', 'function' => 'regex_extract', 'args' => ['pattern' => "^(?!.*\bx\b).*?(\d+(\.\d+)?)\s*(KG|G|ML)\b", 'flag' => '1'], 'newField' => 'pack_total_size', 'strict' => 0, 'condition' => null], "");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testRegexExtract2()
    {
        $data = [
            "items" => [
                ["description" => "450ML x 6"],
                ["description" => "2.89KG CARTON"],
                ["description" => "25KG BALE/BAG"],
                ["description" => "12KG BALE"],
                ["description" => "5KG BAG/PACK"],
                ["description" => "6.5KG PACK"],
                ["description" => "12 X 250ML"],
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => 'description', 'function' => 'regex_extract', 'args' => ['pattern' => "^(?=.*\bx\b).*?(\d+(\.\d+)?)\s*(KG|G|ML)\b", 'flag' => '1'], 'newField' => 'unit_size', 'strict' => 0, 'condition' => null], "");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testRegexExtract3()
    {
        $data = [
            "items" => [
                ["description" => "450ML x 6", "unit_of_measure" =>[["quantity" => 721]]],
                ["description" => "2.89KG CARTON", "unit_of_measure" =>[["quantity" => 42]]],
                ["description" => "25KG BALE/BAG", "unit_of_measure" =>[["quantity" => 20]]]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => 'unit_of_measure.0.quantity', 'function' => 'regex_extract', 'args' => ['pattern' => "^(\d{1,})(?:1)$", 'flag' => '1', 'isCaseSensitive' => true, 'returnSubjectOnNull' => true], 'newField' => '', 'strict' => 0, 'condition' => null], "");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testMultiplication()
    {
        $data = [
            "items" => [
                ["description" => "450ML x 6", "unit_size" => "450", "pack_total_size" => "", "number_pieces_bale" => "6"],
                ["description" => "2.89KG CARTON", "unit_size" => "", "pack_total_size" => "2.89", "number_pieces_bale" => "36"],
                ["description" => "25KG BALE/BAG", "unit_size" => "", "pack_total_size" => "25", "number_pieces_bale" => "5"],
                ["description" => "12KG BALE", "unit_size" => "", "pack_total_size" => "12", "number_pieces_bale" => "24"],
                ["description" => "5KG BAG/PACK", "unit_size" => "", "pack_total_size" => "5", "number_pieces_bale" => "1"],
                ["description" => "6.5KG PACK", "unit_size" => "", "pack_total_size" => "6.5", "number_pieces_bale" => "0"],
                ["description" => "12 X 250ML", "unit_size" => "250", "pack_total_size" => "", "number_pieces_bale" => "12"],
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => '', 'function' => 'basic_arithmetic', 'args' => ['operator' => "divide", 'operands' => [['path' => 'pack_total_size'], ['path' => 'number_pieces_bale']], 'defaultValue' => ['path' => 'unit_size'], 'moduloHandler' => 'round', 'decimalPlaces' => 2], 'newField' => 'unit_size', 'strict' => 0, 'condition' => ["operator" => "not exists", "path" => "unit_size"]], "");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testDuplicator()
    {
        $data = [
            "items" => [
                    [
                        "item_code" => "FG2001003",
                        "item_quantity"=> 4,
                        "uom_code"=> "22",
                        "warehouse_code"=> "WH16"
                    ],
                    [
                        "item_code"=> "FG4110002",
                        "item_quantity" => 1,
                        "uom_code" => "19",
                        "warehouse_code" => "WH16"
                    ]
                ]
        ];

        $replacement = [
            [
                "item_code" => "FG2001003",
                "replacements"  => [
                    "item_code" => "FG2001002",
                    "uom_code"  => "23"
                ]
            ],
            [
                "item_code" => "FG2001002",
                "replacements"  => [
                    "item_code" => "FG2001003",
                    "uom_code"  => "23"
                ]
            ]
        ];

        $condition = [
            'path'      => 'item_code',
            'operator'  => 'in list any',
            'value'     => ['FG2001003']
        ];

        $expectedData = [];

        $action = new FunctionAction("items", [$this, 'duplicate_list_item'], ['replacementKey' => 'item_code', 'replacement' => $replacement], '' , 0, $condition);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testTemplateParser()
    {
        $data = [
            "text" => "Email Subject: Purchase order Confirmation: P021751369 - KENCHIC LIMITED :BRANCH-PRESTIGE\n Email Body:\n Dear KENCHIC LIMITED, \n\nPlease find attached Purchase Order for supply. \nKindly deliver on the specified date to PRESTIGE. \nRegards, \nNaivas Team."
        ];

        $expectedData = [];

        $pattern = "Sent By:\s*{{sender_name}}[\n\r]Email:(?:.*)@{{email_domain}}\.(?:.*?)[\n\r]\s*Email Subject:{{email_subject}}[\n\r]";

        $action = new FunctionAction("text", [$this, 'parse_template'], ['template' => $pattern, 'config' => [['non_greedy' => '1'], ['non_greedy' => '1'], ['non_greedy' => '1']]], "template_data");

        $action->execute($data);

        print_r($data['template_data']);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testMapObjectToArray()
    {
        $data = [
            "items" => [
                [
                    "code" => "PC0001", "name" => "chicken chicken", "unit_of_measure" => [['quantity' => 200, 'uom' => 'PCS']]
                ],
                [
                    "code" => "PC0002", "name" => "beef", "unit_of_measure" => [['quantity' => 30, 'uom' => 'PCS']]
                ]
            ]
        ];

        $expectedData = [];

        $args = [
            'data_path' => "unit_of_measure.0.uom",
            'value' => "Bales",
            'valueFromField' => "",
            'valueMapping' => null,
            'conditionalValue' => null,
            'newField'  => ""
        ];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => '', 'function' => 'set', 'args' => $args, 'newField' => null, 'strict' => 0, 'condition' => null], "new_items");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testStrLen()
    {
        $data = [
            "customer_name" => "Naivas"
        ];

        $expectedData = [];

        $action = new FunctionAction("customer_name", [$this, 'strtoupper'], null, '', 0, null);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testConditionalAppend()
    {
        $data = [
            "customer_name" => "Naivas",
            "items" => [
                ["description" => "Assorted 1kg", "pack" => "Pack: 1 x 1"],
                ["description" => "Smoked sausage", "pack" => "Pack: 1 x 1"]
            ]
        ];

        $expectedData = [];

        $condition = [
            "operator" => "not matches",
            "path" => "description",
            "value" => "\d+\s*(?:G|GM|GMS|KG|KGS|PC|PCS)"
        ];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => '', 'function' => 'append', 'args' => ['stringsToAppend' => ["[",["path" => "pack"],"]"], "seperator" => "", "use_data_as_path_value" => false, 'valueKey' => "description"], 'newField' => '', 'strict' => 0 ,'condition' => $condition], '', 0, null);

        $action->execute($data);

        //print "\nTest data:\n";
        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testFilterInArray()
    {
        $data = [
            "items" => [
                [
                    "description" => [
                        "original_value" => "Item 1",
                        "similarity"    => 89
                    ],
                    "quantity" => 10
                ],
                [
                    "description" => [
                        "original_value" => "Item 2",
                        "similarity"    => 35
                    ],
                    "quantity" => 5
                ],

            ]
        ];

        $filterCriteria = [
            "operator" => "AND",
            "conditions" => [
                [
                    'term' => "50",
                    "mode" => "lte",
                    "key" => "description.similarity",
                    'threshold' => "50",
                    "term_exclusion_pattern" => "",
                    "value_exclusion_pattern" => ""
                ]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("items", [$this, 'filter'], ["filter_criteria" => $filterCriteria], '', 0, null);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testFuzzyExtractTopN()
    {
        $data = [
            "items" => [
                ["description" => "HI - CAL E G 100ML [x]", "pack" => "Pack: 1 x 1", "products"  => [
                    ["description" => "ENRICH BM - 20GMS [48 X 20 GRM]", "pack" => "Pack: 1 x 1"],
                    ["description" => "Aquawett-100ML", "pack" => "Pack: 1 x 1"],
                    ["description" => "PEARL x 24 x 100ml [24 x 100]", "pack" => "Pack: 1 x 1"],
                    ["description" => "Smoked chicken sausage 6pc", "pack" => "Pack: 1 x 1"]
                ]]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => '', 'function' => 'fuzzy_extract_n', 'args' => ["query" => ["path" => "description"], "choices" => ["path" => "products"],"searchKey" => "description", "n" => "2"], 'newField' => 'products', 'strict' => 0 ,'condition' => null], '', 0, null);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testFuzzyExtractTopNCustomers()
    {
        $data = [
            "customer_name" => "QUICKMART LTD.",
            "customers_list" => [
                ["description" => "UZURI SUPERMARKET LTD."],
                ["description" => "QUICKMART LIMITED"],
                ["description" => "Kamkunji Agrovet"],
                ["description" => "FARM CARE AGROVET"],
            ],
            "stop_words" => [
                "(?<!FARM CARE )(\\bAGRO(?:\\s*)(?:-)?(?:\\s*)VET\\b)",
                "LIMITED"
            ]
        ];

        $expectedData = [];

        $stopWords = [
            "path" => "stop_words"
        ];

        $action = new FunctionAction("", [$this, 'fuzzy_extract_n'], ["query" => ["path" => "customer_name"], "choices" => ["path" => "customers_list"],"searchKey" => "description", "n" => "2", "order" => "desc", "fuzzy_method" => "tokenSetRatio", "stop_words" => $stopWords], 'shortlisted_customers',  0 , null);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testUDFInMap()
    {
        $data = [
            "items" => [
                [
                    "description" => "AQuangel 100ml x 24 x 100ml [24 x 100]",
                    "pack" => "Pack: 1 x 1",
                    "products"  => [
                        ["description" => "Cotaf 100ml", "pack" => "Pack: 1 x 1"],
                        ["description" => "Aquawett-100ML", "pack" => "Pack: 1 x 1"],
                        ["description" => "PEARL x 24 x 100ml [24 x 100]", "pack" => "Pack: 1 x 1"],
                        ["description" => "Smoked chicken sausage 6pc", "pack" => "Pack: 1 x 1"]
                    ]
                ],
                [
                    "description" => "Smoked sausage",
                    "pack" => "Pack: 1 x 1",
                    "products"  => [
                        ["description" => "Assorted pack 1kg", "pack" => "Pack: 1 x 1"],
                        ["description" => "Assorted pack 10kg", "pack" => "Pack: 1 x 1"],
                        ["description" => "Smoked chicken sausage", "pack" => "Pack: 1 x 1"],
                        ["description" => "Smoked chicken sausage 6pc", "pack" => "Pack: 1 x 1"]
                    ]
                ]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => 'products', 'function' => 'user_defined_function', 'args' => ["function_name"=> "first_array"], 'newField' => 'products', 'strict' => 0 ,'condition' => null], '', 0, null);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public static function first_array($data) {
        return $data[0];
    }

    public function _testMultipleRegexMapper()
    {
        $data = [
            "items" => [
                ["description" => "PIX 100CJ SKARA LCJ M"],
                ["description" => "24 X SOG OSHOTHANE"],
                ["description" => "10 X log OSHOTHANE"],
                ["description" => "10 X lopes AQUAWETT"],
                ["description" => "10 X loopes AQUAWETT"],
                ["description" => "10 X 10 pes AQUAWETT"],
                ["description" => "SOOMI AQUAWETT"],
                ["description" => "/ LTR AQUAWETT"],
                ["description" => "10M' AQUAWETT"],
                ["description" => "XRIEX 250g Skana"],
                ["description" => "XSom' degree"],
            ]
        ];

        $mappers = [
            [
                "description" => "Correct common misspelt units of measure based on common misspellings immediately following a number",
                "order" => "1",
                "data" => [
                    'pattern' => "\b(\d+\s*(?:CJ))\b",
                    'modifiers' => "i",
                    "replacements" => [
                        ["pattern" => "CJ", "replacement" => "G"]
                    ]
                ]
            ],
            [
                "description" => "Correct ML being misspelt as MI or M'. Pattern checks where MI or M' appears at the end of a word or line ",
                "order" => "2",
                "data" => [
                    'pattern' => "\b(\w*\s*(?:MI|M'))(?=\s|$)",
                    'modifiers' => "i",
                    "replacements" => [
                        ["pattern" => "M(I|')", "replacement" => "ML"]
                    ]
                ]
            ],
            [
                "description" => "",
                "order" => "3",
                "data" => [
                    'pattern' => "\b((?:lo+|\d+\s*)pes)\b",
                    'modifiers' => "i",
                    "replacements" => [
                        ["pattern" => "PES", "replacement" => "PCS"]
                    ]
                ]
            ],
            [
                "description" => "Check for common misspelt numbers that are immediately followed by common units of measure",
                "order" => "4",
                "data" => [
                    'pattern' => "(?<=^|\s)([ISOBZL\/]+)(?=\s*(?:L|LTR|LT|LTS|Liters|Liter|Litre|Litres|ML|MLS|KG|G|GM|GMS|GRM|GRMS|CM|MM|PC|PCS)\b|\s|$)",
                    'modifiers' => "i",
                    "replacements" => [
                        ["pattern" => "I|L|\/", "replacement" => "1"],
                        ["pattern" => "S", "replacement" => "5"],
                        ["pattern" => "O", "replacement" => "0"],
                        ["pattern" => "B", "replacement" => "8"],
                        ["pattern" => "Z", "replacement" => "2"]
                    ]
                ]
            ]
        ];

        $expectedData = [];


        $action = new FunctionAction("items.*.description", [$this, 'regex_mapper_multiple'], ["mappers" => $mappers, "sort_by_order" => "1"], '', 0, null);

        $action->execute($data);

        print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testCorrectDate()
    {
        $data = [
            "dates" => "2024-03-07 12:25:03"
        ];

        $expectedData = [];

        $action = new FunctionAction("dates", [$this, 'correct_date'], [], '',  1, null);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

}
