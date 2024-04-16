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
            "persons"=> [
            [
                "name" => "Phelix"
            ],
            [
                "name" => "Juma"
            ]]
        ];
        // validate the config
        $response = PathResolver::getValueByPath($data, "persons.0");

        print_r($response);

        //$this->assertTrue(ConfigurationValidator::validate($data));
    }

}
