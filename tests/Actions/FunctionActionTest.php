<?php

namespace PhelixJuma\GUIFlow\Tests\Actions;

use Kuza\Krypton\Framework\Helpers\UtilsHelper;
use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Actions\SetValueAction;
use PhelixJuma\GUIFlow\Utils\DataSplitter;
use PhelixJuma\GUIFlow\Utils\EntityExtractor;
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

        $data = json_decode('{"purchase_order_number":"50185","order_date":"2024-10-07","ordered_by_name":"Joan Njeri","customer_name":"KHETIA GARMENTS LTD","customer_email":"khetiapekee@yahoo.com","customer_phone":"+254 725900200,+254 721900200","delivery_location":"KHETIA GARMENTS LTD","delivery_date":"2024-10-22","seller_name":"Pwani Oil Products Limited","items":[{"serial_number":"1","customer_item_code":"180045","item_bar_code":"0","description":"FRESH FRI 12X1L","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"2","customer_item_code":"180180","item_bar_code":"0","description":"FRESH FRI 12X500ML","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"3","customer_item_code":"180040","item_bar_code":"0","description":"FRESH FRI 4X5L","unit_of_measure":[{"selling_quantity":"50","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"4","customer_item_code":"180039","item_bar_code":"0","description":"FRESH FRI 6X2L","unit_of_measure":[{"selling_quantity":"150","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"5","customer_item_code":"180047","item_bar_code":"0","description":"FRESH FRI 6X3LTRS","unit_of_measure":[{"selling_quantity":"50","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"6","customer_item_code":"180049","item_bar_code":"0","description":"FRYMATE 10KG CTN","unit_of_measure":[{"selling_quantity":"300","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"7","customer_item_code":"180064","item_bar_code":"0","description":"FRYMATE 5KG CTN","unit_of_measure":[{"selling_quantity":"300","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"8","customer_item_code":"180096","item_bar_code":"0","description":"MPISHI POA 10KG CTN","unit_of_measure":[{"selling_quantity":"300","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"9","customer_item_code":"180098","item_bar_code":"0","description":"MPISHI POA 12X1KG (T)","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"10","customer_item_code":"180091","item_bar_code":"0","description":"MPISHI POA 24X500G (T)","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"11","customer_item_code":"180100","item_bar_code":"0","description":"MPISHI POA 5KG","unit_of_measure":[{"selling_quantity":"300","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"12","customer_item_code":"180099","item_bar_code":"0","description":"MPISHI POA 6X2KG (T)","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"13","customer_item_code":"171064","item_bar_code":"0","description":"NDUME HERBAL SOAP 10X1KG","unit_of_measure":[{"selling_quantity":"500","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"14","customer_item_code":"170177","item_bar_code":"0","description":"NDUME SOAP 10X1KG (N)","unit_of_measure":[{"selling_quantity":"2000","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"15","customer_item_code":"170354","item_bar_code":"0","description":"NDUME SOAP 12X800 (N)","unit_of_measure":[{"selling_quantity":"500","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"16","customer_item_code":"180103","item_bar_code":"0","description":"POPCO V/01L 12X1LT","unit_of_measure":[{"selling_quantity":"30","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"17","customer_item_code":"180104","item_bar_code":"0","description":"POPCO V/01L 12X500ML","unit_of_measure":[{"selling_quantity":"30","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"18","customer_item_code":"180105","item_bar_code":"0","description":"POPCO V/01L 4X5L","unit_of_measure":[{"selling_quantity":"30","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"19","customer_item_code":"180106","item_bar_code":"0","description":"POPCO V/01L 6X2L","unit_of_measure":[{"selling_quantity":"50","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"20","customer_item_code":"180154","item_bar_code":"0","description":"SALIT 12X1L","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"21","customer_item_code":"180151","item_bar_code":"0","description":"SALIT 12X500ML","unit_of_measure":[{"selling_quantity":"50","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"22","customer_item_code":"180152","item_bar_code":"0","description":"SALIT 20L","unit_of_measure":[{"selling_quantity":"1000","selling_unit":"Jerry Cans"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"23","customer_item_code":"180148","item_bar_code":"0","description":"SALIT 4X5L","unit_of_measure":[{"selling_quantity":"50","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"24","customer_item_code":"180149","item_bar_code":"0","description":"SALIT 6X2L","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"25","customer_item_code":"180150","item_bar_code":"0","description":"SALIT 6X3L","unit_of_measure":[{"selling_quantity":"50","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"26","customer_item_code":"171174","item_bar_code":"0","description":"SAWA BUBBLE GUM 24X225G","unit_of_measure":[{"selling_quantity":"40","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"27","customer_item_code":"171183","item_bar_code":"0","description":"SAWA BUBBLE GUM 48X125G","unit_of_measure":[{"selling_quantity":"20","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"28","customer_item_code":"170882","item_bar_code":"0","description":"SAWA LEMON+HONEY 24X225G","unit_of_measure":[{"selling_quantity":"50","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"29","customer_item_code":"170848","item_bar_code":"0","description":"SAWA LIQUID H/WASH ALOE VERA 12X250ML","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"30","customer_item_code":"170850","item_bar_code":"0","description":"SAWA LIQUID H/WASH ORIGINAL 12X250ML","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"31","customer_item_code":"170849","item_bar_code":"0","description":"SAWA LIQUID H/WASH STRAWBERRY 12X250ML","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"32","customer_item_code":"171023","item_bar_code":"0","description":"SAWA SOAP HONEY 7LEMON 125G","unit_of_measure":[{"selling_quantity":"50","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"33","customer_item_code":"170949","item_bar_code":"0","description":"USHINDI LEMON D/WASHINGPASTE 6X400G BAND","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"34","customer_item_code":"170940","item_bar_code":"0","description":"USHINDI LEMON D/WASHINGPASTE 6X800G BAND","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"35","customer_item_code":"170936","item_bar_code":"0","description":"USHINDI ORANGE D/W LIQUID 6X400ML(BANDED","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"36","customer_item_code":"171214","item_bar_code":"0","description":"USHINDI ORANGE D/WASHINGPASTE 6X400G BAN","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"},{"serial_number":"37","customer_item_code":"171215","item_bar_code":"0","description":"USHINDI ORANGE D/WASHINGPASTE 6X800 BAND","unit_of_measure":[{"selling_quantity":"10","selling_unit":"Cartons"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"}],"currency":"KES","total_number_of_items":"37","total_number_of_extracted_items":"37","sub_total":"0","total_amount":"0","vat_no":"P0511032915"}', true);

        $action = new FunctionAction("", [$this, 'split'], ['method' => 'vertical_split', 'split_path' => "items", "criteria_path" => "total_weight", "limit" => 28000], '');

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
        $data = [
            "items" => []
        ];

        $mapping = [
            "\b\d+\s*(MG|G|GM|GRM|GMS|KG|KGS|PC|PCS|ML|L|LT|LTR|LTRS|M|X)?\b|[^\w\s]+|(\b|\d+)((?!BIO)[a-zA-Z]{1,3})\b" => "",
            "(\d+[X])" => ""
        ];

        $expectedData = [];

        $action = new FunctionAction("items.*.description", [$this, "transform"], ["regex_mapper", "args" => ["mappings" => $mapping, "is_case_sensitive" => false], "target_keys" => []], 'items.*.formatted_description');

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
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
                ["name" => "NAIVAS LIMURU", "id" => 1],
                ["name" => "NAIVAS KATANI- CHARLES MATHEKA", "id" => 2]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("", [$this, 'fuzzy_search'], ['search_key' => 'customer', 'match_key' => 'matched_customer','corpus' => ["path" => "customers_list"], "corpus_search_key" => "name", "corpus_id_key" => "id", "corpus_value_key" => "name", "master_data_type" => "customers", "similarity_threshold" => 20, 'number_of_matches' => 1, 'scorer' => 'tokenSetRatio']);

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
        $data = json_decode('{"purchase_order_number":"000621668","order_date":"2024-09-11","ordered_by_name":"STEPHEN KAHENYA","customer_name":{"original_value":"EASTLEIGH MATTRESSES LIMITED","matched_value":"Eastleigh Matresses Ltd : C00082","similarity":"85.962314393984","meta_data":{"master_data":"customers","value_key":"name","id_key":"customer_code","id":"C00082","value":"Eastleigh Matresses Ltd","other_details":{"customer_code":"C00082","name":"Eastleigh Matresses Ltd","pricelist_code":"5","status":"active"}}},"customer_email":"info@eastmatt.com","customer_phone":"","delivery_location":"KARTASI PRODUCTS LTD EASTLEIGH MATTRESSES LIMITED -KAJIADO","delivery_date":"2024-09-12","seller_name":"KARTASI PRODUCTS LIMITED","items":[{"serial_number":"1","customer_item_code":"65025","item_bar_code":"0","description":{"original_value":"AFRI BROWN TAPE 48MMX35MT #701","matched_value":"Afri 701 Tape Brown 48mmx35Mts 72Rolls : AF001020505","similarity":"87.054761837738","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF001020505","value":"Afri 701 Tape Brown 48mmx35Mts 72Rolls","other_details":{"product_code":"AF001020505","category":"FG Tapes","category_id":"107","description":"Afri 701 Tape Brown 48mmx35Mts 72Rolls","status":"Active","active_from":"","active_to":"","bar_code":"5034624605261","brand_name":"Afri","quantity_in_stock":"16.2084","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"72","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"72ROLLS","dimension":"48mmx35Mts","item_reference":"","book_size":"","similarity":"94"}}},"unit_of_measure":[{"selling_quantity":"24","selling_unit":"Pieces","descriptive_quantity":"24 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"72"},"unit_price":"49.65","total_price":"1191.6","brand_classification":"Afri","pack_size":"","dimension":"48MMX35MT","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"72"}],"converted_units":{"original_value":"24","original_unit":"pieces","converted_value":"0.33333","converted_unit":"ctn"}},{"serial_number":"2","customer_item_code":"65026","item_bar_code":"0","description":{"original_value":"AFRI BROWN TAPE 48MMX50MT #501","matched_value":"Afri 501 Tape Brown 48mmx50Mts 72Rolls : AF000020605","similarity":"87.34135942114","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF000020605","value":"Afri 501 Tape Brown 48mmx50Mts 72Rolls","other_details":{"product_code":"AF000020605","category":"FG Tapes","category_id":"107","description":"Afri 501 Tape Brown 48mmx50Mts 72Rolls","status":"Active","active_from":"","active_to":"","bar_code":"5034624603564","brand_name":"Afri","quantity_in_stock":"8.1257","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"72","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"72ROLLS","dimension":"48mmx50Mts","item_reference":"","book_size":"","similarity":"94"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"72"},"unit_price":"134","total_price":"1608","brand_classification":"Afri","pack_size":"","dimension":"48MMX50MT","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"72"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"0.16667","converted_unit":"ctn"}},{"serial_number":"3","customer_item_code":"65027","item_bar_code":"0","description":{"original_value":"AFRI PLAIN/CLEAR TAPE 12MMX35MTR #501","matched_value":"Afri 501 Tape Clear 12mmx35Mts 288Rolls : AF000000500","similarity":"78.192758091933","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF000000500","value":"Afri 501 Tape Clear 12mmx35Mts 288Rolls","other_details":{"product_code":"AF000000500","category":"FG Tapes","category_id":"107","description":"Afri 501 Tape Clear 12mmx35Mts 288Rolls","status":"Active","active_from":"","active_to":"","bar_code":"5034624600211","brand_name":"Afri","quantity_in_stock":"3.0659","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"288","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"288ROLLS","dimension":"12mmx35Mts","item_reference":"","book_size":"","similarity":"94"}}},"unit_of_measure":[{"selling_quantity":"60","selling_unit":"Pieces","descriptive_quantity":"60 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"288"},"unit_price":"23.44","total_price":"1406.52","brand_classification":"Afri","pack_size":"","dimension":"12MMX35MT","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"288"}],"converted_units":{"original_value":"60","original_unit":"pieces","converted_value":"0.20833","converted_unit":"ctn"}},{"serial_number":"4","customer_item_code":"65027","item_bar_code":"0","description":{"original_value":"AFRI PLAIN/CLEAR TAPE 18MMX35M #701","matched_value":"Afri 501 Tape Clear 18mmx35Mts 192Rolls : AF000000502","similarity":"76.069741404815","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF000000502","value":"Afri 501 Tape Clear 18mmx35Mts 192Rolls","other_details":{"product_code":"AF000000502","category":"FG Tapes","category_id":"107","description":"Afri 501 Tape Clear 18mmx35Mts 192Rolls","status":"Active","active_from":"","active_to":"","bar_code":"5034624600235","brand_name":"Afri","quantity_in_stock":"9.5052","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"192","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"192ROLLS","dimension":"18mmx35Mts","item_reference":"","book_size":"","similarity":"88"}}},"unit_of_measure":[{"selling_quantity":"48","selling_unit":"Pieces","descriptive_quantity":"48 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"192"},"unit_price":"18.62","total_price":"893.71","brand_classification":"Afri","pack_size":"","dimension":"18MMX35M","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"192"}],"converted_units":{"original_value":"48","original_unit":"pieces","converted_value":"0.25","converted_unit":"ctn"}},{"serial_number":"5","customer_item_code":"65203","item_bar_code":"0","description":{"original_value":"AFRI PLAIN/CLEAR TAPE 24MMX25MT 701","matched_value":"Afri 701 Tape CLEAR 24mmx25Mts 144Rolls : AF001020853","similarity":"82.866509801629","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF001020853","value":"Afri 701 Tape CLEAR 24mmx25Mts 144Rolls","other_details":{"product_code":"AF001020853","category":"FG Tapes","category_id":"107","description":"Afri 701 Tape CLEAR 24mmx25Mts 144Rolls","status":"Active","active_from":"","active_to":"","bar_code":"","brand_name":"Afri","quantity_in_stock":"0.1111","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"144","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"144ROLLS","dimension":"24mmx25Mts","item_reference":"","book_size":"","similarity":"94"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"144"},"unit_price":"17.72","total_price":"212.66","brand_classification":"Afri","pack_size":"","dimension":"24MMX25MT","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"144"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"0.08333","converted_unit":"ctn"}},{"serial_number":"6","customer_item_code":"65205","item_bar_code":"0","description":{"original_value":"AFRI PLAIN/CLEAR TAPE 24MMX35M #501","matched_value":"Afri 501 Tape Clear 24mmx35Mts 144Rolls : AF000000503","similarity":"79.938778410334","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF000000503","value":"Afri 501 Tape Clear 24mmx35Mts 144Rolls","other_details":{"product_code":"AF000000503","category":"FG Tapes","category_id":"107","description":"Afri 501 Tape Clear 24mmx35Mts 144Rolls","status":"Active","active_from":"","active_to":"","bar_code":"5034624600242","brand_name":"Afri","quantity_in_stock":"21.3662","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"144","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"144ROLLS","dimension":"24mmx35Mts","item_reference":"","book_size":"","similarity":"88"}}},"unit_of_measure":[{"selling_quantity":"24","selling_unit":"Pieces","descriptive_quantity":"24 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"144"},"unit_price":"46.9","total_price":"1125.6","brand_classification":"Afri","pack_size":"","dimension":"24MMX35M","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"144"}],"converted_units":{"original_value":"24","original_unit":"pieces","converted_value":"0.16667","converted_unit":"ctn"}},{"serial_number":"7","customer_item_code":"65026","item_bar_code":"0","description":{"original_value":"AFRI MASKING TAPE 12MMX50MT","matched_value":"Afri Masking Tape 12mmx50Mts 144Rolls : AF0040600","similarity":"84.038495065989","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF0040600","value":"Afri Masking Tape 12mmx50Mts 144Rolls","other_details":{"product_code":"AF0040600","category":"FG Tapes","category_id":"107","description":"Afri Masking Tape 12mmx50Mts 144Rolls","status":"Active","active_from":"","active_to":"","bar_code":"5034624606510","brand_name":"Afri","quantity_in_stock":"68.1594","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"144","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"144ROLLS","dimension":"12mmx50Mts","item_reference":"","book_size":"","similarity":"94"}}},"unit_of_measure":[{"selling_quantity":"24","selling_unit":"Pieces","descriptive_quantity":"24 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"144"},"unit_price":"50.5","total_price":"1212","brand_classification":"Afri","pack_size":"","dimension":"12MMX50MT","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"144"}],"converted_units":{"original_value":"24","original_unit":"pieces","converted_value":"0.16667","converted_unit":"ctn"}},{"serial_number":"8","customer_item_code":"65028","item_bar_code":"0","description":{"original_value":"AFRI MASKING TAPE 18MMX25MT","matched_value":"Afri Masking Tape 18mmx25Mts 96Rolls : AF0040402","similarity":"84.21746351112","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF0040402","value":"Afri Masking Tape 18mmx25Mts 96Rolls","other_details":{"product_code":"AF0040402","category":"FG Tapes","category_id":"107","description":"Afri Masking Tape 18mmx25Mts 96Rolls","status":"Active","active_from":"","active_to":"","bar_code":"5034624606138","brand_name":"Afri","quantity_in_stock":"47.8973","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"96","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"96ROLLS","dimension":"18mmx25Mts","item_reference":"","book_size":"","similarity":"94"}}},"unit_of_measure":[{"selling_quantity":"60","selling_unit":"Pieces","descriptive_quantity":"60 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"96"},"unit_price":"37.81","total_price":"2268.48","brand_classification":"Afri","pack_size":"","dimension":"18MMX25MT","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"96"}],"converted_units":{"original_value":"60","original_unit":"pieces","converted_value":"0.625","converted_unit":"ctn"}},{"serial_number":"9","customer_item_code":"65151","item_bar_code":"0","description":{"original_value":"AFRI MASKING TAPE 48MMX50MTR","matched_value":"Afri Masking Tape 48mmx50Mts 36Rolls : AF0040605","similarity":"82.961033624333","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF0040605","value":"Afri Masking Tape 48mmx50Mts 36Rolls","other_details":{"product_code":"AF0040605","category":"FG Tapes","category_id":"107","description":"Afri Masking Tape 48mmx50Mts 36Rolls","status":"Active","active_from":"","active_to":"","bar_code":"5034624606565","brand_name":"Afri","quantity_in_stock":"79.6679","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"36","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"36ROLLS","dimension":"48mmx50Mts","item_reference":"","book_size":"","similarity":"94"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"36"},"unit_price":"201.97","total_price":"2423.64","brand_classification":"Afri","pack_size":"","dimension":"48MMX50MT","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"36"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"0.33333","converted_unit":"ctn"}},{"serial_number":"10","customer_item_code":"16535","item_bar_code":"0","description":{"original_value":"BALL POINT MEDIUM 1.0MM BLACK PILOT","matched_value":"Pilot Medium Ball Point Pen 1.0mm Black Blue x2 Non-Retractable : TR012712","similarity":"74.757302414691","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"TR012712","value":"Pilot Medium Ball Point Pen 1.0mm Black Blue x2 Non-Retractable","other_details":{"product_code":"TR012712","category":"FG Traded","category_id":"109","description":"Pilot Medium Ball Point Pen 1.0mm Black Blue x2 Non-Retractable","status":"Active","active_from":"","active_to":"","bar_code":"","brand_name":"Pilot","quantity_in_stock":"96","sales_uom":"Set","quantity_conversion_uom":"SET","quantity_conversion":"1","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"1.0MM","dimension":"","item_reference":"","book_size":"","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"100","selling_unit":"Pieces","descriptive_quantity":"100 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"1000"},"unit_price":"18","total_price":"1800","brand_classification":"Pilot","pack_size":"1.0MM","dimension":"","item_reference":"","book_size":"","conversion_table":[{"from":"Set","to":"Pieces","factor":"1"}],"converted_units":{"original_value":"100","original_unit":"pieces","converted_value":"100","converted_unit":"set"}},{"serial_number":"11","customer_item_code":"16535","item_bar_code":"0","description":{"original_value":"BALL POINT MEDIUM 1.0MM BLUE PILOT","matched_value":"Pilot Medium Ball Point Pen 1.0mm Blue Blue x2 Non-Retractable : TR012706","similarity":"75.924735263826","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"TR012706","value":"Pilot Medium Ball Point Pen 1.0mm Blue Blue x2 Non-Retractable","other_details":{"product_code":"TR012706","category":"FG Traded","category_id":"109","description":"Pilot Medium Ball Point Pen 1.0mm Blue Blue x2 Non-Retractable","status":"Active","active_from":"","active_to":"","bar_code":"","brand_name":"Pilot","quantity_in_stock":"53","sales_uom":"Set","quantity_conversion_uom":"SET","quantity_conversion":"1","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"1.0MM","dimension":"","item_reference":"","book_size":"","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"200","selling_unit":"Pieces","descriptive_quantity":"200 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"1000"},"unit_price":"18","total_price":"3600","brand_classification":"Pilot","pack_size":"1.0MM","dimension":"","item_reference":"","book_size":"","conversion_table":[{"from":"Set","to":"Pieces","factor":"1"}],"converted_units":{"original_value":"200","original_unit":"pieces","converted_value":"200","converted_unit":"set"}},{"serial_number":"12","customer_item_code":"16506","item_bar_code":"0","description":{"original_value":"KARTASI BRAND ARTIST SKETCH PADS REF 161 A4","matched_value":"KARTASI BRAND Top Glued Artist Sketch Pads A4 PC 50Shts 2 DOZEN REF 161 : ST008001","similarity":"78.36442423118","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST008001","value":"KARTASI BRAND Top Glued Artist Sketch Pads A4 PC 50Shts 2 DOZEN REF 161","other_details":{"product_code":"ST008001","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Top Glued Artist Sketch Pads A4 PC 50Shts 2 DOZEN REF 161","status":"Active","active_from":"","active_to":"","bar_code":"5034624027018","brand_name":"Kartasi","quantity_in_stock":"9.1249","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"24","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"50SHEETS","dimension":"","item_reference":"161","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"24"},"unit_price":"139.75","total_price":"1677","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"161","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"24"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"0.5","converted_unit":"ctn"}},{"serial_number":"13","customer_item_code":"16506","item_bar_code":"0","description":{"original_value":"KARTASI BRAND ARTIST SKETCH PADS REF 162 A3","matched_value":"KARTASI BRAND Top Glued Artist Sketch Pads A3 PC 50Shts 1 DOZEN REF 162 : ST008002","similarity":"78.199587970668","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST008002","value":"KARTASI BRAND Top Glued Artist Sketch Pads A3 PC 50Shts 1 DOZEN REF 162","other_details":{"product_code":"ST008002","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Top Glued Artist Sketch Pads A3 PC 50Shts 1 DOZEN REF 162","status":"Active","active_from":"","active_to":"","bar_code":"5034624027025","brand_name":"Kartasi","quantity_in_stock":"20.4167","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"12","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"50SHEETS","dimension":"","item_reference":"162","book_size":"A3","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"12"},"unit_price":"279.6","total_price":"3355.2","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"162","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"12"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"1","converted_unit":"ctn"}},{"serial_number":"14","customer_item_code":"16506","item_bar_code":"0","description":{"original_value":"KARTASI BRAND CASH SALE BOOK 100X2 10 UP REF 322","matched_value":"Njema Dup. Cash Sale BOOK A4 10Up 100X2 2.5 DOZEN REF 322 : ST009038","similarity":"70.439823608385","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST009038","value":"Njema Dup. Cash Sale BOOK A4 10Up 100X2 2.5 DOZEN REF 322","other_details":{"product_code":"ST009038","category":"FG Stationery","category_id":"108","description":"Njema Dup. Cash Sale BOOK A4 10Up 100X2 2.5 DOZEN REF 322","status":"Active","active_from":"","active_to":"","bar_code":"","brand_name":"Njema","quantity_in_stock":"0","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"30","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"2.5DOZENS","dimension":"","item_reference":"322","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"10","selling_unit":"Pieces","descriptive_quantity":"10 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"9"},"unit_price":"244.46","total_price":"2444.6","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"322","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"30"}],"converted_units":{"original_value":"10","original_unit":"pieces","converted_value":"0.33333","converted_unit":"ctn"}},{"serial_number":"15","customer_item_code":"16507","item_bar_code":"0","description":{"original_value":"KARTASI BRAND CONDOLENCE BOOK REF 153","matched_value":"KARTASI BRAND Condolence BOOK S.Size 1 DOZEN REF 153 : ST010001","similarity":"80.189445635513","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST010001","value":"KARTASI BRAND Condolence BOOK S.Size 1 DOZEN REF 153","other_details":{"product_code":"ST010001","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Condolence BOOK S.Size 1 DOZEN REF 153","status":"Active","active_from":"","active_to":"","bar_code":"5034624013103","brand_name":"Kartasi","quantity_in_stock":"0.5","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"12","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"1DOZENS","dimension":"","item_reference":"153","book_size":"","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"12"},"unit_price":"339.1","total_price":"4069.2","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"153","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"12"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"1","converted_unit":"ctn"}},{"serial_number":"16","customer_item_code":"16507","item_bar_code":"0","description":{"original_value":"KARTASI BRAND COUNTER BOOK 1QUIRE H/5 REF 236","matched_value":"KARTASI BRAND Counter BOOK HALF SIZE HARD COVER 1 QUIRE 6 DOZEN REF 236 : ST001321","similarity":"82.023335961267","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST001321","value":"KARTASI BRAND Counter BOOK HALF SIZE HARD COVER 1 QUIRE 6 DOZEN REF 236","other_details":{"product_code":"ST001321","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Counter BOOK HALF SIZE HARD COVER 1 QUIRE 6 DOZEN REF 236","status":"Active","active_from":"","active_to":"","bar_code":"5034624015411","brand_name":"Kartasi","quantity_in_stock":"0","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"72","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"6DOZENS","dimension":"","item_reference":"236","book_size":"","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"72"},"unit_price":"104.82","total_price":"1257.84","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"236","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"72"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"0.16667","converted_unit":"ctn"}},{"serial_number":"17","customer_item_code":"16507","item_bar_code":"0","description":{"original_value":"KARTASI BRAND COUNTER BOOK 2QUIRE A4 REF 232","matched_value":"KARTASI BRAND Counter BOOK A4 HARD COVER 2 QUIRE 4 DOZEN REF 232 : ST001308","similarity":"88.889313628602","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST001308","value":"KARTASI BRAND Counter BOOK A4 HARD COVER 2 QUIRE 4 DOZEN REF 232","other_details":{"product_code":"ST001308","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Counter BOOK A4 HARD COVER 2 QUIRE 4 DOZEN REF 232","status":"Active","active_from":"","active_to":"","bar_code":"5034624015121","brand_name":"Kartasi","quantity_in_stock":"75.6457","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"48","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"4DOZENS","dimension":"","item_reference":"232","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"24","selling_unit":"Pieces","descriptive_quantity":"24 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"24"},"unit_price":"169.58","total_price":"4069.92","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"232","book_size":"A4","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"48"}],"converted_units":{"original_value":"24","original_unit":"pieces","converted_value":"0.5","converted_unit":"ctn"}},{"serial_number":"18","customer_item_code":"16507","item_bar_code":"0","description":{"original_value":"KARTASI BRAND COUNTER BOOK 4QUIRE A4 REF 234","matched_value":"KARTASI BRAND Counter BOOK A4 HARD COVER 4 QUIRE 2.5 DOZEN REF 234 : ST001310","similarity":"88.243197305888","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST001310","value":"KARTASI BRAND Counter BOOK A4 HARD COVER 4 QUIRE 2.5 DOZEN REF 234","other_details":{"product_code":"ST001310","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Counter BOOK A4 HARD COVER 4 QUIRE 2.5 DOZEN REF 234","status":"Active","active_from":"","active_to":"","bar_code":"5034624015145","brand_name":"Kartasi","quantity_in_stock":"59.5","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"30","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"2.5DOZENS","dimension":"","item_reference":"234","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"24","selling_unit":"Pieces","descriptive_quantity":"24 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"15"},"unit_price":"297","total_price":"7128","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"234","book_size":"A4","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"30"}],"converted_units":{"original_value":"24","original_unit":"pieces","converted_value":"0.8","converted_unit":"ctn"}},{"serial_number":"19","customer_item_code":"16507","item_bar_code":"0","description":{"original_value":"KARTASI BRAND COUNTER BOOK 6QUIRE A4 REF 235","matched_value":"KARTASI BRAND Counter BOOK A4 HARD COVER 6 QUIRE 1 DOZEN REF 235 : ST001306","similarity":"87.554141676442","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST001306","value":"KARTASI BRAND Counter BOOK A4 HARD COVER 6 QUIRE 1 DOZEN REF 235","other_details":{"product_code":"ST001306","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Counter BOOK A4 HARD COVER 6 QUIRE 1 DOZEN REF 235","status":"Active","active_from":"","active_to":"","bar_code":"5034624015169","brand_name":"Kartasi","quantity_in_stock":"13.1666","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"12","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"1DOZENS","dimension":"","item_reference":"235","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"12"},"unit_price":"434.5","total_price":"5214","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"235","book_size":"A4","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"12"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"1","converted_unit":"ctn"}},{"serial_number":"20","customer_item_code":"26516","item_bar_code":"0","description":{"original_value":"Exercise Book 200PGS HIT MANILLA SOLID/SINGLE LINE","matched_value":"Exercise Book Hit A4 200Pg CHIPBOARD SOLID/SINGLE LINE 5.5 DOZEN : EX11101000","similarity":"77.124114368803","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"EX11101000","value":"Exercise Book Hit A4 200Pg CHIPBOARD SOLID/SINGLE LINE 5.5 DOZEN","other_details":{"product_code":"EX11101000","category":"FG Exercise Books","category_id":"106","description":"Exercise Book Hit A4 200Pg CHIPBOARD SOLID/SINGLE LINE 5.5 DOZEN","status":"Active","active_from":"","active_to":"","bar_code":"5034624110314","brand_name":"Hit","quantity_in_stock":"125.8933","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"66","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"200PAGES","dimension":"","item_reference":"","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"126","selling_unit":"Pieces","descriptive_quantity":"126 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"126"},"unit_price":"65.33","total_price":"8231.58","brand_classification":"Hit","pack_size":"200PAGES","dimension":"","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"66"}],"converted_units":{"original_value":"126","original_unit":"pieces","converted_value":"1.90909","converted_unit":"ctn"}},{"serial_number":"21","customer_item_code":"26535","item_bar_code":"0","description":{"original_value":"Exercise Book 96PGS SQUARE/L HIT CHIPBOARD A4","matched_value":"Exercise Book Hit A4 96Pg CHIPBOARD SQUARE 10 DOZEN : EX11100601","similarity":"86.25018770702","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"EX11100601","value":"Exercise Book Hit A4 96Pg CHIPBOARD SQUARE 10 DOZEN","other_details":{"product_code":"EX11100601","category":"FG Exercise Books","category_id":"106","description":"Exercise Book Hit A4 96Pg CHIPBOARD SQUARE 10 DOZEN","status":"Active","active_from":"","active_to":"","bar_code":"5034624106324","brand_name":"Hit","quantity_in_stock":"35.7166","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"120","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"96PAGES","dimension":"","item_reference":"","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"48","selling_unit":"Pieces","descriptive_quantity":"48 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"120"},"unit_price":"63","total_price":"3024","brand_classification":"Hit","pack_size":"96PAGES","dimension":"","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"120"}],"converted_units":{"original_value":"48","original_unit":"pieces","converted_value":"0.4","converted_unit":"ctn"}},{"serial_number":"22","customer_item_code":"26515","item_bar_code":"0","description":{"original_value":"KARTASI BRAND Exercise Book DRAWING A3 40PG REF 053","matched_value":"Exercise Book KARTASI BRAND A3 40Pg MANILLA DRAWING 7 DOZEN : EX00330214","similarity":"78.45437759462","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"EX00330214","value":"Exercise Book KARTASI BRAND A3 40Pg MANILLA DRAWING 7 DOZEN","other_details":{"product_code":"EX00330214","category":"FG Exercise Books","category_id":"106","description":"Exercise Book KARTASI BRAND A3 40Pg MANILLA DRAWING 7 DOZEN","status":"Active","active_from":"","active_to":"","bar_code":"5034624002107","brand_name":"Kartasi","quantity_in_stock":"0","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"84","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"40PAGES","dimension":"","item_reference":"","book_size":"A3","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"84"},"unit_price":"98","total_price":"1176","brand_classification":"Kartasi","pack_size":"40PAGES","dimension":"","item_reference":"053","book_size":"A3","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"84"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"0.14286","converted_unit":"ctn"}},{"serial_number":"23","customer_item_code":"26507","item_bar_code":"0","description":{"original_value":"KARTASI BRAND Exercise Book DRAWING A4 20PGS REF 051","matched_value":"Exercise Book KARTASI BRAND A4 20Pg MACHINE GLAZED DRAWING 15 DOZEN : EX00110014","similarity":"75.49097675631","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"EX00110014","value":"Exercise Book KARTASI BRAND A4 20Pg MACHINE GLAZED DRAWING 15 DOZEN","other_details":{"product_code":"EX00110014","category":"FG Exercise Books","category_id":"106","description":"Exercise Book KARTASI BRAND A4 20Pg MACHINE GLAZED DRAWING 15 DOZEN","status":"Active","active_from":"","active_to":"","bar_code":"5034624000004","brand_name":"Kartasi","quantity_in_stock":"1.5785","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"180","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"20PAGES","dimension":"","item_reference":"","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"48","selling_unit":"Pieces","descriptive_quantity":"48 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"336"},"unit_price":"47.14","total_price":"2262.72","brand_classification":"Kartasi","pack_size":"20PAGES","dimension":"","item_reference":"051","book_size":"A4","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"180"}],"converted_units":{"original_value":"48","original_unit":"pieces","converted_value":"0.26667","converted_unit":"ctn"}},{"serial_number":"24","customer_item_code":"26507","item_bar_code":"0","description":{"original_value":"KARTASI BRAND Exercise Book HARD COVER 200PGS A5 SOLID/SINGLE LINE","matched_value":"KARTASI BRAND Executive NOTE BOOK A5 200Pg 1 DOZEN REF 480 : ST014003","similarity":"71.854462814269","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST014003","value":"KARTASI BRAND Executive NOTE BOOK A5 200Pg 1 DOZEN REF 480","other_details":{"product_code":"ST014003","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Executive NOTE BOOK A5 200Pg 1 DOZEN REF 480","status":"Active","active_from":"","active_to":"","bar_code":"5034624023669","brand_name":"Kartasi","quantity_in_stock":"6.1667","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"12","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"200PAGES","dimension":"","item_reference":"480","book_size":"A5","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"72"},"unit_price":"84.7","total_price":"1016.4","brand_classification":"Kartasi","pack_size":"200PAGES","dimension":"","item_reference":"","book_size":"A5","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"12"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"1","converted_unit":"ctn"}},{"serial_number":"25","customer_item_code":"26516","item_bar_code":"0","description":{"original_value":"KARTASI BRAND EXECUTIVE CONDOLENCE BOOK REF 155","matched_value":"KARTASI BRAND Condolence BOOK S.Size Executive 0.5 DOZEN REF 155 : ST010002","similarity":"81.84685664015","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST010002","value":"KARTASI BRAND Condolence BOOK S.Size Executive 0.5 DOZEN REF 155","other_details":{"product_code":"ST010002","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Condolence BOOK S.Size Executive 0.5 DOZEN REF 155","status":"Active","active_from":"","active_to":"","bar_code":"5034624013110","brand_name":"Kartasi","quantity_in_stock":"1","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"6","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"0.5DOZENS","dimension":"","item_reference":"155","book_size":"","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"6","selling_unit":"Pieces","descriptive_quantity":"6 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"6"},"unit_price":"910.79","total_price":"5464.74","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"155","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"6"}],"converted_units":{"original_value":"6","original_unit":"pieces","converted_value":"1","converted_unit":"ctn"}},{"serial_number":"26","customer_item_code":"26507","item_bar_code":"0","description":{"original_value":"KARTASI BRAND FIELD NOTE BOOK 96PG A5 REF 151","matched_value":"KARTASI BRAND Field NOTE BOOK A5 CHIPBOARD 96Pg 3 DOZEN REF 151 : ST012004","similarity":"86.005518276765","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST012004","value":"KARTASI BRAND Field NOTE BOOK A5 CHIPBOARD 96Pg 3 DOZEN REF 151","other_details":{"product_code":"ST012004","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Field NOTE BOOK A5 CHIPBOARD 96Pg 3 DOZEN REF 151","status":"Active","active_from":"","active_to":"","bar_code":"","brand_name":"Kartasi","quantity_in_stock":"14.6667","sales_uom":"Ctn","quantity_conversion_uom":"","quantity_conversion":"36","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"96PAGES","dimension":"","item_reference":"151","book_size":"A5","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"36"},"unit_price":"56.53","total_price":"678.36","brand_classification":"Kartasi","pack_size":"96PAGES","dimension":"","item_reference":"151","book_size":"A5","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"36"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"0.33333","converted_unit":"ctn"}},{"serial_number":"27","customer_item_code":"26507","item_bar_code":"0","description":{"original_value":"KARTASI BRAND JOURNAL BOOK A4 2 QUIRE REF 222","matched_value":"KARTASI BRAND Journal BOOK A4 HARD COVER 2 QUIRE 2.5 DOZEN REF 222 : ST003008","similarity":"91.790828680136","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST003008","value":"KARTASI BRAND Journal BOOK A4 HARD COVER 2 QUIRE 2.5 DOZEN REF 222","other_details":{"product_code":"ST003008","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Journal BOOK A4 HARD COVER 2 QUIRE 2.5 DOZEN REF 222","status":"Active","active_from":"","active_to":"","bar_code":"","brand_name":"Kartasi","quantity_in_stock":"0","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"30","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"2.5DOZENS","dimension":"","item_reference":"222","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"12"},"unit_price":"245.21","total_price":"2942.52","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"222","book_size":"A4","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"30"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"0.4","converted_unit":"ctn"}},{"serial_number":"28","customer_item_code":"26507","item_bar_code":"0","description":{"original_value":"KARTASI BRAND LEDGER BOOK A4 1 QUIRE REF 216","matched_value":"KARTASI BRAND Ledger BOOK A4 HARD COVER 1 QUIRE 4.5 DOZEN REF 216 : ST001007","similarity":"90.660503029619","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST001007","value":"KARTASI BRAND Ledger BOOK A4 HARD COVER 1 QUIRE 4.5 DOZEN REF 216","other_details":{"product_code":"ST001007","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Ledger BOOK A4 HARD COVER 1 QUIRE 4.5 DOZEN REF 216","status":"Active","active_from":"","active_to":"","bar_code":"","brand_name":"Kartasi","quantity_in_stock":"8.1666","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"48","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"4.5DOZENS","dimension":"","item_reference":"216","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"18"},"unit_price":"149.71","total_price":"1796.5","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"216","book_size":"A4","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"48"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"0.25","converted_unit":"ctn"}},{"serial_number":"29","customer_item_code":"26513","item_bar_code":"0","description":{"original_value":"KARTASI BRAND LETTER Y BOOK A5 2 QUIRE REF 157","matched_value":"KARTASI BRAND Letter Delivery BOOK A5 2 QUIRE 1 DOZEN REF 157 : ST017002","similarity":"82.727181422109","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST017002","value":"KARTASI BRAND Letter Delivery BOOK A5 2 QUIRE 1 DOZEN REF 157","other_details":{"product_code":"ST017002","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Letter Delivery BOOK A5 2 QUIRE 1 DOZEN REF 157","status":"Active","active_from":"","active_to":"","bar_code":"5034624013165","brand_name":"Kartasi","quantity_in_stock":"0.0833","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"12","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"1DOZENS","dimension":"","item_reference":"157","book_size":"A5","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"12"},"unit_price":"260.33","total_price":"3124","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"157","book_size":"A5","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"12"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"1","converted_unit":"ctn"}},{"serial_number":"30","customer_item_code":"36516","item_bar_code":"0","description":{"original_value":"KARTASI BRAND BOX FILE (LEVER ARCH) BLACK REF 142 5 - 10","matched_value":"KARTASI BRAND Box File A4 PVC/PAP Black WO/I 10Pcs REF 1426) : ST061304","similarity":"82.646792540014","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST061304","value":"KARTASI BRAND Box File A4 PVC/PAP Black WO/I 10Pcs REF 1426)","other_details":{"product_code":"ST061304","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Box File A4 PVC/PAP Black WO/I 10Pcs REF 1426)","status":"Active","active_from":"","active_to":"","bar_code":"5034624200107","brand_name":"Kartasi","quantity_in_stock":"3","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"10","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"10PIECES","dimension":"","item_reference":"142","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"10","selling_unit":"Pieces","descriptive_quantity":"10 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"10"},"unit_price":"187","total_price":"1870","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"142","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"10"}],"converted_units":{"original_value":"10","original_unit":"pieces","converted_value":"1","converted_unit":"ctn"}},{"serial_number":"31","customer_item_code":"36516","item_bar_code":"0","description":{"original_value":"KARTASI BRAND BOX FILE (LEVER ARCH) BLUE REF 142 5 - 01","matched_value":"KARTASI BRAND Box File A4 PVC/PAP Blue WO/I 10Pcs REF 1426) : ST061310","similarity":"80.524573444572","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST061310","value":"KARTASI BRAND Box File A4 PVC/PAP Blue WO/I 10Pcs REF 1426)","other_details":{"product_code":"ST061310","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Box File A4 PVC/PAP Blue WO/I 10Pcs REF 1426)","status":"Active","active_from":"","active_to":"","bar_code":"","brand_name":"Kartasi","quantity_in_stock":"0","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"10","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"10PIECES","dimension":"","item_reference":"142","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"10","selling_unit":"Pieces","descriptive_quantity":"10 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"10"},"unit_price":"187","total_price":"1870","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"142","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"10"}],"converted_units":{"original_value":"10","original_unit":"pieces","converted_value":"1","converted_unit":"ctn"}},{"serial_number":"32","customer_item_code":"36508","item_bar_code":"0","description":{"original_value":"KARTASI BRAND MUSTER ROLL A4 2 QUIRE REF 259","matched_value":"KARTASI BRAND Muster Roll A4 HARD COVER 2 QUIRE 1 DOZEN REF 259 : ST020002","similarity":"85.423058809531","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST020002","value":"KARTASI BRAND Muster Roll A4 HARD COVER 2 QUIRE 1 DOZEN REF 259","other_details":{"product_code":"ST020002","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Muster Roll A4 HARD COVER 2 QUIRE 1 DOZEN REF 259","status":"Active","active_from":"","active_to":"","bar_code":"5034624022129","brand_name":"Kartasi","quantity_in_stock":"1","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"12","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"1DOZENS","dimension":"","item_reference":"259","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"6","selling_unit":"Pieces","descriptive_quantity":"6 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"6"},"unit_price":"289.27","total_price":"1735.62","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"259","book_size":"A4","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"12"}],"converted_units":{"original_value":"6","original_unit":"pieces","converted_value":"0.5","converted_unit":"ctn"}},{"serial_number":"33","customer_item_code":"36516","item_bar_code":"0","description":{"original_value":"KARTASI BRAND NEW GENERATION N/8 A6 REF 476","matched_value":"KARTASI BRAND Side Spiral NEW GENERATION A6 GP 80Shts 2 DOZEN REF 476 : ST027001","similarity":"68.75616088876","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST027001","value":"KARTASI BRAND Side Spiral NEW GENERATION A6 GP 80Shts 2 DOZEN REF 476","other_details":{"product_code":"ST027001","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Side Spiral NEW GENERATION A6 GP 80Shts 2 DOZEN REF 476","status":"Active","active_from":"","active_to":"","bar_code":"5034624023492","brand_name":"Kartasi","quantity_in_stock":"405.1248","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"24","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"80SHEETS","dimension":"","item_reference":"476","book_size":"A6","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"60","selling_unit":"Pieces","descriptive_quantity":"60 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"24"},"unit_price":"82.5","total_price":"4950","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"476","book_size":"A6","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"24"}],"converted_units":{"original_value":"60","original_unit":"pieces","converted_value":"2.5","converted_unit":"ctn"}},{"serial_number":"34","customer_item_code":"36516","item_bar_code":"0","description":{"original_value":"KARTASI BRAND NEW GENERATION NOTE BOOK A5 REF 477","matched_value":"KARTASI BRAND Side Spiral NEW GENERATION A5 GP 80Shts 2 DOZEN REF 477 : ST027002","similarity":"78.089709002293","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST027002","value":"KARTASI BRAND Side Spiral NEW GENERATION A5 GP 80Shts 2 DOZEN REF 477","other_details":{"product_code":"ST027002","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Side Spiral NEW GENERATION A5 GP 80Shts 2 DOZEN REF 477","status":"Active","active_from":"","active_to":"","bar_code":"5034624023638","brand_name":"Kartasi","quantity_in_stock":"0.1249","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"24","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"80SHEETS","dimension":"","item_reference":"477","book_size":"A5","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"48","selling_unit":"Pieces","descriptive_quantity":"48 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"24"},"unit_price":"158.1","total_price":"7588.8","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"477","book_size":"A5","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"24"}],"converted_units":{"original_value":"48","original_unit":"pieces","converted_value":"2","converted_unit":"ctn"}},{"serial_number":"35","customer_item_code":"36508","item_bar_code":"0","description":{"original_value":"KARTASI BRAND SCHOOL DIARY REF 254 A5","matched_value":"KARTASI BRAND Sch. Diary A5 56Pg 3 DOZEN REF 254 : ST024003","similarity":"82.618543295589","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST024003","value":"KARTASI BRAND Sch. Diary A5 56Pg 3 DOZEN REF 254","other_details":{"product_code":"ST024003","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Sch. Diary A5 56Pg 3 DOZEN REF 254","status":"Active","active_from":"","active_to":"","bar_code":"5034624013813","brand_name":"Kartasi","quantity_in_stock":"1.6392","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"36","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"56PAGES","dimension":"","item_reference":"254","book_size":"A5","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"36"},"unit_price":"53.47","total_price":"641.64","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"254","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"36"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"0.33333","converted_unit":"ctn"}},{"serial_number":"36","customer_item_code":"36523","item_bar_code":"0","description":{"original_value":"SHORT HAND N/BOOK A5 REF 428 HIT","matched_value":"KARTASI BRAND Top Spiral SHORTHAND NOTE BOOK A5 CHIPBOARD 50Shts 20 DOZEN REF 428 : ST071207","similarity":"64.265149465259","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST071207","value":"KARTASI BRAND Top Spiral SHORTHAND NOTE BOOK A5 CHIPBOARD 50Shts 20 DOZEN REF 428","other_details":{"product_code":"ST071207","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Top Spiral SHORTHAND NOTE BOOK A5 CHIPBOARD 50Shts 20 DOZEN REF 428","status":"Active","active_from":"","active_to":"","bar_code":"5034624023584","brand_name":"Hit","quantity_in_stock":"25.8833","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"240","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"50SHEETS","dimension":"","item_reference":"428","book_size":"A5","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"48","selling_unit":"Pieces","descriptive_quantity":"48 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"48"},"unit_price":"40.13","total_price":"1926.24","brand_classification":"Hit","pack_size":"","dimension":"","item_reference":"428","book_size":"A5","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"240"}],"converted_units":{"original_value":"48","original_unit":"pieces","converted_value":"0.2","converted_unit":"ctn"}},{"serial_number":"37","customer_item_code":"36508","item_bar_code":"0","description":{"original_value":"KARTASI BRAND SQUARE RULED PAD REF 131","matched_value":"KARTASI BRAND SQUARE L/Leaf Pads A4 AP 70 Shts 2 DOZEN REF 131 : ST018003","similarity":"77.550715062002","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST018003","value":"KARTASI BRAND SQUARE L/Leaf Pads A4 AP 70 Shts 2 DOZEN REF 131","other_details":{"product_code":"ST018003","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND SQUARE L/Leaf Pads A4 AP 70 Shts 2 DOZEN REF 131","status":"Active","active_from":"","active_to":"","bar_code":"5034624027421","brand_name":"Hit","quantity_in_stock":"33.0417","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"24","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"70SHEETS","dimension":"","item_reference":"131","book_size":"A4","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"24"},"unit_price":"114.58","total_price":"1375","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"131","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"24"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"0.5","converted_unit":"ctn"}},{"serial_number":"38","customer_item_code":"36508","item_bar_code":"0","description":{"original_value":"KARTASI BRAND VISITORS BOOK REF 152","matched_value":"KARTASI BRAND Visitors BOOK S.Size 1 DOZEN REF 152 : ST030001","similarity":"87.302708665821","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"ST030001","value":"KARTASI BRAND Visitors BOOK S.Size 1 DOZEN REF 152","other_details":{"product_code":"ST030001","category":"FG Stationery","category_id":"108","description":"KARTASI BRAND Visitors BOOK S.Size 1 DOZEN REF 152","status":"Active","active_from":"","active_to":"","bar_code":"5034624013202","brand_name":"Kartasi","quantity_in_stock":"10","sales_uom":"Ctn","quantity_conversion_uom":"PCS","quantity_conversion":"12","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"1DOZENS","dimension":"","item_reference":"152","book_size":"","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"12","selling_unit":"Pieces","descriptive_quantity":"12 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"12"},"unit_price":"339","total_price":"4068","brand_classification":"Kartasi","pack_size":"","dimension":"","item_reference":"152","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"12"}],"converted_units":{"original_value":"12","original_unit":"pieces","converted_value":"1","converted_unit":"ctn"}},{"serial_number":"39","customer_item_code":"36503","item_bar_code":"0","description":{"original_value":"AFRI PLAIN/CLEAR TAPE 12MMX35MTS #701","matched_value":"Afri 501 Tape Clear 12mmx35Mts 288Rolls : AF000000500","similarity":"76.850486441552","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF000000500","value":"Afri 501 Tape Clear 12mmx35Mts 288Rolls","other_details":{"product_code":"AF000000500","category":"FG Tapes","category_id":"107","description":"Afri 501 Tape Clear 12mmx35Mts 288Rolls","status":"Active","active_from":"","active_to":"","bar_code":"5034624600211","brand_name":"Afri","quantity_in_stock":"3.0659","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"288","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"288ROLLS","dimension":"12mmx35Mts","item_reference":"","book_size":"","similarity":"100"}}},"unit_of_measure":[{"selling_quantity":"48","selling_unit":"Pieces","descriptive_quantity":"48 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"288"},"unit_price":"12.41","total_price":"595.73","brand_classification":"Afri","pack_size":"","dimension":"12MMX35MTS","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"288"}],"converted_units":{"original_value":"48","original_unit":"pieces","converted_value":"0.16667","converted_unit":"ctn"}},{"serial_number":"40","customer_item_code":"46503","item_bar_code":"0","description":{"original_value":"AFRI PLAIN/CLEAR TAPE 12MMX50MT #501","matched_value":"Afri 501 Tape Clear 12mmx50Mts 288Rolls : AF000000600","similarity":"80.670106797844","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF000000600","value":"Afri 501 Tape Clear 12mmx50Mts 288Rolls","other_details":{"product_code":"AF000000600","category":"FG Tapes","category_id":"107","description":"Afri 501 Tape Clear 12mmx50Mts 288Rolls","status":"Active","active_from":"","active_to":"","bar_code":"5034624600518","brand_name":"Afri","quantity_in_stock":"22.08","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"288","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"288ROLLS","dimension":"12mmx50Mts","item_reference":"","book_size":"","similarity":"94"}}},"unit_of_measure":[{"selling_quantity":"24","selling_unit":"Pieces","descriptive_quantity":"24 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"288"},"unit_price":"33.5","total_price":"804","brand_classification":"Afri","pack_size":"","dimension":"12MMX50MT","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"288"}],"converted_units":{"original_value":"24","original_unit":"pieces","converted_value":"0.08333","converted_unit":"ctn"}},{"serial_number":"41","customer_item_code":"46503","item_bar_code":"0","description":{"original_value":"AFRI PLAIN/CLEAR TAPE 24MMX35M #701","matched_value":"Afri 701 Tape CLEAR 24mmx35Mts 144Rolls : AF001010500","similarity":"81.787936577158","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF001010500","value":"Afri 701 Tape CLEAR 24mmx35Mts 144Rolls","other_details":{"product_code":"AF001010500","category":"FG Tapes","category_id":"107","description":"Afri 701 Tape CLEAR 24mmx35Mts 144Rolls","status":"Active","active_from":"","active_to":"","bar_code":"","brand_name":"Afri","quantity_in_stock":"0","sales_uom":"Ctn","quantity_conversion_uom":"","quantity_conversion":"144","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"144ROLLS","dimension":"24mmx35Mts","item_reference":"","book_size":"","similarity":"88"}}},"unit_of_measure":[{"selling_quantity":"36","selling_unit":"Pieces","descriptive_quantity":"36 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"144"},"unit_price":"24.83","total_price":"893.88","brand_classification":"Afri","pack_size":"","dimension":"24MMX35M","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"144"}],"converted_units":{"original_value":"36","original_unit":"pieces","converted_value":"0.25","converted_unit":"ctn"}},{"serial_number":"42","customer_item_code":"46503","item_bar_code":"0","description":{"original_value":"AFRI PLAIN/CLEAR TAPE 48MMX35M #701","matched_value":"Afri 501 Tape Clear 48mmx35Mts 72Rolls : AF000000505","similarity":"76.747130990016","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF000000505","value":"Afri 501 Tape Clear 48mmx35Mts 72Rolls","other_details":{"product_code":"AF000000505","category":"FG Tapes","category_id":"107","description":"Afri 501 Tape Clear 48mmx35Mts 72Rolls","status":"Active","active_from":"","active_to":"","bar_code":"5034624600266","brand_name":"Afri","quantity_in_stock":"41.9166","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"72","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"72ROLLS","dimension":"48mmx35Mts","item_reference":"","book_size":"","similarity":"88"}}},"unit_of_measure":[{"selling_quantity":"48","selling_unit":"Pieces","descriptive_quantity":"48 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"72"},"unit_price":"49.66","total_price":"2383.68","brand_classification":"Afri","pack_size":"","dimension":"48MMX35M","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"72"}],"converted_units":{"original_value":"48","original_unit":"pieces","converted_value":"0.66667","converted_unit":"ctn"}},{"serial_number":"43","customer_item_code":"46503","item_bar_code":"0","description":{"original_value":"AFRI MASKING TAPE 12MMX25MT","matched_value":"Afri Masking Tape 12mmx25Mts 144Rolls : AF0040400","similarity":"83.737887365737","meta_data":{"master_data":"all products","value_key":"description","id_key":"product_code","id":"AF0040400","value":"Afri Masking Tape 12mmx25Mts 144Rolls","other_details":{"product_code":"AF0040400","category":"FG Tapes","category_id":"107","description":"Afri Masking Tape 12mmx25Mts 144Rolls","status":"Active","active_from":"","active_to":"","bar_code":"5034624606114","brand_name":"Afri","quantity_in_stock":"129.3508","sales_uom":"Ctn","quantity_conversion_uom":"ROLLS","quantity_conversion":"144","uom_list":[{"uom_code":"-1","uom_name":"Manual","uom_quantity":"1.000000"}],"pack_size":"144ROLLS","dimension":"12mmx25Mts","item_reference":"","book_size":"","similarity":"94"}}},"unit_of_measure":[{"selling_quantity":"48","selling_unit":"Pieces","descriptive_quantity":"48 Pieces"}],"pack_configuration":{"pack_size":"","unit_size":"144"},"unit_price":"25.2","total_price":"1209.6","brand_classification":"Afri","pack_size":"","dimension":"12MMX25MT","item_reference":"","book_size":"","conversion_table":[{"from":"Ctn","to":"Pieces","factor":"144"}],"converted_units":{"original_value":"48","original_unit":"pieces","converted_value":"0.33333","converted_unit":"ctn"}}],"currency":"KES","total_number_of_items":"43","total_number_of_extracted_items":"43","sub_total":"0","total_amount":"0","vat_no":"","workflow_list_position":"0","sender_details":{"sent_by_name":"","sent_by_phone":"","sent_by_email":"","sent_by_email_domain":""},"extraction_data":{"document":{"link":"https://d2znkbrywkld08.cloudfront.net/1726127716_75.pdf"},"document_text_content":"","document_text":"EH\nEASTMATT\nPURCHASE ORDER\nEASTLEIGH MATTRESSES LIMITED\nPO BOX 54816 00200 NAIROBI NAIROBI\nMore Saving. Better Living\nTel: Fax:\n0 000006 216689\nEmail info@eastmatt.com\nOrder No: 000621668 Date Created: 11/09/2024  Date Placed: 11/09/2024  Requisitioner: STEPHEN KAHENYA\nTO\tSHIP TO: KARTASI PRODUCTS LTD\tEASTLEIGH MATTRESSES LIMITED -KAJIADOPO BOX 54816 - 00200 \n|1| Item Code | Description | SELLING QUANTITY | Price | TOTAL PRICE|\n|2| 1.65025 | AFRI BROWN TAPE 48MMX35MT #701 [Packaging: 0.33 X 72] | 24.00 Pieces | 49.65 | 1,191.60|\n|3| 2.65026 | AFRI BROWN TAPE 48MMX50MT #501 [Packaging: 0.17 X 72] | 12.00 Pieces | 134 | 1,608.00|\n|4| 3.65027 | AFRI CLEAR TAPE 12MMX35MTR #501 [Packaging: 0.21 X 288] | 60.00 Pieces | 23.44 | 1,406.52|\n|5| 4.65027 | AFRI CLEAR TAPE 18MMX35M #701 [Packaging: 0.25 X 192] | 48.00 Pieces | 18.62 | 893.71|\n|6| 5.65203 | AFRI CLEAR TAPE 24MMX25MT 701 [Packaging: 0.08 X 144] | 12.00 Pieces | 17.72 | 212.66|\n|7| 6.65205 | AFRI CLEAR TAPE 24MMX35M #501 [Packaging: 0.17 X 144] | 24.00 Pieces | 46.9 | 1,125.60|\n|8| 7.65026 | AFRI MASKING TAPE 12MMX50MT [Packaging: 0.17 X 144] | 24.00 Pieces | 50.5 | 1,212.00|\n|9| 8.65028 | AFRI MASKING TAPE 18MMX25MT [Packaging: 0.63 X 96] | 60.00 Pieces | 37.81 | 2,268.48|\n|10| 9.65151 | AFRI MASKING TAPE 48MMX50MTR [Packaging: 0.33 X 36] | 12.00 Pieces | 201.97 | 2,423.64|\n|11| 10.6535 | BALL POINT MEDIUM 1.0MM BLACK PILOT [Packaging: 0.10 X 1,000] | 100.00 Pieces | 18 | 1,800.00|\n|12| 11.6535 | BALL POINT MEDIUM 1.0MM BLUE PILOT [Packaging: 0.20 X 1,000] | 200.00 Pieces | 18 | 3,600.00|\n|13| 12.6506 | KB ARTIST SKETCH PADS #161 A4 [Packaging: 0.50 X 24] | 12.00 Pieces | 139.75 | 1,677.00|\n|14| 13.6506 | KB ARTIST SKETCH PADS #162 A3 [Packaging: 1.00 X 12] | 12.00 Pieces | 279.6 | 3,355.20|\n|15| 14.6506 | KB CASH SALE BK 100X2 10 UP #322 [Packaging: 1.11 X 9] | 10.00 Pieces | 244.46 | 2,444.60|\n|16| 15.6507 | KB CONDOLENCE BOOK #153 [Packaging: 1.00 X 12] | 12.00 Pieces | 339.1 | 4,069.20|\n|17| 16.6507 | KB COUNTER BOOK 1QUIRE H/5 #236 [Packaging: 0.17 X 72] | 12.00 Pieces | 104.82 | 1,257.84|\n|18| 17.6507 | KB COUNTER BOOK 2QUIRE A4 232 [Packaging: 1.00 X 24] | 24.00 Pieces | 169.58 | 4,069.92|\n|19| 18.6507 | KB COUNTER BOOK 4QUIRE A4 #234 [Packaging: 1.60 X 15] | 24.00 Pieces | 297 | 7,128.00|\n|20| 19.6507 | KB COUNTER BOOK 6QUIRE A4 #235 [Packaging: 1.00 X 12] | 12.00 Pieces | 434.5 | 5,214.00|\n|21| 20.6516 | KB EX BOOK 200PGS HIT MAN S/L [Packaging: 1.00 X 126] | 126.00 Pieces | 65.33 | 8,231.58|\n|22| 21.6535 | KB EX BOOK 96PGS SQ/L HIT CHIPBOARD A4 [Packaging: 0.40 X 120] | 48.00 Pieces | 63 | 3,024.00|\n|23| 22.6515 | KB EX BOOK DRAWING A3 40PG #053 [Packaging: 0.14 X 84] | 12.00 Pieces | 98 | 1,176.00|\n|24| 23.6507 | KB EX BOOK DRAWING A4 20PGS #051 [Packaging: 0.14 X 336] | 48.00 Pieces | 47.14 | 2,262.72|\n|25| 24.6507 | KB EX BOOK H/COVER 200PGS A5 S/L [Packaging: 0.17 X 72] | 12.00 Pieces | 84.7 | 1,016.40|\n|26| 25.6516 | KB EXECUTIVE CONDOLENCE BK #155 [Packaging: 1.00 X 6] | 6.00 Pieces | 910.79 | 5,464.74|\n|27| 26.6507 | KB FIELD NOTE BOOK 96PG A5 #151 [Packaging: 0.33 X 36] | 12.00 Pieces | 56.53 | 678.36|\n|28| 27.6507 | KB JOURNAL BK A4 2 QUIRE #222 [Packaging: 1.00 X 12] | 12.00 Pieces | 245.21 | 2,942.52|\n|29| 28.6507 | KB LEDGER BOOK A4 1 QUIRE #216 [Packaging: 0.67 X 18] | 12.00 Pieces | 149.71 | 1,796.50|\n|30| 29.6513 | KB LETTER DELIVERY BK A5 2Q #157 [Packaging: 1.00 X 12] | 12.00 Pieces | 260.33 | 3,124.00|\n|31| 30.6516 | KB LEVER ARCH BLACK #1425-10 [Packaging: 1.00 X 10] | 10.00 Pieces | 187 | 1,870.00|\n|32| 31.6516 | KB LEVER ARCH BLUE #1425-01 [Packaging: 1.00 X 10] | 10.00 Pieces | 187 | 1,870.00|\n|33| 32.6508 | KB MUSTER ROLL A4 2 QUIRE #259 [Packaging: 1.00 X 6] | 6.00 Pieces | 289.27 | 1,735.62|\n|34| 33.6516 | KB NEW GENERATION N/8 A6 #476 [Packaging: 2.50 X 24] | 60.00 Pieces | 82.5 | 4,950.00|\n|35| 34.6516 | KB NEW GENERATION NOTE BOOK A5 #477 [Packaging: 2.00 X 24] | 48.00 Pieces | 158.1 | 7,588.80|\n|36| 35.6508 | KB SCHOOL DIARY #254 A5 [Packaging: 0.33 X 36] | 12.00 Pieces | 53.47 | 641.64|\n|37| 36.6523 | KB SHORT HAND N/BK A5 #428 HIT [Packaging: 1.00 X 48] | 48.00 Pieces | 40.13 | 1,926.24|\n|38| 37.6508 | KB SQUARE RULED PAD#131 [Packaging: 0.50 X 24] | 12.00 Pieces | 114.58 | 1,375.00|\n|39| 38.6508 | KB VISITORS BOOK #152 [Packaging: 1.00 X 12] | 12.00 Pieces | 339 | 4,068.00|\n|40| 39.6503 | AFRI CLEAR TAPE 12MMX35MTS #701 [Packaging: 0.17 X 288] | 48.00 Pieces | 12.41 | 595.73|\n|41| 40.6503 | AFRI CLEAR TAPE 12MMX50MT #501 [Packaging: 0.08 X 288] | 24.00 Pieces | 33.5 | 804.00|\n|42| 41.6503 | AFRI CLEAR TAPE 24MMX35M #701 [Packaging: 0.25 X 144] | 36.00 Pieces | 24.83 | 893.88|\n|43| 42.6503 | AFRI CLEAR TAPE 48MMX35M #701 [Packaging: 0.67 X 72] | 48.00 Pieces | 49.66 | 2,383.68|\n|44| 43.6503 | AFRI MASKING TAPE 12MMX25MT [Packaging: 0.33 X 144] | 48.00 Pieces | 25.2 | 1,209.60|\nMore Saving. Better Living\n Report lateTime: Thursday, September 12, 2024\nVortexERP ver .766\n","subscribed_mailbox_email":""},"current_date_time_string":"now","current_date_time":"2024-09-12"}', true);

        $expectedData = [];

        $action = new FunctionAction("", [$this, 'flatten_and_expand'], []);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testCollapseData()
    {
        $data = json_decode('[{"entity_name":"customer_name","exported":{"value":"C00048","description":"Chandarana Supermarket Ltd"},"final":{"value":"C00048","description":"Chandarana Supermarket Ltd"},"original_value":"CHANDARANA SUPERMARKET LTD"},{"entity_name":"delivery_location","exported":{"value":"Riverside Square","description":""},"final":{"value":"Chandarana Supermarket Ltd","description":""},"original_value":"Riverside Square"},{"entity_name":"items.0.serial_number","exported":{"value":"1","description":""},"final":{"value":"0","description":""},"original_value":"1"},{"entity_name":"items.0.description","exported":{"value":"ST001310","description":"5034624015145"},"final":{"value":"ST001310","description":"KB Counter Bk A4 HC 4Q 2.5Dz (234)"},"original_value":"KARTASI COUNTER BOOK A4 4QUIRE REF 234"},{"entity_name":"items.0.converted_units.converted_value","exported":{"value":"0.5","description":""},"final":{"value":"0.5","description":""},"original_value":"0.5"},{"entity_name":"items.1.serial_number","exported":{"value":"2","description":""},"final":{"value":"1","description":""},"original_value":"2"},{"entity_name":"items.1.description","exported":{"value":"ST016001","description":"5034624016029"},"final":{"value":"ST016001","description":"Njema Duplicate Bk Invoice A5 CB 100X2 1.25Dz (561)"},"original_value":"INVOICE BOOK NUMBERED NJEMA DUPLICATE REF 561"},{"entity_name":"items.1.converted_units.converted_value","exported":{"value":"1","description":""},"final":{"value":"1","description":""},"original_value":"1"},{"entity_name":"items.2.serial_number","exported":{"value":"3","description":""},"final":{"value":"2","description":""},"original_value":"3"},{"entity_name":"items.2.description","exported":{"value":"ST120203","description":"5034624030223"},"final":{"value":"ST120203","description":"KB Rubber Bands No.18 100Gms 48Pkts"},"original_value":"KARTASI RUBBER BAND REF 100 G"},{"entity_name":"items.2.converted_units.converted_value","exported":{"value":"0.25","description":""},"final":{"value":"0.25","description":""},"original_value":"0.25"},{"entity_name":"items.3.serial_number","exported":{"value":"4","description":""},"final":{"value":"3","description":""},"original_value":"4"},{"entity_name":"items.3.description","exported":{"value":"ST120112","description":"5034624030124"},"final":{"value":"ST120112","description":"KB Rubber Bands No.18 50Gms 72Pkts"},"original_value":"KARTASI RUBBER BANDS 50G NO18"},{"entity_name":"items.3.converted_units.converted_value","exported":{"value":"0.16667","description":""},"final":{"value":"0.1667","description":""},"original_value":"0.16667"}]', true);

        $action = new FunctionAction("", [$this, 'collapse'], []);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
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
        //$condition = null;

        $action = new FunctionAction("products", [$this, 'append'], ["strings" => ['(PER KG)'], "separator" => "", "use_data_as_path_value" => "", "valueKey" => "name"], null, null, $condition);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
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
                ["code" => "PC0002", 'quantity' => 12, 'uom' => 'PCS',
                    "conversion_table" => [
                        ["from" => "CTN", "to" => "PCS", "factor" => "24"]
                    ]
                ]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("", [$this, 'convert_unit_multi'], ['items' => ["path" => "items"], 'conversionTable' => ['in_item_path' => 'conversion_table'], 'quantity' => ['in_item_path' => 'quantity'], 'from_unit' => ['in_item_path' => 'uom'], 'to_unit' => 'CTN', 'invert_factor' => 0, 'decimal_handler' => 'off', 'number_of_decimal_places' => "4", 'output_path' => 'converted_units'], "items");

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

        //$this->assertEquals($data, $expectedData);
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
            "operator" => "==",
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
        $data = [
            'items' => []
        ];

        $expectedData = [];

        $args = [
            "path" => "search_field",
            "value" => "",
            "valueFromField" => "description",
            "valueMapping" => "",
            "conditionalValue" => [],
            "newField" => ""
        ];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => '', 'function' => 'set', 'args' => $args, 'newField' => null, 'strict' => 0, 'condition' => null], "new_items");

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
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
                //["description" => "Assorted 1kg", "pack" => "Pack: 1 x 1"],
                //["description" => "Smoked sausage", "pack" => "Pack: 1 x 1"]
            ]
        ];

        $expectedData = [];

        $condition = [
            //"operator" => "not matches",
            //"path" => "description",
            //"value" => "\d+\s*(?:G|GM|GMS|KG|KGS|PC|PCS)"
        ];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => '', 'function' => 'append', 'args' => ['stringsToAppend' => ["[",["path" => "pack"],"]"], "seperator" => "", "use_data_as_path_value" => false, 'valueKey' => ""], 'newField' => 'description', 'strict' => 0 ,'condition' => $condition], '', 0, null);

        $action->execute($data);

        //print "\nTest data:\n";
        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testFilterInArray()
    {
        $data = [
            "products_list" => [
                [
                    "product_code" => "EX00100600"
                ],
                [
                    "product_code" => "EX00100601"
                ],

            ]
        ];

        $filterCriteria = [
            "operator" => "AND",
            "conditions" => [
                [
                    'term' => ["EX00100600"],
                    "mode" => "not in",
                    "key" => "product_code",
                    'threshold' => "",
                    "term_exclusion_pattern" => "",
                    "value_exclusion_pattern" => ""
                ]
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("products_list", [$this, 'filter'], ["filter_criteria" => $filterCriteria], '', 0, null);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testFuzzyExtractTopN1()
    {
        $data = [
            "items" => [
                [
                    "description" => "1000ML",
                    "pack" => "Pack: 1 x 1",
                    "products"  => [
                        ["description" => "1000ML", "pack" => "Pack: 1 x 1"]
                    ],
                    "products_attributes"  => [
                        ["description" => "1000ML", "pack" => "Pack: 1 x 1"],
                        ["description" => "Aquawett-100ML", "pack" => "Pack: 1 x 1"],
                        ["description" => "PEARL x 24 x 100ml [24 x 100]", "pack" => "Pack: 1 x 1"],
                        ["description" => "Smoked chicken sausage 6pc", "pack" => "Pack: 1 x 1", "similarity" => 100]
                    ]
                ]
            ]
        ];

        $args = ["query" => ["path" => "description"], "choices" => ["path" => "parent.products_attributes"],"searchKey" => "description", "minScore" => 1, "n" => "2", "order" => "desc", "fuzzy_method" => "tokenSetRatio", "stop_words" => []];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => 'products', 'function' => 'map', 'args' => ['path' => '', 'function' => 'fuzzy_extract_n', 'args' => $args, 'newField' => 'filtered_products', 'strict' => 0 ,'condition' => null], 'newField' => '', 'strict' => 0 ,'condition' => null], '', 0, null);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testFuzzyExtractTopN2()
    {
        $data = [
            "items" => [
                [
                    "description" => "1000ML",
                    "pack" => "Pack: 1 x 1",
                ]
            ],
            "products"  => [
                ["description" => "1000ML", "pack" => "Pack: 1 x 1"],
                ["description" => "Aquawett-100ML", "pack" => "Pack: 1 x 1"],
                ["description" => "PEARL x 24 x 100ml [24 x 100]", "pack" => "Pack: 1 x 1"],
                ["description" => "Smoked chicken sausage 6pc", "pack" => "Pack: 1 x 1", "similarity" => 100]
            ]
        ];

        $args = ["query" => ["path" => "description"], "choices" => ["path" => "parent.products"],"searchKey" => "description", "minScore" => 1, "n" => "2", "order" => "desc", "fuzzy_method" => "tokenSetRatio", "stop_words" => []];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => '', 'function' => 'fuzzy_extract_n', 'args' => $args, 'newField' => 'filtered_products', 'strict' => 0 ,'condition' => null], '', 0, null);

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

    public function _testUDF()
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

        $action = new FunctionAction("items", [$this, 'user_defined_function'], ["function_name"=> "get_array_at_index", "index" => ["path" => ""]], '', 0, null);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

    public static function get_array_at_index($data, $index) {
        return $data[$index];
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

    public function _testExtractUnit()
    {
        $data = [
            "description" => "Afri Label K09 Flourescent Red 1Bx(25Pkt)"
        ];

        $expectedData = [];

        $condition = null;
        $condition = ["operator"  => "contains","value" => "afri"];

        $action = new FunctionAction("description.meta_data.id|description", [$this, 'extract_unit'], ["only_include" => ["SHEETS",
            "ROLLS",
            "BOXES",
            "G",
            "PACKETS",
            "PAGES",
            "MM",
            "DOZENS",
            "PIECES"], "exclude" => [], "additional_uoms" =>[], "priority" => ["SHEETS",
            "ROLLS",
            "BOXES",
            "G",
            "PACKETS",
            "PAGES",
            "MM",
            "DOZENS",
            "PIECES"]], 'unit',  0, $condition);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testConvertUnitV2()
    {
        $data = [
            "items" => [
                [
                    "unit_of_measure" => [
                        [
                            "selling_quantity" => 30,
                            "selling_unit" => "PCS"
                        ]
                    ],
                    "selling_unit"      => "BOXES",
                    "pieces_per_bale"   => 6
                ]
            ]
        ];

        $expectedData = [];

        $args = [
            "item_quantity" => ["path"  => "unit_of_measure.0.selling_quantity"],
            "item_unit" => ["path"  => "unit_of_measure.0.selling_unit"],
            "convert_to_unit" => ["path"  => "selling_unit"],
            "pieces_per_bundle" => ["path"  => "pieces_per_bale"],
            "additional_pieces_uoms" => [""],
            "decimal_handler" => "off",
            "number_of_decimal_places" => 5,
        ];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => '', 'function' => 'convert_units_v2', 'args' => $args, 'newField' => "converted_units", 'strict' => 0, 'condition' => null], "");

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testExtractPackagingDetails()
    {
        $data = [
            "items" => [
                ["description"   => "D/LAND LIGHT  COMPOUND CHOCOLATE(LCC) 1*2.5KG"],
                ["description"   => "D/LAND WHITE COMPOUND CHOCOLATE 4 X 2.5kgs"],
                ["description"   => "D/L CHOCOLATE BUNDLE 8x(50GMS x 2)"],
                ["description"   => "D/land Vegan Brownie 4ltr"],
                ["description"   => "D/LAND VANILLA/STRAWBERRY 24X120ML"],
                ["description"   => "YOGHURT Kiwi Apple (30gms x 24 pcs)"],
                ["description"   => "Palsgaard 5934"],
                ["description"   => "Dextrose"],
                ["description"   => "BOHORA BLUEBERRY 12 X 250 ML"],
            ]
        ];

        $expectedData = [];

        $action = new FunctionAction("items", [$this, 'map'], ['path' => 'description', 'function' => 'extract_packaging_details', 'args' => ["additional_uoms" => []], 'newField' => "packaging_details", 'strict' => 0, 'condition' => null], "");

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

    public function _testGetEntities() {

        $data = json_decode('[{"purchase_order_number":"","order_date":"2024-10-07","ordered_by_name":"Risper","customer_name":{"original_value":"TIPWANA COMPANY LTD (RISPER | TIPWANA COMPANY LTD)","matched_value":"TIPWANA COMPANY LTD: PC02792","similarity":"88.313877436328","meta_data":{"master_data":"customers","value_key":"Name","id_key":"No","id":"PC02792","value":"TIPWANA COMPANY LTD","matcher":"semantic_search","other_details":{"No":"PC02792","Name":"TIPWANA COMPANY LTD","Phone_No":"0722896127/721434459/728690841","E_Mail":"davegithomi@gmail.com","Contact":"JOHN K. GITHOMI/DORCAS W. MUNGAI/DAVID KIRAGU","Customer_Region":"NAIROBI","Dormant":"","Blocked":" ","Customer_Category":"Kuku Shop","_search_key":"Name","_id_key":"No"}}},"customer_email":"","customer_phone":"","delivery_location":{"original_value":"Tipwana company ltd","matched_value":"TIPWANA COMPANY LTD: MOMBASA","similarity":"72.246925582277","meta_data":{"master_data":"shipping locations","value_key":"Name","id_key":"Code","id":"MOMBASA","value":"TIPWANA COMPANY LTD","matcher":"semantic_search","other_details":{"Customer_No":"PC02792","Code":"MOMBASA","Name":"TIPWANA COMPANY LTD","Address_2":"DAGHORETTI DISTRICT","Contact":"JOHN K. GITHOMI/DORCAS W. MUNGAI/DAVID KIRAGU","Route_plan":"MOMBASA A","Customer_Region":"MOMBASA","_search_key":"Name","_id_key":"Code","Search_Field":"TIPWANA COMPANY LTD (MOMBASA)"}}},"delivery_date":"2024-10-07","seller_name":"Kenchic","items":[{"serial_number":"1","customer_item_code":"","item_bar_code":"0","description":{"original_value":"1.1","matched_value":"CAPON CATERING 11: PC02042","similarity":"96.178526403443","meta_data":{"master_data":"all products","value_key":"Description","id_key":"No","id":"PC02042","value":"CAPON CATERING 11","matcher":"semantic_search","other_details":{"No":"PC02042","Description":"CAPON CATERING 11","Unit_Price":"430","Blocked":"","PC_Type":"Frozen","_search_key":"Description","_id_key":"No","Unit_of_Measure_Code":"KGS","Search_Field":"CAPON CATERING 1.1KG (Frozen) (CHICKEN)"}}},"unit_of_measure":[{"selling_quantity":"300","selling_unit":"PCS","descriptive_quantity":"300 PCS"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0","search_field":"CATERING CAPON 1.1KG (CHICKEN) (Frozen)","section":"Shop"}],"currency":"KES","total_number_of_items":"1","total_number_of_extracted_items":"1","sub_total":"0","total_amount":"0","vat_no":"","workflow_list_position":"0","sender_details":{"sent_by_name":"Risper A. Amotto","sent_by_phone":"","sent_by_email":"amottorisper@kenchic.com","sent_by_email_domain":"kenchic"},"extraction_data":{"document":{"link":""},"document_text_content":"Email Subject: Tipwana company ltd\n Email Body:\n 1.1 300pcs\n\nRegards\n\nRisper","document_text":"Email Subject: Tipwana company ltd\n Email Body:\n 1.1 300pcs\n\nRegards\n\nRisper","subscribed_mailbox_email":"pcsales@kenchic.com"},"email_meta_data":{"email_subject":" Tipwana company ltd"},"timezone":"1","is_consignment_sales":"No","is_standing_order":"No","is_tdr_order":"No","is_staff_order":"No","is_email_body_order":"Yes","contact_person":{"original_value":"TIPWANA COMPANY LTD MOMBASA","matched_value":"DORCAS W. MUNGAI: PCC406661","similarity":"44.252213363108","meta_data":{"master_data":"contacts","value_key":"Name","id_key":"No","id":"PCC406661","value":"DORCAS W. MUNGAI","matcher":"semantic_search","other_details":{"No":"PCC406661","Name":"DORCAS W. MUNGAI","Designation_New":"OWNER","Customer_No":"PC02792","_search_key":"Name","_id_key":"No"}}},"promised_delivery_date":"2024-10-08T12:55:00Z","production_date":"2024-10-07T12:55:00Z"}]', true);
        //$data = json_decode('[{"customer_name":"Tipwana company ltd","items":[{"serial_number":"1","customer_item_code":"","item_bar_code":"0","description":"1.1","unit_of_measure":[{"selling_quantity":"300","selling_unit":"PCS"}],"pack_configuration":{"pack_size":"","unit_size":"0"},"unit_price":"0","total_price":"0"}]}]', true);

        $entities = [];

        if (Utils::isList($data)) {
            foreach ($data as $index => $d) {
                $entities[$index] = EntityExtractor::extractEntities($d, '', true);
            }
        } else {
            $entities = EntityExtractor::extractEntities($data, '', true);
        }

        print_r($entities);

    }

}
