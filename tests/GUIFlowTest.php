<?php

namespace PhelixJuma\GUIFlow\Tests;

use PHPUnit\Framework\TestCase;
use PhelixJuma\GUIFlow\Workflow;
use Ramsey\Uuid\Uuid;

class GUIFlowTest extends TestCase
{

    /**
     * @throws \ReflectionException
     */
    public function testFullRulesSet()
    {
        $config_json = file_get_contents(__DIR__ ."/config.json");
        $config = json_decode($config_json);

        // validate the config

        $data = json_decode('[{"purchase_order_number":"PO-2023-09-28-11-48-09","order_date":"2023-09-28 11:48:09","customer_name":"Naivas","customer_email":"","customer_phone":"","delivery_location":"Aggymart Supermarket","delivery_date":"2023-09-28 11:48:09","seller_name":"","requested_by_name":"","requested_by_phone":"","requested_by_email":"","items":[{"original_value":{"name":"Blue band 1kg","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Pieces"}],"unit_price":"100","total_price":"10000"},"matched_value":{"ItemName":"Blue Band Original-1kg","ItemCode":"UP00006","UPC":"12","Scale":"1","UOM":"Kg"},"similarity":"95.374464125392"},{"original_value":{"name":"Blue band 500g","unit_of_measure":[{"selling_quantity":"300","selling_unit":"Pieces"}],"unit_price":"300","total_price":"90000"},"matched_value":{"ItemName":"Blue Band Original-500g","ItemCode":"UP00005","UPC":"24","Scale":"500","UOM":"Grams"},"similarity":"95.686883549906"},{"original_value":{"name":"Blue band 250g","unit_of_measure":[{"selling_quantity":"300","selling_unit":"Pieces"}],"unit_price":"300","total_price":"90000"},"matched_value":{"ItemName":"Blue Band Original-250g","ItemCode":"UP00004","UPC":"48","Scale":"250","UOM":"Grams"},"similarity":"95.737936524197"},{"original_value":{"name":"Blu band 100g","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Pieces"}],"unit_price":"100","total_price":"10000"},"matched_value":{"ItemName":"Blue Band Original-100g","ItemCode":"UP00003","UPC":"48","Scale":"100","UOM":"Grams"},"similarity":"92.372622912911"},{"original_value":{"name":"Ribena cordial 1ltr","unit_of_measure":[{"selling_quantity":"500","selling_unit":"Milliliters"}],"unit_price":"500","total_price":"250000"},"matched_value":{"ItemName":"Ribena Cordial 1l Pet Cordial","ItemCode":"SUN00036","UPC":"6","Scale":"1","UOM":"L"},"similarity":"94.629886295685"},{"original_value":{"name":"Ribena cordial 500ml","unit_of_measure":[{"selling_quantity":"700","selling_unit":"Milliliters"}],"unit_price":"700","total_price":"490000"},"matched_value":{"ItemName":"Ribena Cordial 50cl Pet Cordial","ItemCode":"SUN00035","UPC":"12","Scale":"50","UOM":"Cl"},"similarity":"92.621840295737"},{"original_value":{"name":"Ribena B 12*Ltr","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Liters"}],"unit_price":"100","total_price":"1200000"},"matched_value":{"ItemName":"Ribena Rtd Bc 1l Tet X12","ItemCode":"SUN00028","UPC":"12","Scale":"1","UOM":"L"},"similarity":"91.85399415946"},{"original_value":{"name":"Ribena S 12*Ltr","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Liters"}],"unit_price":"100","total_price":"1200000"},"matched_value":{"ItemName":"Ribena Rtd Bc 1l Tet X12","ItemCode":"SUN00028","UPC":"12","Scale":"1","UOM":"L"},"similarity":"92.523158966724"},{"original_value":{"name":"Lucazade 12*Ltr","unit_of_measure":[{"selling_quantity":"100","selling_unit":"Liters"}],"unit_price":"100","total_price":"1200000"},"matched_value":{"ItemName":"Lucozade Boost Buzz 1l Pet X12","ItemCode":"SUN00020","UPC":"12","Scale":"1","UOM":"L"},"similarity":"91.677946293234"}],"currency":"KES","total_amount":"6000000"}]', JSON_FORCE_OBJECT);
        //print "\nBefore\n";
        //print(json_encode($data));

        $observability = [
            'backend'   => 'redis',
            'config'    => [
                'relational'    => [
                    'dbname' => 'observability',
                    'user' => 'root',
                    'password' => 'password',
                    'host' => '127.0.0.1',
                    'driver' => 'pdo_mysql',
                ],
                'redis' => [
                    'scheme' => 'tcp',
                    'host' => '127.0.0.1',
                    'port' => 6379,
                ],
                'dynamodb' => [
                    'region' => 'your-region',
                    'version' => 'latest',
                    'credentials' => [
                        'key' => 'your-access-key',
                        'secret' => 'your-secret-key',
                    ],
                ]
            ]
        ];

        $workflowId = Uuid::uuid4()->toString();

        $workflow = new Workflow($workflowId, $config, $this, $observability['backend'], $observability['config']);

        $workflowExecutionId = $workflow->run($data, true);

        print "\nWorkflow Execution Id {$workflowExecutionId}:\n";
        //print_r($workflow->workflowExecutor->getResults()[0]->getExecutionTime());

        print "\nWorkflow Executions:\n";
        print_r($workflow->getExecutionById($workflowExecutionId));

        if (!empty($workflow->errors)) {
            print "\nFound errors:\n";
            //print_r($workflow->errors);
        } else {
            //print "\nAfter\n";
            //print_r($data);
        }

        //print_r($data);

        //$this->assertEquals($expectedData, $data);
    }

    /**
     * @param $type
     * @return array[]
     */
    public function get_master_data($type) {
        return match ($type) {
            "customers" => $this->getCustomersList(),
            default => []
        };
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
