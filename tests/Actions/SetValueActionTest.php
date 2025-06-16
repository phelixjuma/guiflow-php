<?php

namespace PhelixJuma\GUIFlow\Tests\Actions;

use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Actions\SetValueAction;
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
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100],
                ['name' => 'Capon Chicken', 'quantity' => 4, 'unit_price' => 100],
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100],
                ['name' => 'Capon Chicken', 'quantity' => 3, 'unit_price' => 100],
                ['name' => 'Capon Chicken', 'quantity' => 7, 'unit_price' => 100],
                ['name' => 'Capon Chicken', 'quantity' => 3, 'unit_price' => 100],
            ],
        ];

        $valueMapping = [
            '2' => '20',
            '3' => '30'
        ];


        $action = new SetValueAction("products.*.quantity", null, null, $valueMapping);

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
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

        $action = new SetValueAction("products.*.ItemName", null, null, $valueMapping);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testSetConditionalValueFromMapping()
    {
        $data =
            [
                'customer' => 'Naivas',
                'location' => [
                    'address' => 'Kilimani',
                    'region' => 'Nairobi'
                ],
                'products' => [
                    ["description" => "CLEANSHELF KENCHIC FRESH CAPON 1.1-1.3KG", "category" => "Flour"],
                    ["description" => "CAPON 1.1", "category" => "Rice"],
                    ["description" => "Wings", "category" => "Biscuits"],
                    ["description" => "Ribs", "category" => "Others"],
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
                            "value" => ["Rice", "Biscuits", "Others"]
                        ]
                    ]
                ],
                "value"     => "Rice",
                "valueFromField"    => ""
            ],
            [
                "condition" => [
                    "operator" => "AND",
                    "conditions" => [
                        [
                            "operator" => "in list any",
                            "value" => [
                                "Flour"
                            ]
                        ],
                    ]
                ],
                "value"     => "Maize",
                "valueFromField"    => ""
            ]
        ];

        $action = new SetValueAction("products.*.category", null, null, null, $conditionalValue, "products.*.section");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testConditionalSet()
    {
        $data = [
            "delivery_location" => "Kisumu Wholesalers"
        ];

        $expectedData = [];

        $conditionalValue = [
            [
                "condition" => [
                    "operator" => "AND",
                    "conditions" => [
                        [
                            "operator" => "in list any",
                            "value" => ["wholesale"]
                        ]
                    ]
                ],
                'use_data_as_path_value' => "1",
                "value"     => "distribution",
                "valueFromField"    => ""
            ],
            [
                "condition" => [
                    "operator" => "AND",
                    "conditions" => [
                        [
                            "operator" => "not in list any",
                            "value" => ["wholesale"]
                        ]
                    ]
                ],
                'use_data_as_path_value' => "1",
                "value"     => "supermarket",
                "valueFromField"    => ""
            ],
        ];

        $action = new SetValueAction("delivery_location", '', '', '', $conditionalValue, "new_delivery_location");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testConditional()
    {
        $data = [
            "customer_name_search" => "MAJID ALFUTTAIM HYPERMARKETS LTD",
            "unique_brands" => [
                "CHANDAN",
                "BISCOLATA",
                "CRISS CROSS",
                "OZMO",
                "MILANGO",
                "BOOMBASTIC",
                "PAPITA"
            ],
            "majid_brands" => [
                "ky" => [
                    "VELVEX",
                    "NICE & SOFT",
                    "ROSY",
                    "TOILEX",
                    "DAWN",
                    "QIKDRI",
                    "SAFARI",
                    "ROYALE",
                    "AZHAR",
                    "AONE",
                    "SINAR LINE",
                    "SURECOPY",
                    "SANDY",
                    "FRESHEN UP",
                    "CARREFOUR"
                ],
                "hyg" => [
                    "SOFY",
                    "WHITEDENT",
                    "HIMALAYA",
                    "TRESSA",
                    "SIRI",
                    "BANNISTERS",
                    "BABY SOFT",
                    "SKIN GLOW",
                    "BODYLINE",
                    "VELVEX CONFORTA",
                    "VELVEX ADULT BODY",
                    "VELVEX ANTIBACTERIAL",
                    "VELVEX BABY WATER",
                    "VELVEX BAMBOO",
                    "VELVEX MAKE-UP"
                ],
                "sol"=> [
                    "BISCOLATA",
                    "CHANDAN",
                    "OILYSSA",
                    "OZMO",
                    "MILANGO",
                    "BOOMBASTIC",
                    "PAPITA",
                    "CRISS CROSS",
                    "ONLI"
                ],
                "hky"=> [
                    "ATA",
                    "PERSIL"
                ],
                "tg"=> [
                    "TONI GLASS",
                    "PERRIER"
                ],
                "hbky"=> [
                    "FA",
                    "GOT2B"
                ],
                "mck"=> [
                    "MACCOFFEE",
                    "MACTEA",
                    "KRACKS"
                ],
                "ken"=> [
                    "KENTASTE"
                ]
            ],
            "majid_same_brands_group"=> "No"
        ];

        $conditionalValue = json_decode('[
            {
              "condition": {
                "operator": "AND",
                "conditions": [
                  {
                    "path": "customer_name_search",
                    "operator": "matches",
                    "value": "MAJID"
                  },
                  {
                    "operator": "OR",
                    "conditions": [
                      {
                        "path": "majid_brands.ky",
                        "operator": "list_contains_all",
                        "value": {
                          "path": "unique_brands"
                        }
                      },
                      {
                        "path": "majid_brands.hyg",
                        "operator": "list_contains_all",
                        "value": {
                          "path": "unique_brands"
                        }
                      },
                      {
                        "path": "majid_brands.sol",
                        "operator": "list_contains_all",
                        "value": {
                          "path": "unique_brands"
                        }
                      },
                      {
                        "path": "majid_brands.hky",
                        "operator": "list_contains_all",
                        "value": {
                          "path": "unique_brands"
                        }
                      },
                      {
                        "path": "majid_brands.tg",
                        "operator": "list_contains_all",
                        "value": {
                          "path": "unique_brands"
                        }
                      },
                      {
                        "path": "majid_brands.hbky",
                        "operator": "list_contains_all",
                        "value": {
                          "path": "unique_brands"
                        }
                      },
                      {
                        "path": "majid_brands.mck",
                        "operator": "list_contains_all",
                        "value": {
                          "path": "unique_brands"
                        }
                      },
                      {
                        "path": "majid_brands.ken",
                        "operator": "list_contains_all",
                        "value": {
                          "path": "unique_brands"
                        }
                      }
                    ]
                  }
                ]
              },
              "value": "Yes",
              "valueFromField": "",
              "use_data_as_path_value": "0"
            }
          ]', true);


        $action = new SetValueAction("", null, null, null, $conditionalValue, 'majid_same_brands_group');

        $action->execute($data);

        print_r($data);

        //$this->assertEquals($data, $expectedData);
    }

}
