<?php

namespace PhelixJuma\DataTransformer\Tests\Actions;

use PhelixJuma\DataTransformer\Actions\DeleteValueAction;
use PHPUnit\Framework\TestCase;

class DeleteValueActionTest extends TestCase
{
    public function _testDeleteSimple()
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
            'customer' => null,
            'location' => [
                'address' => 'Kilimani',
                'region' => 'Nairobi'
            ],
            'products' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100]
            ]
        ];

        $action = new DeleteValueAction("customer");

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
