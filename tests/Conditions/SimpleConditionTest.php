<?php

namespace PhelixJuma\GUIFlow\Tests\Conditions;

use PHPUnit\Framework\TestCase;
use PhelixJuma\GUIFlow\Conditions\SimpleCondition;
use PhelixJuma\GUIFlow\Utils\PathResolver;

class SimpleConditionTest extends TestCase
{
    public function _testEvaluateWildcard()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2]
            ],
        ];

        $condition = [
            'path' => 'products.*.name',
            'operator' => '==',
            'value' => 'Capon Chicken',
        ];

        $pathResolver = new PathResolver();

        $simpleCondition = new SimpleCondition($condition, $pathResolver);

        $this->assertTrue($simpleCondition->evaluate($data));
    }

    public function _testEvaluateNestedObject()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2]
            ],
        ];

        $condition = [
            'path' => 'location.address',
            'operator' => '==',
            'value' => 'Kilimani',
        ];

        $pathResolver = new PathResolver();

        $simpleCondition = new SimpleCondition($condition, $pathResolver);

        $this->assertTrue($simpleCondition->evaluate($data));
    }

    public function _testEvaluateBasic()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2]
            ],
        ];

        $condition = [
            'path' => 'customer',
            'operator' => '==',
            'value' => 'Naivas',
        ];

        $pathResolver = new PathResolver();

        $simpleCondition = new SimpleCondition($condition, $pathResolver);

        $this->assertTrue($simpleCondition->evaluate($data));
    }

    public function _testEvaluateAlways()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2]
            ],
        ];

        $condition = 'always';

        $pathResolver = new PathResolver();

        $simpleCondition = new SimpleCondition($condition, $pathResolver);

        $this->assertTrue($simpleCondition->evaluate($data));
    }

    public function _testListContains()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2]
            ],
            'sections' => [
                'Shop', 'Deli'
            ]
        ];

        $condition = [
            'path' => 'sections',
            'operator' => 'contains',
            'value' => 'Shop',
        ];

        $pathResolver = new PathResolver();

        $simpleCondition = new SimpleCondition($condition, $pathResolver);

        $evaluation = $simpleCondition->evaluate($data);

        //print "Evaluation: ".($evaluation);

        $this->assertTrue($simpleCondition->evaluate($evaluation));
    }

    public function _testNotContains()
    {
        $data = [
            'delivery_schedule_details' => [
                "REGION"=> "EASTERN",
                "ROUTE"=> "KITENGELA",
                "NAIVAS"=> "Monday/Thursday",
                "QUICKMART"=> "Monday/Thursday",
                "MAJID"=> "",
                "OTHERS"=> "Monday/Thursday"
            ]
        ];

        $condition = [
            'path' => 'days',
            'operator' => 'not exists'
        ];

        $pathResolver = new PathResolver();

        $simpleCondition = new SimpleCondition($condition, $pathResolver);

        $evaluation = $simpleCondition->evaluate($data);

        print "Evaluation: ".($evaluation);

        $this->assertTrue($simpleCondition->evaluate($evaluation));
    }

    public function _testDateGreaterThan()
    {
        $data = [
            "current_time" => "07:21:42",
            "hours_to_delivery" => 24
        ];

        $condition = [
            "path" => "hours_to_delivery",
            "operator" => "lte",
            "value" => "24"
        ];

        $pathResolver = new PathResolver();

        $simpleCondition = new SimpleCondition($condition, $pathResolver);

        $response = $simpleCondition->evaluate($data);

        //print "\nCondition Returns $response\n";

        $this->assertTrue($response);
    }

    public function _testInListAny()
    {
        $data = [
            "customer_name" => [
                "meta_data" => [
                    "id" => "C00048"
                ]
            ],
            //"from_email" => "fouma@khetia.com",
            "hours_to_delivery" => 24
        ];

        $condition = [
            "path" => "customer_name.meta_data.id",
            "operator" => "in",
            "value" => [
                "C00048",
                "C00225",
                "C00164",
                "C00056",
                "C00082",
                "C00286",
                "C00135",
                "C00148",
                "C00147",
                "C00167",
                "C00188",
                "C00262",
                "C00968"
            ]
        ];

        $pathResolver = new PathResolver();

        $simpleCondition = new SimpleCondition($condition, $pathResolver);

        $response = $simpleCondition->evaluate($data);

        print "\nCondition Returns $response\n";

        //$this->assertTrue($response);
    }

}
