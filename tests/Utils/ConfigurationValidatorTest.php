<?php

namespace PhelixJuma\GUIFlow\Tests\Utils;

use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PHPUnit\Framework\TestCase;

class ConfigurationValidatorTest extends TestCase
{
    public function _testValidate()
    {

        $data = json_decode('[
                  {
                    "rule": "Test Rule",
                    "condition": {
                     "path":"quantity",
                     "operator": "==",
                     "value":"bottles"
                    },
                    "actions": [
                      {
                        "action":"set",
                        "path":"",
                        "value":"",
                        "valueFromField":"",
                        "valueMapping":{},
                        "function":"",
                        "args":{},
                        "newField":""
                      }
                    ]
                  }
                ]');

        //print_r($data);

        $this->assertTrue(ConfigurationValidator::validate($data));
    }

}
