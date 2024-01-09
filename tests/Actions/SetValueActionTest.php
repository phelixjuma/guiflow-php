<?php

namespace PhelixJuma\DataTransformer\Tests\Actions;

use PhelixJuma\DataTransformer\Actions\SetValueAction;
use PHPUnit\Framework\TestCase;

class SetValueActionTest extends TestCase
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
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100]
            ],
        ];
        $expectedData = [];

        $action = new SetValueAction("order", null, "", '');

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testSetValueFromMapping()
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
                ]
            ];
        $expectedData = [];

        $valueMapping = [
            'Luc Boost Buzz 1l Tet X12' => 'Lucozade Boost Buzz 1l Pet'
        ];

        $action = new SetValueAction("products.*.ItemName", null, "products.*.ItemName", $valueMapping);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function testSetConditionalValueFromMapping()
    {
        $data =
            [
                'customer' => 'Naivas',
                'location' => [
                    'address' => 'Kilimani',
                    'region' => 'Nairobi'
                ],
                'products' => [
                    ["description" => "NAIVAS GIZZARDS"],
                    ["description" => "NAIVAS LIVER"],
                    ["description" => "NAIVAS DELI SAUSAGES"],
                    ["description" => "KENCHIC CAT.LIVER"],
                    ["description" => "HUNGARIAN CHOMA SAUSAGES 1KG"],
                    ["description" => "HUNGARIAN CHOMA SAUSAGES 500G"],
                    ["description" => "CHICKEN SAUSAGES 250G"],
                    ["description" => "CHICKEN FRESH LIVER PKG"],
                    ["description" => "CHICKEN FRESH GIZZARDS P/KG"],
                    ["description" => "KENCHIC CHICKEN SAUSAGE 500G"],
                    ["description" => "KENCHIC CHICKEN SAUSAGE 26PCS"],
                    ["description" => "KENCHIC CHICKEN SAUSAGE", "section" => "Butchery"],
                    ["description" => "CLEANSHELF KENCHIC FRESH CAPON 1.1-1.3KG"],
                ]
            ];
        $expectedData = [];

        $conditionalValue = [
            [
                "condition" => [
                    "operator" => "AND",
                    "conditions" => [
                        [
                            "operator" => "in list any",
                            "value" => ["(\d+)\s*(G|GM|GMS|KG|KGS|PC|PCS)"]
                        ]
                    ]
                ],
                "value"     => "Shop",
                "valueFromField"    => ""
            ],
            [
                "condition" => [
                    "operator" => "AND",
                    "conditions" => [
//                        [
//                            "operator" => "contains",
//                            "value" => 'NAIVAS'
//                        ],
//                        [
//                            "operator" => "not contains",
//                            "value" => "NAIVAS DELI"
//                        ],
                        [
                            "operator" => "in list any",
                            "value" => [
                                "CAPON (\d+)\.(\d+)-(\d+)\.(\d+)KG"
                            ]
                        ],
                    ]
                ],
                "value"     => "Butchery",
                "valueFromField"    => ""
            ],
            [
                "condition" => [
                    "operator" => "OR",
                    "conditions" => [
                        [
                            "operator" => "in list any",
                            "value" => [
                                "NAIVAS DELI",
                                "KENCHIC CAT",
                                "HUNGARIAN CHOMA SAUSAGES 1KG",
                                "\b(PERKG|PER KG|P/KG|PKG|PK|/KG|PER 500G)\b"
                            ]
                        ]
                    ]
                ],
                "value"     => "Deli",
                "valueFromField"    => ""
            ]
        ];

        $conditionalValue_ = [
            [
                "condition" => [
                    "operator" => "not exists"
                ],
                "value"     => "Shop",
                "valueFromField"    => ""
            ]
        ];

        $action = new SetValueAction("products.*.description", null, null, null, $conditionalValue, "products.*.section");

        $action->execute($data);

        print_r($data);

        $this->assertEquals($data, $expectedData);
    }

}
