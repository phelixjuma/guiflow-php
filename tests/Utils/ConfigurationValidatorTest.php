<?php

namespace PhelixJuma\DataTransformer\Tests\Utils;

use PhelixJuma\DataTransformer\Utils\ConfigurationValidator;
use PHPUnit\Framework\TestCase;

class ConfigurationValidatorTest extends TestCase
{
    public function testValidate()
    {
        $validator = new ConfigurationValidator();

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

        $this->assertTrue($validator->validate($data));
    }

}
