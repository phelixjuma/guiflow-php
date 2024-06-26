<?php

namespace PhelixJuma\GUIFlow\Tests\Actions;

use PhelixJuma\GUIFlow\Actions\DeleteValueAction;
use PhelixJuma\GUIFlow\Actions\RemovePathAction;
use PHPUnit\Framework\TestCase;

class RemovePathActionTest extends TestCase
{
    public function _testRemovePathSimple()
    {
        $data = [
            'customer' => "Naivas",
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100],
                ['name' => 'Capon Chicken 2', 'quantity' => 3, 'unit_price' => 300],
            ],
        ];
        $expectedData = [];

        $action = new RemovePathAction("products.*.unit_price");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testDeleteNestedObject()
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
                'address' => null,
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100]
            ]
        ];

        $action = new DeleteValueAction("location.address");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testDeleteArrayElements()
    {
        $data = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => 300],
            ],
        ];
        $expectedData = [
            'customer' => 'Naivas',
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => null],
                ['name' => 'Chicken Sausages', 'quantity' => 3, 'unit_price' => null],
            ]
        ];

        $action = new DeleteValueAction("products.*.unit_price");

        $action->execute($data);

        //print_r($data);

        $this->assertEquals($data, $expectedData);
    }

}
