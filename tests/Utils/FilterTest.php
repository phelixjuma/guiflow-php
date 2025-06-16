<?php

namespace PhelixJuma\GUIFlow\Tests\Utils;

use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PhelixJuma\GUIFlow\Utils\Filter;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    public function _testContains()
    {
        $data = [
            'Apples',
            'Mangoes',
            [['Apples', "Oranges"], ['Mangoes', "Pears"], ["Lemons"]],
            ["name" => "Apple", "Type" => "fruit"],
            [['name' => "mango", 'quantity' => 9], ["name" => "apples", 'quantity' => 8]]
        ];

        $filters = ['term' => 'mango', 'mode' => 'contains', 'key' => false, 'threshold' => null];

        $filtered = Filter::filterArray($data, $filters);

        //print_r($filtered);

        $this->assertTrue($data == $filtered);
    }

    public function _testSimilarTo()
    {
        $data = [
            'Apples',
            'Mangoes',
            [['Apples', "Oranges"], ['Mangoes', "Pears"], ["Lemons"], ["Mago"]],
            ["name" => "Apple", "Type" => "fruit"],
            [['name' => "mango", 'quantity' => 9], ["name" => "apples", 'quantity' => 8]]
        ];

        $filters = ['term' => 'mago', 'mode' => 'similar_to', 'key' => false, 'threshold' => 50];

        $filtered = Filter::filterArray($data, $filters);

        //print_r($filtered);

        $this->assertTrue($data == $filtered);
    }

    public function _testEqualTo()
    {
        $data = [
            'Apples',
            'Mangoes',
            [['Apples', "Oranges"], ['Mangoes', "Pears"], ["Lemons"], ["Mago"]],
            ["name" => "Apple", "Type" => "fruit"],
            [['name' => "mango", 'quantity' => 9], ["name" => "apples", 'quantity' => 8]]
        ];

        $filters = ['term' => 9, 'mode' => Filter::GREATER_OR_EQUAL, 'key' => false, 'threshold' => null];

        $filtered = Filter::filterArray($data, $filters);

        //print_r($filtered);

        $this->assertTrue($data == $filtered);
    }

    public function _testComposite()
    {
        $data = [
            'Apples',
            'Mangoes',
            [['Apples', "Oranges"], ['Mangoes', "Pears"], ["Lemons"], ["Mago"]],
            ["name" => "Apple", "Type" => "fruit"],
            [['name' => "mango", 'quantity' => 9], ["name" => "apples", 'quantity' => 8]]
        ];

        $data = [
            ['name' => "mango", 'quantity' => 9],
            ['name' => "mango", 'quantity' => 15],
            ["name" => "apples", 'quantity' => 8],
            ["name" => "oranges", 'quantity' => 5],
            ["name" => "lemons", 'quantity' => 10],
        ];

        $conditions = [
            "operator"      => "OR",
            "conditions"    => [
                ['term' => 'appel', 'mode' => Filter::SIMILAR_TO, 'key' => 'name', 'threshold' => 50],
                [
                    "operator" => "AND",
                    "conditions" => [
                        ['term' => 'mango', 'mode' => Filter::CONTAINS, 'key' => 'name'],
                        ['term' => 15, 'mode' => Filter::GREATER_OR_EQUAL, 'key' => 'quantity']
                    ]
                ],

            ]
        ];

        $filtered = Filter::filterArray($data, $conditions);

        //print_r($filtered);

        $this->assertTrue($data == $filtered);
    }

    public function _testComposite2()
    {
        $data = [
            'Apples',
            'Mangoes',
            [['Apples', "Oranges"], ['Mangoes', "Pears"], ["Lemons"], ["Mago"]],
            ["name" => "Apple", "Type" => "fruit"],
            [['name' => "mango", 'quantity' => 9], ["name" => "apples", 'quantity' => 8]]
        ];

        $data = ['name' => "mango", 'quantity' => 15];

        $conditions = [
            "operator"      => "OR",
            "conditions"    => [
                ['term' => 'mango', 'mode' => Filter::EQUAL, 'key' => 'name'],
                ['term' => 15, 'mode' => Filter::EQUAL, 'key' => 'quantity']
            ]
        ];

        $filtered = Filter::filterArray($data, $conditions);

        //print_r($filtered);

        $this->assertTrue($data == $filtered);
    }

    public function _testComposite3()
    {
        $data = [
            'Apples',
            'Mangoes',
            [['Apples', "Oranges"], ['Mangoes', "Pears"], ["Lemons"], ["Mago"]],
            ["name" => "Apple", "Type" => "fruit"],
            [['name' => "mango", 'quantity' => 9], ["name" => "apples", 'quantity' => 8]]
        ];

        $data = [
            ['Apples', "Oranges"],
            ['Mangoes', "Pears"],
            ["Lemons"], ["Mago"]
        ];

        $conditions = [
            "operator"      => "OR",
            "conditions"    => [
                ['term' => 'mango', 'mode' => Filter::SIMILAR_TO, 'threshold' => 80],
                ['term' => 'pears', 'mode' => Filter::EQUAL]
            ]
        ];

        $filtered = Filter::filterArray($data, $conditions);

        //print_r($filtered);

        $this->assertTrue($data == $filtered);
    }

    public function _testComposite4()
    {
        $data = [
            'Apples',
            'Mangoes',
            [['Apples', "Oranges"], ['Mangoes', "Pears"], ["Lemons"], ["Mago"]],
            ["name" => "Apple", "Type" => "fruit"],
            [['name' => "mango", 'quantity' => 9], ["name" => "apples", 'quantity' => 8]]
        ];

        $data = [
            'order_date' => 'today',
            'products' => [
                ['name' => 'Apples'],
                ['name' => "Oranges"],
                ['name' => "Lemons"],
                ['name' => "Pears"],
                ['name' => "Mangoes"],
            ]
        ];

        $data = [
            ['name' => 'Apples', "blocked" => "1"],
            ['name' => "Oranges", "blocked" => "1"],
            ['name' => "Lemons", "blocked" => ""],
            ['name' => "Pears", "blocked" => ""],
            ['name' => "Mangoes", "blocked" => "1"],
        ];


        $conditions = [
            "operator"      => "OR",
            "conditions"    => [
                ['term' => '', 'mode' => Filter::GREATER_OR_EQUAL, 'key' => 'blocked']
            ]
        ];

        $filtered = Filter::filterArray($data, $conditions);

        //print_r($filtered);

        $this->assertTrue($data == $filtered);
    }

    public function _testFilterByPath()
    {
        $data = [
            'order_date' => 'today',
            'products' => [
                ['name' => ''],
                ['name' => "Oranges"],
                ['name' => "Lemons"],
                ['name' => ""],
                ['name' => "  \n"],
            ]
        ];

        $conditions = ['term' => '', 'mode' => Filter::NOT_EMPTY, 'key' => 'name'];

        $data['products'] = Filter::filterArray($data['products'], $conditions);

        print_r($data);

        //$this->assertTrue($data == $filtered);
    }

    public function _testFilterListByPath()
    {
        $data =
            [
                "items" => [
                    [
                        "description" => [
                            "name" => "Product A",
                            "similarity" => "88"
                        ]
                    ],
                    [
                        "description" => [
                            "name" => "Product B",
                            "similarity" => "100"
                        ]
                    ],
                    [
                        "description" => [
                            "name" => "Product B",
                            "similarity" => "20"
                        ]
                    ]
                ]
            ];


        $conditions = ['term' => "50", 'mode' => '>=', 'key' => 'description.similarity', "similarity_threshold" => "",
              "term_exclusion_pattern" => "", "value_exclusion_pattern" => ""];

        $data = Filter::filterArray($data, $conditions);

        print_r($data);

        //$this->assertTrue($data == $filtered);
    }

}
