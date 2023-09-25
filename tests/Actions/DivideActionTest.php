<?php

namespace PhelixJuma\DataTransformer\Tests\Actions;

use PhelixJuma\DataTransformer\Actions\DivideAction;
use PHPUnit\Framework\TestCase;

class DivideActionTest extends TestCase
{
    public function _testDivide()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 10, 'unit_price' => 100]
            ],
        ];
        $expectedData = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 5, 'unit_price' => 100]
            ],
        ];

        $action = new DivideAction("products.*.quantity", 2);

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

}
