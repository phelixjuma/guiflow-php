<?php

namespace PhelixJuma\GUIFlow\Tests\Actions;

use PhelixJuma\GUIFlow\Actions\SubtractAction;
use PHPUnit\Framework\TestCase;

class SubtractActionTest extends TestCase
{
    public function _testAdd()
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
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100, 'new_quantity' => -98]
            ]
        ];

        $action = new SubtractAction("products.*.quantity", null, "products.*.unit_price", "products.*.new_quantity");
        //$action = new SubtractAction("no_products", null, "factor", "new_no_products");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

}
