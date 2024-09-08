<?php

namespace PhelixJuma\GUIFlow\Tests\Utils;

use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PhelixJuma\GUIFlow\Utils\JsonSchemaResolver;
use PHPUnit\Framework\TestCase;

class ConfigurationValidatorTest extends TestCase
{
    public function _testSchemaResolver()
    {

        $resolvedSchema = JsonSchemaResolver::resolveSchema('v3');

        // Save all refs to a JSON file
        $jsonString = json_encode($resolvedSchema, JSON_PRETTY_PRINT);
        echo "\n$jsonString\n";

        $this->assertNotEmpty($resolvedSchema, "There should be \$ref occurrences in the schema");
    }

    public function _testSchemaComponents()
    {

        $components = ConfigurationValidator::getSchemaComponents('v3');

        // Save all refs to a JSON file
        //$jsonString = file_put_contents("schema-components.json", json_encode($components));
        //echo "\n$jsonString\n";

        //$this->assertNotEmpty($components, "There should be \$ref occurrences in the schema");
    }
}
