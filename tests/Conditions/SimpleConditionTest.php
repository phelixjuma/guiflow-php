<?php

namespace PhelixJuma\DataTransformer\Tests\Conditions;

use PHPUnit\Framework\TestCase;
use PhelixJuma\DataTransformer\Conditions\SimpleCondition;
use PhelixJuma\DataTransformer\Utils\PathResolver;

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

    public function testEvaluateBasic()
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
}
