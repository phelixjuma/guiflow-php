<?php

namespace PhelixJuma\GUIFlow\Tests\Utils;

use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PhelixJuma\GUIFlow\Utils\PathResolver;
use PHPUnit\Framework\TestCase;

class PathResolverTest extends TestCase
{
    public function _testGetArrayAtIndex()
    {

        $data = [
            "customers_list"=> [
                [
                    "shipping_locations" => ""
                ],
                [
                    "shipping_locations" => [
                        [
                            "name" => "Nairobi"
                        ]
                    ]
                ],
                [
                    "shipping_locations" => [
                        [
                            "name" => "Mombasa"
                        ]
                    ]
                ],
                [
                    "shipping_locations" => []
                ]
            ]
        ];
        // validate the config
        $response = PathResolver::getValueByPath($data, "customers_list.*.shipping_locations");

        print_r($response);

        //$this->assertTrue(ConfigurationValidator::validate($data));
    }

}
