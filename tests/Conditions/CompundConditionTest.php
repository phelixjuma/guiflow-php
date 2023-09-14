<?php

namespace PhelixJuma\DataTransformer\Tests\Conditions;

use PhelixJuma\DataTransformer\Conditions\CompositeCondition;
use PHPUnit\Framework\TestCase;
use PhelixJuma\DataTransformer\Conditions\SimpleCondition;
use PhelixJuma\DataTransformer\Utils\PathResolver;

class CompundConditionTest extends TestCase
{
    public function testEvaluateNested()
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
            'operator' => 'and',
            'conditions' => [
                [
                    'operator' => 'or',
                    'conditions' => [
                        [
                            'path' => 'products.*.name',
                            'operator' => '==',
                            'value' => 'Capon Chicken',
                        ],
                        [
                            'path' => 'location.address',
                            'operator' => '==',
                            'value' => 'Kilimani'
                        ]
                    ]
                ],
                [
                    'path' => 'customer',
                    'operator' => '==',
                    'value' => 'Naivas',
                ]
            ]
        ];

        $pathResolver = new PathResolver();

        $simpleCondition = new CompositeCondition($condition, $pathResolver);

        $this->assertTrue($simpleCondition->evaluate($data));
    }

}
