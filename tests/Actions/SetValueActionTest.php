<?php

namespace PhelixJuma\DataTransformer\Tests\Actions;

use PhelixJuma\DataTransformer\Actions\SetValueAction;
use PHPUnit\Framework\TestCase;

class SetValueActionTest extends TestCase
{
    public function _testSet()
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
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100]
            ],
            'delivery_date' => '2023-09-04'
        ];

        $valueMapping = [
            'Nairobi' => '2023-09-04',
            'Kisumu' => '2023-09-04',
            'Mombasa' => '2023-09-04',
        ];

        $action = new SetValueAction("delivery_date", null, "location.region", $valueMapping);

        $action->execute($data);

        $this->assertEquals($data, $expectedData);
    }

    public function _testSetStaticValue()
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
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100]
            ],
            'delivery_date' => '2023-09-18'
        ];

        $action = new SetValueAction("delivery_date", '2023-09-18');

        $action->execute($data);

        $this->assertEquals($data, $expectedData);
    }

}
