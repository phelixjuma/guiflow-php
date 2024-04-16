<?php

namespace PhelixJuma\GUIFlow\Tests\Utils;

use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PHPUnit\Framework\TestCase;

class ConfigurationValidatorTest extends TestCase
{
    public function _testValidate()
    {

        $config_json = file_get_contents(dirname(__DIR__) ."/config.json");
        $config = json_decode($config_json);

        // validate the config
        $valid = ConfigurationValidator::validate($config, 'v2');

        if ($valid) {
            print "\nValid\n";
        } else {
            print "\nInvalid\n";
        }

        //print_r($data);

        //$this->assertTrue(ConfigurationValidator::validate($data));
    }

}
