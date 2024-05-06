<?php

namespace PhelixJuma\GUIFlow\Tests\Utils;

use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PhelixJuma\GUIFlow\Utils\Schema;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    public function _testSubSchemas()
    {

        $subschemas = Schema::getGranularSchemas();

        print_r($subschemas);

        //print_r($data);

        //$this->assertTrue(ConfigurationValidator::validate($data));
    }

}
