<?php

namespace PhelixJuma\DataTransformer\Tests;

use PHPUnit\Framework\TestCase;
use PhelixJuma\DataTransformer\DataTransformer;

class DataTransformerTest extends TestCase
{

    public function _testSettingStaticValue()
    {
        $config_json = '[{"rule":"Delivery Dates","condition":{"operator":"AND","conditions":[{"path":"location.region","operator":"==","value":"Nairobi"}]},"actions":[{"action":"set","path":"delivery_date","value":"2023-09-19"}]}]';

        $config = json_decode($config_json);

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
            'delivery_date' => '2023-09-19'
        ];

        $dataTransformer = new DataTransformer($config, $this);
        $dataTransformer->transform($data);

        $this->assertEquals($expectedData, $data);
    }

    public function _testSettingMappedValue()
    {
        $valueMapping = [
            'Nairobi' => '2023-09-04',
            'Kisumu' => '2023-09-04',
            'Mombasa' => '2023-09-04',
        ];

        $config_json = '[{"rule":"Delivery Dates","condition":{"operator":"AND","conditions":[{"path":"location.region","operator":"==","value":"Nairobi"}]},"actions":[{"action":"set","path":"delivery_date","value":"", "valueFromField":"location.region", "valueMapping":'.json_encode($valueMapping).'}]}]';

        $config = json_decode($config_json);

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

        $dataTransformer = new DataTransformer($config, $this);
        $dataTransformer->transform($data);

        //print_r($data);

        $this->assertEquals($expectedData, $data);
    }

    public function testFullRulesSet()
    {
        $config_json = file_get_contents(__DIR__ ."/config.json");
        $config = json_decode($config_json);

        $data = [
            'customer_name' => 'Naivas',
            'delivery_location' => 'Kilimani',
            'items' => [
                ['name' => 'Capon Chicken', 'quantity' => 2,'uom' => 'KGS', 'unit_price' => 100]
            ],
            "delivery_date" => "2023-09-04"
        ];

        $expectedData = [
            'customer_name' => 'Naivas',
            'delivery_location' => "Kilimani",
            'items' => [
                ['name' => 'Capon Chicken', 'quantity' => 2, 'unit_price' => 100]
            ],
            'delivery_date' => '2023-09-04'
        ];

        $dataTransformer = new DataTransformer($config, $this);
        $dataTransformer->transform($data);

        //print_r($data);

        $this->assertEquals($expectedData, $data);
    }

    public function getCustomersList() {
        return [
            [
                "no"=> "PC00072",
                "name"=> "NAIVAS LIMITED",
                "region" => "NAIROBI",
            ]
        ];
    }

    public function getMatchedCustomer($customerName, $customersList) {
        return [
            'original_value' => $customerName,
            'matched_value' => [
                "no"=> "PC00072",
                "name"=> "NAIVAS LIMITED",
                "region" => "NAIROBI"
            ]
        ];
    }

    public function getCustomerProducts($customerId) {
        return [
            [
                "no"            => "PC02009",
                "name"          => "CAPON FRESH BUTCHERY",
                "uom"           => "KGS",
                "unit_price"    => 590,
                "type"          => "Frozen"
            ],
            [
                "no"            => "PC02080",
                "name"          => "CHICKEN LEGS 800GM PACK",
                "uom"           => "PACK",
                "unit_price"    => 420,
                "type"          => "Frozen"
            ]
        ];
    }

    public function getMatchedProducts($orderedProducts, $customerProducts) {
        return [
            [
                "original_value"    => $orderedProducts[0],
                "matched_value"     => [
                    "no"            => "PC02009",
                    "name"          => "CAPON FRESH BUTCHERY",
                    "uom"           => "KGS",
                    "unit_price"    => 590,
                    "type"          => "Frozen"
                ]
            ]
        ];
    }

    public function getUnitConversionTable() {
        return [
            [
                "from"        => "pieces",
                "to"          => "kgs",
                "multiplier"  => 0.2
            ],
            [
                "from"        => "bottles",
                "to"          => "kgs",
                "multiplier"  => 0.2
            ]
        ];
    }

    /**
     * @param $quantity
     * @param $fromUom
     * @param $toUom
     * @return array[]
     */
    public function convertUnitOfMeasurement($quantity, $fromUom, $toUom) {
        return [
            'original_value'  => [
                'quantity'  => $quantity,
                'uom'       => $fromUom
            ],
            'matched_value' => [
                'quantity'  => 1,
                'uom'       => $toUom
            ]
        ];
    }

    public function getCustomerShippingAddresses($customerId) {
        return [
            [
                "code"      => "A/KH WLK B",
                "name"      => "NAIVAS AGA KHAN WALK BUTCHERY",
                "region"    => "NAIROBI"
            ],
            [
                "code"      => "AIR P BUTC",
                "name"      => "NAIVAS AIRPORT BUTCHERY",
                "region"    => "NAIROBI"
            ],
            [
                "code"      => "B/LULU SH",
                "name"      => "NAIVAS LIMITED",
                "region"    => "MOMBASA"
            ]
        ];
    }

    public function getMatchedShippingAddress($deliveryLocation, $shippingAddresses) {
        return [
            'original_value'    => $deliveryLocation,
            'matched_value'     => [
                "code"      => "AIR P BUTC",
                "name"      => "NAIVAS AIRPORT BUTCHERY",
                "region"    => "NAIROBI"
            ]
        ];
    }

    public function getCustomerContacts($customerId, $filters) {
        return [
            [
                "no"            => "PCC407541",
                "name"          => "NAIVAS ANANAS - BEATRICE ABIGAEL WANJIKU",
                "designation"   => "BRANCH MANAGER"
            ]
        ];
    }

    public function getMatchedContact($customerName, $deliveryLocation, $customerContacts) {
        return [
            "no"            => "PCC407541",
            "name"          => "NAIVAS ANANAS - BEATRICE ABIGAEL WANJIKU",
            "designation"   => "BRANCH MANAGER"
        ];
    }

    public function getRegionDeliverySchedule($region) {
        return ["Monday", "Friday"];
    }

    public function getPromisedDeliveryDate($deliveryDate, $deliverySchedule) {
        return [
            'original_value'    => $deliveryDate,
            'matched_value'     => "2023-09-11"
        ];
    }

}
