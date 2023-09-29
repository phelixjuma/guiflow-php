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

    public function _testFullRulesSet()
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

        $data = json_decode('[{"purchase_order_number":"PO-2023-09-28-11-48-09","order_date":"2023-09-28 11:48:09","customer_name":{"original_value":"Aggymart Supermarket","matched_value":{"Customer Code":"C-20008","BPNAME":"Aggymart Supermarket","Customer Type":"Supermarket"},"similarity":"99.256816414004"},"customer_email":"","customer_phone":"","delivery_location":"Aggymart Supermarket","delivery_date":"2023-09-28 11:48:09","seller_name":"","requested_by_name":"","requested_by_phone":"","requested_by_email":"","items":[{"original_value":{"name":"Blue band 1kg","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Pieces"}],"unit_price":"100","total_price":"10000","number_of_pieces":"100","number_of_cases":""},"matched_value":{"ItemName":"Blue Band Original-1kg","ItemCode":"UP00006","UPC":"12","Scale":"1","UOM":"Kg","PrincipalCode":"UP"},"similarity":"95.374464125392"},{"original_value":{"name":"Blue band 500g","unit_of_measure":[{"selling_quantity":"300","selling_unit":"Pieces"}],"unit_price":"300","total_price":"90000","number_of_pieces":"300","number_of_cases":""},"matched_value":{"ItemName":"Blue Band Original-500g","ItemCode":"UP00005","UPC":"24","Scale":"500","UOM":"Grams","PrincipalCode":"UP"},"similarity":"95.686883549906"},{"original_value":{"name":"Blue band 250g","unit_of_measure":[{"selling_quantity":"300","selling_unit":"Pieces"}],"unit_price":"300","total_price":"90000","number_of_pieces":"300","number_of_cases":""},"matched_value":{"ItemName":"Blue Band Original-250g","ItemCode":"UP00004","UPC":"48","Scale":"250","UOM":"Grams","PrincipalCode":"UP"},"similarity":"95.737936524197"},{"original_value":{"name":"Blu band 100g","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Pieces"}],"unit_price":"100","total_price":"10000","number_of_pieces":"100","number_of_cases":""},"matched_value":{"ItemName":"Blue Band Original-100g","ItemCode":"UP00003","UPC":"48","Scale":"100","UOM":"Grams","PrincipalCode":"UP"},"similarity":"92.372622912911"},{"original_value":{"name":"Ribena cordial 1ltr","unit_of_measure":[{"selling_quantity":"500","selling_unit":"Milliliters"}],"unit_price":"500","total_price":"250000","number_of_pieces":"","number_of_cases":""},"matched_value":{"ItemName":"Ribena Cordial 1l Pet Cordial","ItemCode":"SUN00036","UPC":"6","Scale":"1","UOM":"L","PrincipalCode":"SUN"},"similarity":"94.629886295685"},{"original_value":{"name":"Ribena cordial 500ml","unit_of_measure":[{"selling_quantity":"700","selling_unit":"Milliliters"}],"unit_price":"700","total_price":"490000","number_of_pieces":"","number_of_cases":""},"matched_value":{"ItemName":"Ribena Cordial 50cl Pet Cordial","ItemCode":"SUN00035","UPC":"12","Scale":"50","UOM":"Cl","PrincipalCode":"SUN"},"similarity":"92.621840295737"},{"original_value":{"name":"Ribena B 12*Ltr","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Liters"}],"unit_price":"100","total_price":"1200000","number_of_pieces":"","number_of_cases":""},"matched_value":{"ItemName":"Ribena Rtd Bc 1l Tet X12","ItemCode":"SUN00028","UPC":"12","Scale":"1","UOM":"L","PrincipalCode":"SUN"},"similarity":"91.85399415946"},{"original_value":{"name":"Ribena S 12*Ltr","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Liters"}],"unit_price":"100","total_price":"1200000","number_of_pieces":"","number_of_cases":""},"matched_value":{"ItemName":"Ribena Rtd Bc 1l Tet X12","ItemCode":"SUN00028","UPC":"12","Scale":"1","UOM":"L","PrincipalCode":"SUN"},"similarity":"92.523158966724"},{"original_value":{"name":"Lucazade 12*Ltr","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Liters"}],"unit_price":"100","total_price":"1200000","number_of_pieces":"","number_of_cases":""},"matched_value":{"ItemName":"Lucozade Boost Buzz 1l Pet X12","ItemCode":"SUN00020","UPC":"12","Scale":"1","UOM":"L","PrincipalCode":"SUN"},"similarity":"91.677946293234"}],"currency":"KES","total_amount":"6000000","customers_list":"","products_list":""}]', JSON_FORCE_OBJECT);

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

        print_r($data);

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
