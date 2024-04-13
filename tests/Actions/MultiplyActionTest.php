<?php

namespace PhelixJuma\GUIFlow\Tests\Actions;

use PhelixJuma\GUIFlow\Actions\MultiplyAction;
use PHPUnit\Framework\TestCase;

class MultiplyActionTest extends TestCase
{
    public function _testMultiply()
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
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100, 'total_price' => 200]
            ],
        ];

        $action = new MultiplyAction("products.*.quantity", 10, "products.*.unit_price", "products.*.total_price");

        $action->execute($data);

        $this->assertEquals($data, $expectedData);
    }

}
