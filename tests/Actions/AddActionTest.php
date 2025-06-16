<?php

namespace PhelixJuma\GUIFlow\Tests\Actions;

use PhelixJuma\GUIFlow\Actions\AddAction;
use PhelixJuma\GUIFlow\Actions\MultiplyAction;
use PHPUnit\Framework\TestCase;

class AddActionTest extends TestCase
{
    public function _testAdd()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            "factor" => 6,
            "no_products" => 7,
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
            "factor" => 6,
            "no_products" => 7,
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100]
            ],
            "new_no_products" => 13
        ];

        //$action = new AddAction("products.*.quantity", 3, "products.*.unit_price", "products.*.new_quantity");
        $action = new AddAction("no_products", null, "factor", "new_no_products");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

}
