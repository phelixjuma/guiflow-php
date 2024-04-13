<?php

namespace PhelixJuma\GUIFlow\Utils;

namespace PhelixJuma\GUIFlow\Utils;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;

class ConfigurationValidator
{

    public static function validate($data, $schemaVersion = null): bool
    {

        $schema = self::getSchema($schemaVersion);

        $validator = new Validator();

        $result = $validator->validate($data, $schema);

        if ($result->isValid()) {
            return true;
        } else {

            $errors = ((new ErrorFormatter())->format($result->error()));

            throw new \InvalidArgumentException(implode(', ', $errors));
        }
    }

    /**
     * @param $v
     * @return string
     */
    public static function getSchemaPath($v = null): string
    {

        if (!empty($v)) {
            $extension = ".$v.json";
        } else {
            $extension = ".json";
        }
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . "SchemaDefinitions/config-schema{$extension}";
    }

    public static function getSchema($v = null) {

        $schemaPath = self::getSchemaPath($v);

        return json_decode(file_get_contents($schemaPath));
    }
}
